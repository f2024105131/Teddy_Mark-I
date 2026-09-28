<?php

namespace App\Controllers\Api;

use App\Core\Controller;

/**
 * Api\GlobalSearchController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Unified search endpoint for the header search bar.
 *          Searches across multiple entities in parallel and
 *          returns grouped JSON results.
 *
 * Searchable entities:
 *   • Customers            (name, email, phone)
 *   • Orders               (order_number, customer)
 *   • Staff                (name, email, phone)      [admin only]
 *   • Items                (item_name, category)
 *   • Services             (service_name, description)
 *   • Complaints           (complaint_number, description)
 *   • Pickup Requests      (request_number, customer)
 *   • Deliveries           (order_number, customer)
 *
 * Role rules:
 *   • admin  → searches everything
 *   • staff  → operational entities only
 *              (no staff directory, no financial data)
 *   • customer → NOT allowed (returns 403)
 *
 * Response shape (all endpoints return this envelope):
 *   {
 *     "success": true,
 *     "data": {
 *       "query":       "shirt",
 *       "total":       12,
 *       "categories":  { "customers": [...], "orders": [...] },
 *       "took_ms":     8
 *     }
 *   }
 *
 * Notes:
 *   • Uses raw SQL — no dependency on models.
 *   • All values are bound via prepared statements.
 *   • LIKE wildcards in user input are escaped.
 *   • Results are capped per category for performance.
 * ----------------------------------------------------------
 */
class GlobalSearchController extends Controller
{
    /** Minimum query length to trigger a search */
    private const MIN_QUERY_LENGTH = 2;

    /** Maximum query length accepted */
    private const MAX_QUERY_LENGTH = 100;

    /** Default number of results per category */
    private const PER_CATEGORY_LIMIT = 5;

    /** Allowed categories to filter on (via ?category=...) */
    private const CATEGORIES = [
        'customers',
        'orders',
        'staff',
        'items',
        'services',
        'complaints',
        'pickups',
        'deliveries',
    ];

    /** Which categories staff (non-admin) may search */
    private const STAFF_ALLOWED = [
        'customers',
        'orders',
        'items',
        'services',
        'complaints',
        'pickups',
        'deliveries',
    ];

    // =========================================================
    //  MAIN ENDPOINT
    // =========================================================
    public function search(): void
    {
        // API expects JSON responses for all paths, even errors
        $started = microtime(true);

        // ---- Auth check ----
        $staffId   = (int) ($_SESSION['staff_id']   ?? 0);
        $staffRole =     ($_SESSION['staff_role']   ?? '');

        if ($staffId <= 0) {
            $this->jsonError('Unauthorized. Please log in.', 401);
            return;
        }

        if (!in_array($staffRole, ['admin', 'staff'], true)) {
            $this->jsonError('Forbidden.', 403);
            return;
        }

        // ---- Query input ----
        $query = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));

        if ($query === '') {
            $this->jsonSuccess([
                'query'      => '',
                'total'      => 0,
                'categories' => [],
                'took_ms'    => 0,
            ]);
            return;
        }

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            $this->jsonError(
                'Query must be at least ' . self::MIN_QUERY_LENGTH . ' characters.',
                422
            );
            return;
        }

        if (mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            $query = mb_substr($query, 0, self::MAX_QUERY_LENGTH);
        }

        // ---- Optional category filter ----
        $requestedCategory = trim((string) ($_GET['category'] ?? ''));
        $limit             = min(
            25,
            max(1, (int) ($_GET['limit'] ?? self::PER_CATEGORY_LIMIT))
        );

        $allowed = $staffRole === 'admin' ? self::CATEGORIES : self::STAFF_ALLOWED;

        if ($requestedCategory !== '') {
            if (!in_array($requestedCategory, $allowed, true)) {
                $this->jsonError('Unknown or unauthorized category.', 422);
                return;
            }
            $allowed = [$requestedCategory];
        }

        // ---- Prepared LIKE pattern (escaped) ----
        $like = $this->buildLike($query);

        // ---- Run each category ----
        $results = [];
        $total   = 0;

        foreach ($allowed as $category) {
            $method = 'search' . ucfirst($category);
            if (!method_exists($this, $method)) {
                continue;
            }

            try {
                $rows = $this->{$method}($like, $limit);
            } catch (\Throwable $e) {
                error_log("[GlobalSearch::{$category}] " . $e->getMessage());
                $rows = [];
            }

            if (empty($rows)) {
                continue;
            }

            $results[$category] = $rows;
            $total             += count($rows);
        }

        $tookMs = (int) round((microtime(true) - $started) * 1000);

        $this->jsonSuccess([
            'query'      => $query,
            'total'      => $total,
            'categories' => $results,
            'took_ms'    => $tookMs,
        ]);
    }

    // =========================================================
    //  CATEGORY SEARCHERS
    // =========================================================

    private function searchCustomers(string $like, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT customer_id AS id,
                    full_name    AS title,
                    email        AS subtitle,
                    phone        AS meta,
                    account_status AS badge,
                    city         AS meta2
             FROM customer
             WHERE full_name LIKE :like
                OR email     LIKE :like
                OR phone     LIKE :like
             ORDER BY
                CASE
                    WHEN full_name LIKE :like_prefix THEN 0
                    WHEN email     LIKE :like_prefix THEN 1
                    ELSE 2
                END,
                full_name ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':like',        $like);
        $stmt->bindValue(':like_prefix', $this->buildLikePrefix($like));
        $stmt->bindValue(':lim',         $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'title'    => $r['title'],
            'subtitle' => $r['subtitle'],
            'meta'     => $r['meta'],
            'meta2'    => $r['meta2'],
            'badge'    => $r['badge'],
            'url'      => '/admin/customers/' . (int) $r['id'],
            'icon'     => 'bi-person',
        ], $rows);
    }

    private function searchOrders(string $like, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.order_id AS id,
                    o.order_number AS title,
                    c.full_name   AS subtitle,
                    o.order_status AS badge,
                    o.order_date  AS meta,
                    o.total_amount AS amount
             FROM orders o
             JOIN customer c ON c.customer_id = o.customer_id
             WHERE o.order_number LIKE :like
                OR c.full_name     LIKE :like
                OR c.phone         LIKE :like
             ORDER BY
                CASE
                    WHEN o.order_number LIKE :like_prefix THEN 0
                    ELSE 1
                END,
                o.order_date DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':like',        $like);
        $stmt->bindValue(':like_prefix', $this->buildLikePrefix($like));
        $stmt->bindValue(':lim',         $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'title'    => $r['title'],
            'subtitle' => $r['subtitle'] ?? '',
            'meta'     => $r['meta'],
            'meta2'    => null,
            'badge'    => $r['badge'],
            'url'      => '/admin/orders/' . (int) $r['id'],
            'icon'     => 'bi-bag-check',
        ], $rows);
    }

    private function searchStaff(string $like, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT staff_id  AS id,
                    full_name AS title,
                    email     AS subtitle,
                    phone     AS meta,
                    role      AS badge,
                    is_active AS meta2
             FROM staff
             WHERE full_name LIKE :like
                OR email     LIKE :like
                OR phone     LIKE :like
             ORDER BY
                CASE
                    WHEN full_name LIKE :like_prefix THEN 0
                    ELSE 1
                END,
                is_active DESC,
                full_name ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':like',        $like);
        $stmt->bindValue(':like_prefix', $this->buildLikePrefix($like));
        $stmt->bindValue(':lim',         $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'title'    => $r['title'],
            'subtitle' => $r['subtitle'],
            'meta'     => $r['meta'],
            'meta2'    => ((int) $r['meta2']) ? 'Active' : 'Inactive',
            'badge'    => $r['badge'],
            'url'      => '/admin/staff/' . (int) $r['id'],
            'icon'     => 'bi-person-badge',
        ], $rows);
    }

    private function searchItems(string $like, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT it.item_id   AS id,
                    it.item_name AS title,
                    ic.category_name AS subtitle,
                    it.is_active AS badge,
                    NULL         AS meta,
                    NULL         AS meta2
             FROM item_type it
             JOIN item_category ic ON ic.category_id = it.category_id
             WHERE it.item_name      LIKE :like
                OR ic.category_name  LIKE :like
             ORDER BY
                CASE
                    WHEN it.item_name LIKE :like_prefix THEN 0
                    ELSE 1
                END,
                it.item_name ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':like',        $like);
        $stmt->bindValue(':like_prefix', $this->buildLikePrefix($like));
        $stmt->bindValue(':lim',         $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'title'    => $r['title'],
            'subtitle' => $r['subtitle'],
            'meta'     => null,
            'meta2'    => null,
            'badge'    => ((int) $r['badge']) ? 'active' : 'inactive',
            'url'      => '/admin/catalog/items/' . (int) $r['id'] . '/edit',
            'icon'     => 'bi-basket',
        ], $rows);
    }

    private function searchServices(string $like, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT service_id   AS id,
                    service_name AS title,
                    description  AS subtitle,
                    service_type AS badge,
                    duration_hours AS meta,
                    duration_days  AS meta2
             FROM service
             WHERE service_name LIKE :like
                OR description  LIKE :like
             ORDER BY
                CASE
                    WHEN service_name LIKE :like_prefix THEN 0
                    ELSE 1
                END,
                service_name ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':like',        $like);
        $stmt->bindValue(':like_prefix', $this->buildLikePrefix($like));
        $stmt->bindValue(':lim',         $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'title'    => $r['title'],
            'subtitle' => $r['subtitle'] ?? '',
            'meta'     => null,
            'meta2'    => null,
            'badge'    => $r['badge'],
            'url'      => '/admin/catalog/services/' . (int) $r['id'],
            'icon'     => 'bi-tag',
        ], $rows);
    }

    private function searchComplaints(string $like, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT cm.complaint_id     AS id,
                    cm.complaint_number AS title,
                    c.full_name         AS subtitle,
                    cm.status           AS badge,
                    cm.type             AS meta,
                    cm.created_at       AS meta2
             FROM complaint cm
             JOIN customer c ON c.customer_id = cm.customer_id
             JOIN orders   o ON o.order_id    = cm.order_id
             WHERE cm.complaint_number LIKE :like
                OR c.full_name         LIKE :like
                OR o.order_number      LIKE :like
                OR cm.description      LIKE :like
             ORDER BY
                CASE
                    WHEN cm.complaint_number LIKE :like_prefix THEN 0
                    ELSE 1
                END,
                cm.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':like',        $like);
        $stmt->bindValue(':like_prefix', $this->buildLikePrefix($like));
        $stmt->bindValue(':lim',         $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'title'    => $r['title'],
            'subtitle' => $r['subtitle'],
            'meta'     => $r['meta'],
            'meta2'    => $r['meta2'],
            'badge'    => $r['badge'],
            'url'      => '/admin/complaints/' . (int) $r['id'],
            'icon'     => 'bi-exclamation-circle',
        ], $rows);
    }

    private function searchPickups(string $like, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT pr.pickup_id     AS id,
                    pr.request_number AS title,
                    c.full_name       AS subtitle,
                    pr.status         AS badge,
                    pr.pickup_date    AS meta,
                    c.phone           AS meta2
             FROM pickup_request pr
             JOIN customer c ON c.customer_id = pr.customer_id
             WHERE pr.request_number LIKE :like
                OR c.full_name       LIKE :like
                OR c.phone           LIKE :like
             ORDER BY
                CASE
                    WHEN pr.request_number LIKE :like_prefix THEN 0
                    ELSE 1
                END,
                pr.pickup_date DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':like',        $like);
        $stmt->bindValue(':like_prefix', $this->buildLikePrefix($like));
        $stmt->bindValue(':lim',         $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'title'    => $r['title'],
            'subtitle' => $r['subtitle'],
            'meta'     => $r['meta'],
            'meta2'    => $r['meta2'],
            'badge'    => $r['badge'],
            'url'      => '/admin/pickups/monitor?highlight=' . (int) $r['id'],
            'icon'     => 'bi-box-seam',
        ], $rows);
    }

    private function searchDeliveries(string $like, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.delivery_id   AS id,
                    o.order_number  AS title,
                    c.full_name     AS subtitle,
                    d.status        AS badge,
                    d.delivery_date AS meta,
                    c.phone         AS meta2
             FROM delivery d
             JOIN orders   o ON o.order_id    = d.order_id
             JOIN customer c ON c.customer_id = o.customer_id
             WHERE o.order_number LIKE :like
                OR c.full_name    LIKE :like
                OR c.phone        LIKE :like
             ORDER BY
                CASE
                    WHEN o.order_number LIKE :like_prefix THEN 0
                    ELSE 1
                END,
                d.delivery_date DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':like',        $like);
        $stmt->bindValue(':like_prefix', $this->buildLikePrefix($like));
        $stmt->bindValue(':lim',         $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'title'    => $r['title'],
            'subtitle' => $r['subtitle'],
            'meta'     => $r['meta'],
            'meta2'    => $r['meta2'],
            'badge'    => $r['badge'],
            'url'      => '/admin/pickups/monitor?tab=deliveries&highlight=' . (int) $r['id'],
            'icon'     => 'bi-truck',
        ], $rows);
    }

    // =========================================================
    //  UTILITIES
    // =========================================================

    /**
     * Build a safe LIKE pattern: %query%
     * Escapes SQL LIKE wildcards (% _ \) in user input.
     */
    private function buildLike(string $query): string
    {
        return '%' . $this->escapeLike($query) . '%';
    }

    /**
     * Build a prefix-only LIKE pattern: query%
     * Used for ranking (prefix matches rank higher).
     */
    private function buildLikePrefix(string $like): string
    {
        // $like is already "%escaped%", convert to "escaped%"
        return ltrim($like, '%') === '' ? '%' : substr($like, 1);
    }

    /**
     * Escape special LIKE characters in user input.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
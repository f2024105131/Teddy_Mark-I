<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * ItemCategory Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : item_category  (PK: category_id)
 * Purpose: Catalog master data for laundry item categories
 *          (Men's Wear, Women's Wear, Kids Wear, Household).
 *
 * Used by:
 *   • Admin\ItemController          (CRUD)
 *   • Admin\ServiceController       (pricing matrix grouping)
 *   • Admin\ReportController        (item popularity by category)
 *   • Customer portal item picker   (options dropdown)
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → raw queries used
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class ItemCategory extends Model
{
    protected string $table      = 'item_category';
    protected string $primaryKey = 'category_id';

    // =========================================================
    //  BASIC LOOKUPS
    // =========================================================

    /**
     * All active categories, ordered by name.
     * Used by customer-facing pickers + admin forms.
     */
    public function allActive(): array
    {
        return $this->db
            ->query("SELECT * FROM {$this->table}
                     WHERE is_active = 1
                     ORDER BY category_name ASC")
            ->fetchAll();
    }

    /**
     * All categories (active + inactive) for admin listing.
     */
    public function allOrdered(): array
    {
        return $this->db
            ->query("SELECT * FROM {$this->table}
                     ORDER BY is_active DESC, category_name ASC")
            ->fetchAll();
    }

    /**
     * Find a category by name (case-insensitive).
     */
    public function findByName(string $name): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE LOWER(category_name) = LOWER(:n)
             LIMIT 1"
        );
        $stmt->execute(['n' => trim($name)]);
        return $stmt->fetch();
    }

    // =========================================================
    //  UNIQUENESS / USAGE GUARDS
    // =========================================================

    /**
     * Check if a category name already exists.
     * Pass $excludeId on edit to ignore the current row.
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE LOWER(category_name) = LOWER(:n)";
        $params = ['n' => trim($name)];

        if ($excludeId !== null) {
            $sql .= " AND category_id <> :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Guard against deleting a category that has items.
     */
    public function hasItems(int $categoryId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM item_type
             WHERE category_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $categoryId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Total items in a category (all states).
     */
    public function itemsCount(int $categoryId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM item_type WHERE category_id = :id"
        );
        $stmt->execute(['id' => $categoryId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Active items in a category.
     */
    public function activeItemsCount(int $categoryId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM item_type
             WHERE category_id = :id AND is_active = 1"
        );
        $stmt->execute(['id' => $categoryId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Compound delete check — returns reason string or null if deletable.
     */
    public function deletionBlockReason(int $categoryId): ?string
    {
        $count = $this->itemsCount($categoryId);
        if ($count > 0) {
            return "This category has {$count} item(s). Move or delete them first, or deactivate the category.";
        }
        return null;
    }

    // =========================================================
    //  ADMIN LISTINGS WITH COUNTS
    // =========================================================

    /**
     * Full listing with attached item counts + search + status filter.
     * Used by Admin\ItemController::categories().
     */
    public function allWithCounts(?string $search = null, string $status = ''): array
    {
        $sql = "SELECT ic.*,
                       (SELECT COUNT(*) FROM item_type it
                          WHERE it.category_id = ic.category_id) AS items_count,
                       (SELECT COUNT(*) FROM item_type it
                          WHERE it.category_id = ic.category_id
                            AND it.is_active = 1) AS active_items_count,
                       (SELECT COUNT(*) FROM service_pricing sp
                          JOIN item_type it ON it.item_id = sp.item_id
                         WHERE it.category_id = ic.category_id) AS pricing_count
                FROM {$this->table} ic
                WHERE 1 = 1";

        $params = [];

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND ic.category_name LIKE :search";
            $params['search'] = '%' . trim($search) . '%';
        }

        if ($status === 'active') {
            $sql .= " AND ic.is_active = 1";
        } elseif ($status === 'inactive') {
            $sql .= " AND ic.is_active = 0";
        }

        $sql .= " ORDER BY ic.is_active DESC, ic.category_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Single category with counts.
     */
    public function findWithCounts(int $categoryId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT ic.*,
                    (SELECT COUNT(*) FROM item_type it
                       WHERE it.category_id = ic.category_id) AS items_count,
                    (SELECT COUNT(*) FROM item_type it
                       WHERE it.category_id = ic.category_id
                         AND it.is_active = 1) AS active_items_count,
                    (SELECT COUNT(*) FROM service_pricing sp
                       JOIN item_type it ON it.item_id = sp.item_id
                      WHERE it.category_id = ic.category_id) AS pricing_count
             FROM {$this->table} ic
             WHERE ic.category_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $categoryId]);
        return $stmt->fetch();
    }

    // =========================================================
    //  STATE HELPERS
    // =========================================================

    /**
     * Guarded toggle of is_active. Returns the new state.
     * Throws if the row doesn't exist.
     */
    public function toggleActive(int $categoryId): bool
    {
        $current = $this->find($categoryId);
        if (!$current) {
            throw new \RuntimeException("Item category {$categoryId} not found");
        }

        $newState = !((bool) $current['is_active']);

        $this->update($categoryId, [
            'is_active' => $newState ? 1 : 0,
        ]);

        return $newState;
    }

    // =========================================================
    //  REPORTING / DASHBOARD
    // =========================================================

    /**
     * Summary stats used by admin catalog page.
     */
    public function stats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                (SELECT COUNT(*) FROM item_type) AS total_items
             FROM {$this->table}"
        )->fetch() ?: [];

        return [
            'total'       => (int) ($row['total']       ?? 0),
            'active'      => (int) ($row['active']      ?? 0),
            'inactive'    => (int) ($row['inactive']    ?? 0),
            'total_items' => (int) ($row['total_items'] ?? 0),
        ];
    }

    /**
     * Categories ranked by revenue within a date range
     * (from order_item joins). Used by Admin\ReportController.
     */
    public function topByRevenue(string $from, string $to, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT ic.category_id, ic.category_name,
                    COUNT(DISTINCT oi.item_id) AS items_count,
                    COALESCE(SUM(oi.quantity), 0)     AS total_quantity,
                    COALESCE(SUM(oi.total_price), 0)  AS revenue
             FROM {$this->table} ic
             JOIN item_type it ON it.category_id = ic.category_id
             JOIN order_item oi ON oi.item_id = it.item_id
             JOIN orders o ON o.order_id = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to
             GROUP BY ic.category_id, ic.category_name
             ORDER BY revenue DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to',   $to);
        $stmt->bindValue(':lim',  $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Categories with zero items (idle categories).
     * Useful for admin cleanup.
     */
    public function unused(): array
    {
        return $this->db
            ->query(
                "SELECT ic.*
                 FROM {$this->table} ic
                 LEFT JOIN item_type it ON it.category_id = ic.category_id
                 WHERE it.item_id IS NULL
                 ORDER BY ic.category_name ASC"
            )
            ->fetchAll();
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    /**
     * Select list for dropdowns: category_id => category_name.
     */
    public function options(bool $activeOnly = true): array
    {
        $sql = "SELECT category_id, category_name
                FROM {$this->table}";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY category_name ASC";

        $rows = $this->db->query($sql)->fetchAll();

        $options = [];
        foreach ($rows as $r) {
            $options[(int) $r['category_id']] = $r['category_name'];
        }
        return $options;
    }

    /**
     * Human-readable label (defensive against underscores).
     */
    public static function label(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    /**
     * CSS badge class for active state.
     */
    public static function statusBadge(int $isActive): string
    {
        return $isActive ? 'badge-success' : 'badge-secondary';
    }

    /**
     * Pluralized human label for item count (used in views).
     */
    public static function itemCountLabel(int $count): string
    {
        if ($count === 0) {
            return 'No items';
        }
        if ($count === 1) {
            return '1 item';
        }
        return "{$count} items";
    }
}
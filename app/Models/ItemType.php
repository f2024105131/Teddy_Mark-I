<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * ItemType Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : item_type  (PK: item_id)
 * Purpose: Catalog master data for laundry items (Shirt, Pant,
 *          Saree, Blanket, ...). Every item belongs to exactly
 *          one item_category via FK.
 *
 * Unique constraint: uk_category_item (category_id, item_name)
 *   → an item name must be unique WITHIN its category, but
 *     the same name can exist in different categories.
 *
 * Used by:
 *   • Admin\ItemController          (CRUD)
 *   • Admin\ServiceController       (pricing matrix rows)
 *   • Admin\ReportController        (item popularity)
 *   • Customer portal item picker   (cascading dropdowns)
 *   • BillingService (Shehreen)     (validate order items)
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → raw queries used
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class ItemType extends Model
{
    protected string $table      = 'item_type';
    protected string $primaryKey = 'item_id';

    // =========================================================
    //  BASIC LOOKUPS
    // =========================================================

    /**
     * All active items with their parent category name.
     * Used by customer-facing pickers + admin reports.
     */
    public function allWithCategory(bool $activeOnly = true): array
    {
        $sql = "SELECT it.*, ic.category_name
                FROM {$this->table} it
                JOIN item_category ic ON ic.category_id = it.category_id";

        if ($activeOnly) {
            $sql .= " WHERE it.is_active = 1 AND ic.is_active = 1";
        }

        $sql .= " ORDER BY ic.category_name ASC, it.item_name ASC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * All items in a specific category.
     */
    public function byCategory(int $categoryId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE category_id = :cid";

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY item_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cid' => $categoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Find a specific item by name within its category.
     * (Matches the uk_category_item unique constraint semantics.)
     */
    public function findByNameInCategory(string $itemName, int $categoryId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE LOWER(item_name) = LOWER(:n)
               AND category_id = :cid
             LIMIT 1"
        );
        $stmt->execute(['n' => trim($itemName), 'cid' => $categoryId]);
        return $stmt->fetch();
    }

    /**
     * Find a single item with its category name attached.
     */
    public function findWithCategory(int $itemId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT it.*, ic.category_name, ic.is_active AS category_is_active
             FROM {$this->table} it
             JOIN item_category ic ON ic.category_id = it.category_id
             WHERE it.item_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $itemId]);
        return $stmt->fetch();
    }

    // =========================================================
    //  UNIQUENESS / USAGE GUARDS
    // =========================================================

    /**
     * Check if an item name already exists within a category.
     * Pass $excludeId on edit to ignore the current row.
     * (Case-insensitive — matches the schema's ci collation.)
     */
    public function nameExistsInCategory(
        string $itemName,
        int $categoryId,
        ?int $excludeId = null
    ): bool {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE LOWER(item_name) = LOWER(:n)
                  AND category_id = :cid";
        $params = [
            'n'   => trim($itemName),
            'cid' => $categoryId,
        ];

        if ($excludeId !== null) {
            $sql .= " AND item_id <> :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Simple "is this item in use" check — used before delete.
     * Returns true if the item appears in ANY order_item OR
     * has any service_pricing rows.
     */
    public function isInUse(int $itemId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM order_item   WHERE item_id = :id
             UNION
             SELECT 1 FROM service_pricing WHERE item_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $itemId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Count of order_item rows referencing this item.
     */
    public function ordersCount(int $itemId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM order_item WHERE item_id = :id"
        );
        $stmt->execute(['id' => $itemId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count of service_pricing rows referencing this item.
     */
    public function pricingCount(int $itemId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM service_pricing WHERE item_id = :id"
        );
        $stmt->execute(['id' => $itemId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Compound delete check — returns a human-readable reason
     * string if the item cannot be deleted, or null if deletable.
     */
    public function deletionBlockReason(int $itemId): ?string
    {
        $orderCount = $this->ordersCount($itemId);
        if ($orderCount > 0) {
            return "This item appears in {$orderCount} order(s). Deactivate it instead to preserve order history.";
        }

        $pricingCount = $this->pricingCount($itemId);
        if ($pricingCount > 0) {
            return "This item has {$pricingCount} pricing row(s). Remove pricing first, or deactivate the item.";
        }

        return null;
    }

    // =========================================================
    //  ADMIN LISTINGS WITH COUNTS
    // =========================================================

    /**
     * Full listing with attached counts + search + filters.
     * Used by Admin\ItemController::index().
     */
    public function allWithCounts(
        ?string $search = null,
        int $categoryId = 0,
        string $status = ''
    ): array {
        $sql = "SELECT it.item_id, it.item_name, it.description,
                       it.is_active, it.created_at, it.updated_at,
                       ic.category_id, ic.category_name,
                       (SELECT COUNT(*) FROM service_pricing sp
                          WHERE sp.item_id = it.item_id) AS pricing_count,
                       (SELECT COUNT(*) FROM order_item oi
                          WHERE oi.item_id = it.item_id) AS orders_count
                FROM {$this->table} it
                JOIN item_category ic ON ic.category_id = it.category_id
                WHERE 1 = 1";

        $params = [];

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (it.item_name LIKE :search
                        OR ic.category_name LIKE :search)";
            $params['search'] = '%' . trim($search) . '%';
        }

        if ($categoryId > 0) {
            $sql .= " AND it.category_id = :cid";
            $params['cid'] = $categoryId;
        }

        if ($status === 'active') {
            $sql .= " AND it.is_active = 1";
        } elseif ($status === 'inactive') {
            $sql .= " AND it.is_active = 0";
        }

        $sql .= " ORDER BY ic.category_name ASC, it.item_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Single item with counts.
     */
    public function findWithCounts(int $itemId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT it.*,
                    ic.category_name,
                    (SELECT COUNT(*) FROM service_pricing sp
                       WHERE sp.item_id = it.item_id) AS pricing_count,
                    (SELECT COUNT(*) FROM order_item oi
                       WHERE oi.item_id = it.item_id) AS orders_count
             FROM {$this->table} it
             JOIN item_category ic ON ic.category_id = it.category_id
             WHERE it.item_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $itemId]);
        return $stmt->fetch();
    }

    // =========================================================
    //  STATE HELPERS
    // =========================================================

    /**
     * Guarded toggle of is_active. Returns the new state.
     * Throws if the row doesn't exist.
     */
    public function toggleActive(int $itemId): bool
    {
        $current = $this->find($itemId);
        if (!$current) {
            throw new \RuntimeException("Item type {$itemId} not found");
        }

        $newState = !((bool) $current['is_active']);

        $this->update($itemId, [
            'is_active' => $newState ? 1 : 0,
        ]);

        return $newState;
    }

    /**
     * Cascade deactivation of pricing rows when deactivating an item.
     * Used optionally by Admin\ItemController::toggle().
     * Returns number of pricing rows affected.
     */
    public function deactivatePricing(int $itemId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE service_pricing
             SET is_active = 0, updated_at = CURRENT_TIMESTAMP
             WHERE item_id = :id AND is_active = 1"
        );
        $stmt->execute(['id' => $itemId]);
        return $stmt->rowCount();
    }

    // =========================================================
    //  REPORTING / DASHBOARD
    // =========================================================

    /**
     * Summary stats for admin catalog page.
     */
    public function stats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                (SELECT COUNT(*) FROM service_pricing) AS pricing_rows,
                (SELECT COUNT(DISTINCT item_id) FROM service_pricing
                  WHERE is_active = 1) AS items_with_pricing
             FROM {$this->table}"
        )->fetch() ?: [];

        return [
            'total'              => (int) ($row['total']              ?? 0),
            'active'             => (int) ($row['active']             ?? 0),
            'inactive'           => (int) ($row['inactive']           ?? 0),
            'pricing_rows'       => (int) ($row['pricing_rows']       ?? 0),
            'items_with_pricing' => (int) ($row['items_with_pricing'] ?? 0),
        ];
    }

    /**
     * Top items by revenue over a date range.
     * Used by Admin\ReportController::items().
     */
    public function topByRevenue(string $from, string $to, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT it.item_id, it.item_name,
                    ic.category_name,
                    SUM(oi.quantity)     AS total_quantity,
                    SUM(oi.total_price)  AS revenue,
                    COUNT(DISTINCT oi.order_id) AS orders_count
             FROM {$this->table} it
             JOIN item_category ic ON ic.category_id = it.category_id
             JOIN order_item    oi ON oi.item_id    = it.item_id
             JOIN orders        o  ON o.order_id    = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to
             GROUP BY it.item_id, it.item_name, ic.category_name
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
     * Items that have no pricing rows yet (incomplete catalog).
     * Useful for admin cleanup widget.
     */
    public function withoutPricing(bool $activeOnly = true): array
    {
        $sql = "SELECT it.item_id, it.item_name,
                       ic.category_name
                FROM {$this->table} it
                JOIN item_category ic ON ic.category_id = it.category_id
                LEFT JOIN service_pricing sp ON sp.item_id = it.item_id
                WHERE sp.pricing_id IS NULL";

        if ($activeOnly) {
            $sql .= " AND it.is_active = 1";
        }

        $sql .= " ORDER BY ic.category_name ASC, it.item_name ASC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Item popularity by category (aggregate).
     */
    public function revenueByCategory(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT ic.category_id, ic.category_name,
                    COUNT(DISTINCT it.item_id)  AS items_count,
                    COALESCE(SUM(oi.quantity), 0)    AS total_quantity,
                    COALESCE(SUM(oi.total_price), 0) AS revenue
             FROM item_category ic
             JOIN {$this->table} it ON it.category_id = ic.category_id
             JOIN order_item oi ON oi.item_id = it.item_id
             JOIN orders o ON o.order_id = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to
             GROUP BY ic.category_id, ic.category_name
             ORDER BY revenue DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    /**
     * Select list for dropdowns: item_id => item_name.
     * Optional category filter.
     */
    public function options(bool $activeOnly = true, int $categoryId = 0): array
    {
        $sql = "SELECT item_id, item_name
                FROM {$this->table}
                WHERE 1 = 1";

        $params = [];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        if ($categoryId > 0) {
            $sql .= " AND category_id = :cid";
            $params['cid'] = $categoryId;
        }

        $sql .= " ORDER BY item_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $options = [];
        foreach ($rows as $r) {
            $options[(int) $r['item_id']] = $r['item_name'];
        }
        return $options;
    }

    /**
     * Grouped options for cascading dropdowns:
     *   [ category_id => [ item_id => item_name, ... ], ... ]
     * Perfect for building <optgroup> in <select>.
     */
    public function groupedOptions(bool $activeOnly = true): array
    {
        $sql = "SELECT it.item_id, it.item_name,
                       ic.category_id, ic.category_name
                FROM {$this->table} it
                JOIN item_category ic ON ic.category_id = it.category_id";

        if ($activeOnly) {
            $sql .= " WHERE it.is_active = 1 AND ic.is_active = 1";
        }

        $sql .= " ORDER BY ic.category_name ASC, it.item_name ASC";

        $rows = $this->db->query($sql)->fetchAll();

        $grouped = [];
        foreach ($rows as $r) {
            $cid = (int) $r['category_id'];
            if (!isset($grouped[$cid])) {
                $grouped[$cid] = [
                    'category_name' => $r['category_name'],
                    'items'         => [],
                ];
            }
            $grouped[$cid]['items'][(int) $r['item_id']] = $r['item_name'];
        }
        return $grouped;
    }

    /**
     * Human-readable item label.
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
     * Is this item fully configured for ordering?
     * (Active + has at least one active pricing row.)
     */
    public function isOrderable(int $itemId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM {$this->table} it
             WHERE it.item_id = :id AND it.is_active = 1
               AND EXISTS (
                   SELECT 1 FROM service_pricing sp
                   WHERE sp.item_id = it.item_id AND sp.is_active = 1
               )
             LIMIT 1"
        );
        $stmt->execute(['id' => $itemId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Bulk fetch items by ID array (used by billing / cart).
     * Returns [item_id => row, ...].
     */
    public function findManyByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT it.*, ic.category_name
                FROM {$this->table} it
                JOIN item_category ic ON ic.category_id = it.category_id
                WHERE it.item_id IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['item_id']] = $r;
        }
        return $out;
    }
}
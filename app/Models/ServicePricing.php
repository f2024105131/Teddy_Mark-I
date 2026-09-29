<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * ServicePricing Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : service_pricing  (PK: pricing_id)
 * Purpose: Per-item, per-service unit price matrix.
 *          Each row answers: "How much does ITEM X cost
 *          when processed with SERVICE Y?"
 *
 * Unique constraint: uk_item_service (item_id, service_id)
 *   → one price per (item, service) pair. Upsert-friendly.
 *
 * Used by:
 *   • Admin\ServiceController       (pricing matrix CRUD)
 *   • Admin\ItemController          (pricing count badge)
 *   • Admin\ReportController        (avg/min/max price)
 *   • Customer order flow (Faizan)  (price lookup at checkout)
 *   • BillingService (Shehreen)     (getPriceFor + bulk lookup)
 *   • Cart / quote calculators      (quoteForCart)
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → raw queries used
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class ServicePricing extends Model
{
    protected string $table      = 'service_pricing';
    protected string $primaryKey = 'pricing_id';

    // =========================================================
    //  BASIC LOOKUPS
    // =========================================================

    /**
     * Find a pricing row by its composite key.
     * Returns false if no row exists for the (item, service) pair.
     */
    public function findByItemService(int $itemId, int $serviceId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE item_id    = :iid
               AND service_id = :sid
             LIMIT 1"
        );
        $stmt->execute(['iid' => $itemId, 'sid' => $serviceId]);
        return $stmt->fetch();
    }

    /**
     * Fetch a pricing row with joined item, category, and service
     * names. Used by admin detail views.
     */
    public function findWithRelations(int $pricingId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT sp.*,
                    it.item_name, it.item_id, it.is_active AS item_is_active,
                    ic.category_id, ic.category_name,
                    s.service_name, s.service_id, s.service_type,
                    s.is_active AS service_is_active
             FROM {$this->table} sp
             JOIN item_type      it ON it.item_id     = sp.item_id
             JOIN item_category  ic ON ic.category_id = it.category_id
             JOIN service        s  ON s.service_id   = sp.service_id
             WHERE sp.pricing_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $pricingId]);
        return $stmt->fetch();
    }

    // =========================================================
    //  PRICE LOOKUPS (used by BillingService + Cart)
    // =========================================================

    /**
     * Fast price lookup used by BillingService (Shehreen).
     * Returns the unit price (float) or null if not found/inactive.
     */
    public function getPriceFor(int $itemId, int $serviceId): ?float
    {
        $stmt = $this->db->prepare(
            "SELECT unit_price FROM {$this->table}
             WHERE item_id    = :iid
               AND service_id = :sid
               AND is_active  = 1
             LIMIT 1"
        );
        $stmt->execute(['iid' => $itemId, 'sid' => $serviceId]);
        $price = $stmt->fetchColumn();

        return $price !== false ? (float) $price : null;
    }

    /**
     * Bulk price lookup — one query for many pairs.
     *
     * $pairs example:
     *   [
     *       ['item_id' => 1, 'service_id' => 2],
     *       ['item_id' => 3, 'service_id' => 2],
     *   ]
     *
     * Returns: [ "1:2" => 150.00, "3:2" => 450.00 ]
     */
    public function getPricesFor(array $pairs): array
    {
        if (empty($pairs)) {
            return [];
        }

        // Build OR conditions for each pair
        $conditions = [];
        $params     = [];

        foreach ($pairs as $i => $pair) {
            $iid = (int) ($pair['item_id']    ?? 0);
            $sid = (int) ($pair['service_id'] ?? 0);

            if ($iid <= 0 || $sid <= 0) {
                continue;
            }

            $conditions[]      = "(item_id = :iid{$i} AND service_id = :sid{$i})";
            $params["iid{$i}"] = $iid;
            $params["sid{$i}"] = $sid;
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = "SELECT item_id, service_id, unit_price
                FROM {$this->table}
                WHERE is_active = 1
                  AND (" . implode(' OR ', $conditions) . ")";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $key         = $r['item_id'] . ':' . $r['service_id'];
            $out[$key]   = (float) $r['unit_price'];
        }
        return $out;
    }

    /**
     * Whole pricing matrix — every item × service combination
     * that has an existing row. Used by admin pricing matrix page.
     *
     * Returns: [ "item_id:service_id" => row, ... ]
     */
    public function fullMatrixIndexed(): array
    {
        $rows = $this->db
            ->query("SELECT * FROM {$this->table}")
            ->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $key       = $r['item_id'] . ':' . $r['service_id'];
            $out[$key] = $r;
        }
        return $out;
    }

    /**
     * Full matrix with joined names — one query, all rows.
     * Used by admin pricing matrix rendering.
     */
    public function fullMatrixWithNames(): array
    {
        $sql = "SELECT sp.pricing_id, sp.unit_price, sp.is_active,
                       it.item_id, it.item_name, it.is_active AS item_is_active,
                       ic.category_id, ic.category_name,
                       s.service_id, s.service_name, s.service_type,
                       s.is_active AS service_is_active
                FROM {$this->table} sp
                JOIN item_type      it ON it.item_id     = sp.item_id
                JOIN item_category  ic ON ic.category_id = it.category_id
                JOIN service        s  ON s.service_id   = sp.service_id
                ORDER BY ic.category_name ASC,
                         it.item_name ASC,
                         s.service_name ASC";

        return $this->db->query($sql)->fetchAll();
    }

    // =========================================================
    //  PRICING FOR A SPECIFIC ITEM OR SERVICE
    // =========================================================

    /**
     * All pricing rows for a single item.
     * Returns service info attached — useful for "item detail" view.
     */
    public function forItem(int $itemId, bool $activeOnly = true): array
    {
        $sql = "SELECT sp.*,
                       s.service_name, s.service_type, s.is_active AS service_is_active
                FROM {$this->table} sp
                JOIN service s ON s.service_id = sp.service_id
                WHERE sp.item_id = :iid";

        if ($activeOnly) {
            $sql .= " AND sp.is_active = 1 AND s.is_active = 1";
        }

        $sql .= " ORDER BY s.service_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['iid' => $itemId]);
        return $stmt->fetchAll();
    }

    /**
     * All pricing rows for a single service.
     * Returns item info attached — used by service detail page.
     */
    public function forService(int $serviceId, bool $activeOnly = true): array
    {
        $sql = "SELECT sp.*,
                       it.item_name,
                       ic.category_id, ic.category_name,
                       it.is_active AS item_is_active
                FROM {$this->table} sp
                JOIN item_type     it ON it.item_id     = sp.item_id
                JOIN item_category ic ON ic.category_id = it.category_id
                WHERE sp.service_id = :sid";

        if ($activeOnly) {
            $sql .= " AND sp.is_active = 1 AND it.is_active = 1";
        }

        $sql .= " ORDER BY ic.category_name ASC, it.item_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $serviceId]);
        return $stmt->fetchAll();
    }

    /**
     * Full pricing matrix grouped by category for a single service.
     * Returns:
     *   [
     *     ["category_id" => 1, "category_name" => "Men's Wear",
     *      "items" => [ ["item_id" => 1, "item_name" => "Shirt",
     *                    "pricing_id" => 5, "unit_price" => 150.0, "is_active" => 1], ... ],
     *     ],
     *     ...
     *   ]
     */
    public function matrixByCategoryForService(int $serviceId): array
    {
        $rows = $this->forService($serviceId, false);

        $grouped = [];
        foreach ($rows as $r) {
            $cid = (int) $r['category_id'];
            if (!isset($grouped[$cid])) {
                $grouped[$cid] = [
                    'category_id'   => $cid,
                    'category_name' => $r['category_name'],
                    'items'         => [],
                ];
            }
            $grouped[$cid]['items'][] = [
                'pricing_id'   => (int)    $r['pricing_id'],
                'item_id'      => (int)    $r['item_id'],
                'item_name'    => $r['item_name'],
                'item_is_active' => (int)  $r['item_is_active'],
                'unit_price'   => (float)  $r['unit_price'],
                'is_active'    => (int)    $r['is_active'],
            ];
        }
        return array_values($grouped);
    }

    // =========================================================
    //  UPSERT / BULK WRITE
    // =========================================================

    /**
     * Upsert a single (item, service) price.
     * Uses the uk_item_service unique key for ON DUPLICATE KEY UPDATE.
     */
    public function upsertPrice(int $itemId, int $serviceId, float $unitPrice, bool $isActive = true): bool
    {
        $sql = "INSERT INTO {$this->table}
                    (item_id, service_id, unit_price, is_active)
                VALUES
                    (:iid, :sid, :price, :active)
                ON DUPLICATE KEY UPDATE
                    unit_price = VALUES(unit_price),
                    is_active  = VALUES(is_active),
                    updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'iid'    => $itemId,
            'sid'    => $serviceId,
            'price'  => $unitPrice,
            'active' => $isActive ? 1 : 0,
        ]);
    }

    /**
     * Bulk-upsert a whole pricing matrix from an admin form.
     *
     * $matrix example:
     *   [
     *     ['item_id' => 1, 'service_id' => 2, 'unit_price' => 150.00],
     *     ['item_id' => 3, 'service_id' => 2, 'unit_price' => 450.00],
     *   ]
     *
     * Returns the number of rows processed. Wraps in a transaction.
     * Throws on failure — caller should catch and rollback.
     */
    public function bulkUpsert(array $matrix): int
    {
        if (empty($matrix)) {
            return 0;
        }

        $sql = "INSERT INTO {$this->table}
                    (item_id, service_id, unit_price, is_active)
                VALUES
                    (:iid, :sid, :price, 1)
                ON DUPLICATE KEY UPDATE
                    unit_price = VALUES(unit_price),
                    is_active  = 1,
                    updated_at = CURRENT_TIMESTAMP";

        $this->db->beginTransaction();
        try {
            $stmt  = $this->db->prepare($sql);
            $count = 0;

            foreach ($matrix as $row) {
                $itemId    = (int)   ($row['item_id']    ?? 0);
                $serviceId = (int)   ($row['service_id'] ?? 0);
                $price     = (float) ($row['unit_price'] ?? 0);

                if ($itemId <= 0 || $serviceId <= 0 || $price <= 0) {
                    continue; // skip invalid rows
                }

                $stmt->execute([
                    'iid'   => $itemId,
                    'sid'   => $serviceId,
                    'price' => $price,
                ]);
                $count++;
            }

            $this->db->commit();
            return $count;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Bulk upsert with is_active flag also controlled.
     * Use when importing a full matrix including inactive rows.
     */
    public function bulkUpsertWithStatus(array $matrix): int
    {
        if (empty($matrix)) {
            return 0;
        }

        $sql = "INSERT INTO {$this->table}
                    (item_id, service_id, unit_price, is_active)
                VALUES
                    (:iid, :sid, :price, :active)
                ON DUPLICATE KEY UPDATE
                    unit_price = VALUES(unit_price),
                    is_active  = VALUES(is_active),
                    updated_at = CURRENT_TIMESTAMP";

        $this->db->beginTransaction();
        try {
            $stmt  = $this->db->prepare($sql);
            $count = 0;

            foreach ($matrix as $row) {
                $itemId    = (int)   ($row['item_id']    ?? 0);
                $serviceId = (int)   ($row['service_id'] ?? 0);
                $price     = (float) ($row['unit_price'] ?? 0);
                $active    = !empty($row['is_active']) ? 1 : 0;

                if ($itemId <= 0 || $serviceId <= 0 || $price <= 0) {
                    continue;
                }

                $stmt->execute([
                    'iid'    => $itemId,
                    'sid'    => $serviceId,
                    'price'  => $price,
                    'active' => $active,
                ]);
                $count++;
            }

            $this->db->commit();
            return $count;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =========================================================
    //  STATE HELPERS
    // =========================================================

    /**
     * Guarded toggle of is_active. Returns the new state.
     * Throws if the row doesn't exist.
     */
    public function toggleActive(int $pricingId): bool
    {
        $current = $this->find($pricingId);
        if (!$current) {
            throw new \RuntimeException("Pricing row {$pricingId} not found");
        }

        $newState = !((bool) $current['is_active']);

        $this->update($pricingId, [
            'is_active' => $newState ? 1 : 0,
        ]);

        return $newState;
    }

    /**
     * Deactivate every pricing row for a given item.
     * Returns number of rows affected.
     */
    public function deactivateForItem(int $itemId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET is_active = 0, updated_at = CURRENT_TIMESTAMP
             WHERE item_id = :id AND is_active = 1"
        );
        $stmt->execute(['id' => $itemId]);
        return $stmt->rowCount();
    }

    /**
     * Deactivate every pricing row for a given service.
     * Returns number of rows affected.
     */
    public function deactivateForService(int $serviceId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET is_active = 0, updated_at = CURRENT_TIMESTAMP
             WHERE service_id = :id AND is_active = 1"
        );
        $stmt->execute(['id' => $serviceId]);
        return $stmt->rowCount();
    }

    /**
     * Delete every pricing row for a given item.
     * Returns number of rows affected.
     */
    public function deleteForItem(int $itemId): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE item_id = :id"
        );
        $stmt->execute(['id' => $itemId]);
        return $stmt->rowCount();
    }

    /**
     * Delete every pricing row for a given service.
     * Returns number of rows affected.
     */
    public function deleteForService(int $serviceId): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE service_id = :id"
        );
        $stmt->execute(['id' => $serviceId]);
        return $stmt->rowCount();
    }

    // =========================================================
    //  REPORTING / STATS
    // =========================================================

    /**
     * Overall pricing stats for admin dashboard.
     */
    public function stats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                COUNT(DISTINCT item_id)    AS items_covered,
                COUNT(DISTINCT service_id) AS services_covered,
                COALESCE(AVG(unit_price), 0) AS avg_price,
                COALESCE(MIN(unit_price), 0) AS min_price,
                COALESCE(MAX(unit_price), 0) AS max_price
             FROM {$this->table}"
        )->fetch() ?: [];

        return [
            'total'            => (int)   ($row['total']            ?? 0),
            'active'           => (int)   ($row['active']           ?? 0),
            'inactive'         => (int)   ($row['inactive']         ?? 0),
            'items_covered'    => (int)   ($row['items_covered']    ?? 0),
            'services_covered' => (int)   ($row['services_covered'] ?? 0),
            'avg_price'        => (float) ($row['avg_price']        ?? 0),
            'min_price'        => (float) ($row['min_price']        ?? 0),
            'max_price'        => (float) ($row['max_price']        ?? 0),
        ];
    }

    /**
     * Price stats for a single service.
     */
    public function statsForService(int $serviceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                COALESCE(AVG(unit_price), 0) AS avg_price,
                COALESCE(MIN(unit_price), 0) AS min_price,
                COALESCE(MAX(unit_price), 0) AS max_price
             FROM {$this->table}
             WHERE service_id = :sid"
        );
        $stmt->execute(['sid' => $serviceId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total'     => (int)   ($row['total']     ?? 0),
            'active'    => (int)   ($row['active']    ?? 0),
            'avg_price' => (float) ($row['avg_price'] ?? 0),
            'min_price' => (float) ($row['min_price'] ?? 0),
            'max_price' => (float) ($row['max_price'] ?? 0),
        ];
    }

    /**
     * Price stats for a single item (across all services).
     */
    public function statsForItem(int $itemId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                COALESCE(AVG(unit_price), 0) AS avg_price,
                COALESCE(MIN(unit_price), 0) AS min_price,
                COALESCE(MAX(unit_price), 0) AS max_price
             FROM {$this->table}
             WHERE item_id = :iid"
        );
        $stmt->execute(['iid' => $itemId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total'     => (int)   ($row['total']     ?? 0),
            'active'    => (int)   ($row['active']    ?? 0),
            'avg_price' => (float) ($row['avg_price'] ?? 0),
            'min_price' => (float) ($row['min_price'] ?? 0),
            'max_price' => (float) ($row['max_price'] ?? 0),
        ];
    }

    /**
     * Coverage report: for each item, how many services have an
     * active price? Identifies catalog gaps.
     */
    public function itemCoverage(): array
    {
        $sql = "SELECT
                    it.item_id,
                    it.item_name,
                    ic.category_name,
                    COUNT(sp.pricing_id) AS services_with_price,
                    SUM(CASE WHEN sp.is_active = 1 THEN 1 ELSE 0 END) AS active_prices
                FROM item_type     it
                JOIN item_category ic ON ic.category_id = it.category_id
                LEFT JOIN {$this->table} sp ON sp.item_id = it.item_id
                WHERE it.is_active = 1
                GROUP BY it.item_id, it.item_name, ic.category_name
                ORDER BY services_with_price ASC, it.item_name ASC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Missing price report: every (item, service) pair that has
     * NO pricing row. Useful for admins to see what's incomplete.
     *
     * Returns: [ ["item_id","item_name","category_name",
     *             "service_id","service_name","service_type"], ... ]
     */
    public function missingCombinations(): array
    {
        $sql = "SELECT it.item_id, it.item_name, ic.category_name,
                       s.service_id, s.service_name, s.service_type
                FROM item_type     it
                JOIN item_category ic ON ic.category_id = it.category_id
                CROSS JOIN service s
                LEFT JOIN {$this->table} sp
                       ON sp.item_id    = it.item_id
                      AND sp.service_id = s.service_id
                WHERE it.is_active = 1
                  AND s.is_active  = 1
                  AND sp.pricing_id IS NULL
                ORDER BY ic.category_name ASC,
                         it.item_name ASC,
                         s.service_name ASC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Count of missing (item, service) combinations.
     */
    public function missingCombinationsCount(): int
    {
        return (int) $this->db
            ->query(
                "SELECT COUNT(*)
                 FROM item_type it
                 JOIN item_category ic ON ic.category_id = it.category_id
                 CROSS JOIN service s
                 LEFT JOIN {$this->table} sp
                        ON sp.item_id    = it.item_id
                       AND sp.service_id = s.service_id
                 WHERE it.is_active = 1
                   AND s.is_active  = 1
                   AND sp.pricing_id IS NULL"
            )
            ->fetchColumn();
    }

    // =========================================================
    //  QUOTING (used by checkout / order flow)
    // =========================================================

    /**
     * Compute a quote for a cart.
     *
     * $cart example:
     *   [
     *     ['item_id' => 1, 'service_id' => 2, 'quantity' => 3],
     *     ['item_id' => 3, 'service_id' => 2, 'quantity' => 1],
     *   ]
     *
     * Returns:
     *   [
     *     'lines' => [
     *         ['item_id'=>1, 'service_id'=>2, 'quantity'=>3,
     *          'unit_price'=>150.0, 'line_total'=>450.0, 'found'=>true],
     *         ...
     *     ],
     *     'subtotal' => 450.0,
     *     'missing'  => [ ['item_id'=>3,'service_id'=>5], ... ],
     *   ]
     */
    public function quoteForCart(array $cart): array
    {
        $pairs = [];
        foreach ($cart as $line) {
            $pairs[] = [
                'item_id'    => (int) ($line['item_id']    ?? 0),
                'service_id' => (int) ($line['service_id'] ?? 0),
            ];
        }

        $prices = $this->getPricesFor($pairs);

        $lines   = [];
        $missing = [];
        $subtotal = 0.0;

        foreach ($cart as $line) {
            $itemId    = (int) ($line['item_id']    ?? 0);
            $serviceId = (int) ($line['service_id'] ?? 0);
            $qty       = max(1, (int) ($line['quantity'] ?? 1));

            $key   = $itemId . ':' . $serviceId;
            $found = array_key_exists($key, $prices);

            if (!$found) {
                $missing[] = [
                    'item_id'    => $itemId,
                    'service_id' => $serviceId,
                ];
                $lines[] = [
                    'item_id'    => $itemId,
                    'service_id' => $serviceId,
                    'quantity'   => $qty,
                    'unit_price' => null,
                    'line_total' => null,
                    'found'      => false,
                ];
                continue;
            }

            $unit      = (float) $prices[$key];
            $lineTotal = round($unit * $qty, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'item_id'    => $itemId,
                'service_id' => $serviceId,
                'quantity'   => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
                'found'      => true,
            ];
        }

        return [
            'lines'    => $lines,
            'subtotal' => round($subtotal, 2),
            'missing'  => $missing,
        ];
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    /**
     * Human-readable pricing label for views.
     */
    public static function priceLabel(?float $price, string $currency = 'Rs.'): string
    {
        if ($price === null) {
            return 'N/A';
        }
        return $currency . ' ' . number_format($price, 2);
    }

    /**
     * CSS badge class for active state.
     */
    public static function statusBadge(int $isActive): string
    {
        return $isActive ? 'badge-success' : 'badge-secondary';
    }

    /**
     * Is this exact (item, service) pair orderable right now?
     * Requires item active, service active, pricing active.
     */
    public function isOrderable(int $itemId, int $serviceId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM {$this->table} sp
             JOIN item_type it ON it.item_id  = sp.item_id
             JOIN service   s  ON s.service_id = sp.service_id
             WHERE sp.item_id    = :iid
               AND sp.service_id = :sid
               AND sp.is_active  = 1
               AND it.is_active  = 1
               AND s.is_active   = 1
             LIMIT 1"
        );
        $stmt->execute(['iid' => $itemId, 'sid' => $serviceId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Bulk lookup of orderable pairs (used at checkout validation).
     * Returns true only if all pairs are orderable.
     */
    public function allOrderable(array $pairs): bool
    {
        if (empty($pairs)) {
            return true;
        }

        $prices = $this->getPricesFor($pairs);
        foreach ($pairs as $pair) {
            $iid = (int) ($pair['item_id']    ?? 0);
            $sid = (int) ($pair['service_id'] ?? 0);
            $key = $iid . ':' . $sid;
            if (!isset($prices[$key])) {
                return false;
            }
        }
        return true;
    }
}
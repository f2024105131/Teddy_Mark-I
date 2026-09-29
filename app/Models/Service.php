<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Service Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : service  (PK: service_id)
 * Purpose: Catalog master data for laundry services
 *          (Wash & Fold, Dry Cleaning, Ironing, Express, ...).
 *
 * Unique constraint: service_name UNIQUE
 * ENUM: service_type — must match schema exactly
 *
 * Used by:
 *   • Admin\ServiceController       (CRUD + pricing matrix columns)
 *   • Admin\ReportController        (service revenue mix)
 *   • Customer portal               (service picker)
 *   • DeliveryDateEstimator (Faizan) (getDurationInHours)
 *   • BillingService (Shehreen)     (validate service + lookup price)
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → raw queries used
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class Service extends Model
{
    protected string $table      = 'service';
    protected string $primaryKey = 'service_id';

    /** Enum values — single source of truth for validation */
    public const TYPES = [
        'wash_fold',
        'dry_cleaning',
        'ironing',
        'wash_iron',
        'express',
        'premium',
        'blanket_cleaning',
        'curtain_cleaning',
    ];

    /** Human-readable labels for service_type */
    public const TYPE_LABELS = [
        'wash_fold'        => 'Wash & Fold',
        'dry_cleaning'     => 'Dry Cleaning',
        'ironing'          => 'Ironing Only',
        'wash_iron'        => 'Wash & Iron',
        'express'          => 'Express',
        'premium'          => 'Premium',
        'blanket_cleaning' => 'Blanket Cleaning',
        'curtain_cleaning' => 'Curtain Cleaning',
    ];

    /** Types considered "express" for surcharge logic */
    public const EXPRESS_TYPES = ['express'];

    // =========================================================
    //  BASIC LOOKUPS
    // =========================================================

    /**
     * All active services, ordered by name.
     * Used by customer pickers + admin forms.
     */
    public function allActive(): array
    {
        return $this->db
            ->query("SELECT * FROM {$this->table}
                     WHERE is_active = 1
                     ORDER BY service_name ASC")
            ->fetchAll();
    }

    /**
     * All services (active + inactive) for admin listing.
     */
    public function allOrdered(): array
    {
        return $this->db
            ->query("SELECT * FROM {$this->table}
                     ORDER BY is_active DESC, service_name ASC")
            ->fetchAll();
    }

    /**
     * All services of a given type.
     */
    public function byType(string $serviceType, bool $activeOnly = true): array
    {
        if (!in_array($serviceType, self::TYPES, true)) {
            return [];
        }

        $sql = "SELECT * FROM {$this->table} WHERE service_type = :type";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY service_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $serviceType]);
        return $stmt->fetchAll();
    }

    /**
     * Find a service by name (case-insensitive).
     */
    public function findByName(string $name): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE LOWER(service_name) = LOWER(:n)
             LIMIT 1"
        );
        $stmt->execute(['n' => trim($name)]);
        return $stmt->fetch();
    }

    // =========================================================
    //  DURATION HELPERS
    // =========================================================

    /**
     * Total duration in HOURS for a service row.
     * Combines days*24 + hours. Used by DeliveryDateEstimator.
     */
    public function getDurationInHours(array $service): int
    {
        return ((int) ($service['duration_days']  ?? 0) * 24)
             +  (int) ($service['duration_hours'] ?? 0);
    }

    /**
     * Convenience: duration in hours by ID.
     */
    public function durationInHours(int $serviceId): ?int
    {
        $service = $this->find($serviceId);
        if (!$service) {
            return null;
        }
        return $this->getDurationInHours($service);
    }

    /**
     * Estimated ready date for an order placed now with this service.
     * Returns Y-m-d string, or null if service missing.
     */
    public function estimateReadyDate(int $serviceId, ?string $fromDate = null): ?string
    {
        $service = $this->find($serviceId);
        if (!$service) {
            return null;
        }

        $hours = $this->getDurationInHours($service);
        $from  = $fromDate ? strtotime($fromDate) : time();

        return date('Y-m-d', strtotime("+{$hours} hours", $from));
    }

    // =========================================================
    //  UNIQUENESS / USAGE GUARDS
    // =========================================================

    /**
     * Check if a service name already exists.
     * Pass $excludeId on edit to ignore the current row.
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE LOWER(service_name) = LOWER(:n)";
        $params = ['n' => trim($name)];

        if ($excludeId !== null) {
            $sql .= " AND service_id <> :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Count of order_item rows referencing this service.
     */
    public function ordersCount(int $serviceId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM order_item WHERE service_id = :id"
        );
        $stmt->execute(['id' => $serviceId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count of service_pricing rows referencing this service.
     */
    public function pricingCount(int $serviceId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM service_pricing WHERE service_id = :id"
        );
        $stmt->execute(['id' => $serviceId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Compound delete check — returns a human-readable reason
     * string if the service cannot be deleted, or null if deletable.
     */
    public function deletionBlockReason(int $serviceId): ?string
    {
        $orderCount = $this->ordersCount($serviceId);
        if ($orderCount > 0) {
            return "This service is used in {$orderCount} order(s). Deactivate it instead to preserve history.";
        }
        return null;
    }

    // =========================================================
    //  ADMIN LISTINGS WITH COUNTS
    // =========================================================

    /**
     * Full listing with attached counts + search + filters.
     * Used by Admin\ServiceController::index().
     */
    public function allWithCounts(
        ?string $search = null,
        string $type = '',
        string $status = ''
    ): array {
        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM service_pricing sp
                          WHERE sp.service_id = s.service_id) AS pricing_count,
                       (SELECT COUNT(*) FROM order_item oi
                          WHERE oi.service_id = s.service_id) AS orders_count
                FROM {$this->table} s
                WHERE 1 = 1";

        $params = [];

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (s.service_name LIKE :search
                        OR s.description LIKE :search)";
            $params['search'] = '%' . trim($search) . '%';
        }

        if ($type !== '' && in_array($type, self::TYPES, true)) {
            $sql .= " AND s.service_type = :type";
            $params['type'] = $type;
        }

        if ($status === 'active') {
            $sql .= " AND s.is_active = 1";
        } elseif ($status === 'inactive') {
            $sql .= " AND s.is_active = 0";
        }

        $sql .= " ORDER BY s.is_active DESC, s.service_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Single service with counts.
     */
    public function findWithCounts(int $serviceId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM service_pricing sp
                       WHERE sp.service_id = s.service_id) AS pricing_count,
                    (SELECT COUNT(*) FROM order_item oi
                       WHERE oi.service_id = s.service_id) AS orders_count
             FROM {$this->table} s
             WHERE s.service_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $serviceId]);
        return $stmt->fetch();
    }

    /**
     * Service + full pricing matrix (items × this service).
     * Used by Admin\ServiceController::show().
     */
    public function withPricing(int $serviceId): array|false
    {
        $service = $this->find($serviceId);
        if (!$service) {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT sp.pricing_id, sp.unit_price, sp.is_active,
                    it.item_id, it.item_name,
                    ic.category_id, ic.category_name
             FROM service_pricing sp
             JOIN item_type     it ON it.item_id     = sp.item_id
             JOIN item_category ic ON ic.category_id = it.category_id
             WHERE sp.service_id = :sid
             ORDER BY ic.category_name ASC, it.item_name ASC"
        );
        $stmt->execute(['sid' => $serviceId]);
        $service['pricing'] = $stmt->fetchAll();

        return $service;
    }

    // =========================================================
    //  STATE HELPERS
    // =========================================================

    /**
     * Guarded toggle of is_active. Returns the new state.
     * Throws if the row doesn't exist.
     */
    public function toggleActive(int $serviceId): bool
    {
        $current = $this->find($serviceId);
        if (!$current) {
            throw new \RuntimeException("Service {$serviceId} not found");
        }

        $newState = !((bool) $current['is_active']);

        $this->update($serviceId, [
            'is_active' => $newState ? 1 : 0,
        ]);

        return $newState;
    }

    /**
     * Cascade deactivation of pricing rows when deactivating
     * a service. Returns number of pricing rows affected.
     */
    public function deactivatePricing(int $serviceId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE service_pricing
             SET is_active = 0, updated_at = CURRENT_TIMESTAMP
             WHERE service_id = :id AND is_active = 1"
        );
        $stmt->execute(['id' => $serviceId]);
        return $stmt->rowCount();
    }

    // =========================================================
    //  REPORTING / DASHBOARD
    // =========================================================

    /**
     * Summary stats for admin service page.
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
                  WHERE is_active = 1) AS covered_items,
                (SELECT COALESCE(AVG(unit_price), 0) FROM service_pricing
                  WHERE is_active = 1) AS avg_price
             FROM {$this->table}"
        )->fetch() ?: [];

        return [
            'total'         => (int)   ($row['total']         ?? 0),
            'active'        => (int)   ($row['active']        ?? 0),
            'inactive'      => (int)   ($row['inactive']      ?? 0),
            'pricing_rows'  => (int)   ($row['pricing_rows']  ?? 0),
            'covered_items' => (int)   ($row['covered_items'] ?? 0),
            'avg_price'     => (float) ($row['avg_price']     ?? 0),
        ];
    }

    /**
     * Services ranked by revenue over a date range.
     * Used by Admin\ReportController::orders() (service mix).
     */
    public function topByRevenue(string $from, string $to, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.service_id, s.service_name, s.service_type,
                    COUNT(DISTINCT oi.order_id) AS orders_count,
                    COALESCE(SUM(oi.quantity), 0)    AS items_count,
                    COALESCE(SUM(oi.total_price), 0) AS revenue
             FROM {$this->table} s
             JOIN order_item oi ON oi.service_id = s.service_id
             JOIN orders     o  ON o.order_id    = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to
             GROUP BY s.service_id, s.service_name, s.service_type
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
     * Services with no pricing rows (catalog gap).
     * Useful for admin cleanup.
     */
    public function withoutPricing(bool $activeOnly = true): array
    {
        $sql = "SELECT s.*
                FROM {$this->table} s
                LEFT JOIN service_pricing sp ON sp.service_id = s.service_id
                WHERE sp.pricing_id IS NULL";

        if ($activeOnly) {
            $sql .= " AND s.is_active = 1";
        }

        $sql .= " ORDER BY s.service_name ASC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Services that are active but have no active pricing rows
     * (broken — customers can't order them). Cleanup candidate.
     */
    public function brokenCatalog(): array
    {
        return $this->db
            ->query(
                "SELECT s.*
                 FROM {$this->table} s
                 WHERE s.is_active = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM service_pricing sp
                       WHERE sp.service_id = s.service_id
                         AND sp.is_active = 1
                   )
                 ORDER BY s.service_name ASC"
            )
            ->fetchAll();
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    /**
     * Select list for dropdowns: service_id => service_name.
     * Optional type filter.
     */
    public function options(bool $activeOnly = true, string $type = ''): array
    {
        $sql = "SELECT service_id, service_name
                FROM {$this->table}
                WHERE 1 = 1";

        $params = [];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        if ($type !== '' && in_array($type, self::TYPES, true)) {
            $sql .= " AND service_type = :type";
            $params['type'] = $type;
        }

        $sql .= " ORDER BY service_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $options = [];
        foreach ($rows as $r) {
            $options[(int) $r['service_id']] = $r['service_name'];
        }
        return $options;
    }

    /**
     * Grouped options: [service_type => [id => name, ...], ...].
     * Perfect for building <optgroup> in a <select>.
     */
    public function groupedOptions(bool $activeOnly = true): array
    {
        $sql = "SELECT service_id, service_name, service_type
                FROM {$this->table}";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY service_type ASC, service_name ASC";

        $rows = $this->db->query($sql)->fetchAll();

        $grouped = [];
        foreach ($rows as $r) {
            $type = $r['service_type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [
                    'label'   => self::TYPE_LABELS[$type] ?? ucfirst($type),
                    'services'=> [],
                ];
            }
            $grouped[$type]['services'][(int) $r['service_id']] = $r['service_name'];
        }
        return $grouped;
    }

    /**
     * Bulk fetch services by ID array (used by billing / cart).
     * Returns [service_id => row, ...].
     */
    public function findManyByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT * FROM {$this->table}
                WHERE service_id IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['service_id']] = $r;
        }
        return $out;
    }

    /**
     * Is this service fully configured for ordering?
     * (Active + has at least one active pricing row.)
     */
    public function isOrderable(int $serviceId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM {$this->table} s
             WHERE s.service_id = :id AND s.is_active = 1
               AND EXISTS (
                   SELECT 1 FROM service_pricing sp
                   WHERE sp.service_id = s.service_id AND sp.is_active = 1
               )
             LIMIT 1"
        );
        $stmt->execute(['id' => $serviceId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Human-readable label for a service_type enum value.
     */
    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * CSS badge class for a service_type.
     */
    public static function typeBadge(string $type): string
    {
        return match ($type) {
            'wash_fold'        => 'badge-primary',
            'wash_iron'        => 'badge-info',
            'dry_cleaning'     => 'badge-dark',
            'ironing'          => 'badge-secondary',
            'express'          => 'badge-warning',
            'premium'          => 'badge-success',
            'blanket_cleaning' => 'badge-light',
            'curtain_cleaning' => 'badge-light',
            default            => 'badge-secondary',
        };
    }

    /**
     * Is this service an express type (used for surcharge logic)?
     */
    public static function isExpress(string $type): bool
    {
        return in_array($type, self::EXPRESS_TYPES, true);
    }

    /**
     * CSS badge for active state.
     */
    public static function statusBadge(int $isActive): string
    {
        return $isActive ? 'badge-success' : 'badge-secondary';
    }

    /**
     * Format the duration into a short label like "2 days" or
     * "24 hours" or "1 day 12 hours". Used in views.
     */
    public static function formatDuration(int $hours, int $days): string
    {
        $parts = [];
        if ($days > 0) {
            $parts[] = $days === 1 ? '1 day' : "{$days} days";
        }
        if ($hours > 0) {
            $parts[] = $hours === 1 ? '1 hour' : "{$hours} hours";
        }
        return empty($parts) ? 'Instant' : implode(' ', $parts);
    }
}
<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Delivery Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : delivery  (PK: delivery_id)
 * Purpose: Data access for staff-side & admin-side delivery
 *          operations, dashboards, and reporting.
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → we use raw queries
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class Delivery extends Model
{
    protected string $table      = 'delivery';
    protected string $primaryKey = 'delivery_id';

    /** Status enum values (kept in one place for reuse by controllers) */
    public const STATUS_SCHEDULED        = 'scheduled';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED        = 'delivered';
    public const STATUS_FAILED           = 'failed';
    public const STATUS_RESCHEDULED      = 'rescheduled';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
        self::STATUS_RESCHEDULED,
    ];

    /** Statuses that count as "still on the road" */
    public const ACTIVE_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_OUT_FOR_DELIVERY,
    ];

    // =========================================================
    //  BASIC LOOKUPS
    // =========================================================

    /**
     * Find a delivery by its parent order.
     * (Useful because orders.delivery_id is a 1-to-1 relationship.)
     */
    public function findByOrder(int $orderId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE order_id = :oid
             LIMIT 1"
        );
        $stmt->execute(['oid' => $orderId]);
        return $stmt->fetch();
    }

    /**
     * Does a delivery row already exist for this order?
     */
    public function existsForOrder(int $orderId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM {$this->table}
             WHERE order_id = :oid
             LIMIT 1"
        );
        $stmt->execute(['oid' => $orderId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Deliveries assigned to a staff member in a specific status set.
     * Staff dashboard uses this.
     */
    public function forStaffByStatuses(int $staffId, array $statuses = self::ACTIVE_STATUSES): array
    {
        if (empty($statuses)) {
            return [];
        }

        // Sanitize statuses against the whitelist to prevent SQL injection
        $safe = array_values(array_intersect($statuses, self::STATUSES));
        if (empty($safe)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($safe), '?'));

        $sql = "SELECT d.*,
                       o.order_number, o.order_status,
                       o.delivered_at AS order_delivered_at,
                       c.full_name AS customer_name,
                       c.phone     AS customer_phone,
                       c.address_house, c.address_street,
                       c.address_area,  c.city,
                       ds.start_time,   ds.end_time,
                       st.full_name AS staff_name
                FROM {$this->table} d
                JOIN orders        o  ON o.order_id    = d.order_id
                JOIN customer      c  ON c.customer_id = o.customer_id
                JOIN delivery_slot ds ON ds.slot_id    = d.slot_id
                LEFT JOIN staff    st ON st.staff_id   = d.assigned_staff_id
                WHERE d.assigned_staff_id = ?
                  AND d.status IN ({$placeholders})
                ORDER BY d.delivery_date ASC, ds.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$staffId], $safe));
        return $stmt->fetchAll();
    }

    // =========================================================
    //  STAFF-SIDE LISTS
    // =========================================================

    /**
     * Today's deliveries for a staff member (or all if $staffId is null).
     * Optional $status filter.
     */
    public function todaysFor(?int $staffId = null, ?string $status = null): array
    {
        $sql = "SELECT d.*,
                       o.order_number, o.order_status,
                       c.full_name AS customer_name,
                       c.phone     AS customer_phone,
                       ds.start_time, ds.end_time,
                       st.full_name AS staff_name
                FROM {$this->table} d
                JOIN orders        o  ON o.order_id    = d.order_id
                JOIN customer      c  ON c.customer_id = o.customer_id
                JOIN delivery_slot ds ON ds.slot_id    = d.slot_id
                LEFT JOIN staff    st ON st.staff_id   = d.assigned_staff_id
                WHERE d.delivery_date = CURDATE()";

        $params = [];

        if ($staffId !== null) {
            $sql .= " AND d.assigned_staff_id = :sid";
            $params['sid'] = $staffId;
        }

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $sql .= " AND d.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY ds.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Deliveries in a specific status for a staff member.
     */
    public function byStatusForStaff(int $staffId, string $status): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT d.*,
                    o.order_number, o.order_status,
                    c.full_name AS customer_name,
                    c.phone     AS customer_phone,
                    ds.start_time, ds.end_time
             FROM {$this->table} d
             JOIN orders        o  ON o.order_id    = d.order_id
             JOIN customer      c  ON c.customer_id = o.customer_id
             JOIN delivery_slot ds ON ds.slot_id    = d.slot_id
             WHERE d.assigned_staff_id = :sid
               AND d.status = :status
             ORDER BY d.delivery_date ASC, ds.start_time ASC"
        );
        $stmt->execute(['sid' => $staffId, 'status' => $status]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  ADMIN-SIDE LISTS
    // =========================================================

    /**
     * All deliveries with filters — used by admin monitor page.
     * $filters can include:
     *   status, assigned_staff_id, delivery_date, from, to
     */
    public function allWithRelations(array $filters = []): array
    {
        $sql = "SELECT d.*,
                       o.order_number, o.order_status, o.customer_id,
                       c.full_name AS customer_name,
                       c.phone     AS customer_phone,
                       c.address_area, c.city,
                       ds.start_time, ds.end_time,
                       st.full_name AS staff_name
                FROM {$this->table} d
                JOIN orders        o  ON o.order_id    = d.order_id
                JOIN customer      c  ON c.customer_id = o.customer_id
                JOIN delivery_slot ds ON ds.slot_id    = d.slot_id
                LEFT JOIN staff    st ON st.staff_id   = d.assigned_staff_id
                WHERE 1 = 1";

        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= " AND d.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['assigned_staff_id'])) {
            $sql .= " AND d.assigned_staff_id = :sid";
            $params['sid'] = (int) $filters['assigned_staff_id'];
        }

        if (!empty($filters['delivery_date'])) {
            $sql .= " AND d.delivery_date = :ddate";
            $params['ddate'] = $filters['delivery_date'];
        }

        if (!empty($filters['from'])) {
            $sql .= " AND d.delivery_date >= :from";
            $params['from'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $sql .= " AND d.delivery_date <= :to";
            $params['to'] = $filters['to'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= " AND o.customer_id = :cid";
            $params['cid'] = (int) $filters['customer_id'];
        }

        $sql .= " ORDER BY d.delivery_date DESC, ds.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  CAPACITY & SCHEDULING
    // =========================================================

    /**
     * Count of deliveries booked for a given date + slot (active only).
     * Used by SlotAvailabilityService / staff reschedule logic.
     */
    public function countForSlot(string $date, int $slotId, array $statuses = self::ACTIVE_STATUSES): int
    {
        if (empty($statuses)) {
            return 0;
        }

        $safe = array_values(array_intersect($statuses, self::STATUSES));
        if (empty($safe)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($safe), '?'));

        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE delivery_date = ?
                  AND slot_id = ?
                  AND status IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$date, $slotId], $safe));
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get all deliveries scheduled for a date, grouped by status.
     * Used by admin dashboard metrics.
     */
    public function statusCountsOn(string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT status, COUNT(*) AS total
             FROM {$this->table}
             WHERE delivery_date = :d
             GROUP BY status"
        );
        $stmt->execute(['d' => $date]);
        $rows = $stmt->fetchAll();

        // Seed with all statuses set to 0, then override from DB
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $r) {
            $counts[$r['status']] = (int) $r['total'];
        }
        return $counts;
    }

    /**
     * Convenience: counts for today.
     */
    public function statusCountsToday(): array
    {
        return $this->statusCountsOn(date('Y-m-d'));
    }

    // =========================================================
    //  STATE TRANSITIONS
    // =========================================================

    /**
     * Guarded status update. Verifies the transition is legal.
     * Returns true on success, throws on invalid transition.
     */
    public function transition(int $deliveryId, string $newStatus, array $extra = []): bool
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid delivery status: {$newStatus}");
        }

        $current = $this->find($deliveryId);
        if (!$current) {
            throw new \RuntimeException("Delivery {$deliveryId} not found");
        }

        $allowed = self::allowedTransitions()[$current['status']] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException(
                "Cannot transition delivery from '{$current['status']}' to '{$newStatus}'"
            );
        }

        $data = array_merge(['status' => $newStatus], $extra);

        // Auto-fill delivered_at when marking delivered
        if ($newStatus === self::STATUS_DELIVERED && empty($data['delivered_at'])) {
            $data['delivered_at'] = date('Y-m-d H:i:s');
        }

        // Clear failure_reason when leaving 'failed'
        if ($newStatus !== self::STATUS_FAILED && array_key_exists('failure_reason', $data) === false) {
            // no-op: leave column alone unless explicitly set
        }

        return $this->update($deliveryId, $data);
    }

    /**
     * Static map of allowed delivery state transitions.
     * Kept here (and mirrored in DeliveryController) as a single source of truth.
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_SCHEDULED        => [self::STATUS_OUT_FOR_DELIVERY, self::STATUS_FAILED, self::STATUS_RESCHEDULED],
            self::STATUS_OUT_FOR_DELIVERY => [self::STATUS_DELIVERED, self::STATUS_FAILED, self::STATUS_RESCHEDULED],
            self::STATUS_DELIVERED        => [],  // terminal
            self::STATUS_FAILED           => [self::STATUS_RESCHEDULED],
            self::STATUS_RESCHEDULED      => [self::STATUS_SCHEDULED, self::STATUS_OUT_FOR_DELIVERY],
        ];
    }

    // =========================================================
    //  REPORTING / ADMIN DASHBOARD
    // =========================================================

    /**
     * Per-staff delivery totals over a date range.
     * Used by Admin\ReportController.
     */
    public function statsPerStaff(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.staff_id, s.full_name,
                    COUNT(d.delivery_id) AS total_deliveries,
                    SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                    SUM(CASE WHEN d.status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN d.status = 'out_for_delivery' THEN 1 ELSE 0 END) AS in_progress
             FROM staff s
             LEFT JOIN {$this->table} d
                    ON d.assigned_staff_id = s.staff_id
                   AND d.delivery_date BETWEEN :from AND :to
             WHERE s.is_active = 1
             GROUP BY s.staff_id, s.full_name
             ORDER BY delivered DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    /**
     * Delivery performance over a date range (aggregate).
     */
    public function performanceSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'delivered'        THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = 'failed'           THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'scheduled'        THEN 1 ELSE 0 END) AS scheduled,
                AVG(CASE WHEN status = 'delivered' AND delivered_at IS NOT NULL
                         THEN TIMESTAMPDIFF(MINUTE, created_at, delivered_at)
                         ELSE NULL END) AS avg_minutes_to_deliver
             FROM {$this->table}
             WHERE delivery_date BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        return [
            'total'                    => (int)   ($row['total']    ?? 0),
            'delivered'                => (int)   ($row['delivered'] ?? 0),
            'failed'                   => (int)   ($row['failed']    ?? 0),
            'in_progress'              => (int)   ($row['in_progress'] ?? 0),
            'scheduled'                => (int)   ($row['scheduled'] ?? 0),
            'avg_minutes_to_deliver'   => $row['avg_minutes_to_deliver'] !== null
                                            ? (float) $row['avg_minutes_to_deliver']
                                            : null,
        ];
    }

    /**
     * Deliveries scheduled for a specific date — used by admin pickup monitor.
     */
    public function forDate(string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*,
                    o.order_number, o.order_status,
                    c.full_name AS customer_name, c.phone AS customer_phone,
                    ds.start_time, ds.end_time,
                    st.full_name AS staff_name
             FROM {$this->table} d
             JOIN orders        o  ON o.order_id    = d.order_id
             JOIN customer      c  ON c.customer_id = o.customer_id
             JOIN delivery_slot ds ON ds.slot_id    = d.slot_id
             LEFT JOIN staff    st ON st.staff_id   = d.assigned_staff_id
             WHERE d.delivery_date = :d
             ORDER BY ds.start_time ASC"
        );
        $stmt->execute(['d' => $date]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    /**
     * Is this status "active" (still in the pipeline)?
     */
    public static function isActiveStatus(string $status): bool
    {
        return in_array($status, self::ACTIVE_STATUSES, true);
    }

    /**
     * Is this status terminal (no further transitions)?
     */
    public static function isTerminalStatus(string $status): bool
    {
        return $status === self::STATUS_DELIVERED;
    }

    /**
     * Return a human-readable label for a status (for views).
     */
    public static function label(string $status): string
    {
        return match ($status) {
            self::STATUS_SCHEDULED        => 'Scheduled',
            self::STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
            self::STATUS_DELIVERED        => 'Delivered',
            self::STATUS_FAILED           => 'Failed',
            self::STATUS_RESCHEDULED      => 'Rescheduled',
            default                       => ucfirst($status),
        };
    }
}
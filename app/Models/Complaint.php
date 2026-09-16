<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Complaint Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : complaint  (PK: complaint_id)
 * Purpose: Data access for staff-side, customer-side, and
 *          admin-side complaint operations, dashboards,
 *          reporting, and state transitions.
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → we use raw queries
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class Complaint extends Model
{
    protected string $table      = 'complaint';
    protected string $primaryKey = 'complaint_id';

    /** Status enum values (single source of truth) */
    public const STATUS_OPEN                = 'open';
    public const STATUS_ASSIGNED            = 'assigned';
    public const STATUS_UNDER_INVESTIGATION = 'under_investigation';
    public const STATUS_RESOLVED            = 'resolved';
    public const STATUS_REJECTED            = 'rejected';
    public const STATUS_ESCALATED           = 'escalated';
    public const STATUS_CLOSED              = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ASSIGNED,
        self::STATUS_UNDER_INVESTIGATION,
        self::STATUS_RESOLVED,
        self::STATUS_REJECTED,
        self::STATUS_ESCALATED,
        self::STATUS_CLOSED,
    ];

    /** Statuses that count as "still active / open" */
    public const ACTIVE_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ASSIGNED,
        self::STATUS_UNDER_INVESTIGATION,
        self::STATUS_ESCALATED,
    ];

    /** Statuses that count as "finished" */
    public const TERMINAL_STATUSES = [
        self::STATUS_RESOLVED,
        self::STATUS_REJECTED,
        self::STATUS_CLOSED,
    ];

    /** Type enum values (matches schema ENUM exactly) */
    public const TYPES = [
        'missing_item',
        'damaged_item',
        'late_delivery',
        'wrong_billing',
        'poor_cleaning_quality',
    ];

    // =========================================================
    //  BASIC LOOKUPS
    // =========================================================

    /**
     * Find a complaint by its complaint_number (unique).
     */
    public function findByNumber(string $complaintNumber): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE complaint_number = :n
             LIMIT 1"
        );
        $stmt->execute(['n' => $complaintNumber]);
        return $stmt->fetch();
    }

    /**
     * Check if the customer already has an active complaint on this order.
     * Returns the complaint row if so, otherwise false.
     */
    public function activeForOrder(int $orderId, int $customerId): array|false
    {
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));

        $sql = "SELECT * FROM {$this->table}
                WHERE order_id    = ?
                  AND customer_id = ?
                  AND status IN ({$placeholders})
                ORDER BY complaint_id DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$orderId, $customerId], self::ACTIVE_STATUSES));
        return $stmt->fetch();
    }

    // =========================================================
    //  STAFF-SIDE LISTS
    // =========================================================

    /**
     * Complaints assigned to a staff member that are still actionable.
     */
    public function assignedTo(int $staffId): array
    {
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));

        $sql = "SELECT cm.*,
                       c.full_name AS customer_name,
                       c.phone     AS customer_phone,
                       o.order_number,
                       cc.category_name
                FROM {$this->table} cm
                JOIN customer c ON c.customer_id = cm.customer_id
                JOIN orders   o ON o.order_id    = cm.order_id
                LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
                WHERE cm.assigned_staff_id = ?
                  AND cm.status IN ({$placeholders})
                ORDER BY
                    FIELD(cm.status,
                          'under_investigation',
                          'assigned',
                          'open',
                          'escalated'),
                    cm.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$staffId], self::ACTIVE_STATUSES));
        return $stmt->fetchAll();
    }

    /**
     * All complaints assigned to a staff member (any status).
     */
    public function allAssignedTo(int $staffId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cm.*,
                    c.full_name AS customer_name,
                    c.phone     AS customer_phone,
                    o.order_number,
                    cc.category_name
             FROM {$this->table} cm
             JOIN customer c ON c.customer_id = cm.customer_id
             JOIN orders   o ON o.order_id    = cm.order_id
             LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
             WHERE cm.assigned_staff_id = :sid
             ORDER BY cm.created_at DESC"
        );
        $stmt->execute(['sid' => $staffId]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  CUSTOMER-SIDE LISTS
    // =========================================================

    /**
     * All complaints raised by a customer (any status).
     */
    public function forCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT cm.*,
                    o.order_number,
                    cc.category_name,
                    st.full_name AS assigned_staff_name
             FROM {$this->table} cm
             JOIN orders o ON o.order_id = cm.order_id
             LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
             LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
             WHERE cm.customer_id = :cid
             ORDER BY cm.created_at DESC"
        );
        $stmt->execute(['cid' => $customerId]);
        return $stmt->fetchAll();
    }

    /**
     * Count of active complaints for a customer — used by customer dashboard.
     */
    public function activeCountForCustomer(int $customerId): int
    {
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE customer_id = ?
               AND status IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$customerId], self::ACTIVE_STATUSES));
        return (int) $stmt->fetchColumn();
    }

    // =========================================================
    //  ADMIN-SIDE LISTS
    // =========================================================

    /**
     * Full filtered listing with joins — used by admin index page.
     * Supported $filters keys:
     *   status (string | array), type, category_id,
     *   assigned_staff_id, customer_id, order_id,
     *   from (date), to (date), search (complaint_number|order_number)
     */
    public function allWithRelations(array $filters = []): array
    {
        $sql = "SELECT cm.*,
                       c.full_name  AS customer_name,
                       c.phone      AS customer_phone,
                       c.email      AS customer_email,
                       o.order_number,
                       o.order_status,
                       cc.category_name,
                       st.full_name AS assigned_staff_name
                FROM {$this->table} cm
                JOIN customer c ON c.customer_id = cm.customer_id
                JOIN orders   o ON o.order_id    = cm.order_id
                LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
                LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
                WHERE 1 = 1";

        $params = [];

        // Status filter: single or array
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? array_values(array_intersect($filters['status'], self::STATUSES))
                : [$filters['status']];

            $statuses = array_filter($statuses, fn($s) => in_array($s, self::STATUSES, true));

            if (!empty($statuses)) {
                $placeholders = [];
                foreach ($statuses as $i => $s) {
                    $key = 'st' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $s;
                }
                $sql .= " AND cm.status IN (" . implode(',', $placeholders) . ")";
            }
        }

        if (!empty($filters['type']) && in_array($filters['type'], self::TYPES, true)) {
            $sql .= " AND cm.type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND cm.category_id = :cid";
            $params['cid'] = (int) $filters['category_id'];
        }

        if (!empty($filters['assigned_staff_id'])) {
            $sql .= " AND cm.assigned_staff_id = :sid";
            $params['sid'] = (int) $filters['assigned_staff_id'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= " AND cm.customer_id = :cust";
            $params['cust'] = (int) $filters['customer_id'];
        }

        if (!empty($filters['order_id'])) {
            $sql .= " AND cm.order_id = :oid";
            $params['oid'] = (int) $filters['order_id'];
        }

        if (!empty($filters['from'])) {
            $sql .= " AND cm.created_at >= :from";
            $params['from'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to'])) {
            $sql .= " AND cm.created_at <= :to";
            $params['to'] = $filters['to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (cm.complaint_number LIKE :search OR o.order_number LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY
                    FIELD(cm.status,
                          'open',
                          'assigned',
                          'under_investigation',
                          'escalated',
                          'resolved',
                          'rejected',
                          'closed'),
                    cm.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  DETAIL VIEWS
    // =========================================================

    /**
     * Single complaint with all joins for detail views (admin / staff / customer).
     */
    public function findDetailed(int $complaintId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT cm.*,
                    c.customer_id,
                    c.full_name  AS customer_name,
                    c.email      AS customer_email,
                    c.phone      AS customer_phone,
                    c.address_house, c.address_street,
                    c.address_area,  c.city, c.landmark,
                    o.order_number, o.order_status, o.order_date, o.delivered_at,
                    cc.category_name,
                    st.full_name AS assigned_staff_name
             FROM {$this->table} cm
             JOIN customer c ON c.customer_id = cm.customer_id
             JOIN orders   o ON o.order_id    = cm.order_id
             LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
             LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
             WHERE cm.complaint_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $complaintId]);
        return $stmt->fetch();
    }

    // =========================================================
    //  STATE TRANSITIONS
    // =========================================================

    /**
     * Guarded status update. Verifies the transition is legal.
     * Returns true on success; throws on invalid transition.
     *
     * $extra can include: resolution_notes, assigned_staff_id, resolved_at
     */
    public function transition(int $complaintId, string $newStatus, array $extra = []): bool
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid complaint status: {$newStatus}");
        }

        $current = $this->find($complaintId);
        if (!$current) {
            throw new \RuntimeException("Complaint {$complaintId} not found");
        }

        $allowed = self::allowedTransitions()[$current['status']] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException(
                "Cannot transition complaint from '{$current['status']}' to '{$newStatus}'"
            );
        }

        $data = array_merge(['status' => $newStatus], $extra);

        // Auto-fill resolved_at when reaching a terminal status
        if (in_array($newStatus, self::TERMINAL_STATUSES, true)
            && empty($data['resolved_at'])) {
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($complaintId, $data);
    }

    /**
     * Map of allowed transitions (single source of truth).
     * Mirrored in controllers for validation pre-flight.
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_OPEN                => [self::STATUS_ASSIGNED, self::STATUS_REJECTED, self::STATUS_ESCALATED, self::STATUS_CLOSED],
            self::STATUS_ASSIGNED            => [self::STATUS_UNDER_INVESTIGATION, self::STATUS_RESOLVED, self::STATUS_REJECTED, self::STATUS_ESCALATED],
            self::STATUS_UNDER_INVESTIGATION => [self::STATUS_RESOLVED, self::STATUS_REJECTED, self::STATUS_ESCALATED],
            self::STATUS_RESOLVED            => [self::STATUS_CLOSED],
            self::STATUS_REJECTED            => [self::STATUS_CLOSED],
            self::STATUS_ESCALATED           => [self::STATUS_RESOLVED, self::STATUS_REJECTED, self::STATUS_CLOSED],
            self::STATUS_CLOSED              => [],  // terminal
        ];
    }

    // =========================================================
    //  REPORTING / DASHBOARD
    // =========================================================

    /**
     * Status counts across a date range (or overall if null).
     * Returns an array with all statuses seeded to 0.
     */
    public function statusCounts(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT status, COUNT(*) AS total
                FROM {$this->table}
                WHERE 1 = 1";
        $params = [];

        if ($from) {
            $sql .= " AND created_at >= :from";
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= " AND created_at <= :to";
            $params['to'] = $to . ' 23:59:59';
        }
        $sql .= " GROUP BY status";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $r) {
            $counts[$r['status']] = (int) $r['total'];
        }
        return $counts;
    }

    /**
     * Counts grouped by complaint type over a date range.
     * Used by Admin\ReportController.
     */
    public function typeCounts(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT type, COUNT(*) AS total
                FROM {$this->table}
                WHERE 1 = 1";
        $params = [];

        if ($from) {
            $sql .= " AND created_at >= :from";
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= " AND created_at <= :to";
            $params['to'] = $to . ' 23:59:59';
        }
        $sql .= " GROUP BY type ORDER BY total DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $counts = array_fill_keys(self::TYPES, 0);
        foreach ($rows as $r) {
            $counts[$r['type']] = (int) $r['total'];
        }
        return $counts;
    }

    /**
     * Counts grouped by complaint category.
     */
    public function categoryCounts(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT cm.category_id,
                       cc.category_name,
                       COUNT(*) AS total
                FROM {$this->table} cm
                LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
                WHERE 1 = 1";
        $params = [];

        if ($from) {
            $sql .= " AND cm.created_at >= :from";
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= " AND cm.created_at <= :to";
            $params['to'] = $to . ' 23:59:59';
        }
        $sql .= " GROUP BY cm.category_id, cc.category_name ORDER BY total DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Per-staff complaint resolution stats.
     */
    public function statsPerStaff(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.staff_id, s.full_name,
                    COUNT(cm.complaint_id) AS total_assigned,
                    SUM(CASE WHEN cm.status = 'resolved' THEN 1 ELSE 0 END) AS resolved,
                    SUM(CASE WHEN cm.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN cm.status = 'escalated' THEN 1 ELSE 0 END) AS escalated,
                    SUM(CASE WHEN cm.status IN ('open','assigned','under_investigation') THEN 1 ELSE 0 END) AS active
             FROM staff s
             LEFT JOIN {$this->table} cm
                    ON cm.assigned_staff_id = s.staff_id
                   AND cm.created_at BETWEEN :from AND :to
             WHERE s.is_active = 1
             GROUP BY s.staff_id, s.full_name
             ORDER BY resolved DESC"
        );
        $stmt->execute([
            'from' => $from . ' 00:00:00',
            'to'   => $to   . ' 23:59:59',
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Aggregate summary for admin dashboard.
     */
    public function dashboardSummary(): array
    {
        $stmt = $this->db->query(
            "SELECT
                SUM(CASE WHEN status = 'open'                THEN 1 ELSE 0 END) AS open_total,
                SUM(CASE WHEN status = 'assigned'            THEN 1 ELSE 0 END) AS assigned_total,
                SUM(CASE WHEN status = 'under_investigation' THEN 1 ELSE 0 END) AS investigating_total,
                SUM(CASE WHEN status = 'escalated'           THEN 1 ELSE 0 END) AS escalated_total,
                SUM(CASE WHEN status = 'resolved' AND DATE(resolved_at) = CURDATE()
                                                          THEN 1 ELSE 0 END) AS resolved_today,
                SUM(CASE WHEN status IN ('resolved','rejected','closed')
                         AND resolved_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                                          THEN 1 ELSE 0 END) AS closed_last_30d,
                COUNT(*) AS total
             FROM {$this->table}"
        );
        $row = $stmt->fetch() ?: [];

        return [
            'open_total'          => (int) ($row['open_total']          ?? 0),
            'assigned_total'      => (int) ($row['assigned_total']      ?? 0),
            'investigating_total' => (int) ($row['investigating_total'] ?? 0),
            'escalated_total'     => (int) ($row['escalated_total']     ?? 0),
            'resolved_today'      => (int) ($row['resolved_today']      ?? 0),
            'closed_last_30d'     => (int) ($row['closed_last_30d']     ?? 0),
            'total'               => (int) ($row['total']               ?? 0),
        ];
    }

    /**
     * Recent complaints for admin dashboard widget.
     */
    public function recent(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT cm.complaint_id, cm.complaint_number, cm.type, cm.status,
                    cm.created_at, c.full_name AS customer_name,
                    o.order_number
             FROM {$this->table} cm
             JOIN customer c ON c.customer_id = cm.customer_id
             JOIN orders   o ON o.order_id    = cm.order_id
             ORDER BY cm.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    public static function isActiveStatus(string $status): bool
    {
        return in_array($status, self::ACTIVE_STATUSES, true);
    }

    public static function isTerminalStatus(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Human-readable label for a status (for views).
     */
    public static function label(string $status): string
    {
        return match ($status) {
            self::STATUS_OPEN                => 'Open',
            self::STATUS_ASSIGNED            => 'Assigned',
            self::STATUS_UNDER_INVESTIGATION => 'Under Investigation',
            self::STATUS_RESOLVED            => 'Resolved',
            self::STATUS_REJECTED            => 'Rejected',
            self::STATUS_ESCALATED           => 'Escalated',
            self::STATUS_CLOSED              => 'Closed',
            default                          => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Human-readable label for a complaint type.
     */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'missing_item'          => 'Missing Item',
            'damaged_item'          => 'Damaged Item',
            'late_delivery'         => 'Late Delivery',
            'wrong_billing'         => 'Wrong Billing',
            'poor_cleaning_quality' => 'Poor Cleaning Quality',
            default                 => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * CSS-friendly badge class for a status (for views).
     */
    public static function statusBadge(string $status): string
    {
        return match ($status) {
            self::STATUS_OPEN                => 'badge-danger',
            self::STATUS_ASSIGNED            => 'badge-warning',
            self::STATUS_UNDER_INVESTIGATION => 'badge-info',
            self::STATUS_RESOLVED            => 'badge-success',
            self::STATUS_REJECTED            => 'badge-secondary',
            self::STATUS_ESCALATED           => 'badge-dark',
            self::STATUS_CLOSED              => 'badge-light',
            default                          => 'badge-secondary',
        };
    }
}
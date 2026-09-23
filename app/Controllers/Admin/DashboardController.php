<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\SystemConfig;

/**
 * Admin\DashboardController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Executive dashboard for admin.
 *            • KPI cards       (customers, orders, revenue, complaints)
 *            • Today's ops     (pickups, deliveries, active orders)
 *            • Revenue windows (today, week, month)
 *            • Trends charts   (14-day orders + revenue)
 *            • Recent activity (orders, complaints, payments)
 *            • Top performers  (staff leaderboard)
 *            • Alerts          (pending payments, escalations, low stock)
 *
 * Notes:
 *   • Uses raw SQL for aggregates to avoid depending on other
 *     teammates' models being ready. Every table is from
 *     database/schema.sql.
 *   • All amounts assume PKR; symbol pulled from SystemConfig.
 * ----------------------------------------------------------
 */
class DashboardController extends Controller
{
    /** Lookback window (days) for dashboard charts */
    private const TREND_DAYS = 14;

    /** How many rows to show in "recent" widgets */
    private const RECENT_LIMIT = 8;

    // =========================================================
    //  INDEX
    // =========================================================
    public function index(): void
    {
        if (!$this->isAdmin()) {
            $this->forbidden();
            return;
        }

        $data = [
            'currency'          => (string) SystemConfig::get('currency_symbol', 'Rs.'),
            'businessName'      => (string) SystemConfig::get('business_name', 'LaundryPro'),

            // KPI cards
            'kpis'              => $this->kpis(),

            // Today's operations
            'todayOps'          => $this->todayOps(),

            // Revenue windows
            'revenue'           => $this->revenueWindows(),

            // Trends (last N days)
            'orderTrend'        => $this->orderTrend(self::TREND_DAYS),
            'revenueTrend'      => $this->revenueTrend(self::TREND_DAYS),

            // Status breakdowns
            'orderStatusCounts' => $this->orderStatusCounts(),
            'complaintCounts'   => $this->complaintStatusCounts(),

            // Recent activity
            'recentOrders'      => $this->recentOrders(self::RECENT_LIMIT),
            'recentComplaints'  => $this->recentComplaints(self::RECENT_LIMIT),
            'recentPayments'    => $this->recentPayments(self::RECENT_LIMIT),

            // Top performers
            'topPerformers'     => $this->topPerformers(5),

            // Alerts
            'alerts'            => $this->alerts(),
        ];

        $this->view('admin/dashboard/index', $data);
    }

    // =========================================================
    //  KPI CARDS
    // =========================================================
    private function kpis(): array
    {
        // Customers
        $cust = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN account_status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN account_status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS new_today
             FROM customer"
        )->fetch() ?: [];

        // Staff
        $staff = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active
             FROM staff"
        )->fetch() ?: [];

        // Orders
        $orders = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN DATE(order_date) = CURDATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN order_status NOT IN ('delivered','cancelled')
                         THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
             FROM orders"
        )->fetch() ?: [];

        // Complaints
        $complaints = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('open','assigned','under_investigation','escalated')
                         THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) AS escalated,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved
             FROM complaint"
        )->fetch() ?: [];

        return [
            'customers' => [
                'total'     => (int) ($cust['total']     ?? 0),
                'active'    => (int) ($cust['active']    ?? 0),
                'pending'   => (int) ($cust['pending']   ?? 0),
                'new_today' => (int) ($cust['new_today'] ?? 0),
            ],
            'staff' => [
                'total'  => (int) ($staff['total']  ?? 0),
                'active' => (int) ($staff['active'] ?? 0),
            ],
            'orders' => [
                'total'     => (int) ($orders['total']     ?? 0),
                'today'     => (int) ($orders['today']     ?? 0),
                'active'    => (int) ($orders['active']    ?? 0),
                'delivered' => (int) ($orders['delivered'] ?? 0),
                'cancelled' => (int) ($orders['cancelled'] ?? 0),
            ],
            'complaints' => [
                'total'     => (int) ($complaints['total']     ?? 0),
                'active'    => (int) ($complaints['active']    ?? 0),
                'escalated' => (int) ($complaints['escalated'] ?? 0),
                'resolved'  => (int) ($complaints['resolved']  ?? 0),
            ],
        ];
    }

    // =========================================================
    //  TODAY'S OPERATIONS
    // =========================================================
    private function todayOps(): array
    {
        // Pickups today
        $pickups = $this->db->query(
            "SELECT
                SUM(CASE WHEN status = 'requested' THEN 1 ELSE 0 END) AS requested,
                SUM(CASE WHEN status = 'assigned'  THEN 1 ELSE 0 END) AS assigned,
                SUM(CASE WHEN status = 'picked_up' THEN 1 ELSE 0 END) AS picked_up,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COUNT(*) AS total
             FROM pickup_request
             WHERE pickup_date = CURDATE()"
        )->fetch() ?: [];

        // Deliveries today
        $deliveries = $this->db->query(
            "SELECT
                SUM(CASE WHEN status = 'scheduled'        THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) AS out_for_delivery,
                SUM(CASE WHEN status = 'delivered'        THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = 'failed'           THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'rescheduled'      THEN 1 ELSE 0 END) AS rescheduled,
                COUNT(*) AS total
             FROM delivery
             WHERE delivery_date = CURDATE()"
        )->fetch() ?: [];

        // Orders in each laundry stage right now (not today-bound)
        $pipeline = $this->db->query(
            "SELECT order_status, COUNT(*) AS total
             FROM orders
             WHERE order_status NOT IN ('delivered','cancelled')
             GROUP BY order_status"
        )->fetchAll();

        $pipelineMap = [];
        foreach ($pipeline as $r) {
            $pipelineMap[$r['order_status']] = (int) $r['total'];
        }

        return [
            'pickups' => [
                'requested' => (int) ($pickups['requested'] ?? 0),
                'assigned'  => (int) ($pickups['assigned']  ?? 0),
                'picked_up' => (int) ($pickups['picked_up'] ?? 0),
                'cancelled' => (int) ($pickups['cancelled'] ?? 0),
                'total'     => (int) ($pickups['total']     ?? 0),
            ],
            'deliveries' => [
                'scheduled'        => (int) ($deliveries['scheduled']        ?? 0),
                'out_for_delivery' => (int) ($deliveries['out_for_delivery'] ?? 0),
                'delivered'        => (int) ($deliveries['delivered']        ?? 0),
                'failed'           => (int) ($deliveries['failed']           ?? 0),
                'rescheduled'      => (int) ($deliveries['rescheduled']      ?? 0),
                'total'            => (int) ($deliveries['total']            ?? 0),
            ],
            'pipeline' => [
                'pickup_requested'    => $pipelineMap['pickup_requested']    ?? 0,
                'pickup_assigned'     => $pipelineMap['pickup_assigned']     ?? 0,
                'picked_up'           => $pipelineMap['picked_up']           ?? 0,
                'received_at_laundry' => $pipelineMap['received_at_laundry'] ?? 0,
                'washing'             => $pipelineMap['washing']             ?? 0,
                'ironing'             => $pipelineMap['ironing']             ?? 0,
                'ready'               => $pipelineMap['ready']               ?? 0,
                'out_for_delivery'    => $pipelineMap['out_for_delivery']    ?? 0,
            ],
        ];
    }

    // =========================================================
    //  REVENUE WINDOWS (from approved payments only)
    // =========================================================
    private function revenueWindows(): array
    {
        $row = $this->db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN DATE(approved_at) = CURDATE() THEN amount END), 0) AS today,
                COALESCE(SUM(CASE WHEN approved_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN amount END), 0) AS last_7_days,
                COALESCE(SUM(CASE WHEN YEARWEEK(approved_at, 1) = YEARWEEK(CURDATE(), 1) THEN amount END), 0) AS this_week,
                COALESCE(SUM(CASE WHEN MONTH(approved_at) = MONTH(CURDATE())
                                   AND YEAR(approved_at)  = YEAR(CURDATE()) THEN amount END), 0) AS this_month,
                COALESCE(SUM(amount), 0) AS all_time
             FROM payment
             WHERE status = 'approved'"
        )->fetch() ?: [];

        // Outstanding receivables (unpaid + partially paid bills)
        $outstanding = $this->db->query(
            "SELECT COALESCE(SUM(outstanding_amount), 0) AS total
             FROM bill
             WHERE payment_status IN ('unpaid','partially_paid')"
        )->fetchColumn();

        // Refunds in the last 30 days
        $refunds = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM refund
             WHERE status IN ('approved','completed')
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        )->fetchColumn();

        return [
            'today'         => (float) ($row['today']       ?? 0),
            'last_7_days'   => (float) ($row['last_7_days'] ?? 0),
            'this_week'     => (float) ($row['this_week']   ?? 0),
            'this_month'    => (float) ($row['this_month']  ?? 0),
            'all_time'      => (float) ($row['all_time']    ?? 0),
            'outstanding'   => (float) $outstanding,
            'refunds_30d'   => (float) $refunds,
        ];
    }

    // =========================================================
    //  TRENDS (for charts)
    // =========================================================

    /**
     * Orders per day for the last N days, keyed by date.
     * Fills gaps with 0 so the chart is continuous.
     */
    private function orderTrend(int $days): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(order_date) AS d, COUNT(*) AS total
             FROM orders
             WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(order_date)
             ORDER BY d ASC"
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['d']] = (int) $r['total'];
        }

        return $this->fillDateRange($byDate, $days, 0);
    }

    /**
     * Revenue per day for the last N days (approved payments only).
     */
    private function revenueTrend(int $days): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(approved_at) AS d, COALESCE(SUM(amount), 0) AS total
             FROM payment
             WHERE status = 'approved'
               AND approved_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(approved_at)
             ORDER BY d ASC"
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['d']] = (float) $r['total'];
        }

        return $this->fillDateRange($byDate, $days, 0.0);
    }

    /**
     * Helper: ensure the date range is continuous for charts.
     */
    private function fillDateRange(array $byDate, int $days, mixed $default): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} day"));
            $out[] = [
                'date'  => $date,
                'label' => date('M j', strtotime($date)),
                'value' => $byDate[$date] ?? $default,
            ];
        }
        return $out;
    }

    // =========================================================
    //  STATUS BREAKDOWNS
    // =========================================================
    private function orderStatusCounts(): array
    {
        $rows = $this->db->query(
            "SELECT order_status, COUNT(*) AS total
             FROM orders
             GROUP BY order_status"
        )->fetchAll();

        $map = [
            'pickup_requested'    => 0,
            'pickup_assigned'     => 0,
            'picked_up'           => 0,
            'received_at_laundry' => 0,
            'washing'             => 0,
            'ironing'             => 0,
            'ready'               => 0,
            'out_for_delivery'    => 0,
            'delivered'           => 0,
            'cancelled'           => 0,
        ];
        foreach ($rows as $r) {
            $map[$r['order_status']] = (int) $r['total'];
        }
        return $map;
    }

    private function complaintStatusCounts(): array
    {
        $rows = $this->db->query(
            "SELECT status, COUNT(*) AS total
             FROM complaint
             GROUP BY status"
        )->fetchAll();

        $map = [
            'open'                => 0,
            'assigned'            => 0,
            'under_investigation' => 0,
            'resolved'            => 0,
            'rejected'            => 0,
            'escalated'           => 0,
            'closed'              => 0,
        ];
        foreach ($rows as $r) {
            $map[$r['status']] = (int) $r['total'];
        }
        return $map;
    }

    // =========================================================
    //  RECENT ACTIVITY
    // =========================================================
    private function recentOrders(int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.order_id, o.order_number, o.order_status,
                    o.order_date, o.delivery_date,
                    c.full_name AS customer_name,
                    (SELECT COUNT(*) FROM order_item oi WHERE oi.order_id = o.order_id) AS item_count
             FROM orders o
             JOIN customer c ON c.customer_id = o.customer_id
             ORDER BY o.order_date DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function recentComplaints(int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT cm.complaint_id, cm.complaint_number, cm.type, cm.status,
                    cm.created_at,
                    c.full_name AS customer_name,
                    o.order_number
             FROM complaint cm
             JOIN customer c ON c.customer_id = cm.customer_id
             JOIN orders   o ON o.order_id    = cm.order_id
             ORDER BY cm.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function recentPayments(int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.payment_id, p.amount, p.payment_method, p.status,
                    p.created_at, p.approved_at,
                    o.order_number,
                    c.full_name AS customer_name
             FROM payment p
             JOIN orders   o ON o.order_id    = p.order_id
             JOIN customer c ON c.customer_id = o.customer_id
             ORDER BY p.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =========================================================
    //  TOP PERFORMERS
    // =========================================================
    private function topPerformers(int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT sp.staff_id, sp.pickups_completed, sp.deliveries_completed,
                    sp.orders_handled, sp.complaints_resolved,
                    sp.average_rating, sp.performance_score,
                    s.full_name, s.email
             FROM staff_performance sp
             JOIN staff s ON s.staff_id = sp.staff_id
             WHERE sp.recorded_date = CURDATE()
               AND s.is_active = 1
             ORDER BY sp.performance_score DESC,
                      sp.deliveries_completed DESC,
                      sp.orders_handled DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =========================================================
    //  ALERTS
    // =========================================================
    private function alerts(): array
    {
        $alerts = [];

        // 1. Payments awaiting verification/approval
        $pendingPayments = (int) $this->db->query(
            "SELECT COUNT(*) FROM payment
             WHERE status IN ('pending_verification','verified')"
        )->fetchColumn();
        if ($pendingPayments > 0) {
            $alerts[] = [
                'level'   => 'warning',
                'icon'    => 'bi-cash-coin',
                'message' => "{$pendingPayments} payment(s) awaiting verification.",
                'link'    => '/admin/payments',
                'count'   => $pendingPayments,
            ];
        }

        // 2. Escalated complaints
        $escalated = (int) $this->db->query(
            "SELECT COUNT(*) FROM complaint WHERE status = 'escalated'"
        )->fetchColumn();
        if ($escalated > 0) {
            $alerts[] = [
                'level'   => 'danger',
                'icon'    => 'bi-exclamation-triangle',
                'message' => "{$escalated} complaint(s) escalated to admin.",
                'link'    => '/admin/complaints?status=escalated',
                'count'   => $escalated,
            ];
        }

        // 3. Pending refund requests
        $pendingRefunds = (int) $this->db->query(
            "SELECT COUNT(*) FROM refund WHERE status = 'pending'"
        )->fetchColumn();
        if ($pendingRefunds > 0) {
            $alerts[] = [
                'level'   => 'warning',
                'icon'    => 'bi-arrow-counterclockwise',
                'message' => "{$pendingRefunds} refund request(s) pending approval.",
                'link'    => '/admin/refunds?status=pending',
                'count'   => $pendingRefunds,
            ];
        }

        // 4. Account deletion requests
        $deletionRequests = (int) $this->db->query(
            "SELECT COUNT(*) FROM account_deletion_request WHERE status = 'pending'"
        )->fetchColumn();
        if ($deletionRequests > 0) {
            $alerts[] = [
                'level'   => 'info',
                'icon'    => 'bi-person-x',
                'message' => "{$deletionRequests} account deletion request(s) pending.",
                'link'    => '/admin/customers/deletion-requests',
                'count'   => $deletionRequests,
            ];
        }

        // 5. Overdue deliveries (scheduled for past dates, still open)
        $overdue = (int) $this->db->query(
            "SELECT COUNT(*) FROM delivery
             WHERE delivery_date < CURDATE()
               AND status IN ('scheduled','out_for_delivery')"
        )->fetchColumn();
        if ($overdue > 0) {
            $alerts[] = [
                'level'   => 'danger',
                'icon'    => 'bi-truck',
                'message' => "{$overdue} delivery(ies) overdue from previous days.",
                'link'    => '/admin/pickups/monitor',
                'count'   => $overdue,
            ];
        }

        // 6. Orders stuck in 'ready' for > 3 days (waiting for delivery scheduling)
        $stuckReady = (int) $this->db->query(
            "SELECT COUNT(*) FROM orders
             WHERE order_status = 'ready'
               AND ready_at < DATE_SUB(NOW(), INTERVAL 3 DAY)"
        )->fetchColumn();
        if ($stuckReady > 0) {
            $alerts[] = [
                'level'   => 'warning',
                'icon'    => 'bi-hourglass-split',
                'message' => "{$stuckReady} order(s) ready but not dispatched for 3+ days.",
                'link'    => '/admin/orders?status=ready',
                'count'   => $stuckReady,
            ];
        }

        // 7. Unassigned pickups scheduled for today
        $unassignedPickups = (int) $this->db->query(
            "SELECT COUNT(*) FROM pickup_request
             WHERE pickup_date = CURDATE()
               AND status = 'requested'
               AND assigned_staff_id IS NULL"
        )->fetchColumn();
        if ($unassignedPickups > 0) {
            $alerts[] = [
                'level'   => 'warning',
                'icon'    => 'bi-person-plus',
                'message' => "{$unassignedPickups} today pickup(s) unassigned.",
                'link'    => '/admin/pickups/monitor',
                'count'   => $unassignedPickups,
            ];
        }

        return $alerts;
    }

    // =========================================================
    //  HELPERS
    // =========================================================
    private function isAdmin(): bool
    {
        return ($_SESSION['staff_role'] ?? '') === 'admin';
    }
}
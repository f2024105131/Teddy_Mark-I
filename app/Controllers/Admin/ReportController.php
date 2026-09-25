<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin\ReportController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Analytics + report generation for admin.
 *            • Sales / revenue report
 *            • Orders report (by status, service, item)
 *            • Customer report (new, active, top spenders)
 *            • Staff performance report
 *            • Complaint report (by type, category, resolution time)
 *            • Refund report
 *            • Item / service popularity report
 *          Each report:
 *            • has filters (date range, grouping)
 *            • has a JSON endpoint for chart widgets
 *            • exports as CSV
 *
 * Notes:
 *   • Read-only. Never writes to the DB.
 *   • Uses raw SQL — no dependency on models.
 *   • All filters use prepared statements.
 *   • Aggregates are seeded with 0 so views get stable shapes.
 * ----------------------------------------------------------
 */
class ReportController extends Controller
{
    /** Default date range when none provided (days) */
    private const DEFAULT_RANGE_DAYS = 30;

    /** Hard cap on rows returned for CSV exports */
    private const CSV_ROW_LIMIT = 50000;

    // =========================================================
    //  REPORT HUB — landing page
    // =========================================================
    public function index(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $snapshot = $this->overviewSnapshot($from, $to);

        $this->view('admin/reports/index', [
            'from'     => $from,
            'to'       => $to,
            'snapshot' => $snapshot,
            'reports'  => [
                ['slug' => 'sales',              'name' => 'Sales & Revenue',       'icon' => 'bi-cash-coin',      'desc' => 'Revenue, taxes, refunds, and payment breakdown'],
                ['slug' => 'orders',             'name' => 'Orders',                'icon' => 'bi-bag-check',      'desc' => 'Volume, status breakdown, service mix'],
                ['slug' => 'customers',          'name' => 'Customers',             'icon' => 'bi-people',         'desc' => 'New signups, activity, top spenders'],
                ['slug' => 'staff-performance',  'name' => 'Staff Performance',     'icon' => 'bi-person-badge',   'desc' => 'Pickups, deliveries, complaints resolved'],
                ['slug' => 'complaints',         'name' => 'Complaints',            'icon' => 'bi-exclamation-circle', 'desc' => 'Types, categories, resolution times'],
                ['slug' => 'refunds',            'name' => 'Refunds',               'icon' => 'bi-arrow-counterclockwise', 'desc' => 'Requested, approved, completed'],
                ['slug' => 'items',              'name' => 'Item Popularity',       'icon' => 'bi-basket',         'desc' => 'Most-ordered items and services'],
                ['slug' => 'deliveries',         'name' => 'Deliveries',            'icon' => 'bi-truck',          'desc' => 'Success rate, delays, per-staff load'],
            ],
        ]);
    }

    // =========================================================
    //  OVERVIEW JSON — for dashboard widgets
    // =========================================================
    public function overview(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $this->jsonSuccess([
            'from'     => $from,
            'to'       => $to,
            'snapshot' => $this->overviewSnapshot($from, $to),
            'timestamp'=> date('c'),
        ]);
    }

    // =========================================================
    //  SALES / REVENUE REPORT
    // =========================================================
    public function sales(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();
        $groupBy     = $_GET['group'] ?? 'day'; // day | week | month

        $summary = $this->salesSummary($from, $to);

        $revenueByPeriod = $this->revenueByPeriod($from, $to, $groupBy);
        $revenueByMethod = $this->revenueByMethod($from, $to);

        $this->view('admin/reports/sales', [
            'from'             => $from,
            'to'               => $to,
            'groupBy'          => $groupBy,
            'summary'          => $summary,
            'revenueByPeriod'  => $revenueByPeriod,
            'revenueByMethod'  => $revenueByMethod,
        ]);
    }

    public function salesJson(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();
        $groupBy     = $_GET['group'] ?? 'day';

        $this->jsonSuccess([
            'from'             => $from,
            'to'               => $to,
            'groupBy'          => $groupBy,
            'summary'          => $this->salesSummary($from, $to),
            'revenueByPeriod'  => $this->revenueByPeriod($from, $to, $groupBy),
            'revenueByMethod'  => $this->revenueByMethod($from, $to),
        ]);
    }

    // =========================================================
    //  ORDERS REPORT
    // =========================================================
    public function orders(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $summary      = $this->ordersSummary($from, $to);
        $statusCounts = $this->orderStatusCounts($from, $to);
        $serviceMix   = $this->serviceMix($from, $to);
        $trend        = $this->orderTrend($from, $to);

        $this->view('admin/reports/orders', [
            'from'         => $from,
            'to'           => $to,
            'summary'      => $summary,
            'statusCounts' => $statusCounts,
            'serviceMix'   => $serviceMix,
            'trend'        => $trend,
        ]);
    }

    public function ordersJson(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $this->jsonSuccess([
            'from'         => $from,
            'to'           => $to,
            'summary'      => $this->ordersSummary($from, $to),
            'statusCounts' => $this->orderStatusCounts($from, $to),
            'serviceMix'   => $this->serviceMix($from, $to),
            'trend'        => $this->orderTrend($from, $to),
        ]);
    }

    // =========================================================
    //  CUSTOMERS REPORT
    // =========================================================
    public function customers(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $summary  = $this->customersSummary($from, $to);
        $signupTrend = $this->customerSignupTrend($from, $to);
        $topSpenders = $this->topSpendingCustomers($from, $to, 20);
        $cityBreakdown = $this->customersByCity($from, $to);

        $this->view('admin/reports/customers', [
            'from'          => $from,
            'to'            => $to,
            'summary'       => $summary,
            'signupTrend'   => $signupTrend,
            'topSpenders'   => $topSpenders,
            'cityBreakdown' => $cityBreakdown,
        ]);
    }

    public function customersJson(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $this->jsonSuccess([
            'from'          => $from,
            'to'            => $to,
            'summary'       => $this->customersSummary($from, $to),
            'signupTrend'   => $this->customerSignupTrend($from, $to),
            'topSpenders'   => $this->topSpendingCustomers($from, $to, 20),
            'cityBreakdown' => $this->customersByCity($from, $to),
        ]);
    }

    // =========================================================
    //  STAFF PERFORMANCE REPORT
    // =========================================================
    public function staffPerformance(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $summary   = $this->staffPerformanceSummary($from, $to);
        $perStaff  = $this->staffPerformancePerStaff($from, $to);
        $trend     = $this->staffPerformanceTrend($from, $to);

        $this->view('admin/reports/staff_performance', [
            'from'      => $from,
            'to'        => $to,
            'summary'   => $summary,
            'perStaff'  => $perStaff,
            'trend'     => $trend,
        ]);
    }

    public function staffPerformanceJson(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $this->jsonSuccess([
            'from'     => $from,
            'to'       => $to,
            'summary'  => $this->staffPerformanceSummary($from, $to),
            'perStaff' => $this->staffPerformancePerStaff($from, $to),
            'trend'    => $this->staffPerformanceTrend($from, $to),
        ]);
    }

    // =========================================================
    //  COMPLAINTS REPORT
    // =========================================================
    public function complaints(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $summary         = $this->complaintsSummary($from, $to);
        $byType          = $this->complaintsByType($from, $to);
        $byCategory      = $this->complaintsByCategory($from, $to);
        $resolutionTimes = $this->complaintResolutionTimes($from, $to);
        $trend           = $this->complaintTrend($from, $to);

        $this->view('admin/reports/complaints', [
            'from'            => $from,
            'to'              => $to,
            'summary'         => $summary,
            'byType'          => $byType,
            'byCategory'      => $byCategory,
            'resolutionTimes' => $resolutionTimes,
            'trend'           => $trend,
        ]);
    }

    public function complaintsJson(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $this->jsonSuccess([
            'from'            => $from,
            'to'              => $to,
            'summary'         => $this->complaintsSummary($from, $to),
            'byType'          => $this->complaintsByType($from, $to),
            'byCategory'      => $this->complaintsByCategory($from, $to),
            'resolutionTimes' => $this->complaintResolutionTimes($from, $to),
            'trend'           => $this->complaintTrend($from, $to),
        ]);
    }

    // =========================================================
    //  REFUNDS REPORT
    // =========================================================
    public function refunds(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $summary = $this->refundsSummary($from, $to);
        $byStatus = $this->refundsByStatus($from, $to);
        $recent = $this->recentRefunds($from, $to, 50);

        $this->view('admin/reports/refunds', [
            'from'     => $from,
            'to'       => $to,
            'summary'  => $summary,
            'byStatus' => $byStatus,
            'recent'   => $recent,
        ]);
    }

    public function refundsJson(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $this->jsonSuccess([
            'from'     => $from,
            'to'       => $to,
            'summary'  => $this->refundsSummary($from, $to),
            'byStatus' => $this->refundsByStatus($from, $to),
            'recent'   => $this->recentRefunds($from, $to, 50),
        ]);
    }

    // =========================================================
    //  ITEM / SERVICE POPULARITY
    // =========================================================
    public function items(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $summary     = $this->itemsSummary($from, $to);
        $topItems    = $this->topItems($from, $to, 20);
        $topServices = $this->topServices($from, $to, 20);
        $byCategory  = $this->itemsByCategory($from, $to);

        $this->view('admin/reports/items', [
            'from'        => $from,
            'to'          => $to,
            'summary'     => $summary,
            'topItems'    => $topItems,
            'topServices' => $topServices,
            'byCategory'  => $byCategory,
        ]);
    }

    public function itemsJson(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $this->jsonSuccess([
            'from'        => $from,
            'to'          => $to,
            'summary'     => $this->itemsSummary($from, $to),
            'topItems'    => $this->topItems($from, $to, 20),
            'topServices' => $this->topServices($from, $to, 20),
            'byCategory'  => $this->itemsByCategory($from, $to),
        ]);
    }

    // =========================================================
    //  DELIVERIES REPORT
    // =========================================================
    public function deliveries(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $summary  = $this->deliveriesSummary($from, $to);
        $perStaff = $this->deliveriesPerStaff($from, $to);
        $trend    = $this->deliveriesTrend($from, $to);

        $this->view('admin/reports/deliveries', [
            'from'     => $from,
            'to'       => $to,
            'summary'  => $summary,
            'perStaff' => $perStaff,
            'trend'    => $trend,
        ]);
    }

    public function deliveriesJson(): void
    {
        $this->requireAdmin();

        [$from, $to] = $this->resolveDateRange();

        $this->jsonSuccess([
            'from'     => $from,
            'to'       => $to,
            'summary'  => $this->deliveriesSummary($from, $to),
            'perStaff' => $this->deliveriesPerStaff($from, $to),
            'trend'    => $this->deliveriesTrend($from, $to),
        ]);
    }

    // =========================================================
    //  CSV EXPORTS
    // =========================================================
    public function export(): void
    {
        $this->requireAdmin();

        $report = $_GET['report'] ?? '';
        $allowed = ['sales', 'orders', 'customers', 'staff-performance',
                    'complaints', 'refunds', 'items', 'deliveries'];

        if (!in_array($report, $allowed, true)) {
            $this->jsonError('Invalid report type.', 422);
            return;
        }

        [$from, $to] = $this->resolveDateRange();

        $filename = $report . '_' . $from . '_to_' . $to . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        // Meta header
        fputcsv($out, ['Report', $report]);
        fputcsv($out, ['From',   $from]);
        fputcsv($out, ['To',     $to]);
        fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
        fputcsv($out, []);

        switch ($report) {
            case 'sales':             $this->exportSales($out, $from, $to);             break;
            case 'orders':            $this->exportOrders($out, $from, $to);            break;
            case 'customers':         $this->exportCustomers($out, $from, $to);         break;
            case 'staff-performance': $this->exportStaffPerformance($out, $from, $to);  break;
            case 'complaints':        $this->exportComplaints($out, $from, $to);        break;
            case 'refunds':           $this->exportRefunds($out, $from, $to);           break;
            case 'items':             $this->exportItems($out, $from, $to);             break;
            case 'deliveries':        $this->exportDeliveries($out, $from, $to);        break;
        }

        fclose($out);
        exit;
    }

    // =========================================================
    //  OVERVIEW SNAPSHOT
    // =========================================================
    private function overviewSnapshot(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM orders
                  WHERE DATE(order_date) BETWEEN :from AND :to) AS orders_count,
                (SELECT COUNT(*) FROM customer
                  WHERE DATE(created_at) BETWEEN :from AND :to) AS new_customers,
                (SELECT COALESCE(SUM(amount), 0) FROM payment
                  WHERE status = 'approved'
                    AND DATE(approved_at) BETWEEN :from AND :to) AS revenue,
                (SELECT COUNT(*) FROM complaint
                  WHERE DATE(created_at) BETWEEN :from AND :to) AS complaints_count,
                (SELECT COALESCE(SUM(amount), 0) FROM refund
                  WHERE status IN ('approved','completed')
                    AND DATE(created_at) BETWEEN :from AND :to) AS refunds_amount,
                (SELECT COUNT(*) FROM delivery
                  WHERE DATE(delivery_date) BETWEEN :from AND :to) AS deliveries_count"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        // Previous period comparison (equal length, ending day before $from)
        $rangeDays = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
        $prevTo    = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom  = date('Y-m-d', strtotime($prevTo . ' -' . ($rangeDays - 1) . ' days'));

        $prevStmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM orders
                  WHERE DATE(order_date) BETWEEN :from AND :to) AS orders_count,
                (SELECT COALESCE(SUM(amount), 0) FROM payment
                  WHERE status = 'approved'
                    AND DATE(approved_at) BETWEEN :from AND :to) AS revenue"
        );
        $prevStmt->execute(['from' => $prevFrom, 'to' => $prevTo]);
        $prev = $prevStmt->fetch() ?: [];

        $ordersCount = (int) ($row['orders_count']    ?? 0);
        $revenue     = (float) ($row['revenue']       ?? 0);
        $prevOrders  = (int) ($prev['orders_count']   ?? 0);
        $prevRevenue = (float) ($prev['revenue']      ?? 0);

        return [
            'orders_count'      => $ordersCount,
            'new_customers'     => (int) ($row['new_customers']    ?? 0),
            'revenue'           => $revenue,
            'complaints_count'  => (int) ($row['complaints_count'] ?? 0),
            'refunds_amount'    => (float) ($row['refunds_amount'] ?? 0),
            'deliveries_count'  => (int) ($row['deliveries_count'] ?? 0),
            'prev_from'         => $prevFrom,
            'prev_to'           => $prevTo,
            'orders_change_pct' => $this->pctChange($ordersCount, $prevOrders),
            'revenue_change_pct'=> $this->pctChange($revenue, $prevRevenue),
        ];
    }

    // =========================================================
    //  SALES AGGREGATES
    // =========================================================
    private function salesSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN p.status = 'approved' THEN p.amount END), 0) AS approved_revenue,
                COALESCE(SUM(CASE WHEN p.status = 'pending_verification' THEN p.amount END), 0) AS pending_revenue,
                COALESCE(SUM(CASE WHEN p.status = 'rejected' THEN p.amount END), 0) AS rejected_revenue,
                COUNT(CASE WHEN p.status = 'approved' THEN 1 END) AS approved_count,
                COUNT(CASE WHEN p.status = 'pending_verification' THEN 1 END) AS pending_count,
                COALESCE(AVG(CASE WHEN p.status = 'approved' THEN p.amount END), 0) AS avg_payment
             FROM payment p
             WHERE DATE(p.created_at) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $pay = $stmt->fetch() ?: [];

        // Bill-based aggregation
        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(b.subtotal), 0) AS total_subtotal,
                COALESCE(SUM(b.tax_amount), 0) AS total_tax,
                COALESCE(SUM(b.discount_amount), 0) AS total_discount,
                COALESCE(SUM(b.total_amount), 0) AS total_billed,
                COALESCE(SUM(b.paid_amount), 0) AS total_paid,
                COALESCE(SUM(b.outstanding_amount), 0) AS total_outstanding,
                COUNT(*) AS bill_count
             FROM bill b
             JOIN orders o ON o.order_id = b.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $bill = $stmt->fetch() ?: [];

        // Refunds within range
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS refunds
             FROM refund
             WHERE status IN ('approved','completed')
               AND DATE(created_at) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $refunds = (float) $stmt->fetchColumn();

        return [
            'approved_revenue'  => (float) ($pay['approved_revenue']  ?? 0),
            'pending_revenue'   => (float) ($pay['pending_revenue']   ?? 0),
            'rejected_revenue'  => (float) ($pay['rejected_revenue']  ?? 0),
            'approved_count'    => (int)   ($pay['approved_count']    ?? 0),
            'pending_count'     => (int)   ($pay['pending_count']     ?? 0),
            'avg_payment'       => (float) ($pay['avg_payment']       ?? 0),
            'total_subtotal'    => (float) ($bill['total_subtotal']   ?? 0),
            'total_tax'         => (float) ($bill['total_tax']        ?? 0),
            'total_discount'    => (float) ($bill['total_discount']   ?? 0),
            'total_billed'      => (float) ($bill['total_billed']     ?? 0),
            'total_paid'        => (float) ($bill['total_paid']       ?? 0),
            'total_outstanding' => (float) ($bill['total_outstanding']?? 0),
            'bill_count'        => (int)   ($bill['bill_count']       ?? 0),
            'total_refunds'     => $refunds,
            'net_revenue'       => (float) ($pay['approved_revenue'] ?? 0) - $refunds,
        ];
    }

    private function revenueByPeriod(string $from, string $to, string $groupBy): array
    {
        $groupExpr = $this->groupExpr('DATE(p.approved_at)', $groupBy);

        $sql = "SELECT {$groupExpr} AS period,
                       COALESCE(SUM(p.amount), 0) AS total,
                       COUNT(*) AS payments
                FROM payment p
                WHERE p.status = 'approved'
                  AND DATE(p.approved_at) BETWEEN :from AND :to
                GROUP BY {$groupExpr}
                ORDER BY period ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['from' => $from, 'to' => $to]);

        return $stmt->fetchAll();
    }

    private function revenueByMethod(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT payment_method,
                    COUNT(*) AS payments,
                    COALESCE(SUM(amount), 0) AS total
             FROM payment
             WHERE status = 'approved'
               AND DATE(approved_at) BETWEEN :from AND :to
             GROUP BY payment_method
             ORDER BY total DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  ORDERS AGGREGATES
    // =========================================================
    private function ordersSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) AS delivered,
                COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) AS cancelled,
                COUNT(CASE WHEN order_status NOT IN ('delivered','cancelled') THEN 1 END) AS active
             FROM orders
             WHERE DATE(order_date) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        $total = (int) ($row['total'] ?? 0);
        $delivered = (int) ($row['delivered'] ?? 0);

        return [
            'total'        => $total,
            'delivered'    => $delivered,
            'cancelled'    => (int) ($row['cancelled'] ?? 0),
            'active'       => (int) ($row['active']    ?? 0),
            'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0.0,
        ];
    }

    private function orderStatusCounts(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT order_status, COUNT(*) AS total
             FROM orders
             WHERE DATE(order_date) BETWEEN :from AND :to
             GROUP BY order_status"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $rows = $stmt->fetchAll();

        $seed = [
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
            $seed[$r['order_status']] = (int) $r['total'];
        }
        return $seed;
    }

    private function serviceMix(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.service_id, s.service_name, s.service_type,
                    COUNT(DISTINCT oi.order_id) AS orders_count,
                    COALESCE(SUM(oi.quantity), 0) AS items_count,
                    COALESCE(SUM(oi.total_price), 0) AS revenue
             FROM order_item oi
             JOIN service s ON s.service_id = oi.service_id
             JOIN orders  o ON o.order_id   = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to
             GROUP BY s.service_id, s.service_name, s.service_type
             ORDER BY revenue DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    private function orderTrend(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(order_date) AS d,
                    COUNT(*) AS total,
                    COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) AS delivered,
                    COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) AS cancelled
             FROM orders
             WHERE DATE(order_date) BETWEEN :from AND :to
             GROUP BY DATE(order_date)
             ORDER BY d ASC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  CUSTOMERS AGGREGATES
    // =========================================================
    private function customersSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS new_customers,
                SUM(CASE WHEN account_status = 'active' THEN 1 ELSE 0 END) AS new_active
             FROM customer
             WHERE DATE(created_at) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $new = $stmt->fetch() ?: [];

        $total = (int) $this->db->query("SELECT COUNT(*) FROM customer")->fetchColumn();
        $active = (int) $this->db->query(
            "SELECT COUNT(*) FROM customer WHERE account_status = 'active'"
        )->fetchColumn();

        // Customers who placed at least one order in range (active buyers)
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT customer_id) FROM orders
             WHERE DATE(order_date) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $buyers = (int) $stmt->fetchColumn();

        // Repeat buyers (>=2 orders in range)
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM (
                SELECT customer_id, COUNT(*) AS c
                FROM orders
                WHERE DATE(order_date) BETWEEN :from AND :to
                GROUP BY customer_id
                HAVING c >= 2
             ) t"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $repeatBuyers = (int) $stmt->fetchColumn();

        return [
            'total_customers'  => $total,
            'active_customers' => $active,
            'new_customers'    => (int) ($new['new_customers'] ?? 0),
            'new_active'       => (int) ($new['new_active']    ?? 0),
            'buyers_in_range'  => $buyers,
            'repeat_buyers'    => $repeatBuyers,
            'repeat_rate_pct'  => $buyers > 0 ? round(($repeatBuyers / $buyers) * 100, 2) : 0.0,
        ];
    }

    private function customerSignupTrend(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) AS d, COUNT(*) AS total
             FROM customer
             WHERE DATE(created_at) BETWEEN :from AND :to
             GROUP BY DATE(created_at)
             ORDER BY d ASC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    private function topSpendingCustomers(string $from, string $to, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.customer_id, c.full_name, c.email, c.phone, c.city,
                    COUNT(DISTINCT o.order_id) AS orders_count,
                    COALESCE(SUM(p.amount), 0) AS total_spent
             FROM customer c
             JOIN orders  o ON o.customer_id = c.customer_id
             JOIN payment p ON p.order_id    = o.order_id
             WHERE p.status = 'approved'
               AND DATE(p.approved_at) BETWEEN :from AND :to
             GROUP BY c.customer_id, c.full_name, c.email, c.phone, c.city
             ORDER BY total_spent DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to',   $to);
        $stmt->bindValue(':lim',  $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function customersByCity(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT city, COUNT(*) AS total
             FROM customer
             WHERE DATE(created_at) BETWEEN :from AND :to
               AND city IS NOT NULL AND city <> ''
             GROUP BY city
             ORDER BY total DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  STAFF PERFORMANCE AGGREGATES
    // =========================================================
    private function staffPerformanceSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(pickups_completed), 0)     AS pickups,
                COALESCE(SUM(deliveries_completed), 0)  AS deliveries,
                COALESCE(SUM(orders_handled), 0)        AS orders,
                COALESCE(SUM(complaints_resolved), 0)   AS complaints_resolved,
                COALESCE(AVG(average_rating), 0)        AS avg_rating,
                COALESCE(AVG(performance_score), 0)     AS avg_score,
                COUNT(DISTINCT staff_id)                AS active_staff
             FROM staff_performance
             WHERE recorded_date BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        return [
            'pickups'             => (int)   ($row['pickups']             ?? 0),
            'deliveries'          => (int)   ($row['deliveries']          ?? 0),
            'orders'              => (int)   ($row['orders']              ?? 0),
            'complaints_resolved' => (int)   ($row['complaints_resolved'] ?? 0),
            'avg_rating'          => (float) ($row['avg_rating']          ?? 0),
            'avg_score'           => (float) ($row['avg_score']           ?? 0),
            'active_staff'        => (int)   ($row['active_staff']        ?? 0),
        ];
    }

    private function staffPerformancePerStaff(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.staff_id, s.full_name, s.email, s.role, s.is_active,
                    COALESCE(SUM(sp.pickups_completed), 0)     AS pickups,
                    COALESCE(SUM(sp.deliveries_completed), 0)  AS deliveries,
                    COALESCE(SUM(sp.orders_handled), 0)        AS orders,
                    COALESCE(SUM(sp.complaints_resolved), 0)   AS complaints_resolved,
                    COALESCE(AVG(sp.average_rating), 0)        AS avg_rating,
                    COALESCE(AVG(sp.performance_score), 0)     AS avg_score,
                    COUNT(sp.performance_id)                   AS days_recorded
             FROM staff s
             LEFT JOIN staff_performance sp
                    ON sp.staff_id = s.staff_id
                   AND sp.recorded_date BETWEEN :from AND :to
             GROUP BY s.staff_id, s.full_name, s.email, s.role, s.is_active
             ORDER BY avg_score DESC, deliveries DESC, pickups DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    private function staffPerformanceTrend(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT recorded_date AS d,
                    SUM(pickups_completed)     AS pickups,
                    SUM(deliveries_completed)  AS deliveries,
                    SUM(orders_handled)        AS orders,
                    AVG(performance_score)     AS avg_score
             FROM staff_performance
             WHERE recorded_date BETWEEN :from AND :to
             GROUP BY recorded_date
             ORDER BY d ASC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  COMPLAINTS AGGREGATES
    // =========================================================
    private function complaintsSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('open','assigned','under_investigation','escalated') THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) AS escalated,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed
             FROM complaint
             WHERE DATE(created_at) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        $total    = (int) ($row['total'] ?? 0);
        $resolved = (int) ($row['resolved'] ?? 0);

        return [
            'total'           => $total,
            'active'          => (int) ($row['active']    ?? 0),
            'resolved'        => $resolved,
            'rejected'        => (int) ($row['rejected']  ?? 0),
            'escalated'       => (int) ($row['escalated'] ?? 0),
            'closed'          => (int) ($row['closed']    ?? 0),
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 2) : 0.0,
        ];
    }

    private function complaintsByType(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT type, COUNT(*) AS total
             FROM complaint
             WHERE DATE(created_at) BETWEEN :from AND :to
             GROUP BY type
             ORDER BY total DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    private function complaintsByCategory(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT cc.category_id, cc.category_name, COUNT(cm.complaint_id) AS total
             FROM complaint cm
             LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
             WHERE DATE(cm.created_at) BETWEEN :from AND :to
             GROUP BY cc.category_id, cc.category_name
             ORDER BY total DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    private function complaintResolutionTimes(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)), 0) AS avg_hours,
                COALESCE(MIN(TIMESTAMPDIFF(HOUR, created_at, resolved_at)), 0) AS min_hours,
                COALESCE(MAX(TIMESTAMPDIFF(HOUR, created_at, resolved_at)), 0) AS max_hours,
                COUNT(CASE WHEN resolved_at IS NOT NULL THEN 1 END) AS resolved_count
             FROM complaint
             WHERE DATE(created_at) BETWEEN :from AND :to
               AND resolved_at IS NOT NULL"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        return [
            'avg_hours'      => round((float) ($row['avg_hours'] ?? 0), 2),
            'min_hours'      => (int) ($row['min_hours'] ?? 0),
            'max_hours'      => (int) ($row['max_hours'] ?? 0),
            'resolved_count' => (int) ($row['resolved_count'] ?? 0),
        ];
    }

    private function complaintTrend(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) AS d,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) AS completed
             FROM complaint
             WHERE DATE(created_at) BETWEEN :from AND :to
             GROUP BY DATE(created_at)
             ORDER BY d ASC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  REFUNDS AGGREGATES
    // =========================================================
    private function refundsSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'pending'   THEN amount END), 0) AS pending_amount,
                COALESCE(SUM(CASE WHEN status = 'approved'  THEN amount END), 0) AS approved_amount,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN amount END), 0) AS completed_amount,
                COALESCE(SUM(CASE WHEN status = 'rejected'  THEN amount END), 0) AS rejected_amount,
                COUNT(CASE WHEN status = 'pending'   THEN 1 END) AS pending_count,
                COUNT(CASE WHEN status = 'approved'  THEN 1 END) AS approved_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
                COUNT(CASE WHEN status = 'rejected'  THEN 1 END) AS rejected_count
             FROM refund
             WHERE DATE(created_at) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        return [
            'total'            => (int)   ($row['total']            ?? 0),
            'pending_amount'   => (float) ($row['pending_amount']   ?? 0),
            'approved_amount'  => (float) ($row['approved_amount']  ?? 0),
            'completed_amount' => (float) ($row['completed_amount'] ?? 0),
            'rejected_amount'  => (float) ($row['rejected_amount']  ?? 0),
            'pending_count'    => (int)   ($row['pending_count']    ?? 0),
            'approved_count'   => (int)   ($row['approved_count']   ?? 0),
            'completed_count'  => (int)   ($row['completed_count']  ?? 0),
            'rejected_count'   => (int)   ($row['rejected_count']   ?? 0),
        ];
    }

    private function refundsByStatus(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT status, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
             FROM refund
             WHERE DATE(created_at) BETWEEN :from AND :to
             GROUP BY status"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    private function recentRefunds(string $from, string $to, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.refund_id, r.amount, r.status, r.refund_reason,
                    r.created_at, r.approved_at, r.completed_at,
                    cm.complaint_number, cm.type AS complaint_type,
                    o.order_number,
                    c.full_name AS customer_name,
                    st.full_name AS approved_by_name
             FROM refund r
             JOIN complaint cm ON cm.complaint_id = r.complaint_id
             JOIN orders    o  ON o.order_id      = r.order_id
             JOIN customer  c  ON c.customer_id   = cm.customer_id
             LEFT JOIN staff st ON st.staff_id    = r.approved_by_staff_id
             WHERE DATE(r.created_at) BETWEEN :from AND :to
             ORDER BY r.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to',   $to);
        $stmt->bindValue(':lim',  $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =========================================================
    //  ITEMS AGGREGATES
    // =========================================================
    private function itemsSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(DISTINCT oi.item_id)    AS unique_items,
                COUNT(DISTINCT oi.service_id) AS unique_services,
                COALESCE(SUM(oi.quantity), 0) AS items_sold,
                COALESCE(SUM(oi.total_price), 0) AS revenue
             FROM order_item oi
             JOIN orders o ON o.order_id = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        return [
            'unique_items'    => (int)   ($row['unique_items']    ?? 0),
            'unique_services' => (int)   ($row['unique_services'] ?? 0),
            'items_sold'      => (int)   ($row['items_sold']      ?? 0),
            'revenue'         => (float) ($row['revenue']         ?? 0),
        ];
    }

    private function topItems(string $from, string $to, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT it.item_id, it.item_name,
                    ic.category_name,
                    SUM(oi.quantity)     AS total_quantity,
                    SUM(oi.total_price)  AS revenue,
                    COUNT(DISTINCT oi.order_id) AS orders_count
             FROM order_item oi
             JOIN item_type     it ON it.item_id     = oi.item_id
             JOIN item_category ic ON ic.category_id = it.category_id
             JOIN orders        o  ON o.order_id     = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to
             GROUP BY it.item_id, it.item_name, ic.category_name
             ORDER BY revenue DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to',   $to);
        $stmt->bindValue(':lim',  $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function topServices(string $from, string $to, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.service_id, s.service_name, s.service_type,
                    SUM(oi.quantity)     AS total_quantity,
                    SUM(oi.total_price)  AS revenue,
                    COUNT(DISTINCT oi.order_id) AS orders_count
             FROM order_item oi
             JOIN service s ON s.service_id = oi.service_id
             JOIN orders  o ON o.order_id   = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to
             GROUP BY s.service_id, s.service_name, s.service_type
             ORDER BY revenue DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to',   $to);
        $stmt->bindValue(':lim',  $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function itemsByCategory(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT ic.category_id, ic.category_name,
                    COUNT(DISTINCT oi.item_id) AS items_count,
                    COALESCE(SUM(oi.quantity), 0) AS quantity,
                    COALESCE(SUM(oi.total_price), 0) AS revenue
             FROM order_item oi
             JOIN item_type     it ON it.item_id     = oi.item_id
             JOIN item_category ic ON ic.category_id = it.category_id
             JOIN orders        o  ON o.order_id     = oi.order_id
             WHERE DATE(o.order_date) BETWEEN :from AND :to
             GROUP BY ic.category_id, ic.category_name
             ORDER BY revenue DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  DELIVERIES AGGREGATES
    // =========================================================
    private function deliveriesSummary(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) AS in_transit,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled
             FROM delivery
             WHERE delivery_date BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        $total     = (int) ($row['total'] ?? 0);
        $delivered = (int) ($row['delivered'] ?? 0);
        $failed    = (int) ($row['failed'] ?? 0);

        return [
            'total'          => $total,
            'delivered'      => $delivered,
            'failed'         => $failed,
            'in_transit'     => (int) ($row['in_transit'] ?? 0),
            'scheduled'      => (int) ($row['scheduled']  ?? 0),
            'success_rate'   => $total > 0 ? round(($delivered / $total) * 100, 2) : 0.0,
            'failure_rate'   => $total > 0 ? round(($failed    / $total) * 100, 2) : 0.0,
        ];
    }

    private function deliveriesPerStaff(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.staff_id, s.full_name,
                    COUNT(d.delivery_id) AS total,
                    SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                    SUM(CASE WHEN d.status = 'failed'    THEN 1 ELSE 0 END) AS failed
             FROM staff s
             LEFT JOIN delivery d
                    ON d.assigned_staff_id = s.staff_id
                   AND d.delivery_date BETWEEN :from AND :to
             WHERE s.is_active = 1
             GROUP BY s.staff_id, s.full_name
             ORDER BY delivered DESC, total DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    private function deliveriesTrend(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT delivery_date AS d,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                    SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed
             FROM delivery
             WHERE delivery_date BETWEEN :from AND :to
             GROUP BY delivery_date
             ORDER BY d ASC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  CSV EXPORTERS
    // =========================================================
    private function exportSales($out, string $from, string $to): void
    {
        $summary = $this->salesSummary($from, $to);

        fputcsv($out, ['SUMMARY']);
        fputcsv($out, ['Metric', 'Value']);
        foreach ($summary as $k => $v) {
            fputcsv($out, [$this->humanize($k), $v]);
        }
        fputcsv($out, []);

        fputcsv($out, ['REVENUE BY METHOD']);
        fputcsv($out, ['Method', 'Payments', 'Total']);
        foreach ($this->revenueByMethod($from, $to) as $row) {
            fputcsv($out, [$row['payment_method'], $row['payments'], $row['total']]);
        }
        fputcsv($out, []);

        fputcsv($out, ['REVENUE BY PERIOD']);
        fputcsv($out, ['Period', 'Payments', 'Total']);
        foreach ($this->revenueByPeriod($from, $to, 'day') as $row) {
            fputcsv($out, [$row['period'], $row['payments'], $row['total']]);
        }
    }

    private function exportOrders($out, string $from, string $to): void
    {
        $summary = $this->ordersSummary($from, $to);

        fputcsv($out, ['SUMMARY']);
        fputcsv($out, ['Metric', 'Value']);
        foreach ($summary as $k => $v) {
            fputcsv($out, [$this->humanize($k), $v]);
        }
        fputcsv($out, []);

        fputcsv($out, ['STATUS BREAKDOWN']);
        fputcsv($out, ['Status', 'Count']);
        foreach ($this->orderStatusCounts($from, $to) as $status => $count) {
            fputcsv($out, [$this->humanize($status), $count]);
        }
        fputcsv($out, []);

        fputcsv($out, ['SERVICE MIX']);
        fputcsv($out, ['Service', 'Type', 'Orders', 'Items', 'Revenue']);
        foreach ($this->serviceMix($from, $to) as $row) {
            fputcsv($out, [
                $row['service_name'],
                $row['service_type'],
                $row['orders_count'],
                $row['items_count'],
                $row['revenue'],
            ]);
        }
    }

    private function exportCustomers($out, string $from, string $to): void
    {
        $summary = $this->customersSummary($from, $to);

        fputcsv($out, ['SUMMARY']);
        fputcsv($out, ['Metric', 'Value']);
        foreach ($summary as $k => $v) {
            fputcsv($out, [$this->humanize($k), $v]);
        }
        fputcsv($out, []);

        fputcsv($out, ['TOP SPENDERS']);
        fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'City', 'Orders', 'Total Spent']);
        foreach ($this->topSpendingCustomers($from, $to, 200) as $row) {
            fputcsv($out, [
                $row['customer_id'],
                $row['full_name'],
                $row['email'],
                $row['phone'],
                $row['city'],
                $row['orders_count'],
                $row['total_spent'],
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['CITY BREAKDOWN']);
        fputcsv($out, ['City', 'New Customers']);
        foreach ($this->customersByCity($from, $to) as $row) {
            fputcsv($out, [$row['city'], $row['total']]);
        }
    }

    private function exportStaffPerformance($out, string $from, string $to): void
    {
        fputcsv($out, ['PER STAFF PERFORMANCE']);
        fputcsv($out, [
            'ID', 'Name', 'Email', 'Role', 'Active',
            'Pickups', 'Deliveries', 'Orders', 'Complaints Resolved',
            'Avg Rating', 'Avg Score', 'Days Recorded',
        ]);

        foreach ($this->staffPerformancePerStaff($from, $to) as $row) {
            fputcsv($out, [
                $row['staff_id'],
                $row['full_name'],
                $row['email'],
                $row['role'],
                $row['is_active'] ? 'Yes' : 'No',
                $row['pickups'],
                $row['deliveries'],
                $row['orders'],
                $row['complaints_resolved'],
                round((float) $row['avg_rating'], 2),
                round((float) $row['avg_score'], 2),
                $row['days_recorded'],
            ]);
        }
    }

    private function exportComplaints($out, string $from, string $to): void
    {
        $summary = $this->complaintsSummary($from, $to);

        fputcsv($out, ['SUMMARY']);
        fputcsv($out, ['Metric', 'Value']);
        foreach ($summary as $k => $v) {
            fputcsv($out, [$this->humanize($k), $v]);
        }
        fputcsv($out, []);

        fputcsv($out, ['BY TYPE']);
        fputcsv($out, ['Type', 'Count']);
        foreach ($this->complaintsByType($from, $to) as $row) {
            fputcsv($out, [$row['type'], $row['total']]);
        }
        fputcsv($out, []);

        fputcsv($out, ['BY CATEGORY']);
        fputcsv($out, ['Category', 'Count']);
        foreach ($this->complaintsByCategory($from, $to) as $row) {
            fputcsv($out, [$row['category_name'] ?? 'Uncategorized', $row['total']]);
        }
        fputcsv($out, []);

        fputcsv($out, ['RESOLUTION TIMES']);
        fputcsv($out, ['Metric', 'Hours']);
        foreach ($this->complaintResolutionTimes($from, $to) as $k => $v) {
            fputcsv($out, [$this->humanize($k), $v]);
        }
    }

    private function exportRefunds($out, string $from, string $to): void
    {
        $summary = $this->refundsSummary($from, $to);

        fputcsv($out, ['SUMMARY']);
        fputcsv($out, ['Metric', 'Value']);
        foreach ($summary as $k => $v) {
            fputcsv($out, [$this->humanize($k), $v]);
        }
        fputcsv($out, []);

        fputcsv($out, ['RECENT REFUNDS']);
        fputcsv($out, [
            'ID', 'Complaint #', 'Order #', 'Customer',
            'Amount', 'Status', 'Approved By', 'Created', 'Completed',
        ]);

        foreach ($this->recentRefunds($from, $to, self::CSV_ROW_LIMIT) as $row) {
            fputcsv($out, [
                $row['refund_id'],
                $row['complaint_number'],
                $row['order_number'],
                $row['customer_name'],
                $row['amount'],
                $row['status'],
                $row['approved_by_name'] ?? '',
                $row['created_at'],
                $row['completed_at'] ?? '',
            ]);
        }
    }

    private function exportItems($out, string $from, string $to): void
    {
        fputcsv($out, ['TOP ITEMS']);
        fputcsv($out, ['ID', 'Item', 'Category', 'Quantity', 'Orders', 'Revenue']);
        foreach ($this->topItems($from, $to, 500) as $row) {
            fputcsv($out, [
                $row['item_id'],
                $row['item_name'],
                $row['category_name'],
                $row['total_quantity'],
                $row['orders_count'],
                $row['revenue'],
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['TOP SERVICES']);
        fputcsv($out, ['ID', 'Service', 'Type', 'Quantity', 'Orders', 'Revenue']);
        foreach ($this->topServices($from, $to, 500) as $row) {
            fputcsv($out, [
                $row['service_id'],
                $row['service_name'],
                $row['service_type'],
                $row['total_quantity'],
                $row['orders_count'],
                $row['revenue'],
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['BY CATEGORY']);
        fputcsv($out, ['Category', 'Items', 'Quantity', 'Revenue']);
        foreach ($this->itemsByCategory($from, $to) as $row) {
            fputcsv($out, [
                $row['category_name'],
                $row['items_count'],
                $row['quantity'],
                $row['revenue'],
            ]);
        }
    }

    private function exportDeliveries($out, string $from, string $to): void
    {
        $summary = $this->deliveriesSummary($from, $to);

        fputcsv($out, ['SUMMARY']);
        fputcsv($out, ['Metric', 'Value']);
        foreach ($summary as $k => $v) {
            fputcsv($out, [$this->humanize($k), $v]);
        }
        fputcsv($out, []);

        fputcsv($out, ['PER STAFF']);
        fputcsv($out, ['ID', 'Name', 'Total', 'Delivered', 'Failed']);
        foreach ($this->deliveriesPerStaff($from, $to) as $row) {
            fputcsv($out, [
                $row['staff_id'],
                $row['full_name'],
                $row['total'],
                $row['delivered'],
                $row['failed'],
            ]);
        }
        fputcsv($out, []);

        fputcsv($out, ['DAILY TREND']);
        fputcsv($out, ['Date', 'Total', 'Delivered', 'Failed']);
        foreach ($this->deliveriesTrend($from, $to) as $row) {
            fputcsv($out, [$row['d'], $row['total'], $row['delivered'], $row['failed']]);
        }
    }

    // =========================================================
    //  UTILITIES
    // =========================================================

    /**
     * Resolve a date range from $_GET with safe defaults.
     * Returns [from, to] as YYYY-MM-DD strings.
     */
    private function resolveDateRange(): array
    {
        $to   = $_GET['to']   ?? date('Y-m-d');
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-' . self::DEFAULT_RANGE_DAYS . ' days'));

        if (!$this->isValidDate($from)) {
            $from = date('Y-m-d', strtotime('-' . self::DEFAULT_RANGE_DAYS . ' days'));
        }
        if (!$this->isValidDate($to)) {
            $to = date('Y-m-d');
        }

        // Ensure from <= to
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * Return a SQL expression for grouping a date column.
     * Accepts 'day', 'week', 'month'.
     */
    private function groupExpr(string $dateExpr, string $groupBy): string
    {
        return match ($groupBy) {
            'week'  => "DATE_FORMAT({$dateExpr}, '%x-W%v')",
            'month' => "DATE_FORMAT({$dateExpr}, '%Y-%m')",
            default => $dateExpr, // 'day'
        };
    }

    /**
     * Compute percent change between two numbers.
     */
    private function pctChange(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * "orders_count" → "Orders Count"
     */
    private function humanize(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    private function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            $this->forbidden();
            exit;
        }
    }

    private function isAdmin(): bool
    {
        return ($_SESSION['staff_role'] ?? '') === 'admin';
    }
}
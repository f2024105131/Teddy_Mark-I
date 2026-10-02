<?php
/**
 * Admin Dashboard
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\DashboardController::index()
 *
 * Expected variables (all provided by the controller):
 *   $currency          string   'Rs.'
 *   $businessName      string   'LaundryPro'
 *   $kpis              array    ['customers', 'staff', 'orders', 'complaints']
 *   $todayOps          array    ['pickups', 'deliveries', 'pipeline']
 *   $revenue           array    ['today','last_7_days','this_week','this_month','all_time','outstanding','refunds_30d']
 *   $orderTrend        array    [['date','label','value'], ...]
 *   $revenueTrend      array    [['date','label','value'], ...]
 *   $orderStatusCounts array
 *   $complaintCounts   array
 *   $recentOrders      array
 *   $recentComplaints  array
 *   $recentPayments    array
 *   $topPerformers     array
 *   $alerts            array
 * ----------------------------------------------------------
 */

// Activate sidebar link in layout
$currentPage = 'dashboard';
$title       = 'Dashboard';

// Safe defaults (defensive — controller always provides)
$currency     = $currency     ?? 'Rs.';
$businessName = $businessName ?? 'LaundryPro';

$kpis          = $kpis          ?? ['customers'=>[], 'staff'=>[], 'orders'=>[], 'complaints'=>[]];
$todayOps      = $todayOps      ?? ['pickups'=>[], 'deliveries'=>[], 'pipeline'=>[]];
$revenue       = $revenue       ?? ['today'=>0,'last_7_days'=>0,'this_week'=>0,'this_month'=>0,'all_time'=>0,'outstanding'=>0,'refunds_30d'=>0];
$orderTrend    = $orderTrend    ?? [];
$revenueTrend  = $revenueTrend  ?? [];
$orderStatusCounts = $orderStatusCounts ?? [];
$complaintCounts   = $complaintCounts   ?? [];
$recentOrders      = $recentOrders      ?? [];
$recentComplaints  = $recentComplaints  ?? [];
$recentPayments    = $recentPayments    ?? [];
$topPerformers     = $topPerformers     ?? [];
$alerts            = $alerts            ?? [];

// Helper
$fmtMoney = fn($n) => $currency . ' ' . number_format((float) $n, 2);
$fmtInt   = fn($n) => number_format((int) $n);
$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// Status → Bootstrap badge class
$orderBadge = function (string $s): string {
    return match ($s) {
        'pickup_requested'    => 'secondary',
        'pickup_assigned'     => 'info',
        'picked_up'           => 'primary',
        'received_at_laundry' => 'primary',
        'washing'             => 'warning',
        'ironing'             => 'warning',
        'ready'               => 'success',
        'out_for_delivery'    => 'info',
        'delivered'           => 'success',
        'cancelled'           => 'danger',
        default               => 'secondary',
    };
};
$orderLabel = function (string $s): string {
    return ucwords(str_replace('_', ' ', $s));
};

$complaintBadge = function (string $s): string {
    return match ($s) {
        'open'                => 'danger',
        'assigned'            => 'warning',
        'under_investigation' => 'info',
        'resolved'            => 'success',
        'rejected'            => 'secondary',
        'escalated'           => 'dark',
        'closed'              => 'light',
        default               => 'secondary',
    };
};
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Dashboard</h1>
        <p class="text-muted mb-0 small">
            Welcome back to <strong><?= $e($businessName) ?></strong> ·
            <?= date('l, F j, Y') ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/reports" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bar-chart me-1"></i>Reports
        </a>
        <a href="/admin/pickups/monitor" class="btn btn-primary btn-sm">
            <i class="bi bi-truck me-1"></i>Live Monitor
        </a>
    </div>
</div>

<!-- ==================== ALERTS ==================== -->
<?php if (!empty($alerts)): ?>
    <div class="row g-2 mb-4">
        <?php foreach ($alerts as $alert):
            $level = $alert['level'] ?? 'info';
            $btnClass = match ($level) {
                'danger'  => 'alert-danger',
                'warning' => 'alert-warning',
                'success' => 'alert-success',
                default   => 'alert-info',
            };
        ?>
            <div class="col-md-6 col-xl-4">
                <a href="<?= $e($alert['link'] ?? '#') ?>"
                   class="alert <?= $btnClass ?> d-flex align-items-center gap-3 mb-0 py-2 text-decoration-none">
                    <i class="bi <?= $e($alert['icon'] ?? 'bi-info-circle') ?> fs-5"></i>
                    <div class="flex-grow-1 small"><?= $e($alert['message'] ?? '') ?></div>
                    <?php if (!empty($alert['count'])): ?>
                        <span class="badge bg-dark"><?= (int) $alert['count'] ?></span>
                    <?php endif; ?>
                    <i class="bi bi-chevron-right small"></i>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ==================== KPI CARDS ==================== -->
<div class="row g-3 mb-4">

    <!-- Customers -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Customers</span>
                    <span class="badge bg-primary-subtle text-primary">
                        <i class="bi bi-people"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="mb-0 fw-bold"><?= $fmtInt($kpis['customers']['total'] ?? 0) ?></h3>
                    <?php if (!empty($kpis['customers']['new_today'])): ?>
                        <span class="badge bg-success-subtle text-success small">
                            +<?= $fmtInt($kpis['customers']['new_today']) ?> today
                        </span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small mt-2">
                    <i class="bi bi-circle-fill text-success" style="font-size:.5rem;"></i>
                    <?= $fmtInt($kpis['customers']['active'] ?? 0) ?> active ·
                    <?= $fmtInt($kpis['customers']['pending'] ?? 0) ?> pending
                </div>
            </div>
        </div>
    </div>

    <!-- Orders -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Orders</span>
                    <span class="badge bg-info-subtle text-info">
                        <i class="bi bi-bag-check"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="mb-0 fw-bold"><?= $fmtInt($kpis['orders']['total'] ?? 0) ?></h3>
                    <?php if (!empty($kpis['orders']['today'])): ?>
                        <span class="badge bg-info-subtle text-info small">
                            +<?= $fmtInt($kpis['orders']['today']) ?> today
                        </span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small mt-2">
                    <i class="bi bi-hourglass-split text-warning"></i>
                    <?= $fmtInt($kpis['orders']['active'] ?? 0) ?> in progress ·
                    <i class="bi bi-check2-circle text-success"></i>
                    <?= $fmtInt($kpis['orders']['delivered'] ?? 0) ?> delivered
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Revenue (Today)</span>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-cash-coin"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="mb-0 fw-bold"><?= $fmtMoney($revenue['today'] ?? 0) ?></h3>
                </div>
                <div class="text-muted small mt-2">
                    This month: <strong><?= $fmtMoney($revenue['this_month'] ?? 0) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Complaints -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Complaints</span>
                    <span class="badge bg-danger-subtle text-danger">
                        <i class="bi bi-exclamation-circle"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="mb-0 fw-bold"><?= $fmtInt($kpis['complaints']['active'] ?? 0) ?></h3>
                    <?php if (!empty($kpis['complaints']['escalated'])): ?>
                        <span class="badge bg-danger small">
                            <?= $fmtInt($kpis['complaints']['escalated']) ?> escalated
                        </span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small mt-2">
                    <i class="bi bi-check2-circle text-success"></i>
                    <?= $fmtInt($kpis['complaints']['resolved'] ?? 0) ?> resolved ·
                    <?= $fmtInt($kpis['complaints']['total'] ?? 0) ?> total
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REVENUE + TODAY OPS ==================== -->
<div class="row g-3 mb-4">

    <!-- Revenue summary -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Revenue Overview</h6>
                <a href="/admin/reports/sales" class="small text-decoration-none">
                    Details <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted small">Today</div>
                        <div class="fw-bold"><?= $fmtMoney($revenue['today'] ?? 0) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Last 7 days</div>
                        <div class="fw-bold"><?= $fmtMoney($revenue['last_7_days'] ?? 0) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">This week</div>
                        <div class="fw-bold"><?= $fmtMoney($revenue['this_week'] ?? 0) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">This month</div>
                        <div class="fw-bold"><?= $fmtMoney($revenue['this_month'] ?? 0) ?></div>
                    </div>
                </div>
                <hr class="my-3">
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">All-time revenue</span>
                    <strong><?= $fmtMoney($revenue['all_time'] ?? 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                    <span class="text-muted">Outstanding receivables</span>
                    <strong class="text-warning"><?= $fmtMoney($revenue['outstanding'] ?? 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                    <span class="text-muted">Refunds (last 30 days)</span>
                    <strong class="text-danger"><?= $fmtMoney($revenue['refunds_30d'] ?? 0) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Operations -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Today's Operations</h6>
                <a href="/admin/pickups/monitor" class="small text-decoration-none">
                    Monitor <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3">
                    <!-- Pickups -->
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-box-seam text-primary fs-5"></i>
                                <span class="fw-semibold">Pickups</span>
                                <span class="ms-auto badge bg-secondary">
                                    <?= $fmtInt($todayOps['pickups']['total'] ?? 0) ?>
                                </span>
                            </div>
                            <div class="row small text-muted g-1">
                                <div class="col-6">Requested</div>
                                <div class="col-6 text-end"><?= $fmtInt($todayOps['pickups']['requested'] ?? 0) ?></div>
                                <div class="col-6">Assigned</div>
                                <div class="col-6 text-end"><?= $fmtInt($todayOps['pickups']['assigned'] ?? 0) ?></div>
                                <div class="col-6">Picked up</div>
                                <div class="col-6 text-end text-success"><?= $fmtInt($todayOps['pickups']['picked_up'] ?? 0) ?></div>
                                <div class="col-6">Cancelled</div>
                                <div class="col-6 text-end text-danger"><?= $fmtInt($todayOps['pickups']['cancelled'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                    <!-- Deliveries -->
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-truck text-success fs-5"></i>
                                <span class="fw-semibold">Deliveries</span>
                                <span class="ms-auto badge bg-secondary">
                                    <?= $fmtInt($todayOps['deliveries']['total'] ?? 0) ?>
                                </span>
                            </div>
                            <div class="row small text-muted g-1">
                                <div class="col-6">Scheduled</div>
                                <div class="col-6 text-end"><?= $fmtInt($todayOps['deliveries']['scheduled'] ?? 0) ?></div>
                                <div class="col-6">Out for delivery</div>
                                <div class="col-6 text-end text-info"><?= $fmtInt($todayOps['deliveries']['out_for_delivery'] ?? 0) ?></div>
                                <div class="col-6">Delivered</div>
                                <div class="col-6 text-end text-success"><?= $fmtInt($todayOps['deliveries']['delivered'] ?? 0) ?></div>
                                <div class="col-6">Failed</div>
                                <div class="col-6 text-end text-danger"><?= $fmtInt($todayOps['deliveries']['failed'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pipeline -->
                <div class="mt-3">
                    <div class="text-muted small mb-2 fw-semibold text-uppercase">Pipeline Snapshot</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php
                        $pipeline = $todayOps['pipeline'] ?? [];
                        $pipelineLabels = [
                            'pickup_requested'    => 'Pickup Requested',
                            'pickup_assigned'     => 'Pickup Assigned',
                            'picked_up'           => 'Picked Up',
                            'received_at_laundry' => 'Received',
                            'washing'             => 'Washing',
                            'ironing'             => 'Ironing',
                            'ready'               => 'Ready',
                            'out_for_delivery'    => 'Out for Delivery',
                        ];
                        foreach ($pipelineLabels as $key => $label):
                            $count = (int) ($pipeline[$key] ?? 0);
                            if ($count === 0) continue;
                        ?>
                            <span class="badge bg-<?= $orderBadge($key) ?>-subtle text-<?= $orderBadge($key) ?> px-3 py-2">
                                <?= $e($label) ?>: <strong><?= $count ?></strong>
                            </span>
                        <?php endforeach; ?>
                        <?php if (array_sum($pipeline) === 0): ?>
                            <span class="text-muted small">No active orders in pipeline.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TREND CHARTS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">Orders (Last 14 Days)</h6>
            </div>
            <div class="card-body">
                <canvas id="orderTrendChart" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">Revenue (Last 14 Days)</h6>
            </div>
            <div class="card-body">
                <canvas id="revenueTrendChart" height="140"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RECENT ACTIVITY ==================== -->
<div class="row g-3 mb-4">

    <!-- Recent Orders -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Recent Orders</h6>
                <a href="/admin/reports/orders" class="small text-decoration-none">View all</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentOrders)): ?>
                    <div class="p-3 text-muted small text-center">No orders yet.</div>
                <?php else: foreach ($recentOrders as $o): ?>
                    <div class="list-group-item px-3 py-2 border-0 border-bottom">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <div class="fw-medium small text-truncate"><?= $e($o['order_number']) ?></div>
                                <div class="text-muted small text-truncate"><?= $e($o['customer_name']) ?></div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?= $orderBadge($o['order_status']) ?> small">
                                    <?= $e($orderLabel($o['order_status'])) ?>
                                </span>
                                <div class="text-muted small mt-1">
                                    <?= (int) ($o['item_count'] ?? 0) ?> items
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Complaints -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Recent Complaints</h6>
                <a href="/admin/complaints" class="small text-decoration-none">View all</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentComplaints)): ?>
                    <div class="p-3 text-muted small text-center">No complaints. 🎉</div>
                <?php else: foreach ($recentComplaints as $c): ?>
                    <div class="list-group-item px-3 py-2 border-0 border-bottom">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <div class="fw-medium small text-truncate"><?= $e($c['complaint_number']) ?></div>
                                <div class="text-muted small text-truncate">
                                    <?= $e($c['customer_name']) ?> ·
                                    <?= $e(ucwords(str_replace('_', ' ', $c['type']))) ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= $complaintBadge($c['status']) ?> small">
                                <?= $e(ucfirst(str_replace('_',' ', $c['status']))) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Recent Payments</h6>
                <a href="/admin/reports/sales" class="small text-decoration-none">View all</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentPayments)): ?>
                    <div class="p-3 text-muted small text-center">No payments yet.</div>
                <?php else: foreach ($recentPayments as $p):
                    $statusColor = match ($p['status']) {
                        'approved'              => 'success',
                        'pending_verification'  => 'warning',
                        'verified'              => 'info',
                        'rejected'              => 'danger',
                        'refunded'              => 'secondary',
                        default                 => 'secondary',
                    };
                ?>
                    <div class="list-group-item px-3 py-2 border-0 border-bottom">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <div class="fw-medium small text-truncate">
                                    <?= $fmtMoney($p['amount']) ?>
                                </div>
                                <div class="text-muted small text-truncate">
                                    <?= $e($p['customer_name']) ?> ·
                                    <?= $e(ucwords(str_replace('_', ' ', $p['payment_method']))) ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= $statusColor ?> small">
                                <?= $e(ucwords(str_replace('_',' ', $p['status']))) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TOP PERFORMERS + ORDER STATUS ==================== -->
<div class="row g-3">

    <!-- Top Performers -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Top Performers Today</h6>
                <a href="/admin/staff/performance-overview" class="small text-decoration-none">Full report</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small text-muted">Staff</th>
                            <th class="small text-muted text-center">Pickups</th>
                            <th class="small text-muted text-center">Deliveries</th>
                            <th class="small text-muted text-center">Rating</th>
                            <th class="small text-muted text-end">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topPerformers)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted small py-4">
                                    No performance data for today yet.
                                </td>
                            </tr>
                        <?php else: foreach ($topPerformers as $i => $p):
                            $score      = (float) ($p['performance_score'] ?? 0);
                            $scoreColor = $score >= 85 ? 'success' : ($score >= 70 ? 'info' : ($score >= 50 ? 'warning' : 'danger'));
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-light text-dark">#<?= $i + 1 ?></span>
                                        <div>
                                            <div class="fw-medium small"><?= $e($p['full_name']) ?></div>
                                            <div class="text-muted" style="font-size:.75rem;"><?= $e($p['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center small"><?= $fmtInt($p['pickups_completed'] ?? 0) ?></td>
                                <td class="text-center small"><?= $fmtInt($p['deliveries_completed'] ?? 0) ?></td>
                                <td class="text-center small">
                                    <?php $r = (float) ($p['average_rating'] ?? 0); ?>
                                    <span class="text-warning">
                                        <?= str_repeat('★', (int) round($r)) ?><?= str_repeat('☆', 5 - (int) round($r)) ?>
                                    </span>
                                    <span class="text-muted"><?= number_format($r, 1) ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-<?= $scoreColor ?>"><?= number_format($score, 1) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Status Breakdown -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">Order Status Breakdown</h6>
            </div>
            <div class="card-body pt-0">
                <?php
                $totalOrders = max(1, array_sum($orderStatusCounts));
                foreach ($orderStatusCounts as $status => $count):
                    $pct = round(($count / $totalOrders) * 100, 1);
                    $color = $orderBadge($status);
                ?>
                    <div class="d-flex align-items-center gap-2 mb-2 small">
                        <span class="text-muted" style="min-width:130px;">
                            <?= $e($orderLabel($status)) ?>
                        </span>
                        <div class="progress flex-grow-1" style="height: 6px;">
                            <div class="progress-bar bg-<?= $color ?>"
                                 style="width: <?= $pct ?>%"></div>
                        </div>
                        <span class="text-muted text-end" style="min-width: 50px;">
                            <?= $fmtInt($count) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if (array_sum($orderStatusCounts) === 0): ?>
                    <div class="text-center text-muted small py-3">No orders in the system yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CHART SCRIPT ==================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    // -------- Data from PHP --------
    const orderTrend = <?= json_encode(array_map(fn($r) => [
        'label' => $r['label'],
        'value' => $r['value'],
    ], $orderTrend), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const revenueTrend = <?= json_encode(array_map(fn($r) => [
        'label' => $r['label'],
        'value' => $r['value'],
    ], $revenueTrend), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const currencySymbol = <?= json_encode($currency) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // -------- Orders trend --------
    const orderCanvas = document.getElementById('orderTrendChart');
    if (orderCanvas && orderTrend.length) {
        new Chart(orderCanvas, {
            type: 'line',
            data: {
                labels: orderTrend.map(r => r.label),
                datasets: [{
                    label: 'Orders',
                    data: orderTrend.map(r => r.value),
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,.1)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 2,
                    pointBackgroundColor: '#0ea5e9',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // -------- Revenue trend --------
    const revenueCanvas = document.getElementById('revenueTrendChart');
    if (revenueCanvas && revenueTrend.length) {
        new Chart(revenueCanvas, {
            type: 'bar',
            data: {
                labels: revenueTrend.map(r => r.label),
                datasets: [{
                    label: 'Revenue',
                    data: revenueTrend.map(r => r.value),
                    backgroundColor: '#22c55e',
                    borderRadius: 4,
                    maxBarThickness: 22,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) =>
                                currencySymbol + ' ' +
                                Number(ctx.raw).toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                }),
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => currencySymbol + ' ' + v.toLocaleString()
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
})();
</script>
<?php
/**
 * Admin Reports — Orders
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::orders()
 *
 * Expected variables:
 *   $from           string   Y-m-d
 *   $to             string   Y-m-d
 *   $summary        array    ['total','delivered','cancelled','active','success_rate']
 *   $statusCounts   array    ['pickup_requested'=>N, 'washing'=>N, ...] (all 10 seeded)
 *   $serviceMix     array    rows: [service_id, service_name, service_type,
 *                                   orders_count, items_count, revenue]
 *   $trend          array    rows: [d, total, delivered, cancelled]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Orders Report';

// Safe defaults
$from         = $from         ?? date('Y-m-d', strtotime('-30 days'));
$to           = $to           ?? date('Y-m-d');
$summary      = $summary      ?? [];
$statusCounts = $statusCounts ?? [];
$serviceMix   = $serviceMix   ?? [];
$trend        = $trend        ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Extract summary
$totalOrders     = (int)   ($summary['total']        ?? 0);
$deliveredOrders = (int)   ($summary['delivered']    ?? 0);
$cancelledOrders = (int)   ($summary['cancelled']    ?? 0);
$activeOrders    = (int)   ($summary['active']       ?? 0);
$successRate     = (float) ($summary['success_rate'] ?? 0);

// Status metadata (label + color)
$statusMeta = [
    'pickup_requested'    => ['label' => 'Pickup Requested',    'color' => 'secondary', 'icon' => 'bi-hourglass'],
    'pickup_assigned'     => ['label' => 'Pickup Assigned',     'color' => 'info',      'icon' => 'bi-person-check'],
    'picked_up'           => ['label' => 'Picked Up',           'color' => 'primary',   'icon' => 'bi-box-seam'],
    'received_at_laundry' => ['label' => 'Received',            'color' => 'primary',   'icon' => 'bi-house-check'],
    'washing'             => ['label' => 'Washing',             'color' => 'warning',   'icon' => 'bi-droplet'],
    'ironing'             => ['label' => 'Ironing',             'color' => 'warning',   'icon' => 'bi-thermometer-half'],
    'ready'               => ['label' => 'Ready',               'color' => 'success',   'icon' => 'bi-check-circle'],
    'out_for_delivery'    => ['label' => 'Out for Delivery',    'color' => 'info',      'icon' => 'bi-truck'],
    'delivered'           => ['label' => 'Delivered',           'color' => 'success',   'icon' => 'bi-check2-all'],
    'cancelled'           => ['label' => 'Cancelled',           'color' => 'danger',    'icon' => 'bi-x-circle'],
];

// Service type colors
$typeBadge = function (string $t): string {
    return match ($t) {
        'wash_fold'        => 'primary',
        'wash_iron'        => 'info',
        'dry_cleaning'     => 'dark',
        'ironing'          => 'secondary',
        'express'          => 'warning',
        'premium'          => 'success',
        'blanket_cleaning' => 'light',
        'curtain_cleaning' => 'light',
        default            => 'secondary',
    };
};
$typeLabel = fn(string $t): string => ucwords(str_replace('_', ' ', $t));

// Prepare chart data
$trendLabels    = [];
$trendTotal     = [];
$trendDelivered = [];
$trendCancelled = [];
foreach ($trend as $row) {
    $d = (string) ($row['d'] ?? '');
    $trendLabels[]    = $d ? date('M j', strtotime($d)) : '';
    $trendTotal[]     = (int) ($row['total']     ?? 0);
    $trendDelivered[] = (int) ($row['delivered'] ?? 0);
    $trendCancelled[] = (int) ($row['cancelled'] ?? 0);
}

// Status chart data
$statusLabels = [];
$statusValues = [];
$statusHexes  = [];
$statusColorMap = [
    'secondary' => '#64748b',
    'info'      => '#06b6d4',
    'primary'   => '#0ea5e9',
    'warning'   => '#f59e0b',
    'success'   => '#22c55e',
    'danger'    => '#ef4444',
];
foreach ($statusCounts as $status => $count) {
    if ((int) $count === 0) continue; // skip zero-statuses for cleanliness
    $meta = $statusMeta[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'color' => 'secondary'];
    $statusLabels[] = $meta['label'];
    $statusValues[] = (int) $count;
    $statusHexes[]  = $statusColorMap[$meta['color']] ?? '#64748b';
}

// Service mix chart (top 6 for readability)
$serviceLabels = [];
$serviceValues = [];
$serviceHexes  = [];
$typeHexMap = [
    'primary'   => '#0ea5e9',
    'info'      => '#06b6d4',
    'dark'      => '#1e293b',
    'secondary' => '#64748b',
    'warning'   => '#f59e0b',
    'success'   => '#22c55e',
    'light'     => '#cbd5e1',
];
$serviceMixTop = array_slice($serviceMix, 0, 6);
foreach ($serviceMixTop as $sm) {
    $serviceLabels[] = $sm['service_name'] ?? '';
    $serviceValues[] = (int) ($sm['orders_count'] ?? 0);
    $badge = $typeBadge($sm['service_type'] ?? '');
    $serviceHexes[] = $typeHexMap[$badge] ?? '#64748b';
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/reports?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1">
                <i class="bi bi-bag-check me-2 text-primary"></i>Orders Report
            </h1>
            <p class="text-muted mb-0 small">
                <strong><?= $e($fmtDate($from)) ?></strong> → <strong><?= $e($fmtDate($to)) ?></strong>
                · <?= $fmtInt($totalOrders) ?> total order(s)
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/reports/orders/json?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-code-slash me-1"></i>JSON
        </a>
        <a href="/admin/reports/export?report=orders&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== FILTER BAR ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports/orders" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="from" class="form-label small text-muted mb-1">From</label>
                <input type="date"
                       class="form-control"
                       id="from"
                       name="from"
                       value="<?= $e($from) ?>"
                       max="<?= $e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <label for="to" class="form-label small text-muted mb-1">To</label>
                <input type="date"
                       class="form-control"
                       id="to"
                       name="to"
                       value="<?= $e($to) ?>"
                       max="<?= $e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Apply Range
                </button>
            </div>
        </form>

        <div class="d-flex flex-wrap gap-2 align-items-center mt-3 pt-3 border-top">
            <span class="text-muted small me-1">Quick ranges:</span>
            <?php
            $presets = [
                ['label' => 'Today',        'from' => date('Y-m-d'),                  'to' => date('Y-m-d')],
                ['label' => 'Last 7 days',  'from' => date('Y-m-d', strtotime('-6 days')),  'to' => date('Y-m-d')],
                ['label' => 'Last 30 days', 'from' => date('Y-m-d', strtotime('-29 days')), 'to' => date('Y-m-d')],
                ['label' => 'This month',   'from' => date('Y-m-01'),                 'to' => date('Y-m-d')],
                ['label' => 'Last 90 days', 'from' => date('Y-m-d', strtotime('-89 days')), 'to' => date('Y-m-d')],
                ['label' => 'This year',    'from' => date('Y-01-01'),                'to' => date('Y-m-d')],
            ];
            foreach ($presets as $p):
                $isActive = ($from === $p['from'] && $to === $p['to']);
            ?>
                <a href="?from=<?= $e($p['from']) ?>&to=<?= $e($p['to']) ?>"
                   class="badge text-decoration-none <?= $isActive ? 'bg-primary' : 'bg-light text-dark border' ?>">
                    <?= $e($p['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ==================== PRIMARY KPIs ==================== -->
<div class="row g-3 mb-4">
    <!-- Total orders -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Total Orders</span>
                    <span class="badge bg-primary-subtle text-primary">
                        <i class="bi bi-bag-check"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($totalOrders) ?></h3>
                <div class="text-muted small">
                    placed in range
                </div>
            </div>
        </div>
    </div>

    <!-- Delivered -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Delivered</span>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-check2-all"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($deliveredOrders) ?></h3>
                <div class="text-muted small">
                    successfully fulfilled
                </div>
            </div>
        </div>
    </div>

    <!-- Active -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Active</span>
                    <span class="badge bg-info-subtle text-info">
                        <i class="bi bi-hourglass-split"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($activeOrders) ?></h3>
                <div class="text-muted small">
                    in progress
                </div>
            </div>
        </div>
    </div>

    <!-- Success rate (hero card) -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"
             style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-uppercase fw-semibold opacity-75">Success Rate</span>
                    <i class="bi bi-graph-up-arrow fs-4 opacity-75"></i>
                </div>
                <h3 class="mb-2 fw-bold"><?= number_format($successRate, 1) ?>%</h3>
                <div class="small opacity-75">
                    <?= $fmtInt($deliveredOrders) ?> / <?= $fmtInt($totalOrders) ?> delivered
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CHARTS ROW ==================== -->
<div class="row g-3 mb-4">

    <!-- Daily trend chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-graph-up me-2 text-primary"></i>Daily Order Trend
                </h6>
                <span class="text-muted small"><?= $fmtInt(count($trend)) ?> day(s) of data</span>
            </div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No orders placed in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="orderTrendChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status breakdown doughnut -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-pie-chart me-2 text-primary"></i>Status Breakdown
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($statusLabels)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No status data.</div>
                    </div>
                <?php else: ?>
                    <canvas id="statusChart" height="180"></canvas>
                    <div class="mt-3 pt-3 border-top">
                        <?php foreach ($statusCounts as $status => $count):
                            if ((int) $count === 0) continue;
                            $meta = $statusMeta[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'color' => 'secondary'];
                            $pct = $totalOrders > 0 ? ($count / $totalOrders) * 100 : 0;
                        ?>
                            <div class="d-flex align-items-center gap-2 mb-2 small">
                                <span class="status-dot bg-<?= $meta['color'] ?>"></span>
                                <span class="flex-grow-1">
                                    <?= $e($meta['label']) ?>
                                </span>
                                <span class="text-muted">
                                    <?= number_format($pct, 1) ?>%
                                </span>
                                <span class="fw-medium text-end" style="min-width: 50px;">
                                    <?= $fmtInt($count) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== SERVICE MIX + TOP SERVICES ==================== -->
<div class="row g-3 mb-4">

    <!-- Service mix doughnut -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-diagram-3 me-2 text-primary"></i>Service Mix
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($serviceLabels)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-diagram-3 fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No service usage yet.</div>
                    </div>
                <?php else: ?>
                    <canvas id="serviceMixChart" height="180"></canvas>
                    <div class="mt-3 pt-3 border-top small text-muted">
                        Top <?= count($serviceMixTop) ?> service(s) by order count.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Service table -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-list-ol me-2 text-primary"></i>Service Performance
                </h6>
                <span class="text-muted small"><?= count($serviceMix) ?> service(s)</span>
            </div>
            <div class="table-responsive" style="max-height: 480px;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="small text-muted fw-semibold">Service</th>
                            <th class="small text-muted fw-semibold text-center">Orders</th>
                            <th class="small text-muted fw-semibold text-center">Items</th>
                            <th class="small text-muted fw-semibold text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($serviceMix)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    <div class="small">No services used in this range.</div>
                                </td>
                            </tr>
                        <?php else: foreach ($serviceMix as $sm):
                            $sid         = (int) ($sm['service_id'] ?? 0);
                            $sName       = (string) ($sm['service_name'] ?? '');
                            $sType       = (string) ($sm['service_type'] ?? '');
                            $sOrders     = (int) ($sm['orders_count'] ?? 0);
                            $sItems      = (int) ($sm['items_count']  ?? 0);
                            $sRevenue    = (float) ($sm['revenue'] ?? 0);
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-medium small">
                                        <a href="/admin/catalog/services/<?= $sid ?>"
                                           class="text-decoration-none">
                                            <?= $e($sName) ?>
                                        </a>
                                    </div>
                                    <span class="badge bg-<?= $typeBadge($sType) ?>" style="font-size:.65rem;">
                                        <?= $e($typeLabel($sType)) ?>
                                    </span>
                                </td>
                                <td class="text-center small">
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= $fmtInt($sOrders) ?>
                                    </span>
                                </td>
                                <td class="text-center small text-muted">
                                    <?= $fmtInt($sItems) ?>
                                </td>
                                <td class="text-end small fw-medium">
                                    <?= $fmtMoney($sRevenue) ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STATUS TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-table me-2 text-primary"></i>Orders by Status
        </h6>
        <span class="text-muted small"><?= count($statusCounts) ?> statuses</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Status</th>
                    <th class="small text-muted fw-semibold text-center">Count</th>
                    <th class="small text-muted fw-semibold text-end">% of Total</th>
                    <th class="small text-muted fw-semibold" style="width: 40%;">Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($statusCounts)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            <div class="small">No status data.</div>
                        </td>
                    </tr>
                <?php else: foreach ($statusMeta as $status => $meta):
                    $count = (int) ($statusCounts[$status] ?? 0);
                    $pct   = $totalOrders > 0 ? ($count / $totalOrders) * 100 : 0;
                ?>
                    <tr class="<?= $count === 0 ? 'text-muted' : '' ?>">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= $e($meta['icon']) ?> text-<?= $e($meta['color']) ?>"></i>
                                <span class="small fw-medium"><?= $e($meta['label']) ?></span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $e($meta['color']) ?>-subtle text-<?= $e($meta['color']) ?>">
                                <?= $fmtInt($count) ?>
                            </span>
                        </td>
                        <td class="text-end small text-muted">
                            <?= number_format($pct, 1) ?>%
                        </td>
                        <td>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-<?= $e($meta['color']) ?>"
                                     style="width: <?= $pct ?>%"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <td class="small fw-semibold">Total</td>
                    <td class="text-center fw-bold"><?= $fmtInt($totalOrders) ?></td>
                    <td class="text-end small fw-semibold">100%</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .status-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .table > :not(caption) > * > * {
        padding: .75rem .75rem;
        vertical-align: middle;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const trendLabels    = <?= json_encode($trendLabels, $jsonFlags) ?>;
    const trendTotal     = <?= json_encode($trendTotal, $jsonFlags) ?>;
    const trendDelivered = <?= json_encode($trendDelivered, $jsonFlags) ?>;
    const trendCancelled = <?= json_encode($trendCancelled, $jsonFlags) ?>;

    const statusLabels = <?= json_encode($statusLabels, $jsonFlags) ?>;
    const statusValues = <?= json_encode($statusValues, $jsonFlags) ?>;
    const statusHexes  = <?= json_encode($statusHexes, $jsonFlags) ?>;

    const serviceLabels = <?= json_encode($serviceLabels, $jsonFlags) ?>;
    const serviceValues = <?= json_encode($serviceValues, $jsonFlags) ?>;
    const serviceHexes  = <?= json_encode($serviceHexes, $jsonFlags) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // ---------- Daily order trend ----------
    const trendCanvas = document.getElementById('orderTrendChart');
    if (trendCanvas && trendLabels.length) {
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Total Orders',
                        data: trendTotal,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14,165,233,.08)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#0ea5e9',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                    },
                    {
                        label: 'Delivered',
                        data: trendDelivered,
                        borderColor: '#22c55e',
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#22c55e',
                        borderDash: [],
                    },
                    {
                        label: 'Cancelled',
                        data: trendCancelled,
                        borderColor: '#ef4444',
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        borderWidth: 2,
                        borderDash: [4, 4],
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#ef4444',
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, padding: 12, usePointStyle: true }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        title: { display: true, text: 'Orders', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 20 }
                    }
                }
            }
        });
    }

    // ---------- Status doughnut ----------
    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas && statusLabels.length) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: statusHexes,
                    borderColor: '#fff',
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ' + ctx.label + ': ' + Number(ctx.raw).toLocaleString() + ' orders'
                        }
                    }
                }
            }
        });
    }

    // ---------- Service mix doughnut ----------
    const serviceCanvas = document.getElementById('serviceMixChart');
    if (serviceCanvas && serviceLabels.length) {
        new Chart(serviceCanvas, {
            type: 'doughnut',
            data: {
                labels: serviceLabels,
                datasets: [{
                    data: serviceValues,
                    backgroundColor: serviceHexes,
                    borderColor: '#fff',
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            padding: 8,
                            usePointStyle: true,
                            font: { size: 10 },
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ' + ctx.label + ': ' + Number(ctx.raw).toLocaleString() + ' orders'
                        }
                    }
                }
            }
        });
    }
})();
</script>
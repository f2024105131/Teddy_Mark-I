<?php
/**
 * Admin Reports — Report Hub
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::index()
 *
 * Expected variables:
 *   $from      string   Y-m-d  start of range
 *   $to        string   Y-m-d  end of range
 *   $snapshot  array    [
 *     'orders_count','new_customers','revenue','complaints_count',
 *     'refunds_amount','deliveries_count',
 *     'prev_from','prev_to',
 *     'orders_change_pct','revenue_change_pct'
 *   ]
 *   $reports   array    list of report cards:
 *                       [['slug','name','icon','desc'], ...]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Reports';

// Safe defaults
$from     = $from     ?? date('Y-m-d', strtotime('-30 days'));
$to       = $to       ?? date('Y-m-d');
$snapshot = $snapshot ?? [];
$reports  = $reports  ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Extract snapshot values
$ordersCount     = (int)   ($snapshot['orders_count']      ?? 0);
$newCustomers    = (int)   ($snapshot['new_customers']     ?? 0);
$revenue         = (float) ($snapshot['revenue']           ?? 0);
$complaintsCount = (int)   ($snapshot['complaints_count']  ?? 0);
$refundsAmount   = (float) ($snapshot['refunds_amount']    ?? 0);
$deliveriesCount = (int)   ($snapshot['deliveries_count']  ?? 0);
$prevFrom        = $snapshot['prev_from'] ?? null;
$prevTo          = $snapshot['prev_to']   ?? null;
$ordersChange    = $snapshot['orders_change_pct']  ?? null;
$revenueChange   = $snapshot['revenue_change_pct'] ?? null;

// Range length
$rangeDays = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);

// Trend helper: returns Bootstrap class + arrow + formatted pct
$renderTrend = function (?float $pct): array {
    if ($pct === null) {
        return ['class' => 'secondary', 'icon' => 'bi-dash', 'label' => '—', 'raw' => null];
    }
    if ($pct > 0) {
        return [
            'class' => 'success',
            'icon'  => 'bi-arrow-up-right',
            'label' => number_format($pct, 1) . '%',
            'raw'   => $pct,
        ];
    }
    if ($pct < 0) {
        return [
            'class' => 'danger',
            'icon'  => 'bi-arrow-down-right',
            'label' => number_format(abs($pct), 1) . '%',
            'raw'   => $pct,
        ];
    }
    return ['class' => 'secondary', 'icon' => 'bi-dash', 'label' => '0%', 'raw' => 0.0];
};

$ordersTrend  = $renderTrend($ordersChange);
$revenueTrend = $renderTrend($revenueChange);

// Colour per report card (keeps UI lively and scannable)
$reportColors = [
    'sales'             => ['color' => 'success',   'icon-bg' => 'bg-success-subtle text-success'],
    'orders'            => ['color' => 'primary',   'icon-bg' => 'bg-primary-subtle text-primary'],
    'customers'         => ['color' => 'info',      'icon-bg' => 'bg-info-subtle text-info'],
    'staff-performance' => ['color' => 'warning',   'icon-bg' => 'bg-warning-subtle text-warning'],
    'complaints'        => ['color' => 'danger',    'icon-bg' => 'bg-danger-subtle text-danger'],
    'refunds'           => ['color' => 'secondary', 'icon-bg' => 'bg-secondary-subtle text-secondary'],
    'items'             => ['color' => 'dark',      'icon-bg' => 'bg-dark-subtle text-dark'],
    'deliveries'        => ['color' => 'info',      'icon-bg' => 'bg-info-subtle text-info'],
];

// Quick-range presets
$presets = [
    ['label' => 'Today',        'from' => date('Y-m-d'),                 'to' => date('Y-m-d')],
    ['label' => 'Last 7 days',  'from' => date('Y-m-d', strtotime('-6 days')),  'to' => date('Y-m-d')],
    ['label' => 'Last 30 days', 'from' => date('Y-m-d', strtotime('-29 days')), 'to' => date('Y-m-d')],
    ['label' => 'This month',   'from' => date('Y-m-01'),                 'to' => date('Y-m-d')],
    ['label' => 'Last 90 days', 'from' => date('Y-m-d', strtotime('-89 days')), 'to' => date('Y-m-d')],
    ['label' => 'This year',    'from' => date('Y-01-01'),                'to' => date('Y-m-d')],
];
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Reports & Analytics</h1>
        <p class="text-muted mb-0 small">
            Business intelligence and performance insights
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
        </a>
    </div>
</div>

<!-- ==================== DATE RANGE FILTER ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports" class="row g-2 align-items-end mb-3">
            <div class="col-md-3 col-lg-3">
                <label for="from" class="form-label small text-muted mb-1">From</label>
                <input type="date"
                       class="form-control"
                       id="from"
                       name="from"
                       value="<?= $e($from) ?>"
                       max="<?= $e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-3 col-lg-3">
                <label for="to" class="form-label small text-muted mb-1">To</label>
                <input type="date"
                       class="form-control"
                       id="to"
                       name="to"
                       value="<?= $e($to) ?>"
                       max="<?= $e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-3 col-lg-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Apply Range
                </button>
            </div>
            <div class="col-md-3 col-lg-3 text-md-end">
                <div class="small text-muted">
                    <i class="bi bi-calendar-range me-1"></i>
                    <?= $fmtInt($rangeDays) ?> day(s) ·
                    <?= $e($fmtDate($from)) ?> → <?= $e($fmtDate($to)) ?>
                </div>
            </div>
        </form>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted small me-1">Quick range:</span>
            <?php foreach ($presets as $p):
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

<!-- ==================== OVERVIEW SNAPSHOT ==================== -->
<div class="row g-3 mb-4">

    <!-- Revenue (with trend) -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Revenue</span>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-cash-coin"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-2 flex-wrap">
                    <h4 class="mb-0 fw-bold"><?= $fmtMoney($revenue) ?></h4>
                    <?php if ($revenueTrend['raw'] !== null): ?>
                        <span class="badge bg-<?= $revenueTrend['class'] ?>-subtle text-<?= $revenueTrend['class'] ?>">
                            <i class="bi <?= $revenueTrend['icon'] ?>"></i>
                            <?= $e($revenueTrend['label']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($prevFrom && $prevTo): ?>
                    <div class="text-muted small mt-2">
                        vs <?= $fmtMoney($snapshot['prev_revenue'] ?? 0) ?>
                        <br>
                        <span style="font-size:.72rem;">
                            <?= $e($fmtDate($prevFrom)) ?> → <?= $e($fmtDate($prevTo)) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Orders (with trend) -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Orders</span>
                    <span class="badge bg-primary-subtle text-primary">
                        <i class="bi bi-bag-check"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-2 flex-wrap">
                    <h4 class="mb-0 fw-bold"><?= $fmtInt($ordersCount) ?></h4>
                    <?php if ($ordersTrend['raw'] !== null): ?>
                        <span class="badge bg-<?= $ordersTrend['class'] ?>-subtle text-<?= $ordersTrend['class'] ?>">
                            <i class="bi <?= $ordersTrend['icon'] ?>"></i>
                            <?= $e($ordersTrend['label']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($prevFrom && $prevTo): ?>
                    <div class="text-muted small mt-2">
                        vs <?= $fmtInt($snapshot['prev_orders_count'] ?? 0) ?> orders
                        <br>
                        <span style="font-size:.72rem;">
                            previous period
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- New customers -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">New Customers</span>
                    <span class="badge bg-info-subtle text-info">
                        <i class="bi bi-person-plus"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h4 class="mb-0 fw-bold"><?= $fmtInt($newCustomers) ?></h4>
                </div>
                <div class="text-muted small mt-2">
                    in this period
                </div>
            </div>
        </div>
    </div>

    <!-- Deliveries -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Deliveries</span>
                    <span class="badge bg-info-subtle text-info">
                        <i class="bi bi-truck"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h4 class="mb-0 fw-bold"><?= $fmtInt($deliveriesCount) ?></h4>
                </div>
                <div class="text-muted small mt-2">
                    completed in range
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== SECONDARY SNAPSHOT ==================== -->
<div class="row g-3 mb-4">

    <!-- Complaints -->
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-danger-subtle text-danger">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-semibold">Complaints Filed</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($complaintsCount) ?></div>
                    </div>
                    <a href="/admin/reports/complaints?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Refunds -->
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-secondary-subtle text-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-semibold">Refunds Issued</div>
                        <div class="fw-bold fs-6 mb-0"><?= $fmtMoney($refundsAmount) ?></div>
                    </div>
                    <a href="/admin/reports/refunds?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Net revenue -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-semibold">Net Revenue</div>
                        <div class="fw-bold fs-6 mb-0">
                            <?= $fmtMoney($revenue - $refundsAmount) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REPORT CARDS ==================== -->
<h5 class="mb-3">
    <i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>
    Available Reports
</h5>

<div class="row g-3">
    <?php if (empty($reports)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-bar-graph fs-1 d-block mb-2 opacity-50"></i>
                    No reports available.
                </div>
            </div>
        </div>
    <?php else: foreach ($reports as $report):
        $slug     = (string) ($report['slug'] ?? '');
        $name     = (string) ($report['name'] ?? $slug);
        $icon     = (string) ($report['icon'] ?? 'bi-file-earmark-bar-graph');
        $desc     = (string) ($report['desc'] ?? '');
        $colors   = $reportColors[$slug] ?? ['color' => 'primary', 'icon-bg' => 'bg-primary-subtle text-primary'];
        $reportUrl = '/admin/reports/' . $slug . '?from=' . urlencode($from) . '&to=' . urlencode($to);
        $jsonUrl   = '/admin/reports/' . $slug . '/json?from=' . urlencode($from) . '&to=' . urlencode($to);
    ?>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 report-card">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div class="report-icon <?= $e($colors['icon-bg']) ?>">
                            <i class="bi <?= $e($icon) ?>"></i>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle-split"
                                    data-bs-toggle="dropdown"
                                    aria-label="Report options">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?= $e($reportUrl) ?>">
                                        <i class="bi bi-eye me-2"></i>View Report
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= $e($jsonUrl) ?>" target="_blank">
                                        <i class="bi bi-code-slash me-2"></i>View JSON
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item"
                                       href="/admin/reports/export?report=<?= urlencode($slug) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>">
                                        <i class="bi bi-download me-2"></i>Export CSV
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-2"><?= $e($name) ?></h6>
                    <p class="text-muted small mb-3 flex-grow-1">
                        <?= $e($desc) ?>
                    </p>

                    <a href="<?= $e($reportUrl) ?>"
                       class="btn btn-sm btn-outline-<?= $e($colors['color']) ?> mt-auto">
                        View Report <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<!-- ==================== QUICK EXPORT ==================== -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-download me-2 text-primary"></i>Quick Export
        </h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Export any report as a CSV file over the selected date range
            (<?= $e($fmtDate($from)) ?> → <?= $e($fmtDate($to)) ?>).
        </p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($reports as $report):
                $slug = (string) ($report['slug'] ?? '');
                $name = (string) ($report['name'] ?? $slug);
                $colors = $reportColors[$slug] ?? ['color' => 'secondary'];
            ?>
                <a href="/admin/reports/export?report=<?= urlencode($slug) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
                   class="btn btn-sm btn-outline-<?= $e($colors['color']) ?>">
                    <i class="bi bi-download me-1"></i><?= $e($name) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .kpi-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .report-icon {
        width: 52px; height: 52px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .report-card {
        transition: transform .15s, box-shadow .15s;
    }
    .report-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(15,23,42,.08) !important;
    }
</style>
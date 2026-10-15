<?php
/**
 * Admin Reports — Customers
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::customers()
 *
 * Expected variables:
 *   $from           string   Y-m-d
 *   $to             string   Y-m-d
 *   $summary        array    [
 *     total_customers, active_customers, new_customers, new_active,
 *     buyers_in_range, repeat_buyers, repeat_rate_pct
 *   ]
 *   $signupTrend    array    rows: [d, total]
 *   $topSpenders    array    rows: [customer_id, full_name, email, phone, city,
 *                                   orders_count, total_spent]
 *   $cityBreakdown  array    rows: [city, total]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Customers Report';

// Safe defaults
$from          = $from          ?? date('Y-m-d', strtotime('-30 days'));
$to            = $to            ?? date('Y-m-d');
$summary       = $summary       ?? [];
$signupTrend   = $signupTrend   ?? [];
$topSpenders   = $topSpenders   ?? [];
$cityBreakdown = $cityBreakdown ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Extract summary
$totalCustomers   = (int)   ($summary['total_customers']  ?? 0);
$activeCustomers  = (int)   ($summary['active_customers'] ?? 0);
$newCustomers     = (int)   ($summary['new_customers']    ?? 0);
$newActive        = (int)   ($summary['new_active']       ?? 0);
$buyersInRange    = (int)   ($summary['buyers_in_range']  ?? 0);
$repeatBuyers     = (int)   ($summary['repeat_buyers']    ?? 0);
$repeatRatePct    = (float) ($summary['repeat_rate_pct']  ?? 0);

// Growth rate (active vs total)
$activeRatePct = $totalCustomers > 0 ? ($activeCustomers / $totalCustomers) * 100 : 0;

// Signup trend data for chart
$trendLabels = [];
$trendValues = [];
$trendSum = 0;
foreach ($signupTrend as $row) {
    $d = (string) ($row['d'] ?? '');
    $v = (int) ($row['total'] ?? 0);
    $trendLabels[] = $d ? date('M j', strtotime($d)) : '';
    $trendValues[] = $v;
    $trendSum += $v;
}

// Top cities (top 8 for readability)
$cityTop = array_slice($cityBreakdown, 0, 8);
$cityMax = 1;
foreach ($cityTop as $c) {
    $cityMax = max($cityMax, (int) ($c['total'] ?? 0));
}

// Avatar colors — cycle through a palette based on customer_id
$avatarColors = [
    'bg-primary-subtle text-primary',
    'bg-success-subtle text-success',
    'bg-info-subtle text-info',
    'bg-warning-subtle text-warning',
    'bg-danger-subtle text-danger',
    'bg-dark-subtle text-dark',
];
$avatarColor = fn(int $id): string => $avatarColors[$id % count($avatarColors)];

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
                <i class="bi bi-people me-2 text-info"></i>Customers Report
            </h1>
            <p class="text-muted mb-0 small">
                <strong><?= $e($fmtDate($from)) ?></strong> → <strong><?= $e($fmtDate($to)) ?></strong>
                · <?= $fmtInt($newCustomers) ?> new signup(s)
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/reports/customers/json?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-code-slash me-1"></i>JSON
        </a>
        <a href="/admin/reports/export?report=customers&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== FILTER BAR ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports/customers" class="row g-2 align-items-end">
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
    <!-- New signups (hero) -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"
             style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: #fff;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-uppercase fw-semibold opacity-75">New Signups</span>
                    <i class="bi bi-person-plus-fill fs-4 opacity-75"></i>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($newCustomers) ?></h3>
                <div class="small opacity-75">
                    <?= $fmtInt($newActive) ?> activated
                </div>
            </div>
        </div>
    </div>

    <!-- Total customers -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Total Customers</span>
                    <span class="badge bg-primary-subtle text-primary">
                        <i class="bi bi-people"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($totalCustomers) ?></h3>
                <div class="text-muted small">
                    <?= $fmtInt($activeCustomers) ?> active (<?= number_format($activeRatePct, 1) ?>%)
                </div>
            </div>
        </div>
    </div>

    <!-- Buyers in range -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Buyers</span>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-bag-check"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($buyersInRange) ?></h3>
                <div class="text-muted small">
                    placed an order in range
                </div>
            </div>
        </div>
    </div>

    <!-- Repeat rate -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Repeat Rate</span>
                    <span class="badge bg-warning-subtle text-warning">
                        <i class="bi bi-arrow-repeat"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= number_format($repeatRatePct, 1) ?>%</h3>
                <div class="text-muted small">
                    <?= $fmtInt($repeatBuyers) ?> bought 2+ times
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== SIGNUP TREND CHART ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-graph-up-arrow me-2 text-info"></i>New Customer Signups
        </h6>
        <span class="text-muted small">
            <?= $fmtInt($trendSum) ?> total in <?= $fmtInt(count($signupTrend)) ?> day(s)
        </span>
    </div>
    <div class="card-body">
        <?php if (empty($signupTrend)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                <div class="small">No new signups in this range.</div>
            </div>
        <?php else: ?>
            <canvas id="signupTrendChart" height="80"></canvas>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3 mb-4">

    <!-- Top spenders -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-trophy me-2 text-warning"></i>Top Spenders
                </h6>
                <span class="text-muted small">Top <?= count($topSpenders) ?> customers</span>
            </div>
            <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="small text-muted fw-semibold" style="width: 40px;">#</th>
                            <th class="small text-muted fw-semibold">Customer</th>
                            <th class="small text-muted fw-semibold">City</th>
                            <th class="small text-muted fw-semibold text-center">Orders</th>
                            <th class="small text-muted fw-semibold text-end">Total Spent</th>
                            <th class="small text-muted fw-semibold text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topSpenders)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    <div class="small">No customer spending data in this range.</div>
                                </td>
                            </tr>
                        <?php else: foreach ($topSpenders as $i => $c):
                            $rank   = $i + 1;
                            $cid    = (int) ($c['customer_id'] ?? 0);
                            $cName  = (string) ($c['full_name'] ?? '');
                            $cEmail = (string) ($c['email'] ?? '');
                            $cPhone = (string) ($c['phone'] ?? '');
                            $cCity  = (string) ($c['city'] ?? '');
                            $cOrders = (int) ($c['orders_count'] ?? 0);
                            $cSpent  = (float) ($c['total_spent'] ?? 0);
                            $rankBadge = match ($rank) {
                                1 => 'bg-warning text-dark',
                                2 => 'bg-secondary text-white',
                                3 => 'bg-danger text-white',
                                default => 'bg-light text-dark border',
                            };
                        ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $rankBadge ?>"><?= $rank ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle-sm <?= $avatarColor($cid) ?>">
                                            <?= $e(strtoupper(substr($cName, 0, 1))) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fw-medium small text-truncate"
                                                 style="max-width: 220px;">
                                                <a href="/admin/customers/<?= $cid ?>"
                                                   class="text-decoration-none">
                                                    <?= $e($cName) ?>
                                                </a>
                                            </div>
                                            <div class="text-muted" style="font-size:.72rem;">
                                                <?= $e($cEmail) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    <?php if ($cCity !== ''): ?>
                                        <i class="bi bi-geo-alt me-1"></i><?= $e($cCity) ?>
                                    <?php else: ?>
                                        <span class="fst-italic">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center small">
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= $fmtInt($cOrders) ?>
                                    </span>
                                </td>
                                <td class="text-end small fw-medium">
                                    <?= $fmtMoney($cSpent) ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/customers/<?= $cid ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="View customer">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- City breakdown -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-geo-alt me-2 text-primary"></i>By City
                </h6>
                <span class="text-muted small"><?= count($cityBreakdown) ?> cit<?= count($cityBreakdown) === 1 ? 'y' : 'ies' ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($cityTop)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-geo-alt fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No new signups in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="cityChart" height="180"></canvas>
                    <div class="mt-3 pt-3 border-top">
                        <?php foreach ($cityTop as $c):
                            $cCity  = (string) ($c['city'] ?? '');
                            $cCount = (int) ($c['total'] ?? 0);
                            $pct    = $cityMax > 0 ? ($cCount / $cityMax) * 100 : 0;
                        ?>
                            <div class="mb-2">
                                <div class="d-flex align-items-center justify-content-between small mb-1">
                                    <span class="fw-medium text-truncate" style="max-width: 180px;">
                                        <i class="bi bi-geo-alt text-muted me-1"></i>
                                        <?= $e($cCity) ?>
                                    </span>
                                    <span class="text-muted">
                                        <?= $fmtInt($cCount) ?> new
                                    </span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-info" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($cityBreakdown) > count($cityTop)): ?>
                            <div class="text-center text-muted small mt-2">
                                +<?= count($cityBreakdown) - count($cityTop) ?> more cit<?= (count($cityBreakdown) - count($cityTop)) === 1 ? 'y' : 'ies' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CUSTOMER ENGAGEMENT METRICS ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-bar-chart-steps me-2 text-primary"></i>Customer Engagement Metrics
        </h6>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <!-- Active rate -->
            <div class="col-md-6 col-xl-3">
                <div class="metric-block">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-person-check fs-4 text-success"></i>
                        <div class="text-muted small text-uppercase fw-semibold">Active Rate</div>
                    </div>
                    <div class="fs-3 fw-bold"><?= number_format($activeRatePct, 1) ?>%</div>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?= $activeRatePct ?>%"></div>
                    </div>
                    <div class="text-muted small mt-2">
                        <?= $fmtInt($activeCustomers) ?> of <?= $fmtInt($totalCustomers) ?> customers active
                    </div>
                </div>
            </div>

            <!-- Buyer rate -->
            <div class="col-md-6 col-xl-3">
                <?php
                $buyerRatePct = $totalCustomers > 0
                    ? ($buyersInRange / $totalCustomers) * 100
                    : 0;
                ?>
                <div class="metric-block">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-bag-check fs-4 text-primary"></i>
                        <div class="text-muted small text-uppercase fw-semibold">Buyer Rate</div>
                    </div>
                    <div class="fs-3 fw-bold"><?= number_format($buyerRatePct, 1) ?>%</div>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-primary" style="width: <?= min(100, $buyerRatePct) ?>%"></div>
                    </div>
                    <div class="text-muted small mt-2">
                        <?= $fmtInt($buyersInRange) ?> of <?= $fmtInt($totalCustomers) ?> ordered in range
                    </div>
                </div>
            </div>

            <!-- Repeat rate -->
            <div class="col-md-6 col-xl-3">
                <div class="metric-block">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-arrow-repeat fs-4 text-warning"></i>
                        <div class="text-muted small text-uppercase fw-semibold">Repeat Rate</div>
                    </div>
                    <div class="fs-3 fw-bold"><?= number_format($repeatRatePct, 1) ?>%</div>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-warning" style="width: <?= $repeatRatePct ?>%"></div>
                    </div>
                    <div class="text-muted small mt-2">
                        <?= $fmtInt($repeatBuyers) ?> of <?= $fmtInt($buyersInRange) ?> ordered 2+ times
                    </div>
                </div>
            </div>

            <!-- New customer rate -->
            <div class="col-md-6 col-xl-3">
                <?php
                $newRatePct = $totalCustomers > 0
                    ? ($newCustomers / $totalCustomers) * 100
                    : 0;
                ?>
                <div class="metric-block">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-person-plus fs-4 text-info"></i>
                        <div class="text-muted small text-uppercase fw-semibold">New This Period</div>
                    </div>
                    <div class="fs-3 fw-bold"><?= number_format($newRatePct, 1) ?>%</div>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-info" style="width: <?= min(100, $newRatePct) ?>%"></div>
                    </div>
                    <div class="text-muted small mt-2">
                        <?= $fmtInt($newCustomers) ?> new signup(s) this period
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .avatar-circle-sm {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .8rem;
        flex-shrink: 0;
    }
    .min-w-0 { min-width: 0; }
    .metric-block {
        height: 100%;
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
    }
    .table > :not(caption) > * > * {
        padding: .7rem .75rem;
        vertical-align: middle;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const trendLabels = <?= json_encode($trendLabels, $jsonFlags) ?>;
    const trendValues = <?= json_encode($trendValues, $jsonFlags) ?>;

    const cityLabels = <?= json_encode(array_map(fn($c) => $c['city'] ?? '', $cityTop), $jsonFlags) ?>;
    const cityValues = <?= json_encode(array_map(fn($c) => (int) ($c['total'] ?? 0), $cityTop), $jsonFlags) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // ---------- Signup trend chart ----------
    const signupCanvas = document.getElementById('signupTrendChart');
    if (signupCanvas && trendLabels.length) {
        const ctx = signupCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(6,182,212,.28)');
        gradient.addColorStop(1, 'rgba(6,182,212,.02)');

        new Chart(signupCanvas, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'New Signups',
                    data: trendValues,
                    borderColor: '#06b6d4',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#06b6d4',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ' + Number(ctx.raw).toLocaleString() + ' new customer(s)'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        title: { display: true, text: 'New Signups', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 20 }
                    }
                }
            }
        });
    }

    // ---------- City bar chart ----------
    const cityCanvas = document.getElementById('cityChart');
    if (cityCanvas && cityLabels.length) {
        new Chart(cityCanvas, {
            type: 'bar',
            data: {
                labels: cityLabels,
                datasets: [{
                    label: 'New Customers',
                    data: cityValues,
                    backgroundColor: '#06b6d4',
                    borderRadius: 4,
                    maxBarThickness: 32,
                }]
            },
            options: {
                indexAxis: 'y',   // horizontal bars
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ' + Number(ctx.raw).toLocaleString() + ' new customer(s)'
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        title: { display: true, text: 'New Customers', font: { size: 10 } }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
})();
</script>
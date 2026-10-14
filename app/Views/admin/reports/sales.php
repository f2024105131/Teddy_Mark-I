<?php
/**
 * Admin Reports — Sales & Revenue
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::sales()
 *
 * Expected variables:
 *   $from             string   Y-m-d
 *   $to               string   Y-m-d
 *   $groupBy          string   'day' | 'week' | 'month'
 *   $summary          array    [
 *     approved_revenue, pending_revenue, rejected_revenue,
 *     approved_count, pending_count, avg_payment,
 *     total_subtotal, total_tax, total_discount,
 *     total_billed, total_paid, total_outstanding, bill_count,
 *     total_refunds, net_revenue
 *   ]
 *   $revenueByPeriod  array    rows: [period, total, payments]
 *   $revenueByMethod  array    rows: [payment_method, payments, total]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Sales & Revenue Report';

// Safe defaults
$from    = $from    ?? date('Y-m-d', strtotime('-30 days'));
$to      = $to      ?? date('Y-m-d');
$groupBy = $groupBy ?? 'day';

$summary          = $summary          ?? [];
$revenueByPeriod  = $revenueByPeriod  ?? [];
$revenueByMethod  = $revenueByMethod  ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Extract summary
$approvedRevenue = (float) ($summary['approved_revenue']  ?? 0);
$pendingRevenue  = (float) ($summary['pending_revenue']   ?? 0);
$rejectedRevenue = (float) ($summary['rejected_revenue']  ?? 0);
$approvedCount   = (int)   ($summary['approved_count']    ?? 0);
$pendingCount    = (int)   ($summary['pending_count']     ?? 0);
$avgPayment      = (float) ($summary['avg_payment']       ?? 0);
$totalSubtotal   = (float) ($summary['total_subtotal']    ?? 0);
$totalTax        = (float) ($summary['total_tax']         ?? 0);
$totalDiscount   = (float) ($summary['total_discount']    ?? 0);
$totalBilled     = (float) ($summary['total_billed']      ?? 0);
$totalPaid       = (float) ($summary['total_paid']        ?? 0);
$totalOutstanding= (float) ($summary['total_outstanding'] ?? 0);
$billCount       = (int)   ($summary['bill_count']        ?? 0);
$totalRefunds    = (float) ($summary['total_refunds']     ?? 0);
$netRevenue      = (float) ($summary['net_revenue']       ?? 0);

// Payment method colors
$methodColors = [
    'easypaisa'     => ['badge' => 'success',   'hex' => '#22c55e'],
    'jazzcash'      => ['badge' => 'danger',    'hex' => '#ef4444'],
    'bank_transfer' => ['badge' => 'primary',   'hex' => '#0ea5e9'],
    'cash'          => ['badge' => 'warning',   'hex' => '#f59e0b'],
];
$methodColor = fn(string $m): array => $methodColors[$m] ?? ['badge' => 'secondary', 'hex' => '#64748b'];

// Prepare chart data
$periodLabels = [];
$periodValues = [];
$periodCounts = [];
foreach ($revenueByPeriod as $row) {
    $periodLabels[] = $row['period'] ?? '';
    $periodValues[] = (float) ($row['total'] ?? 0);
    $periodCounts[] = (int) ($row['payments'] ?? 0);
}

// Method chart data
$methodLabels = [];
$methodValues = [];
$methodHexes  = [];
$methodSum    = 0.0;
foreach ($revenueByMethod as $row) {
    $m = (string) ($row['payment_method'] ?? '');
    $v = (float) ($row['total'] ?? 0);
    $methodLabels[] = ucwords(str_replace('_', ' ', $m));
    $methodValues[] = $v;
    $methodHexes[]  = $methodColor($m)['hex'];
    $methodSum     += $v;
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

// Group-by options
$groupOptions = [
    'day'   => 'Daily',
    'week'  => 'Weekly',
    'month' => 'Monthly',
];
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/reports?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1">
                <i class="bi bi-cash-coin me-2 text-success"></i>Sales & Revenue
            </h1>
            <p class="text-muted mb-0 small">
                <strong><?= $e($fmtDate($from)) ?></strong> → <strong><?= $e($fmtDate($to)) ?></strong>
                · <?= $fmtInt($billCount) ?> bills · <?= $fmtInt($approvedCount) ?> approved payments
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/reports/sales/json?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&group=<?= urlencode($groupBy) ?>"
           class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-code-slash me-1"></i>JSON
        </a>
        <a href="/admin/reports/export?report=sales&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== FILTER BAR ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports/sales" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="from" class="form-label small text-muted mb-1">From</label>
                <input type="date"
                       class="form-control"
                       id="from"
                       name="from"
                       value="<?= $e($from) ?>"
                       max="<?= $e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-3">
                <label for="to" class="form-label small text-muted mb-1">To</label>
                <input type="date"
                       class="form-control"
                       id="to"
                       name="to"
                       value="<?= $e($to) ?>"
                       max="<?= $e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-2">
                <label for="group" class="form-label small text-muted mb-1">Group by</label>
                <select class="form-select" id="group" name="group">
                    <?php foreach ($groupOptions as $key => $label): ?>
                        <option value="<?= $e($key) ?>" <?= $groupBy === $key ? 'selected' : '' ?>>
                            <?= $e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Apply
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
                <a href="?from=<?= $e($p['from']) ?>&to=<?= $e($p['to']) ?>&group=<?= $e($groupBy) ?>"
                   class="badge text-decoration-none <?= $isActive ? 'bg-primary' : 'bg-light text-dark border' ?>">
                    <?= $e($p['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ==================== PRIMARY KPIs ==================== -->
<div class="row g-3 mb-4">
    <!-- Approved revenue (hero card) -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-uppercase fw-semibold opacity-75">Approved Revenue</span>
                    <i class="bi bi-check-circle-fill fs-4 opacity-75"></i>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($approvedRevenue) ?></h3>
                <div class="small opacity-75">
                    <?= $fmtInt($approvedCount) ?> payment(s)
                </div>
            </div>
        </div>
    </div>

    <!-- Net revenue -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Net Revenue</span>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-graph-up"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($netRevenue) ?></h3>
                <div class="text-muted small">
                    After <?= $fmtMoney($totalRefunds) ?> in refunds
                </div>
            </div>
        </div>
    </div>

    <!-- Pending revenue -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Pending</span>
                    <span class="badge bg-warning-subtle text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($pendingRevenue) ?></h3>
                <div class="text-muted small">
                    <?= $fmtInt($pendingCount) ?> awaiting approval
                </div>
            </div>
        </div>
    </div>

    <!-- Avg payment -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Avg Payment</span>
                    <span class="badge bg-info-subtle text-info">
                        <i class="bi bi-calculator"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($avgPayment) ?></h3>
                <div class="text-muted small">
                    per approved payment
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== BILLING BREAKDOWN ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Subtotal</div>
                <div class="fw-bold fs-6"><?= $fmtMoney($totalSubtotal) ?></div>
                <div class="text-muted" style="font-size:.72rem;">before tax & discounts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Tax Collected</div>
                <div class="fw-bold fs-6 text-primary"><?= $fmtMoney($totalTax) ?></div>
                <div class="text-muted" style="font-size:.72rem;">on billed orders</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Discounts Given</div>
                <div class="fw-bold fs-6 text-warning"><?= $fmtMoney($totalDiscount) ?></div>
                <div class="text-muted" style="font-size:.72rem;">via promotions</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Outstanding</div>
                <div class="fw-bold fs-6 text-danger"><?= $fmtMoney($totalOutstanding) ?></div>
                <div class="text-muted" style="font-size:.72rem;">unpaid + partial</div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3 mb-4">

    <!-- Revenue over time chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-graph-up-arrow me-2 text-success"></i>Revenue Trend
                </h6>
                <span class="text-muted small">
                    Grouped by <?= $e(strtolower($groupOptions[$groupBy] ?? 'day')) ?>
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($periodLabels)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No revenue data in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="revenueTrendChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment method breakdown -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-pie-chart me-2 text-primary"></i>By Payment Method
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($revenueByMethod)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No payment data.</div>
                    </div>
                <?php else: ?>
                    <canvas id="methodChart" height="180"></canvas>

                    <div class="mt-3 pt-3 border-top">
                        <?php foreach ($revenueByMethod as $m):
                            $mMethod = (string) ($m['payment_method'] ?? '');
                            $mTotal  = (float)  ($m['total'] ?? 0);
                            $mCount  = (int)    ($m['payments'] ?? 0);
                            $mPct    = $methodSum > 0 ? ($mTotal / $methodSum) * 100 : 0;
                            $mColor  = $methodColor($mMethod);
                        ?>
                            <div class="d-flex align-items-center gap-2 mb-2 small">
                                <span class="method-dot" style="background: <?= $e($mColor['hex']) ?>"></span>
                                <span class="flex-grow-1">
                                    <?= $e(ucwords(str_replace('_', ' ', $mMethod))) ?>
                                </span>
                                <span class="text-muted">
                                    <?= number_format($mPct, 1) ?>%
                                </span>
                                <span class="fw-medium text-end" style="min-width: 90px;">
                                    <?= $fmtMoney($mTotal) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== PERIOD BREAKDOWN TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-table me-2 text-primary"></i>Period Breakdown
        </h6>
        <span class="text-muted small"><?= $fmtInt(count($revenueByPeriod)) ?> row(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Period</th>
                    <th class="small text-muted fw-semibold text-center">Payments</th>
                    <th class="small text-muted fw-semibold text-end">Revenue</th>
                    <th class="small text-muted fw-semibold text-end">% of Total</th>
                    <th class="small text-muted fw-semibold" style="width: 30%;">Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($revenueByPeriod)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            <div class="small">No revenue data in this range.</div>
                        </td>
                    </tr>
                <?php else:
                    // Reverse for most-recent-first
                    $rowsDesc = array_reverse($revenueByPeriod);
                    $totalSum = array_sum(array_column($revenueByPeriod, 'total'));
                    foreach ($rowsDesc as $row):
                        $period = (string) ($row['period'] ?? '');
                        $total  = (float)  ($row['total'] ?? 0);
                        $count  = (int)    ($row['payments'] ?? 0);
                        $pct    = $totalSum > 0 ? ($total / $totalSum) * 100 : 0;

                        // Format period label based on groupBy
                        $periodLabel = $period;
                        if ($groupBy === 'day' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
                            $periodLabel = date('M j, Y', strtotime($period));
                        } elseif ($groupBy === 'month' && preg_match('/^\d{4}-\d{2}$/', $period)) {
                            $periodLabel = date('F Y', strtotime($period . '-01'));
                        }
                ?>
                    <tr>
                        <td class="small fw-medium"><?= $e($periodLabel) ?></td>
                        <td class="text-center small">
                            <span class="badge bg-primary-subtle text-primary">
                                <?= $fmtInt($count) ?>
                            </span>
                        </td>
                        <td class="text-end small fw-medium"><?= $fmtMoney($total) ?></td>
                        <td class="text-end small text-muted"><?= number_format($pct, 1) ?>%</td>
                        <td>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($revenueByPeriod)): ?>
                <tfoot>
                    <tr class="table-light">
                        <td class="small fw-semibold">Total</td>
                        <td class="text-center small fw-semibold">
                            <?= $fmtInt(array_sum(array_column($revenueByPeriod, 'payments'))) ?>
                        </td>
                        <td class="text-end fw-bold"><?= $fmtMoney($totalSum ?? 0) ?></td>
                        <td class="text-end small fw-semibold">100%</td>
                        <td></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .method-dot {
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
    const periodLabels = <?= json_encode($periodLabels, $jsonFlags) ?>;
    const periodValues = <?= json_encode($periodValues, $jsonFlags) ?>;
    const periodCounts = <?= json_encode($periodCounts, $jsonFlags) ?>;

    const methodLabels = <?= json_encode($methodLabels, $jsonFlags) ?>;
    const methodValues = <?= json_encode($methodValues, $jsonFlags) ?>;
    const methodHexes  = <?= json_encode($methodHexes, $jsonFlags) ?>;

    const groupBy = <?= json_encode($groupBy) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // ---------- Revenue trend chart ----------
    const trendCanvas = document.getElementById('revenueTrendChart');
    if (trendCanvas && periodLabels.length) {
        const ctx = trendCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(34,197,94,.28)');
        gradient.addColorStop(1, 'rgba(34,197,94,.02)');

        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: periodLabels,
                datasets: [{
                    label: 'Revenue (Rs.)',
                    data: periodValues,
                    borderColor: '#22c55e',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#22c55e',
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
                            label: (ctx) => {
                                const v = Number(ctx.raw);
                                return ' Revenue: Rs. ' + v.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                            },
                            afterLabel: (ctx) => {
                                const idx = ctx.dataIndex;
                                const count = periodCounts[idx] ?? 0;
                                return ` ${count} payment(s)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => 'Rs. ' + Number(v).toLocaleString()
                        },
                        title: { display: true, text: 'Revenue', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 20 }
                    }
                }
            }
        });
    }

    // ---------- Payment method doughnut ----------
    const methodCanvas = document.getElementById('methodChart');
    if (methodCanvas && methodLabels.length) {
        new Chart(methodCanvas, {
            type: 'doughnut',
            data: {
                labels: methodLabels,
                datasets: [{
                    data: methodValues,
                    backgroundColor: methodHexes,
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
                            label: (ctx) => {
                                const v = Number(ctx.raw);
                                return ' ' + ctx.label + ': Rs. ' + v.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
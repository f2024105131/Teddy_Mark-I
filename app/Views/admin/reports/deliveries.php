<?php
/**
 * Admin Reports — Deliveries
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::deliveries()
 *
 * Expected variables:
 *   $from       string   Y-m-d
 *   $to         string   Y-m-d
 *   $summary    array    [
 *     total, delivered, failed, in_transit, scheduled,
 *     success_rate, failure_rate
 *   ]
 *   $perStaff   array    rows: [staff_id, full_name, total, delivered, failed]
 *   $trend      array    rows: [d, total, delivered, failed]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Deliveries Report';

// Safe defaults
$from     = $from     ?? date('Y-m-d', strtotime('-30 days'));
$to       = $to       ?? date('Y-m-d');
$summary  = $summary  ?? [];
$perStaff = $perStaff ?? [];
$trend    = $trend    ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtPct   = fn($n) => number_format((float) $n, 1) . '%';

// Extract summary
$totalDeliveries = (int)   ($summary['total']       ?? 0);
$delivered       = (int)   ($summary['delivered']   ?? 0);
$failed          = (int)   ($summary['failed']      ?? 0);
$inTransit       = (int)   ($summary['in_transit']  ?? 0);
$scheduled       = (int)   ($summary['scheduled']   ?? 0);
$successRate     = (float) ($summary['success_rate'] ?? 0);
$failureRate     = (float) ($summary['failure_rate'] ?? 0);

// Ranking (top performers by delivered count)
$sortedStaff = $perStaff;
usort($sortedStaff, function ($a, $b) {
    $da = (int) ($a['delivered'] ?? 0);
    $db = (int) ($b['delivered'] ?? 0);
    if ($da !== $db) return $db <=> $da;
    return (int) ($b['total'] ?? 0) <=> (int) ($a['total'] ?? 0);
});
$topStaff = array_slice($sortedStaff, 0, 3);

// Prepare trend chart data
$trendLabels    = [];
$trendTotal     = [];
$trendDelivered = [];
$trendFailed    = [];
foreach ($trend as $row) {
    $d = (string) ($row['d'] ?? '');
    $trendLabels[]    = $d ? date('M j', strtotime($d)) : '';
    $trendTotal[]     = (int) ($row['total']     ?? 0);
    $trendDelivered[] = (int) ($row['delivered'] ?? 0);
    $trendFailed[]    = (int) ($row['failed']    ?? 0);
}

// Per-staff chart data (top 10)
$staffLabels    = [];
$staffDelivered = [];
$staffFailed    = [];
foreach (array_slice($sortedStaff, 0, 10) as $s) {
    $staffLabels[]    = $s['full_name'] ?? '';
    $staffDelivered[] = (int) ($s['delivered'] ?? 0);
    $staffFailed[]    = (int) ($s['failed']    ?? 0);
}

// Compute max for staff chart
$staffMax = 1;
foreach ($staffDelivered as $v) { $staffMax = max($staffMax, $v); }
foreach ($staffFailed as $v)    { $staffMax = max($staffMax, $v); }

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
                <i class="bi bi-truck me-2 text-success"></i>Deliveries Report
            </h1>
            <p class="text-muted mb-0 small">
                <strong><?= $e($fmtDate($from)) ?></strong> → <strong><?= $e($fmtDate($to)) ?></strong>
                · <?= $fmtInt($totalDeliveries) ?> total deliver<?= $totalDeliveries === 1 ? 'y' : 'ies' ?>
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/pickups/monitor"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-broadcast me-1"></i>Live Monitor
        </a>
        <a href="/admin/reports/deliveries/json?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-code-slash me-1"></i>JSON
        </a>
        <a href="/admin/reports/export?report=deliveries&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== FILTER BAR ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports/deliveries" class="row g-2 align-items-end">
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
    <!-- Success rate (hero) -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"
             style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-uppercase fw-semibold opacity-75">Success Rate</span>
                    <i class="bi bi-check2-circle fs-4 opacity-75"></i>
                </div>
                <h3 class="mb-2 fw-bold"><?= number_format($successRate, 1) ?>%</h3>
                <div class="small opacity-75">
                    <?= $fmtInt($delivered) ?> / <?= $fmtInt($totalDeliveries) ?> delivered
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
                <h3 class="mb-2 fw-bold"><?= $fmtInt($delivered) ?></h3>
                <div class="text-muted small">
                    successfully completed
                </div>
            </div>
        </div>
    </div>

    <!-- Failed -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Failed</span>
                    <span class="badge bg-danger-subtle text-danger">
                        <i class="bi bi-x-circle"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($failed) ?></h3>
                <div class="text-muted small">
                    <?= $fmtPct($failureRate) ?> failure rate
                </div>
            </div>
        </div>
    </div>

    <!-- Pending / In Transit -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">In Progress</span>
                    <span class="badge bg-warning-subtle text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($inTransit + $scheduled) ?></h3>
                <div class="text-muted small">
                    <?= $fmtInt($inTransit) ?> out · <?= $fmtInt($scheduled) ?> scheduled
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CHART + STATUS BREAKDOWN ==================== -->
<div class="row g-3 mb-4">

    <!-- Trend chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-graph-up me-2 text-success"></i>Daily Delivery Trend
                </h6>
                <span class="text-muted small"><?= $fmtInt(count($trend)) ?> day(s) of data</span>
            </div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No deliveries in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="deliveryTrendChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status distribution -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bar-chart-steps me-2 text-primary"></i>Status Overview
                </h6>
            </div>
            <div class="card-body pt-0">
                <?php
                $statusBars = [
                    'Delivered'      => ['count' => $delivered, 'color' => 'success'],
                    'Out for delivery'=> ['count' => $inTransit, 'color' => 'info'],
                    'Scheduled'      => ['count' => $scheduled, 'color' => 'warning'],
                    'Failed'         => ['count' => $failed,    'color' => 'danger'],
                ];
                foreach ($statusBars as $label => $info):
                    $count = (int) $info['count'];
                    $pct   = $totalDeliveries > 0 ? ($count / $totalDeliveries) * 100 : 0;
                ?>
                    <div class="d-flex align-items-center gap-2 mb-3 small">
                        <span class="text-muted" style="min-width: 110px;">
                            <?= $e($label) ?>
                        </span>
                        <div class="progress flex-grow-1" style="height: 8px;">
                            <div class="progress-bar bg-<?= $e($info['color']) ?>"
                                 style="width: <?= $pct ?>%"></div>
                        </div>
                        <span class="fw-medium text-end" style="min-width: 40px;">
                            <?= $fmtInt($count) ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <?php if ($totalDeliveries === 0): ?>
                    <div class="text-center text-muted py-3 small">
                        No deliveries in this range.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TOP PERFORMERS PODIUM ==================== -->
<?php if (!empty($topStaff)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-trophy me-2 text-warning"></i>Top Delivery Staff
            </h6>
            <span class="text-muted small">By completed deliveries</span>
        </div>
        <div class="card-body">
            <div class="row g-3 justify-content-center">
                <?php
                // 2nd / 1st / 3rd
                $podiumOrder = [
                    1 => $topStaff[1] ?? null,
                    0 => $topStaff[0] ?? null,
                    2 => $topStaff[2] ?? null,
                ];
                foreach ($podiumOrder as $idx => $s):
                    if (!$s) continue;
                    $place = $idx + 1;
                    $medal = match ($place) {
                        1 => ['#fbbf24', 'bi-trophy-fill'],
                        2 => ['#94a3b8', 'bi-award-fill'],
                        3 => ['#d97706', 'bi-award-fill'],
                        default => ['#e2e8f0', 'bi-award'],
                    };
                    $staffId    = (int) ($s['staff_id'] ?? 0);
                    $staffName  = (string) ($s['full_name'] ?? '');
                    $total      = (int) ($s['total'] ?? 0);
                    $deliv      = (int) ($s['delivered'] ?? 0);
                    $failedS    = (int) ($s['failed'] ?? 0);
                    $successPct = $total > 0 ? ($deliv / $total) * 100 : 0;
                    $isFirst    = ($place === 1);
                ?>
                    <div class="col-md-4">
                        <div class="podium-card <?= $isFirst ? 'podium-first' : '' ?>">
                            <div class="podium-medal" style="background: <?= $medal[0] ?>;">
                                <i class="bi <?= $medal[1] ?>"></i>
                            </div>
                            <div class="podium-rank">#<?= $place ?></div>

                            <div class="avatar-circle avatar-lg mx-auto mb-2 bg-primary-subtle text-primary">
                                <?= $e(strtoupper(substr($staffName, 0, 1))) ?>
                            </div>

                            <h6 class="fw-semibold mb-1">
                                <a href="/admin/staff/<?= $staffId ?>"
                                   class="text-decoration-none">
                                    <?= $e($staffName) ?>
                                </a>
                            </h6>

                            <div class="mb-3">
                                <span class="badge bg-success-subtle text-success">
                                    <?= number_format($successPct, 1) ?>% success
                                </span>
                            </div>

                            <div class="row g-2 text-center small">
                                <div class="col-4">
                                    <div class="fw-bold"><?= $fmtInt($deliv) ?></div>
                                    <div class="text-muted" style="font-size:.68rem;">Delivered</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold text-danger"><?= $fmtInt($failedS) ?></div>
                                    <div class="text-muted" style="font-size:.68rem;">Failed</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?= $fmtInt($total) ?></div>
                                    <div class="text-muted" style="font-size:.68rem;">Total</div>
                                </div>
                            </div>

                            <a href="/admin/staff/<?= $staffId ?>/performance"
                               class="btn btn-sm btn-outline-primary w-100 mt-3">
                                View Performance <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ==================== PER-STAFF CHART ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-people me-2 text-primary"></i>Per-Staff Deliveries
        </h6>
        <span class="text-muted small">Top <?= count($staffLabels) ?> staff</span>
    </div>
    <div class="card-body">
        <?php if (empty($staffLabels)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                <div class="small">No staff delivery data in this range.</div>
            </div>
        <?php else: ?>
            <canvas id="staffChart" height="100"></canvas>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== FULL PER-STAFF TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-table me-2 text-primary"></i>Delivery Performance by Staff
        </h6>
        <span class="text-muted small"><?= count($sortedStaff) ?> staff member(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold" style="width: 50px;">#</th>
                    <th class="small text-muted fw-semibold">Staff</th>
                    <th class="small text-muted fw-semibold text-center">Total</th>
                    <th class="small text-muted fw-semibold text-center">Delivered</th>
                    <th class="small text-muted fw-semibold text-center">Failed</th>
                    <th class="small text-muted fw-semibold text-end">Success Rate</th>
                    <th class="small text-muted fw-semibold" style="width: 20%;">Performance</th>
                    <th class="small text-muted fw-semibold text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sortedStaff)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No delivery data available</div>
                                <div class="small">Adjust the date range or check that deliveries are assigned.</div>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $rank = 0;
                    foreach ($sortedStaff as $s):
                        $rank++;
                        $staffId   = (int) ($s['staff_id'] ?? 0);
                        $staffName = (string) ($s['full_name'] ?? '');
                        $total     = (int) ($s['total'] ?? 0);
                        $deliv     = (int) ($s['delivered'] ?? 0);
                        $failedS   = (int) ($s['failed'] ?? 0);
                        $successPct = $total > 0 ? ($deliv / $total) * 100 : 0;
                        $rankBadge = match ($rank) {
                            1 => 'bg-warning text-dark',
                            2 => 'bg-secondary text-white',
                            3 => 'bg-danger text-white',
                            default => 'bg-light text-dark border',
                        };
                        $successColor = match (true) {
                            $successPct >= 90 => 'success',
                            $successPct >= 70 => 'info',
                            $successPct >= 50 => 'warning',
                            default           => 'danger',
                        };
                ?>
                    <tr>
                        <td>
                            <span class="badge <?= $rankBadge ?>"><?= $rank ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle-sm bg-primary-subtle text-primary">
                                    <?= $e(strtoupper(substr($staffName, 0, 1))) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-medium small text-truncate">
                                        <a href="/admin/staff/<?= $staffId ?>"
                                           class="text-decoration-none">
                                            <?= $e($staffName) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        #<?= $staffId ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center small fw-medium"><?= $fmtInt($total) ?></td>
                        <td class="text-center small">
                            <span class="badge bg-success-subtle text-success">
                                <?= $fmtInt($deliv) ?>
                            </span>
                        </td>
                        <td class="text-center small">
                            <?php if ($failedS > 0): ?>
                                <span class="badge bg-danger-subtle text-danger">
                                    <?= $fmtInt($failedS) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="badge bg-<?= $successColor ?>-subtle text-<?= $successColor ?>">
                                <?= number_format($successPct, 1) ?>%
                            </span>
                        </td>
                        <td>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-<?= $successColor ?>"
                                     style="width: <?= $successPct ?>%"></div>
                            </div>
                        </td>
                        <td class="text-end">
                            <a href="/admin/staff/<?= $staffId ?>/performance"
                               class="btn btn-sm btn-outline-secondary"
                               title="View performance">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .avatar-circle {
        width: 46px; height: 46px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 1rem;
        flex-shrink: 0;
    }
    .avatar-circle-sm {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .8rem;
        flex-shrink: 0;
    }
    .avatar-lg { width: 56px; height: 56px; font-size: 1.25rem; }
    .min-w-0 { min-width: 0; }

    /* Podium (same as staff_performance) */
    .podium-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem 1rem 1rem;
        text-align: center;
        position: relative;
        background: #fff;
        transition: transform .15s, box-shadow .15s;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .podium-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(15,23,42,.08);
    }
    .podium-first {
        border-color: #fbbf24;
        border-width: 2px;
        box-shadow: 0 0 0 4px rgba(251,191,36,.08);
    }
    .podium-medal {
        position: absolute;
        top: -16px;
        left: 50%;
        transform: translateX(-50%);
        width: 36px; height: 36px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        font-size: 1rem;
        border: 3px solid #fff;
        box-shadow: 0 2px 6px rgba(15,23,42,.15);
    }
    .podium-rank {
        position: absolute;
        top: .6rem;
        right: .75rem;
        font-size: .75rem;
        font-weight: 600;
        color: #94a3b8;
        letter-spacing: .05em;
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
    const trendLabels    = <?= json_encode($trendLabels, $jsonFlags) ?>;
    const trendTotal     = <?= json_encode($trendTotal, $jsonFlags) ?>;
    const trendDelivered = <?= json_encode($trendDelivered, $jsonFlags) ?>;
    const trendFailed    = <?= json_encode($trendFailed, $jsonFlags) ?>;

    const staffLabels    = <?= json_encode($staffLabels, $jsonFlags) ?>;
    const staffDelivered = <?= json_encode($staffDelivered, $jsonFlags) ?>;
    const staffFailed    = <?= json_encode($staffFailed, $jsonFlags) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // ---------- Daily trend chart ----------
    const trendCanvas = document.getElementById('deliveryTrendChart');
    if (trendCanvas && trendLabels.length) {
        const ctx = trendCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(34,197,94,.25)');
        gradient.addColorStop(1, 'rgba(34,197,94,.02)');

        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Total',
                        data: trendTotal,
                        borderColor: '#94a3b8',
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        borderWidth: 2,
                        borderDash: [3, 3],
                        pointRadius: 2,
                        pointHoverRadius: 5,
                    },
                    {
                        label: 'Delivered',
                        data: trendDelivered,
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
                    },
                    {
                        label: 'Failed',
                        data: trendFailed,
                        borderColor: '#ef4444',
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        borderWidth: 2,
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
                        title: { display: true, text: 'Deliveries', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 20 }
                    }
                }
            }
        });
    }

    // ---------- Per-staff grouped bar chart ----------
    const staffCanvas = document.getElementById('staffChart');
    if (staffCanvas && staffLabels.length) {
        new Chart(staffCanvas, {
            type: 'bar',
            data: {
                labels: staffLabels,
                datasets: [
                    {
                        label: 'Delivered',
                        data: staffDelivered,
                        backgroundColor: '#22c55e',
                        borderRadius: 4,
                        maxBarThickness: 22,
                    },
                    {
                        label: 'Failed',
                        data: staffFailed,
                        backgroundColor: '#ef4444',
                        borderRadius: 4,
                        maxBarThickness: 22,
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
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 45, minRotation: 0 }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        title: { display: true, text: 'Count', font: { size: 10 } }
                    }
                }
            }
        });
    }
})();
</script>
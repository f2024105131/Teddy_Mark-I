<?php
/**
 * Admin Reports — Complaints
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::complaints()
 *
 * Expected variables:
 *   $from             string   Y-m-d
 *   $to               string   Y-m-d
 *   $summary          array    [
 *     total, active, resolved, rejected, escalated, closed, resolution_rate
 *   ]
 *   $byType           array    rows: [type, total]
 *   $byCategory       array    rows: [category_id, category_name, total]
 *   $resolutionTimes  array    [avg_hours, min_hours, max_hours, resolved_count]
 *   $trend            array    rows: [d, total, completed]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Complaints Report';

// Safe defaults
$from            = $from            ?? date('Y-m-d', strtotime('-30 days'));
$to              = $to              ?? date('Y-m-d');
$summary         = $summary         ?? [];
$byType          = $byType          ?? [];
$byCategory      = $byCategory      ?? [];
$resolutionTimes = $resolutionTimes ?? [];
$trend           = $trend           ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Extract summary
$totalComplaints  = (int)   ($summary['total']            ?? 0);
$activeCount      = (int)   ($summary['active']           ?? 0);
$resolvedCount    = (int)   ($summary['resolved']         ?? 0);
$rejectedCount    = (int)   ($summary['rejected']         ?? 0);
$escalatedCount   = (int)   ($summary['escalated']        ?? 0);
$closedCount      = (int)   ($summary['closed']           ?? 0);
$resolutionRate   = (float) ($summary['resolution_rate']  ?? 0);

// Resolution times
$avgHours       = (float) ($resolutionTimes['avg_hours']      ?? 0);
$minHours       = (int)   ($resolutionTimes['min_hours']      ?? 0);
$maxHours       = (int)   ($resolutionTimes['max_hours']      ?? 0);
$resolvedInRange = (int)  ($resolutionTimes['resolved_count'] ?? 0);

// Type metadata
$typeMeta = [
    'missing_item'          => ['label' => 'Missing Item',          'color' => 'danger',    'icon' => 'bi-box'],
    'damaged_item'          => ['label' => 'Damaged Item',          'color' => 'warning',   'icon' => 'bi-tools'],
    'late_delivery'         => ['label' => 'Late Delivery',         'color' => 'info',      'icon' => 'bi-clock-history'],
    'wrong_billing'         => ['label' => 'Wrong Billing',         'color' => 'primary',   'icon' => 'bi-receipt'],
    'poor_cleaning_quality' => ['label' => 'Poor Cleaning Quality', 'color' => 'secondary', 'icon' => 'bi-droplet'],
];

// Type colors (hex for chart)
$typeHex = [
    'danger'    => '#ef4444',
    'warning'   => '#f59e0b',
    'info'      => '#06b6d4',
    'primary'   => '#0ea5e9',
    'secondary' => '#64748b',
];

// Format hours → human-readable
$formatDuration = function (float $hours): string {
    if ($hours < 1) {
        $mins = round($hours * 60);
        return $mins <= 0 ? '—' : $mins . ' min';
    }
    if ($hours < 24) {
        return number_format($hours, 1) . ' hr';
    }
    $days = $hours / 24;
    return number_format($days, 1) . ' day' . ($days == 1 ? '' : 's');
};

// Prepare type chart data
$typeLabels = [];
$typeValues = [];
$typeHexes  = [];
$typeSum    = 0;
foreach ($byType as $row) {
    $t = (string) ($row['type'] ?? '');
    $v = (int) ($row['total'] ?? 0);
    $meta = $typeMeta[$t] ?? ['label' => ucwords(str_replace('_', ' ', $t)), 'color' => 'secondary'];
    $typeLabels[] = $meta['label'];
    $typeValues[] = $v;
    $typeHexes[]  = $typeHex[$meta['color']] ?? '#64748b';
    $typeSum     += $v;
}

// Prepare category chart data (top 8)
$catRowsTop = array_slice($byCategory, 0, 8);
$catLabels  = [];
$catValues  = [];
foreach ($catRowsTop as $row) {
    $catLabels[] = (string) ($row['category_name'] ?? 'Uncategorized');
    $catValues[] = (int) ($row['total'] ?? 0);
}
$catMax = 1;
foreach ($catValues as $v) {
    $catMax = max($catMax, $v);
}

// Trend chart data
$trendLabels    = [];
$trendTotal     = [];
$trendCompleted = [];
foreach ($trend as $row) {
    $d = (string) ($row['d'] ?? '');
    $trendLabels[]    = $d ? date('M j', strtotime($d)) : '';
    $trendTotal[]     = (int) ($row['total']     ?? 0);
    $trendCompleted[] = (int) ($row['completed'] ?? 0);
}

// Status distribution (for a bar showing overall pipeline)
$statusBars = [
    'Active'     => ['count' => $activeCount,   'color' => 'danger'],
    'Resolved'   => ['count' => $resolvedCount, 'color' => 'success'],
    'Rejected'   => ['count' => $rejectedCount, 'color' => 'secondary'],
    'Escalated'  => ['count' => $escalatedCount,'color' => 'dark'],
    'Closed'     => ['count' => $closedCount,   'color' => 'light'],
];

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
                <i class="bi bi-exclamation-circle me-2 text-danger"></i>Complaints Report
            </h1>
            <p class="text-muted mb-0 small">
                <strong><?= $e($fmtDate($from)) ?></strong> → <strong><?= $e($fmtDate($to)) ?></strong>
                · <?= $fmtInt($totalComplaints) ?> complaint(s) filed
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/complaints?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i>Full List
        </a>
        <a href="/admin/reports/complaints/json?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-code-slash me-1"></i>JSON
        </a>
        <a href="/admin/reports/export?report=complaints&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== FILTER BAR ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports/complaints" class="row g-2 align-items-end">
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
    <!-- Total complaints -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Total Filed</span>
                    <span class="badge bg-primary-subtle text-primary">
                        <i class="bi bi-exclamation-circle"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($totalComplaints) ?></h3>
                <div class="text-muted small">
                    in selected range
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
                    <span class="badge bg-danger-subtle text-danger">
                        <i class="bi bi-hourglass-split"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($activeCount) ?></h3>
                <div class="text-muted small">
                    need attention
                </div>
            </div>
        </div>
    </div>

    <!-- Escalated -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Escalated</span>
                    <span class="badge bg-dark-subtle text-dark">
                        <i class="bi bi-shield-exclamation"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($escalatedCount) ?></h3>
                <div class="text-muted small">
                    require admin action
                </div>
            </div>
        </div>
    </div>

    <!-- Resolution rate (hero) -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"
             style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-uppercase fw-semibold opacity-75">Resolution Rate</span>
                    <i class="bi bi-check2-circle fs-4 opacity-75"></i>
                </div>
                <h3 class="mb-2 fw-bold"><?= number_format($resolutionRate, 1) ?>%</h3>
                <div class="small opacity-75">
                    <?= $fmtInt($resolvedCount) ?> / <?= $fmtInt($totalComplaints) ?> resolved
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RESOLUTION TIME + STATUS ==================== -->
<div class="row g-3 mb-4">

    <!-- Resolution time metrics -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Resolution Times
                </h6>
            </div>
            <div class="card-body">
                <?php if ($resolvedInRange === 0): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-hourglass fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No complaints resolved in this range yet.</div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="resolution-block">
                                <div class="text-muted small text-uppercase fw-semibold mb-1">Average</div>
                                <div class="fs-3 fw-bold text-primary">
                                    <?= $e($formatDuration($avgHours)) ?>
                                </div>
                                <div class="text-muted small mt-1">
                                    across <?= $fmtInt($resolvedInRange) ?> resolved
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="resolution-block">
                                <div class="text-muted small text-uppercase fw-semibold mb-1">Fastest</div>
                                <div class="fs-3 fw-bold text-success">
                                    <?= $e($formatDuration((float) $minHours)) ?>
                                </div>
                                <div class="text-muted small mt-1">
                                    best resolution time
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="resolution-block">
                                <div class="text-muted small text-uppercase fw-semibold mb-1">Slowest</div>
                                <div class="fs-3 fw-bold text-danger">
                                    <?= $e($formatDuration((float) $maxHours)) ?>
                                </div>
                                <div class="text-muted small mt-1">
                                    needs investigation
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status distribution -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bar-chart-steps me-2 text-primary"></i>Status Distribution
                </h6>
            </div>
            <div class="card-body pt-0">
                <?php foreach ($statusBars as $label => $info):
                    $count = (int) $info['count'];
                    $pct   = $totalComplaints > 0 ? ($count / $totalComplaints) * 100 : 0;
                ?>
                    <div class="d-flex align-items-center gap-2 mb-3 small">
                        <span class="text-muted" style="min-width: 80px;">
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

                <?php if ($totalComplaints === 0): ?>
                    <div class="text-center text-muted py-3 small">
                        No complaints filed in this range.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CHARTS ROW ==================== -->
<div class="row g-3 mb-4">

    <!-- Daily trend -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-graph-up me-2 text-primary"></i>Daily Complaint Trend
                </h6>
                <span class="text-muted small"><?= $fmtInt(count($trend)) ?> day(s) of data</span>
            </div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No complaints filed in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="complaintTrendChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Type breakdown doughnut -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-pie-chart me-2 text-primary"></i>By Type
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($typeLabels)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No type data.</div>
                    </div>
                <?php else: ?>
                    <canvas id="typeChart" height="180"></canvas>
                    <div class="mt-3 pt-3 border-top">
                        <?php foreach ($byType as $row):
                            $t = (string) ($row['type'] ?? '');
                            $v = (int) ($row['total'] ?? 0);
                            $meta = $typeMeta[$t] ?? ['label' => ucwords(str_replace('_', ' ', $t)), 'color' => 'secondary'];
                            $pct  = $typeSum > 0 ? ($v / $typeSum) * 100 : 0;
                        ?>
                            <div class="d-flex align-items-center gap-2 mb-2 small">
                                <span class="status-dot bg-<?= $e($meta['color']) ?>"></span>
                                <span class="flex-grow-1">
                                    <?= $e($meta['label']) ?>
                                </span>
                                <span class="text-muted">
                                    <?= number_format($pct, 1) ?>%
                                </span>
                                <span class="fw-medium text-end" style="min-width: 40px;">
                                    <?= $fmtInt($v) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CATEGORY BREAKDOWN ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-tags me-2 text-primary"></i>By Category
        </h6>
        <span class="text-muted small"><?= count($byCategory) ?> categor<?= count($byCategory) === 1 ? 'y' : 'ies' ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($byCategory)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-tags fs-1 d-block mb-2 opacity-50"></i>
                <div class="small">No categorized complaints in this range.</div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Bar chart -->
                <div class="col-lg-6">
                    <canvas id="categoryChart" height="240"></canvas>
                </div>

                <!-- Table/list -->
                <div class="col-lg-6">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="small text-muted fw-semibold">Category</th>
                                    <th class="small text-muted fw-semibold text-center">Complaints</th>
                                    <th class="small text-muted fw-semibold text-end">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($byCategory as $row):
                                    $catName = (string) ($row['category_name'] ?? 'Uncategorized');
                                    $count   = (int) ($row['total'] ?? 0);
                                    $pct     = $totalComplaints > 0 ? ($count / $totalComplaints) * 100 : 0;
                                ?>
                                    <tr>
                                        <td class="small">
                                            <i class="bi bi-tag text-muted me-1"></i>
                                            <?= $e($catName) ?>
                                        </td>
                                        <td class="text-center small">
                                            <span class="badge bg-primary-subtle text-primary">
                                                <?= $fmtInt($count) ?>
                                            </span>
                                        </td>
                                        <td class="text-end small text-muted">
                                            <?= number_format($pct, 1) ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($byCategory) > 8): ?>
                        <div class="text-center text-muted small mt-3">
                            Chart shows top 8 of <?= count($byCategory) ?> categories
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== TYPE BREAKDOWN TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-table me-2 text-primary"></i>Complaints by Type
        </h6>
        <span class="text-muted small"><?= count($byType) ?> type(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Type</th>
                    <th class="small text-muted fw-semibold text-center">Count</th>
                    <th class="small text-muted fw-semibold text-end">% of Total</th>
                    <th class="small text-muted fw-semibold" style="width: 40%;">Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($byType)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            <div class="small">No type data.</div>
                        </td>
                    </tr>
                <?php else: foreach ($byType as $row):
                    $t      = (string) ($row['type'] ?? '');
                    $count  = (int) ($row['total'] ?? 0);
                    $meta   = $typeMeta[$t] ?? ['label' => ucwords(str_replace('_', ' ', $t)), 'color' => 'secondary', 'icon' => 'bi-circle'];
                    $pct    = $typeSum > 0 ? ($count / $typeSum) * 100 : 0;
                ?>
                    <tr>
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
                    <td class="text-center fw-bold"><?= $fmtInt($typeSum) ?></td>
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
    .resolution-block {
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        text-align: center;
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
    const trendCompleted = <?= json_encode($trendCompleted, $jsonFlags) ?>;

    const typeLabels = <?= json_encode($typeLabels, $jsonFlags) ?>;
    const typeValues = <?= json_encode($typeValues, $jsonFlags) ?>;
    const typeHexes  = <?= json_encode($typeHexes, $jsonFlags) ?>;

    const catLabels = <?= json_encode($catLabels, $jsonFlags) ?>;
    const catValues = <?= json_encode($catValues, $jsonFlags) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // ---------- Daily trend chart ----------
    const trendCanvas = document.getElementById('complaintTrendChart');
    if (trendCanvas && trendLabels.length) {
        const ctx = trendCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(239,68,68,.22)');
        gradient.addColorStop(1, 'rgba(239,68,68,.02)');

        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Filed',
                        data: trendTotal,
                        borderColor: '#ef4444',
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#ef4444',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                    },
                    {
                        label: 'Completed',
                        data: trendCompleted,
                        borderColor: '#22c55e',
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#22c55e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
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
                        title: { display: true, text: 'Complaints', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 20 }
                    }
                }
            }
        });
    }

    // ---------- Type doughnut ----------
    const typeCanvas = document.getElementById('typeChart');
    if (typeCanvas && typeLabels.length) {
        new Chart(typeCanvas, {
            type: 'doughnut',
            data: {
                labels: typeLabels,
                datasets: [{
                    data: typeValues,
                    backgroundColor: typeHexes,
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
                            label: (ctx) => ' ' + ctx.label + ': ' + Number(ctx.raw).toLocaleString()
                        }
                    }
                }
            }
        });
    }

    // ---------- Category bar chart ----------
    const catCanvas = document.getElementById('categoryChart');
    if (catCanvas && catLabels.length) {
        new Chart(catCanvas, {
            type: 'bar',
            data: {
                labels: catLabels,
                datasets: [{
                    label: 'Complaints',
                    data: catValues,
                    backgroundColor: '#0ea5e9',
                    borderRadius: 4,
                    maxBarThickness: 26,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ' + Number(ctx.raw).toLocaleString() + ' complaint(s)'
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        title: { display: true, text: 'Complaints', font: { size: 10 } }
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
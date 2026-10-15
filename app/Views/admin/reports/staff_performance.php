<?php
/**
 * Admin Reports — Staff Performance
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::staffPerformance()
 *
 * Expected variables:
 *   $from       string   Y-m-d
 *   $to         string   Y-m-d
 *   $summary    array    [
 *     pickups, deliveries, orders, complaints_resolved,
 *     avg_rating, avg_score, active_staff
 *   ]
 *   $perStaff   array    rows: [staff_id, full_name, email, role, is_active,
 *                                pickups, deliveries, orders, complaints_resolved,
 *                                avg_rating, avg_score, days_recorded]
 *   $trend      array    rows: [d, pickups, deliveries, orders, avg_score]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Staff Performance Report';

// Safe defaults
$from     = $from     ?? date('Y-m-d', strtotime('-30 days'));
$to       = $to       ?? date('Y-m-d');
$summary  = $summary  ?? [];
$perStaff = $perStaff ?? [];
$trend    = $trend    ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Extract summary
$sumPickups      = (int)   ($summary['pickups']             ?? 0);
$sumDeliveries   = (int)   ($summary['deliveries']          ?? 0);
$sumOrders       = (int)   ($summary['orders']              ?? 0);
$sumComplaints   = (int)   ($summary['complaints_resolved'] ?? 0);
$avgRating       = (float) ($summary['avg_rating']          ?? 0);
$avgScore        = (float) ($summary['avg_score']           ?? 0);
$activeStaff     = (int)   ($summary['active_staff']        ?? 0);

// Score helpers
$scoreBadge = function (float $score): string {
    return match (true) {
        $score >= 85 => 'success',
        $score >= 70 => 'info',
        $score >= 50 => 'warning',
        default      => 'danger',
    };
};
$scoreLabel = function (float $score): string {
    return match (true) {
        $score >= 85 => 'Excellent',
        $score >= 70 => 'Good',
        $score >= 50 => 'Average',
        default      => 'Needs Improvement',
    };
};

// Ranking — sort by avg_score desc, deliveries desc, pickups desc
$sorted = $perStaff;
usort($sorted, function ($a, $b) {
    $sa = (float) ($a['avg_score']  ?? 0);
    $sb = (float) ($b['avg_score']  ?? 0);
    if ($sa !== $sb) return $sb <=> $sa;

    $da = (int) ($a['deliveries'] ?? 0);
    $db = (int) ($b['deliveries'] ?? 0);
    if ($da !== $db) return $db <=> $da;

    return (int) ($b['pickups'] ?? 0) <=> (int) ($a['pickups'] ?? 0);
});

// Top 3 podium
$topPerformers = array_slice($sorted, 0, 3);

// Team totals for percentages
$teamTotalActions = max(1, $sumPickups + $sumDeliveries + $sumOrders + $sumComplaints);

// Prepare trend chart data
$trendLabels     = [];
$trendPickups    = [];
$trendDeliveries = [];
$trendOrders     = [];
$trendScore      = [];
foreach ($trend as $row) {
    $d = (string) ($row['d'] ?? '');
    $trendLabels[]     = $d ? date('M j', strtotime($d)) : '';
    $trendPickups[]    = (int)   ($row['pickups']    ?? 0);
    $trendDeliveries[] = (int)   ($row['deliveries'] ?? 0);
    $trendOrders[]     = (int)   ($row['orders']     ?? 0);
    $trendScore[]      = (float) ($row['avg_score']  ?? 0);
}

// Score comparison bar data (all staff)
$compLabels = [];
$compScores = [];
$compColors = [];
foreach ($sorted as $s) {
    $compLabels[] = $s['full_name'] ?? '';
    $score = (float) ($s['avg_score'] ?? 0);
    $compScores[] = $score;
    $compColors[] = match (true) {
        $score >= 85 => '#22c55e',
        $score >= 70 => '#0ea5e9',
        $score >= 50 => '#f59e0b',
        default      => '#ef4444',
    };
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
                <i class="bi bi-person-badge me-2 text-warning"></i>Staff Performance
            </h1>
            <p class="text-muted mb-0 small">
                <strong><?= $e($fmtDate($from)) ?></strong> → <strong><?= $e($fmtDate($to)) ?></strong>
                · <?= $fmtInt($activeStaff) ?> active staff
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/staff/performance-overview?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i>Overview
        </a>
        <a href="/admin/reports/staff-performance/json?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-code-slash me-1"></i>JSON
        </a>
        <a href="/admin/reports/export?report=staff-performance&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== FILTER BAR ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports/staff-performance" class="row g-2 align-items-end">
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

<!-- ==================== TEAM KPI CARDS ==================== -->
<div class="row g-3 mb-4">
    <!-- Pickups -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pickups</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($sumPickups) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= number_format(($sumPickups / $teamTotalActions) * 100, 1) ?>% of actions
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Deliveries -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Deliveries</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($sumDeliveries) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= number_format(($sumDeliveries / $teamTotalActions) * 100, 1) ?>% of actions
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Orders -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info-subtle text-info">
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Orders Handled</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($sumOrders) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= number_format(($sumOrders / $teamTotalActions) * 100, 1) ?>% of actions
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Complaints -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-chat-square-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Complaints</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($sumComplaints) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= number_format(($sumComplaints / $teamTotalActions) * 100, 1) ?>% of actions
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== SCORE + RATING SUMMARY ==================== -->
<div class="row g-3 mb-4">
    <!-- Team average score (hero) -->
    <div class="col-md-6 col-lg-6">
        <div class="card border-0 shadow-sm h-100"
             style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-uppercase fw-semibold opacity-75">Team Avg Score</span>
                    <i class="bi bi-speedometer2 fs-4 opacity-75"></i>
                </div>
                <div class="d-flex align-items-baseline gap-3">
                    <h2 class="mb-0 fw-bold"><?= number_format($avgScore, 1) ?></h2>
                    <span class="badge bg-white text-dark"><?= $e($scoreLabel($avgScore)) ?></span>
                </div>
                <div class="small opacity-75 mt-2">
                    Across <?= $fmtInt($activeStaff) ?> staff member(s) in range
                </div>
            </div>
        </div>
    </div>

    <!-- Team avg rating -->
    <div class="col-md-6 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Team Avg Rating</span>
                    <span class="badge bg-warning-subtle text-warning">
                        <i class="bi bi-star-fill"></i>
                    </span>
                </div>
                <div class="d-flex align-items-baseline gap-3">
                    <h2 class="mb-0 fw-bold">
                        <?= number_format($avgRating, 2) ?>
                        <span class="text-muted fs-6 fw-normal">/ 5</span>
                    </h2>
                    <span class="text-warning fs-5">
                        <?php
                        $fullStars = (int) round($avgRating);
                        echo str_repeat('★', $fullStars) . str_repeat('☆', 5 - $fullStars);
                        ?>
                    </span>
                </div>
                <div class="text-muted small mt-2">
                    Customer satisfaction across all rated orders
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TOP PERFORMERS PODIUM ==================== -->
<?php if (!empty($topPerformers)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-trophy me-2 text-warning"></i>Top Performers
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3 justify-content-center">
                <?php
                // Display 2nd, 1st, 3rd for classic podium look
                $podiumOrder = [
                    1 => $topPerformers[1] ?? null,
                    0 => $topPerformers[0] ?? null,
                    2 => $topPerformers[2] ?? null,
                ];
                foreach ($podiumOrder as $idx => $p):
                    if (!$p) continue;
                    $place  = $idx + 1;
                    $medal  = match ($place) {
                        1 => ['#fbbf24', 'bi-trophy-fill'],
                        2 => ['#94a3b8', 'bi-award-fill'],
                        3 => ['#d97706', 'bi-award-fill'],
                        default => ['#e2e8f0', 'bi-award'],
                    };
                    $sid     = (int) ($p['staff_id'] ?? 0);
                    $sName   = (string) ($p['full_name'] ?? '');
                    $sRole   = (string) ($p['role'] ?? 'staff');
                    $sScore  = (float) ($p['avg_score'] ?? 0);
                    $sRating = (float) ($p['avg_rating'] ?? 0);
                    $isFirst = ($place === 1);
                ?>
                    <div class="col-md-4">
                        <div class="podium-card <?= $isFirst ? 'podium-first' : '' ?>">
                            <div class="podium-medal" style="background: <?= $medal[0] ?>;">
                                <i class="bi <?= $medal[1] ?>"></i>
                            </div>
                            <div class="podium-rank">#<?= $place ?></div>

                            <div class="avatar-circle avatar-lg mx-auto mb-2 bg-primary-subtle text-primary">
                                <?= $e(strtoupper(substr($sName, 0, 1))) ?>
                            </div>

                            <h6 class="fw-semibold mb-1">
                                <a href="/admin/staff/<?= $sid ?>/performance"
                                   class="text-decoration-none">
                                    <?= $e($sName) ?>
                                </a>
                            </h6>
                            <div class="small text-muted mb-2">
                                <span class="badge bg-<?= $sRole === 'admin' ? 'danger' : 'primary' ?>">
                                    <?= $e(ucfirst($sRole)) ?>
                                </span>
                            </div>

                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <span class="badge bg-<?= $scoreBadge($sScore) ?>">
                                    Score <?= number_format($sScore, 1) ?>
                                </span>
                                <span class="badge bg-warning-subtle text-warning">
                                    <?= number_format($sRating, 1) ?> ★
                                </span>
                            </div>

                            <div class="row g-2 text-center small">
                                <div class="col-4">
                                    <div class="fw-bold"><?= $fmtInt($p['pickups'] ?? 0) ?></div>
                                    <div class="text-muted" style="font-size:.68rem;">Pickups</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?= $fmtInt($p['deliveries'] ?? 0) ?></div>
                                    <div class="text-muted" style="font-size:.68rem;">Deliveries</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?= $fmtInt($p['orders'] ?? 0) ?></div>
                                    <div class="text-muted" style="font-size:.68rem;">Orders</div>
                                </div>
                            </div>

                            <a href="/admin/staff/<?= $sid ?>/performance"
                               class="btn btn-sm btn-outline-primary w-100 mt-3">
                                View Details <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ==================== CHARTS ==================== -->
<div class="row g-3 mb-4">
    <!-- Daily trend -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-graph-up me-2 text-primary"></i>Team Activity Trend
                </h6>
                <span class="text-muted small"><?= $fmtInt(count($trend)) ?> day(s) of data</span>
            </div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No performance data in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="teamTrendChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Score comparison -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bar-chart me-2 text-primary"></i>Score Comparison
                </h6>
                <span class="text-muted small"><?= count($compLabels) ?> staff</span>
            </div>
            <div class="card-body">
                <?php if (empty($compLabels)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No staff data.</div>
                    </div>
                <?php else: ?>
                    <canvas id="scoreCompChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== PER-STAFF TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-table me-2 text-primary"></i>Full Staff Comparison
        </h6>
        <span class="text-muted small"><?= count($perStaff) ?> staff member(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold" style="width: 50px;">#</th>
                    <th class="small text-muted fw-semibold">Staff</th>
                    <th class="small text-muted fw-semibold text-center">Role</th>
                    <th class="small text-muted fw-semibold text-center">Pickups</th>
                    <th class="small text-muted fw-semibold text-center">Deliveries</th>
                    <th class="small text-muted fw-semibold text-center">Orders</th>
                    <th class="small text-muted fw-semibold text-center">Resolved</th>
                    <th class="small text-muted fw-semibold text-center">Rating</th>
                    <th class="small text-muted fw-semibold text-center">Avg Score</th>
                    <th class="small text-muted fw-semibold text-center">Days</th>
                    <th class="small text-muted fw-semibold text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sorted)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No staff data available</div>
                                <div class="small">
                                    Adjust the date range or check that staff members are active.
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $rank = 0;
                    foreach ($sorted as $s):
                        $rank++;
                        $sid       = (int) ($s['staff_id'] ?? 0);
                        $sName     = (string) ($s['full_name'] ?? '');
                        $sEmail    = (string) ($s['email'] ?? '');
                        $sRole     = (string) ($s['role'] ?? 'staff');
                        $sActive   = (int) ($s['is_active'] ?? 0);
                        $sPickups  = (int) ($s['pickups'] ?? 0);
                        $sDeliv    = (int) ($s['deliveries'] ?? 0);
                        $sOrders   = (int) ($s['orders'] ?? 0);
                        $sCompl    = (int) ($s['complaints_resolved'] ?? 0);
                        $sRating   = (float) ($s['avg_rating'] ?? 0);
                        $sScore    = (float) ($s['avg_score'] ?? 0);
                        $sDays     = (int) ($s['days_recorded'] ?? 0);
                        $rankBadge = match ($rank) {
                            1 => 'bg-warning text-dark',
                            2 => 'bg-secondary text-white',
                            3 => 'bg-danger text-white',
                            default => 'bg-light text-dark border',
                        };
                ?>
                    <tr class="<?= $sActive ? '' : 'text-muted opacity-75' ?>">
                        <td>
                            <span class="badge <?= $rankBadge ?>"><?= $rank ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle-sm bg-<?= $sActive ? 'success' : 'secondary' ?>-subtle text-<?= $sActive ? 'success' : 'secondary' ?>">
                                    <?= $e(strtoupper(substr($sName, 0, 1))) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-medium small text-truncate">
                                        <a href="/admin/staff/<?= $sid ?>"
                                           class="text-decoration-none">
                                            <?= $e($sName) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        <?= $e($sEmail) ?>
                                        <?php if (!$sActive): ?>
                                            · <span class="badge bg-secondary" style="font-size:.65rem;">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $sRole === 'admin' ? 'danger' : 'primary' ?>" style="font-size:.7rem;">
                                <?= $e(ucfirst($sRole)) ?>
                            </span>
                        </td>
                        <td class="text-center small"><?= $fmtInt($sPickups) ?></td>
                        <td class="text-center small"><?= $fmtInt($sDeliv) ?></td>
                        <td class="text-center small"><?= $fmtInt($sOrders) ?></td>
                        <td class="text-center small"><?= $fmtInt($sCompl) ?></td>
                        <td class="text-center small">
                            <?php if ($sRating > 0): ?>
                                <span class="text-warning">
                                    <?= str_repeat('★', (int) round($sRating)) ?>
                                </span>
                                <span class="text-muted"><?= number_format($sRating, 1) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($sScore > 0): ?>
                                <span class="badge bg-<?= $scoreBadge($sScore) ?>">
                                    <?= number_format($sScore, 1) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center small text-muted"><?= $fmtInt($sDays) ?></td>
                        <td class="text-end">
                            <a href="/admin/staff/<?= $sid ?>/performance"
                               class="btn btn-sm btn-outline-secondary"
                               title="View detailed performance">
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
    .kpi-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
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

    /* Podium */
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
    const trendLabels     = <?= json_encode($trendLabels, $jsonFlags) ?>;
    const trendPickups    = <?= json_encode($trendPickups, $jsonFlags) ?>;
    const trendDeliveries = <?= json_encode($trendDeliveries, $jsonFlags) ?>;
    const trendOrders     = <?= json_encode($trendOrders, $jsonFlags) ?>;
    const trendScore      = <?= json_encode($trendScore, $jsonFlags) ?>;

    const compLabels = <?= json_encode($compLabels, $jsonFlags) ?>;
    const compScores = <?= json_encode($compScores, $jsonFlags) ?>;
    const compColors = <?= json_encode($compColors, $jsonFlags) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // ---------- Team activity trend (grouped bars + score line) ----------
    const teamCanvas = document.getElementById('teamTrendChart');
    if (teamCanvas && trendLabels.length) {
        new Chart(teamCanvas, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Avg Score',
                        data: trendScore,
                        borderColor: '#f59e0b',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [4, 4],
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        yAxisID: 'y1',
                        order: 0,
                    },
                    {
                        label: 'Pickups',
                        data: trendPickups,
                        backgroundColor: '#0ea5e9',
                        borderRadius: 2,
                        maxBarThickness: 10,
                        yAxisID: 'y',
                        order: 1,
                    },
                    {
                        label: 'Deliveries',
                        data: trendDeliveries,
                        backgroundColor: '#22c55e',
                        borderRadius: 2,
                        maxBarThickness: 10,
                        yAxisID: 'y',
                        order: 1,
                    },
                    {
                        label: 'Orders',
                        data: trendOrders,
                        backgroundColor: '#06b6d4',
                        borderRadius: 2,
                        maxBarThickness: 10,
                        yAxisID: 'y',
                        order: 1,
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
                        position: 'left',
                        ticks: { precision: 0 },
                        title: { display: true, text: 'Count', font: { size: 10 } }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        max: 100,
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Score', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 20 }
                    }
                }
            }
        });
    }

    // ---------- Score comparison (horizontal bars) ----------
    const compCanvas = document.getElementById('scoreCompChart');
    if (compCanvas && compLabels.length) {
        new Chart(compCanvas, {
            type: 'bar',
            data: {
                labels: compLabels,
                datasets: [{
                    label: 'Avg Score',
                    data: compScores,
                    backgroundColor: compColors,
                    borderRadius: 4,
                    maxBarThickness: 22,
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
                            label: (ctx) => ' Score: ' + Number(ctx.raw).toFixed(1)
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { stepSize: 25 },
                        title: { display: true, text: 'Avg Score', font: { size: 10 } }
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
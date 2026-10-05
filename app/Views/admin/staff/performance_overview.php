<?php
/**
 * Admin Staff — Performance Overview (All-Staff Comparison)
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\StaffController::performanceOverview()
 *
 * Expected variables:
 *   $staffStats  array   per-staff aggregates over date range
 *                        each row: staff_id, full_name, email, role, is_active,
 *                                  pickups, deliveries, orders, complaints,
 *                                  avg_rating, avg_score, days_recorded
 *   $from        string  Y-m-d start of range
 *   $to          string  Y-m-d end of range
 * ----------------------------------------------------------
 */

$currentPage = 'staff';
$title       = 'Staff Performance Overview';

// Safe defaults
$staffStats = $staffStats ?? [];
$from       = $from       ?? date('Y-m-d', strtotime('-30 days'));
$to         = $to         ?? date('Y-m-d');

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

$roleBadge = fn(string $r): string => $r === 'admin' ? 'danger' : 'primary';
$roleLabel = fn(string $r): string => $r === 'admin' ? 'Admin' : 'Staff';

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

// Compute team totals & rankings from the fetched data
$teamTotalPickups    = 0;
$teamTotalDeliveries = 0;
$teamTotalOrders     = 0;
$teamTotalComplaints = 0;
$teamRatingSum       = 0.0;
$teamRatingCount     = 0;
$teamScoreSum        = 0.0;
$teamScoreCount      = 0;
$activeStaffCount    = 0;

$ranked = []; // rows with a rank assigned later

foreach ($staffStats as $row) {
    $teamTotalPickups    += (int)   ($row['pickups']    ?? 0);
    $teamTotalDeliveries += (int)   ($row['deliveries'] ?? 0);
    $teamTotalOrders     += (int)   ($row['orders']     ?? 0);
    $teamTotalComplaints += (int)   ($row['complaints'] ?? 0);

    $r = (float) ($row['avg_rating'] ?? 0);
    if ($r > 0) {
        $teamRatingSum += $r;
        $teamRatingCount++;
    }

    $s = (float) ($row['avg_score'] ?? 0);
    if ($s > 0) {
        $teamScoreSum += $s;
        $teamScoreCount++;
    }

    if ((int) ($row['is_active'] ?? 0) === 1) {
        $activeStaffCount++;
    }
}

$teamAvgRating = $teamRatingCount > 0 ? round($teamRatingSum / $teamRatingCount, 2) : 0.0;
$teamAvgScore  = $teamScoreCount  > 0 ? round($teamScoreSum  / $teamScoreCount,  2) : 0.0;

// Sort for top performers (by avg_score desc, then deliveries desc)
$sorted = $staffStats;
usort($sorted, function ($a, $b) {
    $sa = (float) ($a['avg_score'] ?? 0);
    $sb = (float) ($b['avg_score'] ?? 0);
    if ($sa !== $sb) return $sb <=> $sa;

    $da = (int) ($a['deliveries'] ?? 0);
    $db = (int) ($b['deliveries'] ?? 0);
    if ($da !== $db) return $db <=> $da;

    return (int) ($b['pickups'] ?? 0) <=> (int) ($a['pickups'] ?? 0);
});

// Top 3 performers
$topPerformers = array_slice($sorted, 0, 3);

// Prepare chart data (only active staff with score > 0, first 15)
$chartStaff = [];
$chartScores = [];
$chartColors = [];
foreach ($sorted as $row) {
    if (count($chartStaff) >= 15) break;
    $score = (float) ($row['avg_score'] ?? 0);
    if ($score <= 0) continue;
    $chartStaff[]  = $row['full_name'];
    $chartScores[] = $score;
    $chartColors[] = match (true) {
        $score >= 85 => '#22c55e',
        $score >= 70 => '#0ea5e9',
        $score >= 50 => '#f59e0b',
        default      => '#ef4444',
    };
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

// Preset range shortcuts
$ranges = [
    ['label' => 'Last 7 days',  'from' => date('Y-m-d', strtotime('-6 days')),  'to' => date('Y-m-d')],
    ['label' => 'Last 30 days', 'from' => date('Y-m-d', strtotime('-29 days')), 'to' => date('Y-m-d')],
    ['label' => 'This month',   'from' => date('Y-m-01'),                        'to' => date('Y-m-d')],
    ['label' => 'Last 90 days', 'from' => date('Y-m-d', strtotime('-89 days')), 'to' => date('Y-m-d')],
];
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/staff" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1">Staff Performance Overview</h1>
            <p class="text-muted mb-0 small">
                Team-wide comparison ·
                <strong><?= $e($fmtDate($from)) ?></strong> →
                <strong><?= $e($fmtDate($to)) ?></strong>
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/staff" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i>Staff List
        </a>
        <a href="/admin/reports/staff-performance" class="btn btn-primary btn-sm">
            <i class="bi bi-bar-chart me-1"></i>Full Report
        </a>
    </div>
</div>

<!-- ==================== DATE RANGE FILTER ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/staff/performance-overview" class="row g-2 align-items-end">
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
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Apply Range
                </button>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <?php foreach (array_slice($ranges, 0, 2) as $r): ?>
                    <a href="?from=<?= $e($r['from']) ?>&to=<?= $e($r['to']) ?>"
                       class="btn btn-sm btn-outline-secondary flex-grow-1">
                        <?= $e($r['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </form>

        <div class="mt-3 d-flex flex-wrap gap-2">
            <span class="text-muted small align-self-center">Quick ranges:</span>
            <?php foreach ($ranges as $r):
                $isActive = ($from === $r['from'] && $to === $r['to']);
            ?>
                <a href="?from=<?= $e($r['from']) ?>&to=<?= $e($r['to']) ?>"
                   class="badge text-decoration-none <?= $isActive ? 'bg-primary' : 'bg-light text-dark border' ?>">
                    <?= $e($r['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ==================== TEAM KPIs ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Active Staff</div>
                <div class="fw-bold fs-4"><?= $fmtInt($activeStaffCount) ?></div>
                <div class="text-muted" style="font-size:.72rem;">
                    of <?= $fmtInt(count($staffStats)) ?> total
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Pickups</div>
                <div class="fw-bold fs-4"><?= $fmtInt($teamTotalPickups) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Deliveries</div>
                <div class="fw-bold fs-4"><?= $fmtInt($teamTotalDeliveries) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Orders</div>
                <div class="fw-bold fs-4"><?= $fmtInt($teamTotalOrders) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Avg Rating</div>
                <div class="fw-bold fs-4"><?= number_format($teamAvgRating, 2) ?></div>
                <div class="text-warning" style="font-size:.72rem;">
                    <?php
                    $r = (int) round($teamAvgRating);
                    echo str_repeat('★', $r) . str_repeat('☆', 5 - $r);
                    ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Avg Score</div>
                <div class="fw-bold fs-4"><?= number_format($teamAvgScore, 1) ?></div>
                <span class="badge bg-<?= $scoreBadge($teamAvgScore) ?>" style="font-size:.68rem;">
                    <?= $e($scoreLabel($teamAvgScore)) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TOP PERFORMERS PODIUM ==================== -->
<?php if (!empty($topPerformers)):
    $podiumOrder = [
        1 => $topPerformers[1] ?? null,
        0 => $topPerformers[0] ?? null,
        2 => $topPerformers[2] ?? null,
    ];
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-trophy me-2 text-warning"></i>Top Performers
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3 justify-content-center">
                <?php foreach ($podiumOrder as $rank => $p):
                    if (!$p) continue;
                    $place   = $rank + 1;
                    $medal   = match ($place) {
                        1 => ['#fbbf24', 'bi-trophy-fill', 'Gold'],
                        2 => ['#94a3b8', 'bi-award-fill',  'Silver'],
                        3 => ['#d97706', 'bi-award-fill',  'Bronze'],
                        default => ['#e2e8f0', 'bi-award', ''],
                    };
                    $score   = (float) ($p['avg_score'] ?? 0);
                    $rating  = (float) ($p['avg_rating'] ?? 0);
                    $isFirst = ($place === 1);
                ?>
                    <div class="col-md-4">
                        <div class="podium-card <?= $isFirst ? 'podium-first' : '' ?>">
                            <div class="podium-medal" style="background: <?= $medal[0] ?>;">
                                <i class="bi <?= $medal[1] ?>"></i>
                            </div>
                            <div class="podium-rank">
                                #<?= $place ?>
                            </div>
                            <div class="avatar-circle avatar-lg mx-auto mb-2 bg-primary-subtle text-primary">
                                <?= $e(strtoupper(substr($p['full_name'] ?? '?', 0, 1))) ?>
                            </div>
                            <h6 class="fw-semibold mb-1"><?= $e($p['full_name']) ?></h6>
                            <div class="small text-muted mb-2"><?= $e($p['email']) ?></div>

                            <div class="d-flex justify-content-center gap-2 mb-2">
                                <span class="badge bg-<?= $roleBadge($p['role'] ?? 'staff') ?>">
                                    <?= $e($roleLabel($p['role'] ?? 'staff')) ?>
                                </span>
                                <span class="badge bg-<?= $scoreBadge($score) ?>">
                                    Score <?= number_format($score, 1) ?>
                                </span>
                            </div>

                            <div class="row g-2 small text-center mt-2">
                                <div class="col-4">
                                    <div class="fw-bold"><?= $fmtInt($p['pickups'] ?? 0) ?></div>
                                    <div class="text-muted" style="font-size:.7rem;">Pickups</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?= $fmtInt($p['deliveries'] ?? 0) ?></div>
                                    <div class="text-muted" style="font-size:.7rem;">Deliveries</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?= $fmtInt($p['orders'] ?? 0) ?></div>
                                    <div class="text-muted" style="font-size:.7rem;">Orders</div>
                                </div>
                            </div>

                            <a href="/admin/staff/<?= (int) $p['staff_id'] ?>/performance"
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

<!-- ==================== MAIN CONTENT GRID ==================== -->
<div class="row g-3">

    <!-- ==================== LEFT: CHART + FULL TABLE ==================== -->
    <div class="col-lg-12">

        <!-- Score comparison chart -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bar-chart me-2 text-primary"></i>Score Comparison
                </h6>
                <span class="text-muted small">Top <?= count($chartStaff) ?> staff by score</span>
            </div>
            <div class="card-body">
                <?php if (empty($chartStaff)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No score data in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="comparisonChart" height="80"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Full comparison table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-table me-2 text-primary"></i>All Staff Comparison
                </h6>
                <span class="text-muted small"><?= $fmtInt(count($staffStats)) ?> staff member(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small text-muted fw-semibold" style="width: 50px;">#</th>
                            <th class="small text-muted fw-semibold">Staff Member</th>
                            <th class="small text-muted fw-semibold text-center">Role</th>
                            <th class="small text-muted fw-semibold text-center">Pickups</th>
                            <th class="small text-muted fw-semibold text-center">Deliveries</th>
                            <th class="small text-muted fw-semibold text-center">Orders</th>
                            <th class="small text-muted fw-semibold text-center">Resolved</th>
                            <th class="small text-muted fw-semibold text-center">Avg Rating</th>
                            <th class="small text-muted fw-semibold text-center">Avg Score</th>
                            <th class="small text-muted fw-semibold text-center">Days</th>
                            <th class="small text-muted fw-semibold text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffStats)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                        <div class="fw-medium">No staff data available</div>
                                        <div class="small">
                                            Adjust the date range or add staff members to see comparisons.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php else:
                            $rank = 0;
                            foreach ($sorted as $row):
                                $rank++;
                                $rowId      = (int) ($row['staff_id'] ?? 0);
                                $rowName    = (string) ($row['full_name'] ?? '');
                                $rowRole    = (string) ($row['role'] ?? 'staff');
                                $rowActive  = (int) ($row['is_active'] ?? 0);
                                $rowRating  = (float) ($row['avg_rating'] ?? 0);
                                $rowScore   = (float) ($row['avg_score'] ?? 0);
                                $rowDays    = (int) ($row['days_recorded'] ?? 0);
                                $isTopThree = ($rank <= 3);
                                $rankBadge  = match ($rank) {
                                    1 => 'bg-warning text-dark',
                                    2 => 'bg-secondary text-white',
                                    3 => 'bg-danger text-white',
                                    default => 'bg-light text-dark border',
                                };
                        ?>
                            <tr class="<?= $rowActive ? '' : 'text-muted opacity-75' ?>">
                                <td>
                                    <span class="badge <?= $rankBadge ?>"><?= $rank ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle-sm bg-<?= $rowActive ? 'success' : 'secondary' ?>-subtle text-<?= $rowActive ? 'success' : 'secondary' ?>">
                                            <?= $e(strtoupper(substr($rowName, 0, 1))) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fw-medium small text-truncate">
                                                <a href="/admin/staff/<?= $rowId ?>"
                                                   class="text-decoration-none">
                                                    <?= $e($rowName) ?>
                                                </a>
                                            </div>
                                            <div class="text-muted" style="font-size:.72rem;">
                                                <?= $e($row['email'] ?? '') ?>
                                                <?php if (!$rowActive): ?>
                                                    · <span class="badge bg-secondary" style="font-size:.65rem;">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $roleBadge($rowRole) ?>" style="font-size:.7rem;">
                                        <?= $e($roleLabel($rowRole)) ?>
                                    </span>
                                </td>
                                <td class="text-center small"><?= $fmtInt($row['pickups']    ?? 0) ?></td>
                                <td class="text-center small"><?= $fmtInt($row['deliveries'] ?? 0) ?></td>
                                <td class="text-center small"><?= $fmtInt($row['orders']     ?? 0) ?></td>
                                <td class="text-center small"><?= $fmtInt($row['complaints'] ?? 0) ?></td>
                                <td class="text-center small">
                                    <?php if ($rowRating > 0): ?>
                                        <span class="text-warning">
                                            <?= str_repeat('★', (int) round($rowRating)) ?>
                                        </span>
                                        <span class="text-muted"><?= number_format($rowRating, 1) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rowScore > 0): ?>
                                        <span class="badge bg-<?= $scoreBadge($rowScore) ?>">
                                            <?= number_format($rowScore, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center small text-muted"><?= $fmtInt($rowDays) ?></td>
                                <td class="text-end">
                                    <a href="/admin/staff/<?= $rowId ?>/performance"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="View details">
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
    .avatar-circle {
        width: 46px; height: 46px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 1rem;
        flex-shrink: 0;
    }
    .avatar-lg { width: 56px; height: 56px; font-size: 1.25rem; }
    .min-w-0 { min-width: 0; }
    .table > :not(caption) > * > * {
        padding: .7rem .75rem;
        vertical-align: middle;
    }

    /* ---------- Podium ---------- */
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
</style>

<!-- ==================== SCRIPT ==================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const staffLabels = <?= json_encode($chartStaff,  $jsonFlags) ?>;
    const scores      = <?= json_encode($chartScores, $jsonFlags) ?>;
    const colors      = <?= json_encode($chartColors, $jsonFlags) ?>;

    const canvas = document.getElementById('comparisonChart');
    if (!canvas || !staffLabels.length) return;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: staffLabels,
            datasets: [{
                label: 'Avg Score',
                data: scores,
                backgroundColor: colors,
                borderRadius: 4,
                maxBarThickness: 42,
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
                        label: (ctx) => {
                            const s = Number(ctx.raw);
                            let label = 'Score: ' + s.toFixed(1);
                            if (s >= 85) label += ' · Excellent';
                            else if (s >= 70) label += ' · Good';
                            else if (s >= 50) label += ' · Average';
                            else label += ' · Needs Improvement';
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { stepSize: 25 },
                    title: { display: true, text: 'Average Score', font: { size: 10 } }
                },
                y: {
                    grid: { display: false }
                }
            }
        }
    });
})();
</script>
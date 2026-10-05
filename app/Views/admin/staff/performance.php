<?php
/**
 * Admin Staff — Performance Detail (30-day window)
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\StaffController::performance()
 *
 * Expected variables:
 *   $staff      array   full staff row
 *   $rows       array   30-day performance rows (chronological)
 *   $aggregate  array   period aggregates:
 *                       pickups, deliveries, orders, complaints,
 *                       avg_rating, avg_score, days_recorded
 * ----------------------------------------------------------
 */

$currentPage = 'staff';
$title       = 'Staff Performance';

// Safe defaults
$staff     = $staff     ?? [];
$rows      = $rows      ?? [];
$aggregate = $aggregate ?? [];

if (empty($staff)) {
    echo '<div class="alert alert-danger">Staff member not found.</div>';
    return;
}

$e           = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt      = fn($n) => number_format((int) $n);
$fmtDate     = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateShort= fn($d) => $d ? date('M j', strtotime($d)) : '—';

$sRole    = $staff['role']      ?? 'staff';
$isActive = (int) ($staff['is_active'] ?? 0);

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

// Aggregate values
$aggPickups    = (int)   ($aggregate['pickups']      ?? 0);
$aggDeliveries = (int)   ($aggregate['deliveries']   ?? 0);
$aggOrders     = (int)   ($aggregate['orders']       ?? 0);
$aggComplaints = (int)   ($aggregate['complaints']   ?? 0);
$aggAvgRating  = (float) ($aggregate['avg_rating']   ?? 0);
$aggAvgScore   = (float) ($aggregate['avg_score']    ?? 0);
$aggDays       = (int)   ($aggregate['days_recorded'] ?? 0);

// Compute best day (highest score)
$bestDay = null;
$bestScore = -1;
foreach ($rows as $r) {
    $s = (float) ($r['performance_score'] ?? 0);
    if ($s > $bestScore) {
        $bestScore = $s;
        $bestDay = $r;
    }
}

// Compute totals across the window (sum of pickups etc.)
$sumPickups    = 0;
$sumDeliveries = 0;
$sumOrders     = 0;
$sumComplaints = 0;
foreach ($rows as $r) {
    $sumPickups    += (int) ($r['pickups_completed']    ?? 0);
    $sumDeliveries += (int) ($r['deliveries_completed'] ?? 0);
    $sumOrders     += (int) ($r['orders_handled']       ?? 0);
    $sumComplaints += (int) ($r['complaints_resolved']  ?? 0);
}

// Daily averages
$avgPickups    = $aggDays > 0 ? round($sumPickups    / $aggDays, 1) : 0;
$avgDeliveries = $aggDays > 0 ? round($sumDeliveries / $aggDays, 1) : 0;
$avgOrders     = $aggDays > 0 ? round($sumOrders     / $aggDays, 1) : 0;
$avgComplaints = $aggDays > 0 ? round($sumComplaints / $aggDays, 1) : 0;

// Prepare chart data
$chartLabels     = [];
$chartPickups    = [];
$chartDeliveries = [];
$chartOrders     = [];
$chartComplaints = [];
$chartScore      = [];

foreach ($rows as $r) {
    $chartLabels[]     = date('M j', strtotime($r['recorded_date']));
    $chartPickups[]    = (int)   ($r['pickups_completed']    ?? 0);
    $chartDeliveries[] = (int)   ($r['deliveries_completed'] ?? 0);
    $chartOrders[]     = (int)   ($r['orders_handled']       ?? 0);
    $chartComplaints[] = (int)   ($r['complaints_resolved']  ?? 0);
    $chartScore[]      = (float) ($r['performance_score']    ?? 0);
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="d-flex align-items-center gap-3">
            <div class="avatar-circle bg-<?= $isActive ? 'success' : 'secondary' ?>-subtle text-<?= $isActive ? 'success' : 'secondary' ?>">
                <?= $e(strtoupper(substr($staff['full_name'] ?? '?', 0, 1))) ?>
            </div>
            <div>
                <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                    <?= $e($staff['full_name']) ?>
                    <span class="badge bg-<?= $roleBadge($sRole) ?>">
                        <?php if ($sRole === 'admin'): ?>
                            <i class="bi bi-shield-check me-1"></i>
                        <?php endif; ?>
                        <?= $e($roleLabel($sRole)) ?>
                    </span>
                </h1>
                <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                    <span>#<?= (int) $staff['staff_id'] ?></span>
                    <span>·</span>
                    <span>Performance · Last 30 days</span>
                    <span>·</span>
                    <span><?= $fmtInt($aggDays) ?> day(s) recorded</span>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/staff/performance-overview" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people me-1"></i>Compare Staff
        </a>
        <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>/performance?export=csv"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-person me-1"></i>Profile
        </a>
    </div>
</div>

<!-- ==================== KPI CARDS — 6 cards ==================== -->
<div class="row g-3 mb-4">
    <!-- Pickups -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="text-muted small text-uppercase fw-semibold">Pickups</span>
                </div>
                <div class="fw-bold fs-4"><?= $fmtInt($sumPickups) ?></div>
                <div class="text-muted" style="font-size:.72rem;">
                    ~<?= $avgPickups ?>/day
                </div>
            </div>
        </div>
    </div>
    <!-- Deliveries -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="text-muted small text-uppercase fw-semibold">Deliveries</span>
                </div>
                <div class="fw-bold fs-4"><?= $fmtInt($sumDeliveries) ?></div>
                <div class="text-muted" style="font-size:.72rem;">
                    ~<?= $avgDeliveries ?>/day
                </div>
            </div>
        </div>
    </div>
    <!-- Orders -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="text-muted small text-uppercase fw-semibold">Orders</span>
                </div>
                <div class="fw-bold fs-4"><?= $fmtInt($sumOrders) ?></div>
                <div class="text-muted" style="font-size:.72rem;">
                    ~<?= $avgOrders ?>/day
                </div>
            </div>
        </div>
    </div>
    <!-- Complaints -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="text-muted small text-uppercase fw-semibold">Resolved</span>
                </div>
                <div class="fw-bold fs-4"><?= $fmtInt($sumComplaints) ?></div>
                <div class="text-muted" style="font-size:.72rem;">
                    ~<?= $avgComplaints ?>/day
                </div>
            </div>
        </div>
    </div>
    <!-- Avg Rating -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="text-muted small text-uppercase fw-semibold">Avg Rating</span>
                </div>
                <div class="fw-bold fs-4">
                    <?= number_format($aggAvgRating, 2) ?>
                    <span class="text-muted fs-6 fw-normal">/5</span>
                </div>
                <div class="text-warning" style="font-size:.72rem;">
                    <?php
                    $r = (int) round($aggAvgRating);
                    echo str_repeat('★', $r) . str_repeat('☆', 5 - $r);
                    ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Avg Score -->
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="text-muted small text-uppercase fw-semibold">Avg Score</span>
                </div>
                <div class="fw-bold fs-4"><?= number_format($aggAvgScore, 1) ?></div>
                <span class="badge bg-<?= $scoreBadge($aggAvgScore) ?>" style="font-size:.68rem;">
                    <?= $e($scoreLabel($aggAvgScore)) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3">

    <!-- ==================== LEFT: CHARTS ==================== -->
    <div class="col-lg-8">

        <!-- Score trend -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-speedometer2 me-2 text-primary"></i>Daily Performance Score
                </h6>
                <span class="text-muted small">Last 30 days</span>
            </div>
            <div class="card-body">
                <?php if (empty($rows)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No performance data recorded in the last 30 days.</div>
                    </div>
                <?php else: ?>
                    <canvas id="scoreChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity trend -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-activity me-2 text-primary"></i>Daily Activity Breakdown
                </h6>
                <span class="text-muted small">Pickups · Deliveries · Orders · Complaints</span>
            </div>
            <div class="card-body">
                <?php if (empty($rows)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-graph-up fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No activity recorded.</div>
                    </div>
                <?php else: ?>
                    <canvas id="activityChart" height="120"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Daily breakdown table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-table me-2 text-primary"></i>Daily Breakdown
                </h6>
                <span class="text-muted small"><?= $fmtInt($aggDays) ?> row(s)</span>
            </div>
            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="small text-muted fw-semibold">Date</th>
                            <th class="small text-muted fw-semibold text-center">Pickups</th>
                            <th class="small text-muted fw-semibold text-center">Deliveries</th>
                            <th class="small text-muted fw-semibold text-center">Orders</th>
                            <th class="small text-muted fw-semibold text-center">Resolved</th>
                            <th class="small text-muted fw-semibold text-center">Rating</th>
                            <th class="small text-muted fw-semibold text-end">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4 small">
                                    No data recorded yet.
                                </td>
                            </tr>
                        <?php else:
                            // Iterate reverse (most recent first) but keep data intact
                            $revRows = array_reverse($rows);
                            foreach ($revRows as $r):
                                $dayScore = (float) ($r['performance_score'] ?? 0);
                                $isBest   = $bestDay && ($r['performance_id'] ?? null) === ($bestDay['performance_id'] ?? null);
                        ?>
                            <tr class="<?= $isBest ? 'table-success' : '' ?>">
                                <td class="small">
                                    <div class="fw-medium"><?= $fmtDate($r['recorded_date']) ?></div>
                                    <div class="text-muted" style="font-size:.7rem;">
                                        <?= $e(date('D', strtotime($r['recorded_date']))) ?>
                                        <?php if ($isBest): ?>
                                            · <span class="text-success fw-medium">Best day</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center small"><?= $fmtInt($r['pickups_completed'] ?? 0) ?></td>
                                <td class="text-center small"><?= $fmtInt($r['deliveries_completed'] ?? 0) ?></td>
                                <td class="text-center small"><?= $fmtInt($r['orders_handled'] ?? 0) ?></td>
                                <td class="text-center small"><?= $fmtInt($r['complaints_resolved'] ?? 0) ?></td>
                                <td class="text-center small">
                                    <?php $rating = (float) ($r['average_rating'] ?? 0); ?>
                                    <?php if ($rating > 0): ?>
                                        <span class="text-warning">
                                            <?= str_repeat('★', (int) round($rating)) ?>
                                        </span>
                                        <span class="text-muted"><?= number_format($rating, 1) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($dayScore > 0): ?>
                                        <span class="badge bg-<?= $scoreBadge($dayScore) ?>">
                                            <?= number_format($dayScore, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== RIGHT: SUMMARY + HIGHLIGHTS ==================== -->
    <div class="col-lg-4">

        <!-- Period summary -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-clipboard-data me-2 text-primary"></i>Period Summary
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Window</div>
                    <div class="info-value small">Last 30 days</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Days Recorded</div>
                    <div class="info-value"><strong><?= $fmtInt($aggDays) ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total Actions</div>
                    <div class="info-value">
                        <strong><?= $fmtInt($sumPickups + $sumDeliveries + $sumOrders + $sumComplaints) ?></strong>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Avg Daily</div>
                    <div class="info-value small">
                        <?= $avgPickups + $avgDeliveries + $avgOrders + $avgComplaints ?>
                        actions
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Avg Rating</div>
                    <div class="info-value">
                        <strong><?= number_format($aggAvgRating, 2) ?></strong>
                        <span class="text-muted small">/ 5</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Avg Score</div>
                    <div class="info-value">
                        <span class="badge bg-<?= $scoreBadge($aggAvgScore) ?>">
                            <?= number_format($aggAvgScore, 1) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Best day -->
        <?php if ($bestDay): ?>
            <div class="card border-0 shadow-sm mb-3 border-start border-success border-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold text-success">
                        <i class="bi bi-trophy me-2"></i>Best Day
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="info-row">
                        <div class="info-label">Date</div>
                        <div class="info-value small">
                            <?= $fmtDate($bestDay['recorded_date']) ?>
                            <div class="text-muted" style="font-size:.72rem;">
                                <?= $e(date('l', strtotime($bestDay['recorded_date']))) ?>
                            </div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Score</div>
                        <div class="info-value">
                            <span class="badge bg-<?= $scoreBadge((float) $bestDay['performance_score']) ?>">
                                <?= number_format((float) $bestDay['performance_score'], 1) ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Deliveries</div>
                        <div class="info-value"><?= $fmtInt($bestDay['deliveries_completed'] ?? 0) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Pickups</div>
                        <div class="info-value"><?= $fmtInt($bestDay['pickups_completed'] ?? 0) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Orders</div>
                        <div class="info-value"><?= $fmtInt($bestDay['orders_handled'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Performance bar comparison -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bar-chart-steps me-2 text-primary"></i>Metric Breakdown
                </h6>
            </div>
            <div class="card-body pt-0">
                <?php
                $maxMetric = max($sumPickups, $sumDeliveries, $sumOrders, $sumComplaints, 1);
                $metrics = [
                    ['label' => 'Pickups',    'value' => $sumPickups,    'color' => 'primary'],
                    ['label' => 'Deliveries', 'value' => $sumDeliveries, 'color' => 'success'],
                    ['label' => 'Orders',     'value' => $sumOrders,     'color' => 'info'],
                    ['label' => 'Resolved',   'value' => $sumComplaints, 'color' => 'warning'],
                ];
                foreach ($metrics as $m):
                    $pct = round(($m['value'] / $maxMetric) * 100, 1);
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted fw-medium"><?= $e($m['label']) ?></span>
                            <strong><?= $fmtInt($m['value']) ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-<?= $e($m['color']) ?>"
                                 style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .avatar-circle {
        width: 48px; height: 48px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 1.05rem;
        flex-shrink: 0;
    }
    .info-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: .6rem 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: .88rem;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label {
        color: #64748b;
        font-weight: 500;
        flex-shrink: 0;
        min-width: 110px;
    }
    .info-value {
        text-align: right;
        word-break: break-word;
        color: #0f172a;
    }
    .table > :not(caption) > * > * {
        padding: .65rem .75rem;
        vertical-align: middle;
    }
    .table thead th {
        background: #f8fafc;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    // ---------- Shared data ----------
    const labels     = <?= json_encode($chartLabels,     $jsonFlags) ?>;
    const pickups    = <?= json_encode($chartPickups,    $jsonFlags) ?>;
    const deliveries = <?= json_encode($chartDeliveries, $jsonFlags) ?>;
    const orders     = <?= json_encode($chartOrders,     $jsonFlags) ?>;
    const complaints = <?= json_encode($chartComplaints, $jsonFlags) ?>;
    const scores     = <?= json_encode($chartScore,      $jsonFlags) ?>;

    if (!labels.length) return;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // ---------- Score chart (line + gradient area) ----------
    const scoreCanvas = document.getElementById('scoreChart');
    if (scoreCanvas) {
        const ctx = scoreCanvas.getContext('2d');

        // Build a gradient fill from primary blue
        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(14,165,233,.25)');
        gradient.addColorStop(1, 'rgba(14,165,233,.02)');

        new Chart(scoreCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Score',
                    data: scores,
                    borderColor: '#0ea5e9',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#0ea5e9',
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
                            label: (ctx) => ' Score: ' + Number(ctx.raw).toFixed(1)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { stepSize: 25 },
                        title: { display: true, text: 'Score (0–100)', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 20 }
                    }
                }
            }
        });
    }

    // ---------- Activity chart (grouped bar) ----------
    const activityCanvas = document.getElementById('activityChart');
    if (activityCanvas) {
        new Chart(activityCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Pickups',
                        data: pickups,
                        backgroundColor: '#0ea5e9',
                        borderRadius: 2,
                        maxBarThickness: 14,
                    },
                    {
                        label: 'Deliveries',
                        data: deliveries,
                        backgroundColor: '#22c55e',
                        borderRadius: 2,
                        maxBarThickness: 14,
                    },
                    {
                        label: 'Orders',
                        data: orders,
                        backgroundColor: '#06b6d4',
                        borderRadius: 2,
                        maxBarThickness: 14,
                    },
                    {
                        label: 'Resolved',
                        data: complaints,
                        backgroundColor: '#f59e0b',
                        borderRadius: 2,
                        maxBarThickness: 14,
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
                        title: { display: true, text: 'Count', font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 20 }
                    }
                }
            }
        });
    }
})();
</script>
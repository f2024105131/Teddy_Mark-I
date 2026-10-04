<?php
/**
 * Admin Staff — Detail / Profile View
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\StaffController::show()
 *
 * Expected variables:
 *   $staff          array      full staff row
 *   $todayPerf      ?array     today's staff_performance row (or null)
 *   $totals         array      lifetime aggregates
 *   $recentActivity array      recent pickups/deliveries/complaints
 *   $trend          array      14-day performance rows
 * ----------------------------------------------------------
 */

$currentPage = 'staff';
$title       = 'Staff Profile';

// Safe defaults
$staff          = $staff          ?? [];
$todayPerf      = $todayPerf      ?? null;
$totals         = $totals         ?? [];
$recentActivity = $recentActivity ?? [];
$trend          = $trend          ?? [];

if (empty($staff)) {
    echo '<div class="alert alert-danger">Staff member not found.</div>';
    return;
}

$e           = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt      = fn($n) => number_format((int) $n);
$fmtDate     = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

$sRole    = $staff['role']      ?? 'staff';
$isActive = (int) ($staff['is_active'] ?? 0);
$isSelf   = (int) $staff['staff_id'] === (int) ($_SESSION['staff_id'] ?? 0);

// Relative time
$timeAgo = function (?string $d): string {
    if (!$d) return 'Never';
    $ts = strtotime($d);
    if (!$ts) return 'Never';
    $diff = time() - $ts;
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
};

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

// Today's performance values
$tPickups    = (int)   ($todayPerf['pickups_completed']    ?? 0);
$tDeliveries = (int)   ($todayPerf['deliveries_completed'] ?? 0);
$tOrders     = (int)   ($todayPerf['orders_handled']       ?? 0);
$tComplaints = (int)   ($todayPerf['complaints_resolved']  ?? 0);
$tRating     = (float) ($todayPerf['average_rating']       ?? 0);
$tScore      = (float) ($todayPerf['performance_score']    ?? 0);
$hasTodayPerf = $todayPerf !== null;

// Lifetime totals
$totPickupsDone       = (int)   ($totals['pickups_done']        ?? 0);
$totPickupsTotal      = (int)   ($totals['pickups_total']       ?? 0);
$totDeliveriesDone    = (int)   ($totals['deliveries_done']     ?? 0);
$totDeliveriesTotal   = (int)   ($totals['deliveries_total']    ?? 0);
$totComplaintsTotal   = (int)   ($totals['complaints_total']    ?? 0);
$totComplaintsDone    = (int)   ($totals['complaints_resolved'] ?? 0);
$totAvgScore          = (float) ($totals['avg_score']           ?? 0);
$totAvgRating         = (float) ($totals['avg_rating']          ?? 0);

// Completion rates
$pickupRate     = $totPickupsTotal    > 0 ? round(($totPickupsDone    / $totPickupsTotal)    * 100, 1) : 0.0;
$deliveryRate   = $totDeliveriesTotal > 0 ? round(($totDeliveriesDone / $totDeliveriesTotal) * 100, 1) : 0.0;
$complaintRate  = $totComplaintsTotal > 0 ? round(($totComplaintsDone / $totComplaintsTotal) * 100, 1) : 0.0;

// Status badge maps
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
$activityBadge = function (string $kind, string $status): string {
    return match ($kind) {
        'pickup'    => match ($status) {
            'picked_up' => 'success',
            'cancelled' => 'danger',
            'assigned'  => 'info',
            default     => 'secondary',
        },
        'delivery'  => match ($status) {
            'delivered'        => 'success',
            'failed'           => 'danger',
            'out_for_delivery' => 'info',
            'scheduled'        => 'warning',
            'rescheduled'      => 'secondary',
            default            => 'secondary',
        },
        'complaint' => match ($status) {
            'resolved' => 'success',
            'rejected' => 'secondary',
            'escalated'=> 'dark',
            'closed'   => 'light',
            default    => 'warning',
        },
        default     => 'secondary',
    };
};
$activityIcon = function (string $kind): string {
    return match ($kind) {
        'pickup'    => 'bi-box-seam',
        'delivery'  => 'bi-truck',
        'complaint' => 'bi-exclamation-circle',
        default     => 'bi-circle',
    };
};
$activityLabel = function (string $kind, string $status): string {
    $kindLabel = ucfirst($kind);
    $statusLabel = ucwords(str_replace('_', ' ', $status));
    return "{$kindLabel} · {$statusLabel}";
};

// Prepare chart data
$trendLabels = [];
$trendPickups = [];
$trendDeliveries = [];
$trendScore = [];
foreach ($trend as $row) {
    $trendLabels[]     = date('M j', strtotime($row['recorded_date']));
    $trendPickups[]    = (int)   ($row['pickups_completed']    ?? 0);
    $trendDeliveries[] = (int)   ($row['deliveries_completed'] ?? 0);
    $trendScore[]      = (float) ($row['performance_score']    ?? 0);
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/staff" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="d-flex align-items-center gap-3">
            <div class="avatar-circle avatar-lg bg-<?= $isActive ? 'success' : 'secondary' ?>-subtle text-<?= $isActive ? 'success' : 'secondary' ?>">
                <?= $e(strtoupper(substr($staff['full_name'] ?? '?', 0, 1))) ?>
            </div>
            <div>
                <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                    <?= $e($staff['full_name']) ?>
                    <?php if ($isSelf): ?>
                        <span class="badge bg-info-subtle text-info" style="font-size:.7rem;">You</span>
                    <?php endif; ?>
                </h1>
                <div class="d-flex flex-wrap gap-2 align-items-center small">
                    <span class="text-muted">#<?= (int) $staff['staff_id'] ?></span>
                    <span class="text-muted">·</span>
                    <span class="badge bg-<?= $roleBadge($sRole) ?>">
                        <?php if ($sRole === 'admin'): ?>
                            <i class="bi bi-shield-check me-1"></i>
                        <?php endif; ?>
                        <?= $e($roleLabel($sRole)) ?>
                    </span>
                    <span class="text-muted">·</span>
                    <?php if ($isActive): ?>
                        <span class="badge bg-success-subtle text-success">
                            <i class="bi bi-circle-fill" style="font-size:.5rem;"></i> Active
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary">
                            <i class="bi bi-circle-fill" style="font-size:.5rem;"></i> Inactive
                        </span>
                    <?php endif; ?>
                    <span class="text-muted">·</span>
                    <span class="text-muted">Joined <?= $fmtDate($staff['created_at']) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>/performance"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up me-1"></i>Full Performance
        </a>
        <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>/edit"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-lightning-charge me-1"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <button type="button" class="dropdown-item"
                            onclick="staffAction('reset-password', <?= (int) $staff['staff_id'] ?>, <?= json_encode($staff['full_name'], $jsonFlags) ?>)">
                        <i class="bi bi-key me-2"></i>Reset Password
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item"
                            onclick="staffAction('change-role', <?= (int) $staff['staff_id'] ?>, <?= json_encode($staff['full_name'], $jsonFlags) ?>, <?= json_encode($sRole) ?>)">
                        <i class="bi bi-person-gear me-2"></i>Change Role
                    </button>
                </li>
                <?php if (!$isSelf): ?>
                    <li><hr class="dropdown-divider"></li>
                    <?php if ($isActive): ?>
                        <li>
                            <button type="button" class="dropdown-item text-danger"
                                    onclick="staffAction('toggle-active', <?= (int) $staff['staff_id'] ?>, <?= json_encode($staff['full_name'], $jsonFlags) ?>)">
                                <i class="bi bi-person-slash me-2"></i>Deactivate Account
                            </button>
                        </li>
                    <?php else: ?>
                        <li>
                            <button type="button" class="dropdown-item text-success"
                                    onclick="staffAction('toggle-active', <?= (int) $staff['staff_id'] ?>, <?= json_encode($staff['full_name'], $jsonFlags) ?>)">
                                <i class="bi bi-person-check me-2"></i>Activate Account
                            </button>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- ==================== TODAY'S PERFORMANCE BANNER ==================== -->
<?php if ($hasTodayPerf): ?>
    <div class="card border-0 shadow-sm mb-4"
         style="background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%); color: #fff;">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="small opacity-75 text-uppercase fw-semibold">Today's Performance Score</div>
                    <div class="d-flex align-items-center gap-3 mt-1">
                        <h2 class="mb-0 fw-bold"><?= number_format($tScore, 1) ?></h2>
                        <span class="badge bg-white text-dark">
                            <?= $e($scoreLabel($tScore)) ?>
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-4 text-center">
                    <div>
                        <div class="fs-4 fw-bold"><?= $fmtInt($tPickups) ?></div>
                        <div class="small opacity-75">Pickups</div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $fmtInt($tDeliveries) ?></div>
                        <div class="small opacity-75">Deliveries</div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $fmtInt($tOrders) ?></div>
                        <div class="small opacity-75">Orders</div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $fmtInt($tComplaints) ?></div>
                        <div class="small opacity-75">Resolved</div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold">
                            <?= number_format($tRating, 1) ?><span class="fs-6">/5</span>
                        </div>
                        <div class="small opacity-75">Rating</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-info-circle"></i>
        <div class="small">
            No performance data recorded for today yet.
            Metrics are updated as this staff member completes pickups, deliveries, and resolves complaints.
        </div>
    </div>
<?php endif; ?>

<!-- ==================== LIFETIME KPI CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-muted small text-uppercase fw-semibold">Pickups</div>
                        <div class="fw-bold fs-5 mb-0">
                            <?= $fmtInt($totPickupsDone) ?>
                            <span class="text-muted fs-6 fw-normal">/ <?= $fmtInt($totPickupsTotal) ?></span>
                        </div>
                        <div class="progress mt-2" style="height:4px;">
                            <div class="progress-bar bg-primary" style="width: <?= $pickupRate ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-muted small text-uppercase fw-semibold">Deliveries</div>
                        <div class="fw-bold fs-5 mb-0">
                            <?= $fmtInt($totDeliveriesDone) ?>
                            <span class="text-muted fs-6 fw-normal">/ <?= $fmtInt($totDeliveriesTotal) ?></span>
                        </div>
                        <div class="progress mt-2" style="height:4px;">
                            <div class="progress-bar bg-success" style="width: <?= $deliveryRate ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-muted small text-uppercase fw-semibold">Complaints</div>
                        <div class="fw-bold fs-5 mb-0">
                            <?= $fmtInt($totComplaintsDone) ?>
                            <span class="text-muted fs-6 fw-normal">/ <?= $fmtInt($totComplaintsTotal) ?></span>
                        </div>
                        <div class="progress mt-2" style="height:4px;">
                            <div class="progress-bar bg-warning" style="width: <?= $complaintRate ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info-subtle text-info">
                        <i class="bi bi-star"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-muted small text-uppercase fw-semibold">Avg. Score</div>
                        <div class="fw-bold fs-5 mb-0">
                            <?= number_format($totAvgScore, 1) ?>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;">
                            Rating: <?= number_format($totAvgRating, 2) ?> / 5
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3 mb-4">

    <!-- LEFT: Contact + Role -->
    <div class="col-lg-4">

        <!-- Contact Information -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person-lines-fill me-2 text-primary"></i>Contact Information
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value">
                        <a href="mailto:<?= $e($staff['email']) ?>" class="text-decoration-none">
                            <?= $e($staff['email']) ?>
                        </a>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone</div>
                    <div class="info-value">
                        <a href="tel:<?= $e($staff['phone']) ?>" class="text-decoration-none">
                            <?= $e($staff['phone']) ?>
                        </a>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Role</div>
                    <div class="info-value">
                        <span class="badge bg-<?= $roleBadge($sRole) ?>">
                            <?= $e($roleLabel($sRole)) ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <?php if ($isActive): ?>
                            <span class="badge bg-success-subtle text-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Meta -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Account Activity
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Staff ID</div>
                    <div class="info-value"><code>#<?= (int) $staff['staff_id'] ?></code></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Joined</div>
                    <div class="info-value small"><?= $fmtDate($staff['created_at']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Last Updated</div>
                    <div class="info-value small"><?= $fmtDate($staff['updated_at'] ?? null) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Last Login</div>
                    <div class="info-value small">
                        <?php if (!empty($staff['last_login'])): ?>
                            <div><?= $e($timeAgo($staff['last_login'])) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <?= $fmtDateTime($staff['last_login']) ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">Never logged in</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Chart + Recent Activity -->
    <div class="col-lg-8">

        <!-- 14-day trend chart -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-graph-up me-2 text-primary"></i>14-Day Performance Trend
                </h6>
                <span class="text-muted small"><?= count($trend) ?> day(s) of data</span>
            </div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No performance data recorded in the last 14 days.</div>
                    </div>
                <?php else: ?>
                    <canvas id="staffTrendChart" height="120"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-activity me-2 text-primary"></i>Recent Activity
                </h6>
                <span class="text-muted small">Last <?= count($recentActivity) ?> events</span>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentActivity)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No activity recorded yet.</div>
                    </div>
                <?php else: foreach ($recentActivity as $a):
                    $kind       = $a['kind']        ?? 'pickup';
                    $status     = $a['status']      ?? '';
                    $refLabel   = $a['ref_label']   ?? '';
                    $refId      = (int) ($a['ref_id'] ?? 0);
                    $eventDate  = $a['event_date']  ?? null;
                    $customer   = $a['customer_name'] ?? '';
                    $badgeClass = $activityBadge($kind, $status);
                    $icon       = $activityIcon($kind);
                ?>
                    <div class="list-group-item px-3 py-2 border-0 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="activity-icon bg-<?= $badgeClass ?>-subtle text-<?= $badgeClass ?>">
                                <i class="bi <?= $e($icon) ?>"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between gap-2">
                                    <div class="fw-medium small text-truncate">
                                        <?= $e($refLabel) ?>
                                    </div>
                                    <div class="text-muted small flex-shrink-0">
                                        <?= $e($timeAgo($eventDate)) ?>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between gap-2">
                                    <div class="text-muted text-truncate" style="font-size:.78rem;">
                                        <?= $e($customer) ?>
                                    </div>
                                    <span class="badge bg-<?= $badgeClass ?>-subtle text-<?= $badgeClass ?> flex-shrink-0"
                                          style="font-size:.7rem;">
                                        <?= $e(ucwords(str_replace('_', ' ', $status))) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== ACTION MODAL ==================== -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="actionModalText" class="mb-3"></p>

                <div id="roleWrap" class="d-none">
                    <label class="form-label small text-muted">New Role</label>
                    <select class="form-select" id="newRole">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                    <div class="form-text small">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        Admins have full access. You cannot demote the last active admin.
                    </div>
                </div>

                <div id="passwordWrap" class="d-none">
                    <div class="alert alert-info small mb-3 py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Leave fields blank to email a reset link. Fill to set a new password now.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">New Password</label>
                        <input type="password" class="form-control" id="newPassword"
                               placeholder="Leave blank to email reset link"
                               autocomplete="new-password">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">Confirm Password</label>
                        <input type="password" class="form-control" id="confirmPassword"
                               placeholder="Repeat password"
                               autocomplete="new-password">
                    </div>
                    <div class="form-text small">
                        Min 8 chars, must contain a letter and a number.
                    </div>
                    <div class="invalid-feedback d-block" id="passwordError" style="display:none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .avatar-circle {
        width: 38px; height: 38px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .9rem;
        flex-shrink: 0;
    }
    .avatar-lg { width: 56px; height: 56px; font-size: 1.35rem; }
    .kpi-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .activity-icon {
        width: 34px; height: 34px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: .95rem;
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
    .min-w-0 { min-width: 0; }
</style>

<!-- ==================== SCRIPT ==================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ---------- Trend chart ----------
    const canvas = document.getElementById('staffTrendChart');
    if (canvas) {
        const labels     = <?= json_encode($trendLabels, $jsonFlags) ?>;
        const pickups    = <?= json_encode($trendPickups, $jsonFlags) ?>;
        const deliveries = <?= json_encode($trendDeliveries, $jsonFlags) ?>;
        const scores     = <?= json_encode($trendScore, $jsonFlags) ?>;

        Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.color = '#64748b';

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Pickups',
                        data: pickups,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14,165,233,.08)',
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Deliveries',
                        data: deliveries,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,.08)',
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Score',
                        data: scores,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,.08)',
                        tension: 0.35,
                        borderWidth: 2,
                        borderDash: [4, 4],
                        pointRadius: 2,
                        yAxisID: 'y1',
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12 } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: { display: true, text: 'Count', font: { size: 10 } },
                        ticks: { precision: 0 }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Score', font: { size: 10 } },
                        max: 100,
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // ---------- Action modal ----------
    const modal        = new bootstrap.Modal(document.getElementById('actionModal'));
    const modalTitle   = document.getElementById('actionModalTitle');
    const modalText    = document.getElementById('actionModalText');
    const roleWrap     = document.getElementById('roleWrap');
    const newRole      = document.getElementById('newRole');
    const passwordWrap = document.getElementById('passwordWrap');
    const newPwd       = document.getElementById('newPassword');
    const confirmPwd   = document.getElementById('confirmPassword');
    const pwdErr       = document.getElementById('passwordError');
    const confirmBtn   = document.getElementById('confirmBtn');

    let pendingAction = null;

    const ACTIONS = {
        'reset-password': {
            title: 'Reset Password',
            text: (n) => `Generate a password reset for <strong>${escapeHtml(n)}</strong>?`,
            confirmLabel: 'Reset Password',
            btnClass: 'btn-warning',
            showRole: false,
            showPassword: true,
        },
        'change-role': {
            title: 'Change Role',
            text: (n) => `Change role for <strong>${escapeHtml(n)}</strong>?`,
            confirmLabel: 'Change Role',
            btnClass: 'btn-primary',
            showRole: true,
            showPassword: false,
        },
        'toggle-active': {
            title: 'Toggle Account Status',
            text: (n) => `Toggle account status for <strong>${escapeHtml(n)}</strong>?`,
            confirmLabel: 'Confirm',
            btnClass: 'btn-danger',
            showRole: false,
            showPassword: false,
        },
    };

    window.staffAction = function (action, id, name, currentRole) {
        const cfg = ACTIONS[action];
        if (!cfg) return;

        pendingAction = { type: action, id: id };

        modalTitle.textContent = cfg.title;
        modalText.innerHTML    = cfg.text(name);
        confirmBtn.textContent = cfg.confirmLabel;
        confirmBtn.className   = 'btn ' + cfg.btnClass;

        roleWrap.classList.add('d-none');
        passwordWrap.classList.add('d-none');
        pwdErr.style.display = 'none';

        if (cfg.showRole) {
            roleWrap.classList.remove('d-none');
            newRole.value = currentRole || 'staff';
        }
        if (cfg.showPassword) {
            passwordWrap.classList.remove('d-none');
            newPwd.value = '';
            confirmPwd.value = '';
        }

        modal.show();
        setTimeout(() => {
            if (cfg.showRole)     newRole.focus();
            if (cfg.showPassword) newPwd.focus();
        }, 300);
    };

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { type, id } = pendingAction;
        const body = new URLSearchParams({ _csrf: csrf });

        if (type === 'change-role') {
            body.append('role', newRole.value);
        }

        if (type === 'reset-password') {
            const pwd = newPwd.value;
            const cfm = confirmPwd.value;

            if (pwd !== '' || cfm !== '') {
                if (pwd.length < 8) {
                    pwdErr.textContent = 'Password must be at least 8 characters.';
                    pwdErr.style.display = 'block'; return;
                }
                if (pwd.length > 72) {
                    pwdErr.textContent = 'Password must not exceed 72 characters.';
                    pwdErr.style.display = 'block'; return;
                }
                if (!/[A-Za-z]/.test(pwd)) {
                    pwdErr.textContent = 'Password must contain at least one letter.';
                    pwdErr.style.display = 'block'; return;
                }
                if (!/[0-9]/.test(pwd)) {
                    pwdErr.textContent = 'Password must contain at least one number.';
                    pwdErr.style.display = 'block'; return;
                }
                if (pwd !== cfm) {
                    pwdErr.textContent = 'Passwords do not match.';
                    pwdErr.style.display = 'block'; return;
                }
                body.append('mode', 'set');
                body.append('new_password', pwd);
                body.append('confirm_password', cfm);
            } else {
                body.append('mode', 'token');
            }
        }

        confirmBtn.disabled = true;
        const originalText = confirmBtn.textContent;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const url = buildUrl(type, id);
            if (!url) { modal.hide(); return; }

            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Done.', 'success');
                modal.hide();
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(json.message || 'Action failed.', 'danger');
                confirmBtn.disabled = false;
                confirmBtn.textContent = originalText;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            confirmBtn.disabled = false;
            confirmBtn.textContent = originalText;
        }
    });

    function buildUrl(type, id) {
        const base = '/admin/staff/' + id;
        switch (type) {
            case 'reset-password': return base + '/reset-password';
            case 'change-role':    return base + '/change-role';
            case 'toggle-active':  return base + '/toggle-active';
            default:               return null;
        }
    }

    document.getElementById('actionModal').addEventListener('hidden.bs.modal', () => {
        pendingAction = null;
        confirmBtn.disabled = false;
        pwdErr.style.display = 'none';
    });

    // ---------- Toast ----------
    function showToast(message, type) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${escapeHtml(message)}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        container.appendChild(toast);
        new bootstrap.Toast(toast, { delay: 4000 }).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }
})();
</script>
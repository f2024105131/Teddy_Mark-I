<?php
/**
 * Admin Pickups — Live Operations Monitor
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\PickupMonitorController::index()
 *
 * Expected variables:
 *   $date              string   Y-m-d  (current viewed date)
 *   $status            string   filter ('' = all)
 *   $staffId           int      filter (0 = all)
 *   $area              string   filter ('' = all)
 *   $tab               string   'pickups' | 'deliveries' | 'pipeline'
 *   $pickups           array    pickup rows for the date
 *   $deliveries        array    delivery rows for the date
 *   $summary           array    ['pickups'=>[...], 'deliveries'=>[...]]
 *   $alerts            array    alerts from monitorAlerts()
 *   $staffList         array    active staff for assign dropdown
 *   $areas             array    distinct areas for filter
 *   $pickupTimeline    array    24 hourly buckets (pickups)
 *   $deliveryTimeline  array    24 hourly buckets (deliveries)
 *   $onDuty            array    staff with today's load
 *   $prevDate          string
 *   $nextDate          string
 *   $isToday           bool
 *
 * JSON endpoint for auto-refresh:
 *   GET /admin/pickups/monitor/live?date=YYYY-MM-DD
 * ----------------------------------------------------------
 */

$currentPage = 'pickups';
$title       = 'Live Operations Monitor';

// Safe defaults
$date     = $date     ?? date('Y-m-d');
$status   = $status   ?? '';
$staffId  = (int) ($staffId ?? 0);
$area     = $area     ?? '';
$tab      = $tab      ?? 'pickups';
$pickups  = $pickups  ?? [];
$deliveries = $deliveries ?? [];
$summary  = $summary  ?? ['pickups'=>[], 'deliveries'=>[]];
$alerts   = $alerts   ?? [];
$staffList = $staffList ?? [];
$areas    = $areas    ?? [];
$pickupTimeline   = $pickupTimeline   ?? array_fill(0, 24, 0);
$deliveryTimeline = $deliveryTimeline ?? array_fill(0, 24, 0);
$onDuty   = $onDuty   ?? [];
$prevDate = $prevDate ?? date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = $nextDate ?? date('Y-m-d', strtotime($date . ' +1 day'));
$isToday  = (bool) ($isToday ?? ($date === date('Y-m-d')));

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtTime  = fn($t) => $t ? substr($t, 0, 5) : '';
$fmtDateTime = fn($d) => $d ? date('M j · g:i A', strtotime($d)) : '—';

// Date navigation label
$dateLabel = $isToday
    ? 'Today · ' . date('M j, Y', strtotime($date))
    : ($date === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow · ' . date('M j, Y', strtotime($date))
    : ($date === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday · ' . date('M j, Y', strtotime($date))
    : date('l · M j, Y', strtotime($date))));

// Status → badge
$pickupBadge = function (string $s): string {
    return match ($s) {
        'requested' => 'warning',
        'assigned'  => 'info',
        'picked_up' => 'success',
        'cancelled' => 'danger',
        default     => 'secondary',
    };
};
$pickupLabel = function (string $s): string {
    return match ($s) {
        'requested' => 'Requested',
        'assigned'  => 'Assigned',
        'picked_up' => 'Picked Up',
        'cancelled' => 'Cancelled',
        default     => ucfirst($s),
    };
};

$deliveryBadge = function (string $s): string {
    return match ($s) {
        'scheduled'        => 'warning',
        'out_for_delivery' => 'info',
        'delivered'        => 'success',
        'failed'           => 'danger',
        'rescheduled'      => 'secondary',
        default            => 'secondary',
    };
};
$deliveryLabel = function (string $s): string {
    return match ($s) {
        'scheduled'        => 'Scheduled',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'failed'           => 'Failed',
        'rescheduled'      => 'Rescheduled',
        default            => ucwords(str_replace('_', ' ', $s)),
    };
};

// Build querystring helper (preserve filters)
$qs = function (array $overrides = []) use ($date, $status, $staffId, $area, $tab): string {
    $params = array_filter([
        'date'   => $overrides['date']   ?? $date,
        'status' => array_key_exists('status', $overrides) ? $overrides['status'] : $status,
        'staff'  => array_key_exists('staff',  $overrides) ? $overrides['staff']  : ($staffId ?: null),
        'area'   => array_key_exists('area',   $overrides) ? $overrides['area']   : ($area ?: null),
        'tab'    => $overrides['tab']    ?? $tab,
    ], fn($v) => $v !== null && $v !== '');

    return '?' . http_build_query($params);
};

// Timeline max (for height scaling)
$timelineMax = max(1, max(array_merge($pickupTimeline, $deliveryTimeline)));

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <div>
        <h1 class="page-title mb-1">Live Operations Monitor</h1>
        <p class="text-muted mb-0 small">
            Real-time view of pickups, deliveries, and pipeline activity
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/pickups/monitor/export?<?= http_build_query(array_filter([
            'date'  => $date,
            'staff' => $staffId ?: null,
            'area'  => $area ?: null,
        ])) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <button type="button" class="btn btn-outline-primary btn-sm" id="refreshNowBtn">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
        <div class="form-check form-switch mb-0 align-self-center ms-2">
            <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
            <label class="form-check-label small" for="autoRefresh">
                Auto-refresh <span class="text-muted" id="lastRefreshLabel">(15s)</span>
            </label>
        </div>
    </div>
</div>

<!-- ==================== DATE NAVIGATOR ==================== -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <a href="<?= $e($qs(['date' => $prevDate])) ?>"
                   class="btn btn-sm btn-outline-secondary"
                   title="Previous day">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <div class="text-center">
                    <div class="fw-semibold"><?= $e($dateLabel) ?></div>
                    <div class="text-muted small">
                        <?= $isToday ? '<i class="bi bi-broadcast text-success"></i> Live' : 'Historical view' ?>
                    </div>
                </div>
                <a href="<?= $e($qs(['date' => $nextDate])) ?>"
                   class="btn btn-sm btn-outline-secondary"
                   title="Next day">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php if (!$isToday): ?>
                    <a href="<?= $e($qs(['date' => date('Y-m-d')])) ?>"
                       class="btn btn-sm btn-primary ms-2">
                        <i class="bi bi-calendar-check me-1"></i>Jump to Today
                    </a>
                <?php endif; ?>
            </div>

            <form method="get" action="/admin/pickups/monitor" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="hidden" name="tab" value="<?= $e($tab) ?>">
                <input type="date"
                       class="form-control form-control-sm"
                       name="date"
                       value="<?= $e($date) ?>"
                       style="width: auto;">
                <select class="form-select form-select-sm" name="staff" style="width: auto;">
                    <option value="">All staff</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['staff_id'] ?>"
                                <?= ((int) $s['staff_id'] === $staffId) ? 'selected' : '' ?>>
                            <?= $e($s['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm" name="area" style="width: auto;">
                    <option value="">All areas</option>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?= $e($a) ?>" <?= $area === $a ? 'selected' : '' ?>>
                            <?= $e($a) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i>
                </button>
                <?php if ($staffId > 0 || $area !== ''): ?>
                    <a href="/admin/pickups/monitor?date=<?= $e($date) ?>&tab=<?= $e($tab) ?>"
                       class="btn btn-outline-secondary btn-sm"
                       title="Clear filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- ==================== ALERTS ==================== -->
<?php if (!empty($alerts)): ?>
    <div class="row g-2 mb-3">
        <?php foreach ($alerts as $alert):
            $level = $alert['level'] ?? 'info';
            $alertClass = match ($level) {
                'danger'  => 'alert-danger',
                'warning' => 'alert-warning',
                'success' => 'alert-success',
                default   => 'alert-info',
            };
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="alert <?= $alertClass ?> d-flex align-items-center gap-2 mb-0 py-2">
                    <i class="bi <?= $e($alert['icon'] ?? 'bi-info-circle') ?> fs-5"></i>
                    <div class="small flex-grow-1"><?= $e($alert['message'] ?? '') ?></div>
                    <?php if (!empty($alert['count'])): ?>
                        <span class="badge bg-dark"><?= (int) $alert['count'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ==================== KPI CARDS ==================== -->
<div class="row g-3 mb-3">
    <!-- Pickups -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-box-seam text-primary me-1"></i>Pickups
                    </h6>
                    <span class="badge bg-primary">
                        <?= $fmtInt($summary['pickups']['total'] ?? 0) ?> total
                    </span>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-2 text-center">
                    <div class="col">
                        <div class="stat-mini bg-warning-subtle text-warning">
                            <div class="fw-bold"><?= $fmtInt($summary['pickups']['requested'] ?? 0) ?></div>
                            <div class="small">Requested</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-mini bg-info-subtle text-info">
                            <div class="fw-bold"><?= $fmtInt($summary['pickups']['assigned'] ?? 0) ?></div>
                            <div class="small">Assigned</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-mini bg-success-subtle text-success">
                            <div class="fw-bold"><?= $fmtInt($summary['pickups']['picked_up'] ?? 0) ?></div>
                            <div class="small">Picked Up</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-mini bg-danger-subtle text-danger">
                            <div class="fw-bold"><?= $fmtInt($summary['pickups']['cancelled'] ?? 0) ?></div>
                            <div class="small">Cancelled</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-mini bg-dark-subtle text-dark">
                            <div class="fw-bold"><?= $fmtInt($summary['pickups']['unassigned'] ?? 0) ?></div>
                            <div class="small">Unassigned</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deliveries -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-truck text-success me-1"></i>Deliveries
                    </h6>
                    <span class="badge bg-success">
                        <?= $fmtInt($summary['deliveries']['total'] ?? 0) ?> total
                    </span>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-2 text-center">
                    <div class="col">
                        <div class="stat-mini bg-warning-subtle text-warning">
                            <div class="fw-bold"><?= $fmtInt($summary['deliveries']['scheduled'] ?? 0) ?></div>
                            <div class="small">Scheduled</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-mini bg-info-subtle text-info">
                            <div class="fw-bold"><?= $fmtInt($summary['deliveries']['out_for_delivery'] ?? 0) ?></div>
                            <div class="small">Out</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-mini bg-success-subtle text-success">
                            <div class="fw-bold"><?= $fmtInt($summary['deliveries']['delivered'] ?? 0) ?></div>
                            <div class="small">Delivered</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-mini bg-danger-subtle text-danger">
                            <div class="fw-bold"><?= $fmtInt($summary['deliveries']['failed'] ?? 0) ?></div>
                            <div class="small">Failed</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-mini bg-secondary-subtle text-secondary">
                            <div class="fw-bold"><?= $fmtInt($summary['deliveries']['rescheduled'] ?? 0) ?></div>
                            <div class="small">Resched.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3">

    <!-- ==================== LEFT: MAIN CONSOLE ==================== -->
    <div class="col-lg-9">

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-0" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'pickups' ? 'active' : '' ?>"
                   href="<?= $e($qs(['tab' => 'pickups'])) ?>">
                    <i class="bi bi-box-seam me-1"></i>Pickups
                    <span class="badge bg-primary-subtle text-primary ms-1">
                        <?= count($pickups) ?>
                    </span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'deliveries' ? 'active' : '' ?>"
                   href="<?= $e($qs(['tab' => 'deliveries'])) ?>">
                    <i class="bi bi-truck me-1"></i>Deliveries
                    <span class="badge bg-success-subtle text-success ms-1">
                        <?= count($deliveries) ?>
                    </span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === 'pipeline' ? 'active' : '' ?>"
                   href="<?= $e($qs(['tab' => 'pipeline'])) ?>">
                    <i class="bi bi-diagram-3 me-1"></i>Pipeline
                </a>
            </li>
        </ul>

        <!-- Tab content -->
        <div class="card border-0 shadow-sm" style="border-top-left-radius: 0;">
            <!-- ============ PICKUPS TAB ============ -->
            <?php if ($tab === 'pickups'): ?>
                <div class="card-header bg-white border-0 py-2">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0">Status:</label>
                            <div class="btn-group btn-group-sm" role="group">
                                <?php
                                $pickupStatuses = [
                                    '' => 'All',
                                    'requested' => 'Requested',
                                    'assigned' => 'Assigned',
                                    'picked_up' => 'Picked Up',
                                    'cancelled' => 'Cancelled',
                                ];
                                foreach ($pickupStatuses as $s => $label):
                                    $isActive = ($status === $s || ($s === '' && !in_array($status, array_keys($pickupStatuses), true)));
                                    if ($tab !== 'pickups') continue;
                                ?>
                                    <a href="<?= $e($qs(['status' => $s])) ?>"
                                       class="btn btn-outline-secondary <?= $isActive ? 'active' : '' ?>">
                                        <?= $e($label) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    id="bulkAssignBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#bulkAssignModal"
                                    disabled>
                                <i class="bi bi-person-plus me-1"></i>
                                Assign Selected (<span id="selectedCount">0</span>)
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 68vh;">
                    <table class="table table-hover align-middle mb-0" id="pickupsTable">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 2;">
                            <tr>
                                <th style="width: 32px;">
                                    <input type="checkbox" class="form-check-input"
                                           id="selectAllPickups">
                                </th>
                                <th class="small text-muted fw-semibold">Request</th>
                                <th class="small text-muted fw-semibold">Customer</th>
                                <th class="small text-muted fw-semibold">Slot</th>
                                <th class="small text-muted fw-semibold">Area</th>
                                <th class="small text-muted fw-semibold">Staff</th>
                                <th class="small text-muted fw-semibold text-center">Status</th>
                                <th class="small text-muted fw-semibold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pickups)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                        <div class="fw-medium">No pickups scheduled for this date</div>
                                    </td>
                                </tr>
                            <?php else: foreach ($pickups as $p):
                                $pid        = (int) $p['pickup_id'];
                                $pStatus    = (string) $p['status'];
                                $pNumber    = (string) $p['request_number'];
                                $pDate      = (string) $p['pickup_date'];
                                $pSlotStart = (string) ($p['start_time'] ?? '');
                                $pSlotEnd   = (string) ($p['end_time'] ?? '');
                                $custName   = (string) $p['customer_name'];
                                $custPhone  = (string) $p['customer_phone'];
                                $area       = (string) $p['address_area'];
                                $city       = (string) $p['city'];
                                $staffName  = $p['assigned_staff_name'] ?? null;
                                $isOverdue  = false;

                                // Overdue: past date OR same-day past slot end
                                $today = date('Y-m-d');
                                if ($pDate < $today && in_array($pStatus, ['requested', 'assigned'], true)) {
                                    $isOverdue = true;
                                } elseif ($pDate === $today && $pSlotEnd && strtotime($today . ' ' . $pSlotEnd) < time() && in_array($pStatus, ['requested', 'assigned'], true)) {
                                    $isOverdue = true;
                                }

                                $canAssign = in_array($pStatus, ['requested', 'assigned'], true);
                            ?>
                                <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                                    <td>
                                        <?php if ($canAssign): ?>
                                            <input type="checkbox"
                                                   class="form-check-input pickup-checkbox"
                                                   value="<?= $pid ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-medium small">
                                            <?= $e($pNumber) ?>
                                            <?php if ($isOverdue): ?>
                                                <span class="badge bg-danger ms-1"
                                                      style="font-size:.65rem;"
                                                      title="Overdue">
                                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.7rem;">
                                            #<?= $pid ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-truncate"
                                             style="max-width: 200px;">
                                            <?= $e($custName) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.72rem;">
                                            <i class="bi bi-telephone"></i>
                                            <?= $e($custPhone) ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <div>
                                            <i class="bi bi-clock text-muted me-1"></i>
                                            <?= $e($fmtTime($pSlotStart)) ?>–<?= $e($fmtTime($pSlotEnd)) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.72rem;">
                                            <?= $e(date('D', strtotime($pDate))) ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <div><?= $e($area) ?></div>
                                        <div class="text-muted" style="font-size:.72rem;">
                                            <?= $e($city) ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <?php if ($staffName): ?>
                                            <span class="badge bg-info-subtle text-info">
                                                <i class="bi bi-person"></i>
                                                <?= $e($staffName) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning">
                                                Unassigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $pickupBadge($pStatus) ?>">
                                            <?= $e($pickupLabel($pStatus)) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($canAssign): ?>
                                                <button type="button"
                                                        class="btn btn-outline-primary"
                                                        title="Reassign"
                                                        onclick="openReassignModal(<?= $pid ?>, <?= json_encode($pNumber, $jsonFlags) ?>)">
                                                    <i class="bi bi-person-gear"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn btn-outline-warning"
                                                        title="Reschedule"
                                                        onclick="openRescheduleModal(<?= $pid ?>, <?= json_encode($pNumber, $jsonFlags) ?>)">
                                                    <i class="bi bi-calendar-event"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button"
                                                    class="btn btn-outline-secondary dropdown-toggle-split"
                                                    data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item"
                                                       href="/staff/pickups/<?= $pid ?>"
                                                       target="_blank">
                                                        <i class="bi bi-box-arrow-up-right me-2"></i>Open full detail
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item"
                                                       href="tel:<?= $e($custPhone) ?>">
                                                        <i class="bi bi-telephone me-2"></i>Call customer
                                                    </a>
                                                </li>
                                                <?php if ($canAssign): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <button type="button"
                                                                class="dropdown-item text-danger"
                                                                onclick="openForceStatusModal(<?= $pid ?>, <?= json_encode($pNumber, $jsonFlags) ?>, <?= json_encode($pStatus) ?>)">
                                                            <i class="bi bi-shield-exclamation me-2"></i>Force status change
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

            <!-- ============ DELIVERIES TAB ============ -->
            <?php elseif ($tab === 'deliveries'): ?>
                <div class="card-header bg-white border-0 py-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="small text-muted mb-0">Status:</label>
                        <div class="btn-group btn-group-sm" role="group">
                            <?php
                            $deliveryStatuses = [
                                '' => 'All',
                                'scheduled' => 'Scheduled',
                                'out_for_delivery' => 'Out',
                                'delivered' => 'Delivered',
                                'failed' => 'Failed',
                                'rescheduled' => 'Rescheduled',
                            ];
                            foreach ($deliveryStatuses as $s => $label):
                                $isActive = ($status === $s || ($s === '' && !in_array($status, array_keys($deliveryStatuses), true)));
                            ?>
                                <a href="<?= $e($qs(['status' => $s])) ?>"
                                   class="btn btn-outline-secondary <?= $isActive ? 'active' : '' ?>">
                                    <?= $e($label) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 68vh;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 2;">
                            <tr>
                                <th class="small text-muted fw-semibold">Order</th>
                                <th class="small text-muted fw-semibold">Customer</th>
                                <th class="small text-muted fw-semibold">Slot</th>
                                <th class="small text-muted fw-semibold">Area</th>
                                <th class="small text-muted fw-semibold">Staff</th>
                                <th class="small text-muted fw-semibold text-center">Status</th>
                                <th class="small text-muted fw-semibold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deliveries)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                        <div class="fw-medium">No deliveries scheduled for this date</div>
                                    </td>
                                </tr>
                            <?php else: foreach ($deliveries as $d):
                                $did         = (int) $d['delivery_id'];
                                $dStatus     = (string) $d['status'];
                                $dOrder      = (string) $d['order_number'];
                                $dDate       = (string) $d['delivery_date'];
                                $dSlotStart  = (string) ($d['start_time'] ?? '');
                                $dSlotEnd    = (string) ($d['end_time'] ?? '');
                                $custName    = (string) $d['customer_name'];
                                $custPhone   = (string) $d['customer_phone'];
                                $area        = (string) $d['address_area'];
                                $city        = (string) $d['city'];
                                $staffName   = $d['assigned_staff_name'] ?? null;

                                $isOverdue = false;
                                $today = date('Y-m-d');
                                if ($dDate < $today && in_array($dStatus, ['scheduled', 'out_for_delivery'], true)) {
                                    $isOverdue = true;
                                } elseif ($dDate === $today && $dSlotEnd && strtotime($today . ' ' . $dSlotEnd) < time() && in_array($dStatus, ['scheduled', 'out_for_delivery'], true)) {
                                    $isOverdue = true;
                                }
                            ?>
                                <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                                    <td>
                                        <div class="fw-medium small">
                                            <?= $e($dOrder) ?>
                                            <?php if ($isOverdue): ?>
                                                <span class="badge bg-danger ms-1"
                                                      style="font-size:.65rem;"
                                                      title="Overdue">
                                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.7rem;">
                                            #<?= $did ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-medium text-truncate"
                                             style="max-width: 200px;">
                                            <?= $e($custName) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.72rem;">
                                            <i class="bi bi-telephone"></i>
                                            <?= $e($custPhone) ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <div>
                                            <i class="bi bi-clock text-muted me-1"></i>
                                            <?= $e($fmtTime($dSlotStart)) ?>–<?= $e($fmtTime($dSlotEnd)) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.72rem;">
                                            <?= $e(date('D', strtotime($dDate))) ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <div><?= $e($area) ?></div>
                                        <div class="text-muted" style="font-size:.72rem;">
                                            <?= $e($city) ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <?php if ($staffName): ?>
                                            <span class="badge bg-info-subtle text-info">
                                                <i class="bi bi-person"></i>
                                                <?= $e($staffName) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning">
                                                Unassigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $deliveryBadge($dStatus) ?>">
                                            <?= $e($deliveryLabel($dStatus)) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($dStatus !== 'delivered'): ?>
                                                <button type="button"
                                                        class="btn btn-outline-primary"
                                                        title="Reassign"
                                                        onclick="openReassignDeliveryModal(<?= $did ?>, <?= json_encode($dOrder, $jsonFlags) ?>)">
                                                    <i class="bi bi-person-gear"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button"
                                                    class="btn btn-outline-secondary dropdown-toggle-split"
                                                    data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item"
                                                       href="/staff/deliveries/<?= $did ?>"
                                                       target="_blank">
                                                        <i class="bi bi-box-arrow-up-right me-2"></i>Open full detail
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item"
                                                       href="tel:<?= $e($custPhone) ?>">
                                                        <i class="bi bi-telephone me-2"></i>Call customer
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

            <!-- ============ PIPELINE TAB ============ -->
            <?php else: ?>
                <div class="card-body">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-diagram-3 fs-1 d-block mb-2 opacity-50"></i>
                        <div class="fw-medium">Pipeline view</div>
                        <div class="small">
                            Orders currently in progress would appear here.
                            <br>
                            <a href="/admin/reports/orders" class="text-decoration-none">
                                View order reports →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================== HOURLY TIMELINE ==================== -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bar-chart me-2 text-primary"></i>Hourly Distribution
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline-chart">
                    <?php for ($h = 0; $h < 24; $h++):
                        $pVal = (int) ($pickupTimeline[$h] ?? 0);
                        $dVal = (int) ($deliveryTimeline[$h] ?? 0);
                        $pH = $pVal > 0 ? max(6, round(($pVal / $timelineMax) * 60)) : 0;
                        $dH = $dVal > 0 ? max(6, round(($dVal / $timelineMax) * 60)) : 0;
                    ?>
                        <div class="timeline-bar" title="<?= sprintf('%02d:00 — %d pickup(s), %d delivery(ies)', $h, $pVal, $dVal) ?>">
                            <div class="timeline-bars">
                                <div class="timeline-bar-pickup" style="height: <?= $pH ?>%"></div>
                                <div class="timeline-bar-delivery" style="height: <?= $dH ?>%"></div>
                            </div>
                            <div class="timeline-label"><?= $h ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="d-flex justify-content-center gap-3 mt-2 small text-muted">
                    <span><span class="legend-dot bg-primary"></span> Pickups</span>
                    <span><span class="legend-dot bg-success"></span> Deliveries</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== RIGHT: STAFF ON DUTY ==================== -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-people me-2 text-primary"></i>Staff on Duty
                </h6>
                <span class="badge bg-primary-subtle text-primary">
                    <?= count($onDuty) ?>
                </span>
            </div>
            <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                <?php if (empty($onDuty)): ?>
                    <div class="p-3 text-muted small text-center">
                        No active staff.
                    </div>
                <?php else: foreach ($onDuty as $s):
                    $sid      = (int) $s['staff_id'];
                    $sName    = (string) $s['full_name'];
                    $sRole    = (string) $s['role'];
                    $picks    = (int) $s['pickups_today'];
                    $delivs   = (int) $s['deliveries_today'];
                    $load     = $picks + $delivs;
                ?>
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-xs bg-<?= $sRole === 'admin' ? 'danger' : 'primary' ?>-subtle text-<?= $sRole === 'admin' ? 'danger' : 'primary' ?>">
                                <?= $e(strtoupper(substr($sName, 0, 1))) ?>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="small fw-medium text-truncate">
                                    <?= $e($sName) ?>
                                </div>
                                <div class="text-muted" style="font-size:.7rem;">
                                    <?= $e(ucfirst($sRole)) ?>
                                </div>
                            </div>
                            <?php if ($load > 0): ?>
                                <span class="badge bg-<?= $load >= 5 ? 'warning' : 'info' ?>">
                                    <?= $load ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">0</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($load > 0): ?>
                            <div class="d-flex gap-2 mt-2 small text-muted" style="font-size:.72rem;">
                                <span>
                                    <i class="bi bi-box-seam text-primary"></i>
                                    <?= $picks ?> pickups
                                </span>
                                <span>
                                    <i class="bi bi-truck text-success"></i>
                                    <?= $delivs ?> deliveries
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Quick links -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-link-45deg me-2 text-primary"></i>Quick Links
                </h6>
            </div>
            <div class="list-group list-group-flush">
                <a href="/admin/complaints" class="list-group-item list-group-item-action small">
                    <i class="bi bi-exclamation-circle me-2 text-warning"></i>Complaints
                </a>
                <a href="/admin/refunds" class="list-group-item list-group-item-action small">
                    <i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>Refunds
                </a>
                <a href="/admin/staff/performance-overview" class="list-group-item list-group-item-action small">
                    <i class="bi bi-graph-up me-2 text-info"></i>Staff Performance
                </a>
                <a href="/admin/reports/sales" class="list-group-item list-group-item-action small">
                    <i class="bi bi-cash-coin me-2 text-success"></i>Sales Report
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ==================== BULK ASSIGN MODAL ==================== -->
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Pickups</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Assign <strong><span id="bulkCount">0</span></strong> pickup(s) to a staff member:
                </p>
                <select class="form-select" id="bulkStaffSelect">
                    <option value="">— Select staff —</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['staff_id'] ?>">
                            <?= $e($s['full_name']) ?>
                            (<?= $e(ucfirst($s['role'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="bulkAssignConfirm">
                    <i class="bi bi-person-plus me-1"></i>Assign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REASSIGN MODAL ==================== -->
<div class="modal fade" id="reassignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reassign Pickup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reassign <strong id="reassignLabel"></strong> to a different staff member:
                </p>
                <select class="form-select" id="reassignStaffSelect">
                    <option value="">— Select staff —</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['staff_id'] ?>">
                            <?= $e($s['full_name']) ?>
                            (<?= $e(ucfirst($s['role'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="reassignConfirm">
                    <i class="bi bi-arrow-repeat me-1"></i>Reassign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RESCHEDULE MODAL ==================== -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reschedule Pickup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reschedule <strong id="rescheduleLabel"></strong>:
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">New Date</label>
                    <input type="date" class="form-control" id="rescheduleDate"
                           min="<?= date('Y-m-d') ?>"
                           value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-medium">New Slot</label>
                    <select class="form-select" id="rescheduleSlot">
                        <option value="">— Loading slots… —</option>
                    </select>
                    <div class="form-text small">
                        Slots are filtered to the selected day of week.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="rescheduleConfirm">
                    <i class="bi bi-calendar-event me-1"></i>Reschedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FORCE STATUS MODAL ==================== -->
<div class="modal fade" id="forceStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-shield-exclamation me-2"></i>Force Status Change
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <strong>Admin override.</strong> This bypasses the normal workflow and is logged.
                </div>
                <p class="small mb-2">
                    Force status change for <strong id="forceLabel"></strong>.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">
                        New Status <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="forceStatusSelect">
                        <option value="requested">Requested</option>
                        <option value="assigned">Assigned</option>
                        <option value="picked_up">Picked Up</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-medium">
                        Reason <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="forceReason" rows="3"
                              placeholder="Why is this override needed? (min 5 characters)"></textarea>
                    <div class="invalid-feedback" id="forceReasonError"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="forceConfirm">
                    <i class="bi bi-exclamation-triangle me-1"></i>Force Change
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== DELIVERY REASSIGN MODAL ==================== -->
<div class="modal fade" id="deliveryReassignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reassign Delivery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reassign <strong id="deliveryReassignLabel"></strong> to a different staff member:
                </p>
                <select class="form-select" id="deliveryReassignStaffSelect">
                    <option value="">— Select staff —</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['staff_id'] ?>">
                            <?= $e($s['full_name']) ?>
                            (<?= $e(ucfirst($s['role'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="deliveryReassignConfirm">
                    <i class="bi bi-arrow-repeat me-1"></i>Reassign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .stat-mini {
        padding: .5rem .25rem;
        border-radius: 6px;
    }
    .stat-mini .fw-bold {
        font-size: 1.1rem;
        line-height: 1;
    }
    .stat-mini .small {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .02em;
        opacity: .8;
    }
    .avatar-xs {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .8rem;
        flex-shrink: 0;
    }
    .min-w-0 { min-width: 0; }

    .nav-tabs .nav-link {
        color: #475569;
        border: 1px solid transparent;
        border-bottom: none;
    }
    .nav-tabs .nav-link.active {
        background: #fff;
        color: #0ea5e9;
        font-weight: 500;
        border-color: #e2e8f0 #e2e8f0 #fff;
    }
    .nav-tabs .nav-link:hover:not(.active) {
        background: #f8fafc;
    }

    .table > :not(caption) > * > * {
        padding: .7rem .6rem;
        vertical-align: middle;
    }

    /* Hourly timeline */
    .timeline-chart {
        display: flex;
        gap: 4px;
        align-items: flex-end;
        height: 80px;
        padding: 4px;
        background: #f8fafc;
        border-radius: 8px;
    }
    .timeline-bar {
        flex: 1;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        position: relative;
    }
    .timeline-bars {
        width: 100%;
        height: 100%;
        display: flex;
        gap: 1px;
        align-items: flex-end;
        justify-content: center;
    }
    .timeline-bar-pickup {
        width: 45%;
        background: #0ea5e9;
        border-radius: 2px 2px 0 0;
        transition: height .2s;
    }
    .timeline-bar-delivery {
        width: 45%;
        background: #22c55e;
        border-radius: 2px 2px 0 0;
        transition: height .2s;
    }
    .timeline-label {
        position: absolute;
        bottom: -18px;
        font-size: .62rem;
        color: #94a3b8;
    }
    .timeline-bar:nth-child(even) .timeline-label {
        display: none;
    }
    .legend-dot {
        display: inline-block;
        width: 10px; height: 10px;
        border-radius: 50%;
        margin-right: 3px;
        vertical-align: middle;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ---------- Selected pickups for bulk assign ----------
    const selectedIds = new Set();
    const selectAll   = document.getElementById('selectAllPickups');
    const bulkBtn     = document.getElementById('bulkAssignBtn');
    const selectedCnt = document.getElementById('selectedCount');

    function refreshBulkSelection() {
        if (!bulkBtn || !selectedCnt) return;
        selectedCnt.textContent = selectedIds.size;
        bulkBtn.disabled = selectedIds.size === 0;
    }

    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('pickup-checkbox')) {
            const id = parseInt(e.target.value, 10);
            if (e.target.checked) selectedIds.add(id);
            else selectedIds.delete(id);
            refreshBulkSelection();
        }
        if (e.target === selectAll) {
            const checkboxes = document.querySelectorAll('.pickup-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                const id = parseInt(cb.value, 10);
                if (selectAll.checked) selectedIds.add(id);
                else selectedIds.delete(id);
            });
            refreshBulkSelection();
        }
    });

    // ---------- Bulk assign ----------
    const bulkModal    = new bootstrap.Modal(document.getElementById('bulkAssignModal'));
    const bulkSelect   = document.getElementById('bulkStaffSelect');
    const bulkConfirm  = document.getElementById('bulkAssignConfirm');
    const bulkCountEl  = document.getElementById('bulkCount');

    if (bulkBtn) {
        bulkBtn.addEventListener('click', () => {
            bulkCountEl.textContent = selectedIds.size;
            bulkSelect.value = '';
            bulkModal.show();
        });
    }

    bulkConfirm?.addEventListener('click', async () => {
        const staffId = parseInt(bulkSelect.value, 10);
        if (!staffId) {
            showToast('Please select a staff member.', 'danger');
            return;
        }
        if (selectedIds.size === 0) {
            showToast('No pickups selected.', 'danger');
            return;
        }

        bulkConfirm.disabled = true;
        const orig = bulkConfirm.innerHTML;
        bulkConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Assigning…';

        try {
            const body = new URLSearchParams();
            body.append('_csrf', csrf);
            body.append('staff_id', staffId);
            selectedIds.forEach(id => body.append('pickup_ids[]', id));

            const res = await fetch('/admin/pickups/monitor/bulk-assign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Pickups assigned.', 'success');
                bulkModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed to assign.', 'danger');
                bulkConfirm.disabled = false;
                bulkConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            bulkConfirm.disabled = false;
            bulkConfirm.innerHTML = orig;
        }
    });

    // ---------- Reassign ----------
    const reassignModal = new bootstrap.Modal(document.getElementById('reassignModal'));
    const reassignLabel = document.getElementById('reassignLabel');
    const reassignSel   = document.getElementById('reassignStaffSelect');
    const reassignConf  = document.getElementById('reassignConfirm');
    let reassignTargetId = 0;

    window.openReassignModal = function (pickupId, label) {
        reassignTargetId = pickupId;
        reassignLabel.textContent = label;
        reassignSel.value = '';
        reassignModal.show();
    };

    reassignConf?.addEventListener('click', async () => {
        const staffId = parseInt(reassignSel.value, 10);
        if (!staffId) {
            showToast('Please select a staff member.', 'danger');
            return;
        }
        reassignConf.disabled = true;
        const orig = reassignConf.innerHTML;
        reassignConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Reassigning…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, staff_id: staffId });
            const res = await fetch('/admin/pickups/monitor/' + reassignTargetId + '/reassign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Reassigned.', 'success');
                reassignModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed to reassign.', 'danger');
                reassignConf.disabled = false;
                reassignConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            reassignConf.disabled = false;
            reassignConf.innerHTML = orig;
        }
    });

    // ---------- Delivery reassign ----------
    const deliveryReassignModal = new bootstrap.Modal(document.getElementById('deliveryReassignModal'));
    const deliveryReassignLabel = document.getElementById('deliveryReassignLabel');
    const deliveryReassignSel   = document.getElementById('deliveryReassignStaffSelect');
    const deliveryReassignConf  = document.getElementById('deliveryReassignConfirm');
    let deliveryReassignTargetId = 0;

    window.openReassignDeliveryModal = function (deliveryId, label) {
        deliveryReassignTargetId = deliveryId;
        deliveryReassignLabel.textContent = label;
        deliveryReassignSel.value = '';
        deliveryReassignModal.show();
    };

    deliveryReassignConf?.addEventListener('click', async () => {
        const staffId = parseInt(deliveryReassignSel.value, 10);
        if (!staffId) {
            showToast('Please select a staff member.', 'danger');
            return;
        }
        deliveryReassignConf.disabled = true;
        const orig = deliveryReassignConf.innerHTML;
        deliveryReassignConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Reassigning…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, staff_id: staffId });
            const res = await fetch('/admin/pickups/monitor/deliveries/' + deliveryReassignTargetId + '/reassign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Reassigned.', 'success');
                deliveryReassignModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                deliveryReassignConf.disabled = false;
                deliveryReassignConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            deliveryReassignConf.disabled = false;
            deliveryReassignConf.innerHTML = orig;
        }
    });

    // ---------- Reschedule ----------
    const rescheduleModal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
    const rescheduleLabel = document.getElementById('rescheduleLabel');
    const rescheduleDate  = document.getElementById('rescheduleDate');
    const rescheduleSlot  = document.getElementById('rescheduleSlot');
    const rescheduleConf  = document.getElementById('rescheduleConfirm');
    let rescheduleTargetId = 0;

    window.openRescheduleModal = function (pickupId, label) {
        rescheduleTargetId = pickupId;
        rescheduleLabel.textContent = label;
        rescheduleDate.value = '<?= date('Y-m-d') ?>';
        rescheduleSlot.innerHTML = '<option value="">— Loading slots… —</option>';
        rescheduleModal.show();
        loadSlots();
    };

    async function loadSlots() {
        const date = rescheduleDate.value;
        try {
            const res = await fetch('/admin/pickups/monitor/slots?date=' + encodeURIComponent(date));
            const json = await res.json();
            if (json.success && Array.isArray(json.data)) {
                if (json.data.length === 0) {
                    rescheduleSlot.innerHTML = '<option value="">No slots for this day</option>';
                    return;
                }
                rescheduleSlot.innerHTML = '<option value="">— Select slot —</option>' +
                    json.data.map(s =>
                        `<option value="${s.slot_id}">${s.label}</option>`
                    ).join('');
            } else {
                rescheduleSlot.innerHTML = '<option value="">No slots</option>';
            }
        } catch (err) {
            rescheduleSlot.innerHTML = '<option value="">Error loading slots</option>';
        }
    }

    rescheduleDate?.addEventListener('change', loadSlots);

    rescheduleConf?.addEventListener('click', async () => {
        const newDate = rescheduleDate.value;
        const newSlot = parseInt(rescheduleSlot.value, 10);
        if (!newDate || !newSlot) {
            showToast('Please pick a date and slot.', 'danger');
            return;
        }
        rescheduleConf.disabled = true;
        const orig = rescheduleConf.innerHTML;
        rescheduleConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rescheduling…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                new_date: newDate,
                new_slot_id: newSlot,
            });
            const res = await fetch('/admin/pickups/monitor/' + rescheduleTargetId + '/reschedule', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Rescheduled.', 'success');
                rescheduleModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                rescheduleConf.disabled = false;
                rescheduleConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            rescheduleConf.disabled = false;
            rescheduleConf.innerHTML = orig;
        }
    });

    // ---------- Force status ----------
    const forceModal  = new bootstrap.Modal(document.getElementById('forceStatusModal'));
    const forceLabel  = document.getElementById('forceLabel');
    const forceSel    = document.getElementById('forceStatusSelect');
    const forceReason = document.getElementById('forceReason');
    const forceErr    = document.getElementById('forceReasonError');
    const forceConf   = document.getElementById('forceConfirm');
    let forceTargetId = 0;

    window.openForceStatusModal = function (pickupId, label, currentStatus) {
        forceTargetId = pickupId;
        forceLabel.textContent = label;
        forceSel.value = currentStatus || 'requested';
        forceReason.value = '';
        forceErr.style.display = 'none';
        forceModal.show();
    };

    forceConf?.addEventListener('click', async () => {
        const newStatus = forceSel.value;
        const reason = forceReason.value.trim();

        if (reason.length < 5) {
            forceErr.textContent = 'Reason must be at least 5 characters.';
            forceErr.style.display = 'block';
            return;
        }

        forceConf.disabled = true;
        const orig = forceConf.innerHTML;
        forceConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                status: newStatus,
                reason: reason,
            });
            const res = await fetch('/admin/pickups/monitor/' + forceTargetId + '/force-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Status overridden.', 'success');
                forceModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                forceConf.disabled = false;
                forceConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            forceConf.disabled = false;
            forceConf.innerHTML = orig;
        }
    });

    // ---------- Auto-refresh ----------
    const autoRefreshToggle = document.getElementById('autoRefresh');
    const refreshBtn        = document.getElementById('refreshNowBtn');
    const lastRefreshLabel  = document.getElementById('lastRefreshLabel');
    let refreshTimer = null;

    function updateRefreshLabel() {
        if (lastRefreshLabel) lastRefreshLabel.textContent = '(15s)';
    }

    function startAutoRefresh() {
        stopAutoRefresh();
        refreshTimer = setInterval(() => {
            location.reload();
        }, 15000);
    }

    function stopAutoRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = null;
    }

    if (autoRefreshToggle) {
        autoRefreshToggle.addEventListener('change', () => {
            if (autoRefreshToggle.checked) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        if (autoRefreshToggle.checked) startAutoRefresh();
    }

    refreshBtn?.addEventListener('click', () => location.reload());

    // Warn about unsaved actions when navigating
    window.addEventListener('beforeunload', () => stopAutoRefresh());

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
        new bootstrap.Toast(toast, { delay: 3500 }).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }
})();
</script>
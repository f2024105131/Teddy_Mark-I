<?php
/**
 * Staff Pickups — List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: App\Controllers\Staff\PickupController::index()
 *
 * Expected variables:
 *   $pickups    array   paginated pickup rows with customer/slot/staff joins
 *   $stats      array   ['today_pending','today_completed','upcoming','today_failed']
 *   $filter     string  'active' | 'today' | 'all' | 'requested' | 'assigned' | 'picked_up' | 'cancelled'
 * ----------------------------------------------------------
 */

$currentPage = 'staff-pickups';
$title       = 'My Pickups';

// Safe defaults
$pickups = $pickups ?? [];
$stats   = $stats   ?? ['today_pending'=>0, 'today_completed'=>0, 'upcoming'=>0, 'today_failed'=>0];
$filter  = $filter  ?? 'active';

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateShort = fn($d) => $d ? date('M j', strtotime($d)) : '—';
$fmtTime  = fn($t) => $t ? substr($t, 0, 5) : '';
$fmtDateTime = fn($d) => $d ? date('M j, g:i A', strtotime($d)) : '—';

// Relative time
$timeAgo = function (?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    if (!$ts) return '—';
    $diff = time() - $ts;
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
};

// Status badges
$statusBadge = function (string $s): string {
    return match ($s) {
        'requested' => 'warning',
        'assigned'  => 'info',
        'picked_up' => 'success',
        'cancelled' => 'danger',
        default     => 'secondary',
    };
};
$statusLabel = function (string $s): string {
    return match ($s) {
        'requested' => 'Requested',
        'assigned'  => 'Assigned',
        'picked_up' => 'Picked Up',
        'cancelled' => 'Cancelled',
        default     => ucwords(str_replace('_', ' ', $s)),
    };
};

// Overdue detection
$isOverdue = function (array $p): bool {
    $status = (string) ($p['status'] ?? '');
    if (in_array($status, ['picked_up', 'cancelled'], true)) return false;

    $date    = (string) ($p['pickup_date'] ?? '');
    $endTime = (string) ($p['end_time']    ?? '');
    $today   = date('Y-m-d');

    if ($date < $today) return true;
    if ($date === $today && $endTime && strtotime($today . ' ' . $endTime) < time()) {
        return true;
    }
    return false;
};

// Tabs
$tabs = [
    'active'    => ['label' => 'Active',    'count' => ($stats['today_pending'] ?? 0) + ($stats['upcoming'] ?? 0), 'icon' => 'bi-list-check'],
    'today'     => ['label' => 'Today',     'count' => ($stats['today_pending'] ?? 0) + ($stats['today_completed'] ?? 0), 'icon' => 'bi-calendar-day'],
    'upcoming'  => ['label' => 'Upcoming',  'count' => $stats['upcoming'] ?? 0, 'icon' => 'bi-calendar-event'],
    'all'       => ['label' => 'All',       'count' => null, 'icon' => 'bi-list-ul'],
];

// Build querystring
$qs = function (string $status): string {
    if ($status === 'active') return '/staff/pickups';
    return '/staff/pickups?status=' . urlencode($status);
};

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">My Pickups</h1>
        <p class="text-muted mb-0 small">
            View and manage your scheduled pickups
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/staff/pickups?status=all" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i>View All
        </a>
    </div>
</div>

<!-- ==================== KPI CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pending Today</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['today_pending'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            need pickup
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
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Completed Today</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['today_completed'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            picked up
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
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Upcoming</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['upcoming'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            future dates
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
                    <div class="kpi-icon bg-danger-subtle text-danger">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Failed Today</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['today_failed'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            cancelled pickups
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FILTER TABS ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-body pb-0">
        <ul class="nav nav-tabs">
            <?php foreach ($tabs as $slug => $tab):
                $isActive = ($filter === $slug);
                $count = $tab['count'];
            ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive ? 'active' : '' ?>"
                       href="<?= $e($qs($slug)) ?>">
                        <i class="bi <?= $e($tab['icon']) ?> me-1"></i>
                        <?= $e($tab['label']) ?>
                        <?php if ($count !== null && $count > 0): ?>
                            <span class="badge bg-primary-subtle text-primary ms-1">
                                <?= $fmtInt($count) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ==================== PICKUPS LIST ==================== -->
    <div class="card-body p-0">
        <?php if (empty($pickups)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-box-seam fs-1 d-block mb-3 opacity-50"></i>
                <div class="fw-medium">
                    <?php if ($filter === 'active'): ?>
                        You have no active pickups
                    <?php elseif ($filter === 'today'): ?>
                        No pickups scheduled for today
                    <?php elseif ($filter === 'upcoming'): ?>
                        No upcoming pickups
                    <?php else: ?>
                        No pickups found
                    <?php endif; ?>
                </div>
                <div class="small mt-2">
                    New pickups will appear here when assigned to you.
                </div>
            </div>
        <?php else: ?>
            <div class="pickups-list">
                <?php foreach ($pickups as $p):
                    $pid        = (int) $p['pickup_id'];
                    $pStatus    = (string) $p['status'];
                    $pNumber    = (string) $p['request_number'];
                    $pDate      = (string) $p['pickup_date'];
                    $pSlotStart = (string) ($p['start_time'] ?? '');
                    $pSlotEnd   = (string) ($p['end_time']   ?? '');
                    $custName   = (string) ($p['customer_name']  ?? '');
                    $custPhone  = (string) ($p['customer_phone'] ?? '');
                    $area       = (string) ($p['address_area'] ?? '');
                    $city       = (string) ($p['city'] ?? '');
                    $house      = (string) ($p['address_house']  ?? '');
                    $street     = (string) ($p['address_street'] ?? '');
                    $landmark   = (string) ($p['landmark'] ?? '');
                    $notes      = (string) ($p['special_instructions'] ?? '');
                    $orderId    = (int) ($p['order_id'] ?? 0);
                    $orderNum   = (string) ($p['order_number'] ?? '');
                    $isSameDay  = (int) ($p['is_same_day'] ?? 0);
                    $isToday    = ($pDate === date('Y-m-d'));
                    $isTomorrow = ($pDate === date('Y-m-d', strtotime('+1 day')));
                    $overdue    = $isOverdue($p);

                    // Status-based actions
                    $canPickup = in_array($pStatus, ['requested', 'assigned'], true);
                    $canReschedule = in_array($pStatus, ['requested', 'assigned'], true);
                    $canCancel = in_array($pStatus, ['requested', 'assigned'], true);
                ?>
                    <div class="pickup-card <?= $overdue ? 'pickup-overdue' : '' ?> <?= $isToday ? 'pickup-today' : '' ?>">
                        <div class="row g-3 align-items-center">
                            <!-- Left: date + slot -->
                            <div class="col-auto">
                                <div class="pickup-date-block">
                                    <div class="pickup-date-month">
                                        <?= $e(date('M', strtotime($pDate))) ?>
                                    </div>
                                    <div class="pickup-date-day">
                                        <?= $e(date('d', strtotime($pDate))) ?>
                                    </div>
                                    <div class="pickup-date-weekday">
                                        <?= $e(date('D', strtotime($pDate))) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Middle: details -->
                            <div class="col">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="fw-semibold"><?= $e($pNumber) ?></span>
                                    <span class="badge bg-<?= $statusBadge($pStatus) ?>">
                                        <?= $e($statusLabel($pStatus)) ?>
                                    </span>
                                    <?php if ($isSameDay): ?>
                                        <span class="badge bg-warning-subtle text-warning"
                                              style="font-size:.65rem;"
                                              title="Same-day pickup">
                                            <i class="bi bi-lightning-charge-fill"></i> Same Day
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($overdue): ?>
                                        <span class="badge bg-danger" style="font-size:.65rem;">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Overdue
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isToday): ?>
                                        <span class="badge bg-primary" style="font-size:.65rem;">
                                            Today
                                        </span>
                                    <?php elseif ($isTomorrow): ?>
                                        <span class="badge bg-info" style="font-size:.65rem;">
                                            Tomorrow
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex flex-wrap gap-3 small text-muted mb-2">
                                    <span>
                                        <i class="bi bi-clock me-1"></i>
                                        <?= $e($fmtTime($pSlotStart)) ?>–<?= $e($fmtTime($pSlotEnd)) ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-person me-1"></i>
                                        <?= $e($custName) ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-telephone me-1"></i>
                                        <a href="tel:<?= $e($custPhone) ?>"
                                           class="text-decoration-none text-muted">
                                            <?= $e($custPhone) ?>
                                        </a>
                                    </span>
                                </div>

                                <div class="small">
                                    <i class="bi bi-geo-alt text-muted me-1"></i>
                                    <?= $e($house) ?>, <?= $e($street) ?>,
                                    <?= $e($area) ?>, <?= $e($city) ?>
                                    <?php if ($landmark !== ''): ?>
                                        <span class="text-muted">(near <?= $e($landmark) ?>)</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($notes !== ''): ?>
                                    <div class="small text-muted mt-1" style="font-size:.78rem;">
                                        <i class="bi bi-sticky me-1"></i><?= $e($notes) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($orderNum): ?>
                                    <div class="small mt-2">
                                        <a href="/staff/orders/<?= $orderId ?>"
                                           class="text-decoration-none small">
                                            <i class="bi bi-bag-check me-1"></i>Order <?= $e($orderNum) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Right: actions -->
                            <div class="col-md-auto">
                                <div class="d-flex flex-md-column gap-2">
                                    <?php if ($canPickup): ?>
                                        <button type="button"
                                                class="btn btn-success btn-sm"
                                                onclick="openPickedUpModal(<?= $pid ?>, <?= json_encode($pNumber, $jsonFlags) ?>, <?= json_encode($custName, $jsonFlags) ?>)">
                                            <i class="bi bi-check-lg me-1"></i>Picked Up
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-warning btn-sm"
                                                onclick="openRescheduleModal(<?= $pid ?>, <?= json_encode($pNumber, $jsonFlags) ?>, <?= json_encode($pDate) ?>)">
                                            <i class="bi bi-calendar-event me-1"></i>Reschedule
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="openFailedModal(<?= $pid ?>, <?= json_encode($pNumber, $jsonFlags) ?>, <?= json_encode($custName, $jsonFlags) ?>)">
                                            <i class="bi bi-x-circle me-1"></i>Failed
                                        </button>
                                    <?php else: ?>
                                        <a href="/staff/pickups/<?= $pid ?>"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== PICKED UP MODAL ==================== -->
<div class="modal fade" id="pickedUpModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle me-2"></i>Confirm Pickup
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Mark <strong id="pickedUpNumber"></strong> as picked up from
                    <strong id="pickedUpCustomer"></strong>?
                </p>
                <div class="alert alert-info small mb-3 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    This will advance the order to <strong>Received at Laundry</strong>.
                </div>
                <label class="form-label small fw-medium">
                    Notes <span class="text-muted fw-normal">(optional)</span>
                </label>
                <textarea class="form-control" id="pickedUpNotes" rows="2"
                          placeholder="Number of bags, condition, etc…"
                          maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="pickedUpConfirm">
                    <i class="bi bi-check-lg me-1"></i>Confirm Pickup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FAILED MODAL ==================== -->
<div class="modal fade" id="failedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-x-circle me-2"></i>Mark Pickup as Failed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3 py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>This will cancel the pickup.</strong> Customer will be notified.
                </div>
                <p class="small mb-3">
                    Pickup <strong id="failedNumber"></strong> from
                    <strong id="failedCustomer"></strong>
                </p>
                <label class="form-label small fw-medium">
                    Reason <span class="text-danger">*</span>
                </label>
                <select class="form-select form-select-sm mb-2" id="failedReasonSelect">
                    <option value="">— Select a reason —</option>
                    <option value="Customer not available">Customer not available</option>
                    <option value="Address not found">Address not found</option>
                    <option value="Customer requested cancellation">Customer requested cancellation</option>
                    <option value="No items to pickup">No items to pickup</option>
                    <option value="Access denied">Access denied</option>
                    <option value="other">Other (specify below)</option>
                </select>
                <textarea class="form-control" id="failedReason" rows="2"
                          placeholder="Additional details (required if 'Other')…"
                          maxlength="500"></textarea>
                <div class="invalid-feedback" id="failedError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="failedConfirm">
                    <i class="bi bi-x-circle me-1"></i>Mark as Failed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RESCHEDULE MODAL ==================== -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-event me-2"></i>Reschedule Pickup
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reschedule <strong id="rescheduleNumber"></strong>:
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">New Date</label>
                    <input type="date" class="form-control" id="rescheduleDate"
                           min="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-medium">New Slot</label>
                    <select class="form-select" id="rescheduleSlot">
                        <option value="">— Select a slot —</option>
                    </select>
                    <div class="form-text small">
                        Slots are filtered by the selected day of week.
                    </div>
                </div>
                <div class="invalid-feedback d-block" id="rescheduleError" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="rescheduleConfirm">
                    <i class="bi bi-calendar-check me-1"></i>Reschedule
                </button>
            </div>
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
    .nav-tabs .nav-link {
        color: #475569;
        border: 1px solid transparent;
        border-bottom: none;
        padding: .55rem .9rem;
        font-size: .9rem;
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

    /* Pickup card */
    .pickups-list {
        padding: 0;
    }
    .pickup-card {
        padding: 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background-color .12s;
    }
    .pickup-card:last-child {
        border-bottom: none;
    }
    .pickup-card:hover {
        background: #f8fafc;
    }
    .pickup-card.pickup-today {
        border-left: 3px solid #0ea5e9;
    }
    .pickup-card.pickup-overdue {
        background: #fef2f2;
        border-left: 3px solid #ef4444;
    }
    .pickup-card.pickup-overdue:hover {
        background: #fee2e2;
    }

    /* Date block */
    .pickup-date-block {
        width: 68px;
        padding: .5rem 0;
        background: #f8fafc;
        border-radius: 10px;
        text-align: center;
        border: 1px solid #e2e8f0;
    }
    .pickup-date-month {
        font-size: .7rem;
        text-transform: uppercase;
        color: #94a3b8;
        font-weight: 600;
        letter-spacing: .05em;
    }
    .pickup-date-day {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1;
        margin: .1rem 0;
    }
    .pickup-date-weekday {
        font-size: .7rem;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 500;
    }

    .form-control.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ==================== PICKED UP ====================
    const pickedUpModal    = new bootstrap.Modal(document.getElementById('pickedUpModal'));
    const pickedUpNumber   = document.getElementById('pickedUpNumber');
    const pickedUpCustomer = document.getElementById('pickedUpCustomer');
    const pickedUpNotes    = document.getElementById('pickedUpNotes');
    const pickedUpConfirm  = document.getElementById('pickedUpConfirm');
    let pickedUpTargetId = 0;

    window.openPickedUpModal = function (id, number, customer) {
        pickedUpTargetId = id;
        pickedUpNumber.textContent = number;
        pickedUpCustomer.textContent = customer;
        pickedUpNotes.value = '';
        pickedUpModal.show();
    };

    pickedUpConfirm?.addEventListener('click', async () => {
        pickedUpConfirm.disabled = true;
        const orig = pickedUpConfirm.innerHTML;
        pickedUpConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirming…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                notes: pickedUpNotes.value.trim(),
            });

            const res = await fetch(`/staff/pickups/${pickedUpTargetId}/mark-picked-up`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Pickup confirmed.', 'success');
                pickedUpModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                pickedUpConfirm.disabled = false;
                pickedUpConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            pickedUpConfirm.disabled = false;
            pickedUpConfirm.innerHTML = orig;
        }
    });

    // ==================== FAILED ====================
    const failedModal        = new bootstrap.Modal(document.getElementById('failedModal'));
    const failedNumber       = document.getElementById('failedNumber');
    const failedCustomer     = document.getElementById('failedCustomer');
    const failedReasonSelect = document.getElementById('failedReasonSelect');
    const failedReason       = document.getElementById('failedReason');
    const failedError        = document.getElementById('failedError');
    const failedConfirm      = document.getElementById('failedConfirm');
    let failedTargetId = 0;

    window.openFailedModal = function (id, number, customer) {
        failedTargetId = id;
        failedNumber.textContent = number;
        failedCustomer.textContent = customer;
        failedReasonSelect.value = '';
        failedReason.value = '';
        failedError.style.display = 'none';
        failedModal.show();
    };

    failedConfirm?.addEventListener('click', async () => {
        let selectedReason = failedReasonSelect.value;
        const customReason = failedReason.value.trim();

        // Build final reason
        let finalReason = '';
        if (selectedReason === 'other') {
            if (customReason.length < 5) {
                failedError.textContent = 'Please provide a reason (min 5 chars) for "Other".';
                failedError.style.display = 'block';
                return;
            }
            finalReason = customReason;
        } else if (selectedReason) {
            finalReason = selectedReason + (customReason ? ' — ' + customReason : '');
        } else if (customReason.length >= 5) {
            finalReason = customReason;
        } else {
            failedError.textContent = 'Please select or enter a reason.';
            failedError.style.display = 'block';
            return;
        }

        if (finalReason.length < 5) {
            failedError.textContent = 'Reason must be at least 5 characters.';
            failedError.style.display = 'block';
            return;
        }

        failedConfirm.disabled = true;
        const orig = failedConfirm.innerHTML;
        failedConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                reason: finalReason,
            });

            const res = await fetch(`/staff/pickups/${failedTargetId}/mark-failed`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Pickup marked as failed.', 'success');
                failedModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                failedConfirm.disabled = false;
                failedConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            failedConfirm.disabled = false;
            failedConfirm.innerHTML = orig;
        }
    });

    // ==================== RESCHEDULE ====================
    const rescheduleModal   = new bootstrap.Modal(document.getElementById('rescheduleModal'));
    const rescheduleNumber  = document.getElementById('rescheduleNumber');
    const rescheduleDate    = document.getElementById('rescheduleDate');
    const rescheduleSlot    = document.getElementById('rescheduleSlot');
    const rescheduleError   = document.getElementById('rescheduleError');
    const rescheduleConfirm = document.getElementById('rescheduleConfirm');
    let rescheduleTargetId = 0;

    window.openRescheduleModal = function (id, number, currentDate) {
        rescheduleTargetId = id;
        rescheduleNumber.textContent = number;
        rescheduleDate.value = currentDate || '<?= date('Y-m-d') ?>';
        rescheduleSlot.innerHTML = '<option value="">— Loading slots… —</option>';
        rescheduleError.style.display = 'none';
        rescheduleModal.show();
        loadSlots();
    };

    async function loadSlots() {
        const date = rescheduleDate.value;
        if (!date) return;

        try {
            const res = await fetch(`/staff/pickups/slots?date=${encodeURIComponent(date)}`);
            const json = await res.json();

            if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                rescheduleSlot.innerHTML = '<option value="">— Select a slot —</option>' +
                    json.data.map(s =>
                        `<option value="${s.slot_id}">${s.label}</option>`
                    ).join('');
            } else {
                rescheduleSlot.innerHTML = '<option value="">No slots available for this day</option>';
            }
        } catch (err) {
            console.error(err);
            rescheduleSlot.innerHTML = '<option value="">Error loading slots</option>';
        }
    }

    rescheduleDate?.addEventListener('change', loadSlots);

    rescheduleConfirm?.addEventListener('click', async () => {
        const newDate = rescheduleDate.value;
        const newSlot = parseInt(rescheduleSlot.value, 10);

        if (!newDate || !newSlot) {
            rescheduleError.textContent = 'Please select a date and slot.';
            rescheduleError.style.display = 'block';
            return;
        }

        rescheduleConfirm.disabled = true;
        const orig = rescheduleConfirm.innerHTML;
        rescheduleConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rescheduling…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                new_date: newDate,
                new_slot_id: newSlot,
            });

            const res = await fetch(`/staff/pickups/${rescheduleTargetId}/reschedule`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Pickup rescheduled.', 'success');
                rescheduleModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                rescheduleConfirm.disabled = false;
                rescheduleConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            rescheduleConfirm.disabled = false;
            rescheduleConfirm.innerHTML = orig;
        }
    });

    // ==================== TOAST ====================
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
<?php
/**
 * Staff Pickups — Detail View
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: App\Controllers\Staff\PickupController::show()
 *
 * Expected variables:
 *   $pickup  array  pickup row with customer + slot + staff + order joins
 *                   pickup_id, request_number, customer_id, slot_id,
 *                   pickup_date, status, assigned_address,
 *                   special_instructions, assigned_staff_id, is_same_day,
 *                   customer_name, customer_email, customer_phone,
 *                   address_house, address_street, address_area, city, landmark,
 *                   start_time, end_time, day_of_week, max_capacity,
 *                   order_id, order_number, order_status, order_date,
 *                   assigned_staff_name
 * ----------------------------------------------------------
 */

$currentPage = 'staff-pickups';
$title       = 'Pickup Details';

// Safe defaults
$pickup = $pickup ?? [];

if (empty($pickup)) {
    echo '<div class="alert alert-danger">Pickup not found.</div>';
    return;
}

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';
$fmtTime  = fn($t) => $t ? substr($t, 0, 5) : '';

// Extract
$pid           = (int) $pickup['pickup_id'];
$pNumber       = (string) $pickup['request_number'];
$pStatus       = (string) $pickup['status'];
$pDate         = (string) $pickup['pickup_date'];
$pSlotStart    = (string) ($pickup['start_time'] ?? '');
$pSlotEnd      = (string) ($pickup['end_time']   ?? '');
$pDayOfWeek    = (string) ($pickup['day_of_week'] ?? '');
$pMaxCapacity  = (int) ($pickup['max_capacity']  ?? 0);
$pIsSameDay    = (int) ($pickup['is_same_day']   ?? 0);
$specialNotes  = (string) ($pickup['special_instructions'] ?? '');
$assignedAddr  = (string) ($pickup['assigned_address'] ?? '');

// Customer
$custId    = (int) ($pickup['customer_id'] ?? 0);
$custName  = (string) ($pickup['customer_name']  ?? '');
$custEmail = (string) ($pickup['customer_email'] ?? '');
$custPhone = (string) ($pickup['customer_phone'] ?? '');
$house     = (string) ($pickup['address_house']  ?? '');
$street    = (string) ($pickup['address_street'] ?? '');
$area      = (string) ($pickup['address_area']   ?? '');
$city      = (string) ($pickup['city']           ?? '');
$landmark  = (string) ($pickup['landmark']       ?? '');

// Order link
$orderId   = (int) ($pickup['order_id'] ?? 0);
$orderNum  = (string) ($pickup['order_number'] ?? '');
$orderStatus = (string) ($pickup['order_status'] ?? '');
$orderDate = $pickup['order_date'] ?? null;

// Staff
$assignedStaff = $pickup['assigned_staff_name'] ?? null;
$staffRowId    = (int) ($pickup['assigned_staff_id'] ?? 0);

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
$orderStatusLabel = fn(string $s): string => ucwords(str_replace('_', ' ', $s));

// Overdue detection
$isOverdue = false;
if (!in_array($pStatus, ['picked_up', 'cancelled'], true)) {
    $today = date('Y-m-d');
    if ($pDate < $today) {
        $isOverdue = true;
    } elseif ($pDate === $today && $pSlotEnd && strtotime($today . ' ' . $pSlotEnd) < time()) {
        $isOverdue = true;
    }
}

// Can act?
$canAct = in_array($pStatus, ['requested', 'assigned'], true);
$isTerminal = in_array($pStatus, ['picked_up', 'cancelled'], true);

// Today/tomorrow
$isToday    = ($pDate === date('Y-m-d'));
$isTomorrow = ($pDate === date('Y-m-d', strtotime('+1 day')));

// Relative time for pickup date
$pickupRelative = '';
if ($isToday)         $pickupRelative = 'Today';
elseif ($isTomorrow)  $pickupRelative = 'Tomorrow';
elseif ($pDate) {
    $days = (int) round((strtotime($pDate) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0)         $pickupRelative = abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago';
    elseif ($days > 0)     $pickupRelative = 'In ' . $days . ' day' . ($days === 1 ? '' : 's');
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/staff/pickups" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                <span><?= $e($pNumber) ?></span>
                <span class="badge bg-<?= $statusBadge($pStatus) ?>">
                    <?= $e($statusLabel($pStatus)) ?>
                </span>
                <?php if ($isOverdue): ?>
                    <span class="badge bg-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue
                    </span>
                <?php endif; ?>
                <?php if ($pIsSameDay): ?>
                    <span class="badge bg-warning-subtle text-warning">
                        <i class="bi bi-lightning-charge-fill me-1"></i>Same Day
                    </span>
                <?php endif; ?>
            </h1>
            <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                <span>#<?= $pid ?></span>
                <span>·</span>
                <span>
                    <i class="bi bi-calendar me-1"></i>
                    <?= $e($fmtDate($pDate)) ?>
                    <?php if ($pickupRelative): ?>
                        (<?= $e($pickupRelative) ?>)
                    <?php endif; ?>
                </span>
                <span>·</span>
                <span>
                    <i class="bi bi-clock me-1"></i>
                    <?= $e($fmtTime($pSlotStart)) ?>–<?= $e($fmtTime($pSlotEnd)) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="tel:<?= $e($custPhone) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-telephone me-1"></i>Call Customer
        </a>

        <?php if ($canAct): ?>
            <button type="button"
                    class="btn btn-success btn-sm"
                    onclick="openPickedUpModal()">
                <i class="bi bi-check-lg me-1"></i>Mark Picked Up
            </button>
        <?php endif; ?>

        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($canAct): ?>
                    <li>
                        <button type="button" class="dropdown-item"
                                onclick="openRescheduleModal()">
                            <i class="bi bi-calendar-event me-2"></i>Reschedule
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                                onclick="openFailedModal()">
                            <i class="bi bi-x-circle me-2"></i>Mark as Failed
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <?php if ($orderNum): ?>
                    <li>
                        <a class="dropdown-item" href="/staff/orders/<?= $orderId ?>">
                            <i class="bi bi-bag-check me-2"></i>View Order
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($custId > 0): ?>
                    <li>
                        <a class="dropdown-item" href="/admin/customers/<?= $custId ?>">
                            <i class="bi bi-person me-2"></i>View Customer
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3">

    <!-- ==================== LEFT COLUMN ==================== -->
    <div class="col-lg-8">

        <!-- ============ PICKUP INFO ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Pickup Details
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="badge bg-<?= $statusBadge($pStatus) ?>">
                                    <?= $e($statusLabel($pStatus)) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Pickup Date</div>
                            <div class="info-value small">
                                <?= $e($fmtDate($pDate)) ?>
                                <?php if ($pickupRelative): ?>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        <?= $e($pickupRelative) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Time Slot</div>
                            <div class="info-value small">
                                <?= $e($fmtTime($pSlotStart)) ?>–<?= $e($fmtTime($pSlotEnd)) ?>
                                <?php if ($pDayOfWeek): ?>
                                    <span class="text-muted">· <?= $e(ucfirst($pDayOfWeek)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Same Day</div>
                            <div class="info-value small">
                                <?php if ($pIsSameDay): ?>
                                    <span class="badge bg-warning-subtle text-warning">Yes</span>
                                <?php else: ?>
                                    <span class="text-muted">No</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($specialNotes !== ''): ?>
                    <hr>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold mb-2">
                            <i class="bi bi-sticky me-1"></i>Special Instructions
                        </div>
                        <div class="bg-light rounded p-3 small">
                            <?= nl2br($e($specialNotes)) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============ ADDRESS ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-geo-alt me-2 text-primary"></i>Pickup Address
                </h6>
            </div>
            <div class="card-body">
                <div class="bg-light rounded p-3">
                    <div class="fw-medium mb-1">
                        <?= $e($house) ?>, <?= $e($street) ?>
                    </div>
                    <div class="text-muted small">
                        <?= $e($area) ?>, <?= $e($city) ?>
                    </div>
                    <?php if ($landmark !== ''): ?>
                        <div class="text-muted mt-1" style="font-size:.78rem;">
                            <i class="bi bi-signpost-2 me-1"></i>Near <?= $e($landmark) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($assignedAddr !== '' && $assignedAddr !== "$house, $street, $area, $city"): ?>
                    <div class="mt-3">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">
                            Address on File
                        </div>
                        <div class="small text-muted">
                            <?= nl2br($e($assignedAddr)) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============ LINKED ORDER ============ -->
        <?php if ($orderNum): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-bag-check me-2 text-primary"></i>Linked Order
                    </h6>
                    <a href="/staff/orders/<?= $orderId ?>" class="small text-decoration-none">
                        View order <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body pt-0">
                    <div class="info-row">
                        <div class="info-label">Order #</div>
                        <div class="info-value">
                            <a href="/staff/orders/<?= $orderId ?>"
                               class="text-decoration-none fw-medium">
                                <?= $e($orderNum) ?>
                            </a>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="badge bg-<?= $orderBadge($orderStatus) ?>">
                                <?= $e($orderStatusLabel($orderStatus)) ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Order Date</div>
                        <div class="info-value small"><?= $e($fmtDateTime($orderDate)) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ==================== RIGHT COLUMN ==================== -->
    <div class="col-lg-4">

        <!-- ============ CUSTOMER ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person me-2 text-primary"></i>Customer
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="avatar-circle bg-primary-subtle text-primary">
                        <?= $e(strtoupper(substr($custName, 0, 1))) ?>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-medium text-truncate"><?= $e($custName) ?></div>
                        <div class="text-muted small">#<?= $custId ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone</div>
                    <div class="info-value small">
                        <a href="tel:<?= $e($custPhone) ?>" class="text-decoration-none">
                            <?= $e($custPhone) ?>
                        </a>
                    </div>
                </div>
                <?php if ($custEmail !== ''): ?>
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value small text-truncate">
                            <?= $e($custEmail) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============ ASSIGNMENT ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person-badge me-2 text-primary"></i>Assignment
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Assigned To</div>
                    <div class="info-value">
                        <?php if ($assignedStaff): ?>
                            <span class="badge bg-info-subtle text-info">
                                <i class="bi bi-person"></i> <?= $e($assignedStaff) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning">Unassigned</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ ACTIONS ============ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-lightning-charge me-2 text-primary"></i>Actions
                </h6>
            </div>
            <div class="card-body pt-0">
                <?php if ($canAct): ?>
                    <button type="button"
                            class="btn btn-success w-100 mb-2"
                            onclick="openPickedUpModal()">
                        <i class="bi bi-check-lg me-1"></i>Mark Picked Up
                    </button>
                    <button type="button"
                            class="btn btn-outline-warning w-100 mb-2"
                            onclick="openRescheduleModal()">
                        <i class="bi bi-calendar-event me-1"></i>Reschedule
                    </button>
                    <button type="button"
                            class="btn btn-outline-danger w-100"
                            onclick="openFailedModal()">
                        <i class="bi bi-x-circle me-1"></i>Mark as Failed
                    </button>
                <?php else: ?>
                    <div class="alert alert-<?= $pStatus === 'picked_up' ? 'success' : 'secondary' ?> small mb-0 py-2">
                        <i class="bi bi-check-circle me-1"></i>
                        <?php if ($pStatus === 'picked_up'): ?>
                            This pickup has been completed successfully.
                        <?php elseif ($pStatus === 'cancelled'): ?>
                            This pickup was cancelled.
                        <?php else: ?>
                            No actions available for this status.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <hr class="my-3">

                <a href="tel:<?= $e($custPhone) ?>"
                   class="btn btn-outline-secondary w-100 mb-2">
                    <i class="bi bi-telephone me-1"></i>Call Customer
                </a>
                <?php if ($orderNum): ?>
                    <a href="/staff/orders/<?= $orderId ?>"
                       class="btn btn-outline-secondary w-100">
                        <i class="bi bi-bag-check me-1"></i>View Order
                    </a>
                <?php endif; ?>
            </div>
        </div>
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
                    Mark <strong><?= $e($pNumber) ?></strong> as picked up from
                    <strong><?= $e($custName) ?></strong>?
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
                    Pickup <strong><?= $e($pNumber) ?></strong> from
                    <strong><?= $e($custName) ?></strong>
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
                    Reschedule <strong><?= $e($pNumber) ?></strong>:
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">New Date</label>
                    <input type="date" class="form-control" id="rescheduleDate"
                           min="<?= date('Y-m-d') ?>"
                           value="<?= $e($pDate) ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-medium">New Slot</label>
                    <select class="form-select" id="rescheduleSlot">
                        <option value="">— Loading slots… —</option>
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
    .avatar-circle {
        width: 44px; height: 44px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 1rem;
        flex-shrink: 0;
    }
    .min-w-0 { min-width: 0; }
    .info-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: .55rem 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: .88rem;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label {
        color: #64748b;
        font-weight: 500;
        flex-shrink: 0;
        min-width: 100px;
        font-size: .82rem;
    }
    .info-value {
        text-align: right;
        word-break: break-word;
        color: #0f172a;
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
    const pickupId = <?= $pid ?>;

    // ==================== PICKED UP ====================
    const pickedUpModal   = new bootstrap.Modal(document.getElementById('pickedUpModal'));
    const pickedUpNotes   = document.getElementById('pickedUpNotes');
    const pickedUpConfirm = document.getElementById('pickedUpConfirm');

    window.openPickedUpModal = function () {
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

            const res = await fetch(`/staff/pickups/${pickupId}/mark-picked-up`, {
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
    const failedReasonSelect = document.getElementById('failedReasonSelect');
    const failedReason       = document.getElementById('failedReason');
    const failedError        = document.getElementById('failedError');
    const failedConfirm      = document.getElementById('failedConfirm');

    window.openFailedModal = function () {
        failedReasonSelect.value = '';
        failedReason.value = '';
        failedError.style.display = 'none';
        failedModal.show();
    };

    failedConfirm?.addEventListener('click', async () => {
        const selectedReason = failedReasonSelect.value;
        const customReason   = failedReason.value.trim();

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
            const body = new URLSearchParams({ _csrf: csrf, reason: finalReason });

            const res = await fetch(`/staff/pickups/${pickupId}/mark-failed`, {
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
    const rescheduleDate    = document.getElementById('rescheduleDate');
    const rescheduleSlot    = document.getElementById('rescheduleSlot');
    const rescheduleError   = document.getElementById('rescheduleError');
    const rescheduleConfirm = document.getElementById('rescheduleConfirm');

    window.openRescheduleModal = function () {
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

            const res = await fetch(`/staff/pickups/${pickupId}/reschedule`, {
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
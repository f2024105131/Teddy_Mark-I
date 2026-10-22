<?php
/**
 * Staff Deliveries — Detail View
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: App\Controllers\Staff\DeliveryController::show()
 *
 * Expected variables:
 *   $delivery  array  full delivery row with order/customer/slot/staff joins
 *                     delivery_id, order_id, order_number, order_status,
 *                     order_date, special_instructions, delivered_at,
 *                     customer_id, customer_name, customer_email, customer_phone,
 *                     address_house, address_street, address_area, city, landmark,
 *                     slot_id, start_time, end_time, max_capacity,
 *                     status, failure_reason, delivery_notes, delivery_date,
 *                     assigned_staff_id, assigned_staff_name
 * ----------------------------------------------------------
 */

$currentPage = 'staff-deliveries';
$title       = 'Delivery Details';

// Safe defaults
$delivery = $delivery ?? [];

if (empty($delivery)) {
    echo '<div class="alert alert-danger">Delivery not found.</div>';
    return;
}

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';
$fmtTime  = fn($t) => $t ? substr($t, 0, 5) : '';

// Extract
$did            = (int) $delivery['delivery_id'];
$dStatus        = (string) $delivery['status'];
$dDate          = (string) $delivery['delivery_date'];
$dSlotStart     = (string) ($delivery['start_time'] ?? '');
$dSlotEnd       = (string) ($delivery['end_time']   ?? '');
$dFailureReason = (string) ($delivery['failure_reason'] ?? '');
$dNotes         = (string) ($delivery['delivery_notes'] ?? '');
$dDeliveredAt   = $delivery['delivered_at'] ?? null;
$dCreatedAt     = $delivery['created_at']   ?? null;

// Order
$orderId     = (int) ($delivery['order_id'] ?? 0);
$orderNum    = (string) ($delivery['order_number'] ?? '');
$orderStatus = (string) ($delivery['order_status'] ?? '');
$orderDate   = $delivery['order_date'] ?? null;
$specialNotes = (string) ($delivery['special_instructions'] ?? '');

// Customer
$custId    = (int) ($delivery['customer_id'] ?? 0);
$custName  = (string) ($delivery['customer_name'] ?? '');
$custEmail = (string) ($delivery['customer_email'] ?? '');
$custPhone = (string) ($delivery['customer_phone'] ?? '');
$house     = (string) ($delivery['address_house']  ?? '');
$street    = (string) ($delivery['address_street'] ?? '');
$area      = (string) ($delivery['address_area']   ?? '');
$city      = (string) ($delivery['city']           ?? '');
$landmark  = (string) ($delivery['landmark']       ?? '');

// Staff
$assignedStaff = $delivery['assigned_staff_name'] ?? null;
$staffRowId    = (int) ($delivery['assigned_staff_id'] ?? 0);

// Badges
$statusBadge = function (string $s): string {
    return match ($s) {
        'scheduled'        => 'warning',
        'out_for_delivery' => 'info',
        'delivered'        => 'success',
        'failed'           => 'danger',
        'rescheduled'      => 'secondary',
        default            => 'secondary',
    };
};
$statusLabel = function (string $s): string {
    return match ($s) {
        'scheduled'        => 'Scheduled',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'failed'           => 'Failed',
        'rescheduled'      => 'Rescheduled',
        default            => ucwords(str_replace('_', ' ', $s)),
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
if (!in_array($dStatus, ['delivered', 'failed', 'rescheduled'], true)) {
    $today = date('Y-m-d');
    if ($dDate < $today) {
        $isOverdue = true;
    } elseif ($dDate === $today && $dSlotEnd && strtotime($today . ' ' . $dSlotEnd) < time()) {
        $isOverdue = true;
    }
}

// Terminal & can-act flags
$isTerminal = in_array($dStatus, ['delivered'], true);
$canStart    = ($dStatus === 'scheduled');
$canDeliver  = in_array($dStatus, ['scheduled', 'out_for_delivery'], true);
$canFail     = in_array($dStatus, ['scheduled', 'out_for_delivery'], true);
$canReschedule = in_array($dStatus, ['scheduled', 'out_for_delivery', 'failed', 'rescheduled'], true);

// Today/tomorrow relative
$isToday    = ($dDate === date('Y-m-d'));
$isTomorrow = ($dDate === date('Y-m-d', strtotime('+1 day')));
$relative = '';
if ($isToday)         $relative = 'Today';
elseif ($isTomorrow)  $relative = 'Tomorrow';
elseif ($dDate) {
    $days = (int) round((strtotime($dDate) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0)     $relative = abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago';
    elseif ($days > 0) $relative = 'In ' . $days . ' day' . ($days === 1 ? '' : 's');
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/staff/deliveries" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                <span><?= $e($orderNum) ?></span>
                <span class="badge bg-<?= $statusBadge($dStatus) ?>">
                    <?= $e($statusLabel($dStatus)) ?>
                </span>
                <?php if ($isOverdue): ?>
                    <span class="badge bg-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue
                    </span>
                <?php endif; ?>
            </h1>
            <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                <span>#<?= $did ?></span>
                <span>·</span>
                <span>
                    <i class="bi bi-calendar me-1"></i>
                    <?= $e($fmtDate($dDate)) ?>
                    <?php if ($relative): ?>
                        (<?= $e($relative) ?>)
                    <?php endif; ?>
                </span>
                <span>·</span>
                <span>
                    <i class="bi bi-clock me-1"></i>
                    <?= $e($fmtTime($dSlotStart)) ?>–<?= $e($fmtTime($dSlotEnd)) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="tel:<?= $e($custPhone) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-telephone me-1"></i>Call Customer
        </a>

        <?php if ($canStart): ?>
            <button type="button"
                    class="btn btn-info text-white btn-sm"
                    onclick="openStartModal()">
                <i class="bi bi-truck me-1"></i>Start Delivery
            </button>
        <?php endif; ?>

        <?php if ($dStatus === 'out_for_delivery'): ?>
            <button type="button"
                    class="btn btn-success btn-sm"
                    onclick="openDeliveredModal()">
                <i class="bi bi-check-lg me-1"></i>Mark Delivered
            </button>
        <?php endif; ?>

        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($canFail): ?>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                                onclick="openFailedModal()">
                            <i class="bi bi-x-circle me-2"></i>Mark as Failed
                        </button>
                    </li>
                <?php endif; ?>
                <?php if ($canReschedule): ?>
                    <li>
                        <button type="button" class="dropdown-item"
                                onclick="openRescheduleModal()">
                            <i class="bi bi-calendar-event me-2"></i>Reschedule
                        </button>
                    </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="/staff/orders/<?= $orderId ?>">
                        <i class="bi bi-bag-check me-2"></i>View Order
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="/admin/customers/<?= $custId ?>">
                        <i class="bi bi-person me-2"></i>View Customer
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3">

    <!-- ==================== LEFT COLUMN ==================== -->
    <div class="col-lg-8">

        <!-- ============ DELIVERY INFO ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Delivery Details
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="badge bg-<?= $statusBadge($dStatus) ?>">
                                    <?= $e($statusLabel($dStatus)) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Delivery Date</div>
                            <div class="info-value small">
                                <?= $e($fmtDate($dDate)) ?>
                                <?php if ($relative): ?>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        <?= $e($relative) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Time Slot</div>
                            <div class="info-value small">
                                <?= $e($fmtTime($dSlotStart)) ?>–<?= $e($fmtTime($dSlotEnd)) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Created</div>
                            <div class="info-value small"><?= $e($fmtDateTime($dCreatedAt)) ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($dDeliveredAt): ?>
                    <div class="alert alert-success small mb-3 py-2">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        <strong>Delivered</strong> on <?= $e($fmtDateTime($dDeliveredAt)) ?>
                    </div>
                <?php endif; ?>

                <?php if ($dStatus === 'failed' && $dFailureReason !== ''): ?>
                    <div class="alert alert-danger small mb-3 py-2">
                        <i class="bi bi-x-circle-fill me-1"></i>
                        <strong>Failed:</strong> <?= $e($dFailureReason) ?>
                    </div>
                <?php endif; ?>

                <?php if ($dNotes !== ''): ?>
                    <hr>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold mb-2">
                            <i class="bi bi-sticky me-1"></i>Delivery Notes
                        </div>
                        <div class="bg-light rounded p-3 small">
                            <?= nl2br($e($dNotes)) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($specialNotes !== ''): ?>
                    <hr>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold mb-2">
                            <i class="bi bi-exclamation-circle me-1 text-warning"></i>Customer Special Instructions
                        </div>
                        <div class="bg-light rounded p-3 small border-start border-warning border-3">
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
                    <i class="bi bi-geo-alt me-2 text-primary"></i>Delivery Address
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
            </div>
        </div>

        <!-- ============ LINKED ORDER ============ -->
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
                <?php if ($canStart): ?>
                    <button type="button"
                            class="btn btn-info text-white w-100 mb-2"
                            onclick="openStartModal()">
                        <i class="bi bi-truck me-1"></i>Start Delivery
                    </button>
                <?php endif; ?>

                <?php if ($dStatus === 'out_for_delivery'): ?>
                    <button type="button"
                            class="btn btn-success w-100 mb-2"
                            onclick="openDeliveredModal()">
                        <i class="bi bi-check-lg me-1"></i>Mark Delivered
                    </button>
                <?php endif; ?>

                <?php if ($canFail): ?>
                    <button type="button"
                            class="btn btn-outline-danger w-100 mb-2"
                            onclick="openFailedModal()">
                        <i class="bi bi-x-circle me-1"></i>Mark as Failed
                    </button>
                <?php endif; ?>

                <?php if ($canReschedule): ?>
                    <button type="button"
                            class="btn btn-outline-warning w-100 mb-2"
                            onclick="openRescheduleModal()">
                        <i class="bi bi-calendar-event me-1"></i>Reschedule
                    </button>
                <?php endif; ?>

                <?php if ($isTerminal && $dStatus === 'delivered'): ?>
                    <div class="alert alert-success small mb-0 py-2">
                        <i class="bi bi-check-circle me-1"></i>
                        This delivery has been successfully completed.
                    </div>
                <?php endif; ?>

                <?php if (!empty($dFailureReason) && $dStatus === 'failed'): ?>
                    <div class="alert alert-danger small mb-0 py-2 mt-2">
                        <i class="bi bi-x-circle me-1"></i>
                        Delivered failed. You can reschedule to try again.
                    </div>
                <?php endif; ?>

                <hr class="my-3">

                <a href="tel:<?= $e($custPhone) ?>"
                   class="btn btn-outline-secondary w-100 mb-2">
                    <i class="bi bi-telephone me-1"></i>Call Customer
                </a>
                <a href="/staff/orders/<?= $orderId ?>"
                   class="btn btn-outline-secondary w-100">
                    <i class="bi bi-bag-check me-1"></i>View Order
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ==================== START DELIVERY MODAL ==================== -->
<div class="modal fade" id="startModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-truck me-2"></i>Start Delivery
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Start delivery for order <strong><?= $e($orderNum) ?></strong>
                    to <strong><?= $e($custName) ?></strong>?
                </p>
                <div class="alert alert-info small mb-0 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    This marks the delivery as <strong>Out for Delivery</strong>.
                    The customer will be notified.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info text-white" id="startConfirm">
                    <i class="bi bi-truck me-1"></i>Start Delivery
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== DELIVERED MODAL ==================== -->
<div class="modal fade" id="deliveredModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle me-2"></i>Confirm Delivery
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Mark order <strong><?= $e($orderNum) ?></strong> as delivered to
                    <strong><?= $e($custName) ?></strong>?
                </p>
                <div class="alert alert-success small mb-3 py-2">
                    <i class="bi bi-check-circle me-1"></i>
                    This finalizes the order as <strong>Delivered</strong> and triggers billing/receipt generation.
                </div>
                <label class="form-label small fw-medium">
                    Delivery Notes <span class="text-muted fw-normal">(optional)</span>
                </label>
                <textarea class="form-control" id="deliveredNotes" rows="2"
                          placeholder="Number of bags delivered, recipient name, etc…"
                          maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="deliveredConfirm">
                    <i class="bi bi-check-lg me-1"></i>Confirm Delivered
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
                    <i class="bi bi-x-circle me-2"></i>Mark Delivery as Failed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3 py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    The delivery will be marked as failed. The order remains active.
                </div>
                <p class="small mb-3">
                    Failed delivery for order <strong><?= $e($orderNum) ?></strong>
                </p>
                <label class="form-label small fw-medium">
                    Reason <span class="text-danger">*</span>
                </label>
                <select class="form-select form-select-sm mb-2" id="failedReasonSelect">
                    <option value="">— Select a reason —</option>
                    <option value="Customer not available">Customer not available</option>
                    <option value="Address not found">Address not found</option>
                    <option value="Customer refused delivery">Customer refused delivery</option>
                    <option value="Payment not ready">Payment not ready</option>
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
                    <i class="bi bi-x-circle me-1"></i>Mark Failed
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
                    <i class="bi bi-calendar-event me-2"></i>Reschedule Delivery
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reschedule delivery for order <strong><?= $e($orderNum) ?></strong>:
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">New Date</label>
                    <input type="date" class="form-control" id="rescheduleDate"
                           min="<?= date('Y-m-d') ?>"
                           value="<?= $e($dDate) ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-medium">New Slot</label>
                    <select class="form-select" id="rescheduleSlot">
                        <option value="">— Loading slots… —</option>
                    </select>
                    <div class="form-text small">
                        Delivery slots are available every day.
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
    const deliveryId = <?= $did ?>;

    // ==================== START ====================
    const startModal   = new bootstrap.Modal(document.getElementById('startModal'));
    const startConfirm = document.getElementById('startConfirm');

    window.openStartModal = function () {
        startModal.show();
    };

    startConfirm?.addEventListener('click', async () => {
        startConfirm.disabled = true;
        const orig = startConfirm.innerHTML;
        startConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Starting…';

        try {
            const body = new URLSearchParams({ _csrf: csrf });

            const res = await fetch(`/staff/deliveries/${deliveryId}/out-for-delivery`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Delivery started.', 'success');
                startModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                startConfirm.disabled = false;
                startConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            startConfirm.disabled = false;
            startConfirm.innerHTML = orig;
        }
    });

    // ==================== DELIVERED ====================
    const deliveredModal   = new bootstrap.Modal(document.getElementById('deliveredModal'));
    const deliveredNotes   = document.getElementById('deliveredNotes');
    const deliveredConfirm = document.getElementById('deliveredConfirm');

    window.openDeliveredModal = function () {
        deliveredNotes.value = '';
        deliveredModal.show();
    };

    deliveredConfirm?.addEventListener('click', async () => {
        deliveredConfirm.disabled = true;
        const orig = deliveredConfirm.innerHTML;
        deliveredConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Delivering…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                delivery_notes: deliveredNotes.value.trim(),
            });

            const res = await fetch(`/staff/deliveries/${deliveryId}/delivered`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Order delivered.', 'success');
                deliveredModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                deliveredConfirm.disabled = false;
                deliveredConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            deliveredConfirm.disabled = false;
            deliveredConfirm.innerHTML = orig;
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

        failedConfirm.disabled = true;
        const orig = failedConfirm.innerHTML;
        failedConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, reason: finalReason });

            const res = await fetch(`/staff/deliveries/${deliveryId}/failed`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Delivery marked as failed.', 'success');
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
            const res = await fetch(`/staff/deliveries/slots?date=${encodeURIComponent(date)}`);
            const json = await res.json();

            if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                rescheduleSlot.innerHTML = '<option value="">— Select a slot —</option>' +
                    json.data.map(s =>
                        `<option value="${s.slot_id}">${s.label}</option>`
                    ).join('');
            } else {
                rescheduleSlot.innerHTML = '<option value="">No slots available</option>';
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

            const res = await fetch(`/staff/deliveries/${deliveryId}/reschedule`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Delivery rescheduled.', 'success');
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
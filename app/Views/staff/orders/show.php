<?php
/**
 * Staff Orders — Detail View
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\Staff\OrderController::show()
 *
 * Expected variables:
 *   $order        array   full order row with customer + pickup + staff joins
 *   $items        array   order items with item/service names
 *   $history      array   status history with changed_by_name
 *   $bill         ?array  optional bill row (or null)
 *   $delivery     ?array  optional delivery row with slot (or null)
 *   $nextStatuses array   valid next statuses for the current state
 *   $statusLabels array   status => human label
 * ----------------------------------------------------------
 */

$currentPage = 'staff-orders';
$title       = 'Order Details';

// Safe defaults
$order        = $order        ?? [];
$items        = $items        ?? [];
$history      = $history      ?? [];
$bill         = $bill         ?? null;
$delivery     = $delivery     ?? null;
$nextStatuses = $nextStatuses ?? [];
$statusLabels = $statusLabels ?? [];

if (empty($order)) {
    echo '<div class="alert alert-danger">Order not found.</div>';
    return;
}

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';
$fmtTime  = fn($t) => $t ? substr($t, 0, 5) : '';

// Extract
$oid          = (int) $order['order_id'];
$orderNum     = (string) $order['order_number'];
$oStatus      = (string) $order['order_status'];
$orderDate    = $order['order_date'] ?? null;
$estDate      = $order['estimated_delivery_date'] ?? $order['delivery_date'] ?? null;
$deliveredAt  = $order['delivered_at'] ?? null;
$specialNotes = (string) ($order['special_instructions'] ?? '');

// Timestamps
$timestamps = [
    'picked_up'           => $order['picked_up_at']           ?? null,
    'received_at_laundry' => $order['received_at_laundry_at'] ?? null,
    'washing_started'     => $order['washing_started_at']     ?? null,
    'washing_completed'   => $order['washing_completed_at']   ?? null,
    'ironing_started'     => $order['ironing_started_at']     ?? null,
    'ironing_completed'   => $order['ironing_completed_at']   ?? null,
    'ready'               => $order['ready_at']               ?? null,
    'out_for_delivery'    => $order['out_for_delivery_at']    ?? null,
    'delivered'           => $order['delivered_at']           ?? null,
];

// Customer
$custId    = (int) ($order['customer_id'] ?? 0);
$custName  = (string) ($order['customer_name'] ?? '');
$custEmail = (string) ($order['customer_email'] ?? '');
$custPhone = (string) ($order['customer_phone'] ?? '');
$addrParts = array_filter([
    $order['address_house']  ?? '',
    $order['address_street'] ?? '',
    $order['address_area']   ?? '',
    $order['city']           ?? '',
]);
$fullAddress = implode(', ', $addrParts);
$landmark    = (string) ($order['landmark'] ?? '');

// Staff
$assignedStaff = $order['assigned_staff_name'] ?? null;
$staffRowId    = (int) ($order['assigned_staff_id'] ?? 0);

// Pickup
$pickupNum    = $order['request_number']   ?? null;
$pickupStatus = $order['pickup_status']    ?? null;

// Status badge helpers
$statusBadge = function (string $s): string {
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
$statusLabel = function (string $s) use ($statusLabels): string {
    return $statusLabels[$s] ?? ucwords(str_replace('_', ' ', $s));
};
$paymentBadge = function (string $s): string {
    return match ($s) {
        'paid'           => 'success',
        'partially_paid' => 'warning',
        'unpaid'         => 'danger',
        'refunded'       => 'secondary',
        default          => 'secondary',
    };
};

// Compute totals from items
$subtotal = 0;
$totalQty = 0;
foreach ($items as $it) {
    $subtotal += (float) ($it['total_price'] ?? 0);
    $totalQty += (int) ($it['quantity'] ?? 0);
}

// Due date label
$dueLabel = '—';
$dueClass = 'text-muted';
if ($estDate) {
    $dueDays = (int) round((strtotime($estDate) - strtotime(date('Y-m-d'))) / 86400);
    if ($dueDays < 0) {
        $dueLabel = abs($dueDays) . ' day' . (abs($dueDays) === 1 ? '' : 's') . ' overdue';
        $dueClass = 'text-danger fw-medium';
    } elseif ($dueDays === 0) {
        $dueLabel = 'Due today';
        $dueClass = 'text-warning fw-medium';
    } elseif ($dueDays === 1) {
        $dueLabel = 'Due tomorrow';
        $dueClass = 'text-muted';
    } else {
        $dueLabel = 'Due in ' . $dueDays . ' days';
        $dueClass = 'text-muted';
    }
}

// Is terminal?
$isTerminal = in_array($oStatus, ['delivered', 'cancelled'], true);
$canProgress = !$isTerminal && !empty($nextStatuses);

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/staff/orders" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                <span><?= $e($orderNum) ?></span>
                <span class="badge bg-<?= $statusBadge($oStatus) ?>">
                    <?= $e($statusLabel($oStatus)) ?>
                </span>
                <?php if ($dueClass === 'text-danger fw-medium'): ?>
                    <span class="badge bg-danger-subtle text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Overdue
                    </span>
                <?php endif; ?>
            </h1>
            <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                <span>#<?= $oid ?></span>
                <span>·</span>
                <span><?= $fmtInt($totalQty) ?> item<?= $totalQty === 1 ? '' : 's' ?></span>
                <span>·</span>
                <span>Ordered <?= $e($fmtDateTime($orderDate)) ?></span>
                <?php if ($assignedStaff): ?>
                    <span>·</span>
                    <span><i class="bi bi-person me-1"></i><?= $e($assignedStaff) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="tel:<?= $e($custPhone) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-telephone me-1"></i>Call Customer
        </a>
        <?php if ($canProgress):
            $primary = $nextStatuses[0] ?? null;
            if ($primary && $primary !== 'cancelled'):
        ?>
            <button type="button"
                    class="btn btn-success btn-sm"
                    onclick="openAdvanceModal(<?= json_encode($primary) ?>, <?= json_encode($statusLabel($primary), $jsonFlags) ?>)">
                <i class="bi bi-arrow-right-circle me-1"></i>
                Advance to <?= $e($statusLabel($primary)) ?>
            </button>
        <?php endif; endif; ?>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <button type="button" class="dropdown-item" onclick="openNoteModal()">
                        <i class="bi bi-sticky me-2"></i>Add Note
                    </button>
                </li>
                <li>
                    <a class="dropdown-item" href="/staff/pickups/<?= (int) ($order['pickup_id'] ?? 0) ?>">
                        <i class="bi bi-box-seam me-2"></i>View Pickup
                    </a>
                </li>
                <?php if ($delivery): ?>
                    <li>
                        <a class="dropdown-item" href="/staff/deliveries/<?= (int) ($delivery['delivery_id'] ?? 0) ?>">
                            <i class="bi bi-truck me-2"></i>View Delivery
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (in_array('cancelled', $nextStatuses, true)): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                                onclick="openCancelModal()">
                            <i class="bi bi-x-circle me-2"></i>Cancel Order
                        </button>
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

        <!-- ============ ITEMS TABLE ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-basket me-2 text-primary"></i>Order Items
                    <span class="text-muted fw-normal">(<?= count($items) ?>)</span>
                </h6>
                <span class="text-muted small"><?= $fmtInt($totalQty) ?> total items</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small text-muted fw-semibold">Item</th>
                            <th class="small text-muted fw-semibold">Service</th>
                            <th class="small text-muted fw-semibold text-center">Qty</th>
                            <th class="small text-muted fw-semibold text-end">Unit</th>
                            <th class="small text-muted fw-semibold text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4 small">
                                    No items recorded on this order.
                                </td>
                            </tr>
                        <?php else: foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium small"><?= $e($it['item_name'] ?? '') ?></div>
                                    <?php if (!empty($it['category_name'])): ?>
                                        <div class="text-muted" style="font-size:.7rem;">
                                            <?= $e($it['category_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?= $e($it['service_name'] ?? '') ?>
                                </td>
                                <td class="text-center small fw-medium">
                                    <?= $fmtInt($it['quantity'] ?? 0) ?>
                                </td>
                                <td class="text-end small text-muted">
                                    <?= $fmtMoney($it['unit_price'] ?? 0) ?>
                                </td>
                                <td class="text-end small fw-medium">
                                    <?= $fmtMoney($it['total_price'] ?? 0) ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="4" class="text-end small fw-semibold">Items Subtotal</td>
                            <td class="text-end fw-bold"><?= $fmtMoney($subtotal) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ============ BILL (if exists) ============ -->
        <?php if ($bill): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-receipt me-2 text-primary"></i>Bill
                    </h6>
                    <span class="badge bg-<?= $paymentBadge($bill['payment_status'] ?? 'unpaid') ?>">
                        <?= $e(ucwords(str_replace('_', ' ', $bill['payment_status'] ?? 'unpaid'))) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <div class="info-label">Bill #</div>
                        <div class="info-value"><code><?= $e($bill['bill_number'] ?? '') ?></code></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Subtotal</div>
                        <div class="info-value"><?= $fmtMoney($bill['subtotal'] ?? 0) ?></div>
                    </div>
                    <?php if (!empty($bill['tax_amount']) && (float) $bill['tax_amount'] > 0): ?>
                        <div class="info-row">
                            <div class="info-label">Tax</div>
                            <div class="info-value"><?= $fmtMoney($bill['tax_amount']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($bill['discount_amount']) && (float) $bill['discount_amount'] > 0): ?>
                        <div class="info-row">
                            <div class="info-label">Discount</div>
                            <div class="info-value text-success">-<?= $fmtMoney($bill['discount_amount']) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="info-row fw-semibold">
                        <div class="info-label">Total</div>
                        <div class="info-value"><?= $fmtMoney($bill['total_amount'] ?? 0) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Paid</div>
                        <div class="info-value text-success"><?= $fmtMoney($bill['paid_amount'] ?? 0) ?></div>
                    </div>
                    <?php if ((float) ($bill['outstanding_amount'] ?? 0) > 0): ?>
                        <div class="info-row">
                            <div class="info-label">Outstanding</div>
                            <div class="info-value text-danger fw-medium">
                                <?= $fmtMoney($bill['outstanding_amount']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ STATUS HISTORY ============ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Status History
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-clock-history fs-1 d-block mb-2 opacity-50"></i>
                        No status changes recorded yet.
                    </div>
                <?php else: ?>
                    <ul class="timeline-list">
                        <?php foreach ($history as $h):
                            $prev       = (string) ($h['previous_status'] ?? '');
                            $new        = (string) ($h['new_status'] ?? '');
                            $changedBy  = (string) ($h['changed_by_name'] ?? 'System');
                            $changedAt  = $h['changed_at'] ?? null;
                        ?>
                            <li class="timeline-item">
                                <div class="timeline-dot bg-<?= $statusBadge($new) ?>"></div>
                                <div class="timeline-content">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <?php if ($prev): ?>
                                            <span class="badge bg-light text-dark border" style="font-size:.7rem;">
                                                <?= $e($statusLabel($prev)) ?>
                                            </span>
                                            <i class="bi bi-arrow-right text-muted"></i>
                                        <?php endif; ?>
                                        <span class="badge bg-<?= $statusBadge($new) ?>" style="font-size:.75rem;">
                                            <?= $e($statusLabel($new)) ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-person"></i> <?= $e($changedBy) ?>
                                        · <?= $e($fmtDateTime($changedAt)) ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== RIGHT COLUMN ==================== -->
    <div class="col-lg-4">

        <!-- ============ STATUS SUMMARY ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Status & Timeline
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Current Status</div>
                    <div class="info-value">
                        <span class="badge bg-<?= $statusBadge($oStatus) ?>">
                            <?= $e($statusLabel($oStatus)) ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Order Date</div>
                    <div class="info-value small"><?= $e($fmtDateTime($orderDate)) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Due Date</div>
                    <div class="info-value">
                        <?php if ($estDate): ?>
                            <div class="small"><?= $e($fmtDate($estDate)) ?></div>
                            <div class="<?= $e($dueClass) ?>" style="font-size:.72rem;">
                                <?= $e($dueLabel) ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">Not scheduled</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($deliveredAt): ?>
                    <div class="info-row">
                        <div class="info-label">Delivered</div>
                        <div class="info-value small"><?= $e($fmtDateTime($deliveredAt)) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($pickupNum): ?>
                    <div class="info-row">
                        <div class="info-label">Pickup #</div>
                        <div class="info-value">
                            <a href="/staff/pickups/<?= (int) ($order['pickup_id'] ?? 0) ?>"
                               class="small text-decoration-none">
                                <?= $e($pickupNum) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
                <?php if ($custEmail): ?>
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value small text-truncate">
                            <?= $e($custEmail) ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Address</div>
                    <div class="info-value small">
                        <?= $e($fullAddress) ?>
                        <?php if ($landmark !== ''): ?>
                            <div class="text-muted" style="font-size:.72rem;">
                                Near <?= $e($landmark) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ SPECIAL INSTRUCTIONS ============ -->
        <?php if ($specialNotes !== ''): ?>
            <div class="card border-0 shadow-sm mb-3 border-start border-warning border-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold text-warning">
                        <i class="bi bi-sticky me-2"></i>Special Instructions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small bg-light rounded p-2">
                        <?= nl2br($e($specialNotes)) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ DELIVERY ============ -->
        <?php if ($delivery): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-truck me-2 text-primary"></i>Delivery
                    </h6>
                    <a href="/staff/deliveries/<?= (int) $delivery['delivery_id'] ?>"
                       class="small text-decoration-none">
                        View <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body pt-0">
                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="badge bg-info-subtle text-info">
                                <?= $e(ucwords(str_replace('_', ' ', $delivery['status'] ?? ''))) ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Date</div>
                        <div class="info-value small"><?= $e($fmtDate($delivery['delivery_date'] ?? null)) ?></div>
                    </div>
                    <?php if (!empty($delivery['start_time']) && !empty($delivery['end_time'])): ?>
                        <div class="info-row">
                            <div class="info-label">Slot</div>
                            <div class="info-value small">
                                <?= $e($fmtTime($delivery['start_time'])) ?>–<?= $e($fmtTime($delivery['end_time'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($delivery['delivery_staff_name'])): ?>
                        <div class="info-row">
                            <div class="info-label">Assigned</div>
                            <div class="info-value small"><?= $e($delivery['delivery_staff_name']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== ADVANCE MODAL ==================== -->
<div class="modal fade" id="advanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Advance Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Advance <strong><?= $e($orderNum) ?></strong> to
                    <strong class="text-success" id="advanceNextLabel"></strong>?
                </p>
                <label class="form-label small fw-medium">
                    Notes <span class="text-muted fw-normal">(optional)</span>
                </label>
                <textarea class="form-control" id="advanceNote" rows="2"
                          placeholder="Any notes for this status change…"
                          maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="advanceConfirm">
                    <i class="bi bi-arrow-right me-1"></i>Advance
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== NOTE MODAL ==================== -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Add a note to <strong><?= $e($orderNum) ?></strong>
                </p>
                <textarea class="form-control" id="noteText" rows="3"
                          placeholder="Enter your note (min 3 characters)…"
                          maxlength="2000"></textarea>
                <div class="invalid-feedback" id="noteError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="noteConfirm">
                    <i class="bi bi-sticky me-1"></i>Save Note
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CANCEL MODAL ==================== -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-x-circle me-2"></i>Cancel Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>This action cannot be undone.</strong>
                </div>
                <p class="small mb-3">
                    Cancel order <strong><?= $e($orderNum) ?></strong>?
                </p>
                <label class="form-label small fw-medium">
                    Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="cancelReason" rows="3"
                          placeholder="Reason for cancelling (min 5 characters)…"
                          maxlength="2000"></textarea>
                <div class="invalid-feedback" id="cancelError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="cancelConfirm">
                    <i class="bi bi-x-circle me-1"></i>Cancel Order
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
    .table > :not(caption) > * > * {
        padding: .7rem .75rem;
        vertical-align: middle;
    }
    .form-control.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }

    /* Timeline */
    .timeline-list {
        list-style: none;
        padding-left: 1.5rem;
        margin: 0;
        position: relative;
    }
    .timeline-list::before {
        content: '';
        position: absolute;
        left: 6px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: #e2e8f0;
    }
    .timeline-item {
        position: relative;
        padding-bottom: 1rem;
    }
    .timeline-item:last-child { padding-bottom: 0; }
    .timeline-dot {
        position: absolute;
        left: -1.5rem;
        top: 4px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 0 0 1px #e2e8f0;
    }
    .timeline-content {
        padding-left: .5rem;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const orderId = <?= $oid ?>;

    // ==================== ADVANCE ====================
    const advanceModal    = new bootstrap.Modal(document.getElementById('advanceModal'));
    const advanceNextLbl  = document.getElementById('advanceNextLabel');
    const advanceNote     = document.getElementById('advanceNote');
    const advanceConfirm  = document.getElementById('advanceConfirm');
    let advanceStatus = '';

    window.openAdvanceModal = function (newStatus, statusLabel) {
        advanceStatus = newStatus;
        advanceNextLbl.textContent = statusLabel;
        advanceNote.value = '';
        advanceModal.show();
    };

    advanceConfirm?.addEventListener('click', async () => {
        if (!advanceStatus) return;

        advanceConfirm.disabled = true;
        const orig = advanceConfirm.innerHTML;
        advanceConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Advancing…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                status: advanceStatus,
                note: advanceNote.value.trim(),
            });

            const res = await fetch(`/staff/orders/${orderId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Status updated.', 'success');
                advanceModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                advanceConfirm.disabled = false;
                advanceConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            advanceConfirm.disabled = false;
            advanceConfirm.innerHTML = orig;
        }
    });

    // ==================== NOTE ====================
    const noteModal   = new bootstrap.Modal(document.getElementById('noteModal'));
    const noteText    = document.getElementById('noteText');
    const noteError   = document.getElementById('noteError');
    const noteConfirm = document.getElementById('noteConfirm');

    window.openNoteModal = function () {
        noteText.value = '';
        noteError.style.display = 'none';
        noteModal.show();
    };

    noteConfirm?.addEventListener('click', async () => {
        const note = noteText.value.trim();
        if (note.length < 3) {
            noteError.textContent = 'Note must be at least 3 characters.';
            noteError.style.display = 'block';
            return;
        }

        noteConfirm.disabled = true;
        const orig = noteConfirm.innerHTML;
        noteConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, note: note });

            const res = await fetch(`/staff/orders/${orderId}/note`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Note saved.', 'success');
                noteModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                noteConfirm.disabled = false;
                noteConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            noteConfirm.disabled = false;
            noteConfirm.innerHTML = orig;
        }
    });

    // ==================== CANCEL ====================
    const cancelModal   = new bootstrap.Modal(document.getElementById('cancelModal'));
    const cancelReason  = document.getElementById('cancelReason');
    const cancelError   = document.getElementById('cancelError');
    const cancelConfirm = document.getElementById('cancelConfirm');

    window.openCancelModal = function () {
        cancelReason.value = '';
        cancelError.style.display = 'none';
        cancelModal.show();
    };

    cancelConfirm?.addEventListener('click', async () => {
        const reason = cancelReason.value.trim();
        if (reason.length < 5) {
            cancelError.textContent = 'Reason must be at least 5 characters.';
            cancelError.style.display = 'block';
            return;
        }

        cancelConfirm.disabled = true;
        const orig = cancelConfirm.innerHTML;
        cancelConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Cancelling…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, status: 'cancelled', note: reason });

            const res = await fetch(`/staff/orders/${orderId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Order cancelled.', 'success');
                cancelModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                cancelConfirm.disabled = false;
                cancelConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            cancelConfirm.disabled = false;
            cancelConfirm.innerHTML = orig;
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
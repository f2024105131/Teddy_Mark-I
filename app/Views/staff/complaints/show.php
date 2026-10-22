<?php
/**
 * Staff Complaints — Detail View
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: App\Controllers\Staff\ComplaintController::show()
 *
 * Expected variables:
 *   $complaint  array  complaint row with customer/order/staff joins
 *   $order      array  linked order row with bill
 *   $existingRefund  ?array  existing refund row if any
 * ----------------------------------------------------------
 */

$currentPage = 'staff-complaints';
$title       = 'Complaint Details';

// Safe defaults
$complaint      = $complaint      ?? [];
$order          = $order          ?? null;
$existingRefund = $existingRefund ?? null;

if (empty($complaint)) {
    echo '<div class="alert alert-danger">Complaint not found.</div>';
    return;
}

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

// Extract
$cid        = (int) $complaint['complaint_id'];
$cNumber    = (string) $complaint['complaint_number'];
$cStatus    = (string) $complaint['status'];
$cType      = (string) $complaint['type'];
$cCategory  = (string) ($complaint['category_name'] ?? '');
$cDesc      = (string) $complaint['description'];
$cNotes     = (string) ($complaint['resolution_notes'] ?? '');
$cResolvedAt = $complaint['resolved_at'] ?? null;
$cCreatedAt  = $complaint['created_at']  ?? null;

// Customer
$custId    = (int) ($complaint['customer_id'] ?? 0);
$custName  = (string) ($complaint['customer_name'] ?? '');
$custEmail = (string) ($complaint['customer_email'] ?? '');
$custPhone = (string) ($complaint['customer_phone'] ?? '');

// Order
$orderId    = (int) ($complaint['order_id'] ?? 0);
$orderNum   = (string) ($complaint['order_number'] ?? '');
$orderStatus = (string) ($complaint['order_status'] ?? '');

// Staff
$assignedStaff = $complaint['assigned_staff_name'] ?? null;
$staffRowId    = (int) ($complaint['assigned_staff_id'] ?? 0);

// Type meta
$typeMeta = [
    'missing_item'          => ['label' => 'Missing Item',          'color' => 'danger',    'icon' => 'bi-box'],
    'damaged_item'          => ['label' => 'Damaged Item',          'color' => 'warning',   'icon' => 'bi-tools'],
    'late_delivery'         => ['label' => 'Late Delivery',         'color' => 'info',      'icon' => 'bi-clock-history'],
    'wrong_billing'         => ['label' => 'Wrong Billing',         'color' => 'primary',   'icon' => 'bi-receipt'],
    'poor_cleaning_quality' => ['label' => 'Poor Cleaning Quality', 'color' => 'secondary', 'icon' => 'bi-droplet'],
];
$meta = $typeMeta[$cType] ?? ['label' => ucwords(str_replace('_', ' ', $cType)), 'color' => 'secondary', 'icon' => 'bi-circle'];

// Status badges
$statusBadge = function (string $s): string {
    return match ($s) {
        'open'                => 'danger',
        'assigned'            => 'warning',
        'under_investigation' => 'info',
        'resolved'            => 'success',
        'rejected'            => 'secondary',
        'escalated'           => 'dark',
        'closed'              => 'light',
        default               => 'secondary',
    };
};
$statusLabel = function (string $s): string {
    return match ($s) {
        'open'                => 'Open',
        'assigned'            => 'Assigned',
        'under_investigation' => 'Under Investigation',
        'resolved'            => 'Resolved',
        'rejected'            => 'Rejected',
        'escalated'           => 'Escalated',
        'closed'              => 'Closed',
        default               => ucfirst(str_replace('_', ' ', $s)),
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

// Can-act flags
$isActiveStatus = in_array($cStatus, ['assigned', 'under_investigation'], true);
$canInvestigate = ($cStatus === 'assigned');
$canAddNote     = in_array($cStatus, ['assigned', 'under_investigation'], true);
$canResolve     = in_array($cStatus, ['assigned', 'under_investigation'], true);
$canReject      = in_array($cStatus, ['assigned', 'under_investigation'], true);
$canEscalate    = in_array($cStatus, ['assigned', 'under_investigation'], true);
$isTerminal     = in_array($cStatus, ['resolved', 'rejected', 'closed'], true);
$canRequestRefund = in_array($cStatus, ['under_investigation', 'resolved', 'escalated'], true);

// Parse investigation notes for timeline
// The controller appends "ESCALATED" / "REFUND REQUESTED" / "CANCELLED" entries
$noteEntries = [];
if ($cNotes !== '') {
    // Split by newline that starts with "[" timestamp
    $lines = preg_split('/(?=\n\[\d{4}-\d{2}-\d{2})/', "\n" . $cNotes);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $noteEntries[] = $line;
    }
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/staff/complaints" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                <span><?= $e($cNumber) ?></span>
                <span class="badge bg-<?= $statusBadge($cStatus) ?>">
                    <?= $e($statusLabel($cStatus)) ?>
                </span>
            </h1>
            <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                <span>#<?= $cid ?></span>
                <span>·</span>
                <span class="badge bg-<?= $meta['color'] ?>-subtle text-<?= $meta['color'] ?>">
                    <?= $e($meta['label']) ?>
                </span>
                <?php if ($cCategory !== ''): ?>
                    <span>·</span>
                    <span><i class="bi bi-tag me-1"></i><?= $e($cCategory) ?></span>
                <?php endif; ?>
                <span>·</span>
                <span>Filed <?= $e($fmtDate($cCreatedAt)) ?></span>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="tel:<?= $e($custPhone) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-telephone me-1"></i>Call Customer
        </a>
        <?php if ($canInvestigate): ?>
            <button type="button" class="btn btn-info text-white btn-sm" onclick="openInvestigateModal()">
                <i class="bi bi-search me-1"></i>Start Investigation
            </button>
        <?php endif; ?>
        <?php if ($canResolve): ?>
            <button type="button" class="btn btn-success btn-sm" onclick="openResolveModal()">
                <i class="bi bi-check-circle me-1"></i>Resolve
            </button>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($canAddNote): ?>
                    <li>
                        <button type="button" class="dropdown-item" onclick="openNoteModal()">
                            <i class="bi bi-sticky me-2"></i>Add Note
                        </button>
                    </li>
                <?php endif; ?>
                <?php if ($canReject): ?>
                    <li>
                        <button type="button" class="dropdown-item text-secondary" onclick="openRejectModal()">
                            <i class="bi bi-x-circle me-2"></i>Reject
                        </button>
                    </li>
                <?php endif; ?>
                <?php if ($canEscalate): ?>
                    <li>
                        <button type="button" class="dropdown-item text-danger" onclick="openEscalateModal()">
                            <i class="bi bi-shield-exclamation me-2"></i>Escalate to Admin
                        </button>
                    </li>
                <?php endif; ?>
                <?php if ($canRequestRefund && !$existingRefund): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item text-warning" onclick="openRefundModal()">
                            <i class="bi bi-cash-coin me-2"></i>Request Refund
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

        <!-- ============ COMPLAINT DETAILS ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-chat-left-text me-2 text-primary"></i>Complaint Details
                </h6>
                <span class="text-muted small"><?= $e($fmtDateTime($cCreatedAt)) ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Type</div>
                        <span class="badge bg-<?= $meta['color'] ?>">
                            <?= $e($meta['label']) ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Category</div>
                        <div><?= $cCategory !== '' ? $e($cCategory) : '<span class="text-muted fst-italic">Uncategorized</span>' ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Age</div>
                        <?php
                        $hours = (time() - strtotime($cCreatedAt)) / 3600;
                        $ageStr = $hours < 24
                            ? max(1, (int) $hours) . ' hour' . ((int) $hours === 1 ? '' : 's')
                            : floor($hours / 24) . ' day' . ((int) floor($hours / 24) === 1 ? '' : 's');
                        ?>
                        <div class="small"><?= $e($ageStr) ?> ago</div>
                    </div>
                </div>

                <div>
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Description</div>
                    <div class="bg-light rounded p-3">
                        <?= nl2br($e($cDesc)) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ ORDER CONTEXT ============ -->
        <?php if ($order): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-bag-check me-2 text-primary"></i>Order Context
                    </h6>
                    <a href="/staff/orders/<?= $orderId ?>" class="small text-decoration-none">
                        View full order <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body pt-0">
                    <div class="info-row">
                        <div class="info-label">Order #</div>
                        <div class="info-value">
                            <a href="/staff/orders/<?= $orderId ?>" class="text-decoration-none fw-medium">
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
                    <?php if (!empty($order['bill_number'])): ?>
                        <div class="info-row">
                            <div class="info-label">Bill #</div>
                            <div class="info-value"><code><?= $e($order['bill_number']) ?></code></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Total</div>
                            <div class="info-value small fw-medium"><?= $fmtMoney($order['total_amount'] ?? 0) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Payment</div>
                            <div class="info-value small">
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <?= $e(ucwords(str_replace('_', ' ', $order['payment_status'] ?? 'unpaid'))) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ INVESTIGATION LOG ============ -->
        <?php if (!empty($noteEntries)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-journal-text me-2 text-primary"></i>Investigation Log
                    </h6>
                </div>
                <div class="card-body">
                    <div class="investigation-log">
                        <?php foreach ($noteEntries as $entry): ?>
                            <div class="log-entry"><?= nl2br($e($entry)) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ==================== RIGHT COLUMN ==================== -->
    <div class="col-lg-4">

        <!-- ============ STATUS CARD ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Status
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Current</div>
                    <div class="info-value">
                        <span class="badge bg-<?= $statusBadge($cStatus) ?>">
                            <?= $e($statusLabel($cStatus)) ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Assigned To</div>
                    <div class="info-value small">
                        <?php if ($assignedStaff): ?>
                            <span class="badge bg-info-subtle text-info">
                                <i class="bi bi-person"></i> <?= $e($assignedStaff) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning">Unassigned</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($cResolvedAt): ?>
                    <div class="info-row">
                        <div class="info-label">Resolved</div>
                        <div class="info-value small"><?= $e($fmtDateTime($cResolvedAt)) ?></div>
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

        <!-- ============ EXISTING REFUND ============ -->
        <?php if ($existingRefund): ?>
            <div class="card border-0 shadow-sm mb-3 border-start border-warning border-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold text-warning">
                        <i class="bi bi-cash-coin me-2"></i>Refund Request
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="info-row">
                        <div class="info-label">Amount</div>
                        <div class="info-value fw-medium"><?= $fmtMoney($existingRefund['amount']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="badge bg-<?=
                                match ($existingRefund['status']) {
                                    'pending'   => 'warning',
                                    'approved'  => 'info',
                                    'completed' => 'success',
                                    'rejected'  => 'danger',
                                    default     => 'secondary',
                                }
                            ?>">
                                <?= $e(ucfirst($existingRefund['status'])) ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Requested</div>
                        <div class="info-value small"><?= $e($fmtDate($existingRefund['created_at'])) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ ACTIONS ============ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-lightning-charge me-2 text-primary"></i>Actions
                </h6>
            </div>
            <div class="card-body pt-0">
                <?php if ($canInvestigate): ?>
                    <button type="button" class="btn btn-info text-white w-100 mb-2"
                            onclick="openInvestigateModal()">
                        <i class="bi bi-search me-1"></i>Start Investigation
                    </button>
                <?php endif; ?>

                <?php if ($canAddNote): ?>
                    <button type="button" class="btn btn-outline-secondary w-100 mb-2"
                            onclick="openNoteModal()">
                        <i class="bi bi-sticky me-1"></i>Add Note
                    </button>
                <?php endif; ?>

                <?php if ($canResolve): ?>
                    <button type="button" class="btn btn-success w-100 mb-2"
                            onclick="openResolveModal()">
                        <i class="bi bi-check-circle me-1"></i>Resolve
                    </button>
                <?php endif; ?>

                <?php if ($canReject): ?>
                    <button type="button" class="btn btn-outline-secondary w-100 mb-2"
                            onclick="openRejectModal()">
                        <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                <?php endif; ?>

                <?php if ($canEscalate): ?>
                    <button type="button" class="btn btn-outline-danger w-100 mb-2"
                            onclick="openEscalateModal()">
                        <i class="bi bi-shield-exclamation me-1"></i>Escalate to Admin
                    </button>
                <?php endif; ?>

                <?php if ($canRequestRefund && !$existingRefund): ?>
                    <button type="button" class="btn btn-outline-warning w-100 mb-2"
                            onclick="openRefundModal()">
                        <i class="bi bi-cash-coin me-1"></i>Request Refund
                    </button>
                <?php endif; ?>

                <?php if ($isTerminal): ?>
                    <div class="alert alert-success small mb-0 py-2">
                        <i class="bi bi-check-circle me-1"></i>
                        This complaint is <strong><?= $e($statusLabel($cStatus)) ?></strong>.
                        No further actions available.
                    </div>
                <?php endif; ?>

                <?php if ($cStatus === 'escalated'): ?>
                    <div class="alert alert-dark small mb-0 py-2">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        This complaint has been escalated to admin for review.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== INVESTIGATE MODAL ==================== -->
<div class="modal fade" id="investigateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-search me-2"></i>Start Investigation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Start investigating <strong><?= $e($cNumber) ?></strong>?
                </p>
                <div class="alert alert-info small mb-0 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    This moves the complaint to <strong>Under Investigation</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info text-white" id="investigateConfirm">Start</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== NOTE MODAL ==================== -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-sticky me-2"></i>Add Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" id="noteText" rows="3"
                          placeholder="Enter your note (min 5 characters)…"
                          maxlength="2000"></textarea>
                <div class="invalid-feedback" id="noteError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="noteConfirm">Save Note</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RESOLVE MODAL ==================== -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Resolve Complaint</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Resolve <strong><?= $e($cNumber) ?></strong>. The customer will be notified.
                </p>
                <label class="form-label small fw-medium">
                    Resolution Notes <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="resolveNotes" rows="4"
                          placeholder="Describe how the issue was resolved (min 10 characters)…"
                          maxlength="2000"></textarea>
                <div class="invalid-feedback" id="resolveError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="resolveConfirm">Resolve</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REJECT MODAL ==================== -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Complaint</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Reject <strong><?= $e($cNumber) ?></strong>. The customer will see the reason.
                </p>
                <label class="form-label small fw-medium">
                    Rejection Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="rejectReason" rows="4"
                          placeholder="Explain why this complaint is being rejected (min 10 characters)…"
                          maxlength="2000"></textarea>
                <div class="invalid-feedback" id="rejectError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-secondary" id="rejectConfirm">Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== ESCALATE MODAL ==================== -->
<div class="modal fade" id="escalateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-shield-exclamation me-2"></i>Escalate to Admin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Escalate <strong><?= $e($cNumber) ?></strong> for admin review.
                </p>
                <label class="form-label small fw-medium">
                    Escalation Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="escalateReason" rows="4"
                          placeholder="Why is this being escalated? (min 10 characters)…"
                          maxlength="2000"></textarea>
                <div class="invalid-feedback" id="escalateError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="escalateConfirm">Escalate</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REFUND MODAL ==================== -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Request Refund</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Submit a refund request for admin approval. The original payment must be <strong>approved</strong>.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">
                        Refund Amount (Rs.) <span class="text-danger">*</span>
                    </label>
                    <input type="number"
                           class="form-control"
                           id="refundAmount"
                           min="0.01"
                           step="0.01"
                           placeholder="0.00">
                    <div class="invalid-feedback" id="refundAmountError"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-medium">
                        Reason <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="refundReason" rows="3"
                              placeholder="Explain why a refund is appropriate (min 10 characters)…"
                              maxlength="2000"></textarea>
                    <div class="invalid-feedback" id="refundReasonError"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="refundConfirm">Submit Request</button>
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

    /* Investigation log */
    .investigation-log {
        font-family: 'Monaco', 'Menlo', monospace;
        font-size: .82rem;
    }
    .log-entry {
        padding: .75rem;
        border-left: 3px solid #0ea5e9;
        background: #f8fafc;
        border-radius: 0 6px 6px 0;
        margin-bottom: .6rem;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .log-entry:last-child { margin-bottom: 0; }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const complaintId = <?= $cid ?>;

    // ==================== INVESTIGATE ====================
    const investigateModal   = new bootstrap.Modal(document.getElementById('investigateModal'));
    const investigateConfirm = document.getElementById('investigateConfirm');

    window.openInvestigateModal = function () {
        investigateModal.show();
    };

    investigateConfirm?.addEventListener('click', async () => {
        investigateConfirm.disabled = true;
        const orig = investigateConfirm.innerHTML;
        investigateConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>…';

        try {
            const res = await fetch(`/staff/complaints/${complaintId}/investigate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Investigation started.', 'success');
                investigateModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                investigateConfirm.disabled = false;
                investigateConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            investigateConfirm.disabled = false;
            investigateConfirm.innerHTML = orig;
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
        if (note.length < 5) {
            noteError.textContent = 'Note must be at least 5 characters.';
            noteError.style.display = 'block';
            return;
        }

        noteConfirm.disabled = true;
        const orig = noteConfirm.innerHTML;
        noteConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            const res = await fetch(`/staff/complaints/${complaintId}/note`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, note: note }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Note added.', 'success');
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

    // ==================== RESOLVE ====================
    const resolveModal  = new bootstrap.Modal(document.getElementById('resolveModal'));
    const resolveNotes  = document.getElementById('resolveNotes');
    const resolveError  = document.getElementById('resolveError');
    const resolveConfirm = document.getElementById('resolveConfirm');

    window.openResolveModal = function () {
        resolveNotes.value = '';
        resolveError.style.display = 'none';
        resolveModal.show();
    };

    resolveConfirm?.addEventListener('click', async () => {
        const notes = resolveNotes.value.trim();
        if (notes.length < 10) {
            resolveError.textContent = 'Resolution notes must be at least 10 characters.';
            resolveError.style.display = 'block';
            return;
        }

        resolveConfirm.disabled = true;
        const orig = resolveConfirm.innerHTML;
        resolveConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Resolving…';

        try {
            const res = await fetch(`/staff/complaints/${complaintId}/resolve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, resolution_notes: notes }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Complaint resolved.', 'success');
                resolveModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                resolveConfirm.disabled = false;
                resolveConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            resolveConfirm.disabled = false;
            resolveConfirm.innerHTML = orig;
        }
    });

    // ==================== REJECT ====================
    const rejectModal  = new bootstrap.Modal(document.getElementById('rejectModal'));
    const rejectReason = document.getElementById('rejectReason');
    const rejectError  = document.getElementById('rejectError');
    const rejectConfirm = document.getElementById('rejectConfirm');

    window.openRejectModal = function () {
        rejectReason.value = '';
        rejectError.style.display = 'none';
        rejectModal.show();
    };

    rejectConfirm?.addEventListener('click', async () => {
        const reason = rejectReason.value.trim();
        if (reason.length < 10) {
            rejectError.textContent = 'Rejection reason must be at least 10 characters.';
            rejectError.style.display = 'block';
            return;
        }

        rejectConfirm.disabled = true;
        const orig = rejectConfirm.innerHTML;
        rejectConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rejecting…';

        try {
            const res = await fetch(`/staff/complaints/${complaintId}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, reason: reason }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Complaint rejected.', 'success');
                rejectModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                rejectConfirm.disabled = false;
                rejectConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            rejectConfirm.disabled = false;
            rejectConfirm.innerHTML = orig;
        }
    });

    // ==================== ESCALATE ====================
    const escalateModal  = new bootstrap.Modal(document.getElementById('escalateModal'));
    const escalateReason = document.getElementById('escalateReason');
    const escalateError  = document.getElementById('escalateError');
    const escalateConfirm = document.getElementById('escalateConfirm');

    window.openEscalateModal = function () {
        escalateReason.value = '';
        escalateError.style.display = 'none';
        escalateModal.show();
    };

    escalateConfirm?.addEventListener('click', async () => {
        const reason = escalateReason.value.trim();
        if (reason.length < 10) {
            escalateError.textContent = 'Escalation reason must be at least 10 characters.';
            escalateError.style.display = 'block';
            return;
        }

        escalateConfirm.disabled = true;
        const orig = escalateConfirm.innerHTML;
        escalateConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Escalating…';

        try {
            const res = await fetch(`/staff/complaints/${complaintId}/escalate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, reason: reason }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Complaint escalated.', 'success');
                escalateModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                escalateConfirm.disabled = false;
                escalateConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            escalateConfirm.disabled = false;
            escalateConfirm.innerHTML = orig;
        }
    });

    // ==================== REFUND ====================
    const refundModal       = new bootstrap.Modal(document.getElementById('refundModal'));
    const refundAmount      = document.getElementById('refundAmount');
    const refundAmountError = document.getElementById('refundAmountError');
    const refundReason      = document.getElementById('refundReason');
    const refundReasonError = document.getElementById('refundReasonError');
    const refundConfirm     = document.getElementById('refundConfirm');

    window.openRefundModal = function () {
        refundAmount.value = '';
        refundAmountError.style.display = 'none';
        refundReason.value = '';
        refundReasonError.style.display = 'none';
        refundModal.show();
    };

    refundConfirm?.addEventListener('click', async () => {
        const amount = parseFloat(refundAmount.value);
        const reason = refundReason.value.trim();
        let hasError = false;

        if (!amount || amount <= 0) {
            refundAmountError.textContent = 'Amount must be greater than 0.';
            refundAmountError.style.display = 'block';
            hasError = true;
        }

        if (reason.length < 10) {
            refundReasonError.textContent = 'Reason must be at least 10 characters.';
            refundReasonError.style.display = 'block';
            hasError = true;
        }

        if (hasError) return;

        refundConfirm.disabled = true;
        const orig = refundConfirm.innerHTML;
        refundConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting…';

        try {
            const res = await fetch(`/staff/complaints/${complaintId}/request-refund`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({
                    _csrf: csrf,
                    amount: amount.toFixed(2),
                    reason: reason,
                }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Refund requested.', 'success');
                refundModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                refundConfirm.disabled = false;
                refundConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            refundConfirm.disabled = false;
            refundConfirm.innerHTML = orig;
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
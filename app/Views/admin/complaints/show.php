<?php
/**
 * Admin Complaints — Detail View
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ComplaintController::show()
 *
 * Expected variables:
 *   $complaint   array   complaint row with joined customer/order/category/staff
 *                        complaint_id, complaint_number, customer_id, order_id,
 *                        category_id, type, description, status,
 *                        assigned_staff_id, resolution_notes, resolved_at,
 *                        created_at, updated_at,
 *                        customer_name, customer_email, customer_phone,
 *                        address_house, address_street, address_area, city, landmark,
 *                        order_number, order_status, order_date, order_delivered_at,
 *                        category_name, assigned_staff_name
 *   $order       array   order row with optional bill join
 *   $orderItems  array   order item rows
 *   $payments    array   payment rows
 *   $refunds     array   refund rows (with approved_by_name)
 *   $staffList   array   active staff for assignment
 *   $typeLabel   string  human label for this complaint's type
 * ----------------------------------------------------------
 */

$currentPage = 'complaints';
$title       = 'Complaint Details';

// Safe defaults
$complaint  = $complaint  ?? [];
$order      = $order      ?? null;
$orderItems = $orderItems ?? [];
$payments   = $payments   ?? [];
$refunds    = $refunds    ?? [];
$staffList  = $staffList  ?? [];
$typeLabel  = $typeLabel  ?? '';

if (empty($complaint)) {
    echo '<div class="alert alert-danger">Complaint not found.</div>';
    return;
}

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

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

// Extract
$cid        = (int) $complaint['complaint_id'];
$cNumber    = (string) $complaint['complaint_number'];
$cType      = (string) $complaint['type'];
$cStatus    = (string) $complaint['status'];
$cDesc      = (string) $complaint['description'];
$cCategory  = (string) ($complaint['category_name'] ?? '');
$cResolved  = $complaint['resolution_notes'] ?? '';
$resolvedAt = $complaint['resolved_at'] ?? null;
$createdAt  = $complaint['created_at'] ?? null;
$updatedAt  = $complaint['updated_at'] ?? null;

// Customer
$custId     = (int) $complaint['customer_id'];
$custName   = (string) $complaint['customer_name'];
$custEmail  = (string) $complaint['customer_email'];
$custPhone  = (string) $complaint['customer_phone'];
$custHouse  = (string) ($complaint['address_house'] ?? '');
$custStreet = (string) ($complaint['address_street'] ?? '');
$custArea   = (string) ($complaint['address_area'] ?? '');
$custCity   = (string) ($complaint['city'] ?? '');
$custLandmark = (string) ($complaint['landmark'] ?? '');

// Order
$orderId    = (int) $complaint['order_id'];
$orderNum   = (string) $complaint['order_number'];
$orderStatus = (string) ($complaint['order_status'] ?? '');
$orderDate  = $complaint['order_date'] ?? null;
$orderDeliveredAt = $complaint['order_delivered_at'] ?? null;

// Staff
$staffName = $complaint['assigned_staff_name'] ?? null;
$staffRowId = (int) ($complaint['assigned_staff_id'] ?? 0);

// Badges
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
$typeBadge = function (string $t): string {
    return match ($t) {
        'missing_item'          => 'danger',
        'damaged_item'          => 'warning',
        'late_delivery'         => 'info',
        'wrong_billing'         => 'primary',
        'poor_cleaning_quality' => 'secondary',
        default                 => 'secondary',
    };
};
$orderStatusBadge = function (string $s): string {
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
$paymentBadge = function (string $s): string {
    return match ($s) {
        'paid'           => 'success',
        'partially_paid' => 'warning',
        'unpaid'         => 'danger',
        'refunded'       => 'secondary',
        default          => 'secondary',
    };
};
$refundBadge = function (string $s): string {
    return match ($s) {
        'pending'   => 'warning',
        'approved'  => 'info',
        'completed' => 'success',
        'rejected'  => 'danger',
        default     => 'secondary',
    };
};

// UI state
$isTerminal = in_array($cStatus, ['resolved', 'rejected', 'closed'], true);
$canAssign  = in_array($cStatus, ['open', 'escalated', 'assigned', 'under_investigation'], true);
$canResolve = !$isTerminal;
$canReject  = !$inTerminal = !$isTerminal;
$canClose   = in_array($cStatus, ['resolved', 'rejected'], true);
$canEscalate = in_array($cStatus, ['open', 'assigned', 'under_investigation'], true);

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/complaints" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                <?= $e($cNumber) ?>
                <span class="badge bg-<?= $statusBadge($cStatus) ?>">
                    <?= $e($statusLabel($cStatus)) ?>
                </span>
                <?php if (in_array($cStatus, ['escalated', 'open'], true)): ?>
                    <span class="badge bg-danger-subtle text-danger">
                        <i class="bi bi-exclamation-circle-fill me-1"></i>Urgent
                    </span>
                <?php endif; ?>
            </h1>
            <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                <span>#<?= $cid ?></span>
                <span>·</span>
                <span>Filed <?= $e($timeAgo($createdAt)) ?></span>
                <span>·</span>
                <span class="badge bg-<?= $typeBadge($cType) ?>">
                    <?= $e($typeLabel ?: ucwords(str_replace('_', ' ', $cType))) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canAssign): ?>
            <button type="button" class="btn btn-outline-primary btn-sm"
                    onclick="openAssignModal()">
                <i class="bi bi-person-plus me-1"></i>
                <?= $staffName ? 'Reassign' : 'Assign' ?>
            </button>
        <?php endif; ?>
        <?php if ($canResolve): ?>
            <button type="button" class="btn btn-success btn-sm"
                    onclick="openResolveModal()">
                <i class="bi bi-check-circle me-1"></i>Resolve
            </button>
        <?php endif; ?>
        <?php if ($canReject): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    onclick="openRejectModal()">
                <i class="bi bi-x-circle me-1"></i>Reject
            </button>
        <?php endif; ?>
        <?php if ($canClose): ?>
            <button type="button" class="btn btn-dark btn-sm"
                    onclick="openCloseModal()">
                <i class="bi bi-lock me-1"></i>Close
            </button>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item"
                       href="/admin/refunds?search=<?= urlencode($cNumber) ?>">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>View Refunds
                    </a>
                </li>
                <?php if ($canEscalate): ?>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                                onclick="openEscalateModal()">
                            <i class="bi bi-shield-exclamation me-2"></i>Escalate
                        </button>
                    </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="mailto:<?= $e($custEmail) ?>">
                        <i class="bi bi-envelope me-2"></i>Email Customer
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="tel:<?= $e($custPhone) ?>">
                        <i class="bi bi-telephone me-2"></i>Call Customer
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

        <!-- ============ COMPLAINT DESCRIPTION ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-chat-left-text me-2 text-primary"></i>Complaint Details
                </h6>
                <span class="text-muted small"><?= $e($fmtDateTime($createdAt)) ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Type</div>
                        <span class="badge bg-<?= $typeBadge($cType) ?>">
                            <?= $e($typeLabel ?: ucwords(str_replace('_', ' ', $cType))) ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Category</div>
                        <div><?= $cCategory !== '' ? $e($cCategory) : '<span class="text-muted fst-italic">Uncategorized</span>' ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Assigned To</div>
                        <?php if ($staffName): ?>
                            <span class="badge bg-info-subtle text-info">
                                <i class="bi bi-person"></i> <?= $e($staffName) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning">Unassigned</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-0">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Description</div>
                    <div class="bg-light rounded p-3">
                        <?= nl2br($e($cDesc)) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ RESOLUTION (if resolved/rejected) ============ -->
        <?php if ($isTerminal && $cResolved !== ''): ?>
            <div class="card border-0 shadow-sm mb-3 border-start border-<?= $cStatus === 'resolved' ? 'success' : 'secondary' ?> border-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold text-<?= $cStatus === 'resolved' ? 'success' : 'secondary' ?>">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= $cStatus === 'resolved' ? 'Resolution' : ($cStatus === 'rejected' ? 'Rejection Reason' : 'Outcome') ?>
                    </h6>
                    <?php if ($resolvedAt): ?>
                        <span class="text-muted small"><?= $e($fmtDateTime($resolvedAt)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="bg-light rounded p-3">
                        <?= nl2br($e($cResolved)) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ ORDER ITEMS ============ -->
        <?php if (!empty($orderItems)): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-basket me-2 text-primary"></i>Order Items
                        <span class="text-muted fw-normal">(<?= count($orderItems) ?>)</span>
                    </h6>
                    <a href="/admin/reports/orders?highlight=<?= $orderId ?>"
                       class="small text-decoration-none">
                        View full order <i class="bi bi-arrow-right"></i>
                    </a>
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
                            <?php foreach ($orderItems as $oi): ?>
                                <tr>
                                    <td class="small fw-medium"><?= $e($oi['item_name']) ?></td>
                                    <td class="small text-muted"><?= $e($oi['service_name']) ?></td>
                                    <td class="text-center small"><?= $fmtInt($oi['quantity']) ?></td>
                                    <td class="text-end small text-muted">
                                        <?= $fmtMoney($oi['unit_price']) ?>
                                    </td>
                                    <td class="text-end small fw-medium">
                                        <?= $fmtMoney($oi['total_price']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ PAYMENTS ============ -->
        <?php if (!empty($payments)): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-credit-card me-2 text-primary"></i>Payments
                        <span class="text-muted fw-normal">(<?= count($payments) ?>)</span>
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-muted fw-semibold">Method</th>
                                <th class="small text-muted fw-semibold">Transaction</th>
                                <th class="small text-muted fw-semibold text-end">Amount</th>
                                <th class="small text-muted fw-semibold text-center">Status</th>
                                <th class="small text-muted fw-semibold">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p):
                                $pStatus = (string) $p['status'];
                                $pBadge = match ($pStatus) {
                                    'approved'             => 'success',
                                    'pending_verification' => 'warning',
                                    'verified'             => 'info',
                                    'rejected'             => 'danger',
                                    'refunded'             => 'secondary',
                                    default                => 'secondary',
                                };
                            ?>
                                <tr>
                                    <td class="small">
                                        <i class="bi bi-cash-coin me-1 text-muted"></i>
                                        <?= $e(ucwords(str_replace('_', ' ', $p['payment_method']))) ?>
                                    </td>
                                    <td class="small text-muted text-truncate"
                                        style="max-width: 180px;">
                                        <?= $e($p['transaction_id'] ?: '—') ?>
                                    </td>
                                    <td class="text-end small fw-medium">
                                        <?= $fmtMoney($p['amount']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $pBadge ?>-subtle text-<?= $pBadge ?>">
                                            <?= $e(ucwords(str_replace('_', ' ', $pStatus))) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= $e($fmtDate($p['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============ REFUNDS ============ -->
        <?php if (!empty($refunds)): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-arrow-counterclockwise me-2 text-primary"></i>Refunds
                        <span class="text-muted fw-normal">(<?= count($refunds) ?>)</span>
                    </h6>
                    <a href="/admin/refunds?search=<?= urlencode($cNumber) ?>"
                       class="small text-decoration-none">
                        Full queue <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-muted fw-semibold">Amount</th>
                                <th class="small text-muted fw-semibold">Reason</th>
                                <th class="small text-muted fw-semibold text-center">Status</th>
                                <th class="small text-muted fw-semibold">Approved By</th>
                                <th class="small text-muted fw-semibold">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($refunds as $r):
                                $rStatus = (string) $r['status'];
                            ?>
                                <tr>
                                    <td class="small fw-medium">
                                        <?= $fmtMoney($r['amount']) ?>
                                    </td>
                                    <td class="small text-muted text-truncate"
                                        style="max-width: 260px;"
                                        title="<?= $e($r['refund_reason']) ?>">
                                        <?= $e($r['refund_reason']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $refundBadge($rStatus) ?>">
                                            <?= $e(ucfirst($rStatus)) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= $e($r['approved_by_name'] ?? '—') ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= $e($fmtDate($r['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ==================== RIGHT COLUMN ==================== -->
    <div class="col-lg-4">

        <!-- ============ CUSTOMER ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person me-2 text-primary"></i>Customer
                </h6>
                <a href="/admin/customers/<?= $custId ?>"
                   class="small text-decoration-none">
                    Profile <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="avatar-circle bg-primary-subtle text-primary">
                        <?= $e(strtoupper(substr($custName, 0, 1))) ?>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-medium text-truncate"><?= $e($custName) ?></div>
                        <div class="text-muted small">
                            #<?= $custId ?>
                        </div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value small">
                        <a href="mailto:<?= $e($custEmail) ?>"
                           class="text-decoration-none text-truncate d-block"
                           style="max-width: 180px;">
                            <?= $e($custEmail) ?>
                        </a>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone</div>
                    <div class="info-value small">
                        <a href="tel:<?= $e($custPhone) ?>"
                           class="text-decoration-none">
                            <?= $e($custPhone) ?>
                        </a>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Address</div>
                    <div class="info-value small">
                        <?= $e($custHouse) ?>,
                        <?= $e($custStreet) ?>
                        <br>
                        <?= $e($custArea) ?>, <?= $e($custCity) ?>
                        <?php if ($custLandmark !== ''): ?>
                            <div class="text-muted" style="font-size:.72rem;">
                                Near <?= $e($custLandmark) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ ORDER ============ -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bag-check me-2 text-primary"></i>Order
                </h6>
                <a href="/admin/reports/orders?highlight=<?= $orderId ?>"
                   class="small text-decoration-none">
                    View <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Order #</div>
                    <div class="info-value">
                        <code><?= $e($orderNum) ?></code>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="badge bg-<?= $orderStatusBadge($orderStatus) ?>">
                            <?= $e($orderStatusLabel($orderStatus)) ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Ordered</div>
                    <div class="info-value small"><?= $e($fmtDate($orderDate)) ?></div>
                </div>
                <?php if ($orderDeliveredAt): ?>
                    <div class="info-row">
                        <div class="info-label">Delivered</div>
                        <div class="info-value small"><?= $e($fmtDateTime($orderDeliveredAt)) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($order && !empty($order['bill_number'])): ?>
                    <div class="info-row">
                        <div class="info-label">Bill</div>
                        <div class="info-value small"><code><?= $e($order['bill_number']) ?></code></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Total</div>
                        <div class="info-value small fw-medium">
                            <?= $fmtMoney($order['total_amount'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Payment</div>
                        <div class="info-value">
                            <span class="badge bg-<?= $paymentBadge($order['payment_status'] ?? 'unpaid') ?>-subtle text-<?= $paymentBadge($order['payment_status'] ?? 'unpaid') ?>">
                                <?= $e(ucwords(str_replace('_', ' ', $order['payment_status'] ?? 'unpaid'))) ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($order['outstanding_amount']) && (float) $order['outstanding_amount'] > 0): ?>
                        <div class="info-row">
                            <div class="info-label">Outstanding</div>
                            <div class="info-value small text-danger fw-medium">
                                <?= $fmtMoney($order['outstanding_amount']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============ TIMELINE ============ -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Timeline
                </h6>
            </div>
            <div class="card-body pt-2">
                <ul class="timeline-list">
                    <li class="timeline-item">
                        <div class="timeline-dot bg-primary"></div>
                        <div class="timeline-content">
                            <div class="fw-medium small">Complaint filed</div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <?= $e($fmtDateTime($createdAt)) ?>
                            </div>
                        </div>
                    </li>
                    <?php if ($staffName): ?>
                        <li class="timeline-item">
                            <div class="timeline-dot bg-info"></div>
                            <div class="timeline-content">
                                <div class="fw-medium small">Assigned to <?= $e($staffName) ?></div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    Status: Assigned
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array($cStatus, ['under_investigation', 'resolved', 'rejected', 'closed'], true)): ?>
                        <li class="timeline-item">
                            <div class="timeline-dot bg-warning"></div>
                            <div class="timeline-content">
                                <div class="fw-medium small">Investigation</div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    Under review by staff
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>
                    <?php if ($cStatus === 'escalated'): ?>
                        <li class="timeline-item">
                            <div class="timeline-dot bg-dark"></div>
                            <div class="timeline-content">
                                <div class="fw-medium small">Escalated to admin</div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    Awaiting admin action
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>
                    <?php if ($isTerminal && $resolvedAt): ?>
                        <li class="timeline-item">
                            <div class="timeline-dot bg-<?= $cStatus === 'resolved' ? 'success' : 'secondary' ?>"></div>
                            <div class="timeline-content">
                                <div class="fw-medium small">
                                    <?= $cStatus === 'resolved' ? 'Resolved' : ucfirst($cStatus) ?>
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    <?= $e($fmtDateTime($resolvedAt)) ?>
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ==================== ASSIGN MODAL ==================== -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= $staffName ? 'Reassign' : 'Assign' ?> Complaint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Assign <strong><?= $e($cNumber) ?></strong> to a staff member:
                </p>
                <select class="form-select" id="assignStaffSelect">
                    <option value="">— Select staff —</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['staff_id'] ?>"
                                <?= ((int) $s['staff_id'] === $staffRowId) ? 'selected' : '' ?>>
                            <?= $e($s['full_name']) ?>
                            (<?= $e(ucfirst($s['role'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="assignConfirm">
                    <i class="bi bi-person-plus me-1"></i><?= $staffName ? 'Reassign' : 'Assign' ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RESOLVE MODAL ==================== -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success">
                    <i class="bi bi-check-circle me-2"></i>Resolve Complaint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Resolve <strong><?= $e($cNumber) ?></strong>. The customer will be notified.
                </p>
                <label class="form-label small fw-medium">
                    Resolution Notes <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="resolveNotes" rows="4"
                          placeholder="Describe how the issue was resolved (min 10 characters)…"></textarea>
                <div class="invalid-feedback" id="resolveError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="resolveConfirm">
                    <i class="bi bi-check-circle me-1"></i>Resolve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REJECT MODAL ==================== -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-secondary">
                    <i class="bi bi-x-circle me-2"></i>Reject Complaint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reject <strong><?= $e($cNumber) ?></strong>. The customer will see the reason.
                </p>
                <label class="form-label small fw-medium">
                    Rejection Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="rejectReason" rows="4"
                          placeholder="Explain why this complaint is being rejected (min 10 characters)…"></textarea>
                <div class="invalid-feedback" id="rejectError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-secondary" id="rejectConfirm">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CLOSE MODAL ==================== -->
<div class="modal fade" id="closeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Close Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-0">
                    Close <strong><?= $e($cNumber) ?></strong>?
                    <br><br>
                    <span class="text-muted small">
                        Closing a complaint marks it as fully handled.
                        It cannot be reopened.
                    </span>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" id="closeConfirm">
                    <i class="bi bi-lock me-1"></i>Close Complaint
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== ESCALATE MODAL ==================== -->
<div class="modal fade" id="escalateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-shield-exclamation me-2"></i>Escalate Complaint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Escalate <strong><?= $e($cNumber) ?></strong> for admin review.
                </p>
                <label class="form-label small fw-medium">
                    Escalation Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="escalateReason" rows="4"
                          placeholder="Why is this being escalated? (min 10 characters)…"></textarea>
                <div class="invalid-feedback" id="escalateError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="escalateConfirm">
                    <i class="bi bi-shield-exclamation me-1"></i>Escalate
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
        min-width: 90px;
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

    .form-control.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const complaintId = <?= $cid ?>;

    // ==================== ASSIGN ====================
    const assignModal  = new bootstrap.Modal(document.getElementById('assignModal'));
    const assignSelect = document.getElementById('assignStaffSelect');
    const assignConf   = document.getElementById('assignConfirm');

    window.openAssignModal = function () {
        assignModal.show();
    };

    assignConf?.addEventListener('click', async () => {
        const staffId = parseInt(assignSelect.value, 10);
        if (!staffId) {
            showToast('Please select a staff member.', 'danger');
            return;
        }
        assignConf.disabled = true;
        const orig = assignConf.innerHTML;
        assignConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Assigning…';

        try {
            const res = await fetch(`/admin/complaints/${complaintId}/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, staff_id: staffId }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Assigned.', 'success');
                assignModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                assignConf.disabled = false;
                assignConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            assignConf.disabled = false;
            assignConf.innerHTML = orig;
        }
    });

    // ==================== RESOLVE ====================
    const resolveModal = new bootstrap.Modal(document.getElementById('resolveModal'));
    const resolveNotes = document.getElementById('resolveNotes');
    const resolveError = document.getElementById('resolveError');
    const resolveConf  = document.getElementById('resolveConfirm');

    window.openResolveModal = function () {
        resolveNotes.value = '';
        resolveError.style.display = 'none';
        resolveModal.show();
    };

    resolveConf?.addEventListener('click', async () => {
        const notes = resolveNotes.value.trim();
        if (notes.length < 10) {
            resolveError.textContent = 'Resolution notes must be at least 10 characters.';
            resolveError.style.display = 'block';
            return;
        }
        resolveConf.disabled = true;
        const orig = resolveConf.innerHTML;
        resolveConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Resolving…';

        try {
            const res = await fetch(`/admin/complaints/${complaintId}/resolve`, {
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
                resolveConf.disabled = false;
                resolveConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            resolveConf.disabled = false;
            resolveConf.innerHTML = orig;
        }
    });

    // ==================== REJECT ====================
    const rejectModal  = new bootstrap.Modal(document.getElementById('rejectModal'));
    const rejectReason = document.getElementById('rejectReason');
    const rejectError  = document.getElementById('rejectError');
    const rejectConf   = document.getElementById('rejectConfirm');

    window.openRejectModal = function () {
        rejectReason.value = '';
        rejectError.style.display = 'none';
        rejectModal.show();
    };

    rejectConf?.addEventListener('click', async () => {
        const reason = rejectReason.value.trim();
        if (reason.length < 10) {
            rejectError.textContent = 'Rejection reason must be at least 10 characters.';
            rejectError.style.display = 'block';
            return;
        }
        rejectConf.disabled = true;
        const orig = rejectConf.innerHTML;
        rejectConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rejecting…';

        try {
            const res = await fetch(`/admin/complaints/${complaintId}/reject`, {
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
                rejectConf.disabled = false;
                rejectConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            rejectConf.disabled = false;
            rejectConf.innerHTML = orig;
        }
    });

    // ==================== CLOSE ====================
    const closeModal = new bootstrap.Modal(document.getElementById('closeModal'));
    const closeConf  = document.getElementById('closeConfirm');

    window.openCloseModal = function () {
        closeModal.show();
    };

    closeConf?.addEventListener('click', async () => {
        closeConf.disabled = true;
        const orig = closeConf.innerHTML;
        closeConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Closing…';

        try {
            const res = await fetch(`/admin/complaints/${complaintId}/close`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Complaint closed.', 'success');
                closeModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                closeConf.disabled = false;
                closeConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            closeConf.disabled = false;
            closeConf.innerHTML = orig;
        }
    });

    // ==================== ESCALATE ====================
    const escalateModal  = new bootstrap.Modal(document.getElementById('escalateModal'));
    const escalateReason = document.getElementById('escalateReason');
    const escalateError  = document.getElementById('escalateError');
    const escalateConf   = document.getElementById('escalateConfirm');

    window.openEscalateModal = function () {
        escalateReason.value = '';
        escalateError.style.display = 'none';
        escalateModal.show();
    };

    escalateConf?.addEventListener('click', async () => {
        const reason = escalateReason.value.trim();
        if (reason.length < 10) {
            escalateError.textContent = 'Escalation reason must be at least 10 characters.';
            escalateError.style.display = 'block';
            return;
        }
        escalateConf.disabled = true;
        const orig = escalateConf.innerHTML;
        escalateConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Escalating…';

        try {
            const res = await fetch(`/admin/complaints/${complaintId}/escalate`, {
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
                escalateConf.disabled = false;
                escalateConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            escalateConf.disabled = false;
            escalateConf.innerHTML = orig;
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
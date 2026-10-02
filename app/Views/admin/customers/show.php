<?php
/**
 * Admin Customers — Detail View
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\CustomerController::show()
 *
 * Expected variables:
 *   $customer          array  full customer row
 *   $recentOrders      array  last 10 orders (with bill info)
 *   $recentComplaints  array  last 10 complaints
 *   $stats             array  lifetime aggregates
 *   $pendingDeletion   ?array active deletion request or null
 * ----------------------------------------------------------
 */

$currentPage = 'customers';
$title       = 'Customer Details';

// Safe defaults
$customer         = $customer         ?? [];
$recentOrders     = $recentOrders     ?? [];
$recentComplaints = $recentComplaints ?? [];
$stats            = $stats            ?? [];
$pendingDeletion  = $pendingDeletion  ?? null;

if (empty($customer)) {
    echo '<div class="alert alert-danger">Customer not found.</div>';
    return;
}

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

$cStatus  = $customer['account_status'] ?? 'pending';
$verified = (int) ($customer['email_verified'] ?? 0);

// Status → badge class
$statusBadge = function (string $s): string {
    return match ($s) {
        'active'             => 'success',
        'pending'            => 'warning',
        'suspended'          => 'danger',
        'deletion_requested' => 'dark',
        default              => 'secondary',
    };
};
$statusLabel = function (string $s): string {
    return match ($s) {
        'active'             => 'Active',
        'pending'            => 'Pending',
        'suspended'          => 'Suspended',
        'deletion_requested' => 'Deletion Requested',
        default              => ucfirst($s),
    };
};

// Order status badge
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
$orderLabel = fn(string $s): string => ucwords(str_replace('_', ' ', $s));

// Payment status badge
$paymentBadge = function (string $s): string {
    return match ($s) {
        'paid'           => 'success',
        'partially_paid' => 'warning',
        'unpaid'         => 'danger',
        'refunded'       => 'secondary',
        default          => 'secondary',
    };
};

// Complaint status badge
$complaintBadge = function (string $s): string {
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

// Lifetime stats (with defaults)
$totalOrders       = (int)   ($stats['total_orders']       ?? 0);
$deliveredOrders   = (int)   ($stats['delivered_orders']   ?? 0);
$cancelledOrders   = (int)   ($stats['cancelled_orders']   ?? 0);
$totalComplaints   = (int)   ($stats['total_complaints']   ?? 0);
$lifetimeSpent     = (float) ($stats['lifetime_spent']     ?? 0);
$outstanding       = (float) ($stats['outstanding']        ?? 0);
?>

<!-- ==================== HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/customers" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="d-flex align-items-center gap-3">
            <div class="avatar-circle avatar-lg bg-<?= $statusBadge($cStatus) ?>-subtle text-<?= $statusBadge($cStatus) ?>">
                <?= $e(strtoupper(substr($customer['full_name'] ?? '?', 0, 1))) ?>
            </div>
            <div>
                <h1 class="page-title mb-1 d-flex align-items-center gap-2">
                    <?= $e($customer['full_name']) ?>
                    <?php if ($verified): ?>
                        <i class="bi bi-patch-check-fill text-primary fs-6" title="Email verified"></i>
                    <?php endif; ?>
                </h1>
                <div class="d-flex flex-wrap gap-2 align-items-center small">
                    <span class="text-muted">#<?= (int) $customer['customer_id'] ?></span>
                    <span class="text-muted">·</span>
                    <span class="badge bg-<?= $statusBadge($cStatus) ?>">
                        <?= $e($statusLabel($cStatus)) ?>
                    </span>
                    <span class="text-muted">·</span>
                    <span class="text-muted">
                        Joined <?= $fmtDate($customer['created_at']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/customers/<?= (int) $customer['customer_id'] ?>/edit"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-lightning-charge me-1"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($cStatus !== 'suspended'): ?>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                                onclick="customerAction('suspend', <?= (int) $customer['customer_id'] ?>)">
                            <i class="bi bi-person-slash me-2"></i>Suspend Account
                        </button>
                    </li>
                <?php else: ?>
                    <li>
                        <button type="button" class="dropdown-item text-success"
                                onclick="customerAction('activate', <?= (int) $customer['customer_id'] ?>)">
                            <i class="bi bi-person-check me-2"></i>Activate Account
                        </button>
                    </li>
                <?php endif; ?>
                <?php if (!$verified): ?>
                    <li>
                        <button type="button" class="dropdown-item"
                                onclick="customerAction('verify-email', <?= (int) $customer['customer_id'] ?>)">
                            <i class="bi bi-patch-check me-2"></i>Verify Email
                        </button>
                    </li>
                <?php endif; ?>
                <li>
                    <button type="button" class="dropdown-item"
                            onclick="customerAction('reset-password', <?= (int) $customer['customer_id'] ?>)">
                        <i class="bi bi-key me-2"></i>Send Password Reset
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a href="mailto:<?= $e($customer['email']) ?>" class="dropdown-item">
                        <i class="bi bi-envelope me-2"></i>Email Customer
                    </a>
                </li>
                <li>
                    <a href="tel:<?= $e($customer['phone']) ?>" class="dropdown-item">
                        <i class="bi bi-telephone me-2"></i>Call Customer
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- ==================== PENDING DELETION BANNER ==================== -->
<?php if ($pendingDeletion): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        <div class="flex-grow-1">
            <div class="fw-semibold">Account deletion requested</div>
            <div class="small">
                Requested on <?= $fmtDateTime($pendingDeletion['request_date'] ?? null) ?> ·
                Status: <strong><?= $e(ucfirst($pendingDeletion['status'] ?? 'pending')) ?></strong>
            </div>
        </div>
        <a href="/admin/customers/deletion-requests" class="btn btn-sm btn-outline-dark">
            Review <i class="bi bi-arrow-right"></i>
        </a>
    </div>
<?php endif; ?>

<!-- ==================== STAT CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Orders</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalOrders) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt($deliveredOrders) ?> delivered ·
                            <?= $fmtInt($cancelledOrders) ?> cancelled
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
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Lifetime Spent</div>
                        <div class="fw-bold fs-6 mb-0"><?= $fmtMoney($lifetimeSpent) ?></div>
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
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Outstanding</div>
                        <div class="fw-bold fs-6 mb-0"><?= $fmtMoney($outstanding) ?></div>
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
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Complaints</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalComplaints) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3">

    <!-- LEFT: Contact + Address -->
    <div class="col-lg-4">

        <!-- Contact -->
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
                        <a href="mailto:<?= $e($customer['email']) ?>" class="text-decoration-none">
                            <?= $e($customer['email']) ?>
                        </a>
                        <?php if ($verified): ?>
                            <i class="bi bi-patch-check-fill text-primary ms-1" title="Verified"></i>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning ms-1">Unverified</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone</div>
                    <div class="info-value">
                        <a href="tel:<?= $e($customer['phone']) ?>" class="text-decoration-none">
                            <?= $e($customer['phone']) ?>
                        </a>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Registered</div>
                    <div class="info-value"><?= $fmtDateTime($customer['created_at']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Last Updated</div>
                    <div class="info-value"><?= $fmtDateTime($customer['updated_at'] ?? null) ?></div>
                </div>
            </div>
        </div>

        <!-- Address -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-geo-alt me-2 text-primary"></i>Address
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">House / Flat</div>
                    <div class="info-value"><?= $e($customer['address_house']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Street</div>
                    <div class="info-value"><?= $e($customer['address_street']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Area</div>
                    <div class="info-value"><?= $e($customer['address_area']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">City</div>
                    <div class="info-value"><?= $e($customer['city']) ?></div>
                </div>
                <?php if (!empty($customer['landmark'])): ?>
                    <div class="info-row">
                        <div class="info-label">Landmark</div>
                        <div class="info-value"><?= $e($customer['landmark']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Orders + Complaints -->
    <div class="col-lg-8">

        <!-- Recent Orders -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bag-check me-2 text-primary"></i>Recent Orders
                </h6>
                <a href="/admin/reports/orders?customer=<?= (int) $customer['customer_id'] ?>"
                   class="small text-decoration-none">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small text-muted fw-semibold">Order</th>
                            <th class="small text-muted fw-semibold">Status</th>
                            <th class="small text-muted fw-semibold">Date</th>
                            <th class="small text-muted fw-semibold text-end">Amount</th>
                            <th class="small text-muted fw-semibold text-center">Payment</th>
                            <th class="small text-muted fw-semibold text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted small py-4">
                                    <i class="bi bi-inbox fs-4 d-block mb-1 opacity-50"></i>
                                    No orders yet.
                                </td>
                            </tr>
                        <?php else: foreach ($recentOrders as $o):
                            $payStatus = $o['payment_status'] ?? 'unpaid';
                            $outstandingRow = (float) ($o['outstanding_amount'] ?? 0);
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-medium small">
                                        <?= $e($o['order_number']) ?>
                                    </div>
                                    <?php if ($outstandingRow > 0): ?>
                                        <div class="text-muted" style="font-size:.72rem;">
                                            Due: <span class="text-danger fw-medium"><?= $fmtMoney($outstandingRow) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $orderBadge($o['order_status']) ?> small">
                                        <?= $e($orderLabel($o['order_status'])) ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= $fmtDate($o['order_date']) ?>
                                </td>
                                <td class="text-end small">
                                    <?php if ($o['total_amount'] !== null): ?>
                                        <span class="fw-medium"><?= $fmtMoney($o['total_amount']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $paymentBadge($payStatus) ?>-subtle text-<?= $paymentBadge($payStatus) ?> small">
                                        <?= $e(ucwords(str_replace('_', ' ', $payStatus))) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/reports/orders?highlight=<?= (int) $o['order_id'] ?>"
                                       class="btn btn-sm btn-outline-secondary" title="View order">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Complaints -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-exclamation-circle me-2 text-primary"></i>Recent Complaints
                </h6>
                <a href="/admin/complaints?search=<?= urlencode($customer['full_name']) ?>"
                   class="small text-decoration-none">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small text-muted fw-semibold">Complaint</th>
                            <th class="small text-muted fw-semibold">Type</th>
                            <th class="small text-muted fw-semibold">Order</th>
                            <th class="small text-muted fw-semibold">Filed</th>
                            <th class="small text-muted fw-semibold text-center">Status</th>
                            <th class="small text-muted fw-semibold text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentComplaints)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted small py-4">
                                    <i class="bi bi-emoji-smile fs-4 d-block mb-1 opacity-50"></i>
                                    No complaints from this customer. 🎉
                                </td>
                            </tr>
                        <?php else: foreach ($recentComplaints as $c): ?>
                            <tr>
                                <td class="fw-medium small"><?= $e($c['complaint_number']) ?></td>
                                <td class="small">
                                    <?= $e(ucwords(str_replace('_', ' ', $c['type']))) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= $e($c['order_number']) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= $fmtDate($c['created_at']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $complaintBadge($c['status']) ?> small">
                                        <?= $e(ucfirst(str_replace('_',' ', $c['status']))) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/complaints/<?= (int) $c['complaint_id'] ?>"
                                       class="btn btn-sm btn-outline-secondary" title="View complaint">
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

<!-- ==================== MODAL: ACTION CONFIRM ==================== -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="actionModalText" class="mb-3"></p>
                <div id="actionReasonWrap" class="mb-0 d-none">
                    <label class="form-label small text-muted">Reason (optional)</label>
                    <textarea class="form-control" id="actionReason" rows="3"
                              placeholder="Provide a reason for the customer record…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="actionConfirmBtn">Confirm</button>
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
    .avatar-lg {
        width: 56px; height: 56px;
        font-size: 1.35rem;
    }
    .kpi-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem;
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
        padding: .75rem .75rem;
        vertical-align: middle;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const modal       = new bootstrap.Modal(document.getElementById('actionModal'));
    const modalTitle  = document.getElementById('actionModalTitle');
    const modalText   = document.getElementById('actionModalText');
    const reasonWrap  = document.getElementById('actionReasonWrap');
    const reasonInput = document.getElementById('actionReason');
    const confirmBtn  = document.getElementById('actionConfirmBtn');

    const customerName = <?= json_encode($customer['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const ACTIONS = {
        'suspend':        { title: 'Suspend Customer',  text: (n) => `Suspend <strong>${escapeHtml(n)}</strong>? They will lose account access.`,                    needsReason: true,  confirmLabel: 'Suspend',         btnClass: 'btn-danger'  },
        'activate':       { title: 'Activate Customer', text: (n) => `Reactivate <strong>${escapeHtml(n)}</strong>? They will regain account access.`,             needsReason: false, confirmLabel: 'Activate',        btnClass: 'btn-success' },
        'verify-email':   { title: 'Verify Email',      text: (n) => `Mark <strong>${escapeHtml(n)}</strong>'s email as verified?`,                                  needsReason: false, confirmLabel: 'Verify',          btnClass: 'btn-primary' },
        'reset-password': { title: 'Reset Password',    text: (n) => `Generate a password reset link for <strong>${escapeHtml(n)}</strong>? It will be emailed to them.`, needsReason: false, confirmLabel: 'Send Reset Link', btnClass: 'btn-warning' },
    };

    let pendingAction = null;

    window.customerAction = function (action, id) {
        const cfg = ACTIONS[action];
        if (!cfg) return;

        pendingAction = { type: action, id: id };

        modalTitle.textContent = cfg.title;
        modalText.innerHTML    = cfg.text(customerName);
        confirmBtn.textContent = cfg.confirmLabel;
        confirmBtn.className   = 'btn ' + cfg.btnClass;

        if (cfg.needsReason) {
            reasonWrap.classList.remove('d-none');
            reasonInput.value = '';
        } else {
            reasonWrap.classList.add('d-none');
        }

        modal.show();
    };

    function buildRequest(type, id, reason) {
        const base = '/admin/customers/' + id;
        switch (type) {
            case 'suspend':        return { url: base + '/suspend',         body: { reason: reason || '' } };
            case 'activate':       return { url: base + '/activate',        body: {} };
            case 'verify-email':   return { url: base + '/verify-email',    body: {} };
            case 'reset-password': return { url: base + '/reset-password',  body: {} };
            default:               return null;
        }
    }

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { type, id } = pendingAction;
        const req = buildRequest(type, id, reasonInput.value.trim());
        if (!req) { modal.hide(); return; }

        confirmBtn.disabled = true;
        const originalText = confirmBtn.textContent;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const body = new URLSearchParams(req.body);
            body.append('_csrf', csrf);

            const res = await fetch(req.url, {
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

    document.getElementById('actionModal').addEventListener('hidden.bs.modal', () => {
        pendingAction = null;
        confirmBtn.disabled = false;
    });

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

        const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }
})();
</script>
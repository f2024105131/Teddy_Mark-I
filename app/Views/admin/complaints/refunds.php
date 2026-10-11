<?php
/**
 * Admin Complaints — Refund Queue
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ComplaintController::refunds()
 *
 * Expected variables:
 *   $refunds        array   refund rows with joins
 *                           each: refund_id, payment_id, order_id, complaint_id,
 *                                 amount, refund_reason, status, approved_by_staff_id,
 *                                 approved_at, completed_at, created_at,
 *                                 complaint_number, complaint_type,
 *                                 customer_name, customer_phone,
 *                                 order_number, payment_amount, payment_method,
 *                                 payment_status, approved_by_name
 *   $refundSummary  array   ['total','pending_total','approved_total',
 *                            'rejected_total','completed_total',
 *                            'approved_amount','pending_amount']
 *   $status         string  'pending' | 'approved' | 'rejected' | 'completed'
 *   $search         string
 *   $page           int
 *   $perPage        int
 *   $totalRows      int
 *   $totalPages     int
 *   $typeLabels     array
 *
 * POST endpoints:
 *   • /admin/refunds/{id}/approve
 *   • /admin/refunds/{id}/reject  (body: reason)
 *   • /admin/refunds/{id}/complete
 * ----------------------------------------------------------
 */

$currentPage = 'refunds';
$title       = 'Refund Queue';

// Safe defaults
$refunds       = $refunds       ?? [];
$refundSummary = $refundSummary ?? [
    'total'           => 0,
    'pending_total'   => 0,
    'approved_total'  => 0,
    'rejected_total'  => 0,
    'completed_total' => 0,
    'approved_amount' => 0,
    'pending_amount'  => 0,
];
$status     = $status     ?? 'pending';
$search     = $search     ?? '';
$page       = (int) ($page       ?? 1);
$perPage    = (int) ($perPage    ?? 20);
$totalRows  = (int) ($totalRows  ?? 0);
$totalPages = (int) ($totalPages ?? 1);
$typeLabels = $typeLabels ?? [];

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

// Status → badge
$refundBadge = function (string $s): string {
    return match ($s) {
        'pending'   => 'warning',
        'approved'  => 'info',
        'completed' => 'success',
        'rejected'  => 'danger',
        default     => 'secondary',
    };
};
$refundLabel = function (string $s): string {
    return match ($s) {
        'pending'   => 'Pending',
        'approved'  => 'Approved',
        'completed' => 'Completed',
        'rejected'  => 'Rejected',
        default     => ucfirst($s),
    };
};
$paymentBadge = function (string $s): string {
    return match ($s) {
        'approved'             => 'success',
        'pending_verification' => 'warning',
        'verified'             => 'info',
        'rejected'             => 'danger',
        'refunded'             => 'secondary',
        default                => 'secondary',
    };
};

// Complaint type badge
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
$typeLabel = fn(string $t): string => $typeLabels[$t] ?? ucwords(str_replace('_', ' ', $t));

// Tabs
$tabs = [
    'pending'   => ['label' => 'Pending',   'color' => 'warning',   'count' => $refundSummary['pending_total']   ?? 0, 'icon' => 'bi-hourglass-split'],
    'approved'  => ['label' => 'Approved',  'color' => 'info',      'count' => $refundSummary['approved_total']  ?? 0, 'icon' => 'bi-check-circle'],
    'completed' => ['label' => 'Completed', 'color' => 'success',   'count' => $refundSummary['completed_total'] ?? 0, 'icon' => 'bi-check2-all'],
    'rejected'  => ['label' => 'Rejected',  'color' => 'danger',    'count' => $refundSummary['rejected_total']  ?? 0, 'icon' => 'bi-x-circle'],
];

// Build querystring
$qs = function (array $overrides = []) use ($status, $search): string {
    $params = array_filter([
        'status' => $overrides['status'] ?? ($status ?: null),
        'search' => $overrides['search'] ?? ($search ?: null),
        'page'   => $overrides['page']   ?? null,
    ], fn($v) => $v !== null && $v !== '');

    return '?' . http_build_query($params);
};

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
                Refund Queue
                <?php if (!empty($refundSummary['pending_total'])): ?>
                    <span class="badge bg-warning">
                        <?= $fmtInt($refundSummary['pending_total']) ?> pending
                    </span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0 small">
                Review and process customer refund requests
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/complaints" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-exclamation-circle me-1"></i>Complaints
        </a>
        <a href="/admin/reports/sales" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bar-chart me-1"></i>Sales Report
        </a>
    </div>
</div>

<!-- ==================== SUMMARY CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pending</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($refundSummary['pending_total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtMoney($refundSummary['pending_amount'] ?? 0) ?>
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
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Approved</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($refundSummary['approved_total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            awaiting transfer
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
                        <i class="bi bi-check2-all"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Completed</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($refundSummary['completed_total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            successfully refunded
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
                    <div class="kpi-icon bg-secondary-subtle text-secondary">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total Refunded</div>
                        <div class="fw-bold fs-6 mb-0">
                            <?= $fmtMoney($refundSummary['approved_amount'] ?? 0) ?>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;">
                            approved + completed
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FILTER TABS ==================== -->
<ul class="nav nav-pills mb-3 gap-2 flex-wrap">
    <?php foreach ($tabs as $slug => $tab):
        $isActive = ($status === $slug);
        $count = (int) ($tab['count'] ?? 0);
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $isActive ? 'active' : '' ?>"
               href="<?= $e($qs(['status' => $slug, 'page' => null])) ?>">
                <i class="bi <?= $e($tab['icon']) ?> me-1"></i>
                <?= $e($tab['label']) ?>
                <?php if ($count > 0): ?>
                    <span class="badge bg-light text-dark ms-1" style="font-size:.65rem;">
                        <?= $fmtInt($count) ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- ==================== SEARCH ==================== -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="/admin/refunds" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= $e($status) ?>">
            <div class="col-md-8">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text"
                           class="form-control"
                           name="search"
                           value="<?= $e($search) ?>"
                           placeholder="Complaint #, order #, customer name…">
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($search !== ''): ?>
                    <a href="/admin/refunds?status=<?= $e($status) ?>"
                       class="btn btn-outline-secondary"
                       title="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ==================== REFUNDS TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Refund</th>
                    <th class="small text-muted fw-semibold">Complaint</th>
                    <th class="small text-muted fw-semibold">Customer</th>
                    <th class="small text-muted fw-semibold">Order</th>
                    <th class="small text-muted fw-semibold">Payment</th>
                    <th class="small text-muted fw-semibold text-end">Amount</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold">Created</th>
                    <th class="small text-muted fw-semibold text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($refunds)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No refunds found</div>
                                <div class="small">
                                    <?php if ($search !== ''): ?>
                                        No results for "<?= $e($search) ?>" ·
                                        <a href="/admin/refunds?status=<?= $e($status) ?>" class="text-decoration-none">Clear search</a>
                                    <?php else: ?>
                                        <?php if ($status === 'pending'): ?>
                                            No pending refund requests. 🎉
                                        <?php else: ?>
                                            No refunds in this status.
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($refunds as $r):
                    $rid         = (int) $r['refund_id'];
                    $rStatus     = (string) $r['status'];
                    $rAmount     = (float) $r['amount'];
                    $rReason     = (string) $r['refund_reason'];
                    $rCreated    = $r['created_at'] ?? null;
                    $rApprovedAt = $r['approved_at'] ?? null;
                    $rCompletedAt = $r['completed_at'] ?? null;
                    $approvedBy  = $r['approved_by_name'] ?? null;
                    $complaintId = (int) $r['complaint_id'];
                    $cNumber     = (string) $r['complaint_number'];
                    $cType       = (string) $r['complaint_type'];
                    $custName    = (string) $r['customer_name'];
                    $custPhone   = (string) $r['customer_phone'];
                    $orderNum    = (string) $r['order_number'];
                    $payAmount   = (float) ($r['payment_amount'] ?? 0);
                    $payMethod   = (string) $r['payment_method'];
                    $payStatus   = (string) $r['payment_status'];

                    $canApprove  = ($rStatus === 'pending');
                    $canReject   = ($rStatus === 'pending');
                    $canComplete = ($rStatus === 'approved');
                ?>
                    <tr class="<?= $rStatus === 'pending' ? 'table-warning' : ($rStatus === 'completed' ? 'table-success' : '') ?>">
                        <!-- Refund -->
                        <td>
                            <div class="fw-medium small">#R-<?= str_pad((string) $rid, 5, '0', STR_PAD_LEFT) ?></div>
                            <div class="text-muted" style="font-size:.7rem;">
                                #<?= $rid ?>
                            </div>
                        </td>

                        <!-- Complaint -->
                        <td>
                            <div class="small">
                                <a href="/admin/complaints/<?= $complaintId ?>"
                                   class="text-decoration-none fw-medium">
                                    <?= $e($cNumber) ?>
                                </a>
                            </div>
                            <span class="badge bg-<?= $typeBadge($cType) ?>" style="font-size:.65rem;">
                                <?= $e($typeLabel($cType)) ?>
                            </span>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="small fw-medium text-truncate" style="max-width: 160px;">
                                <?= $e($custName) ?>
                            </div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <i class="bi bi-telephone"></i> <?= $e($custPhone) ?>
                            </div>
                        </td>

                        <!-- Order -->
                        <td>
                            <span class="badge bg-info-subtle text-info">
                                <?= $e($orderNum) ?>
                            </span>
                        </td>

                        <!-- Payment -->
                        <td class="small">
                            <div class="text-muted">
                                <?= $e(ucwords(str_replace('_', ' ', $payMethod))) ?>
                            </div>
                            <span class="badge bg-<?= $paymentBadge($payStatus) ?>-subtle text-<?= $paymentBadge($payStatus) ?>"
                                  style="font-size:.65rem;">
                                <?= $e(ucwords(str_replace('_', ' ', $payStatus))) ?>
                            </span>
                        </td>

                        <!-- Amount -->
                        <td class="text-end">
                            <div class="fw-medium"><?= $fmtMoney($rAmount) ?></div>
                            <?php if ($payAmount > 0 && $rAmount < $payAmount): ?>
                                <div class="text-muted" style="font-size:.68rem;">
                                    of <?= $fmtMoney($payAmount) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td class="text-center">
                            <span class="badge bg-<?= $refundBadge($rStatus) ?>">
                                <?= $e($refundLabel($rStatus)) ?>
                            </span>
                            <?php if ($rStatus === 'pending' && $rCreated): ?>
                                <div class="text-muted mt-1" style="font-size:.68rem;">
                                    <?= $e($timeAgo($rCreated)) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Created -->
                        <td class="small text-muted">
                            <div><?= $e($fmtDate($rCreated)) ?></div>
                            <?php if ($approvedBy && $rStatus !== 'pending'): ?>
                                <div style="font-size:.68rem;">
                                    by <?= $e($approvedBy) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php if ($canApprove): ?>
                                    <button type="button"
                                            class="btn btn-success"
                                            title="Approve refund"
                                            onclick="openApproveModal(<?= $rid ?>, <?= json_encode($cNumber, $jsonFlags) ?>, <?= json_encode($rAmount) ?>, <?= json_encode($custName, $jsonFlags) ?>)">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-danger"
                                            title="Reject refund"
                                            onclick="openRejectModal(<?= $rid ?>, <?= json_encode($cNumber, $jsonFlags) ?>, <?= json_encode($custName, $jsonFlags) ?>)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($canComplete): ?>
                                    <button type="button"
                                            class="btn btn-outline-success"
                                            title="Mark as completed"
                                            onclick="openCompleteModal(<?= $rid ?>, <?= json_encode($cNumber, $jsonFlags) ?>, <?= json_encode($rAmount) ?>, <?= json_encode($custName, $jsonFlags) ?>)">
                                        <i class="bi bi-check2-all"></i>
                                    </button>
                                <?php endif; ?>
                                <button type="button"
                                        class="btn btn-outline-secondary dropdown-toggle-split"
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button type="button" class="dropdown-item"
                                                onclick="openDetailModal(<?= $rid ?>,
                                                    <?= json_encode($cNumber, $jsonFlags) ?>,
                                                    <?= json_encode($custName, $jsonFlags) ?>,
                                                    <?= json_encode($orderNum, $jsonFlags) ?>,
                                                    <?= json_encode($rAmount) ?>,
                                                    <?= json_encode($payAmount) ?>,
                                                    <?= json_encode(ucwords(str_replace('_', ' ', $payMethod)), $jsonFlags) ?>,
                                                    <?= json_encode($rReason, $jsonFlags) ?>,
                                                    <?= json_encode($rStatus) ?>,
                                                    <?= json_encode($rCreated ? date('M j, Y · g:i A', strtotime($rCreated)) : '—', $jsonFlags) ?>,
                                                    <?= json_encode($approvedBy ?? '—', $jsonFlags) ?>,
                                                    <?= json_encode($rApprovedAt ? date('M j, Y · g:i A', strtotime($rApprovedAt)) : '—', $jsonFlags) ?>,
                                                    <?= json_encode($rCompletedAt ? date('M j, Y · g:i A', strtotime($rCompletedAt)) : '—', $jsonFlags) ?>)">
                                            <i class="bi bi-eye me-2"></i>View details
                                        </button>
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="/admin/complaints/<?= $complaintId ?>">
                                            <i class="bi bi-exclamation-circle me-2"></i>Open complaint
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="tel:<?= $e($custPhone) ?>">
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div class="small text-muted">
                Showing
                <strong><?= $fmtInt(($page - 1) * $perPage + 1) ?></strong>
                –
                <strong><?= $fmtInt(min($page * $perPage, $totalRows)) ?></strong>
                of <strong><?= $fmtInt($totalRows) ?></strong>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page <= 1 ? '#' : $e($qs(['page' => $page - 1])) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);

                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $e($qs(['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $e($qs(['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $e($qs(['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page >= $totalPages ? '#' : $e($qs(['page' => $page + 1])) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ==================== APPROVE MODAL ==================== -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success">
                    <i class="bi bi-check-circle me-2"></i>Approve Refund
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Approving will mark the payment as <strong>refunded</strong>.
                    The actual money transfer happens separately.
                </div>
                <p class="mb-2 small">
                    Approve refund for complaint
                    <strong id="approveComplaint"></strong>?
                </p>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted small">Customer</td>
                            <td class="text-end small fw-medium" id="approveCustomer"></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Refund Amount</td>
                            <td class="text-end fw-bold text-success" id="approveAmount"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="approveConfirm">
                    <i class="bi bi-check-circle me-1"></i>Approve Refund
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
                <h5 class="modal-title text-danger">
                    <i class="bi bi-x-circle me-2"></i>Reject Refund
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reject refund for complaint <strong id="rejectComplaint"></strong>
                    (<span id="rejectCustomer"></span>)?
                </p>
                <label class="form-label small fw-medium">
                    Rejection Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="rejectReason" rows="4"
                          placeholder="Explain why this refund is being rejected (min 10 characters)…"></textarea>
                <div class="invalid-feedback" id="rejectError"></div>
                <div class="form-text small mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    The customer will see this reason.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="rejectConfirm">
                    <i class="bi bi-x-circle me-1"></i>Reject Refund
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== COMPLETE MODAL ==================== -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success">
                    <i class="bi bi-check2-all me-2"></i>Mark Refund Completed
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success small mb-3">
                    <i class="bi bi-check-circle me-1"></i>
                    Confirm that the refund money has been sent to the customer.
                </div>
                <p class="mb-2 small">
                    Mark refund for complaint <strong id="completeComplaint"></strong> as completed?
                </p>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted small">Customer</td>
                            <td class="text-end small fw-medium" id="completeCustomer"></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Amount</td>
                            <td class="text-end fw-bold text-success" id="completeAmount"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="completeConfirm">
                    <i class="bi bi-check2-all me-1"></i>Mark as Completed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== DETAIL MODAL ==================== -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>Refund Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th class="text-muted small">Complaint</th><td class="text-end small fw-medium" id="dComplaint"></td></tr>
                        <tr><th class="text-muted small">Customer</th><td class="text-end small" id="dCustomer"></td></tr>
                        <tr><th class="text-muted small">Order</th><td class="text-end small" id="dOrder"></td></tr>
                        <tr><th class="text-muted small">Original Payment</th><td class="text-end small" id="dPayment"></td></tr>
                        <tr><th class="text-muted small">Payment Method</th><td class="text-end small" id="dMethod"></td></tr>
                        <tr><th class="text-muted small">Refund Amount</th><td class="text-end fw-bold text-success" id="dAmount"></td></tr>
                        <tr><th class="text-muted small">Status</th><td class="text-end" id="dStatus"></td></tr>
                        <tr><th class="text-muted small">Created</th><td class="text-end small" id="dCreated"></td></tr>
                        <tr><th class="text-muted small">Approved By</th><td class="text-end small" id="dApprovedBy"></td></tr>
                        <tr><th class="text-muted small">Approved At</th><td class="text-end small" id="dApprovedAt"></td></tr>
                        <tr><th class="text-muted small">Completed At</th><td class="text-end small" id="dCompletedAt"></td></tr>
                    </tbody>
                </table>
                <hr>
                <div class="small text-muted fw-semibold text-uppercase mb-1">Reason</div>
                <div class="small bg-light rounded p-2" id="dReason"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
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
    .nav-pills .nav-link {
        color: #475569;
        border: 1px solid transparent;
        padding: .4rem .75rem;
        font-size: .88rem;
    }
    .nav-pills .nav-link.active {
        background: #0ea5e9;
        color: #fff;
    }
    .nav-pills .nav-link:hover:not(.active) {
        background: #f1f5f9;
        border-color: #e2e8f0;
    }
    .table > :not(caption) > * > * {
        padding: .75rem .65rem;
        vertical-align: middle;
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

    // ==================== APPROVE ====================
    const approveModal    = new bootstrap.Modal(document.getElementById('approveModal'));
    const approveComplaint = document.getElementById('approveComplaint');
    const approveCustomer  = document.getElementById('approveCustomer');
    const approveAmount    = document.getElementById('approveAmount');
    const approveConf      = document.getElementById('approveConfirm');
    let approveTargetId = 0;

    window.openApproveModal = function (id, complaint, amount, customer) {
        approveTargetId = id;
        approveComplaint.textContent = complaint;
        approveCustomer.textContent  = customer;
        approveAmount.textContent    = 'Rs. ' + Number(amount).toLocaleString(undefined, {
            minimumFractionDigits: 2, maximumFractionDigits: 2,
        });
        approveModal.show();
    };

    approveConf?.addEventListener('click', async () => {
        approveConf.disabled = true;
        const orig = approveConf.innerHTML;
        approveConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Approving…';

        try {
            const res = await fetch(`/admin/refunds/${approveTargetId}/approve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Refund approved.', 'success');
                approveModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                approveConf.disabled = false;
                approveConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            approveConf.disabled = false;
            approveConf.innerHTML = orig;
        }
    });

    // ==================== REJECT ====================
    const rejectModal     = new bootstrap.Modal(document.getElementById('rejectModal'));
    const rejectComplaint = document.getElementById('rejectComplaint');
    const rejectCustomer  = document.getElementById('rejectCustomer');
    const rejectReason    = document.getElementById('rejectReason');
    const rejectError     = document.getElementById('rejectError');
    const rejectConf      = document.getElementById('rejectConfirm');
    let rejectTargetId = 0;

    window.openRejectModal = function (id, complaint, customer) {
        rejectTargetId = id;
        rejectComplaint.textContent = complaint;
        rejectCustomer.textContent  = customer;
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
            const res = await fetch(`/admin/refunds/${rejectTargetId}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, reason: reason }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Refund rejected.', 'success');
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

    // ==================== COMPLETE ====================
    const completeModal     = new bootstrap.Modal(document.getElementById('completeModal'));
    const completeComplaint = document.getElementById('completeComplaint');
    const completeCustomer  = document.getElementById('completeCustomer');
    const completeAmount    = document.getElementById('completeAmount');
    const completeConf      = document.getElementById('completeConfirm');
    let completeTargetId = 0;

    window.openCompleteModal = function (id, complaint, amount, customer) {
        completeTargetId = id;
        completeComplaint.textContent = complaint;
        completeCustomer.textContent  = customer;
        completeAmount.textContent    = 'Rs. ' + Number(amount).toLocaleString(undefined, {
            minimumFractionDigits: 2, maximumFractionDigits: 2,
        });
        completeModal.show();
    };

    completeConf?.addEventListener('click', async () => {
        completeConf.disabled = true;
        const orig = completeConf.innerHTML;
        completeConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const res = await fetch(`/admin/refunds/${completeTargetId}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Refund completed.', 'success');
                completeModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                completeConf.disabled = false;
                completeConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            completeConf.disabled = false;
            completeConf.innerHTML = orig;
        }
    });

    // ==================== DETAIL ====================
    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

    window.openDetailModal = function (id, complaint, customer, order, amount, payment, method, reason, status, created, approvedBy, approvedAt, completedAt) {
        const fmtMoney = (n) => 'Rs. ' + Number(n).toLocaleString(undefined, {
            minimumFractionDigits: 2, maximumFractionDigits: 2,
        });
        const statusBadge = {
            pending:   '<span class="badge bg-warning">Pending</span>',
            approved:  '<span class="badge bg-info">Approved</span>',
            completed: '<span class="badge bg-success">Completed</span>',
            rejected:  '<span class="badge bg-danger">Rejected</span>',
        }[status] || '<span class="badge bg-secondary">' + status + '</span>';

        document.getElementById('dComplaint').textContent  = complaint;
        document.getElementById('dCustomer').textContent   = customer;
        document.getElementById('dOrder').textContent      = order;
        document.getElementById('dPayment').textContent    = fmtMoney(payment);
        document.getElementById('dMethod').textContent     = method;
        document.getElementById('dAmount').textContent     = fmtMoney(amount);
        document.getElementById('dStatus').innerHTML       = statusBadge;
        document.getElementById('dCreated').textContent    = created;
        document.getElementById('dApprovedBy').textContent = approvedBy;
        document.getElementById('dApprovedAt').textContent = approvedAt;
        document.getElementById('dCompletedAt').textContent = completedAt;

        // Escape reason (it's user input)
        const reasonEl = document.getElementById('dReason');
        reasonEl.textContent = reason || '—';

        detailModal.show();
    };

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
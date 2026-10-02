<?php
/**
 * Admin Customers — List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\CustomerController::index()
 *
 * Expected variables:
 *   $customers  array   paginated customer rows (with aggregated counts)
 *   $summary    array   ['total','active','pending','suspended','deletion_requested','new_today']
 *   $cities     array   distinct city names for filter dropdown
 *   $search     string  current search query
 *   $status     string  current status filter
 *   $city       string  current city filter
 *   $page       int     current page number
 *   $perPage    int     rows per page
 *   $totalRows  int     total matching rows
 *   $totalPages int     total pages
 * ----------------------------------------------------------
 */

$currentPage = 'customers';
$title       = 'Customers';

// Safe defaults
$customers  = $customers  ?? [];
$summary    = $summary    ?? ['total'=>0,'active'=>0,'pending'=>0,'suspended'=>0,'deletion_requested'=>0,'new_today'=>0];
$cities     = $cities     ?? [];
$search     = $search     ?? '';
$status     = $status     ?? '';
$city       = $city       ?? '';
$page       = (int) ($page       ?? 1);
$perPage    = (int) ($perPage    ?? 20);
$totalRows  = (int) ($totalRows  ?? 0);
$totalPages = (int) ($totalPages ?? 1);

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Status → Bootstrap badge
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

// Build querystring for pagination links (preserve filters)
$qs = function (int $targetPage) use ($search, $status, $city): string {
    $params = array_filter([
        'search' => $search !== '' ? $search : null,
        'status' => $status !== '' ? $status : null,
        'city'   => $city   !== '' ? $city   : null,
        'page'   => $targetPage,
    ], fn($v) => $v !== null);

    return '?' . http_build_query($params);
};

// Active filters counter
$activeFilters = (int) ($search !== '') + (int) ($status !== '') + (int) ($city !== '');
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Customers</h1>
        <p class="text-muted mb-0 small">
            Manage all registered customers ·
            <?= $fmtInt($totalRows) ?> total
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/customers/deletion-requests" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-person-x me-1"></i>Deletion Requests
            <?php if (!empty($summary['deletion_requested'])): ?>
                <span class="badge bg-danger ms-1"><?= $fmtInt($summary['deletion_requested']) ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/customers/export<?= $activeFilters ? '?' . http_build_query(array_filter(['search'=>$search ?: null,'status'=>$status ?: null,'city'=>$city ?: null])) : '' ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== SUMMARY CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['total']) ?></div>
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
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Active</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['active']) ?></div>
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
                        <div class="text-muted small text-uppercase fw-semibold">Pending</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['pending']) ?></div>
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
                        <i class="bi bi-person-slash"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Suspended</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['suspended']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FILTERS ==================== -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="/admin/customers" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text"
                           class="form-control"
                           name="search"
                           value="<?= $e($search) ?>"
                           placeholder="Name, email, or phone…">
                </div>
            </div>

            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="">All statuses</option>
                    <option value="active"             <?= $status==='active'             ?'selected':'' ?>>Active</option>
                    <option value="pending"            <?= $status==='pending'            ?'selected':'' ?>>Pending</option>
                    <option value="suspended"          <?= $status==='suspended'          ?'selected':'' ?>>Suspended</option>
                    <option value="deletion_requested" <?= $status==='deletion_requested' ?'selected':'' ?>>Deletion Requested</option>
                </select>
            </div>

            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">City</label>
                <select class="form-select" name="city">
                    <option value="">All cities</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= $e($c) ?>" <?= $city === $c ? 'selected' : '' ?>>
                            <?= $e($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-1 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($activeFilters > 0): ?>
                    <a href="/admin/customers" class="btn btn-outline-secondary" title="Clear filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($activeFilters > 0): ?>
            <div class="mt-3 pt-3 border-top d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted small">Active filters:</span>
                <?php if ($search !== ''): ?>
                    <span class="badge bg-light text-dark border">
                        Search: "<?= $e($search) ?>"
                    </span>
                <?php endif; ?>
                <?php if ($status !== ''): ?>
                    <span class="badge bg-light text-dark border">
                        Status: <?= $e($statusLabel($status)) ?>
                    </span>
                <?php endif; ?>
                <?php if ($city !== ''): ?>
                    <span class="badge bg-light text-dark border">
                        City: <?= $e($city) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== CUSTOMERS TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Customer</th>
                    <th class="small text-muted fw-semibold">Contact</th>
                    <th class="small text-muted fw-semibold">Location</th>
                    <th class="small text-muted fw-semibold text-center">Orders</th>
                    <th class="small text-muted fw-semibold text-center">Complaints</th>
                    <th class="small text-muted fw-semibold text-end">Total Spent</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No customers found</div>
                                <div class="small">
                                    <?php if ($activeFilters > 0): ?>
                                        Try adjusting your filters ·
                                        <a href="/admin/customers" class="text-decoration-none">Clear all</a>
                                    <?php else: ?>
                                        Customers will appear here once they register.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($customers as $c):
                    $cStatus = $c['account_status'] ?? 'pending';
                    $verified = (int) ($c['email_verified'] ?? 0);
                ?>
                    <tr>
                        <!-- Customer -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle <?= 'bg-' . $statusBadge($cStatus) ?>-subtle text-<?= $statusBadge($cStatus) ?>">
                                    <?= $e(strtoupper(substr($c['full_name'] ?? '?', 0, 1))) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-medium text-truncate">
                                        <?= $e($c['full_name']) ?>
                                        <?php if ($verified): ?>
                                            <i class="bi bi-patch-check-fill text-primary ms-1" title="Email verified"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small">
                                        #<?= (int) $c['customer_id'] ?>
                                        · Joined <?= $fmtDate($c['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Contact -->
                        <td>
                            <div class="small">
                                <div class="text-truncate" style="max-width: 220px;">
                                    <i class="bi bi-envelope text-muted me-1"></i>
                                    <?= $e($c['email']) ?>
                                </div>
                                <div class="text-truncate">
                                    <i class="bi bi-telephone text-muted me-1"></i>
                                    <?= $e($c['phone']) ?>
                                </div>
                            </div>
                        </td>

                        <!-- Location -->
                        <td>
                            <div class="small">
                                <div class="text-truncate" style="max-width: 200px;">
                                    <?= $e($c['address_area']) ?>
                                </div>
                                <div class="text-muted">
                                    <i class="bi bi-geo-alt me-1"></i><?= $e($c['city']) ?>
                                </div>
                            </div>
                        </td>

                        <!-- Orders -->
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary">
                                <?= $fmtInt($c['orders_count'] ?? 0) ?>
                            </span>
                        </td>

                        <!-- Complaints -->
                        <td class="text-center">
                            <?php $cc = (int) ($c['complaints_count'] ?? 0); ?>
                            <?php if ($cc > 0): ?>
                                <span class="badge bg-danger-subtle text-danger"><?= $fmtInt($cc) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Total spent -->
                        <td class="text-end">
                            <span class="fw-medium">
                                <?= $fmtMoney($c['total_spent'] ?? 0) ?>
                            </span>
                        </td>

                        <!-- Status -->
                        <td class="text-center">
                            <span class="badge bg-<?= $statusBadge($cStatus) ?>">
                                <?= $e($statusLabel($cStatus)) ?>
                            </span>
                        </td>

                        <!-- Actions -->
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="/admin/customers/<?= (int) $c['customer_id'] ?>"
                                   class="btn btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/admin/customers/<?= (int) $c['customer_id'] ?>/edit"
                                   class="btn btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-outline-secondary dropdown-toggle-split"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($cStatus !== 'suspended'): ?>
                                        <li>
                                            <button type="button"
                                                    class="dropdown-item text-danger"
                                                    onclick="customerAction('suspend', <?= (int) $c['customer_id'] ?>, '<?= $e($c['full_name']) ?>')">
                                                <i class="bi bi-person-slash me-2"></i>Suspend
                                            </button>
                                        </li>
                                    <?php else: ?>
                                        <li>
                                            <button type="button"
                                                    class="dropdown-item text-success"
                                                    onclick="customerAction('activate', <?= (int) $c['customer_id'] ?>, '<?= $e($c['full_name']) ?>')">
                                                <i class="bi bi-person-check me-2"></i>Activate
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item"
                                                onclick="customerAction('verify-email', <?= (int) $c['customer_id'] ?>, '<?= $e($c['full_name']) ?>')">
                                            <i class="bi bi-patch-check me-2"></i>Verify Email
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item"
                                                onclick="customerAction('reset-password', <?= (int) $c['customer_id'] ?>, '<?= $e($c['full_name']) ?>')">
                                            <i class="bi bi-key me-2"></i>Reset Password
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ==================== PAGINATION ==================== -->
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
                    <!-- Prev -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page <= 1 ? '#' : $e($qs($page - 1)) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    // Window of pages: current ± 2
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);

                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $e($qs(1)) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $e($qs($i)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $e($qs($totalPages)) ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>

                    <!-- Next -->
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page >= $totalPages ? '#' : $e($qs($page + 1)) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
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

                <!-- Reason input (for suspend / reset password) -->
                <div id="actionReasonWrap" class="mb-0 d-none">
                    <label class="form-label small text-muted">Reason (optional)</label>
                    <textarea class="form-control" id="actionReason" rows="3"
                              placeholder="Provide a reason for the customer record…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="actionConfirmBtn">
                    Confirm
                </button>
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
    .kpi-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .min-w-0 { min-width: 0; }
    .table > :not(caption) > * > * {
        padding: .85rem .75rem;
        vertical-align: middle;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const modal        = new bootstrap.Modal(document.getElementById('actionModal'));
    const modalTitle   = document.getElementById('actionModalTitle');
    const modalText    = document.getElementById('actionModalText');
    const reasonWrap   = document.getElementById('actionReasonWrap');
    const reasonInput  = document.getElementById('actionReason');
    const confirmBtn   = document.getElementById('actionConfirmBtn');

    let pendingAction = null; // { type, id }

    const ACTIONS = {
        'suspend':        { title: 'Suspend Customer',   text: (n) => `Suspend <strong>${escapeHtml(n)}</strong>? They will not be able to log in.`,                 needsReason: true,  confirmLabel: 'Suspend',        btnClass: 'btn-danger'  },
        'activate':       { title: 'Activate Customer',  text: (n) => `Reactivate <strong>${escapeHtml(n)}</strong>? They will regain account access.`,              needsReason: false, confirmLabel: 'Activate',       btnClass: 'btn-success' },
        'verify-email':   { title: 'Verify Email',       text: (n) => `Mark <strong>${escapeHtml(n)}</strong>'s email as verified?`,                                 needsReason: false, confirmLabel: 'Verify',         btnClass: 'btn-primary' },
        'reset-password': { title: 'Reset Password',     text: (n) => `Generate a password reset link for <strong>${escapeHtml(n)}</strong>? The link will be emailed to them.`, needsReason: false, confirmLabel: 'Send Reset Link', btnClass: 'btn-warning' },
    };

    window.customerAction = function (action, id, name) {
        const cfg = ACTIONS[action];
        if (!cfg) return;

        pendingAction = { type: action, id: id, name: name };

        modalTitle.textContent = cfg.title;
        modalText.innerHTML    = cfg.text(name);
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

    // Build endpoint + body for each action
    function buildRequest(type, id, reason) {
        const base = '/admin/customers/' + id;
        switch (type) {
            case 'suspend':
                return { url: base + '/suspend',         body: { reason: reason || '' } };
            case 'activate':
                return { url: base + '/activate',        body: {} };
            case 'verify-email':
                return { url: base + '/verify-email',    body: {} };
            case 'reset-password':
                return { url: base + '/reset-password',  body: {} };
            default:
                return null;
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

    // Clear state on modal close
    document.getElementById('actionModal').addEventListener('hidden.bs.modal', () => {
        pendingAction = null;
        confirmBtn.disabled = false;
    });

    // ----- Toast helper -----
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
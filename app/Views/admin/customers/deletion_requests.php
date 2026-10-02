<?php
/**
 * Admin Customers — Account Deletion Requests
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\CustomerController::deletionRequests()
 *
 * Expected variables:
 *   $requests  array   filtered list of deletion requests
 *   $filter    string  'pending' | 'approved' | 'rejected' (or '')
 *
 * Row fields (from controller's SQL):
 *   deletion_id, customer_id, request_date, status, rejection_reason,
 *   processed_by_staff_id, processed_at,
 *   full_name, email, phone, account_status, processed_by_name
 *
 * POST endpoints:
 *   /admin/customers/deletion-requests/{id}
 *     body: action=approve|reject, reason (required for reject), _csrf
 *   → Admin\CustomerController::processDeletion()
 * ----------------------------------------------------------
 */

$currentPage = 'deletion-requests';
$title       = 'Account Deletion Requests';

// Safe defaults
$requests = $requests ?? [];
$filter   = $filter   ?? 'pending';

$e           = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt      = fn($n) => number_format((int) $n);
$fmtDate     = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

// Filter tab config
$tabs = [
    'pending'  => ['label' => 'Pending',  'icon' => 'bi-hourglass-split',       'color' => 'warning'],
    'approved' => ['label' => 'Approved', 'icon' => 'bi-check-circle',         'color' => 'success'],
    'rejected' => ['label' => 'Rejected', 'icon' => 'bi-x-circle',             'color' => 'secondary'],
    'all'      => ['label' => 'All',      'icon' => 'bi-list-ul',              'color' => 'primary'],
];

// Status → badge
$statusBadge = function (string $s): string {
    return match ($s) {
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        default    => 'secondary',
    };
};
$statusLabel = fn(string $s): string => ucfirst($s);

// Customer status → badge
$custStatusBadge = function (string $s): string {
    return match ($s) {
        'active'             => 'success',
        'pending'            => 'warning',
        'suspended'          => 'danger',
        'deletion_requested' => 'dark',
        default              => 'secondary',
    };
};

// Build querystring for tabs
$tabQs = fn(string $slug): string => '?' . http_build_query(['status' => $slug]);
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/customers" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1">Account Deletion Requests</h1>
            <p class="text-muted mb-0 small">
                Review, approve, or reject customer account deletion requests
            </p>
        </div>
    </div>
</div>

<!-- ==================== FILTER TABS ==================== -->
<ul class="nav nav-pills mb-3 gap-2">
    <?php foreach ($tabs as $slug => $tab):
        $isActive = ($filter === $slug) || ($slug === 'all' && !in_array($filter, ['pending','approved','rejected'], true));
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $isActive ? 'active' : '' ?>"
               href="<?= $slug === 'all' ? '?status=all' : $e($tabQs($slug)) ?>">
                <i class="bi <?= $e($tab['icon']) ?> me-1"></i>
                <?= $e($tab['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- ==================== REQUESTS TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Request</th>
                    <th class="small text-muted fw-semibold">Customer</th>
                    <th class="small text-muted fw-semibold">Contact</th>
                    <th class="small text-muted fw-semibold">Account</th>
                    <th class="small text-muted fw-semibold">Requested</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold">Processed By</th>
                    <th class="small text-muted fw-semibold text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No requests found</div>
                                <div class="small">
                                    <?php if ($filter === 'pending'): ?>
                                        There are no pending deletion requests. 🎉
                                    <?php else: ?>
                                        No requests in this category.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($requests as $r):
                    $status       = $r['status']        ?? 'pending';
                    $custStatus   = $r['account_status'] ?? 'active';
                    $canAct       = ($status === 'pending');
                    $reason       = trim((string) ($r['rejection_reason'] ?? ''));
                ?>
                    <tr>
                        <!-- Request ID -->
                        <td>
                            <div class="fw-medium small">
                                #DR-<?= str_pad((string) $r['deletion_id'], 5, '0', STR_PAD_LEFT) ?>
                            </div>
                            <div class="text-muted" style="font-size:.72rem;">
                                Customer #<?= (int) $r['customer_id'] ?>
                            </div>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle bg-<?= $statusBadge($status) ?>-subtle text-<?= $statusBadge($status) ?>">
                                    <?= $e(strtoupper(substr($r['full_name'] ?? '?', 0, 1))) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-medium small text-truncate">
                                        <a href="/admin/customers/<?= (int) $r['customer_id'] ?>"
                                           class="text-decoration-none">
                                            <?= $e($r['full_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        View profile
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Contact -->
                        <td>
                            <div class="small">
                                <div class="text-truncate" style="max-width: 200px;">
                                    <i class="bi bi-envelope text-muted me-1"></i>
                                    <?= $e($r['email']) ?>
                                </div>
                                <div class="text-truncate">
                                    <i class="bi bi-telephone text-muted me-1"></i>
                                    <?= $e($r['phone']) ?>
                                </div>
                            </div>
                        </td>

                        <!-- Account status -->
                        <td>
                            <span class="badge bg-<?= $custStatusBadge($custStatus) ?>-subtle text-<?= $custStatusBadge($custStatus) ?> small">
                                <?= $e(ucwords(str_replace('_', ' ', $custStatus))) ?>
                            </span>
                        </td>

                        <!-- Requested date -->
                        <td class="small text-muted">
                            <?= $fmtDateTime($r['request_date'] ?? null) ?>
                        </td>

                        <!-- Request status -->
                        <td class="text-center">
                            <span class="badge bg-<?= $statusBadge($status) ?>">
                                <?= $e($statusLabel($status)) ?>
                            </span>
                        </td>

                        <!-- Processed by -->
                        <td class="small">
                            <?php if (!empty($r['processed_by_name'])): ?>
                                <div><?= $e($r['processed_by_name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    <?= $fmtDateTime($r['processed_at'] ?? null) ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-end">
                            <?php if ($canAct): ?>
                                <div class="btn-group btn-group-sm">
                                    <button type="button"
                                            class="btn btn-outline-danger"
                                            onclick="openActionModal(<?= (int) $r['deletion_id'] ?>, 'approve', <?= json_encode($r['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)"
                                            title="Approve deletion">
                                        <i class="bi bi-check-lg me-1"></i>Approve
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            onclick="openActionModal(<?= (int) $r['deletion_id'] ?>, 'reject', <?= json_encode($r['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)"
                                            title="Reject deletion">
                                        <i class="bi bi-x-lg me-1"></i>Reject
                                    </button>
                                </div>
                            <?php else: ?>
                                <a href="/admin/customers/<?= (int) $r['customer_id'] ?>"
                                   class="btn btn-sm btn-outline-secondary"
                                   title="View customer">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Rejection reason row -->
                    <?php if ($status === 'rejected' && $reason !== ''): ?>
                        <tr class="table-light">
                            <td colspan="8" class="py-2 small">
                                <i class="bi bi-info-circle text-danger me-1"></i>
                                <strong class="text-danger">Rejection reason:</strong>
                                <span class="text-muted"><?= nl2br($e($reason)) ?></span>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== INFO BANNER ==================== -->
<?php if ($filter === 'pending' && !empty($requests)): ?>
    <div class="alert alert-info d-flex gap-3 align-items-start mt-3 mb-0">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">
            <strong>What happens when you approve a request?</strong>
            <ul class="mb-0 mt-1">
                <li>The customer's personal information is anonymized (name, email, phone, address).</li>
                <li>The account is set to <strong>suspended</strong> to preserve order history integrity.</li>
                <li>The customer can no longer log in.</li>
            </ul>
            <div class="mt-1 text-muted">
                Records are kept for accounting and audit purposes — the row is not hard-deleted.
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ==================== ACTION MODAL ==================== -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="actionModalText" class="mb-3"></p>

                <!-- Rejection reason (only for reject) -->
                <div id="reasonWrap" class="d-none">
                    <label for="rejectionReason" class="form-label small fw-medium">
                        Rejection Reason <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control"
                              id="rejectionReason"
                              rows="3"
                              minlength="10"
                              maxlength="2000"
                              placeholder="Explain why this deletion request is being rejected (min 10 characters)…"></textarea>
                    <div class="form-text small">
                        The customer will see this reason. <span id="reasonCounter">0</span>/2000
                    </div>
                    <div class="invalid-feedback" id="reasonError"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .avatar-circle {
        width: 36px; height: 36px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .85rem;
        flex-shrink: 0;
    }
    .min-w-0 { min-width: 0; }
    .nav-pills .nav-link {
        color: #475569;
        border: 1px solid transparent;
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
        padding: .85rem .75rem;
        vertical-align: middle;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const modal      = new bootstrap.Modal(document.getElementById('actionModal'));
    const modalTitle = document.getElementById('actionModalTitle');
    const modalText  = document.getElementById('actionModalText');
    const reasonWrap = document.getElementById('reasonWrap');
    const reasonIn   = document.getElementById('rejectionReason');
    const reasonErr  = document.getElementById('reasonError');
    const reasonCnt  = document.getElementById('reasonCounter');
    const confirmBtn = document.getElementById('confirmBtn');

    let pendingAction = null;

    const CONFIG = {
        'approve': {
            title: 'Approve Deletion Request',
            text: (name) => `
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>You are about to approve the deletion request for
                    <span class="text-danger">${escapeHtml(name)}</span>.</strong>
                </div>
                <ul class="small mb-0 mt-3 ps-3">
                    <li>Their personal info will be anonymized.</li>
                    <li>The account will be set to <strong>suspended</strong>.</li>
                    <li>Order and billing history will be preserved.</li>
                    <li><strong>This action cannot be undone.</strong></li>
                </ul>`,
            confirmLabel: 'Approve Deletion',
            btnClass: 'btn-danger',
            needsReason: false,
        },
        'reject': {
            title: 'Reject Deletion Request',
            text: (name) => `
                <p class="mb-0">
                    Reject the deletion request for
                    <strong>${escapeHtml(name)}</strong>?
                    The customer's account will be restored to <strong>active</strong> status.
                </p>`,
            confirmLabel: 'Reject Request',
            btnClass: 'btn-secondary',
            needsReason: true,
        },
    };

    window.openActionModal = function (deletionId, action, customerName) {
        const cfg = CONFIG[action];
        if (!cfg) return;

        pendingAction = { id: deletionId, action: action };

        modalTitle.textContent = cfg.title;
        modalText.innerHTML    = cfg.text(customerName);
        confirmBtn.textContent = cfg.confirmLabel;
        confirmBtn.className   = 'btn ' + cfg.btnClass;

        if (cfg.needsReason) {
            reasonWrap.classList.remove('d-none');
            reasonIn.value = '';
            reasonIn.classList.remove('is-invalid');
            reasonErr.textContent = '';
            reasonErr.style.display = 'none';
            reasonCnt.textContent = '0';
        } else {
            reasonWrap.classList.add('d-none');
        }

        modal.show();

        if (cfg.needsReason) {
            setTimeout(() => reasonIn.focus(), 300);
        }
    };

    // Character counter
    reasonIn.addEventListener('input', () => {
        reasonCnt.textContent = reasonIn.value.length;
        if (reasonIn.classList.contains('is-invalid') && reasonIn.value.trim().length >= 10) {
            reasonIn.classList.remove('is-invalid');
            reasonErr.style.display = 'none';
        }
    });

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { id, action } = pendingAction;

        // Client-side validation for rejection
        let reason = '';
        if (action === 'reject') {
            reason = reasonIn.value.trim();
            if (reason.length < 10) {
                reasonIn.classList.add('is-invalid');
                reasonErr.textContent = 'Rejection reason must be at least 10 characters.';
                reasonErr.style.display = 'block';
                return;
            }
            if (reason.length > 2000) {
                reasonIn.classList.add('is-invalid');
                reasonErr.textContent = 'Rejection reason must not exceed 2000 characters.';
                reasonErr.style.display = 'block';
                return;
            }
        }

        confirmBtn.disabled = true;
        const originalText = confirmBtn.textContent;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const body = new URLSearchParams({
                action: action,
                _csrf: csrf,
            });
            if (action === 'reject') {
                body.append('reason', reason);
            }

            const res = await fetch('/admin/customers/deletion-requests/' + id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Request processed.', 'success');
                modal.hide();
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(json.message || 'Action failed.', 'danger');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalText;
        }
    });

    document.getElementById('actionModal').addEventListener('hidden.bs.modal', () => {
        pendingAction = null;
        confirmBtn.disabled = false;
        reasonIn.value = '';
        reasonIn.classList.remove('is-invalid');
        reasonErr.style.display = 'none';
    });

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
        new bootstrap.Toast(toast, { delay: 4000 }).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }
})();
</script>
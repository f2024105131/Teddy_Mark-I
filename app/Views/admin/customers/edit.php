<?php
/**
 * Admin Customers — Edit Form
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\CustomerController::edit()
 *
 * Expected variables:
 *   $customer  array  full customer row
 *
 * Posts to: POST /admin/customers/{id}
 * Endpoint: Admin\CustomerController::update()
 * ----------------------------------------------------------
 */

$currentPage = 'customers';
$title       = 'Edit Customer';

// Safe defaults
$customer = $customer ?? [];

if (empty($customer)) {
    echo '<div class="alert alert-danger">Customer not found.</div>';
    return;
}

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

$cStatus  = $customer['account_status'] ?? 'pending';
$verified = (int) ($customer['email_verified'] ?? 0);

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
?>

<!-- ==================== HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/customers/<?= (int) $customer['customer_id'] ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1">Edit Customer</h1>
            <div class="d-flex flex-wrap gap-2 align-items-center small">
                <span class="text-muted">#<?= (int) $customer['customer_id'] ?></span>
                <span class="text-muted">·</span>
                <span class="fw-medium"><?= $e($customer['full_name']) ?></span>
                <span class="badge bg-<?= $statusBadge($cStatus) ?>">
                    <?= $e($statusLabel($cStatus)) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/customers/<?= (int) $customer['customer_id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg me-1"></i>Cancel
        </a>
        <button type="submit" form="editCustomerForm" class="btn btn-primary btn-sm" id="saveBtn">
            <i class="bi bi-check-lg me-1"></i>Save Changes
        </button>
    </div>
</div>

<!-- ==================== FORM ==================== -->
<form id="editCustomerForm"
      method="post"
      action="/admin/customers/<?= (int) $customer['customer_id'] ?>"
      novalidate>
    <input type="hidden" name="_csrf"
           value="<?= $e($_SESSION['_csrf'] ?? '') ?>">

    <div class="row g-3">

        <!-- ==================== LEFT: PERSONAL ==================== -->
        <div class="col-lg-8">

            <!-- Personal Information -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-person me-2 text-primary"></i>Personal Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Full name -->
                        <div class="col-md-6">
                            <label for="full_name" class="form-label small fw-medium">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="full_name"
                                   name="full_name"
                                   value="<?= $e($customer['full_name']) ?>"
                                   maxlength="255"
                                   required>
                            <div class="invalid-feedback" data-field="full_name"></div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-medium">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   value="<?= $e($customer['email']) ?>"
                                   maxlength="255"
                                   required>
                            <div class="invalid-feedback" data-field="email"></div>
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label for="phone" class="form-label small fw-medium">
                                Phone <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="phone"
                                   name="phone"
                                   value="<?= $e($customer['phone']) ?>"
                                   maxlength="20"
                                   required>
                            <div class="invalid-feedback" data-field="phone"></div>
                            <div class="form-text small">
                                Format: 7–20 digits. Spaces and dashes are allowed.
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label for="account_status" class="form-label small fw-medium">
                                Account Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="account_status" name="account_status" required>
                                <option value="active"             <?= $cStatus==='active'             ?'selected':'' ?>>Active</option>
                                <option value="pending"            <?= $cStatus==='pending'            ?'selected':'' ?>>Pending</option>
                                <option value="suspended"          <?= $cStatus==='suspended'          ?'selected':'' ?>>Suspended</option>
                                <option value="deletion_requested" <?= $cStatus==='deletion_requested' ?'selected':'' ?>>Deletion Requested</option>
                            </select>
                            <div class="invalid-feedback" data-field="account_status"></div>
                        </div>

                        <!-- Email verified -->
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="email_verified"
                                       name="email_verified"
                                       value="1"
                                       <?= $verified ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="email_verified">
                                    Email verified
                                    <span class="text-muted">
                                        · Mark the customer's email as verified
                                    </span>
                                </label>
                            </div>
                        </div>
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
                <div class="card-body">
                    <div class="row g-3">
                        <!-- House -->
                        <div class="col-md-4">
                            <label for="address_house" class="form-label small fw-medium">
                                House / Flat <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="address_house"
                                   name="address_house"
                                   value="<?= $e($customer['address_house']) ?>"
                                   maxlength="50"
                                   required>
                            <div class="invalid-feedback" data-field="address_house"></div>
                        </div>

                        <!-- Street -->
                        <div class="col-md-8">
                            <label for="address_street" class="form-label small fw-medium">
                                Street <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="address_street"
                                   name="address_street"
                                   value="<?= $e($customer['address_street']) ?>"
                                   maxlength="100"
                                   required>
                            <div class="invalid-feedback" data-field="address_street"></div>
                        </div>

                        <!-- Area -->
                        <div class="col-md-6">
                            <label for="address_area" class="form-label small fw-medium">
                                Area <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="address_area"
                                   name="address_area"
                                   value="<?= $e($customer['address_area']) ?>"
                                   maxlength="100"
                                   required>
                            <div class="invalid-feedback" data-field="address_area"></div>
                        </div>

                        <!-- City -->
                        <div class="col-md-6">
                            <label for="city" class="form-label small fw-medium">
                                City <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="city"
                                   name="city"
                                   value="<?= $e($customer['city']) ?>"
                                   maxlength="50"
                                   required>
                            <div class="invalid-feedback" data-field="city"></div>
                        </div>

                        <!-- Landmark -->
                        <div class="col-12">
                            <label for="landmark" class="form-label small fw-medium">
                                Landmark
                                <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="landmark"
                                   name="landmark"
                                   value="<?= $e($customer['landmark'] ?? '') ?>"
                                   maxlength="100"
                                   placeholder="e.g., Near Mini Market">
                            <div class="invalid-feedback" data-field="landmark"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== RIGHT: META ==================== -->
        <div class="col-lg-4">

            <!-- Record Meta -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-info-circle me-2 text-primary"></i>Record Information
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="info-row">
                        <div class="info-label">Customer ID</div>
                        <div class="info-value">
                            <code>#<?= (int) $customer['customer_id'] ?></code>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Created</div>
                        <div class="info-value small"><?= $fmtDate($customer['created_at']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Last Updated</div>
                        <div class="info-value small"><?= $fmtDate($customer['updated_at'] ?? null) ?></div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card border-0 shadow-sm border-start border-danger border-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <p class="small text-muted mb-3">
                        These actions take effect immediately and are logged.
                    </p>
                    <?php if ($cStatus !== 'suspended'): ?>
                        <button type="button"
                                class="btn btn-outline-danger btn-sm w-100 mb-2"
                                onclick="customerAction('suspend', <?= (int) $customer['customer_id'] ?>)">
                            <i class="bi bi-person-slash me-1"></i>Suspend Account
                        </button>
                    <?php else: ?>
                        <button type="button"
                                class="btn btn-outline-success btn-sm w-100 mb-2"
                                onclick="customerAction('activate', <?= (int) $customer['customer_id'] ?>)">
                            <i class="bi bi-person-check me-1"></i>Activate Account
                        </button>
                    <?php endif; ?>
                    <button type="button"
                            class="btn btn-outline-warning btn-sm w-100 mb-2"
                            onclick="customerAction('reset-password', <?= (int) $customer['customer_id'] ?>)">
                        <i class="bi bi-key me-1"></i>Send Password Reset
                    </button>
                    <?php if (!$verified): ?>
                        <button type="button"
                                class="btn btn-outline-primary btn-sm w-100"
                                onclick="customerAction('verify-email', <?= (int) $customer['customer_id'] ?>)">
                            <i class="bi bi-patch-check me-1"></i>Verify Email
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== STICKY SAVE BAR (mobile) ==================== -->
    <div class="d-lg-none mt-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex gap-2">
                <a href="/admin/customers/<?= (int) $customer['customer_id'] ?>"
                   class="btn btn-outline-secondary flex-grow-1">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</form>

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
    /* Highlight invalid fields */
    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }
    .form-control.is-invalid:focus,
    .form-select.is-invalid:focus {
        box-shadow: 0 0 0 .2rem rgba(220,53,69,.15);
    }
    /* Sticky save bar visual polish */
    #editCustomerForm .card-header {
        padding-top: .85rem;
        padding-bottom: .85rem;
    }
    @media (min-width: 992px) {
        #saveBtn {
            min-width: 140px;
        }
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const form = document.getElementById('editCustomerForm');
    const saveBtn = document.getElementById('saveBtn');

    const customerName = <?= json_encode($customer['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const customerId   = <?= (int) $customer['customer_id'] ?>;

    // ---------- AJAX save for the main form ----------
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Clear previous errors
        clearErrors();

        // Basic client-side validation
        const errs = clientValidate();
        if (Object.keys(errs).length) {
            showErrors(errs);
            return;
        }

        saveBtn.disabled = true;
        const originalHTML = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            const formData = new FormData(form);

            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Customer updated successfully.', 'success');
                setTimeout(() => {
                    window.location.href = json.redirect || ('/admin/customers/' + customerId);
                }, 700);
            } else {
                if (json.errors && typeof json.errors === 'object') {
                    showErrors(json.errors);
                }
                showToast(json.message || 'Please fix the highlighted fields.', 'danger');
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalHTML;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalHTML;
        }
    });

    // ---------- Client validation (mirrors server rules) ----------
    function clientValidate() {
        const errs = {};

        const fullName = form.full_name.value.trim();
        if (fullName.length < 3 || fullName.length > 255) {
            errs.full_name = 'Full name must be between 3 and 255 characters.';
        }

        const email = form.email.value.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errs.email = 'A valid email is required.';
        }

        const phone = form.phone.value.trim();
        if (!/^[0-9+\-\s]{7,20}$/.test(phone)) {
            errs.phone = 'Phone must be 7–20 digits (spaces/dashes allowed).';
        }

        if (!form.address_house.value.trim()) {
            errs.address_house = 'House / Flat is required.';
        }
        if (!form.address_street.value.trim()) {
            errs.address_street = 'Street is required.';
        }
        if (!form.address_area.value.trim()) {
            errs.address_area = 'Area is required.';
        }
        if (!form.city.value.trim()) {
            errs.city = 'City is required.';
        }

        return errs;
    }

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
    }

    function showErrors(errs) {
        for (const [field, message] of Object.entries(errs)) {
            const input = form.querySelector('[name="' + field + '"]');
            const feedback = form.querySelector('.invalid-feedback[data-field="' + field + '"]');

            if (input) input.classList.add('is-invalid');
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }

        // Scroll to first error
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
        }
    }

    // ---------- Quick actions (suspend / activate / verify / reset) ----------
    const modal       = new bootstrap.Modal(document.getElementById('actionModal'));
    const modalTitle  = document.getElementById('actionModalTitle');
    const modalText   = document.getElementById('actionModalText');
    const reasonWrap  = document.getElementById('actionReasonWrap');
    const reasonInput = document.getElementById('actionReason');
    const confirmBtn  = document.getElementById('actionConfirmBtn');

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
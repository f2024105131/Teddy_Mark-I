<?php
/**
 * Admin Staff — Edit Existing Staff Member
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\StaffController::edit()
 *
 * Expected variables:
 *   $staff  array  full staff row
 *   $roles  array  ['admin', 'staff']
 *
 * Posts to: POST /admin/staff/{id}
 * Endpoint: Admin\StaffController::update()
 *
 * Success JSON:
 *   { success: true, message, redirect }
 * Error JSON:
 *   { success: false, message, errors: { field: msg } }
 * ----------------------------------------------------------
 */

$currentPage = 'staff';
$title       = 'Edit Staff Member';

// Safe defaults
$staff = $staff ?? [];
$roles = $roles ?? ['admin', 'staff'];

if (empty($staff)) {
    echo '<div class="alert alert-danger">Staff member not found.</div>';
    return;
}

$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtDate = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

$sRole    = $staff['role']      ?? 'staff';
$isActive = (int) ($staff['is_active'] ?? 0);
$isSelf   = (int) $staff['staff_id'] === (int) ($_SESSION['staff_id'] ?? 0);

$roleBadge = fn(string $r): string => $r === 'admin' ? 'danger' : 'primary';
$roleLabel = fn(string $r): string => $r === 'admin' ? 'Admin' : 'Staff';
?>

<!-- ==================== HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="d-flex align-items-center gap-3">
            <div class="avatar-circle bg-<?= $isActive ? 'success' : 'secondary' ?>-subtle text-<?= $isActive ? 'success' : 'secondary' ?>">
                <?= $e(strtoupper(substr($staff['full_name'] ?? '?', 0, 1))) ?>
            </div>
            <div>
                <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                    Edit Staff Member
                    <?php if ($isSelf): ?>
                        <span class="badge bg-info-subtle text-info" style="font-size:.7rem;">You</span>
                    <?php endif; ?>
                </h1>
                <div class="d-flex flex-wrap gap-2 align-items-center small">
                    <span class="text-muted">#<?= (int) $staff['staff_id'] ?></span>
                    <span class="text-muted">·</span>
                    <span class="fw-medium"><?= $e($staff['full_name']) ?></span>
                    <span class="badge bg-<?= $roleBadge($sRole) ?>">
                        <?php if ($sRole === 'admin'): ?>
                            <i class="bi bi-shield-check me-1"></i>
                        <?php endif; ?>
                        <?= $e($roleLabel($sRole)) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg me-1"></i>Cancel
        </a>
        <button type="submit" form="editStaffForm" class="btn btn-primary btn-sm" id="saveBtn">
            <i class="bi bi-check-lg me-1"></i>Save Changes
        </button>
    </div>
</div>

<!-- ==================== FORM ==================== -->
<form id="editStaffForm"
      method="post"
      action="/admin/staff/<?= (int) $staff['staff_id'] ?>"
      novalidate>
    <input type="hidden" name="_csrf"
           value="<?= $e($_SESSION['_csrf'] ?? '') ?>">

    <div class="row g-3">

        <!-- ==================== LEFT: FORM FIELDS ==================== -->
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
                                   value="<?= $e($staff['full_name']) ?>"
                                   maxlength="255"
                                   autocomplete="name"
                                   required>
                            <div class="invalid-feedback" data-field="full_name"></div>
                        </div>

                        <!-- Role -->
                        <div class="col-md-6">
                            <label for="role" class="form-label small fw-medium">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="role" name="role" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $e($r) ?>" <?= $sRole === $r ? 'selected' : '' ?>>
                                        <?= $e($roleLabel($r)) ?>
                                        <?= $r === 'admin' ? ' (full access)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-field="role"></div>
                            <?php if ($sRole === 'admin'): ?>
                                <div class="form-text small">
                                    <i class="bi bi-shield-exclamation me-1"></i>
                                    Cannot demote the last active admin.
                                </div>
                            <?php endif; ?>
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
                                   value="<?= $e($staff['email']) ?>"
                                   maxlength="255"
                                   autocomplete="email"
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
                                   value="<?= $e($staff['phone']) ?>"
                                   maxlength="20"
                                   autocomplete="tel"
                                   required>
                            <div class="invalid-feedback" data-field="phone"></div>
                            <div class="form-text small">
                                7–20 digits. Spaces and dashes allowed.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Status -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-toggle-on me-2 text-primary"></i>Account Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input"
                               type="checkbox"
                               id="is_active"
                               name="is_active"
                               value="1"
                               <?= $isActive ? 'checked' : '' ?>
                               <?= $isSelf ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            <span class="fw-medium">Active account</span>
                            <span class="text-muted small d-block">
                                Inactive staff cannot log in but their history is preserved.
                            </span>
                        </label>
                    </div>
                    <?php if ($isSelf): ?>
                        <div class="alert alert-info small mt-3 mb-0 py-2">
                            <i class="bi bi-info-circle me-1"></i>
                            You cannot deactivate your own account.
                            A hidden input preserves your current state.
                        </div>
                        <!-- Send the current state even when disabled -->
                        <input type="hidden" name="is_active" value="<?= $isActive ?>">
                    <?php endif; ?>

                    <hr class="my-3">

                    <div class="row small text-muted g-2">
                        <div class="col-md-6">
                            <i class="bi bi-calendar-plus me-1"></i>
                            Joined: <strong><?= $fmtDate($staff['created_at']) ?></strong>
                        </div>
                        <div class="col-md-6">
                            <i class="bi bi-clock-history me-1"></i>
                            Last login:
                            <strong>
                                <?= !empty($staff['last_login'])
                                    ? $e(date('M j, Y · g:i A', strtotime($staff['last_login'])))
                                    : 'Never' ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password Reset Notice -->
            <div class="alert alert-light border mt-3 mb-0 d-flex align-items-start gap-2">
                <i class="bi bi-key text-warning mt-1"></i>
                <div class="small">
                    <strong>Need to change this staff member's password?</strong>
                    <div class="text-muted mt-1">
                        Password changes are handled separately via
                        <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>"
                           class="text-decoration-none">
                            the profile page
                        </a>
                        → <em>Actions → Reset Password</em>.
                        You can either send a reset link or set a new password directly.
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== RIGHT: INFO + DANGER ==================== -->
        <div class="col-lg-4">

            <!-- Record meta -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-info-circle me-2 text-primary"></i>Record Information
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="info-row">
                        <div class="info-label">Staff ID</div>
                        <div class="info-value"><code>#<?= (int) $staff['staff_id'] ?></code></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Current Role</div>
                        <div class="info-value">
                            <span class="badge bg-<?= $roleBadge($sRole) ?>">
                                <?= $e($roleLabel($sRole)) ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <?php if ($isActive): ?>
                                <span class="badge bg-success-subtle text-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Created</div>
                        <div class="info-value small"><?= $fmtDate($staff['created_at']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Last Updated</div>
                        <div class="info-value small"><?= $fmtDate($staff['updated_at'] ?? null) ?></div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm border-start border-danger border-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold text-danger">
                        <i class="bi bi-lightning-charge me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <p class="small text-muted mb-3">
                        These actions take effect immediately and are logged.
                    </p>

                    <button type="button"
                            class="btn btn-outline-warning btn-sm w-100 mb-2"
                            onclick="staffAction('reset-password', <?= (int) $staff['staff_id'] ?>, <?= json_encode($staff['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                        <i class="bi bi-key me-1"></i>Reset Password
                    </button>

                    <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>/performance"
                       class="btn btn-outline-primary btn-sm w-100 mb-2">
                        <i class="bi bi-graph-up me-1"></i>View Performance
                    </a>

                    <?php if (!$isSelf): ?>
                        <?php if ($isActive): ?>
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm w-100"
                                    onclick="staffAction('toggle-active', <?= (int) $staff['staff_id'] ?>, <?= json_encode($staff['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                                <i class="bi bi-person-slash me-1"></i>Deactivate Account
                            </button>
                        <?php else: ?>
                            <button type="button"
                                    class="btn btn-outline-success btn-sm w-100"
                                    onclick="staffAction('toggle-active', <?= (int) $staff['staff_id'] ?>, <?= json_encode($staff['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                                <i class="bi bi-person-check me-1"></i>Activate Account
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== STICKY SAVE BAR (mobile) ==================== -->
    <div class="d-lg-none mt-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex gap-2">
                <a href="/admin/staff/<?= (int) $staff['staff_id'] ?>"
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

                <div id="passwordWrap" class="d-none">
                    <div class="alert alert-info small mb-3 py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Leave fields blank to email a reset link. Fill to set a new password now.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">New Password</label>
                        <input type="password" class="form-control" id="newPassword"
                               placeholder="Leave blank to email reset link"
                               autocomplete="new-password">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">Confirm Password</label>
                        <input type="password" class="form-control" id="confirmPassword"
                               placeholder="Repeat password"
                               autocomplete="new-password">
                    </div>
                    <div class="form-text small">
                        Min 8 chars, must contain a letter and a number.
                    </div>
                    <div class="invalid-feedback d-block" id="passwordError" style="display:none;"></div>
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
        width: 44px; height: 44px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: 1rem;
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
    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }
    .form-control.is-invalid:focus,
    .form-select.is-invalid:focus {
        box-shadow: 0 0 0 .2rem rgba(220,53,69,.15);
    }
    @media (min-width: 992px) {
        #saveBtn { min-width: 140px; }
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const form = document.getElementById('editStaffForm');
    const saveBtn = document.getElementById('saveBtn');

    const staffName = <?= json_encode($staff['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const staffId   = <?= (int) $staff['staff_id'] ?>;
    const currentRole = <?= json_encode($sRole) ?>;

    // ---------- AJAX submit ----------
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        clearErrors();

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
                showToast(json.message || 'Staff member updated successfully.', 'success');
                setTimeout(() => {
                    window.location.href = json.redirect || ('/admin/staff/' + staffId);
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

    // ---------- Client validation (mirrors server) ----------
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

        const role = form.role.value;
        if (!['admin', 'staff'].includes(role)) {
            errs.role = 'Invalid role selected.';
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

        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
        }
    }

    // ---------- Action modal ----------
    const modal        = new bootstrap.Modal(document.getElementById('actionModal'));
    const modalTitle   = document.getElementById('actionModalTitle');
    const modalText    = document.getElementById('actionModalText');
    const passwordWrap = document.getElementById('passwordWrap');
    const newPwd       = document.getElementById('newPassword');
    const confirmPwd   = document.getElementById('confirmPassword');
    const pwdErr       = document.getElementById('passwordError');
    const confirmBtn   = document.getElementById('confirmBtn');

    let pendingAction = null;

    const ACTIONS = {
        'reset-password': {
            title: 'Reset Password',
            text: (n) => `Generate a password reset for <strong>${escapeHtml(n)}</strong>?`,
            confirmLabel: 'Reset Password',
            btnClass: 'btn-warning',
            showPassword: true,
        },
        'toggle-active': {
            title: 'Toggle Account Status',
            text: (n) => `Toggle account status for <strong>${escapeHtml(n)}</strong>?`,
            confirmLabel: 'Confirm',
            btnClass: 'btn-danger',
            showPassword: false,
        },
    };

    window.staffAction = function (action, id, name) {
        const cfg = ACTIONS[action];
        if (!cfg) return;

        pendingAction = { type: action, id: id };

        modalTitle.textContent = cfg.title;
        modalText.innerHTML    = cfg.text(name);
        confirmBtn.textContent = cfg.confirmLabel;
        confirmBtn.className   = 'btn ' + cfg.btnClass;

        passwordWrap.classList.add('d-none');
        pwdErr.style.display = 'none';

        if (cfg.showPassword) {
            passwordWrap.classList.remove('d-none');
            newPwd.value = '';
            confirmPwd.value = '';
        }

        modal.show();
        setTimeout(() => {
            if (cfg.showPassword) newPwd.focus();
        }, 300);
    };

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { type, id } = pendingAction;
        const body = new URLSearchParams({ _csrf: csrf });

        if (type === 'reset-password') {
            const pwd = newPwd.value;
            const cfm = confirmPwd.value;

            if (pwd !== '' || cfm !== '') {
                if (pwd.length < 8) {
                    pwdErr.textContent = 'Password must be at least 8 characters.';
                    pwdErr.style.display = 'block'; return;
                }
                if (pwd.length > 72) {
                    pwdErr.textContent = 'Password must not exceed 72 characters.';
                    pwdErr.style.display = 'block'; return;
                }
                if (!/[A-Za-z]/.test(pwd)) {
                    pwdErr.textContent = 'Password must contain at least one letter.';
                    pwdErr.style.display = 'block'; return;
                }
                if (!/[0-9]/.test(pwd)) {
                    pwdErr.textContent = 'Password must contain at least one number.';
                    pwdErr.style.display = 'block'; return;
                }
                if (pwd !== cfm) {
                    pwdErr.textContent = 'Passwords do not match.';
                    pwdErr.style.display = 'block'; return;
                }
                body.append('mode', 'set');
                body.append('new_password', pwd);
                body.append('confirm_password', cfm);
            } else {
                body.append('mode', 'token');
            }
        }

        confirmBtn.disabled = true;
        const originalText = confirmBtn.textContent;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const url = buildUrl(type, id);
            if (!url) { modal.hide(); return; }

            const res = await fetch(url, {
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

    function buildUrl(type, id) {
        const base = '/admin/staff/' + id;
        switch (type) {
            case 'reset-password': return base + '/reset-password';
            case 'toggle-active':  return base + '/toggle-active';
            default:               return null;
        }
    }

    document.getElementById('actionModal').addEventListener('hidden.bs.modal', () => {
        pendingAction = null;
        confirmBtn.disabled = false;
        pwdErr.style.display = 'none';
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
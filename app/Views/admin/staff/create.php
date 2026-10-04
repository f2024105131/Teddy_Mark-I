<?php
/**
 * Admin Staff — Create New Staff Member
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\StaffController::create()
 *
 * Expected variables:
 *   $roles  array  ['admin', 'staff']
 *
 * Posts to: POST /admin/staff
 * Endpoint: Admin\StaffController::store()
 *
 * Success JSON:
 *   { success: true, message, staff_id, redirect }
 * Error JSON:
 *   { success: false, message, errors: { field: msg } }
 * ----------------------------------------------------------
 */

$currentPage = 'staff';
$title       = 'Add Staff Member';

// Safe defaults
$roles = $roles ?? ['admin', 'staff'];

$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$roleBadge = fn(string $r): string => $r === 'admin' ? 'danger' : 'primary';
$roleLabel = fn(string $r): string => $r === 'admin' ? 'Admin' : 'Staff';
?>

<!-- ==================== HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/staff" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1">Add Staff Member</h1>
            <p class="text-muted mb-0 small">
                Create a new staff account with login credentials
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/staff" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg me-1"></i>Cancel
        </a>
        <button type="submit" form="createStaffForm" class="btn btn-primary btn-sm" id="saveBtn">
            <i class="bi bi-check-lg me-1"></i>Create Staff
        </button>
    </div>
</div>

<!-- ==================== FORM ==================== -->
<form id="createStaffForm"
      method="post"
      action="/admin/staff"
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
                                   value=""
                                   maxlength="255"
                                   autocomplete="name"
                                   required
                                   autofocus>
                            <div class="invalid-feedback" data-field="full_name"></div>
                            <div class="form-text small">
                                3–255 characters.
                            </div>
                        </div>

                        <!-- Role -->
                        <div class="col-md-6">
                            <label for="role" class="form-label small fw-medium">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="role" name="role" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $e($r) ?>" <?= $r === 'staff' ? 'selected' : '' ?>>
                                        <?= $e($roleLabel($r)) ?>
                                        <?= $r === 'admin' ? ' (full access)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-field="role"></div>
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
                                   value=""
                                   maxlength="255"
                                   autocomplete="email"
                                   required>
                            <div class="invalid-feedback" data-field="email"></div>
                            <div class="form-text small">
                                Must be unique across all staff accounts.
                            </div>
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
                                   value=""
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

            <!-- Login Credentials -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-shield-lock me-2 text-primary"></i>Login Credentials
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Password -->
                        <div class="col-md-6">
                            <label for="password" class="form-label small fw-medium">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       autocomplete="new-password"
                                       required>
                                <button type="button"
                                        class="btn btn-outline-secondary toggle-password"
                                        data-target="password"
                                        tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback" data-field="password"></div>

                            <!-- Strength indicator -->
                            <div class="password-strength mt-2" id="strengthWrap" style="display:none;">
                                <div class="d-flex gap-1">
                                    <div class="strength-bar" data-bar="1"></div>
                                    <div class="strength-bar" data-bar="2"></div>
                                    <div class="strength-bar" data-bar="3"></div>
                                    <div class="strength-bar" data-bar="4"></div>
                                </div>
                                <div class="small mt-1" id="strengthLabel"></div>
                            </div>

                            <div class="form-text small">
                                Min 8 characters, must include a letter and a number.
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label small fw-medium">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password"
                                       class="form-control"
                                       id="password_confirm"
                                       name="password_confirm"
                                       autocomplete="new-password"
                                       required>
                                <button type="button"
                                        class="btn btn-outline-secondary toggle-password"
                                        data-target="password_confirm"
                                        tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback" data-field="password_confirm"></div>
                            <div class="form-text small">
                                Must match the password above.
                            </div>
                        </div>

                        <!-- Active -->
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="is_active"
                                       name="is_active"
                                       value="1"
                                       checked>
                                <label class="form-check-label small" for="is_active">
                                    Activate account immediately
                                    <span class="text-muted">
                                        · Uncheck to create the account in an inactive state
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== RIGHT: INFO CARDS ==================== -->
        <div class="col-lg-4">

            <!-- Role info -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-shield-check me-2 text-primary"></i>Role Permissions
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="role-info">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary">Staff</span>
                        </div>
                        <ul class="small text-muted mb-3 ps-3">
                            <li>Manage orders and pickups</li>
                            <li>Handle deliveries</li>
                            <li>Process complaints</li>
                            <li>No access to settings or staff management</li>
                        </ul>
                    </div>
                    <hr class="my-2">
                    <div class="role-info">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-danger">
                                <i class="bi bi-shield-check me-1"></i>Admin
                            </span>
                        </div>
                        <ul class="small text-muted mb-0 ps-3">
                            <li>Full access to all features</li>
                            <li>Manage staff, catalog, and settings</li>
                            <li>Process refunds and escalations</li>
                            <li>Cannot deactivate the last active admin</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-lightbulb me-2 text-warning"></i>Tips
                    </h6>
                </div>
                <div class="card-body pt-0 small text-muted">
                    <ul class="ps-3 mb-0">
                        <li>Use a real email — password reset links are sent there.</li>
                        <li>The new staff member will be able to log in immediately
                            (unless you uncheck "Activate account").</li>
                        <li>Consider inviting them to change their password after first login.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== STICKY SAVE BAR (mobile) ==================== -->
    <div class="d-lg-none mt-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex gap-2">
                <a href="/admin/staff" class="btn btn-outline-secondary flex-grow-1">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i>Create Staff
                </button>
            </div>
        </div>
    </div>
</form>

<!-- ==================== STYLES ==================== -->
<style>
    .password-strength .strength-bar {
        height: 4px;
        flex: 1;
        background: #e2e8f0;
        border-radius: 2px;
        transition: background .2s;
    }
    .password-strength .strength-bar.active[data-level="1"] { background: #ef4444; }
    .password-strength .strength-bar.active[data-level="2"] { background: #f59e0b; }
    .password-strength .strength-bar.active[data-level="3"] { background: #3b82f6; }
    .password-strength .strength-bar.active[data-level="4"] { background: #22c55e; }

    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }
    .form-control.is-invalid:focus,
    .form-select.is-invalid:focus {
        box-shadow: 0 0 0 .2rem rgba(220,53,69,.15);
    }
    .input-group .form-control.is-invalid {
        border-top-right-radius: .375rem;
        border-bottom-right-radius: .375rem;
    }
    @media (min-width: 992px) {
        #saveBtn { min-width: 140px; }
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const form = document.getElementById('createStaffForm');
    const saveBtn = document.getElementById('saveBtn');

    // ---------- Toggle password visibility ----------
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            const isPwd = target.type === 'password';
            target.type = isPwd ? 'text' : 'password';
            btn.querySelector('i').className = isPwd ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // ---------- Password strength indicator ----------
    const pwdInput = document.getElementById('password');
    const strengthWrap = document.getElementById('strengthWrap');
    const strengthLabel = document.getElementById('strengthLabel');
    const bars = strengthWrap.querySelectorAll('.strength-bar');

    function scorePassword(pwd) {
        if (!pwd) return 0;
        let score = 0;
        if (pwd.length >= 8)  score++;
        if (pwd.length >= 12) score++;
        if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd) && /[^A-Za-z0-9]/.test(pwd)) score++;
        return Math.min(score, 4);
    }

    pwdInput.addEventListener('input', () => {
        const pwd = pwdInput.value;
        if (!pwd) {
            strengthWrap.style.display = 'none';
            return;
        }
        strengthWrap.style.display = 'block';

        const score = scorePassword(pwd);
        const labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
        const colors = ['#ef4444', '#ef4444', '#f59e0b', '#3b82f6', '#22c55e'];

        bars.forEach((bar, i) => {
            bar.classList.toggle('active', i < score);
            bar.setAttribute('data-level', i + 1);
        });

        strengthLabel.textContent = labels[score] || 'Very Weak';
        strengthLabel.style.color = colors[score] || '#ef4444';
    });

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
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating…';

        try {
            const formData = new FormData(form);

            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Staff member created successfully.', 'success');
                setTimeout(() => {
                    window.location.href = json.redirect || ('/admin/staff/' + (json.staff_id || ''));
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

    // ---------- Client-side validation ----------
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

        const pwd = form.password.value;
        const cfm = form.password_confirm.value;

        if (pwd.length < 8) {
            errs.password = 'Password must be at least 8 characters.';
        } else if (pwd.length > 72) {
            errs.password = 'Password must not exceed 72 characters.';
        } else if (!/[A-Za-z]/.test(pwd)) {
            errs.password = 'Password must contain at least one letter.';
        } else if (!/[0-9]/.test(pwd)) {
            errs.password = 'Password must contain at least one number.';
        }

        if (pwd !== cfm) {
            errs.password_confirm = 'Passwords do not match.';
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
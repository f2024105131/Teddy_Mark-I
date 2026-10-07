<?php
/**
 * Admin Catalog — Service Form (Create + Edit)
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by:
 *   • Admin\ServiceController::create()   → mode = 'create'
 *   • Admin\ServiceController::edit()     → mode = 'edit'
 *
 * Expected variables:
 *   $mode        string  'create' | 'edit'
 *   $service     ?array  null on create; full row on edit
 *   $types       array   enum values ['wash_fold','dry_cleaning',...]
 *   $typeLabels  array   map ['wash_fold' => 'Wash & Fold', ...]
 *
 * Posts to:
 *   • mode=create  →  POST /admin/catalog/services
 *   • mode=edit    →  POST /admin/catalog/services/{id}
 * ----------------------------------------------------------
 */

$currentPage = 'catalog-services';

$isEdit  = ($mode ?? 'create') === 'edit';
$service = $service ?? null;
$title   = $isEdit ? 'Edit Service' : 'Add Service';

if ($isEdit && empty($service)) {
    echo '<div class="alert alert-danger">Service not found.</div>';
    return;
}

// Safe defaults
$types      = $types      ?? [];
$typeLabels = $typeLabels ?? [];

$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// Resolve current values
$currentId          = $isEdit ? (int)    $service['service_id']       : 0;
$currentName        = $isEdit ? (string) $service['service_name']     : '';
$currentDesc        = $isEdit ? (string) ($service['description'] ?? '') : '';
$currentType        = $isEdit ? (string) $service['service_type']     : '';
$currentHours       = $isEdit ? (int)    $service['duration_hours']   : 0;
$currentDays        = $isEdit ? (int)    $service['duration_days']    : 0;
$currentActive      = $isEdit ? (int)    $service['is_active']        : 1;

// Form action + button labels
$formAction  = $isEdit
    ? '/admin/catalog/services/' . $currentId
    : '/admin/catalog/services';
$submitLabel = $isEdit ? 'Save Changes' : 'Create Service';
$cancelUrl   = $isEdit
    ? '/admin/catalog/services/' . $currentId
    : '/admin/catalog/services';

// Type label helper
$typeLabel = fn(string $t): string => $typeLabels[$t] ?? ucfirst(str_replace('_', ' ', $t));

// Initial total hours for live preview
$initialTotalHours = ($currentDays * 24) + $currentHours;
$initialDurationLabel = 'Instant';
if ($initialTotalHours > 0) {
    $parts = [];
    if ($currentDays > 0) {
        $parts[] = $currentDays === 1 ? '1 day' : "{$currentDays} days";
    }
    if ($currentHours > 0) {
        $parts[] = $currentHours === 1 ? '1 hour' : "{$currentHours} hours";
    }
    $initialDurationLabel = implode(' ', $parts);
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="<?= $e($cancelUrl) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1"><?= $e($title) ?></h1>
            <p class="text-muted mb-0 small">
                <?php if ($isEdit): ?>
                    Editing service <strong>#<?= $currentId ?></strong>
                    · Created <?= $e(date('M j, Y', strtotime($service['created_at']))) ?>
                <?php else: ?>
                    Add a new laundry service to the catalog
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $e($cancelUrl) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg me-1"></i>Cancel
        </a>
        <button type="submit" form="serviceForm" class="btn btn-primary btn-sm" id="saveBtn">
            <i class="bi bi-check-lg me-1"></i><?= $e($submitLabel) ?>
        </button>
    </div>
</div>

<!-- ==================== CATALOG NAV ==================== -->
<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <a class="nav-link" href="/admin/catalog/items">
            <i class="bi bi-basket me-1"></i>Items
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/admin/catalog/categories">
            <i class="bi bi-tags me-1"></i>Categories
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="/admin/catalog/services">
            <i class="bi bi-tag me-1"></i>Services
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/admin/catalog/pricing">
            <i class="bi bi-grid-3x3-gap me-1"></i>Pricing Matrix
        </a>
    </li>
</ul>

<!-- ==================== FORM ==================== -->
<form id="serviceForm"
      method="post"
      action="<?= $e($formAction) ?>"
      novalidate>
    <input type="hidden" name="_csrf" value="<?= $e($_SESSION['_csrf'] ?? '') ?>">

    <div class="row g-3">

        <!-- ==================== LEFT: FORM FIELDS ==================== -->
        <div class="col-lg-8">

            <!-- Basic Info -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-info-circle me-2 text-primary"></i>Service Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Service Name -->
                        <div class="col-md-6">
                            <label for="service_name" class="form-label small fw-medium">
                                Service Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="service_name"
                                   name="service_name"
                                   value="<?= $e($currentName) ?>"
                                   maxlength="50"
                                   placeholder="e.g., Wash & Fold, Express 24hr"
                                   required
                                   autofocus>
                            <div class="invalid-feedback" data-field="service_name"></div>
                            <div class="form-text small">
                                3–50 characters. Must be unique.
                            </div>
                        </div>

                        <!-- Service Type -->
                        <div class="col-md-6">
                            <label for="service_type" class="form-label small fw-medium">
                                Service Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="service_type" name="service_type" required>
                                <option value="">— Select a type —</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $e($t) ?>"
                                            <?= $currentType === $t ? 'selected' : '' ?>>
                                        <?= $e($typeLabel($t)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-field="service_type"></div>
                            <div class="form-text small">
                                Determines how the service is categorized internally.
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label for="description" class="form-label small fw-medium">
                                Description
                                <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <textarea class="form-control"
                                      id="description"
                                      name="description"
                                      rows="3"
                                      maxlength="255"
                                      placeholder="Short description (e.g., 'Standard wash and fold service')"><?= $e($currentDesc) ?></textarea>
                            <div class="invalid-feedback" data-field="description"></div>
                            <div class="form-text small">
                                <span id="descCounter"><?= strlen($currentDesc) ?></span>/255 characters
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duration -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Processing Duration
                    </h6>
                    <span class="badge bg-primary" id="durationPreview">
                        <?= $e($initialDurationLabel) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Days -->
                        <div class="col-md-6">
                            <label for="duration_days" class="form-label small fw-medium">
                                Days
                            </label>
                            <div class="input-group">
                                <input type="number"
                                       class="form-control"
                                       id="duration_days"
                                       name="duration_days"
                                       value="<?= $currentDays ?>"
                                       min="0"
                                       max="365"
                                       step="1">
                                <span class="input-group-text">day(s)</span>
                            </div>
                            <div class="invalid-feedback" data-field="duration_days"></div>
                            <div class="form-text small">
                                0–365 days.
                            </div>
                        </div>

                        <!-- Hours -->
                        <div class="col-md-6">
                            <label for="duration_hours" class="form-label small fw-medium">
                                Hours
                            </label>
                            <div class="input-group">
                                <input type="number"
                                       class="form-control"
                                       id="duration_hours"
                                       name="duration_hours"
                                       value="<?= $currentHours ?>"
                                       min="0"
                                       max="8760"
                                       step="1">
                                <span class="input-group-text">hour(s)</span>
                            </div>
                            <div class="invalid-feedback" data-field="duration_hours"></div>
                            <div class="form-text small">
                                0–8760 hours (max 1 year).
                            </div>
                        </div>

                        <!-- Total explanation -->
                        <div class="col-12">
                            <div class="alert alert-light border mb-0 py-2 small">
                                <i class="bi bi-calculator text-muted me-1"></i>
                                <strong>Total processing time:</strong>
                                <span id="durationTotalHours"><?= $initialTotalHours ?></span> hour(s)
                                · Displayed as
                                <span class="badge bg-secondary" id="durationTotalLabel">
                                    <?= $e($initialDurationLabel) ?>
                                </span>
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
                                       <?= $currentActive ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    <span class="fw-medium">Active</span>
                                    <span class="text-muted small d-block">
                                        Inactive services are hidden from customers and cannot be ordered.
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <!-- Pricing shortcut (edit mode only) -->
                <div class="alert alert-light border d-flex align-items-start gap-2 mb-0">
                    <i class="bi bi-cash-coin text-warning mt-1"></i>
                    <div class="small">
                        <strong>Pricing for this service</strong>
                        <div class="text-muted mt-1">
                            Service prices are managed per item through the pricing matrix.
                            <a href="/admin/catalog/pricing?service=<?= $currentId ?>" class="text-decoration-none">
                                Manage pricing for this service →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================== RIGHT: INFO PANEL ==================== -->
        <div class="col-lg-4">

            <!-- Service type reference -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-list-check me-2 text-primary"></i>Available Types
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <p class="small text-muted mb-2">
                        Choose the type that best describes this service:
                    </p>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($types as $t): ?>
                            <span class="badge bg-light text-dark border">
                                <?= $e($typeLabel($t)) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-lightbulb me-2 text-warning"></i>Tips
                    </h6>
                </div>
                <div class="card-body pt-0 small text-muted">
                    <ul class="ps-3 mb-0">
                        <li>Use clear names customers recognize ("Wash & Fold" not "WF")</li>
                        <li>Duration is the total <strong>processing time</strong> — not delivery time</li>
                        <li>Set 24h for express services</li>
                        <li>After creating, add <strong>pricing rows</strong> so customers can order</li>
                        <li>Deactivate instead of deleting if the service has order history</li>
                    </ul>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <!-- Record meta -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-info-circle me-2 text-primary"></i>Record Information
                        </h6>
                    </div>
                    <div class="card-body pt-0">
                        <div class="info-row">
                            <div class="info-label">Service ID</div>
                            <div class="info-value"><code>#<?= $currentId ?></code></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Created</div>
                            <div class="info-value small">
                                <?= $e(date('M j, Y', strtotime($service['created_at']))) ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value small">
                                <?php if (!empty($service['updated_at'])): ?>
                                    <?= $e(date('M j, Y · g:i A', strtotime($service['updated_at']))) ?>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================== MOBILE STICKY SAVE ==================== -->
    <div class="d-lg-none mt-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex gap-2">
                <a href="<?= $e($cancelUrl) ?>" class="btn btn-outline-secondary flex-grow-1">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i><?= $e($submitLabel) ?>
                </button>
            </div>
        </div>
    </div>
</form>

<!-- ==================== STYLES ==================== -->
<style>
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
        min-width: 100px;
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
    const form = document.getElementById('serviceForm');
    const saveBtn = document.getElementById('saveBtn');

    const isEdit  = <?= $isEdit ? 'true' : 'false' ?>;
    const serviceId = <?= $currentId ?>;

    // ---------- Description character counter ----------
    const descInput = document.getElementById('description');
    const descCounter = document.getElementById('descCounter');
    if (descInput && descCounter) {
        descInput.addEventListener('input', () => {
            descCounter.textContent = descInput.value.length;
        });
    }

    // ---------- Live duration preview ----------
    const daysInput  = document.getElementById('duration_days');
    const hoursInput = document.getElementById('duration_hours');
    const previewBadge = document.getElementById('durationPreview');
    const totalHoursEl = document.getElementById('durationTotalHours');
    const totalLabelEl = document.getElementById('durationTotalLabel');

    function updateDurationPreview() {
        const days  = Math.max(0, parseInt(daysInput.value, 10) || 0);
        const hours = Math.max(0, parseInt(hoursInput.value, 10) || 0);
        const total = (days * 24) + hours;

        // Total hours
        if (totalHoursEl) totalHoursEl.textContent = total;

        // Human label
        let label = 'Instant';
        if (total > 0) {
            const parts = [];
            if (days > 0) parts.push(days === 1 ? '1 day' : days + ' days');
            if (hours > 0) parts.push(hours === 1 ? '1 hour' : hours + ' hours');
            label = parts.join(' ');
        }

        if (totalLabelEl) totalLabelEl.textContent = label;
        if (previewBadge) previewBadge.textContent = label;
    }

    if (daysInput && hoursInput) {
        daysInput.addEventListener('input', updateDurationPreview);
        hoursInput.addEventListener('input', updateDurationPreview);
    }

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
                showToast(json.message || 'Service saved successfully.', 'success');
                setTimeout(() => {
                    window.location.href = json.redirect || '/admin/catalog/services';
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

    // ---------- Client-side validation (mirrors server) ----------
    function clientValidate() {
        const errs = {};

        const name = form.service_name.value.trim();
        if (name.length < 3) {
            errs.service_name = 'Service name must be at least 3 characters.';
        } else if (name.length > 50) {
            errs.service_name = 'Service name must not exceed 50 characters.';
        }

        const type = form.service_type.value;
        if (!type) {
            errs.service_type = 'Please select a service type.';
        }

        const desc = form.description.value.trim();
        if (desc.length > 255) {
            errs.description = 'Description must not exceed 255 characters.';
        }

        const days  = parseInt(form.duration_days.value, 10)  || 0;
        const hours = parseInt(form.duration_hours.value, 10) || 0;

        if (days < 0 || days > 365) {
            errs.duration_days = 'Days must be between 0 and 365.';
        }

        if (hours < 0 || hours > 8760) {
            errs.duration_hours = 'Hours must be between 0 and 8760.';
        }

        if (days === 0 && hours === 0) {
            errs.duration_hours = 'Provide at least one duration value (hours or days).';
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
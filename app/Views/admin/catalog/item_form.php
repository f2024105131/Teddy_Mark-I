<?php
/**
 * Admin Catalog — Item Form (Create + Edit)
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by:
 *   • Admin\ItemController::create()   → mode = 'create'
 *   • Admin\ItemController::edit()     → mode = 'edit'
 *
 * Expected variables:
 *   $mode         string  'create' | 'edit'
 *   $item         ?array  null on create; full row on edit
 *   $categories   array   list of categories (category_id, category_name, is_active)
 *   $preselected  int     pre-selected category ID (from ?category= or item's own)
 *
 * Posts to:
 *   • mode=create  →  POST /admin/catalog/items
 *   • mode=edit    →  POST /admin/catalog/items/{id}
 * ----------------------------------------------------------
 */

$currentPage = 'catalog-items';

$isEdit = ($mode ?? 'create') === 'edit';
$item   = $item   ?? null;
$title  = $isEdit ? 'Edit Item' : 'Add Catalog Item';

if ($isEdit && empty($item)) {
    echo '<div class="alert alert-danger">Item not found.</div>';
    return;
}

// Safe defaults
$categories  = $categories  ?? [];
$preselected = (int) ($preselected ?? 0);

$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// Resolve current values (works for both modes)
$currentId          = $isEdit ? (int) $item['item_id']        : 0;
$currentName        = $isEdit ? (string) $item['item_name']   : '';
$currentDescription = $isEdit ? (string) ($item['description'] ?? '') : '';
$currentCategory    = $isEdit ? (int) $item['category_id']    : $preselected;
$currentActive      = $isEdit ? (int) $item['is_active']      : 1;

// Form action + button labels
$formAction  = $isEdit
    ? '/admin/catalog/items/' . $currentId
    : '/admin/catalog/items';
$submitLabel = $isEdit ? 'Save Changes' : 'Create Item';
$cancelUrl   = '/admin/catalog/items' . ($currentCategory > 0 ? '?category=' . $currentCategory : '');

// Check if any active categories exist
$hasActiveCategories = false;
foreach ($categories as $c) {
    if ((int) ($c['is_active'] ?? 0) === 1) {
        $hasActiveCategories = true;
        break;
    }
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
                    Editing item <strong>#<?= $currentId ?></strong>
                    · Created <?= $e(date('M j, Y', strtotime($item['created_at']))) ?>
                <?php else: ?>
                    Add a new item to the laundry catalog
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $e($cancelUrl) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg me-1"></i>Cancel
        </a>
        <button type="submit" form="itemForm" class="btn btn-primary btn-sm" id="saveBtn">
            <i class="bi bi-check-lg me-1"></i><?= $e($submitLabel) ?>
        </button>
    </div>
</div>

<!-- ==================== CATALOG NAV ==================== -->
<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <a class="nav-link active" href="/admin/catalog/items">
            <i class="bi bi-basket me-1"></i>Items
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/admin/catalog/categories">
            <i class="bi bi-tags me-1"></i>Categories
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/admin/catalog/services">
            <i class="bi bi-tag me-1"></i>Services
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/admin/catalog/pricing">
            <i class="bi bi-grid-3x3-gap me-1"></i>Pricing Matrix
        </a>
    </li>
</ul>

<?php if (!$hasActiveCategories): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>No active categories available.</strong>
            <div class="small mt-1">
                You need at least one active category before you can create items.
                <a href="/admin/catalog/categories" class="alert-link">Manage categories →</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ==================== FORM ==================== -->
<form id="itemForm"
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
                        <i class="bi bi-info-circle me-2 text-primary"></i>Item Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Category -->
                        <div class="col-md-6">
                            <label for="category_id" class="form-label small fw-medium">
                                Category <span class="text-danger">*</span>
                            </label>
                            <select class="form-select"
                                    id="category_id"
                                    name="category_id"
                                    required
                                    <?= !$hasActiveCategories ? 'disabled' : '' ?>>
                                <option value="">— Select a category —</option>
                                <?php foreach ($categories as $cat):
                                    $catId    = (int) $cat['category_id'];
                                    $catName  = (string) $cat['category_name'];
                                    $catAct   = (int) ($cat['is_active'] ?? 0);
                                    $selected = ($catId === $currentCategory);
                                ?>
                                    <option value="<?= $catId ?>"
                                            <?= $selected ? 'selected' : '' ?>
                                            <?= !$catAct ? 'disabled' : '' ?>>
                                        <?= $e($catName) ?>
                                        <?= !$catAct ? ' (inactive)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-field="category_id"></div>
                            <div class="form-text small">
                                The category this item belongs to.
                            </div>
                        </div>

                        <!-- Item Name -->
                        <div class="col-md-6">
                            <label for="item_name" class="form-label small fw-medium">
                                Item Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="item_name"
                                   name="item_name"
                                   value="<?= $e($currentName) ?>"
                                   maxlength="100"
                                   placeholder="e.g., Shirt, Saree, Blanket"
                                   required
                                   autofocus>
                            <div class="invalid-feedback" data-field="item_name"></div>
                            <div class="form-text small">
                                2–100 characters. Must be unique within the selected category.
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
                                      placeholder="Short description (e.g., 'Formal or casual shirt')"><?= $e($currentDescription) ?></textarea>
                            <div class="invalid-feedback" data-field="description"></div>
                            <div class="form-text small">
                                <span id="descCounter"><?= strlen($currentDescription) ?></span>/255 characters
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
                                        Inactive items are hidden from the customer catalog.
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
                        <strong>Pricing for this item</strong>
                        <div class="text-muted mt-1">
                            Item prices are managed through the pricing matrix (per service).
                            <a href="/admin/catalog/pricing?item=<?= $currentId ?>" class="text-decoration-none">
                                Manage pricing for this item →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================== RIGHT: INFO PANEL ==================== -->
        <div class="col-lg-4">

            <!-- Tips -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-lightbulb me-2 text-warning"></i>Tips
                    </h6>
                </div>
                <div class="card-body pt-0 small text-muted">
                    <ul class="ps-3 mb-0">
                        <li>Use clear names customers recognize ("Shirt" not "Shirt-L")</li>
                        <li>The same item name can exist in different categories</li>
                        <li>After creating, add <strong>pricing rows</strong> so customers can order</li>
                        <li>Deactivate instead of deleting if you have existing orders</li>
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
                            <div class="info-label">Item ID</div>
                            <div class="info-value"><code>#<?= $currentId ?></code></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Created</div>
                            <div class="info-value small">
                                <?= $e(date('M j, Y', strtotime($item['created_at']))) ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value small">
                                <?php if (!empty($item['updated_at'])): ?>
                                    <?= $e(date('M j, Y · g:i A', strtotime($item['updated_at']))) ?>
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
    const form = document.getElementById('itemForm');
    const saveBtn = document.getElementById('saveBtn');

    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    const itemId = <?= $currentId ?>;

    // ---------- Description character counter ----------
    const descInput = document.getElementById('description');
    const descCounter = document.getElementById('descCounter');
    if (descInput && descCounter) {
        descInput.addEventListener('input', () => {
            descCounter.textContent = descInput.value.length;
        });
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
                showToast(json.message || 'Item saved successfully.', 'success');
                setTimeout(() => {
                    window.location.href = json.redirect || '/admin/catalog/items';
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

    // ---------- Client validation ----------
    function clientValidate() {
        const errs = {};

        const categoryId = form.category_id?.value;
        if (!categoryId || parseInt(categoryId, 10) <= 0) {
            errs.category_id = 'Please select a category.';
        }

        const name = form.item_name.value.trim();
        if (name.length < 2) {
            errs.item_name = 'Item name must be at least 2 characters.';
        } else if (name.length > 100) {
            errs.item_name = 'Item name must not exceed 100 characters.';
        }

        const desc = form.description.value.trim();
        if (desc.length > 255) {
            errs.description = 'Description must not exceed 255 characters.';
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
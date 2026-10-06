<?php
/**
 * Admin Catalog — Categories List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ItemController::categories()
 *
 * Expected variables:
 *   $categories  array   category rows with counts
 *                        each: category_id, category_name, description,
 *                              is_active, created_at, updated_at,
 *                              items_count, active_items_count, pricing_count
 *   $search      string  current search query
 *   $status      string  'active' | 'inactive' | ''
 *
 * POST endpoints:
 *   POST /admin/catalog/categories            → storeCategory
 *   POST /admin/catalog/categories/{id}       → updateCategory
 *   POST /admin/catalog/categories/{id}/toggle → toggleCategory
 *   POST /admin/catalog/categories/{id}/delete → deleteCategory
 * ----------------------------------------------------------
 */

$currentPage = 'catalog-categories';
$title       = 'Catalog Categories';

// Safe defaults
$categories = $categories ?? [];
$search     = $search     ?? '';
$status     = $status     ?? '';

$e       = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt  = fn($n) => number_format((int) $n);
$fmtDate = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Summary computations (server already sends these per row)
$totalCats   = count($categories);
$activeCats  = 0;
$totalItems  = 0;
$totalActive = 0;
$totalPrices = 0;
$emptyCats   = 0;

foreach ($categories as $c) {
    if ((int) ($c['is_active'] ?? 0) === 1) {
        $activeCats++;
    }
    $ic = (int) ($c['items_count'] ?? 0);
    $totalItems  += $ic;
    $totalActive += (int) ($c['active_items_count'] ?? 0);
    $totalPrices += (int) ($c['pricing_count'] ?? 0);
    if ($ic === 0) {
        $emptyCats++;
    }
}

// Active filters
$activeFilters = (int) ($search !== '') + (int) ($status !== '');

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Catalog Categories</h1>
        <p class="text-muted mb-0 small">
            Group laundry items into categories ·
            <?= $fmtInt($totalCats) ?> categor<?= $totalCats === 1 ? 'y' : 'ies' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/catalog/items" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-basket me-1"></i>View Items
        </a>
        <button type="button"
                class="btn btn-primary btn-sm"
                onclick="openCategoryModal('create')">
            <i class="bi bi-plus-lg me-1"></i>Add Category
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
        <a class="nav-link active" href="/admin/catalog/categories">
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

<!-- ==================== SUMMARY CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-tags"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Categories</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalCats) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt($activeCats) ?> active
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
                        <i class="bi bi-basket"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Items</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalItems) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt($totalActive) ?> active
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
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pricing Rows</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalPrices) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            across all categories
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
                    <div class="kpi-icon bg-danger-subtle text-danger">
                        <i class="bi bi-folder-x"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Empty</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($emptyCats) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            no items yet
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FILTERS ==================== -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="/admin/catalog/categories" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text"
                           class="form-control"
                           name="search"
                           value="<?= $e($search) ?>"
                           placeholder="Category name…">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="">All statuses</option>
                    <option value="active"   <?= $status==='active'  ?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($activeFilters > 0): ?>
                    <a href="/admin/catalog/categories" class="btn btn-outline-secondary" title="Clear filters">
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
                        Status: <?= $e(ucfirst($status)) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== CATEGORIES TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Category</th>
                    <th class="small text-muted fw-semibold">Description</th>
                    <th class="small text-muted fw-semibold text-center">Items</th>
                    <th class="small text-muted fw-semibold text-center">Pricing Rows</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold">Updated</th>
                    <th class="small text-muted fw-semibold text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-tags fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No categories found</div>
                                <div class="small">
                                    <?php if ($activeFilters > 0): ?>
                                        Try adjusting your filters ·
                                        <a href="/admin/catalog/categories" class="text-decoration-none">Clear all</a>
                                    <?php else: ?>
                                        <a href="#" onclick="openCategoryModal('create'); return false;"
                                           class="text-decoration-none">
                                            Create your first category →
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($categories as $cat):
                    $catId       = (int) $cat['category_id'];
                    $catName     = (string) $cat['category_name'];
                    $catDesc     = (string) ($cat['description'] ?? '');
                    $catActive   = (int) ($cat['is_active'] ?? 0);
                    $itemsCount  = (int) ($cat['items_count'] ?? 0);
                    $activeItems = (int) ($cat['active_items_count'] ?? 0);
                    $pricingCnt  = (int) ($cat['pricing_count'] ?? 0);
                    $updatedAt   = $cat['updated_at'] ?? $cat['created_at'] ?? null;
                    $isEmpty     = ($itemsCount === 0);
                ?>
                    <tr class="<?= $isEmpty ? 'table-light' : '' ?>">
                        <!-- Category -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="cat-icon bg-primary-subtle text-primary">
                                    <i class="bi bi-folder2<?= $isEmpty ? '-open' : '' ?>"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-medium">
                                        <a href="/admin/catalog/items?category=<?= $catId ?>"
                                           class="text-decoration-none">
                                            <?= $e($catName) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        #<?= $catId ?>
                                        <?php if ($isEmpty): ?>
                                            · <span class="text-danger">No items yet</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Description -->
                        <td>
                            <?php if ($catDesc !== ''): ?>
                                <div class="small text-muted text-truncate" style="max-width: 320px;">
                                    <?= $e($catDesc) ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small fst-italic">No description</span>
                            <?php endif; ?>
                        </td>

                        <!-- Items count -->
                        <td class="text-center">
                            <?php if ($itemsCount > 0): ?>
                                <a href="/admin/catalog/items?category=<?= $catId ?>"
                                   class="badge bg-primary-subtle text-primary text-decoration-none">
                                    <?= $fmtInt($itemsCount) ?>
                                    <span class="text-muted">(<?= $fmtInt($activeItems) ?> active)</span>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- Pricing rows -->
                        <td class="text-center">
                            <?php if ($pricingCnt > 0): ?>
                                <span class="badge bg-warning-subtle text-warning">
                                    <?= $fmtInt($pricingCnt) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td class="text-center">
                            <?php if ($catActive): ?>
                                <span class="badge bg-success-subtle text-success">
                                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i> Active
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i> Inactive
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Updated -->
                        <td class="small text-muted">
                            <?= $fmtDate($updatedAt) ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="/admin/catalog/items?category=<?= $catId ?>"
                                   class="btn btn-outline-secondary" title="View items">
                                    <i class="bi bi-basket"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        title="Edit category"
                                        onclick="openCategoryModal('edit', <?= $catId ?>, <?= json_encode($catName, $jsonFlags) ?>, <?= json_encode($catDesc, $jsonFlags) ?>, <?= $catActive ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-outline-secondary dropdown-toggle-split"
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button type="button"
                                                class="dropdown-item"
                                                onclick="categoryAction('toggle', <?= $catId ?>, <?= json_encode($catName, $jsonFlags) ?>, <?= $catActive ?>)">
                                            <?php if ($catActive): ?>
                                                <i class="bi bi-pause-circle me-2"></i>Deactivate
                                            <?php else: ?>
                                                <i class="bi bi-play-circle me-2"></i>Activate
                                            <?php endif; ?>
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item text-danger"
                                                onclick="categoryAction('delete', <?= $catId ?>, <?= json_encode($catName, $jsonFlags) ?>)"
                                                <?= ($itemsCount > 0) ? 'disabled title="Category has items — move or delete them first"' : '' ?>>
                                            <i class="bi bi-trash me-2"></i>Delete
                                            <?php if ($itemsCount > 0): ?>
                                                <small class="text-muted">(<?= $fmtInt($itemsCount) ?> items)</small>
                                            <?php endif; ?>
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
</div>

<!-- ==================== CATEGORY FORM MODAL ==================== -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="categoryForm" method="post" novalidate>
                <input type="hidden" name="_csrf" value="<?= $e($_SESSION['_csrf'] ?? '') ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- Category Name -->
                    <div class="mb-3">
                        <label for="cat_name" class="form-label small fw-medium">
                            Category Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="cat_name"
                               name="category_name"
                               maxlength="50"
                               required>
                        <div class="invalid-feedback" data-field="category_name"></div>
                        <div class="form-text small">
                            2–50 characters. Must be unique (case-insensitive).
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="cat_desc" class="form-label small fw-medium">
                            Description
                            <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <textarea class="form-control"
                                  id="cat_desc"
                                  name="description"
                                  rows="2"
                                  maxlength="255"
                                  placeholder="Short description of what belongs in this category"></textarea>
                        <div class="invalid-feedback" data-field="description"></div>
                    </div>

                    <!-- Active -->
                    <div class="form-check form-switch">
                        <input class="form-check-input"
                               type="checkbox"
                               id="cat_active"
                               name="is_active"
                               value="1"
                               checked>
                        <label class="form-check-label small" for="cat_active">
                            Active
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="categorySaveBtn">
                        Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== ACTION CONFIRM MODAL ==================== -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="actionModalText" class="mb-0"></p>
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
    .kpi-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .cat-icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.05rem;
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
    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const categoryModal    = new bootstrap.Modal(document.getElementById('categoryModal'));
    const categoryForm     = document.getElementById('categoryForm');
    const categoryModalTitle = document.getElementById('categoryModalTitle');
    const categorySaveBtn  = document.getElementById('categorySaveBtn');
    const catNameInput     = document.getElementById('cat_name');
    const catDescInput     = document.getElementById('cat_desc');
    const catActiveInput   = document.getElementById('cat_active');

    const actionModal  = new bootstrap.Modal(document.getElementById('actionModal'));
    const actionTitle  = document.getElementById('actionModalTitle');
    const actionText   = document.getElementById('actionModalText');
    const confirmBtn   = document.getElementById('confirmBtn');

    let pendingAction = null;

    // ==================== CATEGORY FORM MODAL ====================
    window.openCategoryModal = function (mode, id, name, description, isActive) {
        clearCategoryErrors();

        if (mode === 'create') {
            categoryModalTitle.textContent = 'Add Category';
            categoryForm.action = '/admin/catalog/categories';
            catNameInput.value = '';
            catDescInput.value = '';
            catActiveInput.checked = true;
        } else {
            categoryModalTitle.textContent = 'Edit Category';
            categoryForm.action = '/admin/catalog/categories/' + id;
            catNameInput.value = name || '';
            catDescInput.value = description || '';
            catActiveInput.checked = (isActive === 1 || isActive === true);
        }

        categoryModal.show();
        setTimeout(() => catNameInput.focus(), 300);
    };

    categoryForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        clearCategoryErrors();

        // Client validation
        const name = catNameInput.value.trim();
        const errs = {};

        if (name.length < 2) {
            errs.category_name = 'Category name must be at least 2 characters.';
        } else if (name.length > 50) {
            errs.category_name = 'Category name must not exceed 50 characters.';
        }

        if (catDescInput.value.length > 255) {
            errs.description = 'Description must not exceed 255 characters.';
        }

        if (Object.keys(errs).length) {
            showCategoryErrors(errs);
            return;
        }

        categorySaveBtn.disabled = true;
        const originalText = categorySaveBtn.innerHTML;
        categorySaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            const formData = new FormData(categoryForm);

            const res = await fetch(categoryForm.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Category saved.', 'success');
                categoryModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                if (json.errors && typeof json.errors === 'object') {
                    showCategoryErrors(json.errors);
                }
                showToast(json.message || 'Please fix the highlighted fields.', 'danger');
                categorySaveBtn.disabled = false;
                categorySaveBtn.innerHTML = originalText;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            categorySaveBtn.disabled = false;
            categorySaveBtn.innerHTML = originalText;
        }
    });

    function clearCategoryErrors() {
        categoryForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        categoryForm.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
    }

    function showCategoryErrors(errs) {
        for (const [field, message] of Object.entries(errs)) {
            const input = categoryForm.querySelector('[name="' + field + '"]');
            const feedback = categoryForm.querySelector('.invalid-feedback[data-field="' + field + '"]');

            if (input) input.classList.add('is-invalid');
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }
    }

    // ==================== ACTION MODAL (toggle / delete) ====================
    window.categoryAction = function (action, id, name, isActive) {
        pendingAction = { type: action, id: id };

        if (action === 'toggle') {
            actionTitle.textContent = isActive ? 'Deactivate Category' : 'Activate Category';
            actionText.innerHTML = isActive
                ? `Deactivate <strong>${escapeHtml(name)}</strong>? Items in this category will also be hidden from customers.`
                : `Activate <strong>${escapeHtml(name)}</strong>? Items in this category will become visible again.`;
            confirmBtn.textContent = isActive ? 'Deactivate' : 'Activate';
            confirmBtn.className = 'btn ' + (isActive ? 'btn-warning' : 'btn-success');
        } else if (action === 'delete') {
            actionTitle.textContent = 'Delete Category';
            actionText.innerHTML = `<strong class="text-danger">Permanently delete "${escapeHtml(name)}"?</strong><br>
                <span class="small text-muted">This cannot be undone. Only empty categories can be deleted.</span>`;
            confirmBtn.textContent = 'Delete';
            confirmBtn.className = 'btn btn-danger';
        }

        actionModal.show();
    };

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { type, id } = pendingAction;
        const url = '/admin/catalog/categories/' + id + (type === 'toggle' ? '/toggle' : '/delete');

        confirmBtn.disabled = true;
        const originalHTML = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const body = new URLSearchParams({ _csrf: csrf });

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
                actionModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Action failed.', 'danger');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalHTML;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalHTML;
        }
    });

    document.getElementById('actionModal').addEventListener('hidden.bs.modal', () => {
        pendingAction = null;
        confirmBtn.disabled = false;
    });

    document.getElementById('categoryModal').addEventListener('hidden.bs.modal', () => {
        clearCategoryErrors();
        categorySaveBtn.disabled = false;
        categorySaveBtn.innerHTML = 'Save Category';
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
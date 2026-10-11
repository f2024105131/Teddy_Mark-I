<?php
/**
 * Admin Complaints — Category Management
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ComplaintController::categories()
 *
 * Expected variables:
 *   $categories  array   complaint categories with counts
 *                        each: category_id, category_name, description,
 *                              is_active, created_at, updated_at,
 *                              complaints_count, active_count
 *
 * POST endpoints:
 *   • POST /admin/complaints/categories                  → storeCategory
 *   • POST /admin/complaints/categories/{id}             → updateCategory
 *   • POST /admin/complaints/categories/{id}/toggle      → toggleCategory
 *   • POST /admin/complaints/categories/{id}/delete      → deleteCategory
 * ----------------------------------------------------------
 */

$currentPage = 'complaints';
$title       = 'Complaint Categories';

// Safe defaults
$categories = $categories ?? [];

$e       = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt  = fn($n) => number_format((int) $n);
$fmtDate = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Summary computations
$totalCats   = count($categories);
$activeCats  = 0;
$totalComplaints = 0;
$totalActive = 0;
$emptyCats   = 0;

foreach ($categories as $c) {
    if ((int) ($c['is_active'] ?? 0) === 1) {
        $activeCats++;
    }
    $cc = (int) ($c['complaints_count'] ?? 0);
    $totalComplaints += $cc;
    $totalActive += (int) ($c['active_count'] ?? 0);
    if ($cc === 0) {
        $emptyCats++;
    }
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/complaints" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1">Complaint Categories</h1>
            <p class="text-muted mb-0 small">
                Group and organize complaint types ·
                <?= $fmtInt($totalCats) ?> categor<?= $totalCats === 1 ? 'y' : 'ies' ?>
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/complaints" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i>All Complaints
        </a>
        <button type="button"
                class="btn btn-primary btn-sm"
                onclick="openCategoryModal('create')">
            <i class="bi bi-plus-lg me-1"></i>Add Category
        </button>
    </div>
</div>

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
                    <div class="kpi-icon bg-info-subtle text-info">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total Complaints</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalComplaints) ?></div>
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
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Active</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalActive) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            in progress
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
                        <div class="text-muted small text-uppercase fw-semibold">Unused</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($emptyCats) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            no complaints yet
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                    <th class="small text-muted fw-semibold text-center">Complaints</th>
                    <th class="small text-muted fw-semibold text-center">Active</th>
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
                                <div class="fw-medium">No categories yet</div>
                                <div class="small">
                                    <a href="#" onclick="openCategoryModal('create'); return false;"
                                       class="text-decoration-none">
                                        Create your first complaint category →
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($categories as $cat):
                    $catId       = (int) $cat['category_id'];
                    $catName     = (string) $cat['category_name'];
                    $catDesc     = (string) ($cat['description'] ?? '');
                    $catActive   = (int) ($cat['is_active'] ?? 0);
                    $compCount   = (int) ($cat['complaints_count'] ?? 0);
                    $activeCount = (int) ($cat['active_count'] ?? 0);
                    $updatedAt   = $cat['updated_at'] ?? $cat['created_at'] ?? null;
                    $isEmpty     = ($compCount === 0);
                    $hasActive   = ($activeCount > 0);
                ?>
                    <tr class="<?= $isEmpty ? 'table-light' : '' ?>">
                        <!-- Category -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="cat-icon bg-primary-subtle text-primary">
                                    <i class="bi bi-tag<?= $isEmpty ? '' : '-fill' ?>"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-medium">
                                        <a href="/admin/complaints?category=<?= $catId ?>"
                                           class="text-decoration-none">
                                            <?= $e($catName) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        #<?= $catId ?>
                                        <?php if ($isEmpty): ?>
                                            · <span class="text-muted">unused</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Description -->
                        <td>
                            <?php if ($catDesc !== ''): ?>
                                <div class="small text-muted text-truncate" style="max-width: 320px;"
                                     title="<?= $e($catDesc) ?>">
                                    <?= $e($catDesc) ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small fst-italic">No description</span>
                            <?php endif; ?>
                        </td>

                        <!-- Complaints count -->
                        <td class="text-center">
                            <?php if ($compCount > 0): ?>
                                <a href="/admin/complaints?category=<?= $catId ?>"
                                   class="badge bg-primary-subtle text-primary text-decoration-none">
                                    <?= $fmtInt($compCount) ?>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- Active complaints -->
                        <td class="text-center">
                            <?php if ($hasActive): ?>
                                <a href="/admin/complaints?category=<?= $catId ?>&status=open"
                                   class="badge bg-warning-subtle text-warning text-decoration-none"
                                   title="Active complaints">
                                    <?= $fmtInt($activeCount) ?>
                                </a>
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
                            <?= $e($fmtDate($updatedAt)) ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="/admin/complaints?category=<?= $catId ?>"
                                   class="btn btn-outline-secondary"
                                   title="View complaints">
                                    <i class="bi bi-exclamation-circle"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        title="Edit category"
                                        onclick="openCategoryModal(
                                            'edit',
                                            <?= $catId ?>,
                                            <?= json_encode($catName, $jsonFlags) ?>,
                                            <?= json_encode($catDesc, $jsonFlags) ?>,
                                            <?= $catActive ?>
                                        )">
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
                                                onclick="openCategoryAction('toggle', <?= $catId ?>, <?= json_encode($catName, $jsonFlags) ?>, <?= $catActive ?>, <?= $compCount ?>)">
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
                                                onclick="openCategoryAction('delete', <?= $catId ?>, <?= json_encode($catName, $jsonFlags) ?>, <?= $catActive ?>, <?= $compCount ?>)"
                                                <?= ($compCount > 0) ? 'disabled title="Category has complaints — cannot delete"' : '' ?>>
                                            <i class="bi bi-trash me-2"></i>Delete
                                            <?php if ($compCount > 0): ?>
                                                <small class="text-muted">(in use)</small>
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

<!-- ==================== INFO BANNER ==================== -->
<div class="alert alert-light border mt-3 mb-0 d-flex align-items-start gap-2">
    <i class="bi bi-info-circle text-primary mt-1"></i>
    <div class="small">
        <strong>About complaint categories</strong>
        <ul class="mb-0 mt-1">
            <li>Categories help group similar complaints for reporting and filtering.</li>
            <li>Customers select a category when filing a complaint (optional).</li>
            <li>Categories with complaints cannot be deleted — deactivate them instead to hide from the customer form.</li>
        </ul>
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
                               placeholder="e.g., Missing Item, Late Delivery"
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
                        <div class="form-text small">
                            <span id="descCounter">0</span>/255 characters
                        </div>
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
                            <span class="text-muted d-block" style="font-size:.72rem;">
                                Inactive categories are hidden from the customer complaint form
                            </span>
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
    .table > :not(caption) > * > * {
        padding: .85rem .75rem;
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

    // ==================== CATEGORY FORM MODAL ====================
    const categoryModal      = new bootstrap.Modal(document.getElementById('categoryModal'));
    const categoryForm       = document.getElementById('categoryForm');
    const categoryModalTitle = document.getElementById('categoryModalTitle');
    const categorySaveBtn    = document.getElementById('categorySaveBtn');
    const catNameInput       = document.getElementById('cat_name');
    const catDescInput       = document.getElementById('cat_desc');
    const catActiveInput     = document.getElementById('cat_active');
    const descCounter        = document.getElementById('descCounter');

    // Live description counter
    catDescInput?.addEventListener('input', () => {
        descCounter.textContent = catDescInput.value.length;
    });

    window.openCategoryModal = function (mode, id, name, description, isActive) {
        clearCategoryErrors();

        if (mode === 'create') {
            categoryModalTitle.textContent = 'Add Category';
            categoryForm.action = '/admin/complaints/categories';
            catNameInput.value = '';
            catDescInput.value = '';
            catActiveInput.checked = true;
            descCounter.textContent = '0';
        } else {
            categoryModalTitle.textContent = 'Edit Category';
            categoryForm.action = '/admin/complaints/categories/' + id;
            catNameInput.value = name || '';
            catDescInput.value = description || '';
            catActiveInput.checked = (isActive === 1 || isActive === true);
            descCounter.textContent = (description || '').length;
        }

        categoryModal.show();
        setTimeout(() => catNameInput.focus(), 300);
    };

    categoryForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        clearCategoryErrors();

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
    const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
    const actionTitle = document.getElementById('actionModalTitle');
    const actionText  = document.getElementById('actionModalText');
    const confirmBtn  = document.getElementById('confirmBtn');
    let pendingAction = null;

    window.openCategoryAction = function (action, id, name, isActive, complaintsCount) {
        pendingAction = { type: action, id: id };

        if (action === 'toggle') {
            actionTitle.textContent = isActive ? 'Deactivate Category' : 'Activate Category';
            if (isActive) {
                let extra = '';
                if (complaintsCount > 0) {
                    extra = `<br><span class="small text-muted">
                        Existing complaints in this category are unaffected.
                    </span>`;
                }
                actionText.innerHTML = `Deactivate <strong>${escapeHtml(name)}</strong>?
                    It will no longer appear in the customer complaint form.${extra}`;
                confirmBtn.textContent = 'Deactivate';
                confirmBtn.className = 'btn btn-warning';
            } else {
                actionText.innerHTML = `Activate <strong>${escapeHtml(name)}</strong>?
                    Customers will be able to select it again when filing complaints.`;
                confirmBtn.textContent = 'Activate';
                confirmBtn.className = 'btn btn-success';
            }
        } else if (action === 'delete') {
            actionTitle.textContent = 'Delete Category';
            actionText.innerHTML = `<strong class="text-danger">Permanently delete "${escapeHtml(name)}"?</strong><br>
                <span class="small text-muted">This cannot be undone.</span>`;
            confirmBtn.textContent = 'Delete';
            confirmBtn.className = 'btn btn-danger';
        }

        actionModal.show();
    };

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { type, id } = pendingAction;
        const url = '/admin/complaints/categories/' + id + (type === 'toggle' ? '/toggle' : '/delete');

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
        new bootstrap.Toast(toast, { delay: 3500 }).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }));
    }
})();
</script>
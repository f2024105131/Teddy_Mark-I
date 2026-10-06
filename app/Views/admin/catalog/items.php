<?php
/**
 * Admin Catalog — Items List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ItemController::index()
 *
 * Expected variables:
 *   $items       array   paginated item rows
 *                        each: item_id, item_name, description, is_active,
 *                              created_at, updated_at, category_id, category_name,
 *                              pricing_count, orders_count
 *   $categories  array   all categories with items_count, active_items_count
 *   $summary     array   ['categories_total','categories_active','items_total',
 *                         'items_active','pricing_total']
 *   $search      string  current search query
 *   $categoryId  int     current category filter (0 = all)
 *   $status      string  'active' | 'inactive' | ''
 *   $page        int
 *   $perPage     int
 *   $totalRows   int
 *   $totalPages  int
 * ----------------------------------------------------------
 */

$currentPage = 'catalog-items';
$title       = 'Catalog Items';

// Safe defaults
$items       = $items       ?? [];
$categories  = $categories  ?? [];
$summary     = $summary     ?? ['categories_total'=>0,'categories_active'=>0,'items_total'=>0,'items_active'=>0,'pricing_total'=>0];
$search      = $search      ?? '';
$categoryId  = (int) ($categoryId ?? 0);
$status      = $status      ?? '';
$page        = (int) ($page       ?? 1);
$perPage     = (int) ($perPage    ?? 25);
$totalRows   = (int) ($totalRows  ?? 0);
$totalPages  = (int) ($totalPages ?? 1);

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Active filters count
$activeFilters = (int) ($search !== '') + (int) ($categoryId > 0) + (int) ($status !== '');

// Build querystring (preserve filters)
$qs = function (int $targetPage) use ($search, $categoryId, $status): string {
    $params = array_filter([
        'search'   => $search !== '' ? $search : null,
        'category' => $categoryId > 0 ? $categoryId : null,
        'status'   => $status !== '' ? $status : null,
        'page'     => $targetPage,
    ], fn($v) => $v !== null);

    return '?' . http_build_query($params);
};
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Catalog Items</h1>
        <p class="text-muted mb-0 small">
            Manage laundry items and their categories ·
            <?= $fmtInt($totalRows) ?> item(s)
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/catalog/categories" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tags me-1"></i>Categories
        </a>
        <a href="/admin/catalog/items/export<?= $activeFilters ? '?' . http_build_query(array_filter([
            'search'   => $search ?: null,
            'category' => $categoryId ?: null,
            'status'   => $status ?: null,
        ])) : '' ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a href="/admin/catalog/items/create<?= $categoryId > 0 ? '?category=' . $categoryId : '' ?>"
           class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Item
        </a>
    </div>
</div>

<!-- ==================== CATALOG NAV TABS ==================== -->
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

<!-- ==================== SUMMARY CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-basket"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total Items</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['items_total']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt($summary['items_active']) ?> active
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
                        <i class="bi bi-tags"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Categories</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['categories_total']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt($summary['categories_active']) ?> active
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
                        <i class="bi bi-tag"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pricing Rows</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['pricing_total']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            item × service
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
                        <i class="bi bi-filter"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Matching</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalRows) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            current filter
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3">

    <!-- ==================== LEFT: CATEGORY SIDEBAR ==================== -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-tags me-2 text-primary"></i>Categories
                </h6>
                <a href="/admin/catalog/categories"
                   class="small text-decoration-none">
                    Manage
                </a>
            </div>
            <div class="list-group list-group-flush">
                <!-- "All items" -->
                <a href="/admin/catalog/items<?= $status !== '' ? '?status=' . $e($status) : '' ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $categoryId === 0 ? 'active' : '' ?>">
                    <span class="small fw-medium">
                        <i class="bi bi-collection me-2"></i>All Items
                    </span>
                    <span class="badge <?= $categoryId === 0 ? 'bg-white text-primary' : 'bg-primary' ?>">
                        <?= $fmtInt($summary['items_total']) ?>
                    </span>
                </a>

                <!-- Per-category -->
                <?php if (empty($categories)): ?>
                    <div class="list-group-item text-muted small text-center py-3">
                        No categories yet.
                        <a href="/admin/catalog/categories" class="text-decoration-none">Create one →</a>
                    </div>
                <?php else: foreach ($categories as $cat):
                    $catId    = (int) $cat['category_id'];
                    $catName  = (string) $cat['category_name'];
                    $catItems = (int) ($cat['items_count'] ?? 0);
                    $catActive= (int) ($cat['active_items_count'] ?? 0);
                    $isCatAct = (int) ($cat['is_active'] ?? 0);
                    $isSelected = ($catId === $categoryId);

                    // Build URL preserving status + search
                    $catParams = array_filter([
                        'category' => $catId,
                        'search'   => $search ?: null,
                        'status'   => $status ?: null,
                    ], fn($v) => $v !== null);
                    $catUrl = '/admin/catalog/items?' . http_build_query($catParams);
                ?>
                    <a href="<?= $e($catUrl) ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $isSelected ? 'active' : '' ?> <?= $isCatAct ? '' : 'text-muted' ?>">
                        <span class="small text-truncate">
                            <?php if (!$isCatAct): ?>
                                <i class="bi bi-slash-circle me-1"></i>
                            <?php else: ?>
                                <i class="bi bi-folder2 me-1"></i>
                            <?php endif; ?>
                            <?= $e($catName) ?>
                        </span>
                        <span class="badge <?= $isSelected ? 'bg-white text-primary' : ($catActive > 0 ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary') ?>">
                            <?= $fmtInt($catItems) ?>
                        </span>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== RIGHT: FILTERS + ITEMS TABLE ==================== -->
    <div class="col-lg-9">

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="get" action="/admin/catalog/items" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text"
                                   class="form-control"
                                   name="search"
                                   value="<?= $e($search) ?>"
                                   placeholder="Item name or category…">
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
                    <input type="hidden" name="category" value="<?= $categoryId ?: '' ?>">
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <?php if ($activeFilters > 0): ?>
                            <a href="/admin/catalog/items" class="btn btn-outline-secondary" title="Clear filters">
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
                        <?php if ($categoryId > 0):
                            $catName = '';
                            foreach ($categories as $c) {
                                if ((int) $c['category_id'] === $categoryId) {
                                    $catName = $c['category_name'];
                                    break;
                                }
                            }
                        ?>
                            <span class="badge bg-light text-dark border">
                                Category: <?= $e($catName ?: '#' . $categoryId) ?>
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

        <!-- Items table -->
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small text-muted fw-semibold">Item</th>
                            <th class="small text-muted fw-semibold">Category</th>
                            <th class="small text-muted fw-semibold text-center">Pricing Rows</th>
                            <th class="small text-muted fw-semibold text-center">Orders</th>
                            <th class="small text-muted fw-semibold text-center">Status</th>
                            <th class="small text-muted fw-semibold">Updated</th>
                            <th class="small text-muted fw-semibold text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-basket fs-1 d-block mb-2 opacity-50"></i>
                                        <div class="fw-medium">No items found</div>
                                        <div class="small">
                                            <?php if ($activeFilters > 0): ?>
                                                Try adjusting your filters ·
                                                <a href="/admin/catalog/items" class="text-decoration-none">Clear all</a>
                                            <?php else: ?>
                                                <a href="/admin/catalog/items/create" class="text-decoration-none">
                                                    Create your first item →
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($items as $item):
                            $itemId     = (int) $item['item_id'];
                            $itemName   = (string) $item['item_name'];
                            $isActive   = (int) ($item['is_active'] ?? 0);
                            $priceCount = (int) ($item['pricing_count'] ?? 0);
                            $orderCount = (int) ($item['orders_count'] ?? 0);
                            $catName    = (string) ($item['category_name'] ?? '');
                            $catId      = (int) ($item['category_id'] ?? 0);
                        ?>
                            <tr>
                                <!-- Item -->
                                <td>
                                    <div class="fw-medium">
                                        <?= $e($itemName) ?>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <div class="text-muted small text-truncate" style="max-width: 320px;">
                                            <?= $e($item['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        #<?= $itemId ?>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td>
                                    <a href="/admin/catalog/items?category=<?= $catId ?>"
                                       class="badge bg-primary-subtle text-primary text-decoration-none">
                                        <?= $e($catName) ?>
                                    </a>
                                </td>

                                <!-- Pricing count -->
                                <td class="text-center">
                                    <?php if ($priceCount > 0): ?>
                                        <a href="/admin/catalog/pricing?item=<?= $itemId ?>"
                                           class="badge bg-warning-subtle text-warning text-decoration-none">
                                            <?= $fmtInt($priceCount) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger"
                                              title="No pricing rows — customers can't order this item">
                                            <i class="bi bi-exclamation-triangle me-1"></i>0
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Orders count -->
                                <td class="text-center">
                                    <?php if ($orderCount > 0): ?>
                                        <span class="badge bg-info-subtle text-info"><?= $fmtInt($orderCount) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="text-center">
                                    <?php if ($isActive): ?>
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
                                    <?= $fmtDate($item['updated_at'] ?? $item['created_at'] ?? null) ?>
                                </td>

                                <!-- Actions -->
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/admin/catalog/items/<?= $itemId ?>/edit"
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button"
                                                class="btn btn-outline-secondary dropdown-toggle-split"
                                                data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/catalog/pricing?item=<?= $itemId ?>">
                                                    <i class="bi bi-cash-coin me-2"></i>Manage Pricing
                                                </a>
                                            </li>
                                            <li>
                                                <button type="button"
                                                        class="dropdown-item"
                                                        onclick="itemAction('toggle', <?= $itemId ?>, <?= json_encode($itemName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= $isActive ?>)">
                                                    <?php if ($isActive): ?>
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
                                                        onclick="itemAction('delete', <?= $itemId ?>, <?= json_encode($itemName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)"
                                                        <?= ($orderCount > 0 || $priceCount > 0) ? 'disabled title="Cannot delete: in use"' : '' ?>>
                                                    <i class="bi bi-trash me-2"></i>Delete
                                                    <?php if ($orderCount > 0 || $priceCount > 0): ?>
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
                                <a class="page-link" href="<?= $page <= 1 ? '#' : $e($qs($page - 1)) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            <?php
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
    </div>
</div>

<!-- ==================== ACTION MODAL ==================== -->
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
        padding: .8rem .75rem;
        vertical-align: middle;
    }
    .list-group-item.active {
        background: #0ea5e9;
        border-color: #0ea5e9;
    }
    .list-group-item.active .badge {
        background: #fff !important;
        color: #0ea5e9 !important;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const modal      = new bootstrap.Modal(document.getElementById('actionModal'));
    const modalTitle = document.getElementById('actionModalTitle');
    const modalText  = document.getElementById('actionModalText');
    const confirmBtn = document.getElementById('confirmBtn');

    let pendingAction = null;

    window.itemAction = function (action, id, name, isActive) {
        pendingAction = { type: action, id: id };

        if (action === 'toggle') {
            modalTitle.textContent = isActive ? 'Deactivate Item' : 'Activate Item';
            modalText.innerHTML    = isActive
                ? `Deactivate <strong>${escapeHtml(name)}</strong>? It will no longer appear in the customer catalog.`
                : `Activate <strong>${escapeHtml(name)}</strong>? It will be available for orders (if pricing exists).`;
            confirmBtn.textContent = isActive ? 'Deactivate' : 'Activate';
            confirmBtn.className   = 'btn ' + (isActive ? 'btn-warning' : 'btn-success');
        } else if (action === 'delete') {
            modalTitle.textContent = 'Delete Item';
            modalText.innerHTML    = `<strong class="text-danger">Permanently delete "${escapeHtml(name)}"?</strong><br>
                <span class="small text-muted">This cannot be undone. Items used in orders or pricing cannot be deleted.</span>`;
            confirmBtn.textContent = 'Delete';
            confirmBtn.className   = 'btn btn-danger';
        }

        modal.show();
    };

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { type, id } = pendingAction;
        const url = type === 'toggle'
            ? '/admin/catalog/items/' + id + '/toggle'
            : '/admin/catalog/items/' + id + '/delete';

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
                modal.hide();
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

    // Toast
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
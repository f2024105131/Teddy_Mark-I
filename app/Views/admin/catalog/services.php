<?php
/**
 * Admin Catalog — Services List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ServiceController::index()
 *
 * Expected variables:
 *   $services    array   paginated service rows
 *                        each: service_id, service_name, description,
 *                              service_type, duration_hours, duration_days,
 *                              is_active, created_at, updated_at,
 *                              pricing_count, orders_count
 *   $summary     array   ['total','active','inactive','pricing_rows',
 *                         'covered_items','avg_price']
 *   $search      string
 *   $type        string  service_type filter
 *   $status      string  'active' | 'inactive' | ''
 *   $page        int
 *   $perPage     int
 *   $totalRows   int
 *   $totalPages  int
 *   $types       array   ['wash_fold', 'dry_cleaning', ...]
 *   $typeLabels  array   ['wash_fold' => 'Wash & Fold', ...]
 * ----------------------------------------------------------
 */

$currentPage = 'catalog-services';
$title       = 'Catalog Services';

// Safe defaults
$services    = $services    ?? [];
$summary     = $summary     ?? ['total'=>0,'active'=>0,'inactive'=>0,'pricing_rows'=>0,'covered_items'=>0,'avg_price'=>0];
$search      = $search      ?? '';
$type        = $type        ?? '';
$status      = $status      ?? '';
$page        = (int) ($page       ?? 1);
$perPage     = (int) ($perPage    ?? 25);
$totalRows   = (int) ($totalRows  ?? 0);
$totalPages  = (int) ($totalPages ?? 1);
$types       = $types       ?? [];
$typeLabels  = $typeLabels  ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtPrice = fn($n) => 'Rs. ' . number_format((float) $n, 2);

// Type → label
$typeLabel = fn(string $t): string => $typeLabels[$t] ?? ucfirst(str_replace('_', ' ', $t));

// Type → Bootstrap badge
$typeBadge = function (string $t): string {
    return match ($t) {
        'wash_fold'        => 'primary',
        'wash_iron'        => 'info',
        'dry_cleaning'     => 'dark',
        'ironing'          => 'secondary',
        'express'          => 'warning',
        'premium'          => 'success',
        'blanket_cleaning' => 'light',
        'curtain_cleaning' => 'light',
        default            => 'secondary',
    };
};

// Format duration (hours + days)
$formatDuration = function (int $hours, int $days): string {
    $parts = [];
    if ($days > 0) {
        $parts[] = $days === 1 ? '1 day' : "{$days} days";
    }
    if ($hours > 0) {
        $parts[] = $hours === 1 ? '1 hour' : "{$hours} hours";
    }
    return empty($parts) ? 'Instant' : implode(' ', $parts);
};

// Active filters count
$activeFilters = (int) ($search !== '') + (int) ($type !== '') + (int) ($status !== '');

// Build querystring (preserve filters)
$qs = function (int $targetPage) use ($search, $type, $status): string {
    $params = array_filter([
        'search' => $search !== '' ? $search : null,
        'type'   => $type   !== '' ? $type   : null,
        'status' => $status !== '' ? $status : null,
        'page'   => $targetPage,
    ], fn($v) => $v !== null);

    return '?' . http_build_query($params);
};

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Catalog Services</h1>
        <p class="text-muted mb-0 small">
            Manage laundry services ·
            <?= $fmtInt($totalRows) ?> service(s)
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/catalog/pricing" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-grid-3x3-gap me-1"></i>Pricing Matrix
        </a>
        <a href="/admin/catalog/services/export<?= $activeFilters ? '?' . http_build_query(array_filter([
            'search' => $search ?: null,
            'type'   => $type ?: null,
            'status' => $status ?: null,
        ])) : '' ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a href="/admin/catalog/services/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Service
        </a>
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

<!-- ==================== SUMMARY CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-tag"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Services</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['total']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt($summary['active']) ?> active ·
                            <?= $fmtInt($summary['inactive']) ?> inactive
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
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['pricing_rows']) ?></div>
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
                    <div class="kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-basket"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Covered Items</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['covered_items']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            items with pricing
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
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Avg Price</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtPrice($summary['avg_price']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            across all pricing
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
        <form method="get" action="/admin/catalog/services" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text"
                           class="form-control"
                           name="search"
                           value="<?= $e($search) ?>"
                           placeholder="Service name or description…">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Type</label>
                <select class="form-select" name="type">
                    <option value="">All types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $e($t) ?>" <?= $type === $t ? 'selected' : '' ?>>
                            <?= $e($typeLabel($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <option value="active"   <?= $status==='active'  ?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($activeFilters > 0): ?>
                    <a href="/admin/catalog/services" class="btn btn-outline-secondary" title="Clear filters">
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
                <?php if ($type !== ''): ?>
                    <span class="badge bg-light text-dark border">
                        Type: <?= $e($typeLabel($type)) ?>
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

<!-- ==================== SERVICES TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Service</th>
                    <th class="small text-muted fw-semibold">Type</th>
                    <th class="small text-muted fw-semibold">Duration</th>
                    <th class="small text-muted fw-semibold text-center">Pricing Rows</th>
                    <th class="small text-muted fw-semibold text-center">Orders</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold">Updated</th>
                    <th class="small text-muted fw-semibold text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($services)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-tag fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No services found</div>
                                <div class="small">
                                    <?php if ($activeFilters > 0): ?>
                                        Try adjusting your filters ·
                                        <a href="/admin/catalog/services" class="text-decoration-none">Clear all</a>
                                    <?php else: ?>
                                        <a href="/admin/catalog/services/create" class="text-decoration-none">
                                            Create your first service →
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($services as $s):
                    $sid         = (int) $s['service_id'];
                    $sName       = (string) $s['service_name'];
                    $sDesc       = (string) ($s['description'] ?? '');
                    $sType       = (string) $s['service_type'];
                    $sHours      = (int) $s['duration_hours'];
                    $sDays       = (int) $s['duration_days'];
                    $sActive     = (int) ($s['is_active'] ?? 0);
                    $pricingCnt  = (int) ($s['pricing_count'] ?? 0);
                    $ordersCnt   = (int) ($s['orders_count'] ?? 0);
                    $updatedAt   = $s['updated_at'] ?? $s['created_at'] ?? null;
                    $isExpress   = ($sType === 'express');
                ?>
                    <tr>
                        <!-- Service -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="service-icon bg-<?= $typeBadge($sType) ?>-subtle text-<?= $typeBadge($sType) ?>">
                                    <i class="bi bi-tag-fill"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-medium">
                                        <a href="/admin/catalog/services/<?= $sid ?>"
                                           class="text-decoration-none">
                                            <?= $e($sName) ?>
                                        </a>
                                        <?php if ($isExpress): ?>
                                            <span class="badge bg-warning-subtle text-warning ms-1"
                                                  style="font-size:.68rem;">
                                                <i class="bi bi-lightning-charge-fill"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($sDesc !== ''): ?>
                                        <div class="text-muted small text-truncate" style="max-width: 320px;">
                                            <?= $e($sDesc) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-muted" style="font-size:.72rem;">
                                        #<?= $sid ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Type -->
                        <td>
                            <span class="badge bg-<?= $typeBadge($sType) ?>">
                                <?= $e($typeLabel($sType)) ?>
                            </span>
                        </td>

                        <!-- Duration -->
                        <td class="small">
                            <i class="bi bi-clock text-muted me-1"></i>
                            <?= $e($formatDuration($sHours, $sDays)) ?>
                        </td>

                        <!-- Pricing count -->
                        <td class="text-center">
                            <?php if ($pricingCnt > 0): ?>
                                <a href="/admin/catalog/pricing?service=<?= $sid ?>"
                                   class="badge bg-warning-subtle text-warning text-decoration-none">
                                    <?= $fmtInt($pricingCnt) ?>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger"
                                      title="No pricing — customers can't order this service">
                                    <i class="bi bi-exclamation-triangle me-1"></i>0
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Orders count -->
                        <td class="text-center">
                            <?php if ($ordersCnt > 0): ?>
                                <span class="badge bg-info-subtle text-info"><?= $fmtInt($ordersCnt) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td class="text-center">
                            <?php if ($sActive): ?>
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
                                <a href="/admin/catalog/services/<?= $sid ?>"
                                   class="btn btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/admin/catalog/services/<?= $sid ?>/edit"
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
                                           href="/admin/catalog/pricing?service=<?= $sid ?>">
                                            <i class="bi bi-cash-coin me-2"></i>Manage Pricing
                                        </a>
                                    </li>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item"
                                                onclick="serviceAction('toggle', <?= $sid ?>, <?= json_encode($sName, $jsonFlags) ?>, <?= $sActive ?>, <?= $pricingCnt ?>)">
                                            <?php if ($sActive): ?>
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
                                                onclick="serviceAction('delete', <?= $sid ?>, <?= json_encode($sName, $jsonFlags) ?>, <?= $sActive ?>, <?= $pricingCnt ?>, <?= $ordersCnt ?>)"
                                                <?= ($ordersCnt > 0) ? 'disabled title="Service used in orders — deactivate instead"' : '' ?>>
                                            <i class="bi bi-trash me-2"></i>Delete
                                            <?php if ($ordersCnt > 0): ?>
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
    .service-icon {
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

    window.serviceAction = function (action, id, name, isActive, pricingCount, ordersCount) {
        pendingAction = { type: action, id: id };

        if (action === 'toggle') {
            modalTitle.textContent = isActive ? 'Deactivate Service' : 'Activate Service';
            if (isActive) {
                let extra = '';
                if (pricingCount > 0) {
                    extra = `<br><span class="small text-warning">
                        <i class="bi bi-info-circle me-1"></i>
                        ${pricingCount} pricing row(s) will also be deactivated.
                    </span>`;
                }
                modalText.innerHTML = `Deactivate <strong>${escapeHtml(name)}</strong>?
                    Customers will no longer be able to order this service.${extra}`;
                confirmBtn.textContent = 'Deactivate';
                confirmBtn.className = 'btn btn-warning';
            } else {
                modalText.innerHTML = `Activate <strong>${escapeHtml(name)}</strong>?
                    It will become available to customers again (pricing rows stay as-is).`;
                confirmBtn.textContent = 'Activate';
                confirmBtn.className = 'btn btn-success';
            }
        } else if (action === 'delete') {
            modalTitle.textContent = 'Delete Service';
            let extra = '';
            if (pricingCount > 0) {
                extra = `<br><span class="small text-warning">
                    <i class="bi bi-info-circle me-1"></i>
                    ${pricingCount} pricing row(s) will also be removed.
                </span>`;
            }
            modalText.innerHTML = `<strong class="text-danger">Permanently delete "${escapeHtml(name)}"?</strong><br>
                <span class="small text-muted">This cannot be undone.</span>${extra}`;
            confirmBtn.textContent = 'Delete';
            confirmBtn.className = 'btn btn-danger';
        }

        modal.show();
    };

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { type, id } = pendingAction;
        const url = '/admin/catalog/services/' + id + (type === 'toggle' ? '/toggle' : '/delete');

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
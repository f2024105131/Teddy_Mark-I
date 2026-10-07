<?php
/**
 * Admin Catalog — Service Detail View
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ServiceController::show()
 *
 * Expected variables:
 *   $service    array   full service row
 *                       service_id, service_name, description, service_type,
 *                       duration_hours, duration_days, is_active,
 *                       created_at, updated_at
 *   $pricing    array   pricing rows for this service (with item + category joins)
 *                       each: pricing_id, unit_price, is_active,
 *                             item_id, item_name, category_id, category_name
 *   $stats      array   ['orders_count','pricing_count','avg_price','min_price','max_price']
 *   $typeLabel  string  human label for service type
 * ----------------------------------------------------------
 */

$currentPage = 'catalog-services';
$title       = 'Service Details';

// Safe defaults
$service   = $service   ?? [];
$pricing   = $pricing   ?? [];
$stats     = $stats     ?? [];
$typeLabel = $typeLabel ?? '';

if (empty($service)) {
    echo '<div class="alert alert-danger">Service not found.</div>';
    return;
}

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';
$fmtPrice = fn($n) => 'Rs. ' . number_format((float) $n, 2);

// Extract values
$sid        = (int) $service['service_id'];
$sName      = (string) $service['service_name'];
$sDesc      = (string) ($service['description'] ?? '');
$sType      = (string) $service['service_type'];
$sHours     = (int) $service['duration_hours'];
$sDays      = (int) $service['duration_days'];
$sActive    = (int) ($service['is_active'] ?? 0);

// Type badge
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
$badgeColor = $typeBadge($sType);

// Duration formatting
$durationLabel = 'Instant';
$totalHours = ($sDays * 24) + $sHours;
if ($totalHours > 0) {
    $parts = [];
    if ($sDays > 0) $parts[] = $sDays === 1 ? '1 day' : "{$sDays} days";
    if ($sHours > 0) $parts[] = $sHours === 1 ? '1 hour' : "{$sHours} hours";
    $durationLabel = implode(' ', $parts);
}
$isExpress = ($sType === 'express');

// Stats
$ordersCount  = (int)   ($stats['orders_count']  ?? 0);
$pricingCount = (int)   ($stats['pricing_count'] ?? 0);
$avgPrice     = (float) ($stats['avg_price']     ?? 0);
$minPrice     = (float) ($stats['min_price']     ?? 0);
$maxPrice     = (float) ($stats['max_price']     ?? 0);

// Group pricing by category
$pricingByCategory = [];
foreach ($pricing as $p) {
    $cid  = (int) $p['category_id'];
    $cname = (string) $p['category_name'];
    if (!isset($pricingByCategory[$cid])) {
        $pricingByCategory[$cid] = [
            'category_name' => $cname,
            'items' => [],
        ];
    }
    $pricingByCategory[$cid]['items'][] = $p;
}

// Count active/inactive pricing rows
$activePricing = 0;
foreach ($pricing as $p) {
    if ((int) ($p['is_active'] ?? 0) === 1) $activePricing++;
}
$inactivePricing = count($pricing) - $activePricing;

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/catalog/services" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                <?= $e($sName) ?>
                <?php if ($isExpress): ?>
                    <span class="badge bg-warning-subtle text-warning" title="Express service">
                        <i class="bi bi-lightning-charge-fill me-1"></i>Express
                    </span>
                <?php endif; ?>
            </h1>
            <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                <span>#<?= $sid ?></span>
                <span>·</span>
                <span class="badge bg-<?= $badgeColor ?>"><?= $e($typeLabel) ?></span>
                <span>·</span>
                <?php if ($sActive): ?>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-circle-fill" style="font-size:.5rem;"></i> Active
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">
                        <i class="bi bi-circle-fill" style="font-size:.5rem;"></i> Inactive
                    </span>
                <?php endif; ?>
                <span>·</span>
                <span><i class="bi bi-clock me-1"></i><?= $e($durationLabel) ?></span>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/catalog/pricing?service=<?= $sid ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-grid-3x3-gap me-1"></i>Pricing Matrix
        </a>
        <a href="/admin/catalog/services/<?= $sid ?>/edit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-lightning-charge me-1"></i>Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="/admin/catalog/pricing?service=<?= $sid ?>">
                        <i class="bi bi-cash-coin me-2"></i>Manage Pricing
                    </a>
                </li>
                <li>
                    <button type="button" class="dropdown-item"
                            onclick="serviceAction('toggle', <?= $sid ?>, <?= json_encode($sName, $jsonFlags) ?>, <?= $sActive ?>)">
                        <?php if ($sActive): ?>
                            <i class="bi bi-pause-circle me-2"></i>Deactivate Service
                        <?php else: ?>
                            <i class="bi bi-play-circle me-2"></i>Activate Service
                        <?php endif; ?>
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item text-danger"
                            onclick="serviceAction('delete', <?= $sid ?>, <?= json_encode($sName, $jsonFlags) ?>)"
                            <?= $ordersCount > 0 ? 'disabled title="Service is used in orders — deactivate instead"' : '' ?>>
                        <i class="bi bi-trash me-2"></i>Delete Service
                        <?php if ($ordersCount > 0): ?>
                            <small class="text-muted">(in use)</small>
                        <?php endif; ?>
                    </button>
                </li>
            </ul>
        </div>
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

<!-- ==================== KPI CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-list-ol"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pricing Rows</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($pricingCount) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt($activePricing) ?> active ·
                            <?= $fmtInt($inactivePricing) ?> inactive
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
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Orders</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($ordersCount) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            total usage
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
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Avg Price</div>
                        <div class="fw-bold fs-6 mb-0"><?= $fmtPrice($avgPrice) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            per pricing row
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
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Price Range</div>
                        <div class="fw-bold fs-6 mb-0">
                            <?= $fmtPrice($minPrice) ?>
                            <?php if ($minPrice !== $maxPrice): ?>
                                – <?= $fmtPrice($maxPrice) ?>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;">
                            min – max
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MAIN GRID ==================== -->
<div class="row g-3">

    <!-- ==================== LEFT: SERVICE INFO ==================== -->
    <div class="col-lg-4">

        <!-- Service Information -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Service Information
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Name</div>
                    <div class="info-value fw-medium"><?= $e($sName) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Type</div>
                    <div class="info-value">
                        <span class="badge bg-<?= $badgeColor ?>">
                            <?= $e($typeLabel) ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Duration</div>
                    <div class="info-value">
                        <i class="bi bi-clock text-muted me-1"></i>
                        <?= $e($durationLabel) ?>
                        <div class="text-muted small" style="font-size:.72rem;">
                            (<?= $totalHours ?> hours total)
                        </div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <?php if ($sActive): ?>
                            <span class="badge bg-success-subtle text-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($sDesc !== ''): ?>
                    <div class="info-row">
                        <div class="info-label">Description</div>
                        <div class="info-value small"><?= $e($sDesc) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Record meta -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Record Information
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="info-row">
                    <div class="info-label">Service ID</div>
                    <div class="info-value"><code>#<?= $sid ?></code></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Created</div>
                    <div class="info-value small"><?= $fmtDateTime($service['created_at']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Last Updated</div>
                    <div class="info-value small">
                        <?php if (!empty($service['updated_at'])): ?>
                            <?= $fmtDateTime($service['updated_at']) ?>
                        <?php else: ?>
                            <span class="text-muted">Never</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== RIGHT: PRICING ==================== -->
    <div class="col-lg-8">

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-cash-coin me-2 text-primary"></i>Item Pricing
                    <span class="text-muted fw-normal">(<?= $fmtInt($pricingCount) ?>)</span>
                </h6>
                <a href="/admin/catalog/pricing?service=<?= $sid ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil-square me-1"></i>Edit Pricing
                </a>
            </div>

            <div class="card-body pb-0">
                <?php if ($pricingCount === 0): ?>
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div class="small">
                            <strong>No pricing configured for this service.</strong>
                            <div class="mt-1 text-muted">
                                Customers cannot order this service until at least one item has a price set.
                                <a href="/admin/catalog/pricing?service=<?= $sid ?>" class="alert-link">
                                    Add pricing now →
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="small text-muted mb-0">
                        These are the <strong>unit prices</strong> customers pay for each item
                        when processed with this service.
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($pricingCount > 0): ?>
                <div class="table-responsive mt-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-muted fw-semibold">Item</th>
                                <th class="small text-muted fw-semibold">Category</th>
                                <th class="small text-muted fw-semibold text-end">Unit Price</th>
                                <th class="small text-muted fw-semibold text-center">Status</th>
                                <th class="small text-muted fw-semibold text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pricingByCategory as $cid => $group): ?>
                                <!-- Category header row -->
                                <tr class="table-secondary">
                                    <td colspan="5" class="py-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-folder2 text-muted"></i>
                                            <strong class="small">
                                                <?= $e($group['category_name']) ?>
                                            </strong>
                                            <span class="badge bg-light text-dark border"
                                                  style="font-size:.65rem;">
                                                <?= count($group['items']) ?>
                                                item<?= count($group['items']) === 1 ? '' : 's' ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Item rows -->
                                <?php foreach ($group['items'] as $p):
                                    $itemId     = (int) $p['item_id'];
                                    $itemName   = (string) $p['item_name'];
                                    $unitPrice  = (float) $p['unit_price'];
                                    $pricingAct = (int) ($p['is_active'] ?? 0);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-medium small">
                                                <?= $e($itemName) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.7rem;">
                                                #<?= $itemId ?>
                                            </div>
                                        </td>
                                        <td class="small text-muted">
                                            <?= $e($group['category_name']) ?>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-medium">
                                                <?= $fmtPrice($unitPrice) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($pricingAct): ?>
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="bi bi-check-circle-fill" style="font-size:.5rem;"></i>
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary">
                                                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="/admin/catalog/items/<?= $itemId ?>/edit"
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Edit item">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Danger zone (if delete is available) -->
        <?php if ($ordersCount === 0): ?>
            <div class="card border-0 shadow-sm border-start border-danger border-3 mt-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-semibold text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="small text-muted flex-grow-1">
                            <strong>Delete this service permanently.</strong>
                            <div class="mt-1">
                                All pricing rows for this service will also be removed.
                                <?php if ($pricingCount > 0): ?>
                                    <span class="text-warning">
                                        (<?= $fmtInt($pricingCount) ?> pricing row(s))
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button"
                                class="btn btn-outline-danger btn-sm"
                                onclick="serviceAction('delete', <?= $sid ?>, <?= json_encode($sName, $jsonFlags) ?>)">
                            <i class="bi bi-trash me-1"></i>Delete Service
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info d-flex align-items-start gap-2 mt-3 mb-0">
                <i class="bi bi-shield-check mt-1"></i>
                <div class="small">
                    <strong>This service cannot be deleted</strong> because it's been used in
                    <strong><?= $fmtInt($ordersCount) ?></strong> order(s).
                    Deactivate it instead to hide it from customers while preserving order history.
                </div>
            </div>
        <?php endif; ?>
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
    .table > :not(caption) > * > * {
        padding: .7rem .75rem;
        vertical-align: middle;
    }
    .table-secondary td {
        background: #f8fafc;
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

    window.serviceAction = function (action, id, name, isActive) {
        pendingAction = { type: action, id: id };

        if (action === 'toggle') {
            modalTitle.textContent = isActive ? 'Deactivate Service' : 'Activate Service';
            if (isActive) {
                modalText.innerHTML = `Deactivate <strong>${escapeHtml(name)}</strong>?
                    Customers will no longer be able to order this service.
                    <br><span class="small text-warning">
                        <i class="bi bi-info-circle me-1"></i>
                        All pricing rows for this service will also be deactivated.
                    </span>`;
                confirmBtn.textContent = 'Deactivate';
                confirmBtn.className = 'btn btn-warning';
            } else {
                modalText.innerHTML = `Activate <strong>${escapeHtml(name)}</strong>?
                    It will become available to customers again.`;
                confirmBtn.textContent = 'Activate';
                confirmBtn.className = 'btn btn-success';
            }
        } else if (action === 'delete') {
            modalTitle.textContent = 'Delete Service';
            modalText.innerHTML = `<strong class="text-danger">Permanently delete "${escapeHtml(name)}"?</strong><br>
                <span class="small text-muted">This cannot be undone.
                All pricing rows for this service will also be removed.</span>`;
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
                // After delete → redirect to list; after toggle → reload
                setTimeout(() => {
                    if (type === 'delete') {
                        window.location.href = '/admin/catalog/services';
                    } else {
                        location.reload();
                    }
                }, 700);
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
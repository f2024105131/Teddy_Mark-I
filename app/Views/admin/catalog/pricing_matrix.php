<?php
/**
 * Admin Catalog — Pricing Matrix
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ServiceController::pricingMatrix()
 *
 * Expected variables:
 *   $services      array   service columns (service_id, service_name, service_type, is_active)
 *   $items         array   item rows (item_id, item_name, is_active, category_id, category_name)
 *   $pricingIndex  array   keyed by "item_id:service_id" → pricing row
 *                          each: pricing_id, item_id, service_id, unit_price, is_active
 *   $categories    array   for filter dropdown (category_id, category_name)
 *   $categoryId    int     current category filter (0 = all)
 *   $serviceId     int     current service filter (0 = all)
 *   $typeLabels    array   service_type → human label map
 *
 * POST endpoints:
 *   • POST /admin/catalog/pricing/bulk-update   → bulkUpdatePricing()
 *     body: prices["itemId:serviceId"] = price, _csrf
 *   • POST /admin/catalog/pricing/delete        → deletePrice()
 *     body: pricing_id, _csrf
 * ----------------------------------------------------------
 */

$currentPage = 'catalog-pricing';
$title       = 'Pricing Matrix';

// Safe defaults
$services     = $services     ?? [];
$items        = $items        ?? [];
$pricingIndex = $pricingIndex ?? [];
$categories   = $categories   ?? [];
$categoryId   = (int) ($categoryId ?? 0);
$serviceId    = (int) ($serviceId  ?? 0);
$typeLabels   = $typeLabels   ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtPrice = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtNum   = fn($n) => number_format((float) $n, 2);

$typeLabel = fn(string $t): string => $typeLabels[$t] ?? ucfirst(str_replace('_', ' ', $t));

// Type → color for header stripe
$typeColor = function (string $t): string {
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

// Group items by category (for row headers)
$itemsByCategory = [];
foreach ($items as $it) {
    $cid   = (int) $it['category_id'];
    $cname = (string) $it['category_name'];
    if (!isset($itemsByCategory[$cid])) {
        $itemsByCategory[$cid] = [
            'category_id'   => $cid,
            'category_name' => $cname,
            'items'         => [],
        ];
    }
    $itemsByCategory[$cid]['items'][] = $it;
}

// Compute matrix stats
$totalCells = count($items) * count($services);
$withPrice  = 0;
$emptyCells = 0;
$priceSum   = 0.0;
$priceCount = 0;

foreach ($items as $it) {
    foreach ($services as $sv) {
        $key = $it['item_id'] . ':' . $sv['service_id'];
        if (isset($pricingIndex[$key])) {
            $withPrice++;
            $priceSum += (float) $pricingIndex[$key]['unit_price'];
            $priceCount++;
        } else {
            $emptyCells++;
        }
    }
}

$avgPrice = $priceCount > 0 ? $priceSum / $priceCount : 0.0;
$coverage = $totalCells > 0 ? round(($withPrice / $totalCells) * 100, 1) : 0.0;

// Per-column stats (avg price per service)
$colStats = [];
foreach ($services as $sv) {
    $sid  = (int) $sv['service_id'];
    $sum  = 0.0;
    $cnt  = 0;
    foreach ($items as $it) {
        $key = $it['item_id'] . ':' . $sid;
        if (isset($pricingIndex[$key])) {
            $sum += (float) $pricingIndex[$key]['unit_price'];
            $cnt++;
        }
    }
    $colStats[$sid] = [
        'sum' => $sum,
        'cnt' => $cnt,
        'avg' => $cnt > 0 ? $sum / $cnt : 0.0,
    ];
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Pricing Matrix</h1>
        <p class="text-muted mb-0 small">
            Set unit prices for every <strong>item × service</strong> combination
            · <?= $fmtInt($totalCells) ?> cells
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/catalog/pricing/export<?= ($categoryId > 0 || $serviceId > 0) ? '?' . http_build_query(array_filter([
            'category' => $categoryId ?: null,
            'service'  => $serviceId ?: null,
        ])) : '' ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <button type="button"
                class="btn btn-primary btn-sm"
                id="saveAllBtn"
                disabled>
            <i class="bi bi-check-lg me-1"></i>
            <span id="saveAllLabel">Save All Changes</span>
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
        <a class="nav-link" href="/admin/catalog/services">
            <i class="bi bi-tag me-1"></i>Services
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="/admin/catalog/pricing">
            <i class="bi bi-grid-3x3-gap me-1"></i>Pricing Matrix
        </a>
    </li>
</ul>

<!-- ==================== STAT CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total Cells</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalCells) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt(count($items)) ?> items ×
                            <?= $fmtInt(count($services)) ?> services
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
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">With Pricing</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($withPrice) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $coverage ?>% coverage
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
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Empty Cells</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($emptyCells) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            need pricing
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
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Avg Price</div>
                        <div class="fw-bold fs-6 mb-0"><?= $fmtPrice($avgPrice) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            across all prices
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
        <form method="get" action="/admin/catalog/pricing" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Category</label>
                <select class="form-select" name="category" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c):
                        $cid = (int) $c['category_id'];
                    ?>
                        <option value="<?= $cid ?>" <?= $cid === $categoryId ? 'selected' : '' ?>>
                            <?= $e($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Service</label>
                <select class="form-select" name="service" onchange="this.form.submit()">
                    <option value="">All services</option>
                    <?php foreach ($services as $sv):
                        // Note: if serviceId is set, $services already contains just 1
                        $sid = (int) $sv['service_id'];
                    ?>
                        <option value="<?= $sid ?>" <?= $sid === $serviceId ? 'selected' : '' ?>>
                            <?= $e($sv['service_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <div class="form-check form-switch mb-0 align-self-center">
                    <input class="form-check-input" type="checkbox" id="emptyOnly">
                    <label class="form-check-label small" for="emptyOnly">
                        Show only empty cells
                    </label>
                </div>
                <?php if ($categoryId > 0 || $serviceId > 0): ?>
                    <a href="/admin/catalog/pricing" class="btn btn-outline-secondary btn-sm ms-auto" title="Clear filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MATRIX ==================== -->
<?php if (empty($items) || empty($services)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-grid-3x3-gap fs-1 text-muted d-block mb-2 opacity-50"></i>
            <div class="fw-medium">Cannot render matrix</div>
            <div class="small text-muted">
                <?php if (empty($items)): ?>
                    No items match the current filter.
                    <a href="/admin/catalog/items/create" class="text-decoration-none">Create an item →</a>
                <?php elseif (empty($services)): ?>
                    No services match the current filter.
                    <a href="/admin/catalog/services/create" class="text-decoration-none">Create a service →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-table me-2 text-primary"></i>Unit Prices (PKR)
            </h6>
            <div class="d-flex align-items-center gap-3 small text-muted">
                <span>
                    <span class="legend-box legend-filled"></span> Priced
                </span>
                <span>
                    <span class="legend-box legend-empty"></span> No price
                </span>
                <span>
                    <span class="legend-box legend-dirty"></span> Unsaved
                </span>
            </div>
        </div>

        <div class="matrix-wrap" id="matrixWrap">
            <table class="table table-bordered matrix-table mb-0" id="matrixTable">
                <thead>
                    <tr>
                        <th class="col-item">
                            <div class="d-flex align-items-center gap-1">
                                <i class="bi bi-basket text-muted"></i>
                                <span>Item</span>
                            </div>
                        </th>
                        <?php foreach ($services as $sv):
                            $sid  = (int) $sv['service_id'];
                            $sName = (string) $sv['service_name'];
                            $sType = (string) $sv['service_type'];
                            $color = $typeColor($sType);
                            $isActive = (int) ($sv['is_active'] ?? 0);
                        ?>
                            <th class="col-service text-center">
                                <div class="service-header">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <span class="badge bg-<?= $color ?>" style="font-size:.6rem;">
                                            <?= $e($typeLabel($sType)) ?>
                                        </span>
                                        <?php if (!$isActive): ?>
                                            <span class="badge bg-secondary" style="font-size:.6rem;"
                                                  title="Service is inactive">
                                                <i class="bi bi-slash-circle"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="service-name text-truncate" title="<?= $e($sName) ?>">
                                        <?= $e($sName) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.68rem;">
                                        <?= (int) $colStats[$sid]['cnt'] ?> priced
                                    </div>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itemsByCategory as $cid => $group): ?>
                        <!-- Category header row -->
                        <tr class="category-row">
                            <td colspan="<?= count($services) + 1 ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-folder2 text-primary"></i>
                                    <strong><?= $e($group['category_name']) ?></strong>
                                    <span class="badge bg-light text-dark border"
                                          style="font-size:.68rem;">
                                        <?= count($group['items']) ?> item(s)
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <!-- Item rows -->
                        <?php foreach ($group['items'] as $item):
                            $iid      = (int) $item['item_id'];
                            $iName    = (string) $item['item_name'];
                            $isItemActive = (int) ($item['is_active'] ?? 0);
                            $rowHasPrice = false;

                            // Precompute row pricing for data attributes
                            foreach ($services as $sv) {
                                $key = $iid . ':' . (int) $sv['service_id'];
                                if (isset($pricingIndex[$key])) {
                                    $rowHasPrice = true;
                                    break;
                                }
                            }
                        ?>
                            <tr data-item-id="<?= $iid ?>"
                                class="item-row <?= $rowHasPrice ? '' : 'row-empty' ?>">
                                <!-- Item cell (sticky) -->
                                <td class="col-item">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="min-w-0">
                                            <div class="fw-medium small text-truncate"
                                                 title="<?= $e($iName) ?>">
                                                <?= $e($iName) ?>
                                                <?php if (!$isItemActive): ?>
                                                    <i class="bi bi-slash-circle text-secondary ms-1"
                                                       title="Item is inactive"
                                                       style="font-size:.75rem;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.68rem;">
                                                #<?= $iid ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Price cells -->
                                <?php foreach ($services as $sv):
                                    $sid  = (int) $sv['service_id'];
                                    $key  = $iid . ':' . $sid;
                                    $row  = $pricingIndex[$key] ?? null;
                                    $has  = $row !== null;
                                    $price = $has ? (float) $row['unit_price'] : null;
                                    $pricingId = $has ? (int) $row['pricing_id'] : 0;
                                    $pricingActive = $has ? (int) $row['is_active'] : 0;
                                    $cellClass = $has ? 'cell-priced' : 'cell-empty';
                                    $value = $has ? number_format($price, 2, '.', '') : '';
                                ?>
                                    <td class="col-price text-center <?= $cellClass ?>"
                                        data-item-id="<?= $iid ?>"
                                        data-service-id="<?= $sid ?>"
                                        data-pricing-id="<?= $pricingId ?>"
                                        data-has-price="<?= $has ? '1' : '0' ?>">
                                        <div class="price-cell">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Rs.</span>
                                                <input type="number"
                                                       class="form-control form-control-sm price-input"
                                                       name="prices[<?= $iid ?>:<?= $sid ?>]"
                                                       value="<?= $e($value) ?>"
                                                       data-original="<?= $e($value) ?>"
                                                       data-item-id="<?= $iid ?>"
                                                       data-service-id="<?= $sid ?>"
                                                       min="0"
                                                       step="0.01"
                                                       placeholder="—"
                                                       autocomplete="off">
                                                <?php if ($has): ?>
                                                    <button type="button"
                                                            class="btn btn-outline-danger btn-sm delete-price-btn"
                                                            data-pricing-id="<?= $pricingId ?>"
                                                            title="Remove this price">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($has && !$pricingActive): ?>
                                                <div class="text-muted mt-1"
                                                     style="font-size:.65rem;">
                                                    <i class="bi bi-slash-circle"></i> inactive
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td class="col-item small text-muted fw-semibold">
                            <i class="bi bi-calculator me-1"></i>Column Average
                        </td>
                        <?php foreach ($services as $sv):
                            $sid = (int) $sv['service_id'];
                            $avg = $colStats[$sid]['avg'];
                            $cnt = $colStats[$sid]['cnt'];
                        ?>
                            <td class="text-center small">
                                <?php if ($cnt > 0): ?>
                                    <div class="fw-semibold"><?= $fmtPrice($avg) ?></div>
                                    <div class="text-muted" style="font-size:.68rem;">
                                        <?= $fmtInt($cnt) ?> price(s)
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ==================== STICKY SAVE BAR ==================== -->
<div class="sticky-save-bar" id="stickySaveBar" style="display: none;">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-circle-fill text-warning fs-5"></i>
            <div class="small">
                <strong id="dirtyCount">0</strong>
                <span id="dirtyLabel">unsaved change(s)</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="discardBtn">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Discard
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="saveAllBtnBottom">
                <i class="bi bi-check-lg me-1"></i>Save All
            </button>
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

    /* ---------- Matrix layout ---------- */
    .matrix-wrap {
        overflow: auto;
        max-height: 72vh;
        position: relative;
    }
    .matrix-table {
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .88rem;
    }
    .matrix-table th,
    .matrix-table td {
        border-color: #e2e8f0;
        vertical-align: middle;
        padding: .5rem .6rem;
    }

    /* Sticky header row */
    .matrix-table thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: #f8fafc;
        border-bottom: 2px solid #cbd5e1;
        min-width: 140px;
    }

    /* Sticky left column */
    .matrix-table .col-item {
        position: sticky;
        left: 0;
        z-index: 2;
        background: #fff;
        min-width: 180px;
        max-width: 220px;
        border-right: 2px solid #cbd5e1;
    }

    /* Sticky top-left cell (highest z-index) */
    .matrix-table thead .col-item {
        z-index: 4;
        background: #f8fafc;
    }

    /* Category header rows */
    .matrix-table .category-row td {
        background: #f1f5f9;
        border-top: 2px solid #cbd5e1;
        font-size: .82rem;
    }
    .matrix-table .category-row .col-item {
        background: #f1f5f9;
    }

    /* Service header */
    .service-header {
        line-height: 1.2;
    }
    .service-name {
        font-weight: 600;
        font-size: .82rem;
        margin-top: .15rem;
    }

    /* Price cells */
    .price-cell {
        min-width: 130px;
    }
    .price-cell .input-group {
        flex-wrap: nowrap;
    }
    .price-cell .input-group-text {
        background: #f8fafc;
        border-color: #e2e8f0;
        font-size: .72rem;
        color: #64748b;
        padding: .15rem .35rem;
    }
    .price-cell .form-control {
        text-align: right;
        font-size: .82rem;
        min-width: 55px;
    }
    .price-cell .form-control::-webkit-outer-spin-button,
    .price-cell .form-control::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .price-cell .form-control[type=number] {
        -moz-appearance: textfield;
        appearance: textfield;
    }

    /* Empty cell — dashed border */
    .cell-empty .form-control {
        background-image: linear-gradient(transparent, transparent);
        border-style: dashed;
        border-color: #cbd5e1;
    }

    /* Dirty cell — highlighted */
    .cell-dirty {
        background: #fef9c3 !important;
        box-shadow: inset 3px 0 0 0 #eab308;
    }
    .cell-dirty .input-group-text {
        background: #fef3c7;
        border-color: #fbbf24;
    }
    .cell-dirty .form-control {
        background-color: #fefce8;
    }

    /* Priced cell */
    .cell-priced {
        background: #f0f9ff;
    }

    /* Legend boxes */
    .legend-box {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 3px;
        vertical-align: middle;
        margin-right: 3px;
        border: 1px solid #cbd5e1;
    }
    .legend-filled { background: #f0f9ff; border-color: #7dd3fc; }
    .legend-empty  { background: #fff; border-style: dashed; }
    .legend-dirty  { background: #fef9c3; border-color: #fbbf24; }

    /* Sticky save bar */
    .sticky-save-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        padding: .75rem 1rem;
        background: #fff;
        border-top: 2px solid #0ea5e9;
        box-shadow: 0 -4px 12px rgba(15,23,42,.08);
        z-index: 1040;
        animation: slideUp .25s ease;
    }
    @media (min-width: 992px) {
        .sticky-save-bar { left: 250px; } /* match sidebar width */
    }
    @keyframes slideUp {
        from { transform: translateY(100%); }
        to   { transform: translateY(0); }
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const saveAllBtn       = document.getElementById('saveAllBtn');
    const saveAllLabel     = document.getElementById('saveAllLabel');
    const saveAllBtnBottom = document.getElementById('saveAllBtnBottom');
    const discardBtn       = document.getElementById('discardBtn');
    const stickySaveBar    = document.getElementById('stickySaveBar');
    const dirtyCountEl     = document.getElementById('dirtyCount');
    const dirtyLabelEl     = document.getElementById('dirtyLabel');
    const emptyOnlyToggle  = document.getElementById('emptyOnly');

    const allInputs = Array.from(document.querySelectorAll('.price-input'));

    // ---------- Dirty tracking ----------
    function collectDirty() {
        const dirty = [];
        for (const input of allInputs) {
            const orig = (input.dataset.original ?? '').trim();
            const cur  = (input.value ?? '').trim();
            if (orig !== cur) {
                dirty.push(input);
            }
        }
        return dirty;
    }

    function refreshDirtyState() {
        const dirty = collectDirty();

        // Update visual classes
        for (const input of allInputs) {
            const cell = input.closest('.col-price');
            if (!cell) continue;
            const orig = (input.dataset.original ?? '').trim();
            const cur  = (input.value ?? '').trim();
            const isDirty = orig !== cur;

            cell.classList.toggle('cell-dirty', isDirty);
            // Empty vs priced visual
            if (cur === '') {
                cell.classList.add('cell-empty');
                cell.classList.remove('cell-priced');
            } else {
                cell.classList.remove('cell-empty');
                cell.classList.add('cell-priced');
            }
        }

        // Update sticky bar
        if (dirty.length > 0) {
            dirtyCountEl.textContent = dirty.length;
            dirtyLabelEl.textContent = dirty.length === 1 ? 'unsaved change' : 'unsaved changes';
            stickySaveBar.style.display = 'block';

            saveAllBtn.disabled = false;
            saveAllLabel.textContent = `Save All (${dirty.length})`;
        } else {
            stickySaveBar.style.display = 'none';
            saveAllBtn.disabled = true;
            saveAllLabel.textContent = 'Save All Changes';
        }
    }

    // Attach input listeners (delegated for perf)
    document.addEventListener('input', (e) => {
        if (e.target.classList.contains('price-input')) {
            refreshDirtyState();
        }
    });

    // ---------- Save all ----------
    async function saveAll() {
        const dirty = collectDirty();
        if (dirty.length === 0) {
            showToast('No changes to save.', 'info');
            return;
        }

        const btn = saveAllBtnBottom || saveAllBtn;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        // Build payload: only cells with non-empty values
        const body = new URLSearchParams();
        body.append('_csrf', csrf);

        let included = 0;
        for (const input of allInputs) {
            const value = (input.value ?? '').trim();
            if (value === '') continue;

            const numeric = parseFloat(value);
            if (!isFinite(numeric) || numeric <= 0) continue;

            const iid = input.dataset.itemId;
            const sid = input.dataset.serviceId;
            if (!iid || !sid) continue;

            body.append(`prices[${iid}:${sid}]`, numeric.toFixed(2));
            included++;
        }

        if (included === 0) {
            showToast('No valid prices to save.', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }

        try {
            const res = await fetch('/admin/catalog/pricing/bulk-update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || `${included} price(s) saved.`, 'success');

                // Commit: update data-original on all inputs
                for (const input of allInputs) {
                    input.dataset.original = (input.value ?? '').trim();
                }
                refreshDirtyState();
                btn.disabled = false;
                btn.innerHTML = originalText;
            } else {
                showToast(json.message || 'Failed to save prices.', 'danger');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    saveAllBtn?.addEventListener('click', saveAll);
    saveAllBtnBottom?.addEventListener('click', saveAll);

    // ---------- Discard changes ----------
    discardBtn?.addEventListener('click', () => {
        const dirty = collectDirty();
        if (dirty.length === 0) return;

        if (!confirm(`Discard ${dirty.length} unsaved change(s)?`)) return;

        for (const input of allInputs) {
            input.value = input.dataset.original ?? '';
        }
        refreshDirtyState();
    });

    // ---------- Delete price (per cell) ----------
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.delete-price-btn');
        if (!btn) return;

        const pricingId = parseInt(btn.dataset.pricingId, 10);
        if (!pricingId) return;

        const cell = btn.closest('.col-price');
        const input = cell?.querySelector('.price-input');

        if (!confirm('Remove this price? Customers will no longer be able to order this item with this service.')) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const body = new URLSearchParams();
            body.append('pricing_id', pricingId);
            body.append('_csrf', csrf);

            const res = await fetch('/admin/catalog/pricing/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });

            const json = await res.json();

            if (json.success) {
                showToast('Price removed.', 'success');

                // Update cell visually without full reload
                if (input) {
                    input.value = '';
                    input.dataset.original = '';
                }
                if (cell) {
                    cell.dataset.hasPrice = '0';
                    cell.dataset.pricingId = '0';
                    cell.classList.remove('cell-priced');
                    cell.classList.add('cell-empty');
                }
                btn.remove();

                // Remove the dirty state since we committed server-side
                refreshDirtyState();
            } else {
                showToast(json.message || 'Failed to remove price.', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-x"></i>';
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-x"></i>';
        }
    });

    // ---------- Empty-only filter (client-side) ----------
    emptyOnlyToggle?.addEventListener('change', () => {
        const onlyEmpty = emptyOnlyToggle.checked;
        const rows = document.querySelectorAll('.matrix-table tbody .item-row');

        rows.forEach(row => {
            if (!onlyEmpty) {
                row.style.display = '';
                return;
            }

            const cells = row.querySelectorAll('.col-price');
            const hasEmpty = Array.from(cells).some(c => c.classList.contains('cell-empty'));
            row.style.display = hasEmpty ? '' : 'none';
        });

        // Also hide empty category headers
        document.querySelectorAll('.matrix-table tbody .category-row').forEach(catRow => {
            let sibling = catRow.nextElementSibling;
            let anyVisible = false;
            while (sibling && sibling.classList.contains('item-row')) {
                if (sibling.style.display !== 'none') {
                    anyVisible = true;
                    break;
                }
                sibling = sibling.nextElementSibling;
            }
            catRow.style.display = anyVisible ? '' : 'none';
        });
    });

    // ---------- Warn on unload with unsaved changes ----------
    window.addEventListener('beforeunload', (e) => {
        if (collectDirty().length > 0) {
            e.preventDefault();
            e.returnValue = '';
        }
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
        new bootstrap.Toast(toast, { delay: 3500 }).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }

    // Initial paint
    refreshDirtyState();
})();
</script>
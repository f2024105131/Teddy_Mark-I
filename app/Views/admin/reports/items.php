<?php
/**
 * Admin Reports — Item Popularity
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::items()
 *
 * Expected variables:
 *   $from          string   Y-m-d
 *   $to            string   Y-m-d
 *   $summary       array    ['unique_items','unique_services','items_sold','revenue']
 *   $topItems      array    rows: [item_id, item_name, category_name,
 *                                  total_quantity, revenue, orders_count]
 *   $topServices   array    rows: [service_id, service_name, service_type,
 *                                  total_quantity, revenue, orders_count]
 *   $byCategory    array    rows: [category_id, category_name, items_count,
 *                                  quantity, revenue]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Item Popularity Report';

// Safe defaults
$from        = $from        ?? date('Y-m-d', strtotime('-30 days'));
$to          = $to          ?? date('Y-m-d');
$summary     = $summary     ?? [];
$topItems    = $topItems    ?? [];
$topServices = $topServices ?? [];
$byCategory  = $byCategory  ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';

// Extract summary
$uniqueItems    = (int)   ($summary['unique_items']    ?? 0);
$uniqueServices = (int)   ($summary['unique_services'] ?? 0);
$itemsSold      = (int)   ($summary['items_sold']      ?? 0);
$totalRevenue   = (float) ($summary['revenue']         ?? 0);

// Service type badge
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
$typeLabel = fn(string $t): string => ucwords(str_replace('_', ' ', $t));
$typeHex = [
    'primary'   => '#0ea5e9',
    'info'      => '#06b6d4',
    'dark'      => '#1e293b',
    'secondary' => '#64748b',
    'warning'   => '#f59e0b',
    'success'   => '#22c55e',
    'light'     => '#cbd5e1',
];

// Compute max values for progress bars
$maxItemRevenue  = 1;
foreach ($topItems as $it) {
    $maxItemRevenue = max($maxItemRevenue, (float) ($it['revenue'] ?? 0));
}
$maxServiceRevenue = 1;
foreach ($topServices as $sv) {
    $maxServiceRevenue = max($maxServiceRevenue, (float) ($sv['revenue'] ?? 0));
}
$maxCategoryRevenue = 1;
foreach ($byCategory as $cat) {
    $maxCategoryRevenue = max($maxCategoryRevenue, (float) ($cat['revenue'] ?? 0));
}

// Category chart data (top 8)
$catRowsTop = array_slice($byCategory, 0, 8);
$catLabels  = [];
$catValues  = [];
$catHexes   = ['#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#64748b', '#a855f7'];
foreach ($catRowsTop as $i => $cat) {
    $catLabels[] = (string) ($cat['category_name'] ?? 'Uncategorized');
    $catValues[] = (float) ($cat['revenue'] ?? 0);
}

// Top items chart data (top 8)
$itemRowsTop = array_slice($topItems, 0, 8);
$itemLabels  = [];
$itemValues  = [];
foreach ($itemRowsTop as $it) {
    $itemLabels[] = (string) ($it['item_name'] ?? '');
    $itemValues[] = (int) ($it['total_quantity'] ?? 0);
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="/admin/reports?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title mb-1">
                <i class="bi bi-basket me-2 text-primary"></i>Item Popularity
            </h1>
            <p class="text-muted mb-0 small">
                <strong><?= $e($fmtDate($from)) ?></strong> → <strong><?= $e($fmtDate($to)) ?></strong>
                · <?= $fmtInt($itemsSold) ?> items sold
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/catalog/items"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-basket me-1"></i>Catalog
        </a>
        <a href="/admin/reports/items/json?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-code-slash me-1"></i>JSON
        </a>
        <a href="/admin/reports/export?report=items&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== FILTER BAR ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports/items" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="from" class="form-label small text-muted mb-1">From</label>
                <input type="date"
                       class="form-control"
                       id="from"
                       name="from"
                       value="<?= $e($from) ?>"
                       max="<?= $e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <label for="to" class="form-label small text-muted mb-1">To</label>
                <input type="date"
                       class="form-control"
                       id="to"
                       name="to"
                       value="<?= $e($to) ?>"
                       max="<?= $e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Apply Range
                </button>
            </div>
        </form>

        <div class="d-flex flex-wrap gap-2 align-items-center mt-3 pt-3 border-top">
            <span class="text-muted small me-1">Quick ranges:</span>
            <?php
            $presets = [
                ['label' => 'Today',        'from' => date('Y-m-d'),                  'to' => date('Y-m-d')],
                ['label' => 'Last 7 days',  'from' => date('Y-m-d', strtotime('-6 days')),  'to' => date('Y-m-d')],
                ['label' => 'Last 30 days', 'from' => date('Y-m-d', strtotime('-29 days')), 'to' => date('Y-m-d')],
                ['label' => 'This month',   'from' => date('Y-m-01'),                 'to' => date('Y-m-d')],
                ['label' => 'Last 90 days', 'from' => date('Y-m-d', strtotime('-89 days')), 'to' => date('Y-m-d')],
                ['label' => 'This year',    'from' => date('Y-01-01'),                'to' => date('Y-m-d')],
            ];
            foreach ($presets as $p):
                $isActive = ($from === $p['from'] && $to === $p['to']);
            ?>
                <a href="?from=<?= $e($p['from']) ?>&to=<?= $e($p['to']) ?>"
                   class="badge text-decoration-none <?= $isActive ? 'bg-primary' : 'bg-light text-dark border' ?>">
                    <?= $e($p['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ==================== SUMMARY KPIs ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Items Sold</span>
                    <span class="badge bg-primary-subtle text-primary">
                        <i class="bi bi-basket"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($itemsSold) ?></h3>
                <div class="text-muted small">
                    <?= $fmtInt($uniqueItems) ?> unique item(s)
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Services Used</span>
                    <span class="badge bg-info-subtle text-info">
                        <i class="bi bi-tag"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtInt($uniqueServices) ?></h3>
                <div class="text-muted small">
                    unique service(s)
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100"
             style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-uppercase fw-semibold opacity-75">Total Revenue</span>
                    <i class="bi bi-cash-coin fs-4 opacity-75"></i>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($totalRevenue) ?></h3>
                <div class="small opacity-75">
                    Across all items in range
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CHARTS ==================== -->
<div class="row g-3 mb-4">

    <!-- Top items chart -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-bar-chart me-2 text-primary"></i>Top Items by Quantity Sold
                </h6>
                <span class="text-muted small">Top 8</span>
            </div>
            <div class="card-body">
                <?php if (empty($itemLabels)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-basket fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No item sales in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="topItemsChart" height="240"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category revenue doughnut -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-pie-chart me-2 text-primary"></i>Revenue by Category
                </h6>
                <span class="text-muted small"><?= count($catLabels) ?> categor<?= count($catLabels) === 1 ? 'y' : 'ies' ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($catLabels)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No category data.</div>
                    </div>
                <?php else: ?>
                    <canvas id="categoryChart" height="240"></canvas>
                    <div class="mt-3 pt-3 border-top">
                        <?php
                        $catSum = array_sum($catValues);
                        foreach ($catRowsTop as $i => $cat):
                            $catName = (string) ($cat['category_name'] ?? 'Uncategorized');
                            $revenue = (float) ($cat['revenue'] ?? 0);
                            $pct = $catSum > 0 ? ($revenue / $catSum) * 100 : 0;
                        ?>
                            <div class="d-flex align-items-center gap-2 mb-2 small">
                                <span class="status-dot" style="background: <?= $catHexes[$i % count($catHexes)] ?>"></span>
                                <span class="flex-grow-1 text-truncate" style="max-width: 160px;">
                                    <?= $e($catName) ?>
                                </span>
                                <span class="text-muted"><?= number_format($pct, 1) ?>%</span>
                                <span class="fw-medium text-end" style="min-width: 90px;">
                                    <?= $fmtMoney($revenue) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($byCategory) > count($catRowsTop)): ?>
                            <div class="text-center text-muted small mt-2">
                                +<?= count($byCategory) - count($catRowsTop) ?> more categor<?= (count($byCategory) - count($catRowsTop)) === 1 ? 'y' : 'ies' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TOP ITEMS TABLE ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-trophy me-2 text-warning"></i>Top Items by Revenue
        </h6>
        <span class="text-muted small"><?= $fmtInt(count($topItems)) ?> item(s)</span>
    </div>
    <div class="table-responsive" style="max-height: 520px;">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th class="small text-muted fw-semibold" style="width: 40px;">#</th>
                    <th class="small text-muted fw-semibold">Item</th>
                    <th class="small text-muted fw-semibold">Category</th>
                    <th class="small text-muted fw-semibold text-center">Qty</th>
                    <th class="small text-muted fw-semibold text-center">Orders</th>
                    <th class="small text-muted fw-semibold text-end">Revenue</th>
                    <th class="small text-muted fw-semibold" style="width: 20%;">Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topItems)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No items sold in this range</div>
                                <div class="small">Adjust the date range to see item data.</div>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $rank = 0;
                    foreach ($topItems as $it):
                        $rank++;
                        $itemId    = (int) ($it['item_id'] ?? 0);
                        $itemName  = (string) ($it['item_name'] ?? '');
                        $catName   = (string) ($it['category_name'] ?? '');
                        $quantity  = (int) ($it['total_quantity'] ?? 0);
                        $orders    = (int) ($it['orders_count'] ?? 0);
                        $revenue   = (float) ($it['revenue'] ?? 0);
                        $sharePct  = $maxItemRevenue > 0 ? ($revenue / $maxItemRevenue) * 100 : 0;
                        $rankBadge = match ($rank) {
                            1 => 'bg-warning text-dark',
                            2 => 'bg-secondary text-white',
                            3 => 'bg-danger text-white',
                            default => 'bg-light text-dark border',
                        };
                ?>
                    <tr>
                        <td>
                            <span class="badge <?= $rankBadge ?>"><?= $rank ?></span>
                        </td>
                        <td>
                            <div class="fw-medium small">
                                <a href="/admin/catalog/items/<?= $itemId ?>/edit"
                                   class="text-decoration-none">
                                    <?= $e($itemName) ?>
                                </a>
                            </div>
                            <div class="text-muted" style="font-size:.7rem;">
                                #<?= $itemId ?>
                            </div>
                        </td>
                        <td class="small text-muted">
                            <?= $e($catName) ?>
                        </td>
                        <td class="text-center small">
                            <span class="badge bg-primary-subtle text-primary">
                                <?= $fmtInt($quantity) ?>
                            </span>
                        </td>
                        <td class="text-center small text-muted">
                            <?= $fmtInt($orders) ?>
                        </td>
                        <td class="text-end small fw-medium">
                            <?= $fmtMoney($revenue) ?>
                        </td>
                        <td>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: <?= $sharePct ?>%"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== TOP SERVICES TABLE ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-tag me-2 text-primary"></i>Top Services by Revenue
        </h6>
        <span class="text-muted small"><?= $fmtInt(count($topServices)) ?> service(s)</span>
    </div>
    <div class="table-responsive" style="max-height: 520px;">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th class="small text-muted fw-semibold" style="width: 40px;">#</th>
                    <th class="small text-muted fw-semibold">Service</th>
                    <th class="small text-muted fw-semibold">Type</th>
                    <th class="small text-muted fw-semibold text-center">Qty</th>
                    <th class="small text-muted fw-semibold text-center">Orders</th>
                    <th class="small text-muted fw-semibold text-end">Revenue</th>
                    <th class="small text-muted fw-semibold" style="width: 20%;">Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topServices)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No services used in this range</div>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $rank = 0;
                    foreach ($topServices as $sv):
                        $rank++;
                        $serviceId   = (int) ($sv['service_id'] ?? 0);
                        $serviceName = (string) ($sv['service_name'] ?? '');
                        $serviceType = (string) ($sv['service_type'] ?? '');
                        $quantity    = (int) ($sv['total_quantity'] ?? 0);
                        $orders      = (int) ($sv['orders_count'] ?? 0);
                        $revenue     = (float) ($sv['revenue'] ?? 0);
                        $sharePct    = $maxServiceRevenue > 0 ? ($revenue / $maxServiceRevenue) * 100 : 0;
                        $rankBadge   = match ($rank) {
                            1 => 'bg-warning text-dark',
                            2 => 'bg-secondary text-white',
                            3 => 'bg-danger text-white',
                            default => 'bg-light text-dark border',
                        };
                        $badge = $typeBadge($serviceType);
                ?>
                    <tr>
                        <td>
                            <span class="badge <?= $rankBadge ?>"><?= $rank ?></span>
                        </td>
                        <td>
                            <div class="fw-medium small">
                                <a href="/admin/catalog/services/<?= $serviceId ?>"
                                   class="text-decoration-none">
                                    <?= $e($serviceName) ?>
                                </a>
                            </div>
                            <div class="text-muted" style="font-size:.7rem;">
                                #<?= $serviceId ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $badge ?>" style="font-size:.7rem;">
                                <?= $e($typeLabel($serviceType)) ?>
                            </span>
                        </td>
                        <td class="text-center small">
                            <span class="badge bg-primary-subtle text-primary">
                                <?= $fmtInt($quantity) ?>
                            </span>
                        </td>
                        <td class="text-center small text-muted">
                            <?= $fmtInt($orders) ?>
                        </td>
                        <td class="text-end small fw-medium">
                            <?= $fmtMoney($revenue) ?>
                        </td>
                        <td>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?= $sharePct ?>%"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== CATEGORY BREAKDOWN TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-tags me-2 text-primary"></i>Category Performance
        </h6>
        <span class="text-muted small"><?= count($byCategory) ?> categor<?= count($byCategory) === 1 ? 'y' : 'ies' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Category</th>
                    <th class="small text-muted fw-semibold text-center">Items</th>
                    <th class="small text-muted fw-semibold text-center">Quantity</th>
                    <th class="small text-muted fw-semibold text-end">Revenue</th>
                    <th class="small text-muted fw-semibold text-end">Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($byCategory)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            <div class="small">No category data.</div>
                        </td>
                    </tr>
                <?php else:
                    $catTotal = 0;
                    foreach ($byCategory as $c) {
                        $catTotal += (float) ($c['revenue'] ?? 0);
                    }
                    foreach ($byCategory as $i => $cat):
                        $catId    = (int) ($cat['category_id'] ?? 0);
                        $catName  = (string) ($cat['category_name'] ?? 'Uncategorized');
                        $itemsCnt = (int) ($cat['items_count'] ?? 0);
                        $quantity = (int) ($cat['quantity'] ?? 0);
                        $revenue  = (float) ($cat['revenue'] ?? 0);
                        $pct      = $catTotal > 0 ? ($revenue / $catTotal) * 100 : 0;
                ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-dot" style="background: <?= $catHexes[$i % count($catHexes)] ?>"></span>
                                <span class="small fw-medium">
                                    <a href="/admin/catalog/items?category=<?= $catId ?>"
                                       class="text-decoration-none">
                                        <?= $e($catName) ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="text-center small text-muted">
                            <?= $fmtInt($itemsCnt) ?>
                        </td>
                        <td class="text-center small">
                            <span class="badge bg-primary-subtle text-primary">
                                <?= $fmtInt($quantity) ?>
                            </span>
                        </td>
                        <td class="text-end small fw-medium">
                            <?= $fmtMoney($revenue) ?>
                        </td>
                        <td class="text-end small text-muted">
                            <?= number_format($pct, 1) ?>%
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== STYLES ==================== -->
<style>
    .status-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .table > :not(caption) > * > * {
        padding: .7rem .75rem;
        vertical-align: middle;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const itemLabels = <?= json_encode($itemLabels, $jsonFlags) ?>;
    const itemValues = <?= json_encode($itemValues, $jsonFlags) ?>;

    const catLabels = <?= json_encode($catLabels, $jsonFlags) ?>;
    const catValues = <?= json_encode($catValues, $jsonFlags) ?>;
    const catHexes  = <?= json_encode(array_slice($catHexes, 0, count($catLabels)), $jsonFlags) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // ---------- Top items bar chart ----------
    const itemsCanvas = document.getElementById('topItemsChart');
    if (itemsCanvas && itemLabels.length) {
        new Chart(itemsCanvas, {
            type: 'bar',
            data: {
                labels: itemLabels,
                datasets: [{
                    label: 'Quantity Sold',
                    data: itemValues,
                    backgroundColor: '#0ea5e9',
                    borderRadius: 4,
                    maxBarThickness: 26,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ' + Number(ctx.raw).toLocaleString() + ' item(s) sold'
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        title: { display: true, text: 'Quantity Sold', font: { size: 10 } }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // ---------- Category doughnut ----------
    const catCanvas = document.getElementById('categoryChart');
    if (catCanvas && catLabels.length) {
        new Chart(catCanvas, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catValues,
                    backgroundColor: catHexes,
                    borderColor: '#fff',
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const v = Number(ctx.raw);
                                return ' ' + ctx.label + ': Rs. ' + v.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
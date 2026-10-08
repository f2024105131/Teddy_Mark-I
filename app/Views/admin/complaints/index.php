<?php
/**
 * Admin Complaints — List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ComplaintController::index()
 *
 * Expected variables:
 *   $complaints  array   paginated complaint rows (with joins)
 *                        each: complaint_id, complaint_number, type, status,
 *                              created_at, resolved_at,
 *                              customer_id, customer_name, customer_email, customer_phone,
 *                              order_id, order_number, order_status,
 *                              category_name, assigned_staff_id, assigned_staff_name,
 *                              refund_count
 *   $summary     array   ['total','open_total','assigned_total','investigating',
 *                         'escalated_total','resolved_total','rejected_total',
 *                         'closed_total','created_today','resolved_today']
 *   $categories  array   complaint categories for filter
 *   $staffList   array   active staff for assignment filter
 *   $types       array   complaint type enum values
 *   $typeLabels  array   type → human label
 *   $search      string
 *   $status      string
 *   $type        string
 *   $categoryId  int
 *   $staffId     int
 *   $from        string
 *   $to          string
 *   $page        int
 *   $perPage     int
 *   $totalRows   int
 *   $totalPages  int
 * ----------------------------------------------------------
 */

$currentPage = 'complaints';
$title       = 'Complaints';

// Safe defaults
$complaints = $complaints ?? [];
$summary    = $summary    ?? [];
$categories = $categories ?? [];
$staffList  = $staffList  ?? [];
$types      = $types      ?? [];
$typeLabels = $typeLabels ?? [];

$search     = $search     ?? '';
$status     = $status     ?? '';
$type       = $type       ?? '';
$categoryId = (int) ($categoryId ?? 0);
$staffId    = (int) ($staffId    ?? 0);
$from       = $from       ?? '';
$to         = $to         ?? '';
$page       = (int) ($page       ?? 1);
$perPage    = (int) ($perPage    ?? 20);
$totalRows  = (int) ($totalRows  ?? 0);
$totalPages = (int) ($totalPages ?? 1);

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j · g:i A', strtotime($d)) : '—';

// Relative time helper
$timeAgo = function (?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    if (!$ts) return '—';
    $diff = time() - $ts;
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
};

// Status → badge
$statusBadge = function (string $s): string {
    return match ($s) {
        'open'                => 'danger',
        'assigned'            => 'warning',
        'under_investigation' => 'info',
        'resolved'            => 'success',
        'rejected'            => 'secondary',
        'escalated'           => 'dark',
        'closed'              => 'light',
        default               => 'secondary',
    };
};
$statusLabel = function (string $s): string {
    return match ($s) {
        'open'                => 'Open',
        'assigned'            => 'Assigned',
        'under_investigation' => 'Investigating',
        'resolved'            => 'Resolved',
        'rejected'            => 'Rejected',
        'escalated'           => 'Escalated',
        'closed'              => 'Closed',
        default               => ucfirst(str_replace('_', ' ', $s)),
    };
};

// Type badge color
$typeBadge = function (string $t): string {
    return match ($t) {
        'missing_item'          => 'danger',
        'damaged_item'          => 'warning',
        'late_delivery'         => 'info',
        'wrong_billing'         => 'primary',
        'poor_cleaning_quality' => 'secondary',
        default                 => 'secondary',
    };
};

// Type label (fallback to human)
$typeLabel = fn(string $t): string => $typeLabels[$t] ?? ucwords(str_replace('_', ' ', $t));

// Priority: escalated + open = urgent
$isPriority = fn(string $s): bool => in_array($s, ['escalated', 'open'], true);

// Build querystring preserving filters
$qs = function (array $overrides = []) use ($search, $status, $type, $categoryId, $staffId, $from, $to): string {
    $params = array_filter([
        'search'   => $overrides['search']   ?? ($search ?: null),
        'status'   => array_key_exists('status', $overrides) ? $overrides['status'] : ($status ?: null),
        'type'     => array_key_exists('type', $overrides) ? $overrides['type'] : ($type ?: null),
        'category' => array_key_exists('category', $overrides) ? $overrides['category'] : ($categoryId ?: null),
        'staff'    => array_key_exists('staff', $overrides) ? $overrides['staff'] : ($staffId ?: null),
        'from'     => array_key_exists('from', $overrides) ? $overrides['from'] : ($from ?: null),
        'to'       => array_key_exists('to', $overrides) ? $overrides['to'] : ($to ?: null),
        'page'     => $overrides['page'] ?? null,
    ], fn($v) => $v !== null && $v !== '');

    return '?' . http_build_query($params);
};

// Active filters count
$activeFilters = (int) ($search !== '')
               + (int) ($status !== '')
               + (int) ($type !== '')
               + (int) ($categoryId > 0)
               + (int) ($staffId > 0)
               + (int) ($from !== '')
               + (int) ($to !== '');

// Filter tab definitions — pass empty string to clear the filter
$tabs = [
    ''                    => ['label' => 'All',          'color' => 'secondary', 'count' => $summary['total']             ?? 0],
    'escalated'           => ['label' => 'Escalated',    'color' => 'dark',      'count' => $summary['escalated_total']   ?? 0],
    'open'                => ['label' => 'Open',         'color' => 'danger',    'count' => $summary['open_total']        ?? 0],
    'assigned'            => ['label' => 'Assigned',     'color' => 'warning',   'count' => $summary['assigned_total']    ?? 0],
    'under_investigation' => ['label' => 'Investigating','color' => 'info',      'count' => $summary['investigating']     ?? 0],
    'resolved'            => ['label' => 'Resolved',     'color' => 'success',   'count' => $summary['resolved_total']    ?? 0],
    'rejected'            => ['label' => 'Rejected',     'color' => 'secondary', 'count' => $summary['rejected_total']    ?? 0],
    'closed'              => ['label' => 'Closed',       'color' => 'light',     'count' => $summary['closed_total']      ?? 0],
];

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Complaints</h1>
        <p class="text-muted mb-0 small">
            Manage customer complaints and escalations ·
            <?= $fmtInt($totalRows) ?> total
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/complaints/escalations" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-exclamation-triangle me-1"></i>Escalations
            <?php if (!empty($summary['escalated_total'])): ?>
                <span class="badge bg-danger ms-1"><?= $fmtInt($summary['escalated_total']) ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/complaints/categories" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tags me-1"></i>Categories
        </a>
        <a href="/admin/refunds" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Refunds
        </a>
        <a href="/admin/complaints/export<?= $activeFilters ? '?' . http_build_query(array_filter([
            'search'   => $search ?: null,
            'status'   => $status ?: null,
            'type'     => $type ?: null,
            'category' => $categoryId ?: null,
            'staff'    => $staffId ?: null,
            'from'     => $from ?: null,
            'to'       => $to ?: null,
        ])) : '' ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== SUMMARY CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            +<?= $fmtInt($summary['created_today'] ?? 0) ?> today
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
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Active</div>
                        <div class="fw-bold fs-5 mb-0">
                            <?= $fmtInt(($summary['open_total'] ?? 0) + ($summary['assigned_total'] ?? 0) + ($summary['investigating'] ?? 0) + ($summary['escalated_total'] ?? 0)) ?>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;">
                            need attention
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
                        <div class="text-muted small text-uppercase fw-semibold">Resolved</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['resolved_total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt($summary['resolved_today'] ?? 0) ?> today
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
                    <div class="kpi-icon bg-dark-subtle text-dark">
                        <i class="bi bi-shield-exclamation"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Escalated</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['escalated_total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            need admin action
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FILTER TABS ==================== -->
<ul class="nav nav-pills mb-3 gap-2 flex-wrap">
    <?php foreach ($tabs as $slug => $tab):
        $isActive = ($slug === '' && $status === '') || ($slug !== '' && $status === $slug);
        $count = (int) ($tab['count'] ?? 0);
        $hasUrgent = in_array($slug, ['escalated', 'open'], true) && $count > 0;
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $isActive ? 'active' : '' ?>"
               href="<?= $e($qs(['status' => $slug, 'page' => null])) ?>">
                <?php if ($hasUrgent): ?>
                    <i class="bi bi-exclamation-circle-fill me-1"></i>
                <?php endif; ?>
                <?= $e($tab['label']) ?>
                <?php if ($count > 0): ?>
                    <span class="badge bg-light text-dark ms-1" style="font-size:.65rem;">
                        <?= $fmtInt($count) ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- ==================== ADVANCED FILTERS ==================== -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="/admin/complaints" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= $e($status) ?>">

            <!-- Search -->
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text"
                           class="form-control"
                           name="search"
                           value="<?= $e($search) ?>"
                           placeholder="Complaint #, order #, customer, phone…">
                </div>
            </div>

            <!-- Type -->
            <div class="col-md-2">
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

            <!-- Category -->
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Category</label>
                <select class="form-select" name="category">
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

            <!-- Staff -->
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Assigned To</label>
                <select class="form-select" name="staff">
                    <option value="">All staff</option>
                    <?php foreach ($staffList as $s):
                        $sid = (int) $s['staff_id'];
                    ?>
                        <option value="<?= $sid ?>" <?= $sid === $staffId ? 'selected' : '' ?>>
                            <?= $e($s['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date range -->
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" class="form-control" name="from" value="<?= $e($from) ?>">
            </div>

            <!-- Apply button row -->
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" class="form-control" name="to" value="<?= $e($to) ?>">
            </div>

            <div class="col-md-12 d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Apply Filters
                </button>
                <?php if ($activeFilters > 0): ?>
                    <a href="/admin/complaints" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Clear All
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($activeFilters > 0): ?>
            <div class="mt-3 pt-3 border-top d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted small">Active filters:</span>
                <?php if ($search !== ''): ?>
                    <span class="badge bg-light text-dark border">Search: "<?= $e($search) ?>"</span>
                <?php endif; ?>
                <?php if ($type !== ''): ?>
                    <span class="badge bg-light text-dark border">Type: <?= $e($typeLabel($type)) ?></span>
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
                    <span class="badge bg-light text-dark border">Category: <?= $e($catName ?: '#' . $categoryId) ?></span>
                <?php endif; ?>
                <?php if ($staffId > 0):
                    $staffName = '';
                    foreach ($staffList as $s) {
                        if ((int) $s['staff_id'] === $staffId) {
                            $staffName = $s['full_name'];
                            break;
                        }
                    }
                ?>
                    <span class="badge bg-light text-dark border">Staff: <?= $e($staffName ?: '#' . $staffId) ?></span>
                <?php endif; ?>
                <?php if ($from !== ''): ?>
                    <span class="badge bg-light text-dark border">From: <?= $e($fmtDate($from)) ?></span>
                <?php endif; ?>
                <?php if ($to !== ''): ?>
                    <span class="badge bg-light text-dark border">To: <?= $e($fmtDate($to)) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== COMPLAINTS TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Complaint</th>
                    <th class="small text-muted fw-semibold">Customer</th>
                    <th class="small text-muted fw-semibold">Order</th>
                    <th class="small text-muted fw-semibold">Type</th>
                    <th class="small text-muted fw-semibold">Category</th>
                    <th class="small text-muted fw-semibold text-center">Assigned</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold">Filed</th>
                    <th class="small text-muted fw-semibold text-center">Refund</th>
                    <th class="small text-muted fw-semibold text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($complaints)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-emoji-smile fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No complaints found</div>
                                <div class="small">
                                    <?php if ($activeFilters > 0): ?>
                                        Try adjusting your filters ·
                                        <a href="/admin/complaints" class="text-decoration-none">Clear all</a>
                                    <?php else: ?>
                                        No complaints have been filed yet. 🎉
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($complaints as $c):
                    $cid        = (int) $c['complaint_id'];
                    $cNumber    = (string) $c['complaint_number'];
                    $cStatus    = (string) $c['status'];
                    $cType      = (string) $c['type'];
                    $cCategory  = (string) ($c['category_name'] ?? '');
                    $custName   = (string) $c['customer_name'];
                    $custEmail  = (string) $c['customer_email'];
                    $custPhone  = (string) $c['customer_phone'];
                    $orderNum   = (string) $c['order_number'];
                    $orderId    = (int) ($c['order_id'] ?? 0);
                    $staffName  = $c['assigned_staff_name'] ?? null;
                    $staffRowId = (int) ($c['assigned_staff_id'] ?? 0);
                    $refundCnt  = (int) ($c['refund_count'] ?? 0);
                    $createdAt  = $c['created_at'] ?? null;
                    $resolvedAt = $c['resolved_at'] ?? null;
                    $isUrgent   = $isPriority($cStatus);
                ?>
                    <tr class="<?= $cStatus === 'escalated' ? 'table-danger' : ($cStatus === 'open' ? 'table-warning' : '') ?>">
                        <!-- Complaint -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="fw-medium small">
                                    <a href="/admin/complaints/<?= $cid ?>" class="text-decoration-none">
                                        <?= $e($cNumber) ?>
                                    </a>
                                    <?php if ($isUrgent): ?>
                                        <i class="bi bi-exclamation-circle-fill text-danger ms-1"
                                           title="Urgent" style="font-size:.75rem;"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-muted" style="font-size:.7rem;">
                                #<?= $cid ?>
                            </div>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="small fw-medium text-truncate" style="max-width: 200px;">
                                <a href="/admin/customers/<?= (int) $c['customer_id'] ?>"
                                   class="text-decoration-none">
                                    <?= $e($custName) ?>
                                </a>
                            </div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <i class="bi bi-telephone"></i> <?= $e($custPhone) ?>
                            </div>
                        </td>

                        <!-- Order -->
                        <td>
                            <a href="/admin/reports/orders?highlight=<?= $orderId ?>"
                               class="badge bg-info-subtle text-info text-decoration-none">
                                <?= $e($orderNum) ?>
                            </a>
                        </td>

                        <!-- Type -->
                        <td>
                            <span class="badge bg-<?= $typeBadge($cType) ?>">
                                <?= $e($typeLabel($cType)) ?>
                            </span>
                        </td>

                        <!-- Category -->
                        <td class="small text-muted">
                            <?php if ($cCategory !== ''): ?>
                                <?= $e($cCategory) ?>
                            <?php else: ?>
                                <span class="fst-italic">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Assigned -->
                        <td class="text-center">
                            <?php if ($staffName): ?>
                                <span class="badge bg-info-subtle text-info">
                                    <i class="bi bi-person"></i>
                                    <?= $e($staffName) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning">Unassigned</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td class="text-center">
                            <span class="badge bg-<?= $statusBadge($cStatus) ?>">
                                <?= $e($statusLabel($cStatus)) ?>
                            </span>
                        </td>

                        <!-- Filed -->
                        <td class="small text-muted">
                            <div><?= $e($timeAgo($createdAt)) ?></div>
                            <?php if ($resolvedAt): ?>
                                <div style="font-size:.68rem;">
                                    <i class="bi bi-check-circle text-success"></i>
                                    <?= $e($timeAgo($resolvedAt)) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Refund -->
                        <td class="text-center">
                            <?php if ($refundCnt > 0): ?>
                                <a href="/admin/refunds?search=<?= urlencode($cNumber) ?>"
                                   class="badge bg-success-subtle text-success text-decoration-none"
                                   title="Refund requested">
                                    <i class="bi bi-cash-coin"></i> <?= $fmtInt($refundCnt) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="/admin/complaints/<?= $cid ?>"
                                   class="btn btn-outline-primary"
                                   title="View details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-outline-secondary dropdown-toggle-split"
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if (in_array($cStatus, ['open', 'escalated'], true)): ?>
                                        <li>
                                            <button type="button" class="dropdown-item"
                                                    onclick="openAssignModal(<?= $cid ?>, <?= json_encode($cNumber, $jsonFlags) ?>)">
                                                <i class="bi bi-person-plus me-2"></i>Assign
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (!in_array($cStatus, ['resolved', 'rejected', 'closed'], true)): ?>
                                        <li>
                                            <button type="button" class="dropdown-item text-success"
                                                    onclick="openResolveModal(<?= $cid ?>, <?= json_encode($cNumber, $jsonFlags) ?>)">
                                                <i class="bi bi-check-circle me-2"></i>Resolve
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item text-secondary"
                                                    onclick="openRejectModal(<?= $cid ?>, <?= json_encode($cNumber, $jsonFlags) ?>)">
                                                <i class="bi bi-x-circle me-2"></i>Reject
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (in_array($cStatus, ['resolved', 'rejected'], true)): ?>
                                        <li>
                                            <button type="button" class="dropdown-item"
                                                    onclick="openCloseModal(<?= $cid ?>, <?= json_encode($cNumber, $jsonFlags) ?>)">
                                                <i class="bi bi-lock me-2"></i>Close Complaint
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="/admin/refunds?search=<?= urlencode($cNumber) ?>">
                                            <i class="bi bi-arrow-counterclockwise me-2"></i>View Refunds
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="/admin/reports/orders?highlight=<?= $orderId ?>">
                                            <i class="bi bi-bag-check me-2"></i>View Order
                                        </a>
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
                        <a class="page-link" href="<?= $page <= 1 ? '#' : $e($qs(['page' => $page - 1])) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);

                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $e($qs(['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $e($qs(['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $e($qs(['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page >= $totalPages ? '#' : $e($qs(['page' => $page + 1])) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ==================== ASSIGN MODAL ==================== -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Assign <strong id="assignLabel"></strong> to a staff member:
                </p>
                <select class="form-select" id="assignStaffSelect">
                    <option value="">— Select staff —</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['staff_id'] ?>">
                            <?= $e($s['full_name']) ?>
                            (<?= $e(ucfirst($s['role'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="assignConfirm">
                    <i class="bi bi-person-plus me-1"></i>Assign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RESOLVE MODAL ==================== -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success">
                    <i class="bi bi-check-circle me-2"></i>Resolve Complaint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Resolve <strong id="resolveLabel"></strong>. The customer will be notified.
                </p>
                <label class="form-label small fw-medium">
                    Resolution Notes <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="resolveNotes" rows="4"
                          placeholder="Describe how the issue was resolved (min 10 characters)…"></textarea>
                <div class="invalid-feedback" id="resolveError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="resolveConfirm">
                    <i class="bi bi-check-circle me-1"></i>Resolve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REJECT MODAL ==================== -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-secondary">
                    <i class="bi bi-x-circle me-2"></i>Reject Complaint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reject <strong id="rejectLabel"></strong>. The customer will see the reason.
                </p>
                <label class="form-label small fw-medium">
                    Rejection Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="rejectReason" rows="4"
                          placeholder="Explain why this complaint is being rejected (min 10 characters)…"></textarea>
                <div class="invalid-feedback" id="rejectError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-secondary" id="rejectConfirm">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CLOSE MODAL ==================== -->
<div class="modal fade" id="closeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Close Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-0">
                    Close <strong id="closeLabel"></strong>?
                    <br><br>
                    <span class="text-muted small">
                        Closing a complaint marks it as fully handled. It cannot be reopened.
                    </span>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" id="closeConfirm">
                    <i class="bi bi-lock me-1"></i>Close Complaint
                </button>
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
        padding: .4rem .75rem;
        font-size: .88rem;
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
        padding: .8rem .65rem;
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

    // ==================== ASSIGN ====================
    const assignModal  = new bootstrap.Modal(document.getElementById('assignModal'));
    const assignLabel  = document.getElementById('assignLabel');
    const assignSelect = document.getElementById('assignStaffSelect');
    const assignConf   = document.getElementById('assignConfirm');
    let assignTargetId = 0;

    window.openAssignModal = function (id, label) {
        assignTargetId = id;
        assignLabel.textContent = label;
        assignSelect.value = '';
        assignModal.show();
    };

    assignConf?.addEventListener('click', async () => {
        const staffId = parseInt(assignSelect.value, 10);
        if (!staffId) {
            showToast('Please select a staff member.', 'danger');
            return;
        }
        assignConf.disabled = true;
        const orig = assignConf.innerHTML;
        assignConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Assigning…';

        try {
            const res = await fetch(`/admin/complaints/${assignTargetId}/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, staff_id: staffId }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Assigned.', 'success');
                assignModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                assignConf.disabled = false;
                assignConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            assignConf.disabled = false;
            assignConf.innerHTML = orig;
        }
    });

    // ==================== RESOLVE ====================
    const resolveModal  = new bootstrap.Modal(document.getElementById('resolveModal'));
    const resolveLabel  = document.getElementById('resolveLabel');
    const resolveNotes  = document.getElementById('resolveNotes');
    const resolveError  = document.getElementById('resolveError');
    const resolveConf   = document.getElementById('resolveConfirm');
    let resolveTargetId = 0;

    window.openResolveModal = function (id, label) {
        resolveTargetId = id;
        resolveLabel.textContent = label;
        resolveNotes.value = '';
        resolveError.style.display = 'none';
        resolveModal.show();
    };

    resolveConf?.addEventListener('click', async () => {
        const notes = resolveNotes.value.trim();
        if (notes.length < 10) {
            resolveError.textContent = 'Resolution notes must be at least 10 characters.';
            resolveError.style.display = 'block';
            return;
        }
        resolveConf.disabled = true;
        const orig = resolveConf.innerHTML;
        resolveConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Resolving…';

        try {
            const res = await fetch(`/admin/complaints/${resolveTargetId}/resolve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, resolution_notes: notes }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Complaint resolved.', 'success');
                resolveModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                resolveConf.disabled = false;
                resolveConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            resolveConf.disabled = false;
            resolveConf.innerHTML = orig;
        }
    });

    // ==================== REJECT ====================
    const rejectModal  = new bootstrap.Modal(document.getElementById('rejectModal'));
    const rejectLabel  = document.getElementById('rejectLabel');
    const rejectReason = document.getElementById('rejectReason');
    const rejectError  = document.getElementById('rejectError');
    const rejectConf   = document.getElementById('rejectConfirm');
    let rejectTargetId = 0;

    window.openRejectModal = function (id, label) {
        rejectTargetId = id;
        rejectLabel.textContent = label;
        rejectReason.value = '';
        rejectError.style.display = 'none';
        rejectModal.show();
    };

    rejectConf?.addEventListener('click', async () => {
        const reason = rejectReason.value.trim();
        if (reason.length < 10) {
            rejectError.textContent = 'Rejection reason must be at least 10 characters.';
            rejectError.style.display = 'block';
            return;
        }
        rejectConf.disabled = true;
        const orig = rejectConf.innerHTML;
        rejectConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rejecting…';

        try {
            const res = await fetch(`/admin/complaints/${rejectTargetId}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf, reason: reason }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Complaint rejected.', 'success');
                rejectModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                rejectConf.disabled = false;
                rejectConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            rejectConf.disabled = false;
            rejectConf.innerHTML = orig;
        }
    });

    // ==================== CLOSE ====================
    const closeModal  = new bootstrap.Modal(document.getElementById('closeModal'));
    const closeLabel  = document.getElementById('closeLabel');
    const closeConf   = document.getElementById('closeConfirm');
    let closeTargetId = 0;

    window.openCloseModal = function (id, label) {
        closeTargetId = id;
        closeLabel.textContent = label;
        closeModal.show();
    };

    closeConf?.addEventListener('click', async () => {
        closeConf.disabled = true;
        const orig = closeConf.innerHTML;
        closeConf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Closing…';

        try {
            const res = await fetch(`/admin/complaints/${closeTargetId}/close`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({ _csrf: csrf }).toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Complaint closed.', 'success');
                closeModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                closeConf.disabled = false;
                closeConf.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            closeConf.disabled = false;
            closeConf.innerHTML = orig;
        }
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
        })[c]);
    }
})();
</script>
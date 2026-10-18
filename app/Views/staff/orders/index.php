<?php
/**
 * Staff Orders — List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\Staff\OrderController::index()
 *
 * Expected variables:
 *   $orders       array   paginated order rows
 *                         each: order_id, order_number, order_status,
 *                               order_date, estimated_delivery_date, delivery_date,
 *                               assigned_staff_id, customer_id, customer_name,
 *                               customer_phone, assigned_staff_name,
 *                               item_count, total_quantity
 *   $stats        array   ['received','washing','ironing','ready','active','delivered_today']
 *   $filter       string  'active' | 'washing' | 'ironing' | 'ready' | 'all' | any enum value
 *   $search       string
 *   $date         string  Y-m-d
 *   $statusLabels array   status => label
 * ----------------------------------------------------------
 */

$currentPage = 'staff-orders';
$title       = 'Orders';

// Safe defaults
$orders       = $orders       ?? [];
$stats        = $stats        ?? ['received'=>0, 'washing'=>0, 'ironing'=>0, 'ready'=>0, 'active'=>0, 'delivered_today'=>0];
$filter       = $filter       ?? 'active';
$search       = $search       ?? '';
$date         = $date         ?? '';
$statusLabels = $statusLabels ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateShort = fn($d) => $d ? date('M j', strtotime($d)) : '—';

// Status badge + label helpers
$statusBadge = function (string $s): string {
    return match ($s) {
        'pickup_requested'    => 'secondary',
        'pickup_assigned'     => 'info',
        'picked_up'           => 'primary',
        'received_at_laundry' => 'primary',
        'washing'             => 'warning',
        'ironing'             => 'warning',
        'ready'               => 'success',
        'out_for_delivery'    => 'info',
        'delivered'           => 'success',
        'cancelled'           => 'danger',
        default               => 'secondary',
    };
};
$statusLabel = function (string $s) use ($statusLabels): string {
    return $statusLabels[$s] ?? ucwords(str_replace('_', ' ', $s));
};

// Workflow tab definitions (order matters — most urgent first)
$tabs = [
    'active'  => ['label' => 'Active',    'count' => $stats['active']   ?? 0],
    'washing' => ['label' => 'Washing',   'count' => $stats['washing']  ?? 0],
    'ironing' => ['label' => 'Ironing',   'count' => $stats['ironing']  ?? 0],
    'ready'   => ['label' => 'Ready',     'count' => $stats['ready']    ?? 0],
    'all'     => ['label' => 'All',       'count' => null],
];

// Build querystring (preserve other filters)
$qs = function (array $overrides = []) use ($filter, $search, $date): string {
    $params = array_filter([
        'status' => $overrides['status'] ?? ($filter !== 'active' ? $filter : null),
        'search' => $overrides['search'] ?? ($search ?: null),
        'date'   => array_key_exists('date', $overrides) ? $overrides['date'] : ($date ?: null),
    ], fn($v) => $v !== null && $v !== '');

    return '?' . http_build_query($params);
};

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Orders</h1>
        <p class="text-muted mb-0 small">
            Process orders through the laundry pipeline
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/staff/orders?status=all" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i>View All
        </a>
    </div>
</div>

<!-- ==================== STAGE STATS (5 cards) ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-house-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Active</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['active'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            in pipeline
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
                        <i class="bi bi-droplet"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Washing</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['washing'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            in the washer
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
                        <i class="bi bi-thermometer-half"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Ironing</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['ironing'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            being pressed
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
                        <div class="text-muted small text-uppercase fw-semibold">Ready</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['ready'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            awaiting delivery
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== DELIVERED TODAY BANNER ==================== -->
<?php if (($stats['delivered_today'] ?? 0) > 0): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4 py-2">
        <i class="bi bi-check2-all fs-5"></i>
        <div class="flex-grow-1 small">
            <strong><?= $fmtInt($stats['delivered_today']) ?></strong>
            order<?= $stats['delivered_today'] === 1 ? '' : 's' ?> delivered today
        </div>
    </div>
<?php endif; ?>

<!-- ==================== WORKFLOW TABS ==================== -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body pb-0">
        <ul class="nav nav-tabs" role="tablist">
            <?php foreach ($tabs as $slug => $tab):
                $isActive = ($filter === $slug);
                $count = $tab['count'];
            ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive ? 'active' : '' ?>"
                       href="<?= $e($qs(['status' => $slug === 'active' ? null : $slug])) ?>">
                        <?= $e($tab['label']) ?>
                        <?php if ($count !== null && $count > 0): ?>
                            <span class="badge bg-<?= $slug === 'ready' ? 'success' : ($slug === 'washing' || $slug === 'ironing' ? 'warning' : 'primary') ?> ms-1">
                                <?= $fmtInt($count) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Search + date filter -->
    <div class="card-body border-bottom py-2">
        <form method="get" action="/staff/orders" class="row g-2 align-items-center">
            <input type="hidden" name="status" value="<?= $e($filter) ?>">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text"
                           class="form-control form-control-sm"
                           name="search"
                           value="<?= $e($search) ?>"
                           placeholder="Search by order #, customer name, or phone…">
                </div>
            </div>
            <div class="col-md-3">
                <input type="date"
                       class="form-control form-control-sm"
                       name="date"
                       value="<?= $e($date) ?>"
                       max="<?= $e(date('Y-m-d')) ?>"
                       placeholder="Filter by order date">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($search !== '' || $date !== ''): ?>
                    <a href="/staff/orders?status=<?= $e($filter) ?>"
                       class="btn btn-outline-secondary btn-sm"
                       title="Clear filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ==================== ORDERS TABLE ==================== -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Order</th>
                    <th class="small text-muted fw-semibold">Customer</th>
                    <th class="small text-muted fw-semibold text-center">Items</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold">Ordered</th>
                    <th class="small text-muted fw-semibold">Due</th>
                    <th class="small text-muted fw-semibold text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-box-seam fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">
                                    <?php if ($filter === 'active'): ?>
                                        No active orders in the pipeline
                                    <?php elseif ($filter === 'all'): ?>
                                        No orders found
                                    <?php else: ?>
                                        No orders in "<?= $e(ucfirst(str_replace('_', ' ', $filter))) ?>"
                                    <?php endif; ?>
                                </div>
                                <div class="small">
                                    <?php if ($search !== '' || $date !== ''): ?>
                                        Try adjusting your filters ·
                                        <a href="/staff/orders?status=<?= $e($filter) ?>" class="text-decoration-none">Clear</a>
                                    <?php else: ?>
                                        New orders will appear here.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($orders as $o):
                    $oid       = (int) $o['order_id'];
                    $orderNum  = (string) $o['order_number'];
                    $oStatus   = (string) $o['order_status'];
                    $orderDate = $o['order_date'] ?? null;
                    $estDate   = $o['estimated_delivery_date'] ?? $o['delivery_date'] ?? null;
                    $custId    = (int) $o['customer_id'];
                    $custName  = (string) $o['customer_name'];
                    $custPhone = (string) $o['customer_phone'];
                    $staffName = $o['assigned_staff_name'] ?? null;
                    $itemCnt   = (int) ($o['item_count']     ?? 0);
                    $totalQty  = (int) ($o['total_quantity'] ?? 0);

                    // Due indicator
                    $dueLabel = '—';
                    $dueClass = 'text-muted';
                    if ($estDate) {
                        $dueDays = (int) round((strtotime($estDate) - strtotime(date('Y-m-d'))) / 86400);
                        if ($dueDays < 0) {
                            $dueLabel = abs($dueDays) . 'd overdue';
                            $dueClass = 'text-danger fw-medium';
                        } elseif ($dueDays === 0) {
                            $dueLabel = 'Today';
                            $dueClass = 'text-warning fw-medium';
                        } elseif ($dueDays === 1) {
                            $dueLabel = 'Tomorrow';
                            $dueClass = 'text-muted';
                        } else {
                            $dueLabel = 'In ' . $dueDays . 'd';
                            $dueClass = 'text-muted';
                        }
                    }

                    // Actions: can advance status?
                    $nextStatuses = [];
                    switch ($oStatus) {
                        case 'picked_up':           $nextStatuses = ['received_at_laundry', 'cancelled']; break;
                        case 'received_at_laundry': $nextStatuses = ['washing', 'cancelled'];              break;
                        case 'washing':             $nextStatuses = ['ironing', 'ready', 'cancelled'];     break;
                        case 'ironing':             $nextStatuses = ['ready', 'cancelled'];                break;
                        case 'ready':               $nextStatuses = ['out_for_delivery', 'cancelled'];     break;
                        case 'out_for_delivery':    $nextStatuses = ['delivered', 'cancelled'];            break;
                        default:                    $nextStatuses = [];
                    }
                    $canProgress = !empty($nextStatuses);
                    $primaryNext = $nextStatuses[0] ?? null;
                ?>
                    <tr>
                        <!-- Order -->
                        <td>
                            <div class="fw-medium small">
                                <a href="/staff/orders/<?= $oid ?>"
                                   class="text-decoration-none">
                                    <?= $e($orderNum) ?>
                                </a>
                            </div>
                            <div class="text-muted" style="font-size:.7rem;">
                                #<?= $oid ?>
                                <?php if ($staffName): ?>
                                    · <?= $e($staffName) ?>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="small fw-medium text-truncate"
                                 style="max-width: 200px;">
                                <?= $e($custName) ?>
                            </div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <i class="bi bi-telephone"></i>
                                <?= $e($custPhone) ?>
                            </div>
                        </td>

                        <!-- Items -->
                        <td class="text-center small">
                            <span class="badge bg-primary-subtle text-primary">
                                <?= $fmtInt($totalQty) ?>
                            </span>
                            <div class="text-muted" style="font-size:.68rem;">
                                <?= $fmtInt($itemCnt) ?> type<?= $itemCnt === 1 ? '' : 's' ?>
                            </div>
                        </td>

                        <!-- Status -->
                        <td class="text-center">
                            <span class="badge bg-<?= $statusBadge($oStatus) ?>">
                                <?= $e($statusLabel($oStatus)) ?>
                            </span>
                        </td>

                        <!-- Ordered date -->
                        <td class="small text-muted">
                            <?= $e($fmtDateShort($orderDate)) ?>
                        </td>

                        <!-- Due date -->
                        <td>
                            <div class="small <?= $e($dueClass) ?>">
                                <?= $e($dueLabel) ?>
                            </div>
                            <?php if ($estDate): ?>
                                <div class="text-muted" style="font-size:.68rem;">
                                    <?= $e($fmtDateShort($estDate)) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="/staff/orders/<?= $oid ?>"
                                   class="btn btn-outline-primary"
                                   title="View order">
                                    <i class="bi bi-eye"></i>
                                </a>

                                <?php if ($canProgress && $primaryNext): ?>
                                    <button type="button"
                                            class="btn btn-outline-success"
                                            title="Advance to <?= $e($statusLabel($primaryNext)) ?>"
                                            onclick="quickAdvance(<?= $oid ?>, <?= json_encode($primaryNext) ?>, <?= json_encode($statusLabel($primaryNext), $jsonFlags) ?>, <?= json_encode($orderNum, $jsonFlags) ?>)">
                                        <i class="bi bi-arrow-right-circle"></i>
                                    </button>
                                <?php endif; ?>

                                <button type="button"
                                        class="btn btn-outline-secondary dropdown-toggle-split"
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($canProgress): ?>
                                        <li class="dropdown-header small text-muted text-uppercase"
                                            style="font-size:.68rem;">
                                            Advance to
                                        </li>
                                        <?php foreach ($nextStatuses as $next):
                                            if ($next === 'cancelled') continue;
                                        ?>
                                            <li>
                                                <button type="button"
                                                        class="dropdown-item"
                                                        onclick="quickAdvance(<?= $oid ?>, <?= json_encode($next) ?>, <?= json_encode($statusLabel($next), $jsonFlags) ?>, <?= json_encode($orderNum, $jsonFlags) ?>)">
                                                    <i class="bi bi-arrow-right me-2 text-success"></i>
                                                    <?= $e($statusLabel($next)) ?>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item"
                                                onclick="openNoteModal(<?= $oid ?>, <?= json_encode($orderNum, $jsonFlags) ?>)">
                                            <i class="bi bi-sticky me-2"></i>Add Note
                                        </button>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="tel:<?= $e($custPhone) ?>">
                                            <i class="bi bi-telephone me-2"></i>Call Customer
                                        </a>
                                    </li>
                                    <?php if (in_array('cancelled', $nextStatuses, true)): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button"
                                                    class="dropdown-item text-danger"
                                                    onclick="openCancelModal(<?= $oid ?>, <?= json_encode($orderNum, $jsonFlags) ?>)">
                                                <i class="bi bi-x-circle me-2"></i>Cancel Order
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== QUICK ADVANCE MODAL ==================== -->
<div class="modal fade" id="advanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="advanceModalTitle">Advance Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3 small">
                    Advance <strong id="advanceOrderNum"></strong> to
                    <strong class="text-success" id="advanceNextLabel"></strong>?
                </p>
                <label class="form-label small fw-medium">
                    Notes <span class="text-muted fw-normal">(optional)</span>
                </label>
                <textarea class="form-control" id="advanceNote" rows="2"
                          placeholder="Any notes for this status change…"
                          maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="advanceConfirm">
                    <i class="bi bi-arrow-right me-1"></i>Advance
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== NOTE MODAL ==================== -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Add a note to <strong id="noteOrderNum"></strong>
                </p>
                <textarea class="form-control" id="noteText" rows="3"
                          placeholder="Enter your note (min 3 characters)…"
                          maxlength="2000"></textarea>
                <div class="invalid-feedback" id="noteError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="noteConfirm">
                    <i class="bi bi-sticky me-1"></i>Save Note
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== CANCEL MODAL ==================== -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-x-circle me-2"></i>Cancel Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>This action cannot be undone.</strong>
                </div>
                <p class="small mb-3">
                    Cancel order <strong id="cancelOrderNum"></strong>?
                </p>
                <label class="form-label small fw-medium">
                    Reason <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="cancelReason" rows="3"
                          placeholder="Reason for cancelling (min 5 characters)…"
                          maxlength="2000"></textarea>
                <div class="invalid-feedback" id="cancelError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="cancelConfirm">
                    <i class="bi bi-x-circle me-1"></i>Cancel Order
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
    .nav-tabs .nav-link {
        color: #475569;
        border: 1px solid transparent;
        border-bottom: none;
        padding: .55rem .9rem;
        font-size: .9rem;
    }
    .nav-tabs .nav-link.active {
        background: #fff;
        color: #0ea5e9;
        font-weight: 500;
        border-color: #e2e8f0 #e2e8f0 #fff;
    }
    .nav-tabs .nav-link:hover:not(.active) {
        background: #f8fafc;
    }
    .table > :not(caption) > * > * {
        padding: .8rem .75rem;
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

    // ==================== ADVANCE ====================
    const advanceModal       = new bootstrap.Modal(document.getElementById('advanceModal'));
    const advanceModalTitle  = document.getElementById('advanceModalTitle');
    const advanceOrderNum    = document.getElementById('advanceOrderNum');
    const advanceNextLabel   = document.getElementById('advanceNextLabel');
    const advanceNote        = document.getElementById('advanceNote');
    const advanceConfirm     = document.getElementById('advanceConfirm');
    let advanceTarget = { id: 0, status: '' };

    window.quickAdvance = function (orderId, newStatus, statusLabel, orderNum) {
        advanceTarget = { id: orderId, status: newStatus };
        advanceModalTitle.textContent = 'Advance Order';
        advanceOrderNum.textContent = orderNum;
        advanceNextLabel.textContent = statusLabel;
        advanceNote.value = '';
        advanceModal.show();
    };

    advanceConfirm?.addEventListener('click', async () => {
        if (!advanceTarget.id) return;

        advanceConfirm.disabled = true;
        const orig = advanceConfirm.innerHTML;
        advanceConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Advancing…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                status: advanceTarget.status,
                note: advanceNote.value.trim(),
            });

            const res = await fetch(`/staff/orders/${advanceTarget.id}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Status updated.', 'success');
                advanceModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                advanceConfirm.disabled = false;
                advanceConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            advanceConfirm.disabled = false;
            advanceConfirm.innerHTML = orig;
        }
    });

    // ==================== NOTE ====================
    const noteModal    = new bootstrap.Modal(document.getElementById('noteModal'));
    const noteOrderNum = document.getElementById('noteOrderNum');
    const noteText     = document.getElementById('noteText');
    const noteError    = document.getElementById('noteError');
    const noteConfirm  = document.getElementById('noteConfirm');
    let noteTargetId = 0;

    window.openNoteModal = function (orderId, orderNum) {
        noteTargetId = orderId;
        noteOrderNum.textContent = orderNum;
        noteText.value = '';
        noteError.style.display = 'none';
        noteModal.show();
    };

    noteConfirm?.addEventListener('click', async () => {
        const note = noteText.value.trim();
        if (note.length < 3) {
            noteError.textContent = 'Note must be at least 3 characters.';
            noteError.style.display = 'block';
            return;
        }

        noteConfirm.disabled = true;
        const orig = noteConfirm.innerHTML;
        noteConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, note: note });

            const res = await fetch(`/staff/orders/${noteTargetId}/note`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Note saved.', 'success');
                noteModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                noteConfirm.disabled = false;
                noteConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            noteConfirm.disabled = false;
            noteConfirm.innerHTML = orig;
        }
    });

    // ==================== CANCEL ====================
    const cancelModal    = new bootstrap.Modal(document.getElementById('cancelModal'));
    const cancelOrderNum = document.getElementById('cancelOrderNum');
    const cancelReason   = document.getElementById('cancelReason');
    const cancelError    = document.getElementById('cancelError');
    const cancelConfirm  = document.getElementById('cancelConfirm');
    let cancelTargetId = 0;

    window.openCancelModal = function (orderId, orderNum) {
        cancelTargetId = orderId;
        cancelOrderNum.textContent = orderNum;
        cancelReason.value = '';
        cancelError.style.display = 'none';
        cancelModal.show();
    };

    cancelConfirm?.addEventListener('click', async () => {
        const reason = cancelReason.value.trim();
        if (reason.length < 5) {
            cancelError.textContent = 'Reason must be at least 5 characters.';
            cancelError.style.display = 'block';
            return;
        }

        cancelConfirm.disabled = true;
        const orig = cancelConfirm.innerHTML;
        cancelConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Cancelling…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, status: 'cancelled', note: reason });

            const res = await fetch(`/staff/orders/${cancelTargetId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Order cancelled.', 'success');
                cancelModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                cancelConfirm.disabled = false;
                cancelConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            cancelConfirm.disabled = false;
            cancelConfirm.innerHTML = orig;
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
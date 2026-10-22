<?php
/**
 * Staff Deliveries — List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: App\Controllers\Staff\DeliveryController::index()
 *
 * Expected variables:
 *   $deliveries  array   paginated delivery rows with order/customer/slot/staff joins
 *   $stats       array   ['today_pending','today_completed','upcoming','today_failed']
 *   $filter      string  'active' | 'today' | 'all' | 'scheduled' | 'out_for_delivery' | 'delivered' | 'failed' | 'rescheduled'
 * ----------------------------------------------------------
 */

$currentPage = 'staff-deliveries';
$title       = 'My Deliveries';

// Safe defaults
$deliveries = $deliveries ?? [];
$stats      = $stats      ?? ['today_pending'=>0, 'today_completed'=>0, 'upcoming'=>0, 'today_failed'=>0];
$filter     = $filter     ?? 'active';

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateShort = fn($d) => $d ? date('M j', strtotime($d)) : '—';
$fmtTime  = fn($t) => $t ? substr($t, 0, 5) : '';
$fmtDateTime = fn($d) => $d ? date('M j, g:i A', strtotime($d)) : '—';

// Status badges
$statusBadge = function (string $s): string {
    return match ($s) {
        'scheduled'        => 'warning',
        'out_for_delivery' => 'info',
        'delivered'        => 'success',
        'failed'           => 'danger',
        'rescheduled'      => 'secondary',
        default            => 'secondary',
    };
};
$statusLabel = function (string $s): string {
    return match ($s) {
        'scheduled'        => 'Scheduled',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'failed'           => 'Failed',
        'rescheduled'      => 'Rescheduled',
        default            => ucwords(str_replace('_', ' ', $s)),
    };
};

// Order status badge (compact)
$orderBadge = function (string $s): string {
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

// Overdue detection
$isOverdue = function (array $d): bool {
    $status = (string) ($d['status'] ?? '');
    if (in_array($status, ['delivered', 'failed', 'rescheduled'], true)) return false;

    $date    = (string) ($d['delivery_date'] ?? '');
    $endTime = (string) ($d['end_time']      ?? '');
    $today   = date('Y-m-d');

    if ($date < $today) return true;
    if ($date === $today && $endTime && strtotime($today . ' ' . $endTime) < time()) {
        return true;
    }
    return false;
};

// Tabs
$tabs = [
    'active'           => ['label' => 'Active',  'count' => ($stats['today_pending'] ?? 0) + ($stats['upcoming'] ?? 0), 'icon' => 'bi-list-check'],
    'today'            => ['label' => 'Today',   'count' => ($stats['today_pending'] ?? 0) + ($stats['today_completed'] ?? 0), 'icon' => 'bi-calendar-day'],
    'out_for_delivery' => ['label' => 'Out',     'count' => null, 'icon' => 'bi-truck'],
    'scheduled'        => ['label' => 'Scheduled','count' => null, 'icon' => 'bi-clock-history'],
    'all'              => ['label' => 'All',     'count' => null, 'icon' => 'bi-list-ul'],
];

// Build querystring
$qs = function (string $status): string {
    if ($status === 'active') return '/staff/deliveries';
    return '/staff/deliveries?status=' . urlencode($status);
};

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">My Deliveries</h1>
        <p class="text-muted mb-0 small">
            Deliver completed orders to customers
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/staff/deliveries?status=all" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i>View All
        </a>
    </div>
</div>

<!-- ==================== KPI CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pending Today</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['today_pending'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            awaiting delivery
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
                        <div class="text-muted small text-uppercase fw-semibold">Delivered Today</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['today_completed'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            successfully delivered
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
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Upcoming</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['upcoming'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            future dates
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
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Failed Today</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['today_failed'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            could not deliver
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FILTER TABS ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-body pb-0">
        <ul class="nav nav-tabs">
            <?php foreach ($tabs as $slug => $tab):
                $isActive = ($filter === $slug);
                $count = $tab['count'];
            ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive ? 'active' : '' ?>"
                       href="<?= $e($qs($slug)) ?>">
                        <i class="bi <?= $e($tab['icon']) ?> me-1"></i>
                        <?= $e($tab['label']) ?>
                        <?php if ($count !== null && $count > 0): ?>
                            <span class="badge bg-primary-subtle text-primary ms-1">
                                <?= $fmtInt($count) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ==================== DELIVERIES LIST ==================== -->
    <div class="card-body p-0">
        <?php if (empty($deliveries)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-truck fs-1 d-block mb-3 opacity-50"></i>
                <div class="fw-medium">
                    <?php if ($filter === 'active'): ?>
                        You have no active deliveries
                    <?php elseif ($filter === 'today'): ?>
                        No deliveries scheduled for today
                    <?php elseif ($filter === 'scheduled'): ?>
                        No scheduled deliveries
                    <?php elseif ($filter === 'out_for_delivery'): ?>
                        No deliveries currently out for delivery
                    <?php else: ?>
                        No deliveries found
                    <?php endif; ?>
                </div>
                <div class="small mt-2">
                    Deliveries will appear here when orders are ready for drop-off.
                </div>
            </div>
        <?php else: ?>
            <div class="deliveries-list">
                <?php foreach ($deliveries as $d):
                    $did          = (int) $d['delivery_id'];
                    $dStatus      = (string) $d['status'];
                    $dDate        = (string) $d['delivery_date'];
                    $dSlotStart   = (string) ($d['start_time'] ?? '');
                    $dSlotEnd     = (string) ($d['end_time']   ?? '');
                    $orderId      = (int) ($d['order_id'] ?? 0);
                    $orderNum     = (string) ($d['order_number'] ?? '');
                    $orderStatus  = (string) ($d['order_status'] ?? '');
                    $custName     = (string) ($d['customer_name']  ?? '');
                    $custPhone    = (string) ($d['customer_phone'] ?? '');
                    $area         = (string) ($d['address_area'] ?? '');
                    $city         = (string) ($d['city'] ?? '');
                    $house        = (string) ($d['address_house']  ?? '');
                    $street       = (string) ($d['address_street'] ?? '');
                    $landmark     = (string) ($d['landmark'] ?? '');
                    $failureReason = (string) ($d['failure_reason'] ?? '');
                    $deliveredAt  = $d['delivered_at'] ?? null;
                    $isToday      = ($dDate === date('Y-m-d'));
                    $isTomorrow   = ($dDate === date('Y-m-d', strtotime('+1 day')));
                    $overdue      = $isOverdue($d);

                    // Actions per status
                    $canStart    = ($dStatus === 'scheduled');
                    $canDeliver  = in_array($dStatus, ['scheduled', 'out_for_delivery'], true);
                    $canFail     = in_array($dStatus, ['scheduled', 'out_for_delivery'], true);
                    $canReschedule = in_array($dStatus, ['scheduled', 'out_for_delivery', 'failed', 'rescheduled'], true);
                    $isTerminal  = in_array($dStatus, ['delivered', 'failed'], true) === false && $dStatus === 'delivered';
                ?>
                    <div class="delivery-card <?= $overdue ? 'delivery-overdue' : '' ?> <?= $isToday ? 'delivery-today' : '' ?>">
                        <div class="row g-3 align-items-center">
                            <!-- Left: date -->
                            <div class="col-auto">
                                <div class="delivery-date-block">
                                    <div class="delivery-date-month">
                                        <?= $e(date('M', strtotime($dDate))) ?>
                                    </div>
                                    <div class="delivery-date-day">
                                        <?= $e(date('d', strtotime($dDate))) ?>
                                    </div>
                                    <div class="delivery-date-weekday">
                                        <?= $e(date('D', strtotime($dDate))) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Middle: details -->
                            <div class="col">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <a href="/staff/deliveries/<?= $did ?>"
                                       class="fw-semibold text-decoration-none">
                                        <?= $e($orderNum) ?>
                                    </a>
                                    <span class="badge bg-<?= $statusBadge($dStatus) ?>">
                                        <?= $e($statusLabel($dStatus)) ?>
                                    </span>
                                    <?php if ($overdue): ?>
                                        <span class="badge bg-danger" style="font-size:.65rem;">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Overdue
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isToday): ?>
                                        <span class="badge bg-primary" style="font-size:.65rem;">Today</span>
                                    <?php elseif ($isTomorrow): ?>
                                        <span class="badge bg-info" style="font-size:.65rem;">Tomorrow</span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex flex-wrap gap-3 small text-muted mb-2">
                                    <span>
                                        <i class="bi bi-clock me-1"></i>
                                        <?= $e($fmtTime($dSlotStart)) ?>–<?= $e($fmtTime($dSlotEnd)) ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-person me-1"></i>
                                        <?= $e($custName) ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-telephone me-1"></i>
                                        <a href="tel:<?= $e($custPhone) ?>"
                                           class="text-decoration-none text-muted">
                                            <?= $e($custPhone) ?>
                                        </a>
                                    </span>
                                </div>

                                <div class="small">
                                    <i class="bi bi-geo-alt text-muted me-1"></i>
                                    <?= $e($house) ?>, <?= $e($street) ?>,
                                    <?= $e($area) ?>, <?= $e($city) ?>
                                    <?php if ($landmark !== ''): ?>
                                        <span class="text-muted">(near <?= $e($landmark) ?>)</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($failureReason !== '' && $dStatus === 'failed'): ?>
                                    <div class="alert alert-danger small mt-2 mb-0 py-2">
                                        <i class="bi bi-x-circle me-1"></i>
                                        <strong>Failed:</strong> <?= $e($failureReason) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($deliveredAt && $dStatus === 'delivered'): ?>
                                    <div class="small text-success mt-2">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Delivered <?= $e($fmtDateTime($deliveredAt)) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($orderStatus): ?>
                                    <div class="small mt-2">
                                        <span class="text-muted">Order status:</span>
                                        <span class="badge bg-<?= $orderBadge($orderStatus) ?>" style="font-size:.7rem;">
                                            <?= $e(ucwords(str_replace('_', ' ', $orderStatus))) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Right: actions -->
                            <div class="col-md-auto">
                                <div class="d-flex flex-md-column gap-2">
                                    <?php if ($dStatus === 'scheduled'): ?>
                                        <button type="button"
                                                class="btn btn-info btn-sm text-white"
                                                onclick="openOutForDeliveryModal(<?= $did ?>, <?= json_encode($orderNum, $jsonFlags) ?>, <?= json_encode($custName, $jsonFlags) ?>)">
                                            <i class="bi bi-truck me-1"></i>Start Delivery
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($dStatus === 'out_for_delivery'): ?>
                                        <button type="button"
                                                class="btn btn-success btn-sm"
                                                onclick="openDeliveredModal(<?= $did ?>, <?= json_encode($orderNum, $jsonFlags) ?>, <?= json_encode($custName, $jsonFlags) ?>)">
                                            <i class="bi bi-check-lg me-1"></i>Mark Delivered
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canFail): ?>
                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="openFailedModal(<?= $did ?>, <?= json_encode($orderNum, $jsonFlags) ?>)">
                                            <i class="bi bi-x-circle me-1"></i>Failed
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canReschedule): ?>
                                        <button type="button"
                                                class="btn btn-outline-warning btn-sm"
                                                onclick="openRescheduleModal(<?= $did ?>, <?= json_encode($orderNum, $jsonFlags) ?>, <?= json_encode($dDate) ?>)">
                                            <i class="bi bi-calendar-event me-1"></i>Reschedule
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($dStatus, ['delivered', 'failed'], true)): ?>
                                        <a href="/staff/deliveries/<?= $did ?>"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== OUT FOR DELIVERY MODAL ==================== -->
<div class="modal fade" id="outForDeliveryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-truck me-2"></i>Start Delivery
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Start delivery for order <strong id="outOrderNum"></strong>
                    to <strong id="outCustomer"></strong>?
                </p>
                <div class="alert alert-info small mb-0 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    This marks the delivery as <strong>Out for Delivery</strong>. The customer will be notified.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info text-white" id="outConfirm">
                    <i class="bi bi-truck me-1"></i>Start Delivery
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== DELIVERED MODAL ==================== -->
<div class="modal fade" id="deliveredModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle me-2"></i>Confirm Delivery
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Mark order <strong id="deliveredOrderNum"></strong> as delivered to
                    <strong id="deliveredCustomer"></strong>?
                </p>
                <div class="alert alert-success small mb-3 py-2">
                    <i class="bi bi-check-circle me-1"></i>
                    This will finalize the order and mark it as <strong>Delivered</strong>.
                </div>
                <label class="form-label small fw-medium">
                    Delivery Notes <span class="text-muted fw-normal">(optional)</span>
                </label>
                <textarea class="form-control" id="deliveredNotes" rows="2"
                          placeholder="Number of bags delivered, recipient name, etc…"
                          maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="deliveredConfirm">
                    <i class="bi bi-check-lg me-1"></i>Confirm Delivered
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FAILED MODAL ==================== -->
<div class="modal fade" id="failedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-x-circle me-2"></i>Mark Delivery as Failed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3 py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    The delivery will be marked as failed. The order remains active.
                </div>
                <p class="small mb-3">
                    Failed delivery for order <strong id="failedOrderNum"></strong>
                </p>
                <label class="form-label small fw-medium">
                    Reason <span class="text-danger">*</span>
                </label>
                <select class="form-select form-select-sm mb-2" id="failedReasonSelect">
                    <option value="">— Select a reason —</option>
                    <option value="Customer not available">Customer not available</option>
                    <option value="Address not found">Address not found</option>
                    <option value="Customer refused delivery">Customer refused delivery</option>
                    <option value="Payment not ready">Payment not ready</option>
                    <option value="Access denied">Access denied</option>
                    <option value="other">Other (specify below)</option>
                </select>
                <textarea class="form-control" id="failedReason" rows="2"
                          placeholder="Additional details (required if 'Other')…"
                          maxlength="500"></textarea>
                <div class="invalid-feedback" id="failedError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="failedConfirm">
                    <i class="bi bi-x-circle me-1"></i>Mark Failed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RESCHEDULE MODAL ==================== -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-event me-2"></i>Reschedule Delivery
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Reschedule delivery for order <strong id="rescheduleOrderNum"></strong>:
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">New Date</label>
                    <input type="date" class="form-control" id="rescheduleDate"
                           min="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-medium">New Slot</label>
                    <select class="form-select" id="rescheduleSlot">
                        <option value="">— Loading slots… —</option>
                    </select>
                    <div class="form-text small">
                        Delivery slots are available every day.
                    </div>
                </div>
                <div class="invalid-feedback d-block" id="rescheduleError" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="rescheduleConfirm">
                    <i class="bi bi-calendar-check me-1"></i>Reschedule
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

    /* Delivery card */
    .delivery-card {
        padding: 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background-color .12s;
    }
    .delivery-card:last-child {
        border-bottom: none;
    }
    .delivery-card:hover {
        background: #f8fafc;
    }
    .delivery-card.delivery-today {
        border-left: 3px solid #0ea5e9;
    }
    .delivery-card.delivery-overdue {
        background: #fef2f2;
        border-left: 3px solid #ef4444;
    }
    .delivery-card.delivery-overdue:hover {
        background: #fee2e2;
    }

    /* Date block */
    .delivery-date-block {
        width: 68px;
        padding: .5rem 0;
        background: #f8fafc;
        border-radius: 10px;
        text-align: center;
        border: 1px solid #e2e8f0;
    }
    .delivery-date-month {
        font-size: .7rem;
        text-transform: uppercase;
        color: #94a3b8;
        font-weight: 600;
        letter-spacing: .05em;
    }
    .delivery-date-day {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1;
        margin: .1rem 0;
    }
    .delivery-date-weekday {
        font-size: .7rem;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 500;
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

    // ==================== OUT FOR DELIVERY ====================
    const outModal    = new bootstrap.Modal(document.getElementById('outForDeliveryModal'));
    const outOrderNum = document.getElementById('outOrderNum');
    const outCustomer = document.getElementById('outCustomer');
    const outConfirm  = document.getElementById('outConfirm');
    let outTargetId = 0;

    window.openOutForDeliveryModal = function (id, orderNum, customer) {
        outTargetId = id;
        outOrderNum.textContent = orderNum;
        outCustomer.textContent = customer;
        outModal.show();
    };

    outConfirm?.addEventListener('click', async () => {
        outConfirm.disabled = true;
        const orig = outConfirm.innerHTML;
        outConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Starting…';

        try {
            const body = new URLSearchParams({ _csrf: csrf });

            const res = await fetch(`/staff/deliveries/${outTargetId}/out-for-delivery`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Delivery started.', 'success');
                outModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                outConfirm.disabled = false;
                outConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            outConfirm.disabled = false;
            outConfirm.innerHTML = orig;
        }
    });

    // ==================== DELIVERED ====================
    const deliveredModal   = new bootstrap.Modal(document.getElementById('deliveredModal'));
    const deliveredOrderNo = document.getElementById('deliveredOrderNum');
    const deliveredCustomer = document.getElementById('deliveredCustomer');
    const deliveredNotes   = document.getElementById('deliveredNotes');
    const deliveredConfirm = document.getElementById('deliveredConfirm');
    let deliveredTargetId = 0;

    window.openDeliveredModal = function (id, orderNum, customer) {
        deliveredTargetId = id;
        deliveredOrderNo.textContent = orderNum;
        deliveredCustomer.textContent = customer;
        deliveredNotes.value = '';
        deliveredModal.show();
    };

    deliveredConfirm?.addEventListener('click', async () => {
        deliveredConfirm.disabled = true;
        const orig = deliveredConfirm.innerHTML;
        deliveredConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Delivering…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                delivery_notes: deliveredNotes.value.trim(),
            });

            const res = await fetch(`/staff/deliveries/${deliveredTargetId}/delivered`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Order delivered.', 'success');
                deliveredModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                deliveredConfirm.disabled = false;
                deliveredConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            deliveredConfirm.disabled = false;
            deliveredConfirm.innerHTML = orig;
        }
    });

    // ==================== FAILED ====================
    const failedModal        = new bootstrap.Modal(document.getElementById('failedModal'));
    const failedOrderNum     = document.getElementById('failedOrderNum');
    const failedReasonSelect = document.getElementById('failedReasonSelect');
    const failedReason       = document.getElementById('failedReason');
    const failedError        = document.getElementById('failedError');
    const failedConfirm      = document.getElementById('failedConfirm');
    let failedTargetId = 0;

    window.openFailedModal = function (id, orderNum) {
        failedTargetId = id;
        failedOrderNum.textContent = orderNum;
        failedReasonSelect.value = '';
        failedReason.value = '';
        failedError.style.display = 'none';
        failedModal.show();
    };

    failedConfirm?.addEventListener('click', async () => {
        const selectedReason = failedReasonSelect.value;
        const customReason   = failedReason.value.trim();

        let finalReason = '';
        if (selectedReason === 'other') {
            if (customReason.length < 5) {
                failedError.textContent = 'Please provide a reason (min 5 chars) for "Other".';
                failedError.style.display = 'block';
                return;
            }
            finalReason = customReason;
        } else if (selectedReason) {
            finalReason = selectedReason + (customReason ? ' — ' + customReason : '');
        } else if (customReason.length >= 5) {
            finalReason = customReason;
        } else {
            failedError.textContent = 'Please select or enter a reason.';
            failedError.style.display = 'block';
            return;
        }

        failedConfirm.disabled = true;
        const orig = failedConfirm.innerHTML;
        failedConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, reason: finalReason });

            const res = await fetch(`/staff/deliveries/${failedTargetId}/failed`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Delivery marked as failed.', 'success');
                failedModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                failedConfirm.disabled = false;
                failedConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            failedConfirm.disabled = false;
            failedConfirm.innerHTML = orig;
        }
    });

    // ==================== RESCHEDULE ====================
    const rescheduleModal   = new bootstrap.Modal(document.getElementById('rescheduleModal'));
    const rescheduleOrderNo = document.getElementById('rescheduleOrderNum');
    const rescheduleDate    = document.getElementById('rescheduleDate');
    const rescheduleSlot    = document.getElementById('rescheduleSlot');
    const rescheduleError   = document.getElementById('rescheduleError');
    const rescheduleConfirm = document.getElementById('rescheduleConfirm');
    let rescheduleTargetId = 0;

    window.openRescheduleModal = function (id, orderNum, currentDate) {
        rescheduleTargetId = id;
        rescheduleOrderNo.textContent = orderNum;
        rescheduleDate.value = currentDate || '<?= date('Y-m-d') ?>';
        rescheduleSlot.innerHTML = '<option value="">— Loading slots… —</option>';
        rescheduleError.style.display = 'none';
        rescheduleModal.show();
        loadSlots();
    };

    async function loadSlots() {
        const date = rescheduleDate.value;
        if (!date) return;

        try {
            const res = await fetch(`/staff/deliveries/slots?date=${encodeURIComponent(date)}`);
            const json = await res.json();

            if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                rescheduleSlot.innerHTML = '<option value="">— Select a slot —</option>' +
                    json.data.map(s =>
                        `<option value="${s.slot_id}">${s.label}</option>`
                    ).join('');
            } else {
                rescheduleSlot.innerHTML = '<option value="">No slots available</option>';
            }
        } catch (err) {
            console.error(err);
            rescheduleSlot.innerHTML = '<option value="">Error loading slots</option>';
        }
    }

    rescheduleDate?.addEventListener('change', loadSlots);

    rescheduleConfirm?.addEventListener('click', async () => {
        const newDate = rescheduleDate.value;
        const newSlot = parseInt(rescheduleSlot.value, 10);

        if (!newDate || !newSlot) {
            rescheduleError.textContent = 'Please select a date and slot.';
            rescheduleError.style.display = 'block';
            return;
        }

        rescheduleConfirm.disabled = true;
        const orig = rescheduleConfirm.innerHTML;
        rescheduleConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rescheduling…';

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                new_date: newDate,
                new_slot_id: newSlot,
            });

            const res = await fetch(`/staff/deliveries/${rescheduleTargetId}/reschedule`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Delivery rescheduled.', 'success');
                rescheduleModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                rescheduleConfirm.disabled = false;
                rescheduleConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            rescheduleConfirm.disabled = false;
            rescheduleConfirm.innerHTML = orig;
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
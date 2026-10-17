<?php
/**
 * Admin Reports — Refunds
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ReportController::refunds()
 *
 * Expected variables:
 *   $from       string   Y-m-d
 *   $to         string   Y-m-d
 *   $summary    array    [
 *     total, pending_amount, approved_amount, completed_amount, rejected_amount,
 *     pending_count, approved_count, completed_count, rejected_count
 *   ]
 *   $byStatus   array    rows: [status, total, amount]
 *   $recent     array    rows: [refund_id, amount, status, refund_reason,
 *                                created_at, approved_at, completed_at,
 *                                complaint_number, complaint_type,
 *                                order_number, customer_name, approved_by_name]
 * ----------------------------------------------------------
 */

$currentPage = 'reports';
$title       = 'Refunds Report';

// Safe defaults
$from     = $from     ?? date('Y-m-d', strtotime('-30 days'));
$to       = $to       ?? date('Y-m-d');
$summary  = $summary  ?? [];
$byStatus = $byStatus ?? [];
$recent   = $recent   ?? [];

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtMoney = fn($n) => 'Rs. ' . number_format((float) $n, 2);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

// Extract summary
$totalRefunds     = (int)   ($summary['total']            ?? 0);
$pendingAmount    = (float) ($summary['pending_amount']   ?? 0);
$approvedAmount   = (float) ($summary['approved_amount']  ?? 0);
$completedAmount  = (float) ($summary['completed_amount'] ?? 0);
$rejectedAmount   = (float) ($summary['rejected_amount']  ?? 0);
$pendingCount     = (int)   ($summary['pending_count']    ?? 0);
$approvedCount    = (int)   ($summary['approved_count']   ?? 0);
$completedCount   = (int)   ($summary['completed_count']  ?? 0);
$rejectedCount    = (int)   ($summary['rejected_count']   ?? 0);

// Total refunded money (approved + completed)
$totalRefunded = $approvedAmount + $completedAmount;

// Highest amount in range
$maxRefund = 0;
foreach ($recent as $r) {
    $maxRefund = max($maxRefund, (float) ($r['amount'] ?? 0));
}

// Status metadata
$statusMeta = [
    'pending'   => ['label' => 'Pending',   'color' => 'warning',   'icon' => 'bi-hourglass-split'],
    'approved'  => ['label' => 'Approved',  'color' => 'info',      'icon' => 'bi-check-circle'],
    'completed' => ['label' => 'Completed', 'color' => 'success',   'icon' => 'bi-check2-all'],
    'rejected'  => ['label' => 'Rejected',  'color' => 'danger',    'icon' => 'bi-x-circle'],
];
$statusHex = [
    'warning'   => '#f59e0b',
    'info'      => '#06b6d4',
    'success'   => '#22c55e',
    'danger'    => '#ef4444',
];

// Complaint type badge
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
$typeLabel = fn(string $t): string => ucwords(str_replace('_', ' ', $t));

// Prepare status chart data
$statusLabels = [];
$statusValues = [];
$statusAmounts = [];
$statusHexes  = [];
foreach ($statusMeta as $slug => $meta) {
    $count = 0;
    $amount = 0;
    foreach ($byStatus as $row) {
        if (($row['status'] ?? '') === $slug) {
            $count  = (int) ($row['total'] ?? 0);
            $amount = (float) ($row['amount'] ?? 0);
            break;
        }
    }
    $statusLabels[] = $meta['label'];
    $statusValues[] = $count;
    $statusAmounts[] = $amount;
    $statusHexes[] = $statusHex[$meta['color']] ?? '#64748b';
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
                <i class="bi bi-arrow-counterclockwise me-2 text-secondary"></i>Refunds Report
            </h1>
            <p class="text-muted mb-0 small">
                <strong><?= $e($fmtDate($from)) ?></strong> → <strong><?= $e($fmtDate($to)) ?></strong>
                · <?= $fmtInt($totalRefunds) ?> refund request(s)
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/refunds?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i>Refund Queue
        </a>
        <a href="/admin/reports/refunds/json?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-code-slash me-1"></i>JSON
        </a>
        <a href="/admin/reports/export?report=refunds&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- ==================== FILTER BAR ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="/admin/reports/refunds" class="row g-2 align-items-end">
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

<!-- ==================== PRIMARY KPIs ==================== -->
<div class="row g-3 mb-4">
    <!-- Total refunded (hero) -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"
             style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-uppercase fw-semibold opacity-75">Total Refunded</span>
                    <i class="bi bi-cash-stack fs-4 opacity-75"></i>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($totalRefunded) ?></h3>
                <div class="small opacity-75">
                    <?= $fmtInt($approvedCount + $completedCount) ?> refund(s)
                </div>
            </div>
        </div>
    </div>

    <!-- Pending -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Pending</span>
                    <span class="badge bg-warning-subtle text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($pendingAmount) ?></h3>
                <div class="text-muted small">
                    <?= $fmtInt($pendingCount) ?> awaiting approval
                </div>
            </div>
        </div>
    </div>

    <!-- Completed -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Completed</span>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-check2-all"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($completedAmount) ?></h3>
                <div class="text-muted small">
                    <?= $fmtInt($completedCount) ?> transferred
                </div>
            </div>
        </div>
    </div>

    <!-- Rejected -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Rejected</span>
                    <span class="badge bg-danger-subtle text-danger">
                        <i class="bi bi-x-circle"></i>
                    </span>
                </div>
                <h3 class="mb-2 fw-bold"><?= $fmtMoney($rejectedAmount) ?></h3>
                <div class="text-muted small">
                    <?= $fmtInt($rejectedCount) ?> declined
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== ANALYSIS CARDS ==================== -->
<div class="row g-3 mb-4">

    <!-- Status doughnut + legend -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-pie-chart me-2 text-primary"></i>By Status
                </h6>
            </div>
            <div class="card-body">
                <?php if ($totalRefunds === 0): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-pie-chart fs-1 d-block mb-2 opacity-50"></i>
                        <div class="small">No refunds in this range.</div>
                    </div>
                <?php else: ?>
                    <canvas id="statusChart" height="200"></canvas>
                    <div class="mt-3 pt-3 border-top">
                        <?php foreach ($statusMeta as $slug => $meta):
                            $count = 0;
                            $amount = 0;
                            foreach ($byStatus as $row) {
                                if (($row['status'] ?? '') === $slug) {
                                    $count  = (int) ($row['total'] ?? 0);
                                    $amount = (float) ($row['amount'] ?? 0);
                                    break;
                                }
                            }
                            if ($count === 0) continue;
                            $pct = $totalRefunds > 0 ? ($count / $totalRefunds) * 100 : 0;
                        ?>
                            <div class="d-flex align-items-center gap-2 mb-2 small">
                                <span class="status-dot" style="background: <?= $statusHex[$meta['color']] ?? '#64748b' ?>"></span>
                                <span class="flex-grow-1"><?= $e($meta['label']) ?></span>
                                <span class="text-muted"><?= number_format($pct, 1) ?>%</span>
                                <span class="fw-medium text-end" style="min-width: 90px;">
                                    <?= $fmtMoney($amount) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats summary -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Refund Insights
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="insight-block">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Highest Single Refund</div>
                            <div class="fs-4 fw-bold text-warning">
                                <?= $maxRefund > 0 ? $fmtMoney($maxRefund) : '—' ?>
                            </div>
                            <div class="text-muted small mt-1">
                                Based on recent refunds in range
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="insight-block">
                            <?php
                            $approvalRate = $totalRefunds > 0
                                ? (($approvedCount + $completedCount) / $totalRefunds) * 100
                                : 0;
                            ?>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Approval Rate</div>
                            <div class="fs-4 fw-bold <?= $approvalRate >= 70 ? 'text-success' : ($approvalRate >= 40 ? 'text-warning' : 'text-danger') ?>">
                                <?= number_format($approvalRate, 1) ?>%
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-<?= $approvalRate >= 70 ? 'success' : ($approvalRate >= 40 ? 'warning' : 'danger') ?>"
                                     style="width: <?= $approvalRate ?>%"></div>
                            </div>
                            <div class="text-muted small mt-1">
                                <?= $fmtInt($approvedCount + $completedCount) ?> of <?= $fmtInt($totalRefunds) ?> approved
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="insight-block">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Avg Refund</div>
                            <div class="fs-4 fw-bold text-primary">
                                <?php
                                $avgRefund = $totalRefunds > 0
                                    ? ($pendingAmount + $approvedAmount + $completedAmount + $rejectedAmount) / $totalRefunds
                                    : 0;
                                echo $fmtMoney($avgRefund);
                                ?>
                            </div>
                            <div class="text-muted small mt-1">
                                Across all requests
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="insight-block">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Rejection Rate</div>
                            <?php
                            $rejectionRate = $totalRefunds > 0
                                ? ($rejectedCount / $totalRefunds) * 100
                                : 0;
                            ?>
                            <div class="fs-4 fw-bold <?= $rejectionRate > 50 ? 'text-danger' : ($rejectionRate > 20 ? 'text-warning' : 'text-success') ?>">
                                <?= number_format($rejectionRate, 1) ?>%
                            </div>
                            <div class="text-muted small mt-1">
                                <?= $fmtInt($rejectedCount) ?> rejected
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== RECENT REFUNDS TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-table me-2 text-primary"></i>Recent Refunds
        </h6>
        <span class="text-muted small"><?= $fmtInt(count($recent)) ?> record(s)</span>
    </div>
    <div class="table-responsive" style="max-height: 600px;">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th class="small text-muted fw-semibold">Refund</th>
                    <th class="small text-muted fw-semibold">Complaint</th>
                    <th class="small text-muted fw-semibold">Customer</th>
                    <th class="small text-muted fw-semibold">Order</th>
                    <th class="small text-muted fw-semibold text-end">Amount</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold">Created</th>
                    <th class="small text-muted fw-semibold">Approved By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No refunds in this range</div>
                                <div class="small">
                                    Adjust the date range to see refund data.
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($recent as $r):
                    $rid         = (int) ($r['refund_id'] ?? 0);
                    $rStatus     = (string) ($r['status'] ?? 'pending');
                    $rAmount     = (float) ($r['amount'] ?? 0);
                    $rCreated    = $r['created_at'] ?? null;
                    $rApprovedAt = $r['approved_at'] ?? null;
                    $rCompletedAt = $r['completed_at'] ?? null;
                    $cNumber     = (string) ($r['complaint_number'] ?? '');
                    $cType       = (string) ($r['complaint_type'] ?? '');
                    $orderNum    = (string) ($r['order_number'] ?? '');
                    $custName    = (string) ($r['customer_name'] ?? '');
                    $approvedBy  = $r['approved_by_name'] ?? null;
                    $meta        = $statusMeta[$rStatus] ?? ['label' => ucfirst($rStatus), 'color' => 'secondary'];
                ?>
                    <tr class="<?= $rStatus === 'pending' ? 'table-warning' : ($rStatus === 'completed' ? 'table-success' : '') ?>">
                        <td>
                            <div class="fw-medium small">
                                #R-<?= str_pad((string) $rid, 5, '0', STR_PAD_LEFT) ?>
                            </div>
                            <div class="text-muted" style="font-size:.7rem;">
                                #<?= $rid ?>
                            </div>
                        </td>
                        <td>
                            <div class="small">
                                <?php if ($cNumber !== ''): ?>
                                    <a href="/admin/complaints?search=<?= urlencode($cNumber) ?>"
                                       class="text-decoration-none fw-medium">
                                        <?= $e($cNumber) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($cType !== ''): ?>
                                <span class="badge bg-<?= $typeBadge($cType) ?>" style="font-size:.65rem;">
                                    <?= $e($typeLabel($cType)) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-truncate" style="max-width: 140px;">
                            <?= $e($custName) ?>
                        </td>
                        <td>
                            <span class="badge bg-info-subtle text-info">
                                <?= $e($orderNum) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="fw-medium small"><?= $fmtMoney($rAmount) ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $meta['color'] ?>">
                                <i class="bi <?= $meta['icon'] ?> me-1"></i>
                                <?= $e($meta['label']) ?>
                            </span>
                        </td>
                        <td class="small text-muted">
                            <div><?= $e($fmtDate($rCreated)) ?></div>
                            <?php if ($rStatus === 'pending'): ?>
                                <div style="font-size:.68rem;">
                                    <i class="bi bi-clock"></i>
                                    <?= $e($fmtDate($rCreated)) ?>
                                </div>
                            <?php elseif ($rCompletedAt): ?>
                                <div class="text-success" style="font-size:.68rem;">
                                    <i class="bi bi-check2-all"></i>
                                    <?= $e($fmtDate($rCompletedAt)) ?>
                                </div>
                            <?php elseif ($rApprovedAt): ?>
                                <div class="text-info" style="font-size:.68rem;">
                                    <i class="bi bi-check"></i>
                                    <?= $e($fmtDate($rApprovedAt)) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= $approvedBy ? $e($approvedBy) : '<span class="fst-italic">—</span>' ?>
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
    .insight-block {
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        height: 100%;
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
    const statusLabels = <?= json_encode($statusLabels, $jsonFlags) ?>;
    const statusValues = <?= json_encode($statusValues, $jsonFlags) ?>;
    const statusAmounts = <?= json_encode($statusAmounts, $jsonFlags) ?>;
    const statusHexes  = <?= json_encode($statusHexes, $jsonFlags) ?>;

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748b';

    // Only filter out zero-count statuses for cleaner look
    const filtered = statusValues
        .map((v, i) => ({ label: statusLabels[i], value: v, amount: statusAmounts[i], hex: statusHexes[i] }))
        .filter(x => x.value > 0);

    const finalLabels = filtered.map(x => x.label);
    const finalValues = filtered.map(x => x.value);
    const finalHexes  = filtered.map(x => x.hex);
    const finalAmounts = filtered.map(x => x.amount);

    const canvas = document.getElementById('statusChart');
    if (canvas && finalLabels.length) {
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: finalLabels,
                datasets: [{
                    data: finalValues,
                    backgroundColor: finalHexes,
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
                                const idx = ctx.dataIndex;
                                const v = Number(ctx.raw);
                                const amt = Number(finalAmounts[idx] || 0).toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                                return ` ${v} refund(s) · Rs. ${amt}`;
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
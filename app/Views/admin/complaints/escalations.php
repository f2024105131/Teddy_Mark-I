<?php
/**
 * Admin Complaints — Escalation Queue
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\ComplaintController::escalations()
 *
 * Expected variables:
 *   $escalations  array   escalated complaints with joins
 *                         each: complaint_id, complaint_number, type, status,
 *                               created_at, resolution_notes,
 *                               customer_id, customer_name, customer_phone,
 *                               order_number,
 *                               category_name,
 *                               assigned_staff_name
 *   $typeLabels   array   type → human label map
 *
 * POST endpoints (reuse from index/detail):
 *   • /admin/complaints/{id}/assign
 *   • /admin/complaints/{id}/resolve
 *   • /admin/complaints/{id}/reject
 *   • /admin/complaints/{id}/close
 * ----------------------------------------------------------
 */

$currentPage = 'complaints';
$title       = 'Escalated Complaints';

// Safe defaults
$escalations = $escalations ?? [];
$typeLabels  = $typeLabels  ?? [];

$e           = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt      = fn($n) => number_format((int) $n);
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

// Relative time
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

// How long has it been escalated? (urgency metric)
$ageInHours = function (?string $d): float {
    if (!$d) return 0;
    $ts = strtotime($d);
    if (!$ts) return 0;
    return (time() - $ts) / 3600;
};
$urgencyLevel = function (float $hours): string {
    return match (true) {
        $hours < 24 => 'fresh',
        $hours < 72 => 'warm',
        default     => 'stale',
    };
};
$urgencyBadge = function (string $level): string {
    return match ($level) {
        'fresh' => 'success',
        'warm'  => 'warning',
        'stale' => 'danger',
        default => 'secondary',
    };
};
$urgencyLabel = function (string $level): string {
    return match ($level) {
        'fresh' => 'Fresh',
        'warm'  => 'Aging',
        'stale' => 'Stale',
        default => '—',
    };
};

// Type badge / label
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
$typeLabel = fn(string $t): string => $typeLabels[$t] ?? ucwords(str_replace('_', ' ', $t));

// Extract the escalation note from resolution_notes
// (Staff\ComplaintController appends "[timestamp] ESCALATED by name: reason")
$extractEscalationNote = function (?string $notes): string {
    if (!$notes) return '';
    // Find the last "ESCALATED" line
    if (preg_match('/\[.*?\]\s*ESCALATED[^\n]*:\s*(.+)$/im', $notes, $m)) {
        return trim($m[1]);
    }
    return '';
};

// Stats
$totalEscalated = count($escalations);
$fresh = 0; $warm = 0; $stale = 0;
$oldestHours = 0.0;
foreach ($escalations as $e_row) {
    $h = $ageInHours($e_row['created_at'] ?? null);
    if ($h > $oldestHours) $oldestHours = $h;
    $lvl = $urgencyLevel($h);
    if ($lvl === 'fresh') $fresh++;
    elseif ($lvl === 'warm') $warm++;
    else $stale++;
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
            <h1 class="page-title mb-1 d-flex align-items-center gap-2 flex-wrap">
                Escalated Complaints
                <?php if ($totalEscalated > 0): ?>
                    <span class="badge bg-danger"><?= $fmtInt($totalEscalated) ?></span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0 small">
                Complaints escalated by staff — awaiting admin action
            </p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/complaints" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i>All Complaints
        </a>
        <a href="/admin/complaints/categories" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tags me-1"></i>Categories
        </a>
    </div>
</div>

<!-- ==================== URGENCY BANNER ==================== -->
<?php if ($totalEscalated > 0): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="kpi-icon bg-danger-subtle text-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Total</div>
                            <div class="fw-bold fs-5 mb-0"><?= $fmtInt($totalEscalated) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">
                                awaiting action
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
                            <i class="bi bi-clock"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Fresh</div>
                            <div class="fw-bold fs-5 mb-0"><?= $fmtInt($fresh) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">
                                &lt; 24h old
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
                            <div class="text-muted small text-uppercase fw-semibold">Aging</div>
                            <div class="fw-bold fs-5 mb-0"><?= $fmtInt($warm) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">
                                1–3 days
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
                            <i class="bi bi-exclamation-octagon"></i>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Stale</div>
                            <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stale) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">
                                3+ days old
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ==================== ESCALATION LIST ==================== -->
<?php if (empty($escalations)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-shield-check fs-1 text-success d-block mb-3"></i>
            <h5 class="mb-2">All clear! 🎉</h5>
            <p class="text-muted mb-3">
                No complaints are currently escalated to admin.
            </p>
            <a href="/admin/complaints" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-list-ul me-1"></i>View All Complaints
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($escalations as $esc):
            $escId       = (int) $esc['complaint_id'];
            $escNumber   = (string) $esc['complaint_number'];
            $escType     = (string) $esc['type'];
            $escCreated  = $esc['created_at'] ?? null;
            $escNotes    = $esc['resolution_notes'] ?? '';
            $custId      = (int) $esc['customer_id'];
            $custName    = (string) $esc['customer_name'];
            $custPhone   = (string) $esc['customer_phone'];
            $orderNum    = (string) $esc['order_number'];
            $escCategory = (string) ($esc['category_name'] ?? '');
            $staffName   = $esc['assigned_staff_name'] ?? null;

            $hours       = $ageInHours($escCreated);
            $level       = $urgencyLevel($hours);
            $escReason   = $extractEscalationNote($escNotes);
        ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm escalation-card border-start border-<?= $urgencyBadge($level) ?> border-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-start">

                            <!-- Left: complaint info -->
                            <div class="col-lg-7">
                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <a href="/admin/complaints/<?= $escId ?>"
                                       class="fw-semibold fs-6 text-decoration-none">
                                        <?= $e($escNumber) ?>
                                    </a>
                                    <span class="badge bg-<?= $urgencyBadge($level) ?>">
                                        <i class="bi bi-clock-history me-1"></i>
                                        <?= $e($urgencyLabel($level)) ?>
                                    </span>
                                    <span class="badge bg-<?= $typeBadge($escType) ?>">
                                        <?= $e($typeLabel($escType)) ?>
                                    </span>
                                    <?php if ($escCategory !== ''): ?>
                                        <span class="badge bg-light text-dark border">
                                            <?= $e($escCategory) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex flex-wrap gap-3 small text-muted mb-2">
                                    <span>
                                        <i class="bi bi-person me-1"></i>
                                        <a href="/admin/customers/<?= $custId ?>"
                                           class="text-decoration-none">
                                            <?= $e($custName) ?>
                                        </a>
                                    </span>
                                    <span>
                                        <i class="bi bi-telephone me-1"></i>
                                        <?= $e($custPhone) ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-bag-check me-1"></i>
                                        <?= $e($orderNum) ?>
                                    </span>
                                </div>

                                <div class="small text-muted mb-2">
                                    <i class="bi bi-person-badge me-1"></i>
                                    Escalated by
                                    <strong>
                                        <?= $e($staffName ?: 'System') ?>
                                    </strong>
                                    · <?= $e($timeAgo($escCreated)) ?>
                                    · <?= $e($fmtDateTime($escCreated)) ?>
                                </div>

                                <?php if ($escReason !== ''): ?>
                                    <div class="bg-light rounded p-2 small">
                                        <div class="text-muted text-uppercase fw-semibold mb-1"
                                             style="font-size:.65rem; letter-spacing:.05em;">
                                            Escalation Reason
                                        </div>
                                        <div><?= nl2br($e($escReason)) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Right: actions -->
                            <div class="col-lg-5">
                                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                    <a href="/admin/complaints/<?= $escId ?>"
                                       class="btn btn-primary btn-sm">
                                        <i class="bi bi-eye me-1"></i>Review
                                    </a>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            onclick="openAssignModal(<?= $escId ?>, <?= json_encode($escNumber, $jsonFlags) ?>)">
                                        <i class="bi bi-person-plus me-1"></i>Assign
                                    </button>
                                    <button type="button"
                                            class="btn btn-success btn-sm"
                                            onclick="openResolveModal(<?= $escId ?>, <?= json_encode($escNumber, $jsonFlags) ?>)">
                                        <i class="bi bi-check-circle me-1"></i>Resolve
                                    </button>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button"
                                                class="btn btn-outline-secondary dropdown-toggle"
                                                data-bs-toggle="dropdown">
                                            More
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <button type="button"
                                                        class="dropdown-item text-secondary"
                                                        onclick="openRejectModal(<?= $escId ?>, <?= json_encode($escNumber, $jsonFlags) ?>)">
                                                    <i class="bi bi-x-circle me-2"></i>Reject
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item" href="tel:<?= $e($custPhone) ?>">
                                                    <i class="bi bi-telephone me-2"></i>Call Customer
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/refunds?search=<?= urlencode($escNumber) ?>">
                                                    <i class="bi bi-arrow-counterclockwise me-2"></i>View Refunds
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="/admin/reports/orders?search=<?= urlencode($orderNum) ?>">
                                                    <i class="bi bi-bag-check me-2"></i>View Order
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Age indicator bar -->
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                        <span>Escalation age</span>
                                        <span>
                                            <?php
                                            $days  = floor($hours / 24);
                                            $remH  = floor($hours % 24);
                                            if ($days > 0) {
                                                echo $days . 'd ' . $remH . 'h';
                                            } else {
                                                echo floor($hours) . 'h';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php
                                    // Age bar: 0–72 hours is the working range.
                                    // After 72h, we cap at 100%.
                                    $pct = min(100, ($hours / 72) * 100);
                                    ?>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-<?= $urgencyBadge($level) ?>"
                                             style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted mt-1"
                                         style="font-size:.68rem;">
                                        <span>0h</span>
                                        <span>24h</span>
                                        <span>72h+</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Summary footer -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="small text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Escalated complaints sort <strong>oldest first</strong> —
                review the oldest to prevent customer frustration.
            </div>
            <div class="d-flex gap-3 small">
                <span>
                    <i class="bi bi-circle-fill text-success" style="font-size:.5rem;"></i>
                    <?= $fmtInt($fresh) ?> fresh
                </span>
                <span>
                    <i class="bi bi-circle-fill text-warning" style="font-size:.5rem;"></i>
                    <?= $fmtInt($warm) ?> aging
                </span>
                <span>
                    <i class="bi bi-circle-fill text-danger" style="font-size:.5rem;"></i>
                    <?= $fmtInt($stale) ?> stale
                </span>
            </div>
        </div>
    </div>
<?php endif; ?>

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
                    <?php
                    // Fetch active staff dynamically (avoids needing $staffList)
                    // Reuse the $staffList from Admin\ComplaintController::show() if passed.
                    // For this view, we fetch inline.
                    $staffOptions = [];
                    try {
                        $db = \App\Core\Database::getConnection();
                        $staffOptions = $db->query(
                            "SELECT staff_id, full_name, role
                             FROM staff
                             WHERE is_active = 1
                             ORDER BY role DESC, full_name ASC"
                        )->fetchAll();
                    } catch (\Throwable $ex) {
                        $staffOptions = [];
                    }
                    ?>
                    <?php foreach ($staffOptions as $s): ?>
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

<!-- ==================== STYLES ==================== -->
<style>
    .kpi-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .escalation-card {
        transition: transform .12s, box-shadow .12s;
    }
    .escalation-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15,23,42,.08) !important;
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
                showToast(json.message || 'Resolved.', 'success');
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
                showToast(json.message || 'Rejected.', 'success');
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
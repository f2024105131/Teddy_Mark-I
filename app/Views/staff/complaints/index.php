<?php
/**
 * Staff Complaints — List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: App\Controllers\Staff\ComplaintController::index()
 *
 * Expected variables:
 *   $complaints  array   paginated complaint rows with customer + order + staff joins
 *   $stats       array   ['open_total','assigned_total','investigating_total','resolved_today','escalated_total','last_30_days']
 *   $filter      string  'active' | 'all' | any valid complaint status
 * ----------------------------------------------------------
 */

$currentPage = 'staff-complaints';
$title       = 'Assigned Complaints';

// Safe defaults
$complaints = $complaints ?? [];
$stats      = $stats      ?? ['open_total'=>0, 'assigned_total'=>0, 'investigating_total'=>0, 'resolved_today'=>0, 'escalated_total'=>0, 'last_30_days'=>0];
$filter     = $filter     ?? 'active';

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, g:i A', strtotime($d)) : '—';

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

// Urgency: how long since filed
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

// Complaint type meta
$typeMeta = [
    'missing_item'          => ['label' => 'Missing Item',          'color' => 'danger',    'icon' => 'bi-box'],
    'damaged_item'          => ['label' => 'Damaged Item',          'color' => 'warning',   'icon' => 'bi-tools'],
    'late_delivery'         => ['label' => 'Late Delivery',         'color' => 'info',      'icon' => 'bi-clock-history'],
    'wrong_billing'         => ['label' => 'Wrong Billing',         'color' => 'primary',   'icon' => 'bi-receipt'],
    'poor_cleaning_quality' => ['label' => 'Poor Cleaning Quality', 'color' => 'secondary', 'icon' => 'bi-droplet'],
];
$typeMetaOf = fn(string $t): array => $typeMeta[$t] ?? ['label' => ucwords(str_replace('_', ' ', $t)), 'color' => 'secondary', 'icon' => 'bi-circle'];

// Filter tabs
$tabs = [
    'active' => ['label' => 'Active', 'count' => ($stats['assigned_total'] ?? 0) + ($stats['investigating_total'] ?? 0) + ($stats['escalated_total'] ?? 0), 'icon' => 'bi-list-check'],
    'all'    => ['label' => 'All',    'count' => null, 'icon' => 'bi-list-ul'],
];

// Build querystring
$qs = function (string $status): string {
    if ($status === 'active') return '/staff/complaints';
    return '/staff/complaints?status=' . urlencode($status);
};

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">My Complaints</h1>
        <p class="text-muted mb-0 small">
            Investigate and resolve customer complaints assigned to you
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/staff/complaints?status=all" class="btn btn-outline-secondary btn-sm">
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
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Assigned</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['assigned_total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            need to start
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
                        <i class="bi bi-search"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Investigating</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['investigating_total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            in progress
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
                        <i class="bi bi-shield-exclamation"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Escalated</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['escalated_total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            sent to admin
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"
             style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff;">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-white bg-opacity-25 text-white">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div>
                        <div class="small text-uppercase fw-semibold opacity-75">Resolved Today</div>
                        <div class="fw-bold fs-4 mb-0"><?= $fmtInt($stats['resolved_today'] ?? 0) ?></div>
                        <div class="small opacity-75">
                            last 30 days: <?= $fmtInt($stats['last_30_days'] ?? 0) ?>
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

    <!-- ==================== COMPLAINTS LIST ==================== -->
    <div class="card-body p-0">
        <?php if (empty($complaints)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-emoji-smile fs-1 d-block mb-3 opacity-50"></i>
                <div class="fw-medium">
                    <?php if ($filter === 'active'): ?>
                        No active complaints assigned to you 🎉
                    <?php else: ?>
                        No complaints found
                    <?php endif; ?>
                </div>
                <div class="small mt-2">
                    New complaints will appear here when assigned by admin.
                </div>
            </div>
        <?php else: ?>
            <div class="complaints-list">
                <?php foreach ($complaints as $c):
                    $cid        = (int) $c['complaint_id'];
                    $cNumber    = (string) $c['complaint_number'];
                    $cStatus    = (string) $c['status'];
                    $cType      = (string) $c['type'];
                    $cCategory  = (string) ($c['category_name'] ?? '');
                    $cDesc      = (string) ($c['description'] ?? '');
                    $custId     = (int) ($c['customer_id'] ?? 0);
                    $custName   = (string) ($c['customer_name'] ?? '');
                    $custPhone  = (string) ($c['customer_phone'] ?? '');
                    $orderId    = (int) ($c['order_id'] ?? 0);
                    $orderNum   = (string) ($c['order_number'] ?? '');
                    $createdAt  = $c['created_at']  ?? null;
                    $resolvedAt = $c['resolved_at'] ?? null;

                    $meta   = $typeMetaOf($cType);
                    $hours  = $ageInHours($createdAt);
                    $level  = $urgencyLevel($hours);
                    $isActiveStatus = in_array($cStatus, ['assigned', 'under_investigation'], true);

                    // Age display
                    $ageLabel = '';
                    if ($hours < 24) {
                        $ageLabel = max(1, (int) $hours) . 'h';
                    } else {
                        $ageLabel = floor($hours / 24) . 'd';
                    }
                ?>
                    <div class="complaint-card <?= $cStatus === 'escalated' ? 'complaint-escalated' : '' ?> <?= $level === 'stale' && $isActiveStatus ? 'complaint-stale' : '' ?>">
                        <div class="row g-3 align-items-center">
                            <!-- Left: severity indicator -->
                            <div class="col-auto">
                                <div class="complaint-icon <?= 'bg-' . $meta['color'] ?>-subtle text-<?= $meta['color'] ?>">
                                    <i class="bi <?= $e($meta['icon']) ?>"></i>
                                </div>
                            </div>

                            <!-- Middle: details -->
                            <div class="col">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <a href="/staff/complaints/<?= $cid ?>"
                                       class="fw-semibold text-decoration-none">
                                        <?= $e($cNumber) ?>
                                    </a>
                                    <span class="badge bg-<?= $statusBadge($cStatus) ?>">
                                        <?= $e($statusLabel($cStatus)) ?>
                                    </span>
                                    <span class="badge bg-<?= $e($meta['color']) ?>-subtle text-<?= $e($meta['color']) ?>"
                                          style="font-size:.7rem;">
                                        <?= $e($meta['label']) ?>
                                    </span>
                                    <?php if ($isActiveStatus && $level === 'stale'): ?>
                                        <span class="badge bg-danger" style="font-size:.65rem;">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Stale
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($cStatus === 'escalated'): ?>
                                        <span class="badge bg-dark" style="font-size:.65rem;">
                                            <i class="bi bi-shield-exclamation"></i> Escalated
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex flex-wrap gap-3 small text-muted mb-2">
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
                                    <?php if ($orderNum !== ''): ?>
                                        <span>
                                            <i class="bi bi-bag-check me-1"></i>
                                            <a href="/staff/orders/<?= $orderId ?>"
                                               class="text-decoration-none text-muted">
                                                <?= $e($orderNum) ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($cCategory !== ''): ?>
                                        <span>
                                            <i class="bi bi-tag me-1"></i>
                                            <?= $e($cCategory) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="small text-muted text-truncate"
                                     style="max-width: 620px;"
                                     title="<?= $e($cDesc) ?>">
                                    <?= $e($cDesc) ?>
                                </div>

                                <?php if ($resolvedAt): ?>
                                    <div class="small text-success mt-2">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Resolved <?= $e($timeAgo($resolvedAt)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Right: age + actions -->
                            <div class="col-md-auto">
                                <div class="text-end mb-2">
                                    <div class="text-muted small">
                                        <i class="bi bi-clock"></i> <?= $e($ageLabel) ?> old
                                    </div>
                                    <div class="badge bg-<?= $urgencyBadge($level) ?>-subtle text-<?= $urgencyBadge($level) ?>"
                                         style="font-size:.65rem;">
                                        <?= $e($urgencyLabel($level)) ?>
                                    </div>
                                </div>

                                <div class="d-flex flex-md-column gap-2">
                                    <?php if ($isActiveStatus): ?>
                                        <?php if ($cStatus === 'assigned'): ?>
                                            <button type="button"
                                                    class="btn btn-info text-white btn-sm"
                                                    onclick="openInvestigateModal(<?= $cid ?>, <?= json_encode($cNumber, $jsonFlags) ?>)">
                                                <i class="bi bi-search me-1"></i>Investigate
                                            </button>
                                        <?php endif; ?>
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                onclick="openNoteModal(<?= $cid ?>, <?= json_encode($cNumber, $jsonFlags) ?>)">
                                            <i class="bi bi-sticky me-1"></i>Note
                                        </button>
                                        <a href="/staff/complaints/<?= $cid ?>"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>Details
                                        </a>
                                    <?php else: ?>
                                        <a href="/staff/complaints/<?= $cid ?>"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>View
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

<!-- ==================== INVESTIGATE MODAL ==================== -->
<div class="modal fade" id="investigateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-search me-2"></i>Start Investigation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">
                    Start investigating complaint <strong id="investigateLabel"></strong>?
                </p>
                <div class="alert alert-info small mb-0 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    This moves the complaint to <strong>Under Investigation</strong>.
                    The customer will be notified.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info text-white" id="investigateConfirm">
                    <i class="bi bi-search me-1"></i>Start Investigation
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
                <h5 class="modal-title">
                    <i class="bi bi-sticky me-2"></i>Add Note
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Add a note to complaint <strong id="noteLabel"></strong>
                </p>
                <textarea class="form-control" id="noteText" rows="3"
                          placeholder="Enter your note (min 5 characters)…"
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

    /* Complaint cards */
    .complaints-list { padding: 0; }
    .complaint-card {
        padding: 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background-color .12s;
    }
    .complaint-card:last-child { border-bottom: none; }
    .complaint-card:hover { background: #f8fafc; }
    .complaint-card.complaint-escalated {
        background: #fef2f2;
        border-left: 3px solid #1e293b;
    }
    .complaint-card.complaint-escalated:hover {
        background: #fee2e2;
    }
    .complaint-card.complaint-stale {
        border-left: 3px solid #ef4444;
    }
    .complaint-icon {
        width: 48px; height: 48px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
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

    // ==================== INVESTIGATE ====================
    const investigateModal   = new bootstrap.Modal(document.getElementById('investigateModal'));
    const investigateLabel   = document.getElementById('investigateLabel');
    const investigateConfirm = document.getElementById('investigateConfirm');
    let investigateTargetId = 0;

    window.openInvestigateModal = function (id, number) {
        investigateTargetId = id;
        investigateLabel.textContent = number;
        investigateModal.show();
    };

    investigateConfirm?.addEventListener('click', async () => {
        investigateConfirm.disabled = true;
        const orig = investigateConfirm.innerHTML;
        investigateConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Starting…';

        try {
            const body = new URLSearchParams({ _csrf: csrf });

            const res = await fetch(`/staff/complaints/${investigateTargetId}/investigate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Investigation started.', 'success');
                investigateModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                investigateConfirm.disabled = false;
                investigateConfirm.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            investigateConfirm.disabled = false;
            investigateConfirm.innerHTML = orig;
        }
    });

    // ==================== NOTE ====================
    const noteModal   = new bootstrap.Modal(document.getElementById('noteModal'));
    const noteLabel   = document.getElementById('noteLabel');
    const noteText    = document.getElementById('noteText');
    const noteError   = document.getElementById('noteError');
    const noteConfirm = document.getElementById('noteConfirm');
    let noteTargetId = 0;

    window.openNoteModal = function (id, number) {
        noteTargetId = id;
        noteLabel.textContent = number;
        noteText.value = '';
        noteError.style.display = 'none';
        noteModal.show();
    };

    noteConfirm?.addEventListener('click', async () => {
        const note = noteText.value.trim();
        if (note.length < 5) {
            noteError.textContent = 'Note must be at least 5 characters.';
            noteError.style.display = 'block';
            return;
        }

        noteConfirm.disabled = true;
        const orig = noteConfirm.innerHTML;
        noteConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, note: note });

            const res = await fetch(`/staff/complaints/${noteTargetId}/note`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Note added.', 'success');
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
<?php
/**
 * Admin Staff — List
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\StaffController::index()
 *
 * Expected variables:
 *   $staff      array   paginated staff rows (with aggregated counts)
 *   $summary    array   ['total','admins','regular','active','inactive','online_recently']
 *   $search     string  current search query
 *   $role       string  current role filter ('admin' | 'staff' | '')
 *   $status     string  current status filter ('active' | 'inactive' | '')
 *   $page       int     current page
 *   $perPage    int     rows per page
 *   $totalRows  int     total matching rows
 *   $totalPages int     total pages
 * ----------------------------------------------------------
 */

$currentPage = 'staff';
$title       = 'Staff';

// Safe defaults
$staff      = $staff      ?? [];
$summary    = $summary    ?? ['total'=>0,'admins'=>0,'regular'=>0,'active'=>0,'inactive'=>0,'online_recently'=>0];
$search     = $search     ?? '';
$role       = $role       ?? '';
$status     = $status     ?? '';
$page       = (int) ($page       ?? 1);
$perPage    = (int) ($perPage    ?? 20);
$totalRows  = (int) ($totalRows  ?? 0);
$totalPages = (int) ($totalPages ?? 1);

$e        = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt   = fn($n) => number_format((int) $n);
$fmtDate  = fn($d) => $d ? date('M j, Y', strtotime($d)) : '—';
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

// Relative time helper
$timeAgo = function (?string $d): string {
    if (!$d) return 'Never';
    $ts = strtotime($d);
    if (!$ts) return 'Never';
    $diff = time() - $ts;
    if ($diff < 60)          return 'Just now';
    if ($diff < 3600)        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)       return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)      return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
};

// Role badge
$roleBadge = function (string $r): string {
    return match ($r) {
        'admin' => 'danger',
        'staff' => 'primary',
        default => 'secondary',
    };
};
$roleLabel = fn(string $r): string => $r === 'admin' ? 'Admin' : 'Staff';

// Score → badge
$scoreBadge = function (float $score): string {
    return match (true) {
        $score >= 85 => 'success',
        $score >= 70 => 'info',
        $score >= 50 => 'warning',
        default      => 'danger',
    };
};
$scoreLabel = function (float $score): string {
    return match (true) {
        $score >= 85 => 'Excellent',
        $score >= 70 => 'Good',
        $score >= 50 => 'Average',
        default      => 'Needs Improvement',
    };
};

// Build querystring for pagination (preserve filters)
$qs = function (int $targetPage) use ($search, $role, $status): string {
    $params = array_filter([
        'search' => $search !== '' ? $search : null,
        'role'   => $role   !== '' ? $role   : null,
        'status' => $status !== '' ? $status : null,
        'page'   => $targetPage,
    ], fn($v) => $v !== null);

    return '?' . http_build_query($params);
};

// Active filters count
$activeFilters = (int) ($search !== '') + (int) ($role !== '') + (int) ($status !== '');
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">Staff</h1>
        <p class="text-muted mb-0 small">
            Manage your team ·
            <?= $fmtInt($totalRows) ?> total
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/staff/performance-overview" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up me-1"></i>Performance Overview
        </a>
        <a href="/admin/staff/export<?= $activeFilters ? '?' . http_build_query(array_filter(['search'=>$search ?: null,'role'=>$role ?: null,'status'=>$status ?: null])) : '' ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a href="/admin/staff/create" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus me-1"></i>Add Staff
        </a>
    </div>
</div>

<!-- ==================== SUMMARY CARDS ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['total']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-danger-subtle text-danger">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Admins</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['admins']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info-subtle text-info">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Staff</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['regular']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Active</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['active']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-secondary-subtle text-secondary">
                        <i class="bi bi-person-dash"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Inactive</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['inactive']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Online 24h</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($summary['online_recently']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== FILTERS ==================== -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="/admin/staff" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text"
                           class="form-control"
                           name="search"
                           value="<?= $e($search) ?>"
                           placeholder="Name, email, or phone…">
                </div>
            </div>

            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Role</label>
                <select class="form-select" name="role">
                    <option value="">All roles</option>
                    <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
                    <option value="staff" <?= $role==='staff'?'selected':'' ?>>Staff</option>
                </select>
            </div>

            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="">All statuses</option>
                    <option value="active"   <?= $status==='active'  ?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-1 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($activeFilters > 0): ?>
                    <a href="/admin/staff" class="btn btn-outline-secondary" title="Clear filters">
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
                <?php if ($role !== ''): ?>
                    <span class="badge bg-light text-dark border">
                        Role: <?= $e($roleLabel($role)) ?>
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

<!-- ==================== STAFF TABLE ==================== -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="small text-muted fw-semibold">Staff Member</th>
                    <th class="small text-muted fw-semibold">Contact</th>
                    <th class="small text-muted fw-semibold text-center">Role</th>
                    <th class="small text-muted fw-semibold text-center">Pickups</th>
                    <th class="small text-muted fw-semibold text-center">Deliveries</th>
                    <th class="small text-muted fw-semibold text-center">Complaints</th>
                    <th class="small text-muted fw-semibold text-center">Today's Score</th>
                    <th class="small text-muted fw-semibold">Last Login</th>
                    <th class="small text-muted fw-semibold text-center">Status</th>
                    <th class="small text-muted fw-semibold text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staff)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                <div class="fw-medium">No staff members found</div>
                                <div class="small">
                                    <?php if ($activeFilters > 0): ?>
                                        Try adjusting your filters ·
                                        <a href="/admin/staff" class="text-decoration-none">Clear all</a>
                                    <?php else: ?>
                                        <a href="/admin/staff/create" class="text-decoration-none">
                                            Add your first staff member →
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: foreach ($staff as $s):
                    $sRole     = $s['role']      ?? 'staff';
                    $isActive  = (int) ($s['is_active'] ?? 0);
                    $isSelf    = (int) $s['staff_id'] === (int) ($_SESSION['staff_id'] ?? 0);
                    $score     = (float) ($s['today_score'] ?? 0);
                    $rating    = (float) ($s['latest_rating'] ?? 0);
                ?>
                    <tr class="<?= $isSelf ? 'table-info' : '' ?>">
                        <!-- Staff member -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle bg-<?= $isActive ? 'success' : 'secondary' ?>-subtle text-<?= $isActive ? 'success' : 'secondary' ?>">
                                    <?= $e(strtoupper(substr($s['full_name'] ?? '?', 0, 1))) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-medium text-truncate">
                                        <?= $e($s['full_name']) ?>
                                        <?php if ($isSelf): ?>
                                            <span class="badge bg-info-subtle text-info ms-1"
                                                  style="font-size:.65rem;">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small">
                                        #<?= (int) $s['staff_id'] ?>
                                        · Joined <?= $fmtDate($s['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Contact -->
                        <td>
                            <div class="small">
                                <div class="text-truncate" style="max-width: 200px;">
                                    <i class="bi bi-envelope text-muted me-1"></i>
                                    <?= $e($s['email']) ?>
                                </div>
                                <div class="text-truncate">
                                    <i class="bi bi-telephone text-muted me-1"></i>
                                    <?= $e($s['phone']) ?>
                                </div>
                            </div>
                        </td>

                        <!-- Role -->
                        <td class="text-center">
                            <span class="badge bg-<?= $roleBadge($sRole) ?>">
                                <?php if ($sRole === 'admin'): ?>
                                    <i class="bi bi-shield-check me-1"></i>
                                <?php endif; ?>
                                <?= $e($roleLabel($sRole)) ?>
                            </span>
                        </td>

                        <!-- Pickups -->
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary">
                                <?= $fmtInt($s['pickups_total'] ?? 0) ?>
                            </span>
                        </td>

                        <!-- Deliveries -->
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success">
                                <?= $fmtInt($s['deliveries_total'] ?? 0) ?>
                            </span>
                        </td>

                        <!-- Complaints -->
                        <td class="text-center">
                            <?php $cc = (int) ($s['complaints_total'] ?? 0); ?>
                            <?php if ($cc > 0): ?>
                                <span class="badge bg-warning-subtle text-warning"><?= $fmtInt($cc) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Today's Score -->
                        <td class="text-center">
                            <?php if ($score > 0): ?>
                                <span class="badge bg-<?= $scoreBadge($score) ?>"
                                      title="<?= $e($scoreLabel($score)) ?>">
                                    <?= number_format($score, 1) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Last login -->
                        <td class="small text-muted">
                            <?= $e($timeAgo($s['last_login'] ?? null)) ?>
                        </td>

                        <!-- Status -->
                        <td class="text-center">
                            <?php if ($isActive): ?>
                                <span class="badge bg-success-subtle text-success">
                                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="/admin/staff/<?= (int) $s['staff_id'] ?>"
                                   class="btn btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/admin/staff/<?= (int) $s['staff_id'] ?>/edit"
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
                                           href="/admin/staff/<?= (int) $s['staff_id'] ?>/performance">
                                            <i class="bi bi-graph-up me-2"></i>View Performance
                                        </a>
                                    </li>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item"
                                                onclick="staffAction('reset-password', <?= (int) $s['staff_id'] ?>, <?= json_encode($s['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                                            <i class="bi bi-key me-2"></i>Reset Password
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item"
                                                onclick="staffAction('change-role', <?= (int) $s['staff_id'] ?>, <?= json_encode($s['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($sRole) ?>)">
                                            <i class="bi bi-person-gear me-2"></i>Change Role
                                        </button>
                                    </li>
                                    <?php if (!$isSelf): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if ($isActive): ?>
                                            <li>
                                                <button type="button"
                                                        class="dropdown-item text-danger"
                                                        onclick="staffAction('toggle-active', <?= (int) $s['staff_id'] ?>, <?= json_encode($s['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                                                    <i class="bi bi-person-slash me-2"></i>Deactivate
                                                </button>
                                            </li>
                                        <?php else: ?>
                                            <li>
                                                <button type="button"
                                                        class="dropdown-item text-success"
                                                        onclick="staffAction('toggle-active', <?= (int) $s['staff_id'] ?>, <?= json_encode($s['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                                                    <i class="bi bi-person-check me-2"></i>Activate
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ==================== PAGINATION ==================== -->
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
                <p id="actionModalText" class="mb-3"></p>

                <!-- Role selector (only for change-role) -->
                <div id="roleWrap" class="d-none">
                    <label class="form-label small text-muted">New Role</label>
                    <select class="form-select" id="newRole">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                    <div class="form-text small">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        Admins have full access. You cannot demote the last active admin.
                    </div>
                </div>

                <!-- Password fields (only for reset-password set mode) -->
                <div id="passwordWrap" class="d-none">
                    <div class="alert alert-info small mb-3 py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        By default, a <strong>reset link</strong> will be emailed.
                        To set a new password immediately, fill in the fields below.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-muted">New Password</label>
                        <input type="password"
                               class="form-control"
                               id="newPassword"
                               placeholder="Leave blank to email reset link"
                               autocomplete="new-password">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">Confirm Password</label>
                        <input type="password"
                               class="form-control"
                               id="confirmPassword"
                               placeholder="Repeat password"
                               autocomplete="new-password">
                    </div>
                    <div class="form-text small">
                        Min 8 chars, must contain a letter and a number.
                    </div>
                    <div class="invalid-feedback d-block" id="passwordError" style="display:none;"></div>
                </div>
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
    .avatar-circle {
        width: 38px; height: 38px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .9rem;
        flex-shrink: 0;
    }
    .kpi-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .min-w-0 { min-width: 0; }
    .table > :not(caption) > * > * {
        padding: .85rem .75rem;
        vertical-align: middle;
    }
</style>

<!-- ==================== SCRIPT ==================== -->
<script>
(function () {
    const csrf = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

    const modal       = new bootstrap.Modal(document.getElementById('actionModal'));
    const modalTitle  = document.getElementById('actionModalTitle');
    const modalText   = document.getElementById('actionModalText');
    const roleWrap    = document.getElementById('roleWrap');
    const newRole     = document.getElementById('newRole');
    const passwordWrap= document.getElementById('passwordWrap');
    const newPwd      = document.getElementById('newPassword');
    const confirmPwd  = document.getElementById('confirmPassword');
    const pwdErr      = document.getElementById('passwordError');
    const confirmBtn  = document.getElementById('confirmBtn');

    let pendingAction = null;

    const ACTIONS = {
        'reset-password': {
            title: 'Reset Password',
            text: (n) => `Generate a password reset for <strong>${escapeHtml(n)}</strong>?`,
            confirmLabel: 'Reset Password',
            btnClass: 'btn-warning',
            showRole: false,
            showPassword: true,
        },
        'change-role': {
            title: 'Change Role',
            text: (n) => `Change role for <strong>${escapeHtml(n)}</strong>?`,
            confirmLabel: 'Change Role',
            btnClass: 'btn-primary',
            showRole: true,
            showPassword: false,
        },
        'toggle-active': {
            title: 'Toggle Account Status',
            text: (n) => `Toggle account status for <strong>${escapeHtml(n)}</strong>?`,
            confirmLabel: 'Confirm',
            btnClass: 'btn-danger',
            showRole: false,
            showPassword: false,
        },
    };

    window.staffAction = function (action, id, name, currentRole) {
        const cfg = ACTIONS[action];
        if (!cfg) return;

        pendingAction = { type: action, id: id };

        modalTitle.textContent = cfg.title;
        modalText.innerHTML    = cfg.text(name);
        confirmBtn.textContent = cfg.confirmLabel;
        confirmBtn.className   = 'btn ' + cfg.btnClass;

        // Hide both optional blocks first
        roleWrap.classList.add('d-none');
        passwordWrap.classList.add('d-none');
        pwdErr.style.display = 'none';

        if (cfg.showRole) {
            roleWrap.classList.remove('d-none');
            newRole.value = currentRole || 'staff';
        }

        if (cfg.showPassword) {
            passwordWrap.classList.remove('d-none');
            newPwd.value = '';
            confirmPwd.value = '';
        }

        modal.show();

        // Focus first input
        setTimeout(() => {
            if (cfg.showRole)    newRole.focus();
            if (cfg.showPassword) newPwd.focus();
        }, 300);
    };

    confirmBtn.addEventListener('click', async () => {
        if (!pendingAction) return;

        const { type, id } = pendingAction;

        // Build body
        const body = new URLSearchParams({ _csrf: csrf });

        if (type === 'change-role') {
            body.append('role', newRole.value);
        }

        if (type === 'reset-password') {
            const pwd = newPwd.value;
            const cfm = confirmPwd.value;

            if (pwd !== '' || cfm !== '') {
                // Mode: set
                if (pwd.length < 8) {
                    pwdErr.textContent = 'Password must be at least 8 characters.';
                    pwdErr.style.display = 'block';
                    return;
                }
                if (pwd.length > 72) {
                    pwdErr.textContent = 'Password must not exceed 72 characters.';
                    pwdErr.style.display = 'block';
                    return;
                }
                if (!/[A-Za-z]/.test(pwd)) {
                    pwdErr.textContent = 'Password must contain at least one letter.';
                    pwdErr.style.display = 'block';
                    return;
                }
                if (!/[0-9]/.test(pwd)) {
                    pwdErr.textContent = 'Password must contain at least one number.';
                    pwdErr.style.display = 'block';
                    return;
                }
                if (pwd !== cfm) {
                    pwdErr.textContent = 'Passwords do not match.';
                    pwdErr.style.display = 'block';
                    return;
                }
                body.append('mode', 'set');
                body.append('new_password', pwd);
                body.append('confirm_password', cfm);
            } else {
                body.append('mode', 'token');
            }
        }

        confirmBtn.disabled = true;
        const originalText = confirmBtn.textContent;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

        try {
            const url = buildUrl(type, id);
            if (!url) { modal.hide(); return; }

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
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(json.message || 'Action failed.', 'danger');
                confirmBtn.disabled = false;
                confirmBtn.textContent = originalText;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            confirmBtn.disabled = false;
            confirmBtn.textContent = originalText;
        }
    });

    function buildUrl(type, id) {
        const base = '/admin/staff/' + id;
        switch (type) {
            case 'reset-password': return base + '/reset-password';
            case 'change-role':    return base + '/change-role';
            case 'toggle-active':  return base + '/toggle-active';
            default:               return null;
        }
    }

    document.getElementById('actionModal').addEventListener('hidden.bs.modal', () => {
        pendingAction = null;
        confirmBtn.disabled = false;
        pwdErr.style.display = 'none';
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
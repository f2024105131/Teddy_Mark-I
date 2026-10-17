<?php
/**
 * Admin Settings — System Configuration
 * ----------------------------------------------------------
 * Owner : Rohan
 * Rendered by: Admin\SettingsController::index()
 *
 * Expected variables:
 *   $grouped       array   group_slug => [
 *                            'label' => '...',
 *                            'description' => '...',
 *                            'icon' => 'bi-...',
 *                            'settings' => [ config rows ],
 *                          ]
 *   $stats         array   ['total','defaults','customized','last_updated']
 *   $dataTypes     array   ['string','integer','boolean','json','decimal']
 *   $protectedKeys array   ['business_name', 'currency', ...]
 *   $defaults      array   key => [value, type, description]
 *
 * POST endpoints:
 *   • /admin/settings/update                (bulk form)
 *   • /admin/settings/update-single         (inline AJAX)
 *   • /admin/settings/store                 (create)
 *   • /admin/settings/delete                (delete custom)
 *   • /admin/settings/reset                 (reset one / all)
 * ----------------------------------------------------------
 */

$currentPage = 'settings';
$title       = 'System Settings';

// Safe defaults
$grouped       = $grouped       ?? [];
$stats         = $stats         ?? ['total'=>0, 'defaults'=>0, 'customized'=>0, 'last_updated'=>null];
$dataTypes     = $dataTypes     ?? ['string', 'integer', 'boolean', 'json', 'decimal'];
$protectedKeys = $protectedKeys ?? [];
$defaults      = $defaults      ?? [];

$e           = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtInt      = fn($n) => number_format((int) $n);
$fmtDateTime = fn($d) => $d ? date('M j, Y · g:i A', strtotime($d)) : '—';

// Helpers
$keyLabel = fn(string $key): string => ucwords(str_replace(['_', '-'], ' ', $key));

// Input type for a data_type (matches controller's helper)
$inputType = function (string $type): string {
    return match ($type) {
        'integer', 'decimal' => 'number',
        'boolean' => 'checkbox',
        'json'    => 'textarea',
        default   => 'text',
    };
};

// Step for numeric inputs
$inputStep = function (string $type): ?string {
    return match ($type) {
        'integer' => '1',
        'decimal' => '0.01',
        default   => null,
    };
};

// Badge color for data_type
$typeBadge = function (string $type): string {
    return match ($type) {
        'string'  => 'primary',
        'integer' => 'info',
        'decimal' => 'warning',
        'boolean' => 'success',
        'json'    => 'dark',
        default   => 'secondary',
    };
};

// Human label for value (for display)
$displayValue = function (string $value, string $type): string {
    if ($type === 'boolean') {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false';
    }
    if ($value === '') return '—';
    if ($type === 'json') {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? json_encode($decoded) : $value;
    }
    return $value;
};

// Whether this key is protected
$isProtected = fn(string $key): bool => in_array($key, $protectedKeys, true);

// Whether a setting has been customized (updated_at > created_at)
$isCustomized = function (array $s): bool {
    if (empty($s['created_at']) || empty($s['updated_at'])) return false;
    return $s['updated_at'] > $s['created_at'];
};

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<!-- ==================== PAGE HEADER ==================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="page-title mb-1">
            <i class="bi bi-gear me-2 text-primary"></i>System Settings
        </h1>
        <p class="text-muted mb-0 small">
            Configure business rules, defaults, and system behavior
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/settings/export"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <button type="button"
                class="btn btn-outline-primary btn-sm"
                onclick="openNewKeyModal()">
            <i class="bi bi-plus-lg me-1"></i>Add Custom Setting
        </button>
        <button type="button"
                class="btn btn-outline-secondary btn-sm"
                onclick="openResetAllModal()">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset All to Defaults
        </button>
    </div>
</div>

<!-- ==================== STATS BAR ==================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-sliders"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total Keys</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['total'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $fmtInt(count($defaults)) ?> known defaults
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
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Customized</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt($stats['customized'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            since defaults
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
                        <i class="bi bi-key"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Protected</div>
                        <div class="fw-bold fs-5 mb-0"><?= $fmtInt(count($protectedKeys)) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            cannot be deleted
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
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Last Updated</div>
                        <div class="fw-bold small mb-0" style="font-size:.88rem !important;">
                            <?= $e($fmtDateTime($stats['last_updated'] ?? null)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== BULK SAVE FORM ==================== -->
<form id="settingsForm" method="post" action="/admin/settings/update">
    <input type="hidden" name="_csrf" value="<?= $e($_SESSION['_csrf'] ?? '') ?>">

    <?php if (empty($grouped)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-sliders fs-1 d-block mb-3 opacity-50"></i>
                <div class="fw-medium mb-2">No settings available</div>
                <div class="small">
                    Use "Add Custom Setting" to create your first configuration key.
                </div>
            </div>
        </div>
    <?php else: foreach ($grouped as $slug => $group):
        $settings = $group['settings'] ?? [];
        if (empty($settings)) continue;
    ?>
        <div class="card border-0 shadow-sm mb-4 settings-group" data-group="<?= $e($slug) ?>">
            <div class="card-header bg-white border-0 d-flex align-items-center gap-2">
                <div class="group-icon bg-primary-subtle text-primary">
                    <i class="bi <?= $e($group['icon'] ?? 'bi-gear') ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-semibold"><?= $e($group['label'] ?? $slug) ?></h6>
                    <?php if (!empty($group['description'])): ?>
                        <div class="text-muted small"><?= $e($group['description']) ?></div>
                    <?php endif; ?>
                </div>
                <span class="badge bg-light text-dark border">
                    <?= count($settings) ?> setting<?= count($settings) === 1 ? '' : 's' ?>
                </span>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($settings as $s):
                        $key         = (string) ($s['config_key'] ?? '');
                        $value       = (string) ($s['config_value'] ?? '');
                        $type        = (string) ($s['data_type'] ?? 'string');
                        $description = (string) ($s['description'] ?? '');
                        $isMissing   = !empty($s['is_missing']);
                        $isCustom    = $isCustomized($s);
                        $typeIsBool  = ($type === 'boolean');
                        $typeIsNum   = in_array($type, ['integer', 'decimal'], true);
                        $typeIsJson  = ($type === 'json');
                        $hasDefault  = isset($defaults[$key]);
                        $prot        = $isProtected($key);
                    ?>
                        <div class="col-md-6 setting-item" data-key="<?= $e($key) ?>">
                            <div class="setting-card <?= $isMissing ? 'border-warning' : '' ?>">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div class="min-w-0">
                                        <div class="setting-label d-flex align-items-center gap-1 flex-wrap">
                                            <code class="text-dark"><?= $e($key) ?></code>
                                            <span class="badge bg-<?= $typeBadge($type) ?>-subtle text-<?= $typeBadge($type) ?>"
                                                  style="font-size:.6rem;">
                                                <?= $e($type) ?>
                                            </span>
                                            <?php if ($isMissing): ?>
                                                <span class="badge bg-warning" style="font-size:.6rem;">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>missing
                                                </span>
                                            <?php elseif ($isCustom): ?>
                                                <span class="badge bg-info-subtle text-info" style="font-size:.6rem;"
                                                      title="Modified from default">
                                                    <i class="bi bi-pencil me-1"></i>modified
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <?= $e($description ?: $keyLabel($key)) ?>
                                        </div>
                                    </div>
                                    <div class="btn-group btn-group-sm flex-shrink-0">
                                        <?php if ($hasDefault && !$isMissing): ?>
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    title="Reset to default"
                                                    onclick="resetSingle('<?= $e($key) ?>', <?= json_encode($defaults[$key][0] ?? '', $jsonFlags) ?>)">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!$prot): ?>
                                            <button type="button"
                                                    class="btn btn-outline-danger btn-sm"
                                                    title="Delete setting"
                                                    onclick="deleteSingle('<?= $e($key) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    title="Protected — cannot delete"
                                                    disabled>
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($typeIsBool): ?>
                                    <!-- Boolean toggle -->
                                    <input type="hidden" name="settings[<?= $e($key) ?>]" value="false">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input setting-input"
                                               type="checkbox"
                                               id="set_<?= $e($key) ?>"
                                               name="settings[<?= $e($key) ?>]"
                                               value="true"
                                               data-key="<?= $e($key) ?>"
                                               <?= strtolower($value) === 'true' ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="set_<?= $e($key) ?>">
                                            <?= strtolower($value) === 'true' ? 'Enabled' : 'Disabled' ?>
                                        </label>
                                    </div>
                                <?php elseif ($typeIsJson): ?>
                                    <textarea class="form-control form-control-sm font-monospace setting-input"
                                              name="settings[<?= $e($key) ?>]"
                                              data-key="<?= $e($key) ?>"
                                              rows="3"
                                              placeholder="JSON value"><?= $e($value) ?></textarea>
                                <?php else: ?>
                                    <input class="form-control form-control-sm setting-input"
                                           type="<?= $e($inputType($type)) ?>"
                                           name="settings[<?= $e($key) ?>]"
                                           value="<?= $e($displayValue($value, $type)) ?>"
                                           data-key="<?= $e($key) ?>"
                                           data-original="<?= $e($displayValue($value, $type)) ?>"
                                           <?php if ($inputStep($type)): ?>step="<?= $e($inputStep($type)) ?>"<?php endif; ?>>
                                <?php endif; ?>

                                <div class="invalid-feedback" data-field="<?= $e($key) ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <!-- ==================== STICKY SAVE BAR ==================== -->
    <div class="sticky-save-bar" id="stickySaveBar" style="display:none;">
        <div class="container-fluid d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-circle-fill text-warning fs-5"></i>
                <div class="small">
                    <strong id="dirtyCount">0</strong>
                    <span id="dirtyLabel">unsaved change(s)</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetForm()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Discard
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Save All Changes
                </button>
            </div>
        </div>
    </div>
</form>

<!-- ==================== MODAL: ADD CUSTOM SETTING ==================== -->
<div class="modal fade" id="newKeyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="newKeyForm" method="post" action="/admin/settings/store" novalidate>
                <input type="hidden" name="_csrf" value="<?= $e($_SESSION['_csrf'] ?? '') ?>">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Add Custom Setting
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_key" class="form-label small fw-medium">
                            Config Key <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control font-monospace"
                               id="new_key"
                               name="config_key"
                               maxlength="100"
                               pattern="[a-z][a-z0-9_]{2,99}"
                               placeholder="e.g., custom_promo_code"
                               required>
                        <div class="invalid-feedback" data-field="config_key"></div>
                        <div class="form-text small">
                            Must start with a letter, only lowercase + digits + underscores (3–100 chars).
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label for="new_value" class="form-label small fw-medium">
                                Value <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="new_value"
                                   name="config_value"
                                   required>
                            <div class="invalid-feedback" data-field="config_value"></div>
                        </div>
                        <div class="col-4">
                            <label for="new_type" class="form-label small fw-medium">
                                Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="new_type" name="data_type">
                                <?php foreach ($dataTypes as $t): ?>
                                    <option value="<?= $e($t) ?>"><?= $e(ucfirst($t)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="new_desc" class="form-label small fw-medium">
                            Description
                            <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="new_desc"
                               name="description"
                               maxlength="255"
                               placeholder="Short description of what this setting controls">
                        <div class="invalid-feedback" data-field="description"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="newKeySaveBtn">
                        <i class="bi bi-plus-lg me-1"></i>Create Setting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: RESET ALL ==================== -->
<div class="modal fade" id="resetAllModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Reset All to Defaults
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>This will reset all known settings to their default values.</strong>
                </div>
                <p class="small mb-2">
                    <strong><?= $fmtInt(count($defaults)) ?></strong> setting(s) will be reset:
                </p>
                <ul class="small text-muted ps-3 mb-0">
                    <li>Custom settings you created will <strong>not</strong> be affected</li>
                    <li>All other settings will return to their initial seed values</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="resetAllConfirm">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset <?= count($defaults) ?> Setting(s)
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
    .group-icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }
    .min-w-0 { min-width: 0; }

    .setting-card {
        padding: .85rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        height: 100%;
        transition: box-shadow .15s, border-color .15s;
    }
    .setting-card:hover {
        box-shadow: 0 4px 12px rgba(15,23,42,.06);
        border-color: #cbd5e1;
    }
    .setting-card.border-warning {
        background: #fffbeb;
        border-color: #fbbf24 !important;
    }
    .setting-label code {
        font-size: .8rem;
        padding: 1px 6px;
        background: #f1f5f9;
        border-radius: 4px;
    }
    .setting-item.is-dirty .setting-card {
        border-color: #0ea5e9;
        background: #f0f9ff;
        box-shadow: 0 0 0 3px rgba(14,165,233,.08);
    }

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
        .sticky-save-bar { left: 250px; }  /* match sidebar width */
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

    const form         = document.getElementById('settingsForm');
    const stickyBar    = document.getElementById('stickySaveBar');
    const dirtyCountEl = document.getElementById('dirtyCount');
    const dirtyLabelEl = document.getElementById('dirtyLabel');

    // ==================== DIRTY TRACKING ====================
    function collectDirty() {
        const dirty = [];
        form.querySelectorAll('.setting-input').forEach(input => {
            if (input.type === 'checkbox') {
                // Compare checked state to data-original / default
                const isDirty = input.dataset.original === 'true'
                    ? !input.checked
                    : input.checked;
                if (isDirty) dirty.push(input);
            } else {
                const orig = (input.dataset.original ?? '').trim();
                const cur  = (input.value ?? '').trim();
                if (orig !== cur) dirty.push(input);
            }
        });
        return dirty;
    }

    function refreshDirtyState() {
        const dirty = collectDirty();

        // Highlight dirty items
        document.querySelectorAll('.setting-item').forEach(el => el.classList.remove('is-dirty'));
        dirty.forEach(input => {
            const item = input.closest('.setting-item');
            if (item) item.classList.add('is-dirty');
        });

        // Sticky bar
        if (dirty.length > 0) {
            dirtyCountEl.textContent = dirty.length;
            dirtyLabelEl.textContent = dirty.length === 1 ? 'unsaved change' : 'unsaved changes';
            stickyBar.style.display = 'block';
        } else {
            stickyBar.style.display = 'none';
        }
    }

    // Listen for input changes (delegated)
    form.addEventListener('input', (e) => {
        if (e.target.classList.contains('setting-input')) {
            refreshDirtyState();
        }
    });
    form.addEventListener('change', (e) => {
        if (e.target.classList.contains('setting-input')) {
            refreshDirtyState();
        }
    });

    // Initial paint
    refreshDirtyState();

    // ==================== BULK SAVE ====================
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        clearErrors();

        const dirty = collectDirty();
        if (dirty.length === 0) {
            showToast('No changes to save.', 'info');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        const origHTML = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            const formData = new FormData(form);

            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Settings saved.', 'success');

                // Update data-original for all inputs
                form.querySelectorAll('.setting-input').forEach(input => {
                    if (input.type !== 'checkbox') {
                        input.dataset.original = input.value;
                    }
                });
                refreshDirtyState();
                setTimeout(() => location.reload(), 700);
            } else {
                if (json.errors && typeof json.errors === 'object') {
                    showErrors(json.errors);
                }
                showToast(json.message || 'Please fix the highlighted fields.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = origHTML;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = origHTML;
        }
    });

    // ==================== DISCARD ====================
    window.resetForm = function () {
        if (!confirm('Discard all unsaved changes?')) return;

        form.querySelectorAll('.setting-input').forEach(input => {
            if (input.type === 'checkbox') {
                input.checked = (input.dataset.original === 'true');
            } else {
                input.value = input.dataset.original ?? '';
            }
        });
        refreshDirtyState();
    };

    // ==================== INLINE RESET (single key) ====================
    window.resetSingle = async function (key, defaultValue) {
        if (!confirm(`Reset "${key}" to its default value?`)) return;

        try {
            const body = new URLSearchParams({
                _csrf: csrf,
                key: key,
                scope: 'single',
            });

            const res = await fetch('/admin/settings/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Setting reset.', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(json.message || 'Failed.', 'danger');
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
        }
    };

    // ==================== INLINE DELETE (single key) ====================
    window.deleteSingle = async function (key) {
        if (!confirm(`Delete "${key}"? This cannot be undone.`)) return;

        try {
            const body = new URLSearchParams({ _csrf: csrf, key: key });

            const res = await fetch('/admin/settings/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });

            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Setting deleted.', 'success');
                // Remove DOM node
                const item = document.querySelector(`.setting-item[data-key="${CSS.escape(key)}"]`);
                if (item) item.remove();
            } else {
                showToast(json.message || 'Failed.', 'danger');
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
        }
    };

    // ==================== NEW KEY MODAL ====================
    const newKeyModal = new bootstrap.Modal(document.getElementById('newKeyModal'));
    const newKeyForm  = document.getElementById('newKeyForm');
    const newKeyBtn   = document.getElementById('newKeySaveBtn');

    window.openNewKeyModal = function () {
        newKeyForm.reset();
        clearNewKeyErrors();
        newKeyModal.show();
    };

    newKeyForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        clearNewKeyErrors();

        const key   = newKeyForm.querySelector('[name="config_key"]').value.trim();
        const value = newKeyForm.querySelector('[name="config_value"]').value;
        const errs  = {};

        if (!/^[a-z][a-z0-9_]{2,99}$/.test(key)) {
            errs.config_key = 'Key must start with a letter and contain only a–z, 0–9, and underscores (3–100 chars).';
        }

        if (value === '' && newKeyForm.querySelector('[name="data_type"]').value !== 'boolean') {
            errs.config_value = 'Value is required.';
        }

        if (Object.keys(errs).length) {
            showNewKeyErrors(errs);
            return;
        }

        newKeyBtn.disabled = true;
        const orig = newKeyBtn.innerHTML;
        newKeyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            const formData = new FormData(newKeyForm);
            const res = await fetch(newKeyForm.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Setting created.', 'success');
                newKeyModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                if (json.errors && typeof json.errors === 'object') {
                    showNewKeyErrors(json.errors);
                }
                showToast(json.message || 'Failed.', 'danger');
                newKeyBtn.disabled = false;
                newKeyBtn.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            newKeyBtn.disabled = false;
            newKeyBtn.innerHTML = orig;
        }
    });

    function clearNewKeyErrors() {
        newKeyForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        newKeyForm.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
    }

    function showNewKeyErrors(errs) {
        for (const [field, message] of Object.entries(errs)) {
            const input = newKeyForm.querySelector('[name="' + field + '"]');
            const feedback = newKeyForm.querySelector('.invalid-feedback[data-field="' + field + '"]');
            if (input) input.classList.add('is-invalid');
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }
    }

    // ==================== RESET ALL MODAL ====================
    const resetAllModal  = new bootstrap.Modal(document.getElementById('resetAllModal'));
    const resetAllBtn    = document.getElementById('resetAllConfirm');

    window.openResetAllModal = function () {
        resetAllModal.show();
    };

    resetAllBtn.addEventListener('click', async () => {
        resetAllBtn.disabled = true;
        const orig = resetAllBtn.innerHTML;
        resetAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Resetting…';

        try {
            const body = new URLSearchParams({ _csrf: csrf, scope: 'all' });

            const res = await fetch('/admin/settings/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const json = await res.json();

            if (json.success) {
                showToast(json.message || 'Settings reset.', 'success');
                resetAllModal.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(json.message || 'Failed.', 'danger');
                resetAllBtn.disabled = false;
                resetAllBtn.innerHTML = orig;
            }
        } catch (err) {
            console.error(err);
            showToast('Network error.', 'danger');
            resetAllBtn.disabled = false;
            resetAllBtn.innerHTML = orig;
        }
    });

    // ==================== BULK ERROR DISPLAY ====================
    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
    }

    function showErrors(errs) {
        for (const [field, message] of Object.entries(errs)) {
            const input = form.querySelector('[name="settings[' + field + ']"]');
            const feedback = form.querySelector('.invalid-feedback[data-field="' + field + '"]');
            if (input) input.classList.add('is-invalid');
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }

        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
        }
    }

    // ==================== UNSAVED CHANGES WARNING ====================
    window.addEventListener('beforeunload', (e) => {
        if (collectDirty().length > 0) {
            e.preventDefault();
            e.returnValue = '';
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
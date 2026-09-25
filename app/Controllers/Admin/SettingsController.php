<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin\SettingsController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: System-wide configuration management via the
 *          `system_config` key-value table.
 *            • View settings grouped by category
 *            • Bulk update (Save All)
 *            • Inline single-key update (AJAX)
 *            • Create new custom config keys
 *            • Delete custom keys (protected keys are locked)
 *            • Reset to default (per key or all)
 *            • Import/export via CSV
 *
 * Notes:
 *   • Zero model dependency — uses raw SQL.
 *   • Types are validated based on data_type column:
 *       string | integer | decimal | boolean | json
 *   • Every change is audited.
 *   • "Defaults" live in a constant so reset works even
 *     if the table is empty.
 * ----------------------------------------------------------
 */
class SettingsController extends Controller
{
    /**
     * Grouping for the UI. Keys not in any group appear
     * under "Other". Order here determines display order.
     */
    private const GROUPS = [
        'business' => [
            'label'       => 'Business Information',
            'description' => 'Basic business identity and contact info.',
            'icon'        => 'bi-shop',
            'keys'        => [
                'business_name',
                'business_email',
                'business_phone',
                'business_open_time',
                'business_close_time',
                'currency',
                'currency_symbol',
            ],
        ],
        'operations' => [
            'label'       => 'Operations',
            'description' => 'Pickup, delivery, and order processing rules.',
            'icon'        => 'bi-sliders',
            'keys'        => [
                'max_pickup_distance_km',
                'default_delivery_days',
                'same_day_enabled',
                'max_items_per_order',
                'express_surcharge_pct',
            ],
        ],
        'financial' => [
            'label'       => 'Financial',
            'description' => 'Taxes, fees, and monetary defaults.',
            'icon'        => 'bi-cash-stack',
            'keys'        => [
                'tax_percentage',
                'late_fee_amount',
            ],
        ],
        'customer' => [
            'label'       => 'Customer Rules',
            'description' => 'Policies affecting customer experience.',
            'icon'        => 'bi-people',
            'keys'        => [
                'complaint_window_days',
            ],
        ],
    ];

    /**
     * Default values seeded into system_config. Used by
     * reset() to restore a key or all keys.
     * data_type is enforced from here if creating a new key.
     */
    private const DEFAULTS = [
        'business_name'          => ['LaundryPro',              'string',  'Business display name'],
        'business_email'         => ['info@laundrypro.com',     'string',  'Business contact email'],
        'business_phone'         => ['03001234567',             'string',  'Business contact phone'],
        'business_open_time'     => ['09:00',                   'string',  'Daily opening time (HH:MM)'],
        'business_close_time'    => ['21:00',                   'string',  'Daily closing time (HH:MM)'],
        'currency'               => ['PKR',                     'string',  'Currency code'],
        'currency_symbol'        => ['Rs.',                     'string',  'Currency display symbol'],
        'max_pickup_distance_km' => ['10',                      'integer', 'Maximum pickup radius (km)'],
        'default_delivery_days'  => ['3',                       'integer', 'Default delivery duration (days)'],
        'same_day_enabled'       => ['true',                    'boolean', 'Allow same-day pickup requests'],
        'max_items_per_order'    => ['50',                      'integer', 'Maximum items per order'],
        'express_surcharge_pct'  => ['50',                      'decimal', 'Express service surcharge (%)'],
        'tax_percentage'         => ['5',                       'decimal', 'Tax percentage on bills'],
        'late_fee_amount'        => ['100',                     'decimal', 'Late pickup/delivery fee'],
        'complaint_window_days'  => ['7',                       'integer', 'Days after delivery to file a complaint'],
    ];

    /**
     * Keys that cannot be deleted even if unused.
     * Prevents admins from bricking the app.
     */
    private const PROTECTED_KEYS = [
        'business_name',
        'currency',
        'currency_symbol',
        'tax_percentage',
    ];

    /** Valid data_type values from schema ENUM */
    private const DATA_TYPES = ['string', 'integer', 'boolean', 'json', 'decimal'];

    // =========================================================
    //  INDEX — Grouped settings view
    // =========================================================
    public function index(): void
    {
        $this->requireAdmin();

        $all = $this->loadAllKeyed();

        // Build the grouped display model
        $grouped = [];
        $seen    = [];

        foreach (self::GROUPS as $slug => $group) {
            $rows = [];
            foreach ($group['keys'] as $key) {
                if (isset($all[$key])) {
                    $rows[]    = $all[$key];
                    $seen[$key] = true;
                } else {
                    // Key expected by UI but missing in DB → synthesize
                    $defaults = self::DEFAULTS[$key] ?? [null, 'string', null];
                    $rows[] = [
                        'config_id'          => null,
                        'config_key'         => $key,
                        'config_value'       => $defaults[0],
                        'data_type'          => $defaults[1],
                        'description'        => $defaults[2],
                        'updated_by_staff_id'=> null,
                        'created_at'         => null,
                        'updated_at'         => null,
                        'is_missing'         => true,
                    ];
                    $seen[$key] = true;
                }
            }

            $grouped[$slug] = [
                'label'       => $group['label'],
                'description' => $group['description'],
                'icon'        => $group['icon'],
                'settings'    => $rows,
            ];
        }

        // Any remaining keys not in a defined group → "Other"
        $others = [];
        foreach ($all as $key => $row) {
            if (!isset($seen[$key])) {
                $others[] = $row;
            }
        }

        if (!empty($others)) {
            $grouped['other'] = [
                'label'       => 'Other Settings',
                'description' => 'Uncategorized configuration keys.',
                'icon'        => 'bi-three-dots',
                'settings'    => $others,
            ];
        }

        $stats = $this->stats();

        $this->view('admin/settings/index', [
            'grouped'       => $grouped,
            'stats'         => $stats,
            'dataTypes'     => self::DATA_TYPES,
            'protectedKeys' => self::PROTECTED_KEYS,
            'defaults'      => self::DEFAULTS,
        ]);
    }

    // =========================================================
    //  UPDATE — Bulk save from the settings form
    // =========================================================
    public function update(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $incoming = $_POST['settings'] ?? [];
        if (!is_array($incoming) || empty($incoming)) {
            $this->jsonError('No settings submitted.', 422);
            return;
        }

        $staffId = (int) $this->currentStaffId();

        // Load current keys once
        $current = $this->loadAllKeyed();

        // Validate everything first
        $clean  = [];
        $errors = [];

        foreach ($incoming as $key => $rawValue) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            // Determine data_type from existing row or defaults
            $dataType = $current[$key]['data_type']
                     ?? (self::DEFAULTS[$key][1] ?? 'string');

            $result = $this->validateValue($rawValue, $dataType);
            if ($result['error']) {
                $errors[$key] = $result['error'];
                continue;
            }

            $clean[$key] = [
                'value'     => $result['normalized'],
                'data_type' => $dataType,
            ];
        }

        if (!empty($errors)) {
            $this->jsonError(
                'Please fix the highlighted fields.',
                422,
                ['errors' => $errors]
            );
            return;
        }

        if (empty($clean)) {
            $this->jsonError('No valid settings to save.', 422);
            return;
        }

        // Persist atomically
        try {
            $this->db->beginTransaction();

            $updated = 0;
            foreach ($clean as $key => $payload) {
                $old = $current[$key]['config_value'] ?? null;

                $this->upsertKey(
                    $key,
                    $payload['value'],
                    $payload['data_type'],
                    $current[$key]['description'] ?? (self::DEFAULTS[$key][2] ?? null),
                    $staffId
                );

                if ($old !== (string) $payload['value']) {
                    $this->audit('system', 'setting_update', $current[$key]['config_id'] ?? 0, [
                        'key'   => $key,
                        'value' => $old,
                    ], [
                        'key'   => $key,
                        'value' => (string) $payload['value'],
                    ]);
                }
                $updated++;
            }

            $this->db->commit();

            $this->jsonSuccess([
                'message' => "{$updated} setting(s) saved.",
                'updated' => $updated,
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[AdminSettingsController::update] ' . $e->getMessage());
            $this->jsonError('Failed to save settings.', 500);
        }
    }

    // =========================================================
    //  UPDATE SINGLE — Inline AJAX save
    // =========================================================
    public function updateSingle(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $key      = trim($_POST['key']   ?? '');
        $rawValue = $_POST['value']      ?? '';

        if ($key === '') {
            $this->jsonError('Missing config key.', 422);
            return;
        }

        $current  = $this->loadAllKeyed();
        $dataType = $current[$key]['data_type']
                 ?? (self::DEFAULTS[$key][1] ?? 'string');

        $result = $this->validateValue($rawValue, $dataType);
        if ($result['error']) {
            $this->jsonError($result['error'], 422);
            return;
        }

        $staffId = (int) $this->currentStaffId();

        try {
            $old = $current[$key]['config_value'] ?? null;

            $this->upsertKey(
                $key,
                $result['normalized'],
                $dataType,
                $current[$key]['description'] ?? (self::DEFAULTS[$key][2] ?? null),
                $staffId
            );

            $this->audit('system', 'setting_update', $current[$key]['config_id'] ?? 0, [
                'key'   => $key,
                'value' => $old,
            ], [
                'key'   => $key,
                'value' => (string) $result['normalized'],
            ]);

            $this->jsonSuccess([
                'message' => 'Setting saved.',
                'key'     => $key,
                'value'   => $result['normalized'],
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminSettingsController::updateSingle] ' . $e->getMessage());
            $this->jsonError('Failed to save setting.', 500);
        }
    }

    // =========================================================
    //  STORE — Create a new custom config key
    // =========================================================
    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $key         = trim($_POST['config_key']   ?? '');
        $value       = $_POST['config_value']      ?? '';
        $dataType    = $_POST['data_type']         ?? '';
        $description = trim($_POST['description']  ?? '');

        // ---- Validate ----
        $errors = [];

        if ($key === '') {
            $errors['config_key'] = 'Config key is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]{2,99}$/i', $key)) {
            $errors['config_key'] = 'Key must start with a letter and contain only letters, numbers, and underscores (3-100 chars).';
        }

        if (!in_array($dataType, self::DATA_TYPES, true)) {
            $errors['data_type'] = 'Invalid data type.';
        }

        if ($description !== '' && mb_strlen($description) > 255) {
            $errors['description'] = 'Description must not exceed 255 characters.';
        }

        // Uniqueness
        $stmt = $this->db->prepare(
            "SELECT 1 FROM system_config WHERE config_key = :k LIMIT 1"
        );
        $stmt->execute(['k' => $key]);
        if ($stmt->fetchColumn()) {
            $errors['config_key'] = 'A setting with this key already exists.';
        }

        // Value validation
        if (empty($errors['data_type'])) {
            $result = $this->validateValue($value, $dataType);
            if ($result['error']) {
                $errors['config_value'] = $result['error'];
            } else {
                $normalized = $result['normalized'];
            }
        }

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        $staffId = (int) $this->currentStaffId();

        try {
            $this->upsertKey(
                $key,
                $normalized,
                $dataType,
                $description ?: null,
                $staffId
            );

            $newId = (int) $this->db->lastInsertId();

            $this->audit('system', 'setting_create', $newId, null, [
                'key'       => $key,
                'value'     => (string) $normalized,
                'data_type' => $dataType,
            ]);

            $this->jsonSuccess([
                'message'   => 'Setting created.',
                'config_id' => $newId,
                'key'       => $key,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminSettingsController::store] ' . $e->getMessage());
            $this->jsonError('Failed to create setting.', 500);
        }
    }

    // =========================================================
    //  DELETE — Remove a custom config key
    // =========================================================
    public function delete(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $key = trim($_POST['key'] ?? '');
        if ($key === '') {
            $this->jsonError('Missing config key.', 422);
            return;
        }

        if (in_array($key, self::PROTECTED_KEYS, true)) {
            $this->jsonError("This key is protected and cannot be deleted.", 422);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM system_config WHERE config_key = :k LIMIT 1"
        );
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->jsonError('Setting not found.', 404);
            return;
        }

        try {
            $this->db->prepare(
                "DELETE FROM system_config WHERE config_key = :k"
            )->execute(['k' => $key]);

            $this->audit('system', 'setting_delete', (int) $row['config_id'],
                ['key' => $key, 'value' => $row['config_value']],
                null
            );

            $this->jsonSuccess([
                'message' => 'Setting deleted.',
                'key'     => $key,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminSettingsController::delete] ' . $e->getMessage());
            $this->jsonError('Failed to delete setting.', 500);
        }
    }

    // =========================================================
    //  RESET — Restore default value (per key or all)
    // =========================================================
    public function reset(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $key    = trim($_POST['key'] ?? '');
        $scope  = $_POST['scope']   ?? 'single'; // 'single' | 'all'

        $staffId = (int) $this->currentStaffId();

        if ($scope === 'all') {
            try {
                $this->db->beginTransaction();
                $count = $this->resetAllToDefaults($staffId);
                $this->db->commit();

                $this->audit('system', 'settings_reset_all', 0, null,
                    ['keys_reset' => $count]);

                $this->jsonSuccess([
                    'message' => "{$count} setting(s) reset to defaults.",
                    'count'   => $count,
                ]);

            } catch (\Throwable $e) {
                $this->db->rollBack();
                error_log('[AdminSettingsController::reset:all] ' . $e->getMessage());
                $this->jsonError('Failed to reset settings.', 500);
            }
            return;
        }

        // Single key
        if ($key === '') {
            $this->jsonError('Missing config key.', 422);
            return;
        }

        if (!isset(self::DEFAULTS[$key])) {
            $this->jsonError('No default is defined for this key.', 422);
            return;
        }

        [$defValue, $defType, $defDesc] = self::DEFAULTS[$key];

        $current = $this->loadAllKeyed();
        $old     = $current[$key]['config_value'] ?? null;

        try {
            $this->upsertKey($key, $defValue, $defType, $defDesc, $staffId);

            $this->audit('system', 'setting_reset', (int) ($current[$key]['config_id'] ?? 0),
                ['key' => $key, 'value' => $old],
                ['key' => $key, 'value' => $defValue]
            );

            $this->jsonSuccess([
                'message' => 'Setting reset to default.',
                'key'     => $key,
                'value'   => $defValue,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminSettingsController::reset] ' . $e->getMessage());
            $this->jsonError('Failed to reset setting.', 500);
        }
    }

    // =========================================================
    //  EXPORT — CSV of current config
    // =========================================================
    public function export(): void
    {
        $this->requireAdmin();

        $rows = $this->db->query(
            "SELECT config_key, config_value, data_type, description,
                    created_at, updated_at
             FROM system_config
             ORDER BY config_key ASC"
        )->fetchAll();

        $filename = 'settings_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, ['Key', 'Value', 'Type', 'Description', 'Created', 'Updated']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['config_key'],
                $r['config_value'],
                $r['data_type'],
                $r['description'] ?? '',
                $r['created_at'],
                $r['updated_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================================================
    //  PUBLIC HELPER — used by other parts of the app
    //  (kept here so config changes are always consistent)
    // =========================================================

    /**
     * Static-like accessor usable from views/controllers.
     * Returns typed value (int, float, bool, array, string)
     * or the provided default.
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            try {
                $db   = \App\Core\Database::getConnection();
                $rows = $db->query(
                    "SELECT config_key, config_value, data_type FROM system_config"
                )->fetchAll();
                foreach ($rows as $r) {
                    $cache[$r['config_key']] = $r;
                }
            } catch (\Throwable $e) {
                // DB down — return default
                return $default;
            }
        }

        if (!isset($cache[$key])) {
            return $default;
        }

        return match ($cache[$key]['data_type']) {
            'integer' => (int)   $cache[$key]['config_value'],
            'decimal' => (float) $cache[$key]['config_value'],
            'boolean' => in_array(
                            strtolower($cache[$key]['config_value']),
                            ['1', 'true', 'yes', 'on'],
                            true
                         ),
            'json'    => json_decode($cache[$key]['config_value'], true),
            default   => $cache[$key]['config_value'],
        };
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    /**
     * Load all system_config rows, keyed by config_key.
     */
    private function loadAllKeyed(): array
    {
        $rows = $this->db->query(
            "SELECT * FROM system_config ORDER BY config_key ASC"
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['config_key']] = $r;
        }
        return $out;
    }

    /**
     * Insert or update a config key.
     * Uses ON DUPLICATE KEY UPDATE so it works even if the
     * row doesn't yet exist (e.g., UI synthesized default).
     */
    private function upsertKey(
        string $key,
        mixed $value,
        string $dataType,
        ?string $description,
        int $staffId
    ): void {
        $serialized = $this->serializeValue($value, $dataType);

        $sql = "INSERT INTO system_config
                    (config_key, config_value, data_type, description, updated_by_staff_id)
                VALUES
                    (:key, :val, :type, :desc, :staff)
                ON DUPLICATE KEY UPDATE
                    config_value         = VALUES(config_value),
                    data_type            = VALUES(data_type),
                    description          = COALESCE(VALUES(description), description),
                    updated_by_staff_id  = VALUES(updated_by_staff_id),
                    updated_at           = CURRENT_TIMESTAMP";

        $this->db->prepare($sql)->execute([
            'key'   => $key,
            'val'   => $serialized,
            'type'  => $dataType,
            'desc'  => $description,
            'staff' => $staffId ?: null,
        ]);
    }

    /**
     * Reset every key in DEFAULTS. Returns count reset.
     */
    private function resetAllToDefaults(int $staffId): int
    {
        $count = 0;
        foreach (self::DEFAULTS as $key => [$value, $type, $desc]) {
            $this->upsertKey($key, $value, $type, $desc, $staffId);
            $count++;
        }
        return $count;
    }

    /**
     * Validate a raw input value against a data_type.
     * Returns ['error' => ?string, 'normalized' => mixed].
     */
    private function validateValue(mixed $raw, string $dataType): array
    {
        // Treat null as empty string for non-json types
        if ($raw === null) {
            $raw = '';
        }

        // Arrays (from checkbox groups) — flatten safely
        if (is_array($raw)) {
            $raw = implode(',', array_map(
                fn($v) => is_scalar($v) ? (string) $v : '',
                $raw
            ));
        }

        $raw = trim((string) $raw);

        switch ($dataType) {
            case 'string':
                if (mb_strlen($raw) > 1000) {
                    return ['error' => 'String value must not exceed 1000 characters.', 'normalized' => null];
                }
                return ['error' => null, 'normalized' => $raw];

            case 'integer':
                if ($raw === '') {
                    return ['error' => 'Integer value is required.', 'normalized' => null];
                }
                if (!preg_match('/^-?\d+$/', $raw)) {
                    return ['error' => 'Value must be a whole number.', 'normalized' => null];
                }
                $n = (int) $raw;
                if ($n < -2147483648 || $n > 2147483647) {
                    return ['error' => 'Integer value out of range.', 'normalized' => null];
                }
                return ['error' => null, 'normalized' => $n];

            case 'decimal':
                if ($raw === '') {
                    return ['error' => 'Decimal value is required.', 'normalized' => null];
                }
                if (!is_numeric($raw)) {
                    return ['error' => 'Value must be a number.', 'normalized' => null];
                }
                $f = (float) $raw;
                if (!is_finite($f)) {
                    return ['error' => 'Value must be a finite number.', 'normalized' => null];
                }
                if (abs($f) > 9999999999.99) {
                    return ['error' => 'Decimal value out of range.', 'normalized' => null];
                }
                return ['error' => null, 'normalized' => $f];

            case 'boolean':
                $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    return ['error' => 'Value must be true or false.', 'normalized' => null];
                }
                return ['error' => null, 'normalized' => $bool];

            case 'json':
                if ($raw === '') {
                    return ['error' => null, 'normalized' => '{}'];
                }
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return ['error' => 'Invalid JSON: ' . json_last_error_msg(), 'normalized' => null];
                }
                return ['error' => null, 'normalized' => $decoded];

            default:
                return ['error' => 'Unknown data type.', 'normalized' => null];
        }
    }

    /**
     * Serialize a normalized value back to a TEXT storage string.
     */
    private function serializeValue(mixed $value, string $dataType): string
    {
        return match ($dataType) {
            'boolean' => $value ? 'true' : 'false',
            'json'    => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            'integer' => (string) (int)   $value,
            'decimal' => (string) (float) $value,
            default   => (string) $value,
        };
    }

    /**
     * Config table stats for the header.
     */
    private function stats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN updated_at > created_at THEN 1 ELSE 0 END) AS customized,
                MAX(updated_at) AS last_updated
             FROM system_config"
        )->fetch() ?: [];

        return [
            'total'        => (int)    ($row['total']        ?? 0),
            'defaults'     => count(self::DEFAULTS),
            'customized'   => (int)    ($row['customized']   ?? 0),
            'last_updated' => $row['last_updated'] ?? null,
        ];
    }

    private function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            $this->forbidden();
            exit;
        }
    }

    private function isAdmin(): bool
    {
        return ($_SESSION['staff_role'] ?? '') === 'admin';
    }

    private function currentStaffId(): int|string
    {
        return $_SESSION['staff_id'] ?? 0;
    }

    // =========================================================
    //  HOOKS — Audit (stubbed)
    // =========================================================

    private function audit(string $module, string $action, int $entityId,
                            ?array $old, ?array $new): void
    {
        // TODO: (new \App\Core\AuditLogger())->log($module, $action, $entityId, $old, $new);
        error_log(sprintf(
            '[Audit] module=%s action=%s entity=%d old=%s new=%s staff=%s',
            $module, $action, $entityId,
            json_encode($old), json_encode($new),
            $this->currentStaffId()
        ));
    }
}
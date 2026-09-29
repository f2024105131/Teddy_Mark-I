<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * SystemConfig Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : system_config  (PK: config_id)
 * Purpose: Key-value store for system-wide settings.
 *          Every setting has:
 *            • config_key    (unique string identifier)
 *            • config_value  (stored as TEXT)
 *            • data_type     (string|integer|decimal|boolean|json)
 *            • description   (human-readable)
 *
 * Typed access:
 *   SystemConfig::get('tax_percentage')     → float 5.0
 *   SystemConfig::get('same_day_enabled')   → bool true
 *   SystemConfig::get('business_name')      → string 'LaundryPro'
 *
 * Cache:
 *   All values are loaded on first get() and cached for the
 *   lifetime of the request. set()/delete() auto-busts the cache.
 *
 * Used by:
 *   • Admin\SettingsController       (CRUD + reset)
 *   • Admin\DashboardController      (currency + business name)
 *   • BillingService (Shehreen)      (tax percentage)
 *   • DeliveryDateEstimator (Faizan) (default_delivery_days)
 *   • Order/Booking flow             (business rules)
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → raw queries used
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class SystemConfig extends Model
{
    protected string $table      = 'system_config';
    protected string $primaryKey = 'config_id';

    /** Valid data_type ENUM values from schema */
    public const DATA_TYPES = ['string', 'integer', 'boolean', 'json', 'decimal'];

    /**
     * Static per-request cache: config_key => row.
     * Reset by calling self::flush() or after any write.
     */
    private static ?array $cache = null;

    // =========================================================
    //  TYPED ACCESS (STATIC — used across the app)
    // =========================================================

    /**
     * Get a config value by key, with automatic type casting.
     * Returns $default if the key does not exist.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::allKeyed();

        if (!array_key_exists($key, $all)) {
            return $default;
        }

        return self::cast($all[$key]['config_value'], $all[$key]['data_type']);
    }

    /**
     * Get a config value as a STRING (for display).
     */
    public static function getString(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Get a config value as an INTEGER.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get a config value as a FLOAT.
     */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * Get a config value as a BOOLEAN.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        if (is_bool($value))  return $value;
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }

    /**
     * Get a config value as a decoded JSON array.
     */
    public static function getJson(string $key, array $default = []): array
    {
        $value = self::get($key, $default);
        if (is_array($value)) return $value;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : $default;
        }

        return $default;
    }

    /**
     * All configs keyed by config_key with raw row data.
     * Cached for the lifetime of the request.
     */
    public static function allKeyed(): array
    {
        if (self::$cache === null) {
            self::loadCache();
        }
        return self::$cache ?? [];
    }

    /**
     * Just the key => typed value pairs (no metadata).
     */
    public static function allValues(): array
    {
        $out = [];
        foreach (self::allKeyed() as $key => $row) {
            $out[$key] = self::cast($row['config_value'], $row['data_type']);
        }
        return $out;
    }

    /**
     * Get multiple keys in one call.
     * Returns [key => typed value, ...].
     */
    public static function getMany(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    // =========================================================
    //  WRITES
    // =========================================================

    /**
     * Set (insert or update) a config value.
     * Data type is auto-inferred if not provided.
     */
    public static function set(
        string $key,
        mixed $value,
        ?string $dataType = null,
        ?string $description = null,
        ?int $staffId = null
    ): bool {
        $dataType = $dataType ?? self::inferType($value);
        if (!in_array($dataType, self::DATA_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid data_type: {$dataType}");
        }

        $serialized = self::serialize($value, $dataType);

        $model = new self();
        $existing = $model->findByKey($key);

        if ($existing) {
            $ok = $model->update((int) $existing['config_id'], [
                'config_value'        => $serialized,
                'data_type'           => $dataType,
                'description'         => $description ?? $existing['description'],
                'updated_by_staff_id' => $staffId,
            ]);
        } else {
            $ok = (bool) $model->insert([
                'config_key'          => $key,
                'config_value'        => $serialized,
                'data_type'           => $dataType,
                'description'         => $description,
                'updated_by_staff_id' => $staffId,
            ]);
        }

        self::flush();
        return $ok;
    }

    /**
     * Bulk set — transactional. Returns number of keys processed.
     * $pairs example: ['tax_percentage' => 7.5, 'same_day_enabled' => true]
     */
    public static function setMany(array $pairs, ?int $staffId = null): int
    {
        if (empty($pairs)) {
            return 0;
        }

        $model = new self();
        $model->db->beginTransaction();

        try {
            $count = 0;
            foreach ($pairs as $key => $value) {
                self::set($key, $value, null, null, $staffId);
                $count++;
            }
            $model->db->commit();
            self::flush();
            return $count;
        } catch (\Throwable $e) {
            $model->db->rollBack();
            self::flush();
            throw $e;
        }
    }

    /**
     * Delete a config key. Returns true if a row was deleted.
     */
    public static function remove(string $key): bool
    {
        $model = new self();
        $row   = $model->findByKey($key);

        if (!$row) {
            return false;
        }

        $ok = $model->delete((int) $row['config_id']);
        self::flush();
        return $ok;
    }

    /**
     * Force the next access to reload from the DB.
     */
    public static function flush(): void
    {
        self::$cache = null;
    }

    // =========================================================
    //  INSTANCE METHODS — raw row access
    // =========================================================

    /**
     * Find a config row by its unique key.
     */
    public function findByKey(string $key): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE config_key = :k
             LIMIT 1"
        );
        $stmt->execute(['k' => $key]);
        return $stmt->fetch();
    }

    /**
     * Check if a config key exists.
     */
    public function keyExists(string $key): bool
    {
        return (bool) $this->findByKey($key);
    }

    /**
     * All rows in a stable order, keyed by config_key.
     */
    public function allKeyedRows(): array
    {
        $rows = $this->db
            ->query("SELECT * FROM {$this->table} ORDER BY config_key ASC")
            ->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['config_key']] = $r;
        }
        return $out;
    }

    /**
     * Rows filtered by data_type.
     */
    public function byType(string $dataType): array
    {
        if (!in_array($dataType, self::DATA_TYPES, true)) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE data_type = :t
             ORDER BY config_key ASC"
        );
        $stmt->execute(['t' => $dataType]);
        return $stmt->fetchAll();
    }

    /**
     * Rows searched by key or description (admin listing).
     */
    public function search(string $query): array
    {
        $like = '%' . trim($query) . '%';

        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE config_key   LIKE :q
                OR description  LIKE :q
             ORDER BY config_key ASC"
        );
        $stmt->execute(['q' => $like]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  STATS / REPORTING
    // =========================================================

    /**
     * Summary stats for admin settings header.
     */
    public function stats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN updated_at > created_at THEN 1 ELSE 0 END) AS customized,
                MAX(updated_at) AS last_updated
             FROM {$this->table}"
        )->fetch() ?: [];

        return [
            'total'        => (int) ($row['total']        ?? 0),
            'customized'   => (int) ($row['customized']   ?? 0),
            'last_updated' => $row['last_updated'] ?? null,
        ];
    }

    /**
     * Distribution of config keys by data_type.
     */
    public function typeDistribution(): array
    {
        $rows = $this->db
            ->query(
                "SELECT data_type, COUNT(*) AS total
                 FROM {$this->table}
                 GROUP BY data_type"
            )
            ->fetchAll();

        $out = array_fill_keys(self::DATA_TYPES, 0);
        foreach ($rows as $r) {
            $out[$r['data_type']] = (int) $r['total'];
        }
        return $out;
    }

    /**
     * Config keys most recently updated (for audit widget).
     */
    public function recentlyUpdated(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT sc.*, s.full_name AS updated_by_name
             FROM {$this->table} sc
             LEFT JOIN staff s ON s.staff_id = sc.updated_by_staff_id
             ORDER BY sc.updated_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =========================================================
    //  HELPERS (view + controller friendly)
    // =========================================================

    /**
     * Human-readable label for a config key.
     * "default_delivery_days" → "Default Delivery Days"
     */
    public static function keyLabel(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * CSS badge class for a data_type.
     */
    public static function typeBadge(string $type): string
    {
        return match ($type) {
            'string'  => 'badge-primary',
            'integer' => 'badge-info',
            'decimal' => 'badge-warning',
            'boolean' => 'badge-success',
            'json'    => 'badge-dark',
            default   => 'badge-secondary',
        };
    }

    /**
     * HTML input type for a data_type (used in forms).
     */
    public static function inputType(string $type): string
    {
        return match ($type) {
            'integer' => 'number',
            'decimal' => 'number',
            'boolean' => 'checkbox',
            default   => 'text',
        };
    }

    /**
     * Optional step attribute for numeric inputs.
     */
    public static function inputStep(string $type): ?string
    {
        return match ($type) {
            'integer' => '1',
            'decimal' => '0.01',
            default   => null,
        };
    }

    /**
     * Is this key considered "custom" (created by admin)?
     * A row is custom if it was created AFTER the initial seed
     * — approximated by comparing created_at to updated_at.
     */
    public static function isCustomized(array $row): bool
    {
        if (empty($row['created_at']) || empty($row['updated_at'])) {
            return false;
        }
        return $row['updated_at'] > $row['created_at'];
    }

    // =========================================================
    //  PRIVATE — cache + casting
    // =========================================================

    private static function loadCache(): void
    {
        try {
            $model = new self();
            self::$cache = $model->allKeyedRows();
        } catch (\Throwable $e) {
            error_log('[SystemConfig::loadCache] ' . $e->getMessage());
            self::$cache = [];
        }
    }

    private static function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int)   $value,
            'decimal' => (float) $value,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json'    => json_decode($value, true) ?: [],
            default   => $value,
        };
    }

    private static function serialize(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'json'    => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            'integer' => (string) (int)   $value,
            'decimal' => (string) (float) $value,
            default   => (string) $value,
        };
    }

    private static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value)  => 'boolean',
            is_int($value)   => 'integer',
            is_float($value) => 'decimal',
            is_array($value) => 'json',
            default          => 'string',
        };
    }
}
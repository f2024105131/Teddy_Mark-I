<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * ComplaintCategory Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : complaint_category  (PK: category_id)
 * Purpose: Data access for complaint categories used in the
 *          complaint form (customer), admin CRUD, and
 *          reporting.
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → we use raw queries
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class ComplaintCategory extends Model
{
    protected string $table      = 'complaint_category';
    protected string $primaryKey = 'category_id';

    // =========================================================
    //  BASIC LOOKUPS
    // =========================================================

    /**
     * All active categories, ordered by name.
     * Used in the customer complaint-create form.
     */
    public function allActive(): array
    {
        return $this->db
            ->query("SELECT * FROM {$this->table}
                     WHERE is_active = 1
                     ORDER BY category_name ASC")
            ->fetchAll();
    }

    /**
     * All categories including inactive (admin index).
     */
    public function allOrdered(): array
    {
        return $this->db
            ->query("SELECT * FROM {$this->table}
                     ORDER BY is_active DESC, category_name ASC")
            ->fetchAll();
    }

    /**
     * Find a category by its name (case-insensitive).
     */
    public function findByName(string $name): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE LOWER(category_name) = LOWER(:name)
             LIMIT 1"
        );
        $stmt->execute(['name' => trim($name)]);
        return $stmt->fetch();
    }

    // =========================================================
    //  UNIQUENESS / SAFETY CHECKS
    // =========================================================

    /**
     * Check if a category name already exists.
     * Pass $excludeId on edit to ignore the current row.
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table}
                WHERE LOWER(category_name) = LOWER(:name)";
        $params = ['name' => trim($name)];

        if ($excludeId !== null) {
            $sql .= " AND category_id <> :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Guard against deleting categories that have complaints attached.
     */
    public function hasComplaints(int $categoryId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM complaint
             WHERE category_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $categoryId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Count of complaints attached to a category.
     */
    public function complaintsCount(int $categoryId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM complaint
             WHERE category_id = :id"
        );
        $stmt->execute(['id' => $categoryId]);
        return (int) $stmt->fetchColumn();
    }

    // =========================================================
    //  ADMIN LIST + DETAIL
    // =========================================================

    /**
     * Full admin listing with attached complaint counts.
     * Supports optional search by name.
     */
    public function allWithCounts(?string $search = null, bool $activeOnly = false): array
    {
        $sql = "SELECT cc.*,
                       COUNT(cm.complaint_id) AS complaints_count,
                       SUM(CASE WHEN cm.status IN ('open','assigned','under_investigation','escalated')
                                THEN 1 ELSE 0 END) AS active_complaints_count
                FROM {$this->table} cc
                LEFT JOIN complaint cm ON cm.category_id = cc.category_id
                WHERE 1 = 1";

        $params = [];

        if ($activeOnly) {
            $sql .= " AND cc.is_active = 1";
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND cc.category_name LIKE :search";
            $params['search'] = '%' . trim($search) . '%';
        }

        $sql .= " GROUP BY cc.category_id
                  ORDER BY cc.is_active DESC, cc.category_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Single category with complaint counts.
     */
    public function findWithCounts(int $categoryId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT cc.*,
                    COUNT(cm.complaint_id) AS complaints_count,
                    SUM(CASE WHEN cm.status IN ('open','assigned','under_investigation','escalated')
                             THEN 1 ELSE 0 END) AS active_complaints_count
             FROM {$this->table} cc
             LEFT JOIN complaint cm ON cm.category_id = cc.category_id
             WHERE cc.category_id = :id
             GROUP BY cc.category_id
             LIMIT 1"
        );
        $stmt->execute(['id' => $categoryId]);
        return $stmt->fetch();
    }

    // =========================================================
    //  REPORTING
    // =========================================================

    /**
     * Categories ranked by complaint volume over a date range.
     * Used by Admin\ReportController.
     */
    public function topByVolume(?string $from = null, ?string $to = null, int $limit = 10): array
    {
        $sql = "SELECT cc.category_id,
                       cc.category_name,
                       COUNT(cm.complaint_id) AS total
                FROM {$this->table} cc
                LEFT JOIN complaint cm
                       ON cm.category_id = cc.category_id";

        $params = [];
        $where = [];

        if ($from) {
            $where[] = "cm.created_at >= :from";
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $where[] = "cm.created_at <= :to";
            $params['to'] = $to . ' 23:59:59';
        }

        if (!empty($where)) {
            $sql .= " AND " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY cc.category_id, cc.category_name
                  ORDER BY total DESC
                  LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * List of categories with 0 complaints (idle categories).
     */
    public function unused(): array
    {
        return $this->db
            ->query(
                "SELECT cc.*
                 FROM {$this->table} cc
                 LEFT JOIN complaint cm ON cm.category_id = cc.category_id
                 WHERE cm.complaint_id IS NULL
                 ORDER BY cc.category_name ASC"
            )
            ->fetchAll();
    }

    // =========================================================
    //  STATE HELPERS
    // =========================================================

    /**
     * Guarded toggle of is_active. Returns the new state.
     */
    public function toggleActive(int $categoryId): bool
    {
        $current = $this->find($categoryId);
        if (!$current) {
            throw new \RuntimeException("Complaint category {$categoryId} not found");
        }

        $newState = !((bool) $current['is_active']);

        $this->update($categoryId, [
            'is_active' => $newState ? 1 : 0,
        ]);

        return $newState;
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    /**
     * Select list for dropdowns: id => name.
     */
    public function options(bool $activeOnly = true): array
    {
        $sql = "SELECT category_id, category_name
                FROM {$this->table}";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY category_name ASC";

        $rows = $this->db->query($sql)->fetchAll();

        $options = [];
        foreach ($rows as $r) {
            $options[(int) $r['category_id']] = $r['category_name'];
        }
        return $options;
    }

    /**
     * Human-readable label (defensive if name contains underscores).
     */
    public static function label(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }
}
<?php

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * StaffPerformance Model
 * ----------------------------------------------------------
 * Owner : Rohan
 * Table : staff_performance  (PK: performance_id)
 * Purpose: Daily per-staff performance metrics used by:
 *            • Staff dashboards  (bump counters on actions)
 *            • Admin dashboard   (top performers widget)
 *            • Admin reports     (weekly / monthly rollups)
 *
 * Unique key on (staff_id, recorded_date) is used to
 * upsert a single row per staff per day.
 *
 * Notes on Shehroz's base Model:
 *   • where() supports only ONE column → we use raw queries
 *     for multi-condition lookups here.
 *   • update() with array keys must use valid column names.
 * ----------------------------------------------------------
 */
class StaffPerformance extends Model
{
    protected string $table      = 'staff_performance';
    protected string $primaryKey = 'performance_id';

    /** Columns that can safely be incremented via bump() */
    public const BUMPABLE_COLUMNS = [
        'pickups_completed',
        'deliveries_completed',
        'orders_handled',
        'complaints_resolved',
        'total_orders',
        'weekly_orders',
        'monthly_orders',
    ];

    // =========================================================
    //  BASIC LOOKUPS
    // =========================================================

    /**
     * Fetch a single day's row for a staff member.
     */
    public function forDate(int $staffId, string $date): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE staff_id = :sid AND recorded_date = :d
             LIMIT 1"
        );
        $stmt->execute(['sid' => $staffId, 'd' => $date]);
        return $stmt->fetch();
    }

    /**
     * Today's row for a staff member.
     */
    public function forToday(int $staffId): array|false
    {
        return $this->forDate($staffId, date('Y-m-d'));
    }

    /**
     * Most recent N days of rows for a staff member.
     */
    public function recentForStaff(int $staffId, int $days = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE staff_id = :sid
               AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             ORDER BY recorded_date DESC"
        );
        $stmt->bindValue(':sid',  $staffId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Rows for a staff member over an inclusive date range.
     */
    public function forStaffBetween(int $staffId, string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE staff_id = :sid
               AND recorded_date BETWEEN :from AND :to
             ORDER BY recorded_date ASC"
        );
        $stmt->execute(['sid' => $staffId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  UPSERT / BUMP (used by Staff controllers)
    // =========================================================

    /**
     * Upsert today's counters for a staff member.
     *
     * $deltas example:
     *   ['pickups_completed' => 1]
     *   ['deliveries_completed' => 1, 'orders_handled' => 1]
     *   ['complaints_resolved' => 1]
     *
     * Silently ignores keys not in BUMPABLE_COLUMNS.
     * Never lets a counter go below 0 (GREATEST + delta).
     */
    public function bump(int $staffId, array $deltas, ?string $date = null): void
    {
        $date = $date ?? date('Y-m-d');

        // Sanitize deltas — keep only whitelisted columns
        $safeDeltas = [];
        foreach ($deltas as $col => $delta) {
            if (in_array($col, self::BUMPABLE_COLUMNS, true)) {
                $safeDeltas[$col] = (int) $delta;
            }
        }

        if (empty($safeDeltas)) {
            return;
        }

        // Ensure the row exists (upsert seed row)
        $this->db->prepare(
            "INSERT INTO {$this->table} (staff_id, recorded_date)
             VALUES (:sid, :d)
             ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
        )->execute(['sid' => $staffId, 'd' => $date]);

        // Build the SET clause with placeholders
        $sets   = [];
        $params = ['sid' => $staffId, 'd' => $date];

        foreach ($safeDeltas as $col => $delta) {
            // GREATEST(x + :delta, 0) prevents negative counters
            $sets[]         = "{$col} = GREATEST({$col} + :{$col}, 0)";
            $params[$col]   = $delta;
        }

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $sets) . "
                WHERE staff_id = :sid AND recorded_date = :d";

        $this->db->prepare($sql)->execute($params);
    }

    /**
     * Bulk bump multiple staff members at once.
     * Useful for scheduled jobs / cron aggregation.
     */
    public function bulkBump(array $byStaff, ?string $date = null): int
    {
        $count = 0;
        $this->db->beginTransaction();
        try {
            foreach ($byStaff as $staffId => $deltas) {
                $this->bump((int) $staffId, $deltas, $date);
                $count++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $count;
    }

    // =========================================================
    //  ADMIN DASHBOARD / LEADERBOARDS
    // =========================================================

    /**
     * Top N performers for a given date (default today).
     */
    public function topPerformers(int $limit = 5, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        $stmt = $this->db->prepare(
            "SELECT sp.*, s.full_name, s.email
             FROM {$this->table} sp
             JOIN staff s ON s.staff_id = sp.staff_id
             WHERE sp.recorded_date = :d
               AND s.is_active = 1
             ORDER BY sp.performance_score DESC,
                      sp.deliveries_completed DESC,
                      sp.orders_handled DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':d',   $date);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Aggregate stats for all staff over a date range.
     * Used by Admin\ReportController.
     */
    public function statsPerStaff(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.staff_id,
                    s.full_name,
                    COALESCE(SUM(sp.pickups_completed), 0)     AS pickups_completed,
                    COALESCE(SUM(sp.deliveries_completed), 0)  AS deliveries_completed,
                    COALESCE(SUM(sp.orders_handled), 0)        AS orders_handled,
                    COALESCE(SUM(sp.complaints_resolved), 0)   AS complaints_resolved,
                    COALESCE(AVG(sp.average_rating), 0)        AS average_rating,
                    COALESCE(AVG(sp.performance_score), 0)     AS average_score
             FROM staff s
             LEFT JOIN {$this->table} sp
                    ON sp.staff_id = s.staff_id
                   AND sp.recorded_date BETWEEN :from AND :to
             WHERE s.is_active = 1
             GROUP BY s.staff_id, s.full_name
             ORDER BY average_score DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    /**
     * Team-wide totals for a date range.
     */
    public function teamTotals(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(pickups_completed), 0)     AS pickups_completed,
                COALESCE(SUM(deliveries_completed), 0)  AS deliveries_completed,
                COALESCE(SUM(orders_handled), 0)        AS orders_handled,
                COALESCE(SUM(complaints_resolved), 0)   AS complaints_resolved,
                COALESCE(AVG(average_rating), 0)        AS average_rating,
                COALESCE(AVG(performance_score), 0)     AS average_score,
                COUNT(DISTINCT staff_id)                AS active_staff_count
             FROM {$this->table}
             WHERE recorded_date BETWEEN :from AND :to"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $row = $stmt->fetch() ?: [];

        return [
            'pickups_completed'    => (int)   ($row['pickups_completed']    ?? 0),
            'deliveries_completed' => (int)   ($row['deliveries_completed'] ?? 0),
            'orders_handled'       => (int)   ($row['orders_handled']       ?? 0),
            'complaints_resolved'  => (int)   ($row['complaints_resolved']  ?? 0),
            'average_rating'       => (float) ($row['average_rating']       ?? 0),
            'average_score'        => (float) ($row['average_score']        ?? 0),
            'active_staff_count'   => (int)   ($row['active_staff_count']   ?? 0),
        ];
    }

    /**
     * Daily trend for a single staff member (for charts).
     */
    public function dailyTrend(int $staffId, int $days = 14): array
    {
        $stmt = $this->db->prepare(
            "SELECT recorded_date,
                    pickups_completed,
                    deliveries_completed,
                    orders_handled,
                    complaints_resolved,
                    performance_score
             FROM {$this->table}
             WHERE staff_id = :sid
               AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             ORDER BY recorded_date ASC"
        );
        $stmt->bindValue(':sid',  $staffId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Team-wide daily trend (for admin dashboard charts).
     */
    public function teamDailyTrend(int $days = 14): array
    {
        $stmt = $this->db->prepare(
            "SELECT recorded_date,
                    SUM(pickups_completed)     AS pickups_completed,
                    SUM(deliveries_completed)  AS deliveries_completed,
                    SUM(orders_handled)        AS orders_handled,
                    SUM(complaints_resolved)   AS complaints_resolved,
                    AVG(performance_score)     AS average_score
             FROM {$this->table}
             WHERE recorded_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY recorded_date
             ORDER BY recorded_date ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =========================================================
    //  SCORE COMPUTATION
    // =========================================================

    /**
     * Compute and persist a simple weighted performance score
     * for a single day. Called by bump() callers, a cron job,
     * or Admin\StaffController::recomputeScores().
     *
     * Formula (out of 100):
     *   deliveries_completed  * 5
     *   pickups_completed     * 4
     *   orders_handled        * 2
     *   complaints_resolved   * 3
     *   average_rating        * 8   (0–5 → 0–40)
     *   minus 2 per complaint escalated (if any)
     * Capped to 100.
     */
    public function recomputeScore(int $staffId, ?string $date = null): float
    {
        $date = $date ?? date('Y-m-d');
        $row  = $this->forDate($staffId, $date);

        if (!$row) {
            return 0.0;
        }

        $score =
              ((int)   $row['deliveries_completed']) * 5
            + ((int)   $row['pickups_completed'])    * 4
            + ((int)   $row['orders_handled'])       * 2
            + ((int)   $row['complaints_resolved'])  * 3
            + ((float) $row['average_rating'] ?: 0)  * 8;

        // Cap at 100
        $score = min(100.0, round($score, 2));

        $this->update((int) $row['performance_id'], [
            'performance_score' => $score,
        ]);

        return $score;
    }

    /**
     * Recompute scores for every staff member for a given date.
     * Returns number of rows updated.
     */
    public function recomputeScoresForDate(?string $date = null): int
    {
        $date = $date ?? date('Y-m-d');

        $rows = $this->db->prepare(
            "SELECT staff_id FROM {$this->table}
             WHERE recorded_date = :d"
        );
        $rows->execute(['d' => $date]);
        $staffIds = $rows->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($staffIds as $sid) {
            $this->recomputeScore((int) $sid, $date);
            $count++;
        }
        return $count;
    }

    // =========================================================
    //  RATING
    // =========================================================

    /**
     * Record a new rating for a staff member (blended average).
     * Uses the average_rating column (DECIMAL(3,2)).
     */
    public function addRating(int $staffId, float $newRating, ?string $date = null): void
    {
        if ($newRating < 0 || $newRating > 5) {
            throw new \InvalidArgumentException('Rating must be between 0 and 5.');
        }

        $date = $date ?? date('Y-m-d');
        $row  = $this->forDate($staffId, $date);

        if (!$row) {
            // Seed row first
            $this->db->prepare(
                "INSERT INTO {$this->table} (staff_id, recorded_date, average_rating)
                 VALUES (:sid, :d, :r)
                 ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
            )->execute(['sid' => $staffId, 'd' => $date, 'r' => $newRating]);
            return;
        }

        $current = (float) ($row['average_rating'] ?? 0.0);
        // Weighted average — treat existing rating as 1 sample.
        // If your business needs more samples, replace with a proper counter table.
        $blended = $current > 0
            ? round(($current + $newRating) / 2, 2)
            : $newRating;

        $this->update((int) $row['performance_id'], [
            'average_rating' => $blended,
        ]);
    }

    // =========================================================
    //  CLEANUP
    // =========================================================

    /**
     * Delete rows older than N days. Useful for housekeeping.
     */
    public function pruneOlderThan(int $days = 365): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table}
             WHERE recorded_date < DATE_SUB(CURDATE(), INTERVAL :days DAY)"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    /**
     * Has any row been recorded for this staff member today?
     */
    public function hasToday(int $staffId): bool
    {
        return (bool) $this->forToday($staffId);
    }

    /**
     * Human-readable label for a score (for views).
     */
    public static function scoreLabel(float $score): string
    {
        return match (true) {
            $score >= 85 => 'Excellent',
            $score >= 70 => 'Good',
            $score >= 50 => 'Average',
            default      => 'Needs Improvement',
        };
    }

    /**
     * CSS badge class for a score (for views).
     */
    public static function scoreBadge(float $score): string
    {
        return match (true) {
            $score >= 85 => 'badge-success',
            $score >= 70 => 'badge-info',
            $score >= 50 => 'badge-warning',
            default      => 'badge-danger',
        };
    }
}
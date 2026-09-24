<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin\PickupMonitorController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Live operations console for admins.
 *            • Real-time view of today's pickups + deliveries
 *            • Date navigation (any past/future date)
 *            • Status, staff, and area filters
 *            • Overdue / stuck detection with alerts
 *            • Bulk assignment of unassigned pickups
 *            • Manual reschedule override
 *            • Manual status override (admin only)
 *            • JSON live-feed endpoint for auto-refresh
 *            • CSV export of daily operations
 *
 * Notes:
 *   • Read-heavy. Writes are admin overrides only.
 *   • Uses raw SQL — no dependency on Faizan's models.
 *   • All filters use prepared statements (SQL-injection safe).
 * ----------------------------------------------------------
 */
class PickupMonitorController extends Controller
{
    /** Stuck threshold: pickups pending longer than N hours */
    private const STUCK_PICKUP_HOURS = 24;

    /** Stuck threshold: orders sitting 'ready' longer than N days */
    private const STUCK_READY_DAYS = 3;

    /** Statuses that count as active deliveries */
    private const ACTIVE_DELIVERY_STATUSES = ['scheduled', 'out_for_delivery'];

    // =========================================================
    //  INDEX — Live operations dashboard
    // =========================================================
    public function index(): void
    {
        $this->requireAdmin();

        // Date navigation
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!$this->isValidDate($date)) {
            $date = date('Y-m-d');
        }

        // Filters
        $status   = $_GET['status']   ?? '';   // pickup_request.status OR delivery.status
        $staffId  = (int) ($_GET['staff']  ?? 0);
        $area     = trim($_GET['area']  ?? '');
        $tab      = $_GET['tab']      ?? 'pickups'; // pickups | deliveries | pipeline

        // Load data
        $pickups    = $this->loadPickups($date, $status, $staffId, $area);
        $deliveries = $this->loadDeliveries($date, $status, $staffId, $area);

        // KPI summary for this date
        $summary = $this->dailySummary($date);

        // Alerts (overdue, stuck, unassigned)
        $alerts = $this->monitorAlerts($date);

        // Staff list for assignment dropdown
        $staffList = $this->activeStaffList();

        // Areas for filter dropdown (from today's customers)
        $areas = $this->activeAreas();

        // Timeline: hourly distribution of pickups/deliveries
        $pickupTimeline    = $this->hourlyBreakdown('pickup_request', 'pickup_date', $date);
        $deliveryTimeline  = $this->hourlyBreakdown('delivery', 'delivery_date', $date);

        // Recently active staff (last 24h)
        $onDuty = $this->staffOnDuty();

        $this->view('admin/pickups/monitor', [
            'date'             => $date,
            'status'           => $status,
            'staffId'          => $staffId,
            'area'             => $area,
            'tab'              => $tab,
            'pickups'          => $pickups,
            'deliveries'       => $deliveries,
            'summary'          => $summary,
            'alerts'           => $alerts,
            'staffList'        => $staffList,
            'areas'            => $areas,
            'pickupTimeline'   => $pickupTimeline,
            'deliveryTimeline' => $deliveryTimeline,
            'onDuty'           => $onDuty,
            'prevDate'         => date('Y-m-d', strtotime($date . ' -1 day')),
            'nextDate'         => date('Y-m-d', strtotime($date . ' +1 day')),
            'isToday'          => $date === date('Y-m-d'),
        ]);
    }

    // =========================================================
    //  LIVE FEED — JSON endpoint for auto-refresh
    // =========================================================
    public function liveFeed(): void
    {
        $this->requireAdmin();

        $date = $_GET['date'] ?? date('Y-m-d');
        if (!$this->isValidDate($date)) {
            $date = date('Y-m-d');
        }

        $pickups    = $this->loadPickups($date, '', 0, '');
        $deliveries = $this->loadDeliveries($date, '', 0, '');
        $summary    = $this->dailySummary($date);
        $alerts     = $this->monitorAlerts($date);

        // Compact the payload for bandwidth (only fields the view needs)
        $compactPickups = array_map(fn($p) => [
            'id'         => (int) $p['pickup_id'],
            'number'     => $p['request_number'],
            'status'     => $p['status'],
            'date'       => $p['pickup_date'],
            'slot'       => substr($p['start_time'], 0, 5) . '–' . substr($p['end_time'], 0, 5),
            'customer'   => $p['customer_name'],
            'phone'      => $p['customer_phone'],
            'area'       => $p['address_area'],
            'city'       => $p['city'],
            'staff'      => $p['assigned_staff_name'],
            'staff_id'   => $p['assigned_staff_id'] ? (int) $p['assigned_staff_id'] : null,
            'is_overdue' => $this->isPickupOverdue($p),
        ], $pickups);

        $compactDeliveries = array_map(fn($d) => [
            'id'         => (int) $d['delivery_id'],
            'order'      => $d['order_number'],
            'status'     => $d['status'],
            'date'       => $d['delivery_date'],
            'slot'       => substr($d['start_time'], 0, 5) . '–' . substr($d['end_time'], 0, 5),
            'customer'   => $d['customer_name'],
            'phone'      => $d['customer_phone'],
            'area'       => $d['address_area'],
            'city'       => $d['city'],
            'staff'      => $d['assigned_staff_name'],
            'staff_id'   => $d['assigned_staff_id'] ? (int) $d['assigned_staff_id'] : null,
            'is_overdue' => $this->isDeliveryOverdue($d),
        ], $deliveries);

        $this->jsonSuccess([
            'date'       => $date,
            'summary'    => $summary,
            'alerts'     => $alerts,
            'pickups'    => $compactPickups,
            'deliveries' => $compactDeliveries,
            'timestamp'  => date('c'),
        ]);
    }

    // =========================================================
    //  BULK ASSIGN — Assign multiple pickups to one staff
    // =========================================================
    public function bulkAssign(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $staffId   = (int) ($_POST['staff_id'] ?? 0);
        $pickupIds = $_POST['pickup_ids'] ?? [];

        if ($staffId <= 0) {
            $this->jsonError('Please select a staff member.', 422);
            return;
        }

        if (!is_array($pickupIds) || empty($pickupIds)) {
            $this->jsonError('No pickups selected.', 422);
            return;
        }

        // Sanitize IDs
        $ids = array_values(array_filter(array_map('intval', $pickupIds), fn($v) => $v > 0));

        if (empty($ids)) {
            $this->jsonError('No valid pickup IDs provided.', 422);
            return;
        }

        // Verify target staff is active
        $stmt = $this->db->prepare(
            "SELECT staff_id, full_name FROM staff
             WHERE staff_id = :sid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['sid' => $staffId]);
        $staff = $stmt->fetch();

        if (!$staff) {
            $this->jsonError('Target staff member not found or inactive.', 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Only assign those still in 'requested' state
            $sql = "UPDATE pickup_request
                    SET assigned_staff_id = ?,
                        status            = 'assigned'
                    WHERE pickup_id IN ({$placeholders})
                      AND status = 'requested'";

            $params = array_merge([$staffId], $ids);
            $stmt   = $this->db->prepare($sql);
            $stmt->execute($params);
            $assigned = $stmt->rowCount();

            // Sync to orders for the same pickups
            $syncSql = "UPDATE orders
                        SET assigned_staff_id = ?
                        WHERE pickup_id IN ({$placeholders})
                          AND order_status NOT IN ('delivered','cancelled')";
            $syncStmt = $this->db->prepare($syncSql);
            $syncStmt->execute(array_merge([$staffId], $ids));
            $synced = $syncStmt->rowCount();

            $this->db->commit();

            $this->audit('pickup', 'bulk_assign', 0, null, [
                'staff_id'      => $staffId,
                'staff_name'    => $staff['full_name'],
                'pickup_ids'    => $ids,
                'assigned'      => $assigned,
                'orders_synced' => $synced,
            ]);

            $this->notifyBulkAssign($staffId, $ids);

            $this->jsonSuccess([
                'message'       => "{$assigned} pickup(s) assigned to {$staff['full_name']}.",
                'assigned'      => $assigned,
                'orders_synced' => $synced,
                'staff_id'      => $staffId,
                'staff_name'    => $staff['full_name'],
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[AdminPickupMonitor::bulkAssign] ' . $e->getMessage());
            $this->jsonError('Failed to assign pickups.', 500);
        }
    }

    // =========================================================
    //  REASSIGN — Single pickup override
    // =========================================================
    public function reassign(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $newStaffId = (int) ($_POST['staff_id'] ?? 0);
        if ($newStaffId <= 0) {
            $this->jsonError('Please select a staff member.', 422);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM pickup_request WHERE pickup_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $pickup = $stmt->fetch();

        if (!$pickup) {
            $this->jsonError('Pickup not found.', 404);
            return;
        }

        if (in_array($pickup['status'], ['picked_up', 'cancelled'], true)) {
            $this->jsonError("Cannot reassign a {$pickup['status']} pickup.", 422);
            return;
        }

        // Verify target staff
        $stmt = $this->db->prepare(
            "SELECT staff_id, full_name FROM staff
             WHERE staff_id = :sid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['sid' => $newStaffId]);
        $staff = $stmt->fetch();

        if (!$staff) {
            $this->jsonError('Target staff member not found or inactive.', 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            $this->db->prepare(
                "UPDATE pickup_request
                 SET assigned_staff_id = :sid,
                     status = CASE WHEN status = 'requested' THEN 'assigned' ELSE status END
                 WHERE pickup_id = :id"
            )->execute(['sid' => $newStaffId, 'id' => $id]);

            // Sync order
            $this->db->prepare(
                "UPDATE orders SET assigned_staff_id = :sid
                 WHERE pickup_id = :id
                   AND order_status NOT IN ('delivered','cancelled')"
            )->execute(['sid' => $newStaffId, 'id' => $id]);

            $this->db->commit();

            $this->audit('pickup', 'reassign', $id,
                ['assigned_staff_id' => $pickup['assigned_staff_id']],
                ['assigned_staff_id' => $newStaffId]
            );

            $this->notifyReassign($id, $newStaffId);

            $this->jsonSuccess([
                'message'    => "Pickup reassigned to {$staff['full_name']}.",
                'pickup_id'  => $id,
                'staff_id'   => $newStaffId,
                'staff_name' => $staff['full_name'],
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[AdminPickupMonitor::reassign] ' . $e->getMessage());
            $this->jsonError('Failed to reassign pickup.', 500);
        }
    }

    // =========================================================
    //  RESCHEDULE — Admin override
    // =========================================================
    public function reschedule(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $newDate = trim($_POST['new_date'] ?? '');
        $newSlot = (int) ($_POST['new_slot_id'] ?? 0);

        if (!$this->isValidDate($newDate)) {
            $this->jsonError('Invalid date format.', 422);
            return;
        }

        if (strtotime($newDate) < strtotime('today')) {
            $this->jsonError('Cannot reschedule to a past date.', 422);
            return;
        }

        if ($newSlot <= 0) {
            $this->jsonError('Please select a valid slot.', 422);
            return;
        }

        // Load pickup
        $stmt = $this->db->prepare(
            "SELECT * FROM pickup_request WHERE pickup_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $pickup = $stmt->fetch();

        if (!$pickup) {
            $this->jsonError('Pickup not found.', 404);
            return;
        }

        if (in_array($pickup['status'], ['picked_up', 'cancelled'], true)) {
            $this->jsonError("Cannot reschedule a {$pickup['status']} pickup.", 422);
            return;
        }

        // Verify slot + day-of-week
        $stmt = $this->db->prepare(
            "SELECT slot_id, day_of_week, max_capacity, is_active
             FROM pickup_slot WHERE slot_id = :sid LIMIT 1"
        );
        $stmt->execute(['sid' => $newSlot]);
        $slot = $stmt->fetch();

        if (!$slot || !(int) $slot['is_active']) {
            $this->jsonError('Selected slot is not available.', 422);
            return;
        }

        $expectedDow = strtolower(date('l', strtotime($newDate)));
        if ($slot['day_of_week'] !== $expectedDow) {
            $this->jsonError(
                "Selected slot is for {$slot['day_of_week']}s, but {$newDate} is a {$expectedDow}.",
                422
            );
            return;
        }

        // Capacity check (excluding this pickup itself)
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM pickup_request
             WHERE pickup_date = :d
               AND slot_id = :sid
               AND pickup_id <> :id
               AND status IN ('requested','assigned')"
        );
        $stmt->execute(['d' => $newDate, 'sid' => $newSlot, 'id' => $id]);
        $load = (int) $stmt->fetchColumn();

        if ($load >= (int) $slot['max_capacity']) {
            $this->jsonError('Selected slot is fully booked.', 422);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE pickup_request
                 SET pickup_date = :d,
                     slot_id     = :sid,
                     status      = 'requested'
                 WHERE pickup_id = :id"
            )->execute(['d' => $newDate, 'sid' => $newSlot, 'id' => $id]);

            // Sync to order's estimated delivery date
            $this->db->prepare(
                "UPDATE orders
                 SET estimated_delivery_date = DATE_ADD(:d, INTERVAL 3 DAY)
                 WHERE pickup_id = :id
                   AND order_status NOT IN ('delivered','cancelled')"
            )->execute(['d' => $newDate, 'id' => $id]);

            $this->audit('pickup', 'admin_reschedule', $id,
                ['pickup_date' => $pickup['pickup_date'], 'slot_id' => $pickup['slot_id']],
                ['pickup_date' => $newDate, 'slot_id' => $newSlot]
            );

            $this->notifyReschedule($id, $newDate, $newSlot);

            $this->jsonSuccess([
                'message'   => 'Pickup rescheduled.',
                'pickup_id' => $id,
                'new_date'  => $newDate,
                'new_slot'  => $newSlot,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminPickupMonitor::reschedule] ' . $e->getMessage());
            $this->jsonError('Failed to reschedule pickup.', 500);
        }
    }

    // =========================================================
    //  FORCE STATUS — Admin override (rare, logged)
    // =========================================================
    public function forceStatus(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $newStatus = $_POST['status'] ?? '';
        $reason    = trim($_POST['reason'] ?? '');

        $validPickupStatuses = ['requested', 'assigned', 'picked_up', 'cancelled'];
        if (!in_array($newStatus, $validPickupStatuses, true)) {
            $this->jsonError('Invalid status.', 422);
            return;
        }

        if ($reason === '' || mb_strlen($reason) < 5) {
            $this->jsonError('A reason is required for manual overrides (min 5 chars).', 422);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM pickup_request WHERE pickup_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $pickup = $stmt->fetch();

        if (!$pickup) {
            $this->jsonError('Pickup not found.', 404);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE pickup_request SET status = :s WHERE pickup_id = :id"
            )->execute(['s' => $newStatus, 'id' => $id]);

            $this->audit('pickup', 'force_status', $id,
                ['status' => $pickup['status']],
                ['status' => $newStatus, 'reason' => $reason]
            );

            $this->jsonSuccess([
                'message'    => 'Status overridden.',
                'pickup_id'  => $id,
                'new_status' => $newStatus,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminPickupMonitor::forceStatus] ' . $e->getMessage());
            $this->jsonError('Failed to override status.', 500);
        }
    }

    // =========================================================
    //  DELIVERY — Reassign
    // =========================================================
    public function reassignDelivery(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $newStaffId = (int) ($_POST['staff_id'] ?? 0);
        if ($newStaffId <= 0) {
            $this->jsonError('Please select a staff member.', 422);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM delivery WHERE delivery_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $delivery = $stmt->fetch();

        if (!$delivery) {
            $this->jsonError('Delivery not found.', 404);
            return;
        }

        if ($delivery['status'] === 'delivered') {
            $this->jsonError('Cannot reassign a completed delivery.', 422);
            return;
        }

        // Verify staff
        $stmt = $this->db->prepare(
            "SELECT staff_id, full_name FROM staff
             WHERE staff_id = :sid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['sid' => $newStaffId]);
        $staff = $stmt->fetch();

        if (!$staff) {
            $this->jsonError('Target staff member not found or inactive.', 422);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE delivery SET assigned_staff_id = :sid WHERE delivery_id = :id"
            )->execute(['sid' => $newStaffId, 'id' => $id]);

            $this->audit('delivery', 'reassign', $id,
                ['assigned_staff_id' => $delivery['assigned_staff_id']],
                ['assigned_staff_id' => $newStaffId]
            );

            $this->jsonSuccess([
                'message'    => "Delivery reassigned to {$staff['full_name']}.",
                'delivery_id'=> $id,
                'staff_id'   => $newStaffId,
                'staff_name' => $staff['full_name'],
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminPickupMonitor::reassignDelivery] ' . $e->getMessage());
            $this->jsonError('Failed to reassign delivery.', 500);
        }
    }

    // =========================================================
    //  EXPORT CSV — Daily operations
    // =========================================================
    public function export(): void
    {
        $this->requireAdmin();

        $date    = $_GET['date'] ?? date('Y-m-d');
        if (!$this->isValidDate($date)) {
            $date = date('Y-m-d');
        }

        $staffId = (int) ($_GET['staff'] ?? 0);
        $area    = trim($_GET['area'] ?? '');

        $pickups    = $this->loadPickups($date, '', $staffId, $area);
        $deliveries = $this->loadDeliveries($date, '', $staffId, $area);

        $filename = 'operations_' . $date . '_' . date('His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        // --- PICKUPS SECTION ---
        fputcsv($out, ['=== PICKUPS ===']);
        fputcsv($out, [
            'Request #', 'Customer', 'Phone', 'Address',
            'Slot', 'Status', 'Assigned To', 'Overdue', 'Notes',
        ]);

        foreach ($pickups as $p) {
            fputcsv($out, [
                $p['request_number'],
                $p['customer_name'],
                $p['customer_phone'],
                "{$p['address_house']}, {$p['address_street']}, {$p['address_area']}, {$p['city']}",
                substr($p['start_time'], 0, 5) . '–' . substr($p['end_time'], 0, 5),
                $p['status'],
                $p['assigned_staff_name'] ?? 'Unassigned',
                $this->isPickupOverdue($p) ? 'YES' : 'No',
                $p['special_instructions'] ?? '',
            ]);
        }

        fputcsv($out, []);
        fputcsv($out, []);

        // --- DELIVERIES SECTION ---
        fputcsv($out, ['=== DELIVERIES ===']);
        fputcsv($out, [
            'Order #', 'Customer', 'Phone', 'Address',
            'Slot', 'Status', 'Assigned To', 'Overdue', 'Notes',
        ]);

        foreach ($deliveries as $d) {
            fputcsv($out, [
                $d['order_number'],
                $d['customer_name'],
                $d['customer_phone'],
                "{$d['address_house']}, {$d['address_street']}, {$d['address_area']}, {$d['city']}",
                substr($d['start_time'], 0, 5) . '–' . substr($d['end_time'], 0, 5),
                $d['status'],
                $d['assigned_staff_name'] ?? 'Unassigned',
                $this->isDeliveryOverdue($d) ? 'YES' : 'No',
                $d['delivery_notes'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================================================
    //  DATA LOADERS
    // =========================================================

    private function loadPickups(string $date, string $status, int $staffId, string $area): array
    {
        $sql = "SELECT pr.pickup_id, pr.request_number, pr.pickup_date,
                       pr.status, pr.special_instructions,
                       pr.assigned_address, pr.is_same_day,
                       pr.assigned_staff_id, pr.created_at,
                       c.customer_id, c.full_name AS customer_name,
                       c.phone AS customer_phone,
                       c.address_house, c.address_street,
                       c.address_area, c.city, c.landmark,
                       ps.start_time, ps.end_time, ps.day_of_week,
                       st.full_name AS assigned_staff_name,
                       o.order_id, o.order_number, o.order_status
                FROM pickup_request pr
                JOIN customer      c  ON c.customer_id = pr.customer_id
                JOIN pickup_slot   ps ON ps.slot_id    = pr.slot_id
                LEFT JOIN staff    st ON st.staff_id   = pr.assigned_staff_id
                LEFT JOIN orders   o  ON o.pickup_id   = pr.pickup_id
                WHERE pr.pickup_date = :d";

        $params = ['d' => $date];

        if ($status !== '' && in_array($status, ['requested', 'assigned', 'picked_up', 'cancelled'], true)) {
            $sql .= " AND pr.status = :status";
            $params['status'] = $status;
        }

        if ($staffId > 0) {
            $sql .= " AND pr.assigned_staff_id = :sid";
            $params['sid'] = $staffId;
        }

        if ($area !== '') {
            $sql .= " AND c.address_area = :area";
            $params['area'] = $area;
        }

        $sql .= " ORDER BY ps.start_time ASC, pr.pickup_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function loadDeliveries(string $date, string $status, int $staffId, string $area): array
    {
        $sql = "SELECT d.delivery_id, d.order_id, d.delivery_date,
                       d.status, d.failure_reason, d.delivery_notes,
                       d.delivered_at, d.assigned_staff_id, d.created_at,
                       o.order_number, o.order_status,
                       c.customer_id, c.full_name AS customer_name,
                       c.phone AS customer_phone,
                       c.address_house, c.address_street,
                       c.address_area, c.city, c.landmark,
                       ds.start_time, ds.end_time,
                       st.full_name AS assigned_staff_name
                FROM delivery d
                JOIN orders        o  ON o.order_id    = d.order_id
                JOIN customer      c  ON c.customer_id = o.customer_id
                JOIN delivery_slot ds ON ds.slot_id    = d.slot_id
                LEFT JOIN staff    st ON st.staff_id   = d.assigned_staff_id
                WHERE d.delivery_date = :d";

        $params = ['d' => $date];

        $validStatuses = ['scheduled', 'out_for_delivery', 'delivered', 'failed', 'rescheduled'];
        if ($status !== '' && in_array($status, $validStatuses, true)) {
            $sql .= " AND d.status = :status";
            $params['status'] = $status;
        }

        if ($staffId > 0) {
            $sql .= " AND d.assigned_staff_id = :sid";
            $params['sid'] = $staffId;
        }

        if ($area !== '') {
            $sql .= " AND c.address_area = :area";
            $params['area'] = $area;
        }

        $sql .= " ORDER BY ds.start_time ASC, d.delivery_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // =========================================================
    //  SUMMARY + ALERTS
    // =========================================================

    private function dailySummary(string $date): array
    {
        // Pickups
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'requested'  THEN 1 ELSE 0 END) AS requested,
                SUM(CASE WHEN status = 'assigned'   THEN 1 ELSE 0 END) AS assigned,
                SUM(CASE WHEN status = 'picked_up'  THEN 1 ELSE 0 END) AS picked_up,
                SUM(CASE WHEN status = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN assigned_staff_id IS NULL AND status = 'requested'
                                                         THEN 1 ELSE 0 END) AS unassigned
             FROM pickup_request
             WHERE pickup_date = :d"
        );
        $stmt->execute(['d' => $date]);
        $p = $stmt->fetch() ?: [];

        // Deliveries
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'scheduled'        THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) AS out_for_delivery,
                SUM(CASE WHEN status = 'delivered'        THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = 'failed'           THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'rescheduled'      THEN 1 ELSE 0 END) AS rescheduled
             FROM delivery
             WHERE delivery_date = :d"
        );
        $stmt->execute(['d' => $date]);
        $d = $stmt->fetch() ?: [];

        return [
            'pickups' => [
                'total'      => (int) ($p['total']      ?? 0),
                'requested'  => (int) ($p['requested']  ?? 0),
                'assigned'   => (int) ($p['assigned']   ?? 0),
                'picked_up'  => (int) ($p['picked_up']  ?? 0),
                'cancelled'  => (int) ($p['cancelled']  ?? 0),
                'unassigned' => (int) ($p['unassigned'] ?? 0),
            ],
            'deliveries' => [
                'total'            => (int) ($d['total']            ?? 0),
                'scheduled'        => (int) ($d['scheduled']        ?? 0),
                'out_for_delivery' => (int) ($d['out_for_delivery'] ?? 0),
                'delivered'        => (int) ($d['delivered']        ?? 0),
                'failed'           => (int) ($d['failed']           ?? 0),
                'rescheduled'      => (int) ($d['rescheduled']      ?? 0),
            ],
        ];
    }

    private function monitorAlerts(string $date): array
    {
        $alerts = [];

        // 1. Unassigned pickups for this date
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM pickup_request
             WHERE pickup_date = :d
               AND status = 'requested'
               AND assigned_staff_id IS NULL"
        );
        $stmt->execute(['d' => $date]);
        $unassigned = (int) $stmt->fetchColumn();

        if ($unassigned > 0) {
            $alerts[] = [
                'level'   => 'warning',
                'icon'    => 'bi-person-plus',
                'message' => "{$unassigned} pickup(s) unassigned for {$date}.",
                'count'   => $unassigned,
                'filter'  => 'unassigned-pickups',
            ];
        }

        // 2. Overdue pickups (past date, still requested/assigned)
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM pickup_request
             WHERE pickup_date < CURDATE()
               AND status IN ('requested','assigned')"
        );
        $overduePickups = (int) $stmt->fetchColumn();

        if ($overduePickups > 0) {
            $alerts[] = [
                'level'   => 'danger',
                'icon'    => 'bi-exclamation-triangle',
                'message' => "{$overduePickups} overdue pickup(s) from previous dates.",
                'count'   => $overduePickups,
                'filter'  => 'overdue-pickups',
            ];
        }

        // 3. Overdue deliveries
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM delivery
             WHERE delivery_date < CURDATE()
               AND status IN ('scheduled','out_for_delivery')"
        );
        $overdueDeliveries = (int) $stmt->fetchColumn();

        if ($overdueDeliveries > 0) {
            $alerts[] = [
                'level'   => 'danger',
                'icon'    => 'bi-truck',
                'message' => "{$overdueDeliveries} overdue delivery(ies).",
                'count'   => $overdueDeliveries,
                'filter'  => 'overdue-deliveries',
            ];
        }

        // 4. Orders stuck in 'ready' for N+ days
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM orders
             WHERE order_status = 'ready'
               AND ready_at < DATE_SUB(NOW(), INTERVAL :days DAY)"
        );
        $stmt->bindValue(':days', self::STUCK_READY_DAYS, \PDO::PARAM_INT);
        $stmt->execute();
        $stuckReady = (int) $stmt->fetchColumn();

        if ($stuckReady > 0) {
            $alerts[] = [
                'level'   => 'warning',
                'icon'    => 'bi-hourglass-split',
                'message' => "{$stuckReady} order(s) ready for " . self::STUCK_READY_DAYS . "+ days, not dispatched.",
                'count'   => $stuckReady,
                'filter'  => 'stuck-ready',
            ];
        }

        // 5. Stuck pickups (over N hours old, still 'requested')
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM pickup_request
             WHERE status = 'requested'
               AND created_at < DATE_SUB(NOW(), INTERVAL :hours HOUR)"
        );
        $stmt->bindValue(':hours', self::STUCK_PICKUP_HOURS, \PDO::PARAM_INT);
        $stmt->execute();
        $stuckPickups = (int) $stmt->fetchColumn();

        if ($stuckPickups > 0) {
            $alerts[] = [
                'level'   => 'info',
                'icon'    => 'bi-clock-history',
                'message' => "{$stuckPickups} pickup(s) pending over " . self::STUCK_PICKUP_HOURS . "h without assignment.",
                'count'   => $stuckPickups,
                'filter'  => 'stuck-pickups',
            ];
        }

        return $alerts;
    }

    // =========================================================
    //  HELPER LISTS
    // =========================================================

    private function activeStaffList(): array
    {
        return $this->db->query(
            "SELECT staff_id, full_name, role
             FROM staff
             WHERE is_active = 1
             ORDER BY role DESC, full_name ASC"
        )->fetchAll();
    }

    private function activeAreas(): array
    {
        return $this->db
            ->query(
                "SELECT DISTINCT address_area
                 FROM customer
                 WHERE address_area IS NOT NULL AND address_area <> ''
                 ORDER BY address_area ASC"
            )
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function staffOnDuty(): array
    {
        return $this->db->query(
            "SELECT s.staff_id, s.full_name, s.role,
                    (SELECT COUNT(*) FROM pickup_request pr
                      WHERE pr.assigned_staff_id = s.staff_id
                        AND pr.pickup_date = CURDATE()
                        AND pr.status IN ('requested','assigned')) AS pickups_today,
                    (SELECT COUNT(*) FROM delivery d
                      WHERE d.assigned_staff_id = s.staff_id
                        AND d.delivery_date = CURDATE()
                        AND d.status IN ('scheduled','out_for_delivery')) AS deliveries_today
             FROM staff s
             WHERE s.is_active = 1
             ORDER BY pickups_today DESC, deliveries_today DESC, s.full_name ASC"
        )->fetchAll();
    }

    /**
     * Hourly distribution for a table for a given date.
     * Returns 24 buckets (0-23) with counts.
     */
    private function hourlyBreakdown(string $table, string $dateColumn, string $date): array
    {
        // Whitelist table/column to prevent SQL injection from caller
        $allowed = [
            'pickup_request' => 'pickup_date',
            'delivery'       => 'delivery_date',
        ];

        if (!isset($allowed[$table]) || $allowed[$table] !== $dateColumn) {
            return array_fill(0, 24, 0);
        }

        // Both schemas store a DATE, not DATETIME. We can't break by hour
        // from those columns directly. Instead, break by slot start_time
        // joined via slot_id.
        if ($table === 'pickup_request') {
            $sql = "SELECT HOUR(ps.start_time) AS h, COUNT(*) AS total
                    FROM pickup_request pr
                    JOIN pickup_slot ps ON ps.slot_id = pr.slot_id
                    WHERE pr.pickup_date = :d
                    GROUP BY HOUR(ps.start_time)";
        } else {
            $sql = "SELECT HOUR(ds.start_time) AS h, COUNT(*) AS total
                    FROM delivery d
                    JOIN delivery_slot ds ON ds.slot_id = d.slot_id
                    WHERE d.delivery_date = :d
                    GROUP BY HOUR(ds.start_time)";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['d' => $date]);
        $rows = $stmt->fetchAll();

        $buckets = array_fill(0, 24, 0);
        foreach ($rows as $r) {
            $h = (int) $r['h'];
            if ($h >= 0 && $h < 24) {
                $buckets[$h] = (int) $r['total'];
            }
        }
        return $buckets;
    }

    // =========================================================
    //  OVERDUE HELPERS
    // =========================================================

    private function isPickupOverdue(array $pickup): bool
    {
        if (in_array($pickup['status'], ['picked_up', 'cancelled'], true)) {
            return false;
        }

        $today = date('Y-m-d');
        if ($pickup['pickup_date'] < $today) {
            return true;
        }

        // Same-day but slot already ended and still not picked up
        if ($pickup['pickup_date'] === $today) {
            $endTime = $pickup['end_time'] ?? null;
            if ($endTime && strtotime($today . ' ' . $endTime) < time()) {
                return true;
            }
        }

        return false;
    }

    private function isDeliveryOverdue(array $delivery): bool
    {
        if (in_array($delivery['status'], ['delivered', 'failed', 'rescheduled'], true)) {
            return false;
        }

        $today = date('Y-m-d');
        if ($delivery['delivery_date'] < $today) {
            return true;
        }

        if ($delivery['delivery_date'] === $today) {
            $endTime = $delivery['end_time'] ?? null;
            if ($endTime && strtotime($today . ' ' . $endTime) < time()) {
                return true;
            }
        }

        return false;
    }

    // =========================================================
    //  UTILITIES
    // =========================================================

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
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
    //  HOOKS — Audit + Notifications (stubbed)
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

    private function notifyBulkAssign(int $staffId, array $pickupIds): void
    {
        // TODO: (new \App\Services\NotificationService())->bulkAssign($staffId, $pickupIds);
        error_log(sprintf(
            '[BulkAssignNotify] staff_id=%d pickups=%s',
            $staffId, json_encode($pickupIds)
        ));
    }

    private function notifyReassign(int $pickupId, int $newStaffId): void
    {
        // TODO: (new \App\Services\NotificationService())->reassign($pickupId, $newStaffId);
        error_log(sprintf(
            '[ReassignNotify] pickup_id=%d new_staff_id=%d',
            $pickupId, $newStaffId
        ));
    }

    private function notifyReschedule(int $pickupId, string $newDate, int $newSlot): void
    {
        // TODO: (new \App\Services\NotificationService())->reschedule($pickupId, $newDate, $newSlot);
        error_log(sprintf(
            '[RescheduleNotify] pickup_id=%d new_date=%s new_slot=%d',
            $pickupId, $newDate, $newSlot
        ));
    }
}
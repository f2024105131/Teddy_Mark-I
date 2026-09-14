<?php

namespace App\Controllers\Staff;

use App\Core\Controller;
use App\Models\PickupRequest;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StaffPerformance;
use App\Models\SystemConfig;

/**
 * Staff\PickupController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Staff-side pickup execution workflow.
 *          View assigned pickups, mark picked-up, reschedule,
 *          cancel, and record failures.
 * ----------------------------------------------------------
 */
class PickupController extends Controller
{
    private PickupRequest      $pickups;
    private Order              $orders;
    private OrderStatusHistory $history;
    private StaffPerformance   $performance;

    /** Valid status transitions for pickup_request */
    private const ALLOWED_TRANSITIONS = [
        'requested'  => ['assigned', 'picked_up', 'cancelled'],
        'assigned'   => ['picked_up', 'cancelled'],
        'picked_up'  => [],          // terminal
        'cancelled'  => [],          // terminal
    ];

    public function __construct()
    {
        parent::__construct();
        $this->pickups     = new PickupRequest();
        $this->orders      = new Order();
        $this->history     = new OrderStatusHistory();
        $this->performance = new StaffPerformance();
    }

    // =========================================================
    //  INDEX — List pickups assigned to current staff
    // =========================================================
    public function index(): void
    {
        $staffId = (int) $this->currentStaffId();
        $filter  = $_GET['status'] ?? 'active'; // active | today | all | requested

        $sql = "SELECT pr.*,
                       c.full_name   AS customer_name,
                       c.phone       AS customer_phone,
                       c.address_house, c.address_street, c.address_area, c.city,
                       ps.start_time, ps.end_time, ps.day_of_week,
                       o.order_id, o.order_number, o.order_status
                FROM pickup_request pr
                JOIN customer      c  ON c.customer_id = pr.customer_id
                JOIN pickup_slot   ps ON ps.slot_id    = pr.slot_id
                LEFT JOIN orders   o  ON o.pickup_id   = pr.pickup_id
                WHERE 1 = 1";
        $params = [];

        // Staff see only their own unless admin role
        if (!$this->isAdmin()) {
            $sql .= " AND pr.assigned_staff_id = :sid";
            $params['sid'] = $staffId;
        }

        switch ($filter) {
            case 'today':
                $sql .= " AND pr.pickup_date = CURDATE()";
                break;
            case 'active':
                $sql .= " AND pr.status IN ('requested','assigned')";
                break;
            case 'all':
                // no filter
                break;
            default:
                if (in_array($filter, ['requested', 'assigned', 'picked_up', 'cancelled'], true)) {
                    $sql .= " AND pr.status = :status";
                    $params['status'] = $filter;
                }
                break;
        }

        $sql .= " ORDER BY pr.pickup_date ASC, ps.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $pickups = $stmt->fetchAll();

        // Stats for header cards
        $stats = $this->getPickupStats($staffId);

        $this->view('staff/pickups/index', [
            'pickups' => $pickups,
            'stats'   => $stats,
            'filter'  => $filter,
        ]);
    }

    // =========================================================
    //  SHOW — Single pickup detail
    // =========================================================
    public function show(int $id): void
    {
        $pickup = $this->getPickupWithRelations($id);

        if (!$pickup) {
            $this->notFound('Pickup not found');
            return;
        }

        // Authorization: staff can only view their own
        if (!$this->isAdmin() && (int) $pickup['assigned_staff_id'] !== (int) $this->currentStaffId()) {
            $this->forbidden();
            return;
        }

        $this->view('staff/pickups/show', ['pickup' => $pickup]);
    }

    // =========================================================
    //  MARK PICKED UP
    // =========================================================
    public function markPickedUp(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId = (int) $this->currentStaffId();
        $pickup  = $this->pickups->find($id);

        if (!$pickup) {
            $this->jsonError('Pickup not found', 404);
            return;
        }

        if (!$this->isAdmin() && (int) $pickup['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this pickup', 403);
            return;
        }

        if (!$this->canTransition($pickup['status'], 'picked_up')) {
            $this->jsonError("Cannot mark as picked up from status: {$pickup['status']}", 422);
            return;
        }

        $notes = trim($_POST['notes'] ?? '');

        try {
            $this->pickups->beginTransaction();

            // 1. Update pickup_request
            $this->pickups->update($id, [
                'status' => 'picked_up',
            ]);

            // 2. Cascade to order
            if (!empty($pickup['pickup_id'])) {
                // Find associated order via pickup_id
                $stmt = $this->db->prepare(
                    "SELECT order_id, order_status FROM orders WHERE pickup_id = :pid LIMIT 1"
                );
                $stmt->execute(['pid' => $id]);
                $order = $stmt->fetch();

                if ($order) {
                    $prevStatus = $order['order_status'];

                    // Idempotent: only change if not already past this point
                    if (!in_array($prevStatus, ['picked_up', 'received_at_laundry',
                                                 'washing', 'ironing', 'ready',
                                                 'out_for_delivery', 'delivered'], true)) {
                        $this->orders->update($order['order_id'], [
                            'order_status' => 'picked_up',
                        ]);

                        // Log transition
                        $this->history->insert([
                            'order_id'            => $order['order_id'],
                            'previous_status'     => $prevStatus,
                            'new_status'          => 'picked_up',
                            'changed_by_staff_id' => $staffId,
                        ]);
                    }
                }
            }

            // 3. Bump staff performance
            $this->performance->bump($staffId, ['pickups_completed' => 1]);

            $this->pickups->commit();

            // 4. Hook for notification (Shehreen's NotificationService)
            $this->notifyStatusChange($id, 'picked_up');

            $this->jsonSuccess([
                'message' => 'Pickup marked as picked up successfully.',
                'pickup_id' => $id,
                'new_status' => 'picked_up',
            ]);

        } catch (\Throwable $e) {
            $this->pickups->rollBack();
            error_log('[PickupController::markPickedUp] ' . $e->getMessage());
            $this->jsonError('Failed to mark pickup. Please try again.', 500);
        }
    }

    // =========================================================
    //  MARK FAILED (customer not available / address issue)
    // =========================================================
    public function markFailed(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId = (int) $this->currentStaffId();
        $pickup  = $this->pickups->find($id);

        if (!$pickup) {
            $this->jsonError('Pickup not found', 404);
            return;
        }

        if (!$this->isAdmin() && (int) $pickup['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this pickup', 403);
            return;
        }

        if (!in_array($pickup['status'], ['requested', 'assigned'], true)) {
            $this->jsonError("Cannot mark failed from status: {$pickup['status']}", 422);
            return;
        }

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '' || mb_strlen($reason) < 5) {
            $this->jsonError('A reason (min 5 characters) is required.', 422);
            return;
        }

        if (mb_strlen($reason) > 255) {
            $this->jsonError('Reason must not exceed 255 characters.', 422);
            return;
        }

        try {
            $this->pickups->update($id, [
                'status'              => 'cancelled',
                'special_instructions'=> $pickup['special_instructions']
                                         ? $pickup['special_instructions'] . "\n[FAILED] " . $reason
                                         : '[FAILED] ' . $reason,
            ]);

            $this->notifyStatusChange($id, 'failed', ['reason' => $reason]);

            $this->jsonSuccess([
                'message' => 'Pickup marked as failed.',
                'pickup_id' => $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[PickupController::markFailed] ' . $e->getMessage());
            $this->jsonError('Failed to update pickup. Please try again.', 500);
        }
    }

    // =========================================================
    //  RESCHEDULE
    // =========================================================
    public function reschedule(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId = (int) $this->currentStaffId();
        $pickup  = $this->pickups->find($id);

        if (!$pickup) {
            $this->jsonError('Pickup not found', 404);
            return;
        }

        if (!$this->isAdmin() && (int) $pickup['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this pickup', 403);
            return;
        }

        if (in_array($pickup['status'], ['picked_up', 'cancelled'], true)) {
            $this->jsonError("Cannot reschedule a {$pickup['status']} pickup.", 422);
            return;
        }

        $newDate = trim($_POST['new_date'] ?? '');
        $newSlot = (int) ($_POST['new_slot_id'] ?? 0);

        // Validate date
        if (!$this->isValidFutureDate($newDate)) {
            $this->jsonError('Invalid pickup date. Must be today or later.', 422);
            return;
        }

        // Validate slot exists, is active, and matches the day-of-week
        $stmt = $this->db->prepare(
            "SELECT slot_id, day_of_week, max_capacity
             FROM pickup_slot
             WHERE slot_id = :sid AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute(['sid' => $newSlot]);
        $slot = $stmt->fetch();

        if (!$slot) {
            $this->jsonError('Selected pickup slot is not available.', 422);
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

        // Capacity check for the new date+slot
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM pickup_request
             WHERE pickup_date = :d AND slot_id = :sid
               AND status IN ('requested','assigned')"
        );
        $stmt->execute(['d' => $newDate, 'sid' => $newSlot]);
        $currentLoad = (int) $stmt->fetchColumn();

        if ($currentLoad >= (int) $slot['max_capacity']) {
            $this->jsonError('Selected slot is fully booked. Please choose another.', 422);
            return;
        }

        try {
            $this->pickups->update($id, [
                'pickup_date' => $newDate,
                'slot_id'     => $newSlot,
                'status'      => 'requested',   // reset to requested on reschedule
            ]);

            $this->notifyStatusChange($id, 'rescheduled', [
                'new_date' => $newDate,
                'new_slot' => $newSlot,
            ]);

            $this->jsonSuccess([
                'message'    => 'Pickup rescheduled successfully.',
                'pickup_id'  => $id,
                'new_date'   => $newDate,
                'new_slot'   => $newSlot,
            ]);

        } catch (\Throwable $e) {
            error_log('[PickupController::reschedule] ' . $e->getMessage());
            $this->jsonError('Failed to reschedule pickup.', 500);
        }
    }

    // =========================================================
    //  ASSIGN TO SELF (or another staff — admin only)
    // =========================================================
    public function assign(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $pickup = $this->pickups->find($id);
        if (!$pickup) {
            $this->jsonError('Pickup not found', 404);
            return;
        }

        // Determine target staff
        $targetStaffId = $this->isAdmin() && !empty($_POST['staff_id'])
            ? (int) $_POST['staff_id']
            : (int) $this->currentStaffId();

        // Validate target staff exists and is active
        $stmt = $this->db->prepare(
            "SELECT staff_id FROM staff WHERE staff_id = :sid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['sid' => $targetStaffId]);
        if (!$stmt->fetch()) {
            $this->jsonError('Target staff member not found or inactive.', 422);
            return;
        }

        if (!in_array($pickup['status'], ['requested', 'assigned'], true)) {
            $this->jsonError("Cannot assign a {$pickup['status']} pickup.", 422);
            return;
        }

        try {
            $this->pickups->update($id, [
                'assigned_staff_id' => $targetStaffId,
                'status'            => 'assigned',
            ]);

            // Sync to order
            $stmt = $this->db->prepare(
                "SELECT order_id FROM orders WHERE pickup_id = :pid LIMIT 1"
            );
            $stmt->execute(['pid' => $id]);
            $order = $stmt->fetch();
            if ($order) {
                $this->orders->update($order['order_id'], [
                    'assigned_staff_id' => $targetStaffId,
                ]);
            }

            $this->jsonSuccess([
                'message'        => 'Pickup assigned successfully.',
                'pickup_id'      => $id,
                'assigned_staff' => $targetStaffId,
            ]);

        } catch (\Throwable $e) {
            error_log('[PickupController::assign] ' . $e->getMessage());
            $this->jsonError('Failed to assign pickup.', 500);
        }
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function getPickupWithRelations(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT pr.*,
                    c.customer_id, c.full_name AS customer_name,
                    c.email AS customer_email, c.phone AS customer_phone,
                    c.address_house, c.address_street, c.address_area,
                    c.city, c.landmark,
                    ps.start_time, ps.end_time, ps.day_of_week, ps.max_capacity,
                    o.order_id, o.order_number, o.order_status, o.order_date,
                    st.full_name AS assigned_staff_name
             FROM pickup_request pr
             JOIN customer      c  ON c.customer_id = pr.customer_id
             JOIN pickup_slot   ps ON ps.slot_id    = pr.slot_id
             LEFT JOIN orders   o  ON o.pickup_id   = pr.pickup_id
             LEFT JOIN staff    st ON st.staff_id   = pr.assigned_staff_id
             WHERE pr.pickup_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function getPickupStats(int $staffId): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN pickup_date = CURDATE()
                             AND status IN ('requested','assigned') THEN 1 ELSE 0 END) AS today_pending,
                    SUM(CASE WHEN pickup_date = CURDATE()
                             AND status = 'picked_up' THEN 1 ELSE 0 END) AS today_completed,
                    SUM(CASE WHEN pickup_date > CURDATE()
                             AND status IN ('requested','assigned') THEN 1 ELSE 0 END) AS upcoming,
                    SUM(CASE WHEN status = 'cancelled'
                             AND pickup_date = CURDATE() THEN 1 ELSE 0 END) AS today_failed
                FROM pickup_request
                WHERE 1 = 1";
        $params = [];
        if (!$this->isAdmin()) {
            $sql .= " AND assigned_staff_id = :sid";
            $params['sid'] = $staffId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'today_pending'   => (int) ($row['today_pending']   ?? 0),
            'today_completed' => (int) ($row['today_completed'] ?? 0),
            'upcoming'        => (int) ($row['upcoming']        ?? 0),
            'today_failed'    => (int) ($row['today_failed']    ?? 0),
        ];
    }

    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    private function isValidFutureDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            return false;
        }
        return $d >= new \DateTime('today');
    }

    /**
     * Notify customer of pickup status change.
     * Integration hook — will call Shehreen's NotificationService when available.
     */
    private function notifyStatusChange(int $pickupId, string $event, array $context = []): void
    {
        // TODO: Replace with: (new \App\Services\NotificationService())->pickupStatus($pickupId, $event, $context);
        // For now, log so we don't silently swallow the event.
        error_log(sprintf(
            '[PickupNotify] pickup_id=%d event=%s context=%s',
            $pickupId,
            $event,
            json_encode($context)
        ));
    }

    private function currentStaffId(): int|string
    {
        // Integration hook — Shehroz's Auth will populate this
        if (function_exists('auth') && auth()->check()) {
            return auth()->id();
        }
        if (isset($_SESSION['staff_id'])) {
            return $_SESSION['staff_id'];
        }
        // Development fallback — DO NOT use in production
        return $_SESSION['staff_id'] ?? 1;
    }

    private function isAdmin(): bool
    {
        if (isset($_SESSION['staff_role'])) {
            return $_SESSION['staff_role'] === 'admin';
        }
        return false;
    }
}
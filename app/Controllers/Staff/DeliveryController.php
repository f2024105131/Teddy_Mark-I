<?php

namespace App\Controllers\Staff;

use App\Core\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StaffPerformance;
use App\Models\SystemConfig;

/**
 * Staff\DeliveryController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Staff-side delivery execution workflow.
 *          Assign, mark out-for-delivery, mark delivered,
 *          mark failed, and reschedule deliveries.
 * ----------------------------------------------------------
 */
class DeliveryController extends Controller
{
    private Delivery           $deliveries;
    private Order              $orders;
    private OrderStatusHistory $history;
    private StaffPerformance   $performance;

    /** Valid transitions for delivery.status */
    private const ALLOWED_TRANSITIONS = [
        'scheduled'        => ['out_for_delivery', 'failed', 'rescheduled'],
        'out_for_delivery' => ['delivered', 'failed', 'rescheduled'],
        'delivered'        => [],  // terminal
        'failed'           => ['rescheduled'],
        'rescheduled'      => ['scheduled', 'out_for_delivery'],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->deliveries  = new Delivery();
        $this->orders      = new Order();
        $this->history     = new OrderStatusHistory();
        $this->performance = new StaffPerformance();
    }

    // =========================================================
    //  INDEX — List deliveries for current staff
    // =========================================================
    public function index(): void
    {
        $staffId = (int) $this->currentStaffId();
        $filter  = $_GET['status'] ?? 'active'; // active | today | all | scheduled | out_for_delivery | delivered | failed

        $sql = "SELECT d.*,
                       o.order_number, o.order_status, o.delivered_at AS order_delivered_at,
                       c.full_name AS customer_name, c.phone AS customer_phone,
                       c.address_house, c.address_street, c.address_area, c.city,
                       ds.start_time, ds.end_time,
                       st.full_name AS assigned_staff_name
                FROM delivery d
                JOIN orders        o  ON o.order_id    = d.order_id
                JOIN customer      c  ON c.customer_id = o.customer_id
                JOIN delivery_slot ds ON ds.slot_id    = d.slot_id
                LEFT JOIN staff    st ON st.staff_id   = d.assigned_staff_id
                WHERE 1 = 1";
        $params = [];

        if (!$this->isAdmin()) {
            $sql .= " AND d.assigned_staff_id = :sid";
            $params['sid'] = $staffId;
        }

        switch ($filter) {
            case 'today':
                $sql .= " AND d.delivery_date = CURDATE()";
                break;
            case 'active':
                $sql .= " AND d.status IN ('scheduled','out_for_delivery')";
                break;
            case 'all':
                // no filter
                break;
            default:
                if (in_array($filter, ['scheduled','out_for_delivery','delivered','failed','rescheduled'], true)) {
                    $sql .= " AND d.status = :status";
                    $params['status'] = $filter;
                }
                break;
        }

        $sql .= " ORDER BY d.delivery_date ASC, ds.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $deliveries = $stmt->fetchAll();

        $stats = $this->getDeliveryStats($staffId);

        $this->view('staff/deliveries/index', [
            'deliveries' => $deliveries,
            'stats'      => $stats,
            'filter'     => $filter,
        ]);
    }

    // =========================================================
    //  SHOW — Single delivery detail
    // =========================================================
    public function show(int $id): void
    {
        $delivery = $this->getDeliveryWithRelations($id);

        if (!$delivery) {
            $this->notFound('Delivery not found');
            return;
        }

        if (!$this->isAdmin() && (int) $delivery['assigned_staff_id'] !== (int) $this->currentStaffId()) {
            $this->forbidden();
            return;
        }

        $this->view('staff/deliveries/show', ['delivery' => $delivery]);
    }

    // =========================================================
    //  ASSIGN TO SELF / ANOTHER STAFF
    // =========================================================
    public function assign(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $delivery = $this->deliveries->find($id);
        if (!$delivery) {
            $this->jsonError('Delivery not found', 404);
            return;
        }

        $targetStaffId = $this->isAdmin() && !empty($_POST['staff_id'])
            ? (int) $_POST['staff_id']
            : (int) $this->currentStaffId();

        $stmt = $this->db->prepare(
            "SELECT staff_id FROM staff WHERE staff_id = :sid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['sid' => $targetStaffId]);
        if (!$stmt->fetch()) {
            $this->jsonError('Target staff member not found or inactive.', 422);
            return;
        }

        if (!in_array($delivery['status'], ['scheduled', 'rescheduled'], true)) {
            $this->jsonError("Cannot assign a {$delivery['status']} delivery.", 422);
            return;
        }

        try {
            $this->deliveries->update($id, [
                'assigned_staff_id' => $targetStaffId,
                'status'            => 'scheduled',
            ]);

            $this->jsonSuccess([
                'message'        => 'Delivery assigned successfully.',
                'delivery_id'    => $id,
                'assigned_staff' => $targetStaffId,
            ]);

        } catch (\Throwable $e) {
            error_log('[DeliveryController::assign] ' . $e->getMessage());
            $this->jsonError('Failed to assign delivery.', 500);
        }
    }

    // =========================================================
    //  MARK OUT FOR DELIVERY
    // =========================================================
    public function markOutForDelivery(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId  = (int) $this->currentStaffId();
        $delivery = $this->deliveries->find($id);

        if (!$delivery) {
            $this->jsonError('Delivery not found', 404);
            return;
        }

        if (!$this->isAdmin() && (int) $delivery['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this delivery', 403);
            return;
        }

        if (!$this->canTransition($delivery['status'], 'out_for_delivery')) {
            $this->jsonError("Cannot mark out-for-delivery from status: {$delivery['status']}", 422);
            return;
        }

        try {
            $this->deliveries->beginTransaction();

            $this->deliveries->update($id, [
                'status' => 'out_for_delivery',
            ]);

            // Cascade to order
            $order = $this->orders->find($delivery['order_id']);
            if ($order) {
                $prevStatus = $order['order_status'];

                if (!in_array($prevStatus, ['out_for_delivery', 'delivered'], true)) {
                    $this->orders->update($order['order_id'], [
                        'order_status'        => 'out_for_delivery',
                        'out_for_delivery_at' => date('Y-m-d H:i:s'),
                        'assigned_staff_id'   => $staffId,
                    ]);

                    $this->history->insert([
                        'order_id'            => $order['order_id'],
                        'previous_status'     => $prevStatus,
                        'new_status'          => 'out_for_delivery',
                        'changed_by_staff_id' => $staffId,
                    ]);
                }
            }

            $this->deliveries->commit();

            $this->notifyStatusChange($id, 'out_for_delivery');
            $this->notifyOrderStatusChange($delivery['order_id'], 'out_for_delivery');

            $this->jsonSuccess([
                'message'     => 'Delivery marked as out for delivery.',
                'delivery_id' => $id,
                'new_status'  => 'out_for_delivery',
            ]);

        } catch (\Throwable $e) {
            $this->deliveries->rollBack();
            error_log('[DeliveryController::markOutForDelivery] ' . $e->getMessage());
            $this->jsonError('Failed to update delivery.', 500);
        }
    }

    // =========================================================
    //  MARK DELIVERED
    // =========================================================
    public function markDelivered(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId  = (int) $this->currentStaffId();
        $delivery = $this->deliveries->find($id);

        if (!$delivery) {
            $this->jsonError('Delivery not found', 404);
            return;
        }

        if (!$this->isAdmin() && (int) $delivery['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this delivery', 403);
            return;
        }

        if (!$this->canTransition($delivery['status'], 'delivered')) {
            $this->jsonError("Cannot mark delivered from status: {$delivery['status']}", 422);
            return;
        }

        $notes = trim($_POST['delivery_notes'] ?? '');

        try {
            $this->deliveries->beginTransaction();

            $now = date('Y-m-d H:i:s');

            // 1. Update delivery row
            $this->deliveries->update($id, [
                'status'         => 'delivered',
                'delivered_at'   => $now,
                'delivery_notes' => $notes !== '' ? $notes : null,
            ]);

            // 2. Cascade to order
            $order = $this->orders->find($delivery['order_id']);
            if ($order) {
                $prevStatus = $order['order_status'];

                if ($prevStatus !== 'delivered') {
                    $this->orders->update($order['order_id'], [
                        'order_status' => 'delivered',
                        'delivered_at' => $now,
                        'delivery_date'=> date('Y-m-d'),
                    ]);

                    $this->history->insert([
                        'order_id'            => $order['order_id'],
                        'previous_status'     => $prevStatus,
                        'new_status'          => 'delivered',
                        'changed_by_staff_id' => $staffId,
                    ]);
                }
            }

            // 3. Bump staff performance
            $this->performance->bump($staffId, ['deliveries_completed' => 1]);

            $this->deliveries->commit();

            // 4. Hooks for Shehreen's services
            $this->notifyOrderStatusChange($delivery['order_id'], 'delivered');
            $this->finalizeBilling($delivery['order_id']);
            $this->generateReceipt($delivery['order_id'], $staffId);

            $this->jsonSuccess([
                'message'     => 'Delivery marked as delivered.',
                'delivery_id' => $id,
                'delivered_at'=> $now,
            ]);

        } catch (\Throwable $e) {
            $this->deliveries->rollBack();
            error_log('[DeliveryController::markDelivered] ' . $e->getMessage());
            $this->jsonError('Failed to mark delivered.', 500);
        }
    }

    // =========================================================
    //  MARK FAILED
    // =========================================================
    public function markFailed(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId  = (int) $this->currentStaffId();
        $delivery = $this->deliveries->find($id);

        if (!$delivery) {
            $this->jsonError('Delivery not found', 404);
            return;
        }

        if (!$this->isAdmin() && (int) $delivery['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this delivery', 403);
            return;
        }

        if (!$this->canTransition($delivery['status'], 'failed')) {
            $this->jsonError("Cannot mark failed from status: {$delivery['status']}", 422);
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
            $this->deliveries->update($id, [
                'status'         => 'failed',
                'failure_reason' => $reason,
            ]);

            $this->notifyStatusChange($id, 'failed', ['reason' => $reason]);

            $this->jsonSuccess([
                'message'     => 'Delivery marked as failed.',
                'delivery_id' => $id,
                'reason'      => $reason,
            ]);

        } catch (\Throwable $e) {
            error_log('[DeliveryController::markFailed] ' . $e->getMessage());
            $this->jsonError('Failed to update delivery.', 500);
        }
    }

    // =========================================================
    //  RESCHEDULE
    // =========================================================
    public function reschedule(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId  = (int) $this->currentStaffId();
        $delivery = $this->deliveries->find($id);

        if (!$delivery) {
            $this->jsonError('Delivery not found', 404);
            return;
        }

        if (!$this->isAdmin() && (int) $delivery['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this delivery', 403);
            return;
        }

        if (!in_array($delivery['status'], ['scheduled', 'out_for_delivery', 'failed', 'rescheduled'], true)) {
            $this->jsonError("Cannot reschedule a {$delivery['status']} delivery.", 422);
            return;
        }

        $newDate = trim($_POST['new_date'] ?? '');
        $newSlot = (int) ($_POST['new_slot_id'] ?? 0);

        if (!$this->isValidFutureDate($newDate)) {
            $this->jsonError('Invalid delivery date. Must be today or later.', 422);
            return;
        }

        // Validate slot
        $stmt = $this->db->prepare(
            "SELECT slot_id, max_capacity FROM delivery_slot
             WHERE slot_id = :sid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['sid' => $newSlot]);
        $slot = $stmt->fetch();

        if (!$slot) {
            $this->jsonError('Selected delivery slot is not available.', 422);
            return;
        }

        // Capacity
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM delivery
             WHERE delivery_date = :d AND slot_id = :sid
               AND status IN ('scheduled','out_for_delivery')"
        );
        $stmt->execute(['d' => $newDate, 'sid' => $newSlot]);
        $currentLoad = (int) $stmt->fetchColumn();

        if ($currentLoad >= (int) $slot['max_capacity']) {
            $this->jsonError('Selected slot is fully booked. Please choose another.', 422);
            return;
        }

        try {
            $this->deliveries->update($id, [
                'delivery_date' => $newDate,
                'slot_id'       => $newSlot,
                'status'        => 'rescheduled',
                'failure_reason'=> null,
            ]);

            // Also update order's estimated delivery date
            if ($delivery['order_id']) {
                $this->orders->update($delivery['order_id'], [
                    'estimated_delivery_date' => $newDate,
                ]);
            }

            $this->notifyStatusChange($id, 'rescheduled', [
                'new_date' => $newDate,
                'new_slot' => $newSlot,
            ]);

            $this->jsonSuccess([
                'message'     => 'Delivery rescheduled successfully.',
                'delivery_id' => $id,
                'new_date'    => $newDate,
                'new_slot'    => $newSlot,
            ]);

        } catch (\Throwable $e) {
            error_log('[DeliveryController::reschedule] ' . $e->getMessage());
            $this->jsonError('Failed to reschedule delivery.', 500);
        }
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function getDeliveryWithRelations(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT d.*,
                    o.order_number, o.order_status, o.order_date,
                    o.special_instructions, o.delivered_at AS order_delivered_at,
                    c.customer_id, c.full_name AS customer_name,
                    c.email AS customer_email, c.phone AS customer_phone,
                    c.address_house, c.address_street, c.address_area,
                    c.city, c.landmark,
                    ds.start_time, ds.end_time, ds.max_capacity,
                    st.full_name AS assigned_staff_name
             FROM delivery d
             JOIN orders        o  ON o.order_id    = d.order_id
             JOIN customer      c  ON c.customer_id = o.customer_id
             JOIN delivery_slot ds ON ds.slot_id    = d.slot_id
             LEFT JOIN staff    st ON st.staff_id   = d.assigned_staff_id
             WHERE d.delivery_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function getDeliveryStats(int $staffId): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN delivery_date = CURDATE()
                             AND status IN ('scheduled','out_for_delivery') THEN 1 ELSE 0 END) AS today_pending,
                    SUM(CASE WHEN delivery_date = CURDATE()
                             AND status = 'delivered' THEN 1 ELSE 0 END) AS today_completed,
                    SUM(CASE WHEN delivery_date > CURDATE()
                             AND status IN ('scheduled','out_for_delivery') THEN 1 ELSE 0 END) AS upcoming,
                    SUM(CASE WHEN status = 'failed'
                             AND delivery_date = CURDATE() THEN 1 ELSE 0 END) AS today_failed
                FROM delivery
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

    // =========================================================
    //  NOTIFICATION / BILLING HOOKS (Shehreen's services)
    // =========================================================

    private function notifyStatusChange(int $deliveryId, string $event, array $context = []): void
    {
        // TODO: (new \App\Services\NotificationService())->deliveryStatus($deliveryId, $event, $context);
        error_log(sprintf(
            '[DeliveryNotify] delivery_id=%d event=%s context=%s',
            $deliveryId,
            $event,
            json_encode($context)
        ));
    }

    private function notifyOrderStatusChange(int $orderId, string $newStatus): void
    {
        // TODO: (new \App\Services\OrderStatusService())->notify($orderId, $newStatus);
        error_log(sprintf('[OrderNotify] order_id=%d status=%s', $orderId, $newStatus));
    }

    private function finalizeBilling(int $orderId): void
    {
        // TODO: (new \App\Services\BillingService())->finalizeBill($orderId);
        error_log('[BillingHook] finalizeBill order_id=' . $orderId);
    }

    private function generateReceipt(int $orderId, int $staffId): void
    {
        // TODO: (new \App\Services\ReceiptInvoiceService())->generateReceipt($orderId, $staffId);
        error_log('[ReceiptHook] generateReceipt order_id=' . $orderId . ' staff_id=' . $staffId);
    }

    // =========================================================
    //  AUTH / SESSION HELPERS
    // =========================================================

    private function currentStaffId(): int|string
    {
        if (isset($_SESSION['staff_id'])) {
            return $_SESSION['staff_id'];
        }
        // Development fallback
        return 1;
    }

    private function isAdmin(): bool
    {
        return ($_SESSION['staff_role'] ?? '') === 'admin';
    }
}
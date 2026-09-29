<?php

namespace App\Controllers\Staff;

use App\Core\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StaffPerformance;

/**
 * Staff\OrderController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Staff-side order processing workflow.
 *            • View orders in the laundry pipeline
 *            • Filter by status / staff / date
 *            • Detail view with items, customer, timeline
 *            • Advance status (received → washing → ironing → ready)
 *            • Assign to self / another staff
 *            • Add notes / special instructions
 *            • Cancel order (with reason)
 *            • Auto timestamps + performance tracking
 *
 * Notes:
 *   • Uses raw SQL for reads (Faizan owns Order model).
 *   • Writes go through $this->db->prepare() to avoid
 *     coupling. Order/OrderStatusHistory/StaffPerformance
 *     models are used where already available.
 *   • Every status change is logged in order_status_history.
 *   • Every status change is guarded by a transition matrix.
 * ----------------------------------------------------------
 */
class OrderController extends Controller
{
    private OrderStatusHistory $history;
    private StaffPerformance   $performance;

    /** Valid order_status enum values from schema */
    private const STATUSES = [
        'pickup_requested',
        'pickup_assigned',
        'picked_up',
        'received_at_laundry',
        'washing',
        'ironing',
        'ready',
        'out_for_delivery',
        'delivered',
        'cancelled',
    ];

    /** Statuses still "in the laundry pipeline" (staff-actionable) */
    private const ACTIVE_STATUSES = [
        'picked_up',
        'received_at_laundry',
        'washing',
        'ironing',
        'ready',
    ];

    /** Statuses that are terminal — no further processing */
    private const TERMINAL_STATUSES = ['delivered', 'cancelled'];

    /** Human-readable labels for statuses */
    private const STATUS_LABELS = [
        'pickup_requested'    => 'Pickup Requested',
        'pickup_assigned'     => 'Pickup Assigned',
        'picked_up'           => 'Picked Up',
        'received_at_laundry' => 'Received at Laundry',
        'washing'             => 'Washing',
        'ironing'             => 'Ironing',
        'ready'               => 'Ready',
        'out_for_delivery'    => 'Out for Delivery',
        'delivered'           => 'Delivered',
        'cancelled'           => 'Cancelled',
    ];

    /**
     * Allowed status transitions.
     * 'wash_fold' orders can skip 'ironing' and go washing → ready.
     * Use the guarded transition() method to enforce this.
     */
    private const ALLOWED_TRANSITIONS = [
        'pickup_requested'    => ['pickup_assigned', 'picked_up', 'cancelled'],
        'pickup_assigned'     => ['picked_up', 'cancelled'],
        'picked_up'           => ['received_at_laundry', 'cancelled'],
        'received_at_laundry' => ['washing', 'cancelled'],
        'washing'             => ['ironing', 'ready', 'cancelled'],
        'ironing'             => ['ready', 'cancelled'],
        'ready'               => ['out_for_delivery', 'cancelled'],
        'out_for_delivery'    => ['delivered', 'cancelled'],
        'delivered'           => [],  // terminal
        'cancelled'           => [],  // terminal
    ];

    public function __construct()
    {
        parent::__construct();
        $this->history     = new OrderStatusHistory();
        $this->performance = new StaffPerformance();
    }

    // =========================================================
    //  INDEX — List orders in the pipeline
    // =========================================================
    public function index(): void
    {
        $staffId = (int) $this->currentStaffId();
        $filter  = $_GET['status'] ?? 'active';
        $search  = trim($_GET['search'] ?? '');
        $date    = $_GET['date']   ?? '';

        $sql = "SELECT o.order_id, o.order_number, o.order_status,
                       o.order_date, o.estimated_delivery_date, o.delivery_date,
                       o.assigned_staff_id,
                       c.customer_id, c.full_name AS customer_name,
                       c.phone     AS customer_phone,
                       st.full_name AS assigned_staff_name,
                       (SELECT COUNT(*) FROM order_item oi
                          WHERE oi.order_id = o.order_id) AS item_count,
                       (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_item oi
                          WHERE oi.order_id = o.order_id) AS total_quantity
                FROM orders o
                JOIN customer c ON c.customer_id = o.customer_id
                LEFT JOIN staff st ON st.staff_id = o.assigned_staff_id
                WHERE 1 = 1";

        $params = [];

        // Non-admins see only their own orders
        if (!$this->isAdmin()) {
            $sql .= " AND o.assigned_staff_id = :sid";
            $params['sid'] = $staffId;
        }

        // Status filter
        switch ($filter) {
            case 'active':
                $placeholders = [];
                foreach (self::ACTIVE_STATUSES as $i => $status) {
                    $key = 'st' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $status;
                }
                $sql .= " AND o.order_status IN (" . implode(',', $placeholders) . ")";
                break;

            case 'washing':
                $sql .= " AND o.order_status = 'washing'";
                break;

            case 'ironing':
                $sql .= " AND o.order_status = 'ironing'";
                break;

            case 'ready':
                $sql .= " AND o.order_status = 'ready'";
                break;

            case 'all':
                // no filter
                break;

            default:
                if (in_array($filter, self::STATUSES, true)) {
                    $sql .= " AND o.order_status = :status";
                    $params['status'] = $filter;
                }
                break;
        }

        // Search
        if ($search !== '') {
            $sql .= " AND (o.order_number LIKE :search
                        OR c.full_name    LIKE :search
                        OR c.phone        LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        // Date filter (order_date)
        if ($date !== '' && $this->isValidDate($date)) {
            $sql .= " AND DATE(o.order_date) = :d";
            $params['d'] = $date;
        }

        $sql .= " ORDER BY
                    FIELD(o.order_status,
                          'received_at_laundry',
                          'washing',
                          'ironing',
                          'ready',
                          'picked_up',
                          'out_for_delivery',
                          'pickup_assigned',
                          'pickup_requested',
                          'delivered',
                          'cancelled'),
                    o.order_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Stats cards
        $stats = $this->getOrderStats($staffId);

        $this->view('staff/orders/index', [
            'orders'       => $orders,
            'stats'        => $stats,
            'filter'       => $filter,
            'search'       => $search,
            'date'         => $date,
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    // =========================================================
    //  SHOW — Order detail with items + timeline
    // =========================================================
    public function show(int $id): void
    {
        $order = $this->findOrderDetailed($id);

        if (!$order) {
            $this->notFound('Order not found.');
            return;
        }

        // Authorization: staff can view their own orders; admin any
        if (!$this->isAdmin()
            && (int) $order['assigned_staff_id'] !== (int) $this->currentStaffId()) {
            $this->forbidden();
            return;
        }

        // Load order items
        $stmt = $this->db->prepare(
            "SELECT oi.order_item_id, oi.quantity,
                    oi.unit_price, oi.total_price,
                    it.item_id, it.item_name,
                    ic.category_name,
                    s.service_id, s.service_name, s.service_type
             FROM order_item oi
             JOIN item_type     it ON it.item_id     = oi.item_id
             JOIN item_category ic ON ic.category_id = it.category_id
             JOIN service       s  ON s.service_id   = oi.service_id
             WHERE oi.order_id = :oid
             ORDER BY ic.category_name ASC, it.item_name ASC"
        );
        $stmt->execute(['oid' => $id]);
        $items = $stmt->fetchAll();

        // Load status history (audit trail)
        $stmt = $this->db->prepare(
            "SELECT osh.*, s.full_name AS changed_by_name
             FROM order_status_history osh
             LEFT JOIN staff s ON s.staff_id = osh.changed_by_staff_id
             WHERE osh.order_id = :oid
             ORDER BY osh.changed_at ASC"
        );
        $stmt->execute(['oid' => $id]);
        $history = $stmt->fetchAll();

        // Bill (if exists)
        $stmt = $this->db->prepare(
            "SELECT bill_id, bill_number, total_amount, paid_amount,
                    outstanding_amount, payment_status, generated_at
             FROM bill
             WHERE order_id = :oid
             LIMIT 1"
        );
        $stmt->execute(['oid' => $id]);
        $bill = $stmt->fetch() ?: null;

        // Delivery (if exists)
        $stmt = $this->db->prepare(
            "SELECT d.*, ds.start_time, ds.end_time,
                    st.full_name AS delivery_staff_name
             FROM delivery d
             LEFT JOIN delivery_slot ds ON ds.slot_id = d.slot_id
             LEFT JOIN staff st ON st.staff_id = d.assigned_staff_id
             WHERE d.order_id = :oid
             LIMIT 1"
        );
        $stmt->execute(['oid' => $id]);
        $delivery = $stmt->fetch() ?: null;

        // Next allowed statuses for the UI action buttons
        $nextStatuses = self::ALLOWED_TRANSITIONS[$order['order_status']] ?? [];

        $this->view('staff/orders/show', [
            'order'        => $order,
            'items'        => $items,
            'history'      => $history,
            'bill'         => $bill,
            'delivery'     => $delivery,
            'nextStatuses' => $nextStatuses,
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    // =========================================================
    //  ASSIGN — Assign order to staff (self or another)
    // =========================================================
    public function assign(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $order = $this->findOrder($id);
        if (!$order) {
            $this->jsonError('Order not found.', 404);
            return;
        }

        if (in_array($order['order_status'], self::TERMINAL_STATUSES, true)) {
            $this->jsonError("Cannot assign a {$order['order_status']} order.", 422);
            return;
        }

        // Determine target staff
        $targetStaffId = $this->isAdmin() && !empty($_POST['staff_id'])
            ? (int) $_POST['staff_id']
            : (int) $this->currentStaffId();

        // Verify target staff is active
        $stmt = $this->db->prepare(
            "SELECT staff_id, full_name FROM staff
             WHERE staff_id = :sid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['sid' => $targetStaffId]);
        $staff = $stmt->fetch();

        if (!$staff) {
            $this->jsonError('Target staff member not found or inactive.', 422);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE orders SET assigned_staff_id = :sid WHERE order_id = :id"
            )->execute(['sid' => $targetStaffId, 'id' => $id]);

            $this->audit('order', 'assign', $id,
                ['assigned_staff_id' => $order['assigned_staff_id']],
                ['assigned_staff_id' => $targetStaffId]
            );

            $this->jsonSuccess([
                'message'    => "Order assigned to {$staff['full_name']}.",
                'order_id'   => $id,
                'staff_id'   => $targetStaffId,
                'staff_name' => $staff['full_name'],
            ]);

        } catch (\Throwable $e) {
            error_log('[StaffOrderController::assign] ' . $e->getMessage());
            $this->jsonError('Failed to assign order.', 500);
        }
    }

    // =========================================================
    //  UPDATE STATUS — the core workflow method
    // =========================================================
    public function updateStatus(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId   = (int) $this->currentStaffId();
        $order     = $this->findOrder($id);
        $newStatus = $_POST['status'] ?? '';
        $note      = trim($_POST['note'] ?? '');

        if (!$order) {
            $this->jsonError('Order not found.', 404);
            return;
        }

        // Authorization
        if (!$this->isAdmin()
            && (int) $order['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this order.', 403);
            return;
        }

        // Validate the new status is a real enum value
        if (!in_array($newStatus, self::STATUSES, true)) {
            $this->jsonError('Invalid status.', 422);
            return;
        }

        // Guard: state transition must be legal
        if (!$this->canTransition($order['order_status'], $newStatus)) {
            $this->jsonError(
                "Cannot transition from '{$order['order_status']}' to '{$newStatus}'.",
                422
            );
            return;
        }

        // Guard: cannot cancel without a reason
        if ($newStatus === 'cancelled') {
            if ($note === '' || mb_strlen($note) < 5) {
                $this->jsonError('Cancellation requires a reason (min 5 characters).', 422);
                return;
            }
        }

        // Guard: length cap on notes
        if ($note !== '' && mb_strlen($note) > 2000) {
            $this->jsonError('Note must not exceed 2000 characters.', 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            $now         = date('Y-m-d H:i:s');
            $oldStatus   = $order['order_status'];
            $updateData  = ['order_status' => $newStatus];
            $perfDeltas  = [];

            // Auto-fill timestamps based on the new status
            switch ($newStatus) {
                case 'washing':
                    if (empty($order['washing_started_at'])) {
                        $updateData['washing_started_at'] = $now;
                    }
                    break;

                case 'ironing':
                    // If coming from washing, close out washing
                    if ($oldStatus === 'washing' && empty($order['washing_completed_at'])) {
                        $updateData['washing_completed_at'] = $now;
                    }
                    if (empty($order['ironing_started_at'])) {
                        $updateData['ironing_started_at'] = $now;
                    }
                    break;

                case 'ready':
                    // Close out ironing if we were in it
                    if ($oldStatus === 'ironing' && empty($order['ironing_completed_at'])) {
                        $updateData['ironing_completed_at'] = $now;
                    }
                    // Close out washing if we skipped ironing
                    if ($oldStatus === 'washing' && empty($order['washing_completed_at'])) {
                        $updateData['washing_completed_at'] = $now;
                    }
                    if (empty($order['ready_at'])) {
                        $updateData['ready_at'] = $now;
                    }
                    // Bump staff performance: order handled through pipeline
                    $perfDeltas['orders_handled'] = 1;
                    $perfDeltas['total_orders']   = 1;
                    break;

                case 'out_for_delivery':
                    $updateData['out_for_delivery_at'] = $now;
                    break;

                case 'delivered':
                    $updateData['delivered_at']  = $now;
                    $updateData['delivery_date'] = date('Y-m-d');
                    break;

                case 'cancelled':
                    // Append reason to special_instructions for audit trail
                    $existing = $order['special_instructions'] ?? '';
                    $entry    = sprintf(
                        "\n[%s] CANCELLED by staff: %s",
                        $now,
                        $note
                    );
                    $updateData['special_instructions'] = $existing . $entry;
                    break;

                case 'received_at_laundry':
                case 'picked_up':
                case 'pickup_assigned':
                case 'pickup_requested':
                    // No timestamps to set
                    break;
            }

            // Apply order update
            $setClauses = [];
            $bind       = ['id' => $id];
            foreach ($updateData as $col => $val) {
                $setClauses[]   = "{$col} = :{$col}";
                $bind[$col]     = $val;
            }
            $this->db->prepare(
                "UPDATE orders SET " . implode(', ', $setClauses) . " WHERE order_id = :id"
            )->execute($bind);

            // Log to history
            $this->history->insert([
                'order_id'            => $id,
                'previous_status'     => $oldStatus,
                'new_status'          => $newStatus,
                'changed_by_staff_id' => $staffId,
            ]);

            // Bump staff performance
            if (!empty($perfDeltas)) {
                $this->performance->bump($staffId, $perfDeltas);
            }

            $this->db->commit();

            // Notify customer (Shehreen's NotificationService)
            $this->notifyOrderStatus($id, $newStatus, $oldStatus);

            $this->jsonSuccess([
                'message'    => "Order status updated to '" . self::STATUS_LABELS[$newStatus] . "'.",
                'order_id'   => $id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_at' => $now,
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[StaffOrderController::updateStatus] ' . $e->getMessage());
            $this->jsonError('Failed to update order status.', 500);
        }
    }

    // =========================================================
    //  ADD NOTE — append to special_instructions
    // =========================================================
    public function addNote(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId = (int) $this->currentStaffId();
        $order   = $this->findOrder($id);

        if (!$order) {
            $this->jsonError('Order not found.', 404);
            return;
        }

        if (!$this->isAdmin()
            && (int) $order['assigned_staff_id'] !== $staffId) {
            $this->jsonError('Not authorized for this order.', 403);
            return;
        }

        if (in_array($order['order_status'], self::TERMINAL_STATUSES, true)) {
            $this->jsonError("Cannot add notes to a {$order['order_status']} order.", 422);
            return;
        }

        $note = trim($_POST['note'] ?? '');

        if ($note === '' || mb_strlen($note) < 3) {
            $this->jsonError('Note must be at least 3 characters.', 422);
            return;
        }

        if (mb_strlen($note) > 2000) {
            $this->jsonError('Note must not exceed 2000 characters.', 422);
            return;
        }

        try {
            $staffName = $_SESSION['staff_name'] ?? 'Staff';
            $timestamp = date('Y-m-d H:i:s');
            $entry     = "\n[{$timestamp}] {$staffName}: {$note}";

            $existing = $order['special_instructions'] ?? '';

            $this->db->prepare(
                "UPDATE orders SET special_instructions = :notes WHERE order_id = :id"
            )->execute([
                'notes' => $existing . $entry,
                'id'    => $id,
            ]);

            $this->audit('order', 'add_note', $id, null, ['note' => $note]);

            $this->jsonSuccess([
                'message'  => 'Note added.',
                'order_id' => $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[StaffOrderController::addNote] ' . $e->getMessage());
            $this->jsonError('Failed to add note.', 500);
        }
    }

    // =========================================================
    //  MARK RECEIVED — shortcut helper
    // =========================================================
    public function markReceived(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $_POST['status'] = 'received_at_laundry';
        $_POST['note']   = $_POST['note'] ?? 'Marked as received at laundry.';

        $this->updateStatus($id);
    }

    // =========================================================
    //  MARK READY — shortcut helper
    // =========================================================
    public function markReady(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $_POST['status'] = 'ready';
        $_POST['note']   = $_POST['note'] ?? 'Marked as ready.';

        $this->updateStatus($id);
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function findOrder(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM orders WHERE order_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function findOrderDetailed(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT o.*,
                    c.customer_id, c.full_name AS customer_name,
                    c.email     AS customer_email,
                    c.phone     AS customer_phone,
                    c.address_house, c.address_street,
                    c.address_area, c.city, c.landmark,
                    st.full_name AS assigned_staff_name,
                    pr.request_number, pr.status AS pickup_status
             FROM orders o
             JOIN customer c ON c.customer_id = o.customer_id
             LEFT JOIN staff st ON st.staff_id = o.assigned_staff_id
             LEFT JOIN pickup_request pr ON pr.pickup_id = o.pickup_id
             WHERE o.order_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function getOrderStats(int $staffId): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN order_status = 'received_at_laundry' THEN 1 ELSE 0 END) AS received,
                    SUM(CASE WHEN order_status = 'washing'             THEN 1 ELSE 0 END) AS washing,
                    SUM(CASE WHEN order_status = 'ironing'             THEN 1 ELSE 0 END) AS ironing,
                    SUM(CASE WHEN order_status = 'ready'               THEN 1 ELSE 0 END) AS ready,
                    SUM(CASE WHEN order_status IN ('picked_up','received_at_laundry',
                                                   'washing','ironing','ready') THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN order_status = 'delivered'
                             AND DATE(delivered_at) = CURDATE()           THEN 1 ELSE 0 END) AS delivered_today
                FROM orders
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
            'received'        => (int) ($row['received']        ?? 0),
            'washing'         => (int) ($row['washing']         ?? 0),
            'ironing'         => (int) ($row['ironing']         ?? 0),
            'ready'           => (int) ($row['ready']           ?? 0),
            'active'          => (int) ($row['active']          ?? 0),
            'delivered_today' => (int) ($row['delivered_today'] ?? 0),
        ];
    }

    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    // =========================================================
    //  AUTH / SESSION HELPERS
    // =========================================================

    private function currentStaffId(): int|string
    {
        if (isset($_SESSION['staff_id'])) {
            return $_SESSION['staff_id'];
        }
        return 0;
    }

    private function isAdmin(): bool
    {
        return ($_SESSION['staff_role'] ?? '') === 'admin';
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

    private function notifyOrderStatus(int $orderId, string $newStatus, string $oldStatus): void
    {
        // TODO: (new \App\Services\OrderStatusService())->notify($orderId, $newStatus, $oldStatus);
        // TODO: (new \App\Services\NotificationService())->orderStatus($orderId, $newStatus);
        error_log(sprintf(
            '[OrderStatusNotify] order_id=%d old=%s new=%s',
            $orderId, $oldStatus, $newStatus
        ));
    }
}
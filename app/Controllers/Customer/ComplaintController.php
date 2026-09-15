<?php

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Order;
use App\Models\SystemConfig;

/**
 * Customer\ComplaintController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Customer-facing complaint raising & tracking portal.
 *          Customers can:
 *            • View their complaint list
 *            • Raise a complaint for a delivered order
 *            • View a complaint's detail + resolution status
 *            • Cancel a complaint (only while still 'open')
 *          Cannot: assign, resolve, reject, escalate, refund.
 *          Those are staff/admin-only actions.
 * ----------------------------------------------------------
 */
class ComplaintController extends Controller
{
    private Complaint         $complaints;
    private ComplaintCategory $categories;
    private Order             $orders;

    /** Business rule: customer can only file within N days of delivery */
    private const DEFAULT_WINDOW_DAYS = 7;

    /** Customer-cancellable statuses */
    private const CANCELLABLE_STATUSES = ['open'];

    /** Allowed complaint types — must match schema ENUM exactly */
    private const ALLOWED_TYPES = [
        'missing_item',
        'damaged_item',
        'late_delivery',
        'wrong_billing',
        'poor_cleaning_quality',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->complaints = new Complaint();
        $this->categories = new ComplaintCategory();
        $this->orders     = new Order();
    }

    // =========================================================
    //  INDEX — List all complaints raised by the logged-in customer
    // =========================================================
    public function index(): void
    {
        $customerId = (int) $this->currentCustomerId();
        if ($customerId <= 0) {
            $this->redirect('/login');
            return;
        }

        $filter = $_GET['status'] ?? 'all';

        $sql = "SELECT cm.complaint_id, cm.complaint_number, cm.type,
                       cm.description, cm.status, cm.created_at,
                       cm.resolved_at,
                       o.order_id, o.order_number,
                       cc.category_name,
                       st.full_name AS assigned_staff_name
                FROM complaint cm
                JOIN orders o ON o.order_id = cm.order_id
                LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
                LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
                WHERE cm.customer_id = :cid";

        $params = ['cid' => $customerId];

        if (in_array($filter, ['open','assigned','under_investigation','resolved','rejected','escalated','closed'], true)) {
            $sql .= " AND cm.status = :status";
            $params['status'] = $filter;
        } elseif ($filter === 'active') {
            $sql .= " AND cm.status IN ('open','assigned','under_investigation','escalated')";
        }

        $sql .= " ORDER BY cm.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $complaints = $stmt->fetchAll();

        // Stats for header cards
        $stats = $this->getCustomerComplaintStats($customerId);

        $this->view('customer/complaints/index', [
            'complaints' => $complaints,
            'stats'      => $stats,
            'filter'     => $filter,
        ]);
    }

    // =========================================================
    //  CREATE — Show the "raise complaint" form for a given order
    // =========================================================
    public function create(int $orderId): void
    {
        $customerId = (int) $this->currentCustomerId();
        if ($customerId <= 0) {
            $this->redirect('/login');
            return;
        }

        // Load order + verify ownership
        $order = $this->loadCustomerOrder($orderId, $customerId);
        if (!$order) {
            $this->notFound('Order not found or not yours.');
            return;
        }

        // Business rule: complaint only for delivered orders
        if ($order['order_status'] !== 'delivered') {
            $_SESSION['flash_error'] = 'You can only file a complaint for delivered orders.';
            $this->redirect('/customer/orders/' . $orderId);
            return;
        }

        // Business rule: within N days of delivery
        $windowDays = (int) SystemConfig::get('complaint_window_days', self::DEFAULT_WINDOW_DAYS);
        if (!$this->isWithinComplaintWindow($order['delivered_at'] ?? null, $windowDays)) {
            $_SESSION['flash_error'] = "The complaint window ({$windowDays} days) has expired for this order.";
            $this->redirect('/customer/orders/' . $orderId);
            return;
        }

        // Business rule: one complaint per order (any status other than 'rejected'/'closed' counts)
        $stmt = $this->db->prepare(
            "SELECT complaint_id, status FROM complaint
             WHERE order_id = :oid AND customer_id = :cid
               AND status NOT IN ('rejected','closed')
             LIMIT 1"
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $customerId]);
        if ($existing = $stmt->fetch()) {
            $_SESSION['flash_error'] = "You already have an active complaint (#{$existing['complaint_id']}) for this order.";
            $this->redirect('/customer/complaints/' . $existing['complaint_id']);
            return;
        }

        // Load order items (so customer can reference a specific item)
        $stmt = $this->db->prepare(
            "SELECT oi.order_item_id, oi.quantity, oi.unit_price, oi.total_price,
                    it.item_name, it.item_id,
                    s.service_name, s.service_id
             FROM order_item oi
             JOIN item_type it ON it.item_id  = oi.item_id
             JOIN service   s  ON s.service_id = oi.service_id
             WHERE oi.order_id = :oid
             ORDER BY it.item_name"
        );
        $stmt->execute(['oid' => $orderId]);
        $orderItems = $stmt->fetchAll();

        // Load active complaint categories for the dropdown
        $categories = $this->categories->allActive();

        $this->view('customer/complaints/create', [
            'order'      => $order,
            'orderItems' => $orderItems,
            'categories' => $categories,
            'types'      => self::ALLOWED_TYPES,
            'windowDays' => $windowDays,
        ]);
    }

    // =========================================================
    //  STORE — Save the complaint
    // =========================================================
    public function store(): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $customerId = (int) $this->currentCustomerId();
        if ($customerId <= 0) {
            $this->jsonError('Unauthorized. Please log in.', 401);
            return;
        }

        // ---- Collect & validate input ----
        $orderId    = (int)   ($_POST['order_id']    ?? 0);
        $type       = trim(   $_POST['type']         ?? '');
        $categoryId = (int)   ($_POST['category_id'] ?? 0);
        $description= trim(   $_POST['description']  ?? '');

        if ($orderId <= 0) {
            $this->jsonError('Invalid order.', 422);
            return;
        }

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $this->jsonError('Invalid complaint type selected.', 422);
            return;
        }

        if ($categoryId > 0) {
            $cat = $this->categories->find($categoryId);
            if (!$cat || !(int) $cat['is_active']) {
                $this->jsonError('Selected category is invalid or inactive.', 422);
                return;
            }
        } else {
            $categoryId = null;
        }

        if ($description === '' || mb_strlen($description) < 15) {
            $this->jsonError('Description must be at least 15 characters.', 422);
            return;
        }

        if (mb_strlen($description) > 2000) {
            $this->jsonError('Description must not exceed 2000 characters.', 422);
            return;
        }

        // ---- Load & verify order ownership ----
        $order = $this->loadCustomerOrder($orderId, $customerId);
        if (!$order) {
            $this->jsonError('Order not found or does not belong to you.', 404);
            return;
        }

        if ($order['order_status'] !== 'delivered') {
            $this->jsonError('You can only file a complaint for delivered orders.', 422);
            return;
        }

        // ---- Enforce complaint window ----
        $windowDays = (int) SystemConfig::get('complaint_window_days', self::DEFAULT_WINDOW_DAYS);
        if (!$this->isWithinComplaintWindow($order['delivered_at'] ?? null, $windowDays)) {
            $this->jsonError("The complaint window ({$windowDays} days) has expired.", 422);
            return;
        }

        // ---- Prevent duplicate active complaints per order ----
        $stmt = $this->db->prepare(
            "SELECT complaint_id FROM complaint
             WHERE order_id = :oid AND customer_id = :cid
               AND status NOT IN ('rejected','closed')
             LIMIT 1"
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $customerId]);
        if ($existing = $stmt->fetch()) {
            $this->jsonError(
                "You already have an active complaint (#{$existing['complaint_id']}) for this order.",
                409
            );
            return;
        }

        // ---- Generate complaint number ----
        $complaintNumber = $this->generateComplaintNumber();

        // ---- Persist ----
        try {
            $this->complaints->beginTransaction();

            $complaintId = (int) $this->complaints->insert([
                'complaint_number' => $complaintNumber,
                'customer_id'      => $customerId,
                'order_id'         => $orderId,
                'category_id'      => $categoryId,
                'type'             => $type,
                'description'      => $description,
                'status'           => 'open',
            ]);

            $this->complaints->commit();

        } catch (\Throwable $e) {
            $this->complaints->rollBack();
            error_log('[CustomerComplaintController::store] ' . $e->getMessage());
            $this->jsonError('Failed to submit complaint. Please try again.', 500);
            return;
        }

        // ---- Notifications (Shehreen's NotificationService hook) ----
        $this->notifyComplaintCreated($complaintId, $customerId, $orderId, $type);
        $this->notifyAdminNewComplaint($complaintId, $complaintNumber, $type);

        $this->jsonSuccess([
            'message'          => 'Complaint submitted successfully.',
            'complaint_id'     => $complaintId,
            'complaint_number' => $complaintNumber,
            'redirect'         => '/customer/complaints/' . $complaintId,
        ]);
    }

    // =========================================================
    //  SHOW — Complaint detail (customer view only)
    // =========================================================
    public function show(int $id): void
    {
        $customerId = (int) $this->currentCustomerId();
        if ($customerId <= 0) {
            $this->redirect('/login');
            return;
        }

        $complaint = $this->loadCustomerComplaint($id, $customerId);
        if (!$complaint) {
            $this->notFound('Complaint not found.');
            return;
        }

        // Load order details
        $order = $this->orders->find($complaint['order_id']);

        // Load refund (if any)
        $stmt = $this->db->prepare(
            "SELECT refund_id, amount, status, refund_reason, created_at,
                    approved_at, completed_at
             FROM refund
             WHERE complaint_id = :cid
             ORDER BY refund_id DESC
             LIMIT 1"
        );
        $stmt->execute(['cid' => $id]);
        $refund = $stmt->fetch() ?: null;

        // Build a timeline view from status history (using updated_at + resolved_at)
        $timeline = $this->buildTimeline($complaint);

        $this->view('customer/complaints/show', [
            'complaint' => $complaint,
            'order'     => $order,
            'refund'    => $refund,
            'timeline'  => $timeline,
        ]);
    }

    // =========================================================
    //  CANCEL — Customer can cancel their own complaint while 'open'
    // =========================================================
    public function cancel(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $customerId = (int) $this->currentCustomerId();
        if ($customerId <= 0) {
            $this->jsonError('Unauthorized.', 401);
            return;
        }

        $complaint = $this->loadCustomerComplaint($id, $customerId);
        if (!$complaint) {
            $this->jsonError('Complaint not found.', 404);
            return;
        }

        if (!in_array($complaint['status'], self::CANCELLABLE_STATUSES, true)) {
            $this->jsonError(
                "Complaint cannot be cancelled once it is '{$complaint['status']}'.",
                422
            );
            return;
        }

        $reason = trim($_POST['reason'] ?? '');

        try {
            $staffNote = $reason !== ''
                ? sprintf(
                    "\n[%s] CANCELLED by customer: %s",
                    date('Y-m-d H:i:s'),
                    $reason
                )
                : sprintf(
                    "\n[%s] CANCELLED by customer.",
                    date('Y-m-d H:i:s')
                );

            $existing = $complaint['resolution_notes'] ?? '';

            $this->complaints->update($id, [
                'status'           => 'closed',
                'resolution_notes' => $existing . $staffNote,
                'resolved_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->notifyComplaintCancelled($id, $customerId);

            $this->jsonSuccess([
                'message'      => 'Complaint cancelled.',
                'complaint_id' => $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[CustomerComplaintController::cancel] ' . $e->getMessage());
            $this->jsonError('Failed to cancel complaint.', 500);
        }
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    /**
     * Load an order and verify it belongs to the given customer.
     * Returns false if not found or unauthorized.
     */
    private function loadCustomerOrder(int $orderId, int $customerId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT order_id, order_number, order_status, order_date,
                    delivered_at, delivery_date, estimated_delivery_date,
                    special_instructions, customer_id
             FROM orders
             WHERE order_id = :oid AND customer_id = :cid
             LIMIT 1"
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $customerId]);
        return $stmt->fetch();
    }

    /**
     * Load a complaint and verify it belongs to the given customer.
     * Adds joined fields needed by the detail view.
     */
    private function loadCustomerComplaint(int $complaintId, int $customerId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT cm.*,
                    o.order_number, o.order_status, o.delivered_at,
                    cc.category_name,
                    st.full_name AS assigned_staff_name
             FROM complaint cm
             JOIN orders o ON o.order_id = cm.order_id
             LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
             LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
             WHERE cm.complaint_id = :id AND cm.customer_id = :cid
             LIMIT 1"
        );
        $stmt->execute(['id' => $complaintId, 'cid' => $customerId]);
        return $stmt->fetch();
    }

    /**
     * Complaint window check based on order.delivered_at.
     * If delivered_at is NULL, we fall back to order_date (edge case).
     */
    private function isWithinComplaintWindow(?string $deliveredAt, int $windowDays): bool
    {
        if ($windowDays <= 0) {
            return true; // window disabled
        }

        if (empty($deliveredAt)) {
            return false;
        }

        $deliveredTs = strtotime($deliveredAt);
        if ($deliveredTs === false) {
            return false;
        }

        $expiresAt = $deliveredTs + ($windowDays * 86400);
        return time() <= $expiresAt;
    }

    /**
     * Generate a unique complaint number like CMP-20250115-00042.
     */
    private function generateComplaintNumber(): string
    {
        $date   = date('Ymd');
        $prefix = 'CMP-' . $date . '-';

        // Find the highest sequence for today's prefix
        $stmt = $this->db->prepare(
            "SELECT complaint_number FROM complaint
             WHERE complaint_number LIKE :prefix
             ORDER BY complaint_id DESC
             LIMIT 1"
        );
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq = ((int) end($parts)) + 1;
        }

        $candidate = $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

        // Defensive: retry a few times if collision (shouldn't happen on unique key)
        for ($i = 0; $i < 3; $i++) {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM complaint WHERE complaint_number = :n LIMIT 1"
            );
            $stmt->execute(['n' => $candidate]);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
            $seq++;
            $candidate = $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        }

        // Fallback with random suffix
        return $prefix . str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Simple timeline built from complaint fields.
     * We don't have a complaint_status_history table, so we derive
     * milestones from status + timestamps.
     */
    private function buildTimeline(array $complaint): array
    {
        $events = [];

        $events[] = [
            'label' => 'Complaint Filed',
            'at'    => $complaint['created_at'],
            'done'  => true,
        ];

        if (!empty($complaint['assigned_staff_name'])) {
            $events[] = [
                'label' => 'Assigned to ' . $complaint['assigned_staff_name'],
                'at'    => null, // not tracked per-event in schema
                'done'  => true,
            ];
        }

        if (in_array($complaint['status'], ['under_investigation','resolved','rejected','escalated','closed'], true)) {
            $events[] = [
                'label' => 'Investigation',
                'at'    => null,
                'done'  => true,
            ];
        }

        if ($complaint['status'] === 'escalated') {
            $events[] = [
                'label' => 'Escalated to Admin',
                'at'    => null,
                'done'  => true,
            ];
        }

        if (in_array($complaint['status'], ['resolved','rejected','closed'], true) && $complaint['resolved_at']) {
            $label = match ($complaint['status']) {
                'resolved' => 'Resolved',
                'rejected' => 'Rejected',
                'closed'   => 'Closed',
                default    => 'Completed',
            };
            $events[] = [
                'label' => $label,
                'at'    => $complaint['resolved_at'],
                'done'  => true,
            ];
        }

        return $events;
    }

    /**
     * Aggregate complaint stats for the customer dashboard header.
     */
    private function getCustomerComplaintStats(int $customerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN status IN ('open','assigned','under_investigation','escalated') THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                COUNT(*) AS total
             FROM complaint
             WHERE customer_id = :cid"
        );
        $stmt->execute(['cid' => $customerId]);
        $row = $stmt->fetch() ?: [];

        return [
            'active'   => (int) ($row['active']   ?? 0),
            'resolved' => (int) ($row['resolved'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'total'    => (int) ($row['total']    ?? 0),
        ];
    }

    // =========================================================
    //  NOTIFICATION HOOKS (Shehreen's NotificationService)
    // =========================================================

    private function notifyComplaintCreated(
        int $complaintId,
        int $customerId,
        int $orderId,
        string $type
    ): void {
        // TODO: (new \App\Services\NotificationService())->complaintCreated($complaintId, $customerId, $orderId, $type);
        error_log(sprintf(
            '[ComplaintCreated] complaint_id=%d customer_id=%d order_id=%d type=%s',
            $complaintId, $customerId, $orderId, $type
        ));
    }

    private function notifyAdminNewComplaint(
        int $complaintId,
        string $complaintNumber,
        string $type
    ): void {
        // TODO: (new \App\Services\NotificationService())->adminNewComplaint($complaintId, $complaintNumber, $type);
        error_log(sprintf(
            '[AdminNewComplaint] complaint_id=%d number=%s type=%s',
            $complaintId, $complaintNumber, $type
        ));
    }

    private function notifyComplaintCancelled(int $complaintId, int $customerId): void
    {
        // TODO: (new \App\Services\NotificationService())->complaintCancelled($complaintId, $customerId);
        error_log(sprintf(
            '[ComplaintCancelled] complaint_id=%d customer_id=%d',
            $complaintId, $customerId
        ));
    }

    // =========================================================
    //  AUTH / SESSION HELPERS
    // =========================================================

    private function currentCustomerId(): int|string
    {
        if (isset($_SESSION['customer_id'])) {
            return $_SESSION['customer_id'];
        }
        // Development fallback — DO NOT use in production
        return 0;
    }
}
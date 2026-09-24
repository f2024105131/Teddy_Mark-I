<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin\ComplaintController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Admin back-office for complaint + refund management.
 *            • List all complaints with rich filters
 *            • View complaint detail with full context
 *            • Assign / reassign to staff
 *            • Resolve / reject / close (admin override)
 *            • Escalation queue (staff escalated to admin)
 *            • Refund queue: approve / reject / mark complete
 *            • Complaint category CRUD
 *            • Summary stats + CSV export
 *
 * Notes:
 *   • Uses raw SQL — no dependency on models.
 *   • Every state change is audited.
 *   • Refunds are tightly coupled with complaints, so they
 *     live here rather than a separate RefundController.
 * ----------------------------------------------------------
 */
class ComplaintController extends Controller
{
    private const PER_PAGE = 20;

    /** Valid complaint statuses from schema */
    private const STATUSES = [
        'open',
        'assigned',
        'under_investigation',
        'resolved',
        'rejected',
        'escalated',
        'closed',
    ];

    /** Valid complaint types from schema */
    private const TYPES = [
        'missing_item',
        'damaged_item',
        'late_delivery',
        'wrong_billing',
        'poor_cleaning_quality',
    ];

    /** Valid refund statuses from schema */
    private const REFUND_STATUSES = ['pending', 'approved', 'rejected', 'completed'];

    /** Statuses that count as "active" */
    private const ACTIVE_STATUSES = ['open', 'assigned', 'under_investigation', 'escalated'];

    /** Human-readable type labels */
    private const TYPE_LABELS = [
        'missing_item'          => 'Missing Item',
        'damaged_item'          => 'Damaged Item',
        'late_delivery'         => 'Late Delivery',
        'wrong_billing'         => 'Wrong Billing',
        'poor_cleaning_quality' => 'Poor Cleaning Quality',
    ];

    // =========================================================
    //  INDEX — All complaints with filters
    // =========================================================
    public function index(): void
    {
        $this->requireAdmin();

        $search     = trim($_GET['search']      ?? '');
        $status     = $_GET['status']           ?? '';
        $type       = $_GET['type']             ?? '';
        $categoryId = (int) ($_GET['category']  ?? 0);
        $staffId    = (int) ($_GET['staff']     ?? 0);
        $from       = $_GET['from']             ?? '';
        $to         = $_GET['to']               ?? '';
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $perPage    = self::PER_PAGE;
        $offset     = ($page - 1) * $perPage;

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "(cm.complaint_number LIKE :search
                                OR o.order_number LIKE :search
                                OR c.full_name LIKE :search
                                OR c.email LIKE :search
                                OR c.phone LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[]          = "cm.status = :status";
            $params['status'] = $status;
        }

        if ($type !== '' && in_array($type, self::TYPES, true)) {
            $where[]        = "cm.type = :type";
            $params['type'] = $type;
        }

        if ($categoryId > 0) {
            $where[]         = "cm.category_id = :cid";
            $params['cid']   = $categoryId;
        }

        if ($staffId > 0) {
            $where[]         = "cm.assigned_staff_id = :sid";
            $params['sid']   = $staffId;
        }

        if ($from !== '' && $this->isValidDate($from)) {
            $where[]         = "cm.created_at >= :from";
            $params['from']  = $from . ' 00:00:00';
        }

        if ($to !== '' && $this->isValidDate($to)) {
            $where[]       = "cm.created_at <= :to";
            $params['to']  = $to . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        // Total count
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM complaint cm
             JOIN customer c ON c.customer_id = cm.customer_id
             JOIN orders   o ON o.order_id    = cm.order_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $totalRows  = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // Rows
        $sql = "SELECT cm.complaint_id, cm.complaint_number, cm.type,
                       cm.status, cm.created_at, cm.resolved_at,
                       c.customer_id, c.full_name AS customer_name,
                       c.email AS customer_email, c.phone AS customer_phone,
                       o.order_id, o.order_number, o.order_status,
                       cc.category_name,
                       st.staff_id AS assigned_staff_id,
                       st.full_name AS assigned_staff_name,
                       (SELECT COUNT(*) FROM refund r
                          WHERE r.complaint_id = cm.complaint_id) AS refund_count
                FROM complaint cm
                JOIN customer c ON c.customer_id = cm.customer_id
                JOIN orders   o ON o.order_id    = cm.order_id
                LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
                LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
                WHERE {$whereSql}
                ORDER BY
                    FIELD(cm.status,
                          'escalated',
                          'open',
                          'assigned',
                          'under_investigation',
                          'resolved',
                          'rejected',
                          'closed'),
                    cm.created_at DESC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $complaints = $stmt->fetchAll();

        $summary    = $this->summaryStats();
        $categories = $this->allCategories(true);
        $staffList  = $this->activeStaffList();

        $this->view('admin/complaints/index', [
            'complaints'  => $complaints,
            'summary'     => $summary,
            'categories'  => $categories,
            'staffList'   => $staffList,
            'types'       => self::TYPES,
            'typeLabels'  => self::TYPE_LABELS,
            'search'      => $search,
            'status'      => $status,
            'type'        => $type,
            'categoryId'  => $categoryId,
            'staffId'     => $staffId,
            'from'        => $from,
            'to'          => $to,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalRows'   => $totalRows,
            'totalPages'  => $totalPages,
        ]);
    }

    // =========================================================
    //  SHOW — Complaint detail with everything
    // =========================================================
    public function show(int $id): void
    {
        $this->requireAdmin();

        $complaint = $this->findDetailed($id);
        if (!$complaint) {
            $this->notFound('Complaint not found.');
            return;
        }

        // Order context
        $stmt = $this->db->prepare(
            "SELECT o.*, b.bill_number, b.total_amount, b.paid_amount,
                    b.outstanding_amount, b.payment_status
             FROM orders o
             LEFT JOIN bill b ON b.order_id = o.order_id
             WHERE o.order_id = :oid LIMIT 1"
        );
        $stmt->execute(['oid' => $complaint['order_id']]);
        $order = $stmt->fetch();

        // Order items
        $stmt = $this->db->prepare(
            "SELECT oi.order_item_id, oi.quantity, oi.unit_price, oi.total_price,
                    it.item_name, s.service_name
             FROM order_item oi
             JOIN item_type it ON it.item_id  = oi.item_id
             JOIN service   s  ON s.service_id = oi.service_id
             WHERE oi.order_id = :oid
             ORDER BY it.item_name"
        );
        $stmt->execute(['oid' => $complaint['order_id']]);
        $orderItems = $stmt->fetchAll();

        // Payments for this order
        $stmt = $this->db->prepare(
            "SELECT payment_id, amount, payment_method, status,
                    transaction_id, created_at, approved_at
             FROM payment
             WHERE order_id = :oid
             ORDER BY payment_id DESC"
        );
        $stmt->execute(['oid' => $complaint['order_id']]);
        $payments = $stmt->fetchAll();

        // Refunds for this complaint
        $stmt = $this->db->prepare(
            "SELECT r.*, s.full_name AS approved_by_name
             FROM refund r
             LEFT JOIN staff s ON s.staff_id = r.approved_by_staff_id
             WHERE r.complaint_id = :cid
             ORDER BY r.refund_id DESC"
        );
        $stmt->execute(['cid' => $id]);
        $refunds = $stmt->fetchAll();

        // Staff list for assignment dropdown
        $staffList = $this->activeStaffList();

        $this->view('admin/complaints/show', [
            'complaint'  => $complaint,
            'order'      => $order,
            'orderItems' => $orderItems,
            'payments'   => $payments,
            'refunds'    => $refunds,
            'staffList'  => $staffList,
            'typeLabel'  => self::TYPE_LABELS[$complaint['type']] ?? $complaint['type'],
        ]);
    }

    // =========================================================
    //  ASSIGN — Assign / reassign complaint to staff
    // =========================================================
    public function assign(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $staffId = (int) ($_POST['staff_id'] ?? 0);
        if ($staffId <= 0) {
            $this->jsonError('Please select a staff member.', 422);
            return;
        }

        $complaint = $this->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found.', 404);
            return;
        }

        if (in_array($complaint['status'], ['resolved', 'rejected', 'closed'], true)) {
            $this->jsonError("Cannot assign a {$complaint['status']} complaint.", 422);
            return;
        }

        // Verify target staff
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
            $this->db->prepare(
                "UPDATE complaint
                 SET assigned_staff_id = :sid,
                     status = CASE WHEN status IN ('open','escalated') THEN 'assigned' ELSE status END
                 WHERE complaint_id = :id"
            )->execute(['sid' => $staffId, 'id' => $id]);

            $this->audit('complaint', 'assign', $id,
                ['assigned_staff_id' => $complaint['assigned_staff_id'], 'status' => $complaint['status']],
                ['assigned_staff_id' => $staffId, 'status' => 'assigned']
            );

            $this->notifyComplaint($id, 'assigned', ['staff_id' => $staffId]);

            $this->jsonSuccess([
                'message'    => "Complaint assigned to {$staff['full_name']}.",
                'complaint_id' => $id,
                'staff_id'   => $staffId,
                'staff_name' => $staff['full_name'],
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminComplaintController::assign] ' . $e->getMessage());
            $this->jsonError('Failed to assign complaint.', 500);
        }
    }

    // =========================================================
    //  RESOLVE — Admin override
    // =========================================================
    public function resolve(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $notes = trim($_POST['resolution_notes'] ?? '');

        if ($notes === '' || mb_strlen($notes) < 10) {
            $this->jsonError('Resolution notes must be at least 10 characters.', 422);
            return;
        }

        if (mb_strlen($notes) > 2000) {
            $this->jsonError('Resolution notes must not exceed 2000 characters.', 422);
            return;
        }

        $complaint = $this->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found.', 404);
            return;
        }

        if (in_array($complaint['status'], ['resolved', 'rejected', 'closed'], true)) {
            $this->jsonError("Complaint is already {$complaint['status']}.", 422);
            return;
        }

        try {
            $now = date('Y-m-d H:i:s');

            $this->db->prepare(
                "UPDATE complaint
                 SET status = 'resolved',
                     resolution_notes = :notes,
                     resolved_at = :ts
                 WHERE complaint_id = :id"
            )->execute(['notes' => $notes, 'ts' => $now, 'id' => $id]);

            $this->audit('complaint', 'resolve', $id,
                ['status' => $complaint['status']],
                ['status' => 'resolved', 'notes' => $notes]
            );

            $this->notifyComplaint($id, 'resolved', ['notes' => $notes]);

            $this->jsonSuccess([
                'message'      => 'Complaint resolved.',
                'complaint_id' => $id,
                'resolved_at'  => $now,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminComplaintController::resolve] ' . $e->getMessage());
            $this->jsonError('Failed to resolve complaint.', 500);
        }
    }

    // =========================================================
    //  REJECT
    // =========================================================
    public function reject(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $reason = trim($_POST['reason'] ?? '');

        if ($reason === '' || mb_strlen($reason) < 10) {
            $this->jsonError('Rejection reason must be at least 10 characters.', 422);
            return;
        }

        if (mb_strlen($reason) > 2000) {
            $this->jsonError('Rejection reason must not exceed 2000 characters.', 422);
            return;
        }

        $complaint = $this->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found.', 404);
            return;
        }

        if (in_array($complaint['status'], ['resolved', 'rejected', 'closed'], true)) {
            $this->jsonError("Complaint is already {$complaint['status']}.", 422);
            return;
        }

        try {
            $now = date('Y-m-d H:i:s');

            $this->db->prepare(
                "UPDATE complaint
                 SET status = 'rejected',
                     resolution_notes = :notes,
                     resolved_at = :ts
                 WHERE complaint_id = :id"
            )->execute(['notes' => $reason, 'ts' => $now, 'id' => $id]);

            $this->audit('complaint', 'reject', $id,
                ['status' => $complaint['status']],
                ['status' => 'rejected', 'reason' => $reason]
            );

            $this->notifyComplaint($id, 'rejected', ['reason' => $reason]);

            $this->jsonSuccess([
                'message'      => 'Complaint rejected.',
                'complaint_id' => $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminComplaintController::reject] ' . $e->getMessage());
            $this->jsonError('Failed to reject complaint.', 500);
        }
    }

    // =========================================================
    //  CLOSE — Final closing after resolution/rejection
    // =========================================================
    public function close(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $complaint = $this->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found.', 404);
            return;
        }

        if (!in_array($complaint['status'], ['resolved', 'rejected', 'escalated'], true)) {
            $this->jsonError(
                "Complaint must be resolved, rejected, or escalated before closing (current: {$complaint['status']}).",
                422
            );
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE complaint SET status = 'closed' WHERE complaint_id = :id"
            )->execute(['id' => $id]);

            $this->audit('complaint', 'close', $id,
                ['status' => $complaint['status']],
                ['status' => 'closed']
            );

            $this->notifyComplaint($id, 'closed');

            $this->jsonSuccess([
                'message'      => 'Complaint closed.',
                'complaint_id' => $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminComplaintController::close] ' . $e->getMessage());
            $this->jsonError('Failed to close complaint.', 500);
        }
    }

    // =========================================================
    //  ESCALATIONS — Queue of escalated complaints
    // =========================================================
    public function escalations(): void
    {
        $this->requireAdmin();

        $stmt = $this->db->query(
            "SELECT cm.complaint_id, cm.complaint_number, cm.type,
                    cm.status, cm.created_at, cm.resolution_notes,
                    c.customer_id, c.full_name AS customer_name,
                    c.phone AS customer_phone,
                    o.order_number,
                    cc.category_name,
                    st.full_name AS assigned_staff_name
             FROM complaint cm
             JOIN customer c ON c.customer_id = cm.customer_id
             JOIN orders   o ON o.order_id    = cm.order_id
             LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
             LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
             WHERE cm.status = 'escalated'
             ORDER BY cm.created_at ASC"
        );
        $escalations = $stmt->fetchAll();

        $this->view('admin/complaints/escalations', [
            'escalations' => $escalations,
            'typeLabels'  => self::TYPE_LABELS,
        ]);
    }

    // =========================================================
    //  REFUNDS — Queue + processing
    // =========================================================
    public function refunds(): void
    {
        $this->requireAdmin();

        $status  = $_GET['status'] ?? 'pending';
        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = self::PER_PAGE;
        $offset  = ($page - 1) * $perPage;

        $where  = ['1 = 1'];
        $params = [];

        if (in_array($status, self::REFUND_STATUSES, true)) {
            $where[]          = "r.status = :status";
            $params['status'] = $status;
        }

        if ($search !== '') {
            $where[]          = "(cm.complaint_number LIKE :search
                                OR o.order_number LIKE :search
                                OR c.full_name LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        // Total
        $countStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM refund r
             JOIN complaint cm ON cm.complaint_id = r.complaint_id
             JOIN orders    o  ON o.order_id      = r.order_id
             JOIN customer  c  ON c.customer_id   = cm.customer_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $totalRows  = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // Rows
        $sql = "SELECT r.*,
                       cm.complaint_number, cm.type AS complaint_type,
                       c.full_name AS customer_name, c.phone AS customer_phone,
                       o.order_number,
                       p.amount AS payment_amount, p.payment_method,
                       p.status AS payment_status,
                       s1.full_name AS approved_by_name
                FROM refund r
                JOIN complaint cm ON cm.complaint_id = r.complaint_id
                JOIN orders    o  ON o.order_id      = r.order_id
                JOIN customer  c  ON c.customer_id   = cm.customer_id
                JOIN payment   p  ON p.payment_id    = r.payment_id
                LEFT JOIN staff s1 ON s1.staff_id    = r.approved_by_staff_id
                WHERE {$whereSql}
                ORDER BY
                    FIELD(r.status, 'pending', 'approved', 'completed', 'rejected'),
                    r.created_at DESC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $refunds = $stmt->fetchAll();

        $refundSummary = $this->refundSummary();

        $this->view('admin/complaints/refunds', [
            'refunds'       => $refunds,
            'refundSummary' => $refundSummary,
            'status'        => $status,
            'search'        => $search,
            'page'          => $page,
            'perPage'       => $perPage,
            'totalRows'     => $totalRows,
            'totalPages'    => $totalPages,
            'typeLabels'    => self::TYPE_LABELS,
        ]);
    }

    /**
     * Approve a pending refund.
     */
    public function approveRefund(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $refund = $this->findRefund($id);
        if (!$refund) {
            $this->jsonError('Refund not found.', 404);
            return;
        }

        if ($refund['status'] !== 'pending') {
            $this->jsonError("Refund is already {$refund['status']}.", 422);
            return;
        }

        $staffId = (int) $this->currentStaffId();

        try {
            $this->db->beginTransaction();

            $this->db->prepare(
                "UPDATE refund
                 SET status = 'approved',
                     approved_by_staff_id = :sid,
                     approved_at = NOW()
                 WHERE refund_id = :id"
            )->execute(['sid' => $staffId, 'id' => $id]);

            // Update payment status to 'refunded'
            $this->db->prepare(
                "UPDATE payment SET status = 'refunded'
                 WHERE payment_id = :pid"
            )->execute(['pid' => $refund['payment_id']]);

            // Update bill payment_status
            $this->db->prepare(
                "UPDATE bill
                 SET payment_status = 'refunded'
                 WHERE order_id = :oid"
            )->execute(['oid' => $refund['order_id']]);

            $this->db->commit();

            $this->audit('complaint', 'refund_approve', $id,
                ['status' => 'pending'],
                ['status' => 'approved', 'amount' => $refund['amount']]
            );

            $this->notifyRefund($id, 'approved');

            $this->jsonSuccess([
                'message'   => 'Refund approved.',
                'refund_id' => $id,
                'status'    => 'approved',
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[AdminComplaintController::approveRefund] ' . $e->getMessage());
            $this->jsonError('Failed to approve refund.', 500);
        }
    }

    /**
     * Reject a pending refund.
     */
    public function rejectRefund(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $reason = trim($_POST['reason'] ?? '');

        if ($reason === '' || mb_strlen($reason) < 10) {
            $this->jsonError('Rejection reason must be at least 10 characters.', 422);
            return;
        }

        if (mb_strlen($reason) > 2000) {
            $this->jsonError('Rejection reason must not exceed 2000 characters.', 422);
            return;
        }

        $refund = $this->findRefund($id);
        if (!$refund) {
            $this->jsonError('Refund not found.', 404);
            return;
        }

        if ($refund['status'] !== 'pending') {
            $this->jsonError("Refund is already {$refund['status']}.", 422);
            return;
        }

        try {
            // Append rejection reason to refund_reason field
            $combinedReason = $refund['refund_reason']
                . "\n\n[REJECTED " . date('Y-m-d H:i:s') . "] " . $reason;

            $this->db->prepare(
                "UPDATE refund
                 SET status = 'rejected',
                     refund_reason = :reason
                 WHERE refund_id = :id"
            )->execute(['reason' => $combinedReason, 'id' => $id]);

            $this->audit('complaint', 'refund_reject', $id,
                ['status' => 'pending'],
                ['status' => 'rejected', 'reason' => $reason]
            );

            $this->notifyRefund($id, 'rejected', ['reason' => $reason]);

            $this->jsonSuccess([
                'message'   => 'Refund rejected.',
                'refund_id' => $id,
                'status'    => 'rejected',
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminComplaintController::rejectRefund] ' . $e->getMessage());
            $this->jsonError('Failed to reject refund.', 500);
        }
    }

    /**
     * Mark approved refund as completed (money actually sent).
     */
    public function completeRefund(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $refund = $this->findRefund($id);
        if (!$refund) {
            $this->jsonError('Refund not found.', 404);
            return;
        }

        if ($refund['status'] !== 'approved') {
            $this->jsonError("Only approved refunds can be completed (current: {$refund['status']}).", 422);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE refund
                 SET status = 'completed',
                     completed_at = NOW()
                 WHERE refund_id = :id"
            )->execute(['id' => $id]);

            $this->audit('complaint', 'refund_complete', $id,
                ['status' => 'approved'],
                ['status' => 'completed']
            );

            $this->notifyRefund($id, 'completed');

            $this->jsonSuccess([
                'message'   => 'Refund completed.',
                'refund_id' => $id,
                'status'    => 'completed',
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminComplaintController::completeRefund] ' . $e->getMessage());
            $this->jsonError('Failed to complete refund.', 500);
        }
    }

    // =========================================================
    //  CATEGORY CRUD
    // =========================================================
    public function categories(): void
    {
        $this->requireAdmin();

        $stmt = $this->db->query(
            "SELECT cc.*,
                    (SELECT COUNT(*) FROM complaint cm
                     WHERE cm.category_id = cc.category_id) AS complaints_count,
                    (SELECT COUNT(*) FROM complaint cm
                     WHERE cm.category_id = cc.category_id
                       AND cm.status IN ('open','assigned','under_investigation','escalated')) AS active_count
             FROM complaint_category cc
             ORDER BY cc.is_active DESC, cc.category_name ASC"
        );
        $categories = $stmt->fetchAll();

        $this->view('admin/complaints/categories', ['categories' => $categories]);
    }

    public function storeCategory(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $name        = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description']   ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 1;

        $errors = $this->validateCategory($name, $description);

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "INSERT INTO complaint_category (category_name, description, is_active)
                 VALUES (:name, :desc, :active)"
            )->execute([
                'name'   => $name,
                'desc'   => $description ?: null,
                'active' => $isActive,
            ]);

            $catId = (int) $this->db->lastInsertId();

            $this->audit('complaint', 'category_create', $catId, null, [
                'category_name' => $name,
            ]);

            $this->jsonSuccess([
                'message'     => 'Category created.',
                'category_id' => $catId,
                'redirect'    => '/admin/complaints/categories',
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('A category with this name already exists.', 409);
                return;
            }
            error_log('[AdminComplaintController::storeCategory] ' . $e->getMessage());
            $this->jsonError('Failed to create category.', 500);
        }
    }

    public function updateCategory(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $category = $this->findCategory($id);
        if (!$category) {
            $this->jsonError('Category not found.', 404);
            return;
        }

        $name        = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description']   ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        $errors = $this->validateCategory($name, $description, $id);

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE complaint_category
                 SET category_name = :name,
                     description   = :desc,
                     is_active     = :active
                 WHERE category_id = :id"
            )->execute([
                'name'   => $name,
                'desc'   => $description ?: null,
                'active' => $isActive,
                'id'     => $id,
            ]);

            $this->audit('complaint', 'category_update', $id,
                ['category_name' => $category['category_name']],
                ['category_name' => $name]
            );

            $this->jsonSuccess([
                'message'  => 'Category updated.',
                'redirect' => '/admin/complaints/categories',
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('A category with this name already exists.', 409);
                return;
            }
            error_log('[AdminComplaintController::updateCategory] ' . $e->getMessage());
            $this->jsonError('Failed to update category.', 500);
        }
    }

    public function toggleCategory(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $category = $this->findCategory($id);
        if (!$category) {
            $this->jsonError('Category not found.', 404);
            return;
        }

        $newState = !((bool) $category['is_active']);

        try {
            $this->db->prepare(
                "UPDATE complaint_category SET is_active = :a WHERE category_id = :id"
            )->execute(['a' => $newState ? 1 : 0, 'id' => $id]);

            $this->audit('complaint', 'category_toggle', $id,
                ['is_active' => (int) $category['is_active']],
                ['is_active' => $newState ? 1 : 0]
            );

            $this->jsonSuccess([
                'message'   => 'Category ' . ($newState ? 'activated' : 'deactivated') . '.',
                'is_active' => $newState,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminComplaintController::toggleCategory] ' . $e->getMessage());
            $this->jsonError('Failed to update category.', 500);
        }
    }

    public function deleteCategory(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $category = $this->findCategory($id);
        if (!$category) {
            $this->jsonError('Category not found.', 404);
            return;
        }

        // Guard: cannot delete if complaints reference it
        $usage = (int) $this->db->query(
            "SELECT COUNT(*) FROM complaint WHERE category_id = " . (int) $id
        )->fetchColumn();

        if ($usage > 0) {
            $this->jsonError(
                "Cannot delete: {$usage} complaint(s) use this category. Deactivate it instead.",
                422
            );
            return;
        }

        try {
            $this->db->prepare(
                "DELETE FROM complaint_category WHERE category_id = :id"
            )->execute(['id' => $id]);

            $this->audit('complaint', 'category_delete', $id,
                ['category_name' => $category['category_name']], null);

            $this->jsonSuccess(['message' => 'Category deleted.']);

        } catch (\Throwable $e) {
            error_log('[AdminComplaintController::deleteCategory] ' . $e->getMessage());
            $this->jsonError('Failed to delete category.', 500);
        }
    }

    // =========================================================
    //  EXPORT CSV
    // =========================================================
    public function export(): void
    {
        $this->requireAdmin();

        $status     = $_GET['status']      ?? '';
        $type       = $_GET['type']        ?? '';
        $categoryId = (int) ($_GET['category'] ?? 0);
        $staffId    = (int) ($_GET['staff']    ?? 0);
        $from       = $_GET['from']        ?? '';
        $to         = $_GET['to']          ?? '';

        $where  = ['1 = 1'];
        $params = [];

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[]          = "cm.status = :status";
            $params['status'] = $status;
        }
        if ($type !== '' && in_array($type, self::TYPES, true)) {
            $where[]        = "cm.type = :type";
            $params['type'] = $type;
        }
        if ($categoryId > 0) {
            $where[]       = "cm.category_id = :cid";
            $params['cid'] = $categoryId;
        }
        if ($staffId > 0) {
            $where[]       = "cm.assigned_staff_id = :sid";
            $params['sid'] = $staffId;
        }
        if ($from !== '' && $this->isValidDate($from)) {
            $where[]        = "cm.created_at >= :from";
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to !== '' && $this->isValidDate($to)) {
            $where[]      = "cm.created_at <= :to";
            $params['to'] = $to . ' 23:59:59';
        }

        $sql = "SELECT cm.complaint_number, cm.type, cm.status, cm.description,
                       cm.resolution_notes, cm.created_at, cm.resolved_at,
                       c.full_name AS customer_name, c.email AS customer_email,
                       c.phone AS customer_phone,
                       o.order_number,
                       cc.category_name,
                       st.full_name AS assigned_staff_name,
                       (SELECT COALESCE(SUM(r.amount), 0) FROM refund r
                          WHERE r.complaint_id = cm.complaint_id
                            AND r.status IN ('approved','completed')) AS refunded_amount
                FROM complaint cm
                JOIN customer c ON c.customer_id = cm.customer_id
                JOIN orders   o ON o.order_id    = cm.order_id
                LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
                LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY cm.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $filename = 'complaints_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, [
            'Complaint #', 'Type', 'Status', 'Description',
            'Customer', 'Email', 'Phone', 'Order #', 'Category',
            'Assigned To', 'Refunded', 'Created', 'Resolved',
            'Resolution Notes',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['complaint_number'],
                self::TYPE_LABELS[$r['type']] ?? $r['type'],
                $r['status'],
                $r['description'],
                $r['customer_name'],
                $r['customer_email'],
                $r['customer_phone'],
                $r['order_number'],
                $r['category_name'] ?? '',
                $r['assigned_staff_name'] ?? '',
                number_format((float) $r['refunded_amount'], 2),
                $r['created_at'],
                $r['resolved_at'] ?? '',
                $r['resolution_notes'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================================================
    //  SUMMARY STATS
    // =========================================================
    private function summaryStats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'open'                THEN 1 ELSE 0 END) AS open_total,
                SUM(CASE WHEN status = 'assigned'            THEN 1 ELSE 0 END) AS assigned_total,
                SUM(CASE WHEN status = 'under_investigation' THEN 1 ELSE 0 END) AS investigating_total,
                SUM(CASE WHEN status = 'escalated'           THEN 1 ELSE 0 END) AS escalated_total,
                SUM(CASE WHEN status = 'resolved'            THEN 1 ELSE 0 END) AS resolved_total,
                SUM(CASE WHEN status = 'rejected'            THEN 1 ELSE 0 END) AS rejected_total,
                SUM(CASE WHEN status = 'closed'              THEN 1 ELSE 0 END) AS closed_total,
                SUM(CASE WHEN DATE(created_at) = CURDATE()   THEN 1 ELSE 0 END) AS created_today,
                SUM(CASE WHEN DATE(resolved_at) = CURDATE()  THEN 1 ELSE 0 END) AS resolved_today
             FROM complaint"
        )->fetch() ?: [];

        return [
            'total'             => (int) ($row['total']             ?? 0),
            'open_total'        => (int) ($row['open_total']        ?? 0),
            'assigned_total'    => (int) ($row['assigned_total']    ?? 0),
            'investigating'     => (int) ($row['investigating_total'] ?? 0),
            'escalated_total'   => (int) ($row['escalated_total']   ?? 0),
            'resolved_total'    => (int) ($row['resolved_total']    ?? 0),
            'rejected_total'    => (int) ($row['rejected_total']    ?? 0),
            'closed_total'      => (int) ($row['closed_total']      ?? 0),
            'created_today'     => (int) ($row['created_today']     ?? 0),
            'resolved_today'    => (int) ($row['resolved_today']    ?? 0),
        ];
    }

    private function refundSummary(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS pending_total,
                SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END) AS approved_total,
                SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END) AS rejected_total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_total,
                COALESCE(SUM(CASE WHEN status IN ('approved','completed') THEN amount END), 0) AS approved_amount,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) AS pending_amount
             FROM refund"
        )->fetch() ?: [];

        return [
            'total'           => (int)   ($row['total']           ?? 0),
            'pending_total'   => (int)   ($row['pending_total']   ?? 0),
            'approved_total'  => (int)   ($row['approved_total']  ?? 0),
            'rejected_total'  => (int)   ($row['rejected_total']  ?? 0),
            'completed_total' => (int)   ($row['completed_total'] ?? 0),
            'approved_amount' => (float) ($row['approved_amount'] ?? 0),
            'pending_amount'  => (float) ($row['pending_amount']  ?? 0),
        ];
    }

    // =========================================================
    //  VALIDATION
    // =========================================================
    private function validateCategory(string $name, string $description, ?int $excludeId = null): array
    {
        $errors = [];

        if ($name === '') {
            $errors['category_name'] = 'Category name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['category_name'] = 'Category name must be at least 2 characters.';
        } elseif (mb_strlen($name) > 50) {
            $errors['category_name'] = 'Category name must not exceed 50 characters.';
        } else {
            $sql = "SELECT 1 FROM complaint_category
                    WHERE LOWER(category_name) = LOWER(:n)";
            $params = ['n' => $name];
            if ($excludeId !== null) {
                $sql .= " AND category_id <> :id";
                $params['id'] = $excludeId;
            }
            $stmt = $this->db->prepare($sql . " LIMIT 1");
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                $errors['category_name'] = 'A category with this name already exists.';
            }
        }

        if ($description !== '' && mb_strlen($description) > 255) {
            $errors['description'] = 'Description must not exceed 255 characters.';
        }

        return $errors;
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================
    private function find(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM complaint WHERE complaint_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function findDetailed(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT cm.*,
                    c.customer_id, c.full_name AS customer_name,
                    c.email AS customer_email, c.phone AS customer_phone,
                    c.address_house, c.address_street,
                    c.address_area, c.city, c.landmark,
                    o.order_number, o.order_status, o.order_date,
                    o.delivered_at AS order_delivered_at,
                    cc.category_name,
                    st.staff_id AS assigned_staff_id,
                    st.full_name AS assigned_staff_name
             FROM complaint cm
             JOIN customer c ON c.customer_id = cm.customer_id
             JOIN orders   o ON o.order_id    = cm.order_id
             LEFT JOIN complaint_category cc ON cc.category_id = cm.category_id
             LEFT JOIN staff st ON st.staff_id = cm.assigned_staff_id
             WHERE cm.complaint_id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function findRefund(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM refund WHERE refund_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function findCategory(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM complaint_category WHERE category_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function allCategories(bool $activeOnly = false): array
    {
        $sql = "SELECT category_id, category_name, is_active
                FROM complaint_category";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY category_name ASC";

        return $this->db->query($sql)->fetchAll();
    }

    private function activeStaffList(): array
    {
        return $this->db->query(
            "SELECT staff_id, full_name, role
             FROM staff
             WHERE is_active = 1
             ORDER BY role DESC, full_name ASC"
        )->fetchAll();
    }

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

    private function notifyComplaint(int $complaintId, string $event, array $context = []): void
    {
        // TODO: (new \App\Services\NotificationService())->complaintEvent($complaintId, $event, $context);
        error_log(sprintf(
            '[ComplaintNotify] complaint_id=%d event=%s context=%s',
            $complaintId, $event, json_encode($context)
        ));
    }

    private function notifyRefund(int $refundId, string $event, array $context = []): void
    {
        // TODO: (new \App\Services\NotificationService())->refundEvent($refundId, $event, $context);
        error_log(sprintf(
            '[RefundNotify] refund_id=%d event=%s context=%s',
            $refundId, $event, json_encode($context)
        ));
    }
}
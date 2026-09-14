<?php

namespace App\Controllers\Staff;

use App\Core\Controller;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Order;
use App\Models\StaffPerformance;
use App\Models\SystemConfig;

/**
 * Staff\ComplaintController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Staff-side complaint handling workflow.
 *          View assigned complaints, investigate, resolve,
 *          reject, escalate, and request refunds (which admin
 *          later approves via Admin\RefundController).
 * ----------------------------------------------------------
 */
class ComplaintController extends Controller
{
    private Complaint          $complaints;
    private ComplaintCategory  $categories;
    private Order              $orders;
    private StaffPerformance   $performance;

    /** Valid status transitions for complaint.status */
    private const ALLOWED_TRANSITIONS = [
        'open'                => ['assigned', 'rejected', 'escalated'],
        'assigned'            => ['under_investigation', 'resolved', 'rejected', 'escalated'],
        'under_investigation' => ['resolved', 'rejected', 'escalated'],
        'resolved'            => ['closed'],
        'rejected'            => ['closed'],
        'escalated'           => ['resolved', 'rejected', 'closed'],
        'closed'              => [],   // terminal
    ];

    public function __construct()
    {
        parent::__construct();
        $this->complaints  = new Complaint();
        $this->categories  = new ComplaintCategory();
        $this->orders      = new Order();
        $this->performance = new StaffPerformance();
    }

    // =========================================================
    //  INDEX — List complaints (assigned to staff, or all for admin)
    // =========================================================
    public function index(): void
    {
        $staffId = (int) $this->currentStaffId();
        $filter  = $_GET['status'] ?? 'active'; // active | all | open | assigned | under_investigation | resolved | rejected | escalated

        $filters = [];

        // Staff see only assigned; admin sees all
        if (!$this->isAdmin()) {
            $filters['assigned_staff_id'] = $staffId;
        }

        switch ($filter) {
            case 'active':
                // handled specially below (multi-status)
                break;
            case 'all':
                // no status filter
                break;
            default:
                if (in_array($filter, ['open','assigned','under_investigation','resolved','rejected','escalated','closed'], true)) {
                    $filters['status'] = $filter;
                }
                break;
        }

        $complaints = $this->complaints->allWithRelations($filters);

        // Post-filter for 'active' since allWithRelations uses single status
        if ($filter === 'active') {
            $complaints = array_values(array_filter($complaints, function ($c) {
                return in_array($c['status'], ['open', 'assigned', 'under_investigation'], true);
            }));
        }

        $stats = $this->getComplaintStats($staffId);

        $this->view('staff/complaints/index', [
            'complaints' => $complaints,
            'stats'      => $stats,
            'filter'     => $filter,
        ]);
    }

    // =========================================================
    //  SHOW — Complaint detail
    // =========================================================
    public function show(int $id): void
    {
        $complaint = $this->complaints->findDetailed($id);

        if (!$complaint) {
            $this->notFound('Complaint not found');
            return;
        }

        // Authorization
        if (!$this->isAdmin()
            && (int) $complaint['assigned_staff_id'] !== (int) $this->currentStaffId()) {
            $this->forbidden();
            return;
        }

        // Load related data for the view
        $order = $this->orders->find($complaint['order_id']);

        // Check if a refund already exists for this complaint
        $stmt = $this->db->prepare(
            "SELECT refund_id, amount, status, refund_reason, created_at
             FROM refund
             WHERE complaint_id = :cid
             ORDER BY refund_id DESC
             LIMIT 1"
        );
        $stmt->execute(['cid' => $id]);
        $existingRefund = $stmt->fetch() ?: null;

        $this->view('staff/complaints/show', [
            'complaint'      => $complaint,
            'order'          => $order,
            'existingRefund' => $existingRefund,
        ]);
    }

    // =========================================================
    //  ASSIGN — Staff assigns to self; admin can assign to anyone
    // =========================================================
    public function assign(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $complaint = $this->complaints->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found', 404);
            return;
        }

        $targetStaffId = $this->isAdmin() && !empty($_POST['staff_id'])
            ? (int) $_POST['staff_id']
            : (int) $this->currentStaffId();

        // Validate target staff
        $stmt = $this->db->prepare(
            "SELECT staff_id FROM staff WHERE staff_id = :sid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['sid' => $targetStaffId]);
        if (!$stmt->fetch()) {
            $this->jsonError('Target staff member not found or inactive.', 422);
            return;
        }

        if (!$this->canTransition($complaint['status'], 'assigned')) {
            $this->jsonError("Cannot assign complaint from status: {$complaint['status']}", 422);
            return;
        }

        try {
            $this->complaints->update($id, [
                'assigned_staff_id' => $targetStaffId,
                'status'            => 'assigned',
            ]);

            $this->notifyComplaint($id, 'assigned', ['staff_id' => $targetStaffId]);

            $this->jsonSuccess([
                'message'        => 'Complaint assigned successfully.',
                'complaint_id'   => $id,
                'assigned_staff' => $targetStaffId,
            ]);

        } catch (\Throwable $e) {
            error_log('[ComplaintController::assign] ' . $e->getMessage());
            $this->jsonError('Failed to assign complaint.', 500);
        }
    }

    // =========================================================
    //  START INVESTIGATION
    // =========================================================
    public function startInvestigation(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $complaint = $this->complaints->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found', 404);
            return;
        }

        if (!$this->isAuthorizedForComplaint($complaint)) {
            $this->jsonError('Not authorized for this complaint.', 403);
            return;
        }

        if (!$this->canTransition($complaint['status'], 'under_investigation')) {
            $this->jsonError("Cannot start investigation from status: {$complaint['status']}", 422);
            return;
        }

        try {
            $this->complaints->update($id, [
                'status' => 'under_investigation',
            ]);

            $this->notifyComplaint($id, 'under_investigation');

            $this->jsonSuccess([
                'message'      => 'Investigation started.',
                'complaint_id' => $id,
                'new_status'   => 'under_investigation',
            ]);

        } catch (\Throwable $e) {
            error_log('[ComplaintController::startInvestigation] ' . $e->getMessage());
            $this->jsonError('Failed to update complaint.', 500);
        }
    }

    // =========================================================
    //  ADD INVESTIGATION NOTE
    // =========================================================
    public function addNote(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $complaint = $this->complaints->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found', 404);
            return;
        }

        if (!$this->isAuthorizedForComplaint($complaint)) {
            $this->jsonError('Not authorized for this complaint.', 403);
            return;
        }

        if (in_array($complaint['status'], ['resolved', 'rejected', 'closed'], true)) {
            $this->jsonError("Cannot add notes to a {$complaint['status']} complaint.", 422);
            return;
        }

        $note = trim($_POST['note'] ?? '');
        if ($note === '' || mb_strlen($note) < 5) {
            $this->jsonError('Note must be at least 5 characters.', 422);
            return;
        }

        if (mb_strlen($note) > 2000) {
            $this->jsonError('Note must not exceed 2000 characters.', 422);
        }

        // Append to resolution_notes as an investigation log
        $staffName = $_SESSION['staff_name'] ?? 'Staff';
        $timestamp = date('Y-m-d H:i:s');
        $entry     = "\n[{$timestamp}] {$staffName}: {$note}";

        $existing  = $complaint['resolution_notes'] ?? '';
        $newNotes  = $existing . $entry;

        try {
            $this->complaints->update($id, [
                'resolution_notes' => $newNotes,
            ]);

            $this->jsonSuccess([
                'message'      => 'Note added successfully.',
                'complaint_id' => $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[ComplaintController::addNote] ' . $e->getMessage());
            $this->jsonError('Failed to add note.', 500);
        }
    }

    // =========================================================
    //  RESOLVE
    // =========================================================
    public function resolve(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId   = (int) $this->currentStaffId();
        $complaint = $this->complaints->find($id);

        if (!$complaint) {
            $this->jsonError('Complaint not found', 404);
            return;
        }

        if (!$this->isAuthorizedForComplaint($complaint)) {
            $this->jsonError('Not authorized for this complaint.', 403);
            return;
        }

        if (!$this->canTransition($complaint['status'], 'resolved')) {
            $this->jsonError("Cannot resolve from status: {$complaint['status']}", 422);
            return;
        }

        $notes = trim($_POST['resolution_notes'] ?? '');
        if ($notes === '' || mb_strlen($notes) < 10) {
            $this->jsonError('Resolution notes (min 10 characters) are required.', 422);
            return;
        }

        if (mb_strlen($notes) > 2000) {
            $this->jsonError('Resolution notes must not exceed 2000 characters.', 422);
            return;
        }

        try {
            $this->complaints->beginTransaction();

            $now = date('Y-m-d H:i:s');

            $this->complaints->update($id, [
                'status'           => 'resolved',
                'resolution_notes' => $notes,
                'resolved_at'      => $now,
            ]);

            // Bump staff performance
            $this->performance->bump($staffId, ['complaints_resolved' => 1]);

            $this->complaints->commit();

            $this->notifyComplaint($id, 'resolved', ['notes' => $notes]);

            $this->jsonSuccess([
                'message'      => 'Complaint resolved successfully.',
                'complaint_id' => $id,
                'resolved_at'  => $now,
            ]);

        } catch (\Throwable $e) {
            $this->complaints->rollBack();
            error_log('[ComplaintController::resolve] ' . $e->getMessage());
            $this->jsonError('Failed to resolve complaint.', 500);
        }
    }

    // =========================================================
    //  REJECT
    // =========================================================
    public function reject(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $complaint = $this->complaints->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found', 404);
            return;
        }

        if (!$this->isAuthorizedForComplaint($complaint)) {
            $this->jsonError('Not authorized for this complaint.', 403);
            return;
        }

        if (!$this->canTransition($complaint['status'], 'rejected')) {
            $this->jsonError("Cannot reject from status: {$complaint['status']}", 422);
            return;
        }

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '' || mb_strlen($reason) < 10) {
            $this->jsonError('Rejection reason (min 10 characters) is required.', 422);
            return;
        }

        if (mb_strlen($reason) > 2000) {
            $this->jsonError('Rejection reason must not exceed 2000 characters.', 422);
            return;
        }

        try {
            $now = date('Y-m-d H:i:s');

            $this->complaints->update($id, [
                'status'           => 'rejected',
                'resolution_notes' => $reason,
                'resolved_at'      => $now,
            ]);

            $this->notifyComplaint($id, 'rejected', ['reason' => $reason]);

            $this->jsonSuccess([
                'message'      => 'Complaint rejected.',
                'complaint_id' => $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[ComplaintController::reject] ' . $e->getMessage());
            $this->jsonError('Failed to reject complaint.', 500);
        }
    }

    // =========================================================
    //  ESCALATE — Staff escalates to admin
    // =========================================================
    public function escalate(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $complaint = $this->complaints->find($id);
        if (!$complaint) {
            $this->jsonError('Complaint not found', 404);
            return;
        }

        if (!$this->isAuthorizedForComplaint($complaint)) {
            $this->jsonError('Not authorized for this complaint.', 403);
            return;
        }

        if (!$this->canTransition($complaint['status'], 'escalated')) {
            $this->jsonError("Cannot escalate from status: {$complaint['status']}", 422);
            return;
        }

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '' || mb_strlen($reason) < 10) {
            $this->jsonError('Escalation reason (min 10 characters) is required.', 422);
            return;
        }

        if (mb_strlen($reason) > 2000) {
            $this->jsonError('Escalation reason must not exceed 2000 characters.', 422);
            return;
        }

        try {
            $staffName = $_SESSION['staff_name'] ?? 'Staff';
            $timestamp = date('Y-m-d H:i:s');
            $entry     = "\n[{$timestamp}] ESCALATED by {$staffName}: {$reason}";

            $existing = $complaint['resolution_notes'] ?? '';

            $this->complaints->update($id, [
                'status'           => 'escalated',
                'resolution_notes' => $existing . $entry,
            ]);

            $this->notifyComplaint($id, 'escalated', ['reason' => $reason]);
            $this->notifyAdminEscalation($id, $reason);

            $this->jsonSuccess([
                'message'      => 'Complaint escalated to admin.',
                'complaint_id' => $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[ComplaintController::escalate] ' . $e->getMessage());
            $this->jsonError('Failed to escalate complaint.', 500);
        }
    }

    // =========================================================
    //  REQUEST REFUND — Staff requests admin approval
    // =========================================================
    public function requestRefund(int $id): void
    {
        $this->requirePost();
        $this->verifyCsrf();

        $staffId   = (int) $this->currentStaffId();
        $complaint = $this->complaints->find($id);

        if (!$complaint) {
            $this->jsonError('Complaint not found', 404);
            return;
        }

        if (!$this->isAuthorizedForComplaint($complaint)) {
            $this->jsonError('Not authorized for this complaint.', 403);
            return;
        }

        // Only resolvable complaints can be refunded
        if (!in_array($complaint['status'], ['under_investigation', 'resolved', 'escalated'], true)) {
            $this->jsonError(
                "Refund can only be requested for under_investigation, resolved, or escalated complaints.",
                422
            );
            return;
        }

        // Check no refund already exists
        $stmt = $this->db->prepare(
            "SELECT refund_id, status FROM refund WHERE complaint_id = :cid LIMIT 1"
        );
        $stmt->execute(['cid' => $id]);
        if ($existing = $stmt->fetch()) {
            $this->jsonError(
                "A refund request already exists (status: {$existing['status']}).",
                422
            );
            return;
        }

        $amount = (float) ($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($amount <= 0) {
            $this->jsonError('Refund amount must be greater than 0.', 422);
            return;
        }

        if ($reason === '' || mb_strlen($reason) < 10) {
            $this->jsonError('Refund reason (min 10 characters) is required.', 422);
            return;
        }

        if (mb_strlen($reason) > 2000) {
            $this->jsonError('Refund reason must not exceed 2000 characters.', 422);
            return;
        }

        // Validate against the associated payment
        $stmt = $this->db->prepare(
            "SELECT payment_id, amount, status
             FROM payment
             WHERE order_id = :oid
             ORDER BY payment_id DESC
             LIMIT 1"
        );
        $stmt->execute(['oid' => $complaint['order_id']]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $this->jsonError('No payment found for this order. Cannot request refund.', 422);
            return;
        }

        if ($payment['status'] !== 'approved') {
            $this->jsonError(
                "Cannot refund a payment with status: {$payment['status']}. Must be 'approved'.",
                422
            );
            return;
        }

        if ($amount > (float) $payment['amount']) {
            $this->jsonError(
                sprintf('Refund amount (%.2f) exceeds original payment (%.2f).',
                    $amount, (float) $payment['amount']),
                422
            );
            return;
        }

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO refund
                    (payment_id, order_id, complaint_id, amount, refund_reason, status)
                 VALUES (:pid, :oid, :cid, :amt, :reason, 'pending')"
            );
            $stmt->execute([
                'pid'    => $payment['payment_id'],
                'oid'    => $complaint['order_id'],
                'cid'    => $id,
                'amt'    => $amount,
                'reason' => $reason,
            ]);

            $refundId = (int) $this->db->lastInsertId();

            // Log the request inside complaint notes
            $staffName = $_SESSION['staff_name'] ?? 'Staff';
            $timestamp = date('Y-m-d H:i:s');
            $entry     = sprintf(
                "\n[%s] REFUND REQUESTED by %s: Rs. %.2f — %s",
                $timestamp, $staffName, $amount, $reason
            );
            $existing = $complaint['resolution_notes'] ?? '';
            $this->complaints->update($id, [
                'resolution_notes' => $existing . $entry,
            ]);

            $this->notifyComplaint($id, 'refund_requested', [
                'refund_id' => $refundId,
                'amount'    => $amount,
            ]);
            $this->notifyAdminRefundRequest($refundId, $amount, $reason);

            $this->jsonSuccess([
                'message'      => 'Refund request submitted for admin approval.',
                'complaint_id' => $id,
                'refund_id'    => $refundId,
                'amount'       => $amount,
            ]);

        } catch (\Throwable $e) {
            error_log('[ComplaintController::requestRefund] ' . $e->getMessage());
            $this->jsonError('Failed to submit refund request.', 500);
        }
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function isAuthorizedForComplaint(array $complaint): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return (int) ($complaint['assigned_staff_id'] ?? 0) === (int) $this->currentStaffId();
    }

    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    private function getComplaintStats(int $staffId): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN status IN ('open','assigned','under_investigation') THEN 1 ELSE 0 END) AS open_total,
                    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS assigned_total,
                    SUM(CASE WHEN status = 'under_investigation' THEN 1 ELSE 0 END) AS investigating_total,
                    SUM(CASE WHEN status = 'resolved' AND DATE(resolved_at) = CURDATE() THEN 1 ELSE 0 END) AS resolved_today,
                    SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) AS escalated_total,
                    SUM(CASE WHEN status IN ('resolved','rejected','closed')
                             AND resolved_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                             THEN 1 ELSE 0 END) AS last_30_days
                FROM complaint
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
            'open_total'          => (int) ($row['open_total']          ?? 0),
            'assigned_total'      => (int) ($row['assigned_total']      ?? 0),
            'investigating_total' => (int) ($row['investigating_total'] ?? 0),
            'resolved_today'      => (int) ($row['resolved_today']      ?? 0),
            'escalated_total'     => (int) ($row['escalated_total']     ?? 0),
            'last_30_days'        => (int) ($row['last_30_days']        ?? 0),
        ];
    }

    // =========================================================
    //  NOTIFICATION HOOKS (Shehreen's NotificationService)
    // =========================================================

    private function notifyComplaint(int $complaintId, string $event, array $context = []): void
    {
        // TODO: (new \App\Services\NotificationService())->complaintEvent($complaintId, $event, $context);
        error_log(sprintf(
            '[ComplaintNotify] complaint_id=%d event=%s context=%s',
            $complaintId,
            $event,
            json_encode($context)
        ));
    }

    private function notifyAdminEscalation(int $complaintId, string $reason): void
    {
        // TODO: (new \App\Services\NotificationService())->adminEscalation($complaintId, $reason);
        error_log(sprintf(
            '[AdminEscalation] complaint_id=%d reason=%s',
            $complaintId,
            $reason
        ));
    }

    private function notifyAdminRefundRequest(int $refundId, float $amount, string $reason): void
    {
        // TODO: (new \App\Services\NotificationService())->adminRefundRequest($refundId, $amount, $reason);
        error_log(sprintf(
            '[AdminRefundRequest] refund_id=%d amount=%.2f reason=%s',
            $refundId,
            $amount,
            $reason
        ));
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
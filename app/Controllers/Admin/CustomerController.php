<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin\CustomerController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Admin back-office for customer management.
 *            • List + search + filter customers
 *            • View full profile (orders, complaints, spend)
 *            • Edit customer details
 *            • Suspend / reactivate accounts
 *            • Manually verify email
 *            • Trigger password reset
 *            • Process account deletion requests
 *            • Export CSV
 *
 * Notes:
 *   • Uses raw SQL for reads (Shehroz owns Customer model).
 *   • Uses $this->db->prepare() for writes to keep
 *     independence from other teammates' models.
 *   • Every status change is logged via audit hook.
 * ----------------------------------------------------------
 */
class CustomerController extends Controller
{
    private const PER_PAGE = 20;

    /** Valid account_status enum values from schema */
    private const STATUSES = ['pending', 'active', 'suspended', 'deletion_requested'];

    // =========================================================
    //  INDEX — List + search + filters
    // =========================================================
    public function index(): void
    {
        $this->requireAdmin();

        $search   = trim($_GET['search']   ?? '');
        $status   = $_GET['status']        ?? '';
        $city     = trim($_GET['city']     ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = self::PER_PAGE;
        $offset   = ($page - 1) * $perPage;

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]           = "(full_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
            $params['search']  = '%' . $search . '%';
        }

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[]          = "account_status = :status";
            $params['status'] = $status;
        }

        if ($city !== '') {
            $where[]        = "city = :city";
            $params['city'] = $city;
        }

        $whereSql = implode(' AND ', $where);

        // Total count for pagination
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM customer WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // Paged rows with aggregated counts
        $sql = "SELECT c.customer_id, c.full_name, c.email, c.phone,
                       c.city, c.address_area, c.account_status,
                       c.email_verified, c.created_at, c.updated_at,
                       (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.customer_id) AS orders_count,
                       (SELECT COUNT(*) FROM complaint cm WHERE cm.customer_id = c.customer_id) AS complaints_count,
                       (SELECT COALESCE(SUM(p.amount), 0)
                          FROM payment p
                          JOIN orders o2 ON o2.order_id = p.order_id
                         WHERE o2.customer_id = c.customer_id
                           AND p.status = 'approved') AS total_spent
                FROM customer c
                WHERE {$whereSql}
                ORDER BY c.created_at DESC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $customers = $stmt->fetchAll();

        // Distinct cities for filter dropdown
        $cities = $this->db
            ->query("SELECT DISTINCT city FROM customer ORDER BY city ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);

        // Summary stats (top cards)
        $summary = $this->summaryStats();

        $this->view('admin/customers/index', [
            'customers'  => $customers,
            'summary'    => $summary,
            'cities'     => $cities,
            'search'     => $search,
            'status'     => $status,
            'city'       => $city,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalRows'  => $totalRows,
            'totalPages' => $totalPages,
        ]);
    }

    // =========================================================
    //  SHOW — Full profile
    // =========================================================
    public function show(int $id): void
    {
        $this->requireAdmin();

        $customer = $this->findCustomer($id);
        if (!$customer) {
            $this->notFound('Customer not found.');
            return;
        }

        // Last 10 orders
        $stmt = $this->db->prepare(
            "SELECT o.order_id, o.order_number, o.order_status,
                    o.order_date, o.delivery_date, o.delivered_at,
                    b.total_amount, b.payment_status, b.outstanding_amount
             FROM orders o
             LEFT JOIN bill b ON b.order_id = o.order_id
             WHERE o.customer_id = :cid
             ORDER BY o.order_date DESC
             LIMIT 10"
        );
        $stmt->execute(['cid' => $id]);
        $recentOrders = $stmt->fetchAll();

        // Recent complaints
        $stmt = $this->db->prepare(
            "SELECT cm.complaint_id, cm.complaint_number, cm.type, cm.status,
                    cm.created_at, o.order_number
             FROM complaint cm
             JOIN orders o ON o.order_id = cm.order_id
             WHERE cm.customer_id = :cid
             ORDER BY cm.created_at DESC
             LIMIT 10"
        );
        $stmt->execute(['cid' => $id]);
        $recentComplaints = $stmt->fetchAll();

        // Lifetime stats
        $stmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM orders WHERE customer_id = :cid) AS total_orders,
                (SELECT COUNT(*) FROM orders WHERE customer_id = :cid AND order_status = 'delivered') AS delivered_orders,
                (SELECT COUNT(*) FROM orders WHERE customer_id = :cid AND order_status = 'cancelled') AS cancelled_orders,
                (SELECT COUNT(*) FROM complaint WHERE customer_id = :cid) AS total_complaints,
                (SELECT COALESCE(SUM(p.amount), 0)
                   FROM payment p
                   JOIN orders o ON o.order_id = p.order_id
                  WHERE o.customer_id = :cid AND p.status = 'approved') AS lifetime_spent,
                (SELECT COALESCE(SUM(b.outstanding_amount), 0)
                   FROM bill b
                   JOIN orders o ON o.order_id = b.order_id
                  WHERE o.customer_id = :cid
                    AND b.payment_status IN ('unpaid','partially_paid')) AS outstanding"
        );
        $stmt->execute(['cid' => $id]);
        $stats = $stmt->fetch() ?: [];

        // Active deletion request (if any)
        $stmt = $this->db->prepare(
            "SELECT * FROM account_deletion_request
             WHERE customer_id = :cid AND status = 'pending'
             ORDER BY deletion_id DESC LIMIT 1"
        );
        $stmt->execute(['cid' => $id]);
        $pendingDeletion = $stmt->fetch() ?: null;

        $this->view('admin/customers/show', [
            'customer'          => $customer,
            'recentOrders'      => $recentOrders,
            'recentComplaints'  => $recentComplaints,
            'stats'             => $stats,
            'pendingDeletion'   => $pendingDeletion,
        ]);
    }

    // =========================================================
    //  EDIT — Show edit form
    // =========================================================
    public function edit(int $id): void
    {
        $this->requireAdmin();

        $customer = $this->findCustomer($id);
        if (!$customer) {
            $this->notFound('Customer not found.');
            return;
        }

        $this->view('admin/customers/edit', ['customer' => $customer]);
    }

    // =========================================================
    //  UPDATE — Save edits
    // =========================================================
    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $customer = $this->findCustomer($id);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404);
            return;
        }

        // ---- Collect input ----
        $fullName  = trim($_POST['full_name']       ?? '');
        $email     = trim($_POST['email']           ?? '');
        $phone     = trim($_POST['phone']           ?? '');
        $house     = trim($_POST['address_house']   ?? '');
        $street    = trim($_POST['address_street']  ?? '');
        $area      = trim($_POST['address_area']    ?? '');
        $city      = trim($_POST['city']            ?? '');
        $landmark  = trim($_POST['landmark']        ?? '');
        $status    = $_POST['account_status']       ?? '';
        $verified  = isset($_POST['email_verified']) ? 1 : 0;

        // ---- Validate ----
        $errors = [];

        if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 255) {
            $errors['full_name'] = 'Full name must be between 3 and 255 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required.';
        }

        if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            $errors['phone'] = 'Phone must be 7-20 digits.';
        }

        if ($house === '' || mb_strlen($house) > 50) {
            $errors['address_house'] = 'House/Flat is required (max 50 chars).';
        }

        if ($street === '' || mb_strlen($street) > 100) {
            $errors['address_street'] = 'Street is required (max 100 chars).';
        }

        if ($area === '' || mb_strlen($area) > 100) {
            $errors['address_area'] = 'Area is required (max 100 chars).';
        }

        if ($city === '' || mb_strlen($city) > 50) {
            $errors['city'] = 'City is required (max 50 chars).';
        }

        if ($landmark !== '' && mb_strlen($landmark) > 100) {
            $errors['landmark'] = 'Landmark must not exceed 100 chars.';
        }

        if (!in_array($status, self::STATUSES, true)) {
            $errors['account_status'] = 'Invalid account status.';
        }

        // ---- Uniqueness checks ----
        $stmt = $this->db->prepare(
            "SELECT customer_id FROM customer
             WHERE email = :email AND customer_id <> :id LIMIT 1"
        );
        $stmt->execute(['email' => $email, 'id' => $id]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered to another customer.';
        }

        $stmt = $this->db->prepare(
            "SELECT customer_id FROM customer
             WHERE phone = :phone AND customer_id <> :id LIMIT 1"
        );
        $stmt->execute(['phone' => $phone, 'id' => $id]);
        if ($stmt->fetch()) {
            $errors['phone'] = 'This phone is already registered to another customer.';
        }

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        // ---- Persist ----
        try {
            $sql = "UPDATE customer SET
                        full_name      = :full_name,
                        email          = :email,
                        phone          = :phone,
                        address_house  = :house,
                        address_street = :street,
                        address_area   = :area,
                        city           = :city,
                        landmark       = :landmark,
                        account_status = :status,
                        email_verified = :verified
                    WHERE customer_id = :id";

            $this->db->prepare($sql)->execute([
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => $phone,
                'house'     => $house,
                'street'    => $street,
                'area'      => $area,
                'city'      => $city,
                'landmark'  => $landmark ?: null,
                'status'    => $status,
                'verified'  => $verified,
                'id'        => $id,
            ]);

            $this->audit('customer', 'update', $id, $customer, [
                'full_name'      => $fullName,
                'email'          => $email,
                'phone'          => $phone,
                'account_status' => $status,
            ]);

            $this->jsonSuccess([
                'message'  => 'Customer updated successfully.',
                'redirect' => '/admin/customers/' . $id,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminCustomerController::update] ' . $e->getMessage());
            $this->jsonError('Failed to update customer.', 500);
        }
    }

    // =========================================================
    //  SUSPEND
    // =========================================================
    public function suspend(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $customer = $this->findCustomer($id);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404);
            return;
        }

        if ($customer['account_status'] === 'suspended') {
            $this->jsonError('Customer is already suspended.', 422);
            return;
        }

        $reason = trim($_POST['reason'] ?? '');

        try {
            $this->db->prepare(
                "UPDATE customer SET account_status = 'suspended' WHERE customer_id = :id"
            )->execute(['id' => $id]);

            $this->audit('customer', 'suspend', $id,
                ['status' => $customer['account_status']],
                ['status' => 'suspended', 'reason' => $reason]
            );

            $this->notifyCustomerStatus($id, 'suspended', $reason);

            $this->jsonSuccess([
                'message' => 'Customer account suspended.',
                'status'  => 'suspended',
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminCustomerController::suspend] ' . $e->getMessage());
            $this->jsonError('Failed to suspend customer.', 500);
        }
    }

    // =========================================================
    //  ACTIVATE
    // =========================================================
    public function activate(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $customer = $this->findCustomer($id);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404);
            return;
        }

        if ($customer['account_status'] === 'active') {
            $this->jsonError('Customer is already active.', 422);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE customer SET account_status = 'active' WHERE customer_id = :id"
            )->execute(['id' => $id]);

            $this->audit('customer', 'activate', $id,
                ['status' => $customer['account_status']],
                ['status' => 'active']
            );

            $this->notifyCustomerStatus($id, 'active');

            $this->jsonSuccess([
                'message' => 'Customer account activated.',
                'status'  => 'active',
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminCustomerController::activate] ' . $e->getMessage());
            $this->jsonError('Failed to activate customer.', 500);
        }
    }

    // =========================================================
    //  VERIFY EMAIL (manual admin override)
    // =========================================================
    public function verifyEmail(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $customer = $this->findCustomer($id);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404);
            return;
        }

        if ((int) $customer['email_verified'] === 1) {
            $this->jsonError('Email is already verified.', 422);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE customer
                 SET email_verified = 1,
                     verification_token = NULL,
                     token_expiry = NULL
                 WHERE customer_id = :id"
            )->execute(['id' => $id]);

            $this->audit('customer', 'verify_email', $id,
                ['email_verified' => 0], ['email_verified' => 1]);

            $this->jsonSuccess(['message' => 'Email verified successfully.']);

        } catch (\Throwable $e) {
            error_log('[AdminCustomerController::verifyEmail] ' . $e->getMessage());
            $this->jsonError('Failed to verify email.', 500);
        }
    }

    // =========================================================
    //  RESET PASSWORD (generate token + notify)
    // =========================================================
    public function resetPassword(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $customer = $this->findCustomer($id);
        if (!$customer) {
            $this->jsonError('Customer not found.', 404);
            return;
        }

        try {
            // Invalidate old tokens for this customer
            $this->db->prepare(
                "UPDATE password_reset
                 SET used = 1, used_at = NOW()
                 WHERE customer_id = :cid AND used = 0"
            )->execute(['cid' => $id]);

            // Create new token (24-hour expiry)
            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $this->db->prepare(
                "INSERT INTO password_reset
                    (customer_id, token, token_expiry, used)
                 VALUES (:cid, :token, :exp, 0)"
            )->execute([
                'cid'   => $id,
                'token' => $token,
                'exp'   => $expiry,
            ]);

            $this->audit('customer', 'reset_password', $id, null,
                ['token_expiry' => $expiry]);

            $this->notifyPasswordReset($id, $token);

            $this->jsonSuccess([
                'message'  => 'Password reset link generated and sent to customer.',
                'expiry'   => $expiry,
                // NOTE: token is NOT returned to the browser in production.
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminCustomerController::resetPassword] ' . $e->getMessage());
            $this->jsonError('Failed to generate password reset.', 500);
        }
    }

    // =========================================================
    //  DELETION REQUESTS
    // =========================================================
    public function deletionRequests(): void
    {
        $this->requireAdmin();

        $filter = $_GET['status'] ?? 'pending';

        $sql = "SELECT dr.*,
                       c.full_name, c.email, c.phone, c.account_status,
                       s.full_name AS processed_by_name
                FROM account_deletion_request dr
                JOIN customer c ON c.customer_id = dr.customer_id
                LEFT JOIN staff s ON s.staff_id = dr.processed_by_staff_id
                WHERE 1 = 1";

        $params = [];
        if (in_array($filter, ['pending', 'approved', 'rejected'], true)) {
            $sql .= " AND dr.status = :status";
            $params['status'] = $filter;
        }

        $sql .= " ORDER BY dr.request_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        $this->view('admin/customers/deletion_requests', [
            'requests' => $requests,
            'filter'   => $filter,
        ]);
    }

    public function processDeletion(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $action = $_POST['action'] ?? ''; // approve | reject
        $reason = trim($_POST['reason'] ?? '');

        if (!in_array($action, ['approve', 'reject'], true)) {
            $this->jsonError('Invalid action.', 422);
            return;
        }

        if ($action === 'reject' && ($reason === '' || mb_strlen($reason) < 10)) {
            $this->jsonError('Rejection reason must be at least 10 characters.', 422);
            return;
        }

        // Load request
        $stmt = $this->db->prepare(
            "SELECT * FROM account_deletion_request
             WHERE deletion_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();

        if (!$request) {
            $this->jsonError('Deletion request not found.', 404);
            return;
        }

        if ($request['status'] !== 'pending') {
            $this->jsonError("This request has already been {$request['status']}.", 422);
            return;
        }

        $customerId = (int) $request['customer_id'];
        $staffId    = (int) $this->currentStaffId();

        try {
            $this->db->beginTransaction();

            if ($action === 'approve') {
                // Anonymize customer PII, keep row for FK integrity
                $anonEmail = 'deleted_' . $customerId . '_' . time() . '@removed.local';
                $anonPhone = '0000000000' . str_pad((string) $customerId, 4, '0', STR_PAD_LEFT);

                $this->db->prepare(
                    "UPDATE customer SET
                        full_name = 'Deleted User',
                        email     = :email,
                        phone     = :phone,
                        password_hash = '',
                        address_house = 'N/A',
                        address_street = 'N/A',
                        address_area = 'N/A',
                        city = 'N/A',
                        landmark = NULL,
                        account_status = 'suspended',
                        email_verified = 0,
                        verification_token = NULL,
                        token_expiry = NULL
                     WHERE customer_id = :id"
                )->execute([
                    'email' => $anonEmail,
                    'phone' => $anonPhone,
                    'id'    => $customerId,
                ]);

                $newStatus = 'approved';
            } else {
                $newStatus = 'rejected';

                // Roll customer status back from 'deletion_requested' to 'active'
                $this->db->prepare(
                    "UPDATE customer SET account_status = 'active'
                     WHERE customer_id = :id AND account_status = 'deletion_requested'"
                )->execute(['id' => $customerId]);
            }

            // Update request row
            $this->db->prepare(
                "UPDATE account_deletion_request
                 SET status = :status,
                     rejection_reason = :reason,
                     processed_by_staff_id = :staff,
                     processed_at = NOW()
                 WHERE deletion_id = :id"
            )->execute([
                'status' => $newStatus,
                'reason' => $action === 'reject' ? $reason : null,
                'staff'  => $staffId,
                'id'     => $id,
            ]);

            $this->db->commit();

            $this->audit('customer', $action === 'approve' ? 'deletion_approved' : 'deletion_rejected',
                $customerId, ['request_status' => 'pending'],
                ['request_status' => $newStatus, 'reason' => $reason]
            );

            $this->notifyDeletionProcessed($customerId, $newStatus, $reason);

            $this->jsonSuccess([
                'message' => "Deletion request {$newStatus}.",
                'status'  => $newStatus,
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[AdminCustomerController::processDeletion] ' . $e->getMessage());
            $this->jsonError('Failed to process deletion request.', 500);
        }
    }

    // =========================================================
    //  EXPORT CSV
    // =========================================================
    public function export(): void
    {
        $this->requireAdmin();

        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status']      ?? '';
        $city   = trim($_GET['city']   ?? '');

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "(full_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[]          = "account_status = :status";
            $params['status'] = $status;
        }
        if ($city !== '') {
            $where[]        = "city = :city";
            $params['city'] = $city;
        }

        $sql = "SELECT customer_id, full_name, email, phone,
                       address_house, address_street, address_area, city, landmark,
                       account_status, email_verified, created_at
                FROM customer
                WHERE " . implode(' AND ', $where) . "
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Stream CSV
        $filename = 'customers_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens it correctly
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'ID', 'Full Name', 'Email', 'Phone',
            'House', 'Street', 'Area', 'City', 'Landmark',
            'Status', 'Verified', 'Registered',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['customer_id'],
                $r['full_name'],
                $r['email'],
                $r['phone'],
                $r['address_house'],
                $r['address_street'],
                $r['address_area'],
                $r['city'],
                $r['landmark'] ?? '',
                $r['account_status'],
                $r['email_verified'] ? 'Yes' : 'No',
                $r['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function findCustomer(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM customer WHERE customer_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function summaryStats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN account_status = 'active'    THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN account_status = 'pending'   THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN account_status = 'suspended' THEN 1 ELSE 0 END) AS suspended,
                SUM(CASE WHEN account_status = 'deletion_requested'
                                                          THEN 1 ELSE 0 END) AS deletion_requested,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS new_today
             FROM customer"
        )->fetch() ?: [];

        return [
            'total'              => (int) ($row['total']              ?? 0),
            'active'             => (int) ($row['active']             ?? 0),
            'pending'            => (int) ($row['pending']            ?? 0),
            'suspended'          => (int) ($row['suspended']          ?? 0),
            'deletion_requested' => (int) ($row['deletion_requested'] ?? 0),
            'new_today'          => (int) ($row['new_today']          ?? 0),
        ];
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

    private function notifyCustomerStatus(int $customerId, string $status, string $reason = ''): void
    {
        // TODO: (new \App\Services\NotificationService())->customerStatus($customerId, $status, $reason);
        error_log(sprintf(
            '[CustomerStatusNotify] customer_id=%d status=%s reason=%s',
            $customerId, $status, $reason
        ));
    }

    private function notifyPasswordReset(int $customerId, string $token): void
    {
        // TODO: (new \App\Services\NotificationService())->passwordReset($customerId, $token);
        error_log(sprintf(
            '[PasswordResetNotify] customer_id=%d token=%s',
            $customerId, substr($token, 0, 8) . '...'
        ));
    }

    private function notifyDeletionProcessed(int $customerId, string $status, string $reason = ''): void
    {
        // TODO: (new \App\Services\NotificationService())->deletionProcessed($customerId, $status, $reason);
        error_log(sprintf(
            '[DeletionProcessedNotify] customer_id=%d status=%s reason=%s',
            $customerId, $status, $reason
        ));
    }
}
<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin\StaffController
 * ----------------------------------------------------------
 * Owner : Rohan
 * Purpose: Admin back-office for staff management.
 *            • List + search + filter staff
 *            • View profile (performance + recent activity)
 *            • Create new staff (with password hashing)
 *            • Edit staff details
 *            • Activate / deactivate (never hard-delete)
 *            • Change role (admin ↔ staff) with last-admin guard
 *            • Reset password (generate token, notify)
 *            • Performance detail page with 30-day trend
 *            • CSV export
 *
 * Safety guards:
 *   • Cannot deactivate the last active admin
 *   • Cannot demote the last active admin
 *   • Never hard-delete (FK references from orders,
 *     deliveries, payments, etc.)
 * ----------------------------------------------------------
 */
class StaffController extends Controller
{
    private const PER_PAGE = 20;

    /** Valid role enum values from schema */
    private const ROLES = ['admin', 'staff'];

    /** Minimum password length on create / reset */
    private const MIN_PASSWORD_LENGTH = 8;

    // =========================================================
    //  INDEX — List + search + filters
    // =========================================================
    public function index(): void
    {
        $this->requireAdmin();

        $search  = trim($_GET['search'] ?? '');
        $role    = $_GET['role']        ?? '';
        $status  = $_GET['status']      ?? '';   // active | inactive
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = self::PER_PAGE;
        $offset  = ($page - 1) * $perPage;

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "(full_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if ($role !== '' && in_array($role, self::ROLES, true)) {
            $where[]        = "role = :role";
            $params['role'] = $role;
        }

        if ($status === 'active') {
            $where[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "is_active = 0";
        }

        $whereSql = implode(' AND ', $where);

        // Total count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM staff WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // Rows with today's performance joins
        $sql = "SELECT s.staff_id, s.full_name, s.email, s.phone,
                       s.role, s.is_active, s.last_login, s.created_at,
                       (SELECT COUNT(*) FROM pickup_request pr
                         WHERE pr.assigned_staff_id = s.staff_id) AS pickups_total,
                       (SELECT COUNT(*) FROM delivery d
                         WHERE d.assigned_staff_id = s.staff_id) AS deliveries_total,
                       (SELECT COUNT(*) FROM complaint cm
                         WHERE cm.assigned_staff_id = s.staff_id) AS complaints_total,
                       COALESCE((SELECT sp.performance_score
                                   FROM staff_performance sp
                                  WHERE sp.staff_id = s.staff_id
                                    AND sp.recorded_date = CURDATE()), 0) AS today_score,
                       COALESCE((SELECT sp.average_rating
                                   FROM staff_performance sp
                                  WHERE sp.staff_id = s.staff_id
                                  ORDER BY sp.recorded_date DESC LIMIT 1), 0) AS latest_rating
                FROM staff s
                WHERE {$whereSql}
                ORDER BY s.is_active DESC, s.role DESC, s.full_name ASC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $staff = $stmt->fetchAll();

        $summary = $this->summaryStats();

        $this->view('admin/staff/index', [
            'staff'      => $staff,
            'summary'    => $summary,
            'search'     => $search,
            'role'       => $role,
            'status'     => $status,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalRows'  => $totalRows,
            'totalPages' => $totalPages,
        ]);
    }

    // =========================================================
    //  SHOW — Profile + activity
    // =========================================================
    public function show(int $id): void
    {
        $this->requireAdmin();

        $staff = $this->findStaff($id);
        if (!$staff) {
            $this->notFound('Staff member not found.');
            return;
        }

        // Today's performance row (may be null)
        $stmt = $this->db->prepare(
            "SELECT * FROM staff_performance
             WHERE staff_id = :sid AND recorded_date = CURDATE()
             LIMIT 1"
        );
        $stmt->execute(['sid' => $id]);
        $todayPerf = $stmt->fetch() ?: null;

        // Lifetime totals
        $stmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM pickup_request WHERE assigned_staff_id = :sid) AS pickups_total,
                (SELECT COUNT(*) FROM pickup_request WHERE assigned_staff_id = :sid AND status = 'picked_up') AS pickups_done,
                (SELECT COUNT(*) FROM delivery WHERE assigned_staff_id = :sid) AS deliveries_total,
                (SELECT COUNT(*) FROM delivery WHERE assigned_staff_id = :sid AND status = 'delivered') AS deliveries_done,
                (SELECT COUNT(*) FROM complaint WHERE assigned_staff_id = :sid) AS complaints_total,
                (SELECT COUNT(*) FROM complaint WHERE assigned_staff_id = :sid AND status = 'resolved') AS complaints_resolved,
                (SELECT COALESCE(AVG(performance_score), 0) FROM staff_performance WHERE staff_id = :sid) AS avg_score,
                (SELECT COALESCE(AVG(average_rating), 0) FROM staff_performance WHERE staff_id = :sid) AS avg_rating"
        );
        $stmt->execute(['sid' => $id]);
        $totals = $stmt->fetch() ?: [];

        // Recent activity
        $stmt = $this->db->prepare(
            "SELECT 'pickup' AS kind, pr.pickup_id AS ref_id, pr.request_number AS ref_label,
                    pr.status, pr.pickup_date AS event_date, c.full_name AS customer_name
             FROM pickup_request pr
             JOIN customer c ON c.customer_id = pr.customer_id
             WHERE pr.assigned_staff_id = :sid
             UNION ALL
             SELECT 'delivery' AS kind, d.delivery_id, o.order_number,
                    d.status, d.delivery_date, c.full_name
             FROM delivery d
             JOIN orders   o ON o.order_id    = d.order_id
             JOIN customer c ON c.customer_id = o.customer_id
             WHERE d.assigned_staff_id = :sid
             UNION ALL
             SELECT 'complaint' AS kind, cm.complaint_id, cm.complaint_number,
                    cm.status, DATE(cm.created_at), c.full_name
             FROM complaint cm
             JOIN customer c ON c.customer_id = cm.customer_id
             WHERE cm.assigned_staff_id = :sid
             ORDER BY event_date DESC
             LIMIT 15"
        );
        $stmt->execute(['sid' => $id]);
        $recentActivity = $stmt->fetchAll();

        // Last 14 days performance rows (for mini chart)
        $stmt = $this->db->prepare(
            "SELECT recorded_date, pickups_completed, deliveries_completed,
                    orders_handled, complaints_resolved, performance_score, average_rating
             FROM staff_performance
             WHERE staff_id = :sid
               AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             ORDER BY recorded_date ASC"
        );
        $stmt->execute(['sid' => $id]);
        $trend = $stmt->fetchAll();

        $this->view('admin/staff/show', [
            'staff'          => $staff,
            'todayPerf'      => $todayPerf,
            'totals'         => $totals,
            'recentActivity' => $recentActivity,
            'trend'          => $trend,
        ]);
    }

    // =========================================================
    //  CREATE — Show form
    // =========================================================
    public function create(): void
    {
        $this->requireAdmin();

        $this->view('admin/staff/create', [
            'roles' => self::ROLES,
        ]);
    }

    // =========================================================
    //  STORE — Create new staff
    // =========================================================
    public function store(): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $role     = $_POST['role']           ?? 'staff';
        $password = $_POST['password']       ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 1; // default active

        $errors = [];

        // Name
        if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 255) {
            $errors['full_name'] = 'Full name must be between 3 and 255 characters.';
        }

        // Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required.';
        } elseif (mb_strlen($email) > 255) {
            $errors['email'] = 'Email must not exceed 255 characters.';
        }

        // Phone
        if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            $errors['phone'] = 'Phone must be 7-20 digits (spaces/dashes allowed).';
        }

        // Role
        if (!in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Invalid role selected.';
        }

        // Password
        $pwErr = $this->validatePassword($password, $confirm);
        if ($pwErr !== null) {
            $errors['password'] = $pwErr;
        }

        // Uniqueness
        $stmt = $this->db->prepare("SELECT staff_id FROM staff WHERE email = :e LIMIT 1");
        $stmt->execute(['e' => $email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered.';
        }

        $stmt = $this->db->prepare("SELECT staff_id FROM staff WHERE phone = :p LIMIT 1");
        $stmt->execute(['p' => $phone]);
        if ($stmt->fetch()) {
            $errors['phone'] = 'This phone is already registered.';
        }

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        // Hash password using bcrypt (matches our seed format)
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        try {
            $this->db->beginTransaction();

            $this->db->prepare(
                "INSERT INTO staff (full_name, email, phone, password_hash, role, is_active)
                 VALUES (:full_name, :email, :phone, :hash, :role, :active)"
            )->execute([
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => $phone,
                'hash'      => $hash,
                'role'      => $role,
                'active'    => $isActive,
            ]);

            $staffId = (int) $this->db->lastInsertId();

            $this->db->commit();

        } catch (\PDOException $e) {
            $this->db->rollBack();
            if ($e->getCode() === '23000') {
                $this->jsonError('Email or phone is already taken.', 409);
                return;
            }
            error_log('[AdminStaffController::store] ' . $e->getMessage());
            $this->jsonError('Failed to create staff member.', 500);
            return;
        }

        $this->audit('staff', 'create', $staffId, null, [
            'full_name' => $fullName,
            'email'     => $email,
            'role'      => $role,
        ]);

        $this->notifyStaffWelcome($staffId, $email, $role);

        $this->jsonSuccess([
            'message'  => 'Staff member created successfully.',
            'staff_id' => $staffId,
            'redirect' => '/admin/staff/' . $staffId,
        ]);
    }

    // =========================================================
    //  EDIT — Show edit form
    // =========================================================
    public function edit(int $id): void
    {
        $this->requireAdmin();

        $staff = $this->findStaff($id);
        if (!$staff) {
            $this->notFound('Staff member not found.');
            return;
        }

        $this->view('admin/staff/edit', [
            'staff' => $staff,
            'roles' => self::ROLES,
        ]);
    }

    // =========================================================
    //  UPDATE — Save edits
    // =========================================================
    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $staff = $this->findStaff($id);
        if (!$staff) {
            $this->jsonError('Staff member not found.', 404);
            return;
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $role     = $_POST['role']           ?? 'staff';

        $errors = [];

        if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 255) {
            $errors['full_name'] = 'Full name must be between 3 and 255 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required.';
        }

        if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            $errors['phone'] = 'Phone must be 7-20 digits.';
        }

        if (!in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Invalid role selected.';
        }

        // Last-admin guard: if demoting an admin, ensure another active admin remains
        if ($staff['role'] === 'admin' && $role !== 'admin') {
            if ($this->activeAdminCount() <= 1) {
                $errors['role'] = 'Cannot demote the last active admin.';
            }
        }

        // Uniqueness (excluding self)
        $stmt = $this->db->prepare(
            "SELECT staff_id FROM staff WHERE email = :e AND staff_id <> :id LIMIT 1"
        );
        $stmt->execute(['e' => $email, 'id' => $id]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered to another staff member.';
        }

        $stmt = $this->db->prepare(
            "SELECT staff_id FROM staff WHERE phone = :p AND staff_id <> :id LIMIT 1"
        );
        $stmt->execute(['p' => $phone, 'id' => $id]);
        if ($stmt->fetch()) {
            $errors['phone'] = 'This phone is already registered to another staff member.';
        }

        if (!empty($errors)) {
            $this->jsonError('Please fix the highlighted fields.', 422, ['errors' => $errors]);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE staff SET
                    full_name = :full_name,
                    email     = :email,
                    phone     = :phone,
                    role      = :role
                 WHERE staff_id = :id"
            )->execute([
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => $phone,
                'role'      => $role,
                'id'        => $id,
            ]);

            $this->audit('staff', 'update', $id,
                ['full_name' => $staff['full_name'], 'role' => $staff['role']],
                ['full_name' => $fullName, 'role' => $role]
            );

            $this->jsonSuccess([
                'message'  => 'Staff member updated successfully.',
                'redirect' => '/admin/staff/' . $id,
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->jsonError('Email or phone is already taken.', 409);
                return;
            }
            error_log('[AdminStaffController::update] ' . $e->getMessage());
            $this->jsonError('Failed to update staff member.', 500);
        }
    }

    // =========================================================
    //  TOGGLE ACTIVE
    // =========================================================
    public function toggleActive(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $staff = $this->findStaff($id);
        if (!$staff) {
            $this->jsonError('Staff member not found.', 404);
            return;
        }

        // Prevent self-deactivation
        if ((int) $id === (int) $this->currentStaffId()) {
            $this->jsonError('You cannot deactivate your own account.', 422);
            return;
        }

        $newState = !((bool) $staff['is_active']);

        // Last-admin guard: cannot deactivate the last active admin
        if (!$newState && $staff['role'] === 'admin' && $this->activeAdminCount() <= 1) {
            $this->jsonError('Cannot deactivate the last active admin.', 422);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE staff SET is_active = :active WHERE staff_id = :id"
            )->execute([
                'active' => $newState ? 1 : 0,
                'id'     => $id,
            ]);

            $this->audit('staff', $newState ? 'activate' : 'deactivate', $id,
                ['is_active' => (int) $staff['is_active']],
                ['is_active' => $newState ? 1 : 0]
            );

            $this->notifyStaffStatus($id, $newState ? 'active' : 'inactive');

            $this->jsonSuccess([
                'message'   => 'Staff member ' . ($newState ? 'activated' : 'deactivated') . '.',
                'is_active' => $newState,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminStaffController::toggleActive] ' . $e->getMessage());
            $this->jsonError('Failed to update staff status.', 500);
        }
    }

    // =========================================================
    //  CHANGE ROLE (explicit endpoint)
    // =========================================================
    public function changeRole(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $staff = $this->findStaff($id);
        if (!$staff) {
            $this->jsonError('Staff member not found.', 404);
            return;
        }

        $newRole = $_POST['role'] ?? '';

        if (!in_array($newRole, self::ROLES, true)) {
            $this->jsonError('Invalid role.', 422);
            return;
        }

        if ($newRole === $staff['role']) {
            $this->jsonError('Staff member already has this role.', 422);
            return;
        }

        // Last-admin guard: demoting the last admin
        if ($staff['role'] === 'admin' && $newRole !== 'admin' && $this->activeAdminCount() <= 1) {
            $this->jsonError('Cannot demote the last active admin.', 422);
            return;
        }

        try {
            $this->db->prepare(
                "UPDATE staff SET role = :role WHERE staff_id = :id"
            )->execute(['role' => $newRole, 'id' => $id]);

            $this->audit('staff', 'role_change', $id,
                ['role' => $staff['role']], ['role' => $newRole]);

            $this->jsonSuccess([
                'message' => 'Role updated successfully.',
                'role'    => $newRole,
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminStaffController::changeRole] ' . $e->getMessage());
            $this->jsonError('Failed to update role.', 500);
        }
    }

    // =========================================================
    //  RESET PASSWORD
    // =========================================================
    public function resetPassword(int $id): void
    {
        $this->requireAdmin();
        $this->requirePost();
        $this->verifyCsrf();

        $staff = $this->findStaff($id);
        if (!$staff) {
            $this->jsonError('Staff member not found.', 404);
            return;
        }

        // Two modes:
        //   1) Generate reset token (send email)
        //   2) Set a new password immediately (if provided)
        $newPassword = $_POST['new_password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';
        $mode        = $_POST['mode'] ?? 'token'; // 'token' | 'set'

        try {
            if ($mode === 'set' && $newPassword !== '') {
                $pwErr = $this->validatePassword($newPassword, $confirm);
                if ($pwErr !== null) {
                    $this->jsonError($pwErr, 422);
                    return;
                }

                $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);

                $this->db->prepare(
                    "UPDATE staff SET password_hash = :h WHERE staff_id = :id"
                )->execute(['h' => $hash, 'id' => $id]);

                // Invalidate pending reset tokens
                $this->db->prepare(
                    "UPDATE password_reset SET used = 1, used_at = NOW()
                     WHERE staff_id = :sid AND used = 0"
                )->execute(['sid' => $id]);

                $this->audit('staff', 'password_set', $id, null, ['mode' => 'set']);
                $this->notifyPasswordChanged($id, 'staff');

                $this->jsonSuccess([
                    'message' => 'Password updated successfully.',
                    'mode'    => 'set',
                ]);
                return;
            }

            // Default: generate reset token
            $this->db->prepare(
                "UPDATE password_reset SET used = 1, used_at = NOW()
                 WHERE staff_id = :sid AND used = 0"
            )->execute(['sid' => $id]);

            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $this->db->prepare(
                "INSERT INTO password_reset (staff_id, token, token_expiry, used)
                 VALUES (:sid, :token, :exp, 0)"
            )->execute(['sid' => $id, 'token' => $token, 'exp' => $expiry]);

            $this->audit('staff', 'password_reset_token', $id, null, ['expiry' => $expiry]);
            $this->notifyPasswordReset($id, $token, 'staff');

            $this->jsonSuccess([
                'message' => 'Password reset link generated and sent.',
                'expiry'  => $expiry,
                'mode'    => 'token',
            ]);

        } catch (\Throwable $e) {
            error_log('[AdminStaffController::resetPassword] ' . $e->getMessage());
            $this->jsonError('Failed to reset password.', 500);
        }
    }

    // =========================================================
    //  PERFORMANCE — Detail page with 30-day trend
    // =========================================================
    public function performance(int $id): void
    {
        $this->requireAdmin();

        $staff = $this->findStaff($id);
        if (!$staff) {
            $this->notFound('Staff member not found.');
            return;
        }

        // 30-day rows
        $stmt = $this->db->prepare(
            "SELECT * FROM staff_performance
             WHERE staff_id = :sid
               AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             ORDER BY recorded_date ASC"
        );
        $stmt->execute(['sid' => $id]);
        $rows = $stmt->fetchAll();

        // Aggregates for the period
        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(pickups_completed), 0)     AS pickups,
                COALESCE(SUM(deliveries_completed), 0)  AS deliveries,
                COALESCE(SUM(orders_handled), 0)        AS orders,
                COALESCE(SUM(complaints_resolved), 0)   AS complaints,
                COALESCE(AVG(average_rating), 0)        AS avg_rating,
                COALESCE(AVG(performance_score), 0)     AS avg_score,
                COUNT(*)                                AS days_recorded
             FROM staff_performance
             WHERE staff_id = :sid
               AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        );
        $stmt->execute(['sid' => $id]);
        $aggregate = $stmt->fetch() ?: [];

        $this->view('admin/staff/performance', [
            'staff'     => $staff,
            'rows'      => $rows,
            'aggregate' => $aggregate,
        ]);
    }

    // =========================================================
    //  PERFORMANCE — All staff comparison
    // =========================================================
    public function performanceOverview(): void
    {
        $this->requireAdmin();

        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_GET['to']   ?? date('Y-m-d');

        // Basic date sanity
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $stmt = $this->db->prepare(
            "SELECT s.staff_id, s.full_name, s.email, s.role, s.is_active,
                    COALESCE(SUM(sp.pickups_completed), 0)     AS pickups,
                    COALESCE(SUM(sp.deliveries_completed), 0)  AS deliveries,
                    COALESCE(SUM(sp.orders_handled), 0)        AS orders,
                    COALESCE(SUM(sp.complaints_resolved), 0)   AS complaints,
                    COALESCE(AVG(sp.average_rating), 0)        AS avg_rating,
                    COALESCE(AVG(sp.performance_score), 0)     AS avg_score,
                    COUNT(sp.performance_id)                   AS days_recorded
             FROM staff s
             LEFT JOIN staff_performance sp
                    ON sp.staff_id = s.staff_id
                   AND sp.recorded_date BETWEEN :from AND :to
             GROUP BY s.staff_id, s.full_name, s.email, s.role, s.is_active
             ORDER BY avg_score DESC, deliveries DESC, pickups DESC"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $staffStats = $stmt->fetchAll();

        $this->view('admin/staff/performance_overview', [
            'staffStats' => $staffStats,
            'from'       => $from,
            'to'         => $to,
        ]);
    }

    // =========================================================
    //  EXPORT CSV
    // =========================================================
    public function export(): void
    {
        $this->requireAdmin();

        $search = trim($_GET['search'] ?? '');
        $role   = $_GET['role']        ?? '';
        $status = $_GET['status']      ?? '';

        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[]          = "(full_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        if ($role !== '' && in_array($role, self::ROLES, true)) {
            $where[]        = "role = :role";
            $params['role'] = $role;
        }
        if ($status === 'active') {
            $where[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "is_active = 0";
        }

        $sql = "SELECT staff_id, full_name, email, phone, role, is_active,
                       last_login, created_at
                FROM staff
                WHERE " . implode(' AND ', $where) . "
                ORDER BY is_active DESC, role DESC, full_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $filename = 'staff_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($out, [
            'ID', 'Full Name', 'Email', 'Phone',
            'Role', 'Active', 'Last Login', 'Created At',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['staff_id'],
                $r['full_name'],
                $r['email'],
                $r['phone'],
                $r['role'],
                $r['is_active'] ? 'Yes' : 'No',
                $r['last_login'] ?? '',
                $r['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function findStaff(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM staff WHERE staff_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    private function summaryStats(): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admins,
                SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) AS regular,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                                        THEN 1 ELSE 0 END) AS online_recently
             FROM staff"
        )->fetch() ?: [];

        return [
            'total'           => (int) ($row['total']           ?? 0),
            'admins'          => (int) ($row['admins']          ?? 0),
            'regular'         => (int) ($row['regular']         ?? 0),
            'active'          => (int) ($row['active']          ?? 0),
            'inactive'        => (int) ($row['inactive']        ?? 0),
            'online_recently' => (int) ($row['online_recently'] ?? 0),
        ];
    }

    /**
     * Count active admins (for last-admin guard).
     */
    private function activeAdminCount(): int
    {
        return (int) $this->db
            ->query("SELECT COUNT(*) FROM staff WHERE role = 'admin' AND is_active = 1")
            ->fetchColumn();
    }

    /**
     * Validate password + confirmation. Returns error string or null.
     */
    private function validatePassword(string $password, string $confirm): ?string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        }

        if (strlen($password) > 72) {
            // bcrypt truncates at 72 bytes — cap it to avoid surprises
            return 'Password must not exceed 72 characters.';
        }

        if (!preg_match('/[A-Za-z]/', $password)) {
            return 'Password must contain at least one letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number.';
        }

        if ($password !== $confirm) {
            return 'Passwords do not match.';
        }

        return null;
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

    private function notifyStaffWelcome(int $staffId, string $email, string $role): void
    {
        // TODO: (new \App\Services\NotificationService())->staffWelcome($staffId, $email, $role);
        error_log(sprintf(
            '[StaffWelcomeNotify] staff_id=%d email=%s role=%s',
            $staffId, $email, $role
        ));
    }

    private function notifyStaffStatus(int $staffId, string $status): void
    {
        // TODO: (new \App\Services\NotificationService())->staffStatus($staffId, $status);
        error_log(sprintf('[StaffStatusNotify] staff_id=%d status=%s', $staffId, $status));
    }

    private function notifyPasswordReset(int $staffId, string $token, string $audience): void
    {
        // TODO: (new \App\Services\NotificationService())->passwordReset($staffId, $token, $audience);
        error_log(sprintf(
            '[PasswordResetNotify] audience=%s id=%d token=%s...',
            $audience, $staffId, substr($token, 0, 8)
        ));
    }

    private function notifyPasswordChanged(int $staffId, string $audience): void
    {
        // TODO: (new \App\Services\NotificationService())->passwordChanged($staffId, $audience);
        error_log(sprintf('[PasswordChangedNotify] audience=%s id=%d', $audience, $staffId));
    }
}
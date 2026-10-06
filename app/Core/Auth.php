<?php

namespace App\Core;

class Auth
{
    public static function attemptCustomer(string $email, string $password): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM customer WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $customer = $stmt->fetch();

        $success = $customer && password_verify($password, $customer['password_hash']);

        self::logAttempt('customer', $customer['customer_id'] ?? null, $email, $success);

        if (!$success) {
            return false;
        }

        if ($customer['account_status'] !== 'active') {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['auth'] = ['type' => 'customer', 'id' => $customer['customer_id'], 'role' => null];
        return true;
    }

    public static function attemptStaff(string $email, string $password): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM staff WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $staff = $stmt->fetch();

        $success = $staff && password_verify($password, $staff['password_hash']) && $staff['is_active'];

        self::logAttempt('staff', $staff['staff_id'] ?? null, $email, $success);

        if (!$success) {
            return false;
        }

        $db->prepare("UPDATE staff SET last_login = NOW() WHERE staff_id = :id")
           ->execute(['id' => $staff['staff_id']]);

        session_regenerate_id(true);
        $_SESSION['auth'] = ['type' => 'staff', 'id' => $staff['staff_id'], 'role' => $staff['role']];
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION['auth']);
    }

    public static function user(): ?array
    {
        return $_SESSION['auth'] ?? null;
    }

    public static function id(): int|string|null
    {
        return $_SESSION['auth']['id'] ?? null;
    }

    public static function role(): string
    {
        $auth = $_SESSION['auth'] ?? null;
        if (!$auth) {
            return 'guest';
        }
        return $auth['type'] === 'staff' ? $auth['role'] : 'customer';
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private static function logAttempt(string $type, int|string|null $id, string $email, bool $success): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO login_activity (customer_id, staff_id, email_tried, ip_address, is_successful)
            VALUES (:customer_id, :staff_id, :email, :ip, :success)
        ");
        $stmt->execute([
            'customer_id' => $type === 'customer' ? $id : null,
            'staff_id'    => $type === 'staff' ? $id : null,
            'email'       => $email,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'success'     => $success ? 1 : 0,
        ]);
    }
}
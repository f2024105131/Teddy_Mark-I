<?php

namespace App\Core;

class AuditLogger
{
    public static function log(string $module, string $action, ?array $oldValue = null, ?array $newValue = null): void
    {
        $auth = Auth::user();

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO audit_log (module, action, user_id, user_type, old_value, new_value, ip_address, user_agent)
            VALUES (:module, :action, :user_id, :user_type, :old_value, :new_value, :ip_address, :user_agent)
        ");

        $stmt->execute([
            'module'     => $module,
            'action'     => $action,
            'user_id'    => $auth['id'] ?? null,
            'user_type'  => self::resolveUserType($auth),
            'old_value'  => $oldValue !== null ? json_encode($oldValue) : null,
            'new_value'  => $newValue !== null ? json_encode($newValue) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    private static function resolveUserType(?array $auth): string
    {
        if ($auth === null) {
            return 'system';
        }

        if ($auth['type'] === 'customer') {
            return 'customer';
        }

        return $auth['role'] === 'admin' ? 'admin' : 'staff';
    }
}
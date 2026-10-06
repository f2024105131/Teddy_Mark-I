<?php

namespace App\Models;

use App\Core\Model;

class LoginActivity extends Model
{
    protected string $table = 'login_activity';
    protected string $primaryKey = 'log_id';

    public function recentFailedAttempts(string $email, int $minutes = 15): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS attempts
            FROM login_activity
            WHERE email_tried = :email
              AND is_successful = 0
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->execute(['email' => $email, 'minutes' => $minutes]);
        return (int) $stmt->fetch()['attempts'];
    }
}
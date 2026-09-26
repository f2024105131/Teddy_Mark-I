<?php

namespace App\Models;

use App\Core\Model;

class PasswordReset extends Model
{
    protected string $table = 'password_reset';
    protected string $primaryKey = 'reset_id';

    public function findValidToken(string $token): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM password_reset
            WHERE token = :token AND used = 0
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        $reset = $stmt->fetch();

        if ($reset && strtotime($reset['token_expiry']) < time()) {
            return false;
        }

        return $reset;
    }

    public function markUsed(int $resetId): bool
    {
        $stmt = $this->db->prepare("UPDATE password_reset SET used = 1, used_at = NOW() WHERE reset_id = :id");
        return $stmt->execute(['id' => $resetId]);
    }
}
<?php

namespace App\Models;

use App\Core\Model;

class Staff extends Model
{
    protected string $table = 'staff';
    protected string $primaryKey = 'staff_id';

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM staff WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function activeStaff(): array
    {
        return $this->where('is_active', 1);
    }
}
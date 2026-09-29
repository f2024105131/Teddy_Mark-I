<?php

namespace App\Models;

use App\Core\Model;

class AccountDeletionRequest extends Model
{
    protected string $table = 'account_deletion_request';
    protected string $primaryKey = 'deletion_id';

    public function pending(): array
    {
        return $this->where('status', 'pending');
    }

    public function forCustomer(int $customerId): array
    {
        return $this->where('customer_id', $customerId);
    }
}
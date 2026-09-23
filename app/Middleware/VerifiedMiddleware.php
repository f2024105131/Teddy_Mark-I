<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Database;

class VerifiedMiddleware
{
    public static function handle(): void
    {
        $user = Auth::user();

        if (!$user || $user['type'] !== 'customer') {
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT email_verified FROM customer WHERE customer_id = :id LIMIT 1");
        $stmt->execute(['id' => $user['id']]);
        $customer = $stmt->fetch();

        if (!$customer || !$customer['email_verified']) {
            header('Location: /verify-email');
            exit;
        }
    }
}
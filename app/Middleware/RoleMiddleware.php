<?php

namespace App\Middleware;

use App\Core\Auth;

class RoleMiddleware
{
    public static function require(array $allowedRoles): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        if (!in_array(Auth::role(), $allowedRoles, true)) {
            http_response_code(403);
            require __DIR__ . '/../Views/errors/403.php';
            exit;
        }
    }
}
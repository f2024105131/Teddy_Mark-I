<?php

if (file_exists(__DIR__ . '/../../.env')) {
    foreach (file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"");
    }
}

return [
    'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'     => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_NAME'] ?? 'laundry_management',
    'username' => $_ENV['DB_USER'] ?? 'laundry_app',
    'password' => $_ENV['DB_PASS'] ?? '',
];
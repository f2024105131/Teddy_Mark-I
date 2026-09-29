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
    'env'          => $_ENV['APP_ENV'] ?? 'local',
    'url'          => $_ENV['APP_URL'] ?? 'http://localhost',
    'session_name' => $_ENV['SESSION_NAME'] ?? 'teddy_session',
    'timezone'     => 'Asia/Karachi',
    'debug'        => ($_ENV['APP_ENV'] ?? 'local') !== 'production',
];
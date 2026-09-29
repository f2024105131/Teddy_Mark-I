<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $amount): string
{
    return 'PKR ' . number_format($amount, 2);
}

function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '';
    }
    return date($format, strtotime($date));
}

function formatDateTime(?string $date, string $format = 'd M Y, h:i A'): string
{
    if (!$date) {
        return '';
    }
    return date($format, strtotime($date));
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old_input'][$key] ?? $default;
}

function errorFor(string $key): ?string
{
    return $_SESSION['errors'][$key][0] ?? null;
}
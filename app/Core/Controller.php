<?php

namespace App\Core;

use RuntimeException;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = null): void
    {
        $viewPath = __DIR__ . "/../Views/{$view}.php";

        if (!file_exists($viewPath)) {
            throw new RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        if ($layout === null) {
            require $viewPath;
            return;
        }

        $layoutPath = __DIR__ . "/../Views/layouts/{$layout}.php";

        if (!file_exists($layoutPath)) {
            throw new RuntimeException("Layout not found: {$layout}");
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        require $layoutPath;
    }

    protected function redirect(string $path): never
    {
        header("Location: {$path}");
        exit;
    }

    protected function back(): never
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION["flash_{$type}"] = $message;
    }

    protected function getFlash(string $type): ?string
    {
        $message = $_SESSION["flash_{$type}"] ?? null;
        unset($_SESSION["flash_{$type}"]);
        return $message;
    }

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrf(): bool
    {
        $submitted = $_POST['_csrf'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';
        return $expected !== '' && hash_equals($expected, $submitted);
    }

    protected function requireCsrf(): void
    {
        if (!$this->verifyCsrf()) {
            http_response_code(419);
            die('Your session expired or the form was tampered with. Please go back and try again.');
        }
    }
}
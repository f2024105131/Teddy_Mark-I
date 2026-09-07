<?php
//18039
//When a user visits a URL, decide which function handler should run.
namespace App\Core;

use Throwable;


class Router
{
    /** @var array<int, array{method:string, path:string, pattern:string, handler:callable|array, middleware:array}> */
    private array $routes = [];

    private string $groupPrefix = '';
    private array $groupMiddleware = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix .= $prefix;
        $this->groupMiddleware = [...$this->groupMiddleware, ...$middleware];

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $fullPath = rtrim($this->groupPrefix . $path, '/');
        $fullPath = $fullPath === '' ? '/' : $fullPath;

        $this->routes[] = [
            'method'     => $method,
            'path'       => $fullPath,
            'pattern'    => $this->toRegex($fullPath),
            'handler'    => $handler,
            'middleware' => [...$this->groupMiddleware, ...$middleware],
        ];
    }

   
    private function toRegex(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $method = $this->resolveMethod($requestMethod);

        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $path = rtrim($path, '/');
        $path = $path === '' ? '/' : $path;

        try {
            $pathExistsForOtherMethod = false;

            foreach ($this->routes as $route) {
                if (!preg_match($route['pattern'], $path, $matches)) {
                    continue;
                }

                if ($route['method'] !== $method) {
                    $pathExistsForOtherMethod = true;
                    continue;
                }

                // Keep only the named captures ({id} => '5'), drop numeric ones
                $params = array_filter(
                    $matches,
                    fn($key) => !is_int($key),
                    ARRAY_FILTER_USE_KEY
                );

                foreach ($route['middleware'] as $middleware) {
                    $middleware(); // each middleware is responsible for its own redirect/exit on failure
                }

                $this->invoke($route['handler'], $params);
                return;
            }

            if ($pathExistsForOtherMethod) {
                http_response_code(405);
                echo '405 Method Not Allowed';
                return;
            }

            http_response_code(404);
            $this->renderErrorPage(404);
        } catch (Throwable $e) {
            error_log('Unhandled exception in Router::dispatch — ' . $e->getMessage());
            http_response_code(500);
            $this->renderErrorPage(500);
        }
    }

   
    private function resolveMethod(string $method): string
    {
        $method = strtoupper($method);
        if ($method === 'POST' && isset($_POST['_method'])) {
            return strtoupper($_POST['_method']);
        }
        return $method;
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $methodName] = $handler;
            call_user_func_array([new $class(), $methodName], $params);
            return;
        }

        call_user_func_array($handler, $params);
    }

    private function renderErrorPage(int $code): void
    {
        $view = __DIR__ . "/../Views/errors/{$code}.php";
        if (file_exists($view)) {
            require $view;
        } else {
            echo "{$code} error";
        }
    }
}
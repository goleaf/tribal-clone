<?php
declare(strict_types=1);

class Router
{
    private array $routes = [];
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function get(string $path, array $handler, array $options = []): void
    {
        $this->map('GET', $path, $handler, $options);
    }

    public function post(string $path, array $handler, array $options = []): void
    {
        $this->map('POST', $path, $handler, $options);
    }

    private function map(string $method, string $path, array $handler, array $options): void
    {
        $this->routes[$method][$path] = ['handler' => $handler, 'options' => $options];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (!isset($this->routes[$method][$requestPath])) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $route = $this->routes[$method][$requestPath];
        $handler = $route['handler'];
        $options = $route['options'] ?? [];

        if (!isset($handler[0], $handler[1])) {
            http_response_code(500);
            echo 'Invalid route handler';
            return;
        }

        [$class, $methodName] = $handler;

        if (!class_exists($class) || !method_exists($class, $methodName)) {
            http_response_code(500);
            echo 'Route handler not found';
            return;
        }

        if (!empty($options['auth'])) {
            AuthMiddleware::requireAuth();
        }

        $controller = new $class($this->conn);
        $controller->{$methodName}();
    }
}

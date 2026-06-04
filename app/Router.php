<?php

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function match(array $methods, string $path, callable|string $handler, array $middleware = []): void
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $middleware);
        }
    }

    private function addRoute(string $method, string $path, callable|string $handler, array $middleware): void
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $regex = '#^' . $regex . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['regex'], $uri, $matches)) {
                $params = array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $m) {
                    $this->executeMiddleware($m);
                }

                $handler = $route['handler'];
                if (is_string($handler)) {
                    [$class, $method] = explode('@', $handler);
                    $class = "Controllers\\{$class}";
                    if (!class_exists($class)) {
                        $class = "App\\Controllers\\{$class}";
                    }
                    $controller = new $class();
                    echo $controller->$method(...$params);
                } else {
                    echo $handler($params);
                }
                return;
            }
        }

        abort(404);
    }

    private function executeMiddleware(string $middleware): void
    {
        $class = "Middleware\\{$middleware}";
        if (!class_exists($class)) {
            $class = "App\\Middleware\\{$middleware}";
        }
        if (class_exists($class)) {
            (new $class())->handle();
        }
    }
}

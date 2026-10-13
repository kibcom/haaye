<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\PlatformMiddleware;

final class Router
{
    /** @var array<int, array{method:string, path:string, handler:array, middleware:array}> */
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function dispatch(): void
    {
        $method = Request::method();
        $path = Request::path();

        foreach ($this->routes as $route) {
            $params = $this->match($route['path'], $path);
            if ($params === null || $route['method'] !== $method) {
                continue;
            }

            foreach ($route['middleware'] as $middleware) {
                $this->runMiddleware($middleware);
            }

            \App\Services\FeatureService::guard($path);

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->{$action}(...array_values($params));
            return;
        }

        Response::notFound();
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $routeParts = explode('/', trim($routePath, '/'));
        $requestParts = explode('/', trim($requestPath, '/'));

        if (count($routeParts) !== count($requestParts)) {
            return null;
        }

        $params = [];
        foreach ($routeParts as $index => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $params[trim($part, '{}')] = $requestParts[$index];
                continue;
            }

            if ($part !== $requestParts[$index]) {
                return null;
            }
        }

        return $params;
    }

    private function runMiddleware(string $middleware): void
    {
        if ($middleware === 'auth') {
            AuthMiddleware::handle();
            return;
        }

        if ($middleware === 'guest') {
            GuestMiddleware::handle();
            return;
        }

        if ($middleware === 'csrf') {
            CsrfMiddleware::handle();
            return;
        }

        if ($middleware === 'platform') {
            PlatformMiddleware::handle();
            return;
        }

        if (str_starts_with($middleware, 'can:')) {
            PermissionMiddleware::handle(substr($middleware, 4));
        }
    }
}

<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

final class Router
{
    private array $routes = [];

    /**
     * @param string $path
     * @param $handler
     * @param array $middleware
     */
    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param string $path
     * @param $handler
     * @param array $middleware
     */
    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param string $path
     * @param $handler
     * @param array $middleware
     */
    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    /**
     * @param string $path
     * @param $handler
     * @param array $middleware
     */
    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * @param Request $request
     * @return null
     */
    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $pattern = preg_replace(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                '(?P<$1>[^/]+)',
                $route['path']
            );

            if (!preg_match('#^'.$pattern.'/?$#', $request->path(), $matches)) {
                continue;
            }

            $parameters = array_filter(
                $matches,
                static fn(string | int $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            foreach ($route['middleware'] as $middleware) {
                $middleware($request);
            }

            $route['handler']($request, $parameters);
            return;
        }

        Response::error(
            'NOT_FOUND',
            'ไม่พบ API ที่ต้องการ',
            404
        );
    }

    /**
     * @param string $method
     * @param string $path
     * @param $handler
     * @param array $middleware
     */
    private function add(
        string $method,
        string $path,
        callable $handler,
        array $middleware
    ): void {
        $this->routes[] = [
            'method' => $method,
            'path' => '/'.trim($path, '/'),
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }
}
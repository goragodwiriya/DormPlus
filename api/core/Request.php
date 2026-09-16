<?php

declare (strict_types = 1);

namespace DormPlus\Api\Core;

final class Request
{
    private ?array $jsonBody = null;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = '/'.trim(rawurldecode($uri), '/');
        $base = $this->basePath();

        if ($base !== '' && str_starts_with($uri, $base.'/')) {
            $uri = substr($uri, strlen($base));
        }

        return $uri;
    }

    /**
     * โฟลเดอร์ที่ติดตั้งแอป เช่น "/DormPlus/public" เมื่อรันใต้ Apache
     * หรือ "" เมื่อรันด้วย php -S ผ่าน router.php
     */
    public function basePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $directory = rtrim(dirname($script), '/');

        return preg_replace('#/api$#', '', $directory) ?? '';
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * @return mixed
     */
    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (!str_contains(strtolower($contentType), 'application/json')) {
            return $this->jsonBody = [];
        }

        $body = file_get_contents('php://input');

        if ($body === false || trim($body) === '') {
            return $this->jsonBody = [];
        }

        $decoded = json_decode($body, true);

        return $this->jsonBody = is_array($decoded) ? $decoded : [];
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function header(string $name): ?string
    {
        $serverName = 'HTTP_'.strtoupper(str_replace('-', '_', $name));

        return $_SERVER[$serverName] ?? null;
    }
}
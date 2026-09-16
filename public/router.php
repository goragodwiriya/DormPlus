<?php

declare (strict_types = 1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicPath = __DIR__.$path;

if ($path !== '/' && is_file($publicPath)) {
    return false;
}

if (str_starts_with($path, '/api/')) {
    require __DIR__.'/api/index.php';
    return true;
}

require __DIR__.'/index.html';
return true;
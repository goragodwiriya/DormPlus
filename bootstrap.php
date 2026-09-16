<?php

declare (strict_types = 1);

define('ROOT_PATH', __DIR__);

$config = require ROOT_PATH.'/config/config.php';

date_default_timezone_set($config['app']['timezone']);

// ให้ SQLite (datetime('now', 'localtime')) ใช้เขตเวลาเดียวกับ PHP
putenv('TZ='.$config['app']['timezone']);

spl_autoload_register(static function (string $class): void {
    $prefix = 'DormPlus\\';
    $baseDirectory = ROOT_PATH.'/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));

    $directories = [
        'Config\\' => 'config/',
        'Api\\Core\\' => 'api/core/',
        'Api\\Controllers\\' => 'api/controllers/',
        'Api\\Middleware\\' => 'api/middleware/',
        'Api\\Repositories\\' => 'api/repositories/',
        'Api\\Services\\' => 'api/services/'
    ];

    foreach ($directories as $namespace => $directory) {
        if (!str_starts_with($relativeClass, $namespace)) {
            continue;
        }

        $className = substr($relativeClass, strlen($namespace));
        $file = $baseDirectory.$directory
        .str_replace('\\', DIRECTORY_SEPARATOR, $className)
            .'.php';

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});

$logDirectory = ROOT_PATH.'/storage/logs';

if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0775, true);
}

ini_set('log_errors', '1');
ini_set('error_log', $logDirectory.'/php-error.log');

if ($config['app']['debug']) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}
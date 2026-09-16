<?php

declare (strict_types = 1);

namespace DormPlus\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require __DIR__.'/config.php';
        $databasePath = $config['database']['path'];
        $directory = dirname($databasePath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์ฐานข้อมูลได้');
        }

        try {
            self::$connection = new PDO('sqlite:'.$databasePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            self::$connection->exec('PRAGMA foreign_keys = ON');
            self::$connection->exec('PRAGMA journal_mode = WAL');
            self::$connection->exec('PRAGMA busy_timeout = 5000');

            return self::$connection;
        } catch (PDOException $exception) {
            throw new RuntimeException('ไม่สามารถเชื่อมต่อฐานข้อมูลได้', 0, $exception);
        }
    }

    private function __construct()
    {
    }
}
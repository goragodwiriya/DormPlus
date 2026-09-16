<?php

declare (strict_types = 1);

use DormPlus\Config\Database;

require dirname(__DIR__).'/bootstrap.php';

$pdo = Database::connection();
$migrationDirectory = __DIR__.'/migrations';
$migrationFiles = glob($migrationDirectory.'/*.sql') ?: [];

sort($migrationFiles);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration TEXT NOT NULL UNIQUE,
        executed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$checkStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM migrations WHERE migration = :migration'
);

$recordStatement = $pdo->prepare(
    'INSERT INTO migrations (migration) VALUES (:migration)'
);

foreach ($migrationFiles as $file) {
    $migrationName = basename($file);

    $checkStatement->execute(['migration' => $migrationName]);

    if ((int) $checkStatement->fetchColumn() > 0) {
        echo "[ข้าม] {$migrationName}\n";
        continue;
    }

    $sql = file_get_contents($file);

    if ($sql === false) {
        fwrite(STDERR, "ไม่สามารถอ่าน {$migrationName}\n");
        exit(1);
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $recordStatement->execute(['migration' => $migrationName]);
        $pdo->commit();

        echo "[สำเร็จ] {$migrationName}\n";
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, "[ผิดพลาด] {$migrationName}: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "ปรับปรุงฐานข้อมูลเรียบร้อยแล้ว\n";
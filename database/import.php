<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "Importing database schema and seed data...\n";

try {
    $port = defined('DB_PORT') && DB_PORT ? ';port=' . DB_PORT : '';
    $pdo = new PDO("mysql:host=" . DB_HOST . $port, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $dbName = defined('DB_NAME') ? DB_NAME : 'hotel_reservation_db';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");

    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);
    echo "✓ Schema imported successfully.\n";

    $seedSql = file_get_contents(__DIR__ . '/seed.sql');
    $pdo->exec($seedSql);
    echo "✓ Seed data imported successfully.\n";

    echo "Database setup completed clean!\n";
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

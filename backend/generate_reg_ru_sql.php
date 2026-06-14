<?php
declare(strict_types=1);

$dbName = 'u3519830_default';
$schemaPath = __DIR__ . '/schema_parts.sql';
$seedPath = __DIR__ . '/seed_parts.sql';
$outPath = __DIR__ . '/reg_ru_parts_full.sql';

if (!file_exists($schemaPath) || !file_exists($seedPath)) {
    fwrite(STDERR, "Need schema_parts.sql and seed_parts.sql\n");
    exit(1);
}

$schema = file_get_contents($schemaPath);
$seed = file_get_contents($seedPath);
if ($schema === false || $seed === false) {
    fwrite(STDERR, "Read error\n");
    exit(1);
}

$seed = preg_replace('/^SET NAMES utf8mb4;\s*/m', '', $seed) ?? $seed;
$seed = preg_replace('/^SET FOREIGN_KEY_CHECKS = 0;\s*/m', '', $seed) ?? $seed;
$seed = preg_replace('/^TRUNCATE TABLE catalog_parts;\s*/m', '', $seed) ?? $seed;
$seed = preg_replace('/^TRUNCATE TABLE part_models;\s*/m', '', $seed) ?? $seed;
$seed = preg_replace('/^SET FOREIGN_KEY_CHECKS = 1;\s*/m', '', $seed) ?? $seed;

$header = <<<SQL
-- AutoLine: полный SQL для Reg.ru
-- База: {$dbName}
-- Импорт: phpMyAdmin -> выбрать базу {$dbName} -> Импорт -> этот файл
-- Содержит: схему + 468 моделей + 2808 запчастей (BMW, Mercedes, Audi и все марки)

USE `{$dbName}`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS catalog_parts;
DROP TABLE IF EXISTS part_models;

SET FOREIGN_KEY_CHECKS = 1;

SQL;

$footer = <<<'SQL'

ALTER TABLE part_models AUTO_INCREMENT = 469;
ALTER TABLE catalog_parts AUTO_INCREMENT = 2809;

SQL;

$full = $header . "\n" . trim($schema) . "\n\n" . trim($seed) . "\n" . $footer;
file_put_contents($outPath, $full);

echo "Generated: {$outPath}\n";
echo 'Size: ' . number_format(strlen($full)) . " bytes\n";

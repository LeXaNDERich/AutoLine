<?php
declare(strict_types=1);

$schemaPath = __DIR__ . '/schema_parts.sql';
$seedPath = __DIR__ . '/seed_parts.sql';
$outPath = __DIR__ . '/autoline_parts_full.sql';

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

$header = <<<'SQL'
-- AutoLine: полный SQL каталога запчастей
-- Импорт: phpMyAdmin -> база autoline -> Импорт -> этот файл
-- Содержит: схему + 468 моделей + 2808 запчастей (все марки, включая BMW)

CREATE DATABASE IF NOT EXISTS autoline CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE autoline;

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

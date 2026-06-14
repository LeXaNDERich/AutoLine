<?php
declare(strict_types=1);

require_once __DIR__ . '/seed_parts_data.php';

function sqlEsc(string $value): string
{
    return str_replace(["\\", "'"], ["\\\\", "''"], $value);
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = str_replace(['ё', 'й', 'ц', 'у', 'к', 'е', 'н', 'г', 'ш', 'щ', 'з', 'х', 'ъ', 'ф', 'ы', 'в', 'а', 'п', 'р', 'о', 'л', 'д', 'ж', 'э', 'я', 'ч', 'с', 'м', 'и', 'т', 'ь', 'б', 'ю'], ['e', 'y', 'c', 'u', 'k', 'e', 'n', 'g', 'sh', 'sch', 'z', 'h', '', 'f', 'y', 'v', 'a', 'p', 'r', 'o', 'l', 'd', 'zh', 'e', 'ya', 'ch', 's', 'm', 'i', 't', '', 'b', 'yu'], $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

$brands = seedLoadBrands();
$multipliers = seedPriceMultipliers();
$templates = seedPartTemplates();
$now = time();

$lines = [];
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$lines[] = 'TRUNCATE TABLE catalog_parts;';
$lines[] = 'TRUNCATE TABLE part_models;';
$lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$lines[] = '';

$modelId = 1;
$partId = 1;

foreach ($brands as $brand) {
    $brandSlug = slugify((string)($brand['slug'] ?? ''));
    $brandName = trim((string)($brand['name'] ?? ''));
    if ($brandSlug === '' || $brandName === '') {
        continue;
    }

    $multiplier = (float)($multipliers[$brandSlug] ?? 1.2);
    $modelNames = seedModelsForBrand($brandSlug, $brandName);

    foreach ($modelNames as $sortOrder => $modelName) {
        $modelSlug = slugify($brandSlug . '-' . $modelName);
        $lines[] = "INSERT INTO part_models (id, slug, brand_slug, name, sort_order, is_active, created_at) VALUES ({$modelId}, '" . sqlEsc($modelSlug) . "', '" . sqlEsc($brandSlug) . "', '" . sqlEsc($modelName) . "', {$sortOrder}, 1, {$now});";

        for ($i = 0; $i < 6; $i++) {
            $template = $templates[($sortOrder * 3 + $i) % count($templates)];
            $price = (int)round((float)$template['base'] * $multiplier);
            $keyword = seedPartImageKeyword((string)$template['title']);
            $lines[] = "INSERT INTO catalog_parts (id, model_id, title, description, meta, price, image_url, sort_order, is_active, created_at, updated_at) VALUES ({$partId}, {$modelId}, '" . sqlEsc((string)$template['title']) . "', '" . sqlEsc((string)$template['desc']) . "', '" . sqlEsc((string)$template['meta']) . "', '" . sqlEsc(seedFormatPrice($price)) . "', 'https://source.unsplash.com/460x260/?" . sqlEsc($keyword) . "', {$i}, 1, {$now}, {$now});";
            $partId++;
        }

        $modelId++;
    }
}

$lines[] = '';
$lines[] = '-- Models: ' . ($modelId - 1);
$lines[] = '-- Parts: ' . ($partId - 1);

$outPath = __DIR__ . '/seed_parts.sql';
file_put_contents($outPath, implode(PHP_EOL, $lines) . PHP_EOL);

echo 'Generated: ' . $outPath . PHP_EOL;
echo 'Models: ' . ($modelId - 1) . PHP_EOL;
echo 'Parts: ' . ($partId - 1) . PHP_EOL;

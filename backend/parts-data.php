<?php
declare(strict_types=1);

require_once __DIR__ . '/parts_store.php';

header('Content-Type: application/json; charset=utf-8');

$brandSlug = trim((string)($_GET['brand'] ?? ''));
if ($brandSlug !== '') {
    $brandSlug = partsSlugify($brandSlug);
}

$parts = partsGetCatalog($brandSlug !== '' ? $brandSlug : null);
$models = partsGetModelsList($brandSlug !== '' ? $brandSlug : null);

echo json_encode([
    'ok' => true,
    'storage' => partsStorageMode(),
    'brand' => $brandSlug !== '' ? $brandSlug : null,
    'parts' => $parts,
    'models' => $models,
], JSON_UNESCAPED_UNICODE);
exit;

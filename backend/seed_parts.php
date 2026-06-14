<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/parts_store.php';
require_once __DIR__ . '/seed_parts_data.php';

header('Content-Type: text/html; charset=utf-8');

function seedResponse(string $title, array $stats, bool $ok = true): void
{
    $color = $ok ? '#0f7a3f' : '#b42318';
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>body{font-family:Segoe UI,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;color:#111}';
    echo 'h1{font-size:24px}ul{line-height:1.7}.ok{color:' . $color . '}a{color:#f39200}</style></head><body>';
    echo '<h1 class="ok">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><ul>';
    foreach ($stats as $line) {
        echo '<li>' . htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul><p><a href="admin.php">Перейти в админку</a> · <a href="../brand.php?brand=bmw">Открыть BMW на сайте</a></p>';
    echo '</body></html>';
    exit;
}

function seedGetModelId(PDO $pdo, string $brandSlug, string $modelName, int $sortOrder): int
{
    $slug = partsSlugify($brandSlug . '-' . $modelName);
    $stmt = $pdo->prepare('SELECT id FROM part_models WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int)$row['id'];
    }

    $insert = $pdo->prepare('
        INSERT INTO part_models (slug, brand_slug, name, sort_order, is_active, created_at)
        VALUES (:slug, :brand_slug, :name, :sort_order, 1, :created_at)
    ');
    $insert->execute([
        ':slug' => $slug,
        ':brand_slug' => $brandSlug,
        ':name' => $modelName,
        ':sort_order' => $sortOrder,
        ':created_at' => time(),
    ]);
    return (int)$pdo->lastInsertId();
}

function seedCountParts(PDO $pdo, int $modelId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM catalog_parts WHERE model_id = :id');
    $stmt->execute([':id' => $modelId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['c'] ?? 0);
}

function seedInsertParts(PDO $pdo, int $modelId, float $multiplier, int $modelIndex): int
{
    $templates = seedPartTemplates();
    $now = time();
    $insert = $pdo->prepare('
        INSERT INTO catalog_parts (model_id, title, description, meta, price, image_url, sort_order, is_active, created_at, updated_at)
        VALUES (:model_id, :title, :description, :meta, :price, :image_url, :sort_order, 1, :created_at, :updated_at)
    ');

    $added = 0;
    for ($i = 0; $i < 6; $i++) {
        $template = $templates[($modelIndex * 3 + $i) % count($templates)];
        $price = (int)round((float)$template['base'] * $multiplier);
        $keyword = seedPartImageKeyword((string)$template['title']);
        $insert->execute([
            ':model_id' => $modelId,
            ':title' => (string)$template['title'],
            ':description' => (string)$template['desc'],
            ':meta' => (string)$template['meta'],
            ':price' => seedFormatPrice($price),
            ':image_url' => 'https://source.unsplash.com/460x260/?' . $keyword,
            ':sort_order' => $i,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $added++;
    }
    return $added;
}

function seedImportSqlFile(PDO $pdo): array
{
    $path = __DIR__ . '/seed_parts.sql';
    if (!file_exists($path)) {
        return ['ok' => false, 'error' => 'Файл backend/seed_parts.sql не найден.'];
    }

    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        return ['ok' => false, 'error' => 'Не удалось прочитать backend/seed_parts.sql'];
    }

    partsEnsureSchema($pdo);
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $statements = 0;
    foreach (preg_split('/;\s*\n/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '' || strpos($statement, '--') === 0) {
            continue;
        }
        $pdo->exec($statement);
        $statements++;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    partsSyncJsonFromDatabase($pdo);

    $models = (int)($pdo->query('SELECT COUNT(*) AS c FROM part_models')->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    $parts = (int)($pdo->query('SELECT COUNT(*) AS c FROM catalog_parts')->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    $bmwModels = (int)($pdo->query("SELECT COUNT(*) AS c FROM part_models WHERE brand_slug = 'bmw'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    $bmwParts = (int)($pdo->query("SELECT COUNT(*) AS c FROM catalog_parts cp JOIN part_models pm ON pm.id = cp.model_id WHERE pm.brand_slug = 'bmw'")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    return [
        'ok' => true,
        'statements' => $statements,
        'models' => $models,
        'parts' => $parts,
        'bmw_models' => $bmwModels,
        'bmw_parts' => $bmwParts,
    ];
}

function seedRunCatalog(PDO $pdo, bool $reset = false, bool $forceParts = false): array
{
    partsEnsureSchema($pdo);

    if ($reset) {
        $pdo->exec('DELETE FROM catalog_parts');
        $pdo->exec('DELETE FROM part_models');
    }

    $brands = seedLoadBrands();
    if ($brands === []) {
        return ['ok' => false, 'error' => 'Не найден assets/data/brands.json'];
    }

    $multipliers = seedPriceMultipliers();
    $modelsAdded = 0;
    $modelsSkipped = 0;
    $partsAdded = 0;
    $brandsTouched = 0;

    foreach ($brands as $brand) {
        $brandSlug = partsSlugify((string)($brand['slug'] ?? ''));
        $brandName = trim((string)($brand['name'] ?? ''));
        if ($brandSlug === '' || $brandName === '') {
            continue;
        }

        $multiplier = (float)($multipliers[$brandSlug] ?? 1.2);
        $modelNames = seedModelsForBrand($brandSlug, $brandName);
        $brandHasWork = false;

        foreach ($modelNames as $sortOrder => $modelName) {
            $existingId = null;
            $slug = partsSlugify($brandSlug . '-' . $modelName);
            $check = $pdo->prepare('SELECT id FROM part_models WHERE slug = :slug LIMIT 1');
            $check->execute([':slug' => $slug]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $modelId = (int)$existing['id'];
                $modelsSkipped++;
            } else {
                $modelId = seedGetModelId($pdo, $brandSlug, $modelName, (int)$sortOrder);
                $modelsAdded++;
            }

            $partsCount = seedCountParts($pdo, $modelId);
            if ($partsCount === 0 || $forceParts || $reset) {
                if ($partsCount > 0 && ($forceParts || $reset)) {
                    $delete = $pdo->prepare('DELETE FROM catalog_parts WHERE model_id = :id');
                    $delete->execute([':id' => $modelId]);
                }
                $partsAdded += seedInsertParts($pdo, $modelId, $multiplier, (int)$sortOrder);
                $brandHasWork = true;
            }
        }

        if ($brandHasWork || $modelsAdded > 0) {
            $brandsTouched++;
        }
    }

    partsSyncJsonFromDatabase($pdo);

    return [
        'ok' => true,
        'brands' => count($brands),
        'brands_touched' => $brandsTouched,
        'models_added' => $modelsAdded,
        'models_skipped' => $modelsSkipped,
        'parts_added' => $partsAdded,
    ];
}

$pdo = db();
if ($pdo === null) {
    seedResponse('База не подключена', [
        'Проверьте backend/config.php: DB_HOST, DB_NAME, DB_USER, DB_PASS.',
        'Создайте базу autoline и выполните backend/schema_parts.sql в phpMyAdmin.',
        'После этого откройте: seed-parts.php?run=1',
    ], false);
}

$run = isset($_GET['run']) && $_GET['run'] === '1';
$reset = isset($_GET['reset']) && $_GET['reset'] === '1';
$force = isset($_GET['force']) && $_GET['force'] === '1';
$fromSql = !isset($_GET['from_sql']) || $_GET['from_sql'] === '1';

if (!$run) {
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Заполнение каталога</title>';
    echo '<style>body{font-family:Segoe UI,sans-serif;max-width:720px;margin:40px auto;padding:0 16px}';
    echo 'a,button{font-family:inherit}.btn{display:inline-block;padding:12px 18px;background:#f39200;color:#fff;text-decoration:none;border:0;border-radius:8px;font-weight:700}';
    echo '.muted{color:#666;line-height:1.6}</style></head><body>';
    echo '<h1>Заполнение каталога запчастей</h1>';
    echo '<p class="muted">Скрипт загрузит в базу все марки, модели и запчасти: BMW, Mercedes, Audi, Toyota, Lada и остальные.</p>';
    echo '<p><a class="btn" href="?run=1">Загрузить в базу</a></p>';
    echo '<p class="muted">В базе будет: 468 моделей и 2808 запчастей. Для BMW: 12 моделей и 72 запчасти.</p>';
    echo '<p class="muted">Альтернатива через phpMyAdmin: импортируйте файл <code>backend/seed_parts.sql</code></p>';
    echo '</body></html>';
    exit;
}

if ($fromSql) {
    $result = seedImportSqlFile($pdo);
    if (($result['ok'] ?? false) !== true) {
        seedResponse('Ошибка импорта SQL', [(string)($result['error'] ?? 'Неизвестная ошибка')], false);
    }
    seedResponse('Каталог загружен в базу', [
        'SQL-команд выполнено: ' . (int)($result['statements'] ?? 0),
        'Моделей в базе: ' . (int)($result['models'] ?? 0),
        'Запчастей в базе: ' . (int)($result['parts'] ?? 0),
        'BMW моделей: ' . (int)($result['bmw_models'] ?? 0),
        'BMW запчастей: ' . (int)($result['bmw_parts'] ?? 0),
        'JSON синхронизирован с базой.',
    ]);
}

$result = seedRunCatalog($pdo, $reset, $force);
if (($result['ok'] ?? false) !== true) {
    seedResponse('Ошибка заполнения', [(string)($result['error'] ?? 'Неизвестная ошибка')], false);
}

seedResponse('Каталог заполнен', [
    'Марок в каталоге: ' . (int)($result['brands'] ?? 0),
    'Марок обработано: ' . (int)($result['brands_touched'] ?? 0),
    'Моделей добавлено: ' . (int)($result['models_added'] ?? 0),
    'Моделей уже было: ' . (int)($result['models_skipped'] ?? 0),
    'Запчастей добавлено: ' . (int)($result['parts_added'] ?? 0),
    'JSON синхронизирован с базой.',
]);

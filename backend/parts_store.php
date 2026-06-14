<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function partsJsonPath(): string
{
    return __DIR__ . '/data/parts.json';
}

function partsLoadJsonFile(): array
{
    $path = partsJsonPath();
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function partsSaveJsonFile(array $data): bool
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    return file_put_contents(partsJsonPath(), $json, LOCK_EX) !== false;
}

function partsSlugify(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace(['ё'], ['е'], $value);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    return trim($value, '-');
}

function partsInferBrandFromModelSlug(string $modelSlug): string
{
    $known = [
        'lada-granta' => 'lada',
        'hyundai-solaris' => 'hyundai',
        'kia-rio' => 'kia',
        'toyota-corolla' => 'toyota',
    ];
    if (isset($known[$modelSlug])) {
        return $known[$modelSlug];
    }
    $pos = strpos($modelSlug, '-');
    if ($pos === false) {
        return $modelSlug;
    }
    return substr($modelSlug, 0, $pos);
}

function partsModelLabelFromSlug(string $slug): string
{
    $labels = [
        'lada-granta' => 'Lada Granta',
        'hyundai-solaris' => 'Hyundai Solaris',
        'kia-rio' => 'Kia Rio',
        'toyota-corolla' => 'Toyota Corolla',
    ];
    if (isset($labels[$slug])) {
        return $labels[$slug];
    }
    $parts = explode('-', $slug);
    return implode(' ', array_map(static function (string $p): string {
        return mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($p, 1, null, 'UTF-8');
    }, $parts));
}

function partsEnsureSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS part_models (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) NOT NULL,
            brand_slug VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_part_models_slug (slug),
            KEY idx_part_models_brand (brand_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS catalog_parts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            model_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            meta VARCHAR(255) NOT NULL DEFAULT "",
            price VARCHAR(64) NOT NULL,
            image_url VARCHAR(512) NOT NULL DEFAULT "",
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at INT UNSIGNED NOT NULL,
            updated_at INT UNSIGNED NOT NULL,
            KEY idx_catalog_parts_model (model_id, is_active),
            CONSTRAINT fk_catalog_parts_model
                FOREIGN KEY (model_id) REFERENCES part_models(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function partsMigrateFromJson(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM part_models');
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($row && (int)($row['c'] ?? 0) > 0) {
        return;
    }

    $json = partsLoadJsonFile();
    if ($json === []) {
        return;
    }

    $now = time();
    $insertModel = $pdo->prepare('
        INSERT INTO part_models (slug, brand_slug, name, sort_order, is_active, created_at)
        VALUES (:slug, :brand_slug, :name, :sort_order, 1, :created_at)
    ');
    $insertPart = $pdo->prepare('
        INSERT INTO catalog_parts (model_id, title, description, meta, price, image_url, sort_order, is_active, created_at, updated_at)
        VALUES (:model_id, :title, :description, :meta, :price, :image_url, :sort_order, 1, :created_at, :updated_at)
    ');

    $sortModel = 0;
    foreach ($json as $modelSlug => $items) {
        if (!is_string($modelSlug) || !is_array($items)) {
            continue;
        }
        $brandSlug = partsInferBrandFromModelSlug($modelSlug);
        $modelName = partsModelLabelFromSlug($modelSlug);
        $insertModel->execute([
            ':slug' => $modelSlug,
            ':brand_slug' => $brandSlug,
            ':name' => $modelName,
            ':sort_order' => $sortModel++,
            ':created_at' => $now,
        ]);
        $modelId = (int)$pdo->lastInsertId();
        $sortPart = 0;
        foreach ($items as $part) {
            if (!is_array($part)) {
                continue;
            }
            $title = trim((string)($part['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $insertPart->execute([
                ':model_id' => $modelId,
                ':title' => $title,
                ':description' => trim((string)($part['desc'] ?? '')),
                ':meta' => trim((string)($part['meta'] ?? '')),
                ':price' => trim((string)($part['price'] ?? '')),
                ':image_url' => trim((string)($part['image'] ?? '')),
                ':sort_order' => $sortPart++,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }
}

function partsRowToPublic(array $row): array
{
    return [
        'id' => (string)($row['public_id'] ?? $row['id'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'desc' => (string)($row['description'] ?? $row['desc'] ?? ''),
        'meta' => (string)($row['meta'] ?? ''),
        'price' => (string)($row['price'] ?? ''),
        'image' => (string)($row['image_url'] ?? $row['image'] ?? ''),
    ];
}

function partsFetchModels(PDO $pdo, ?string $brandSlug = null): array
{
    if ($brandSlug !== null && $brandSlug !== '') {
        $brandKey = str_replace('-', '', $brandSlug);
        $stmt = $pdo->prepare('
            SELECT id, slug, brand_slug, name, sort_order, is_active
            FROM part_models
            WHERE is_active = 1 AND REPLACE(brand_slug, "-", "") = :brand_key
            ORDER BY sort_order ASC, name ASC
        ');
        $stmt->execute([':brand_key' => $brandKey]);
    } else {
        $stmt = $pdo->query('
            SELECT id, slug, brand_slug, name, sort_order, is_active
            FROM part_models
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ');
    }
    if (!$stmt) {
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function partsFetchGrouped(PDO $pdo, ?string $brandSlug = null): array
{
    $models = partsFetchModels($pdo, $brandSlug);
    if ($models === []) {
        return [];
    }

    $ids = array_map(static function (array $m): int {
        return (int)$m['id'];
    }, $models);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.id, p.model_id, p.title, p.description, p.meta, p.price, p.image_url, p.sort_order
        FROM catalog_parts p
        WHERE p.is_active = 1 AND p.model_id IN ($placeholders)
        ORDER BY p.sort_order ASC, p.title ASC
    ");
    $stmt->execute($ids);
    $partRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($partRows)) {
        $partRows = [];
    }

    $byModelId = [];
    foreach ($partRows as $row) {
        $mid = (int)($row['model_id'] ?? 0);
        if (!isset($byModelId[$mid])) {
            $byModelId[$mid] = [];
        }
        $row['public_id'] = 'part_' . (string)($row['id'] ?? '');
        $byModelId[$mid][] = partsRowToPublic($row);
    }

    $grouped = [];
    foreach ($models as $model) {
        $slug = (string)$model['slug'];
        $grouped[$slug] = $byModelId[(int)$model['id']] ?? [];
    }
    return $grouped;
}

function partsStorageMode(): string
{
    $pdo = db();
    if ($pdo === null) {
        return 'json';
    }
    partsEnsureSchema($pdo);
    partsMigrateFromJson($pdo);
    return 'database';
}

function partsGetCatalog(?string $brandSlug = null): array
{
    $pdo = db();
    if ($pdo !== null) {
        partsEnsureSchema($pdo);
        partsMigrateFromJson($pdo);
        return partsFetchGrouped($pdo, $brandSlug);
    }
    $all = partsLoadJsonFile();
    if ($brandSlug === null || $brandSlug === '') {
        return $all;
    }
    $filtered = [];
    foreach ($all as $modelSlug => $items) {
        if (partsInferBrandFromModelSlug($modelSlug) === $brandSlug) {
            $filtered[$modelSlug] = $items;
        }
    }
    return $filtered;
}

function partsGetModelsList(?string $brandSlug = null): array
{
    $pdo = db();
    if ($pdo !== null) {
        partsEnsureSchema($pdo);
        partsMigrateFromJson($pdo);
        return partsFetchModels($pdo, $brandSlug);
    }
    $labels = [];
    foreach (array_keys(partsLoadJsonFile()) as $slug) {
        if ($brandSlug !== null && $brandSlug !== '' && partsInferBrandFromModelSlug($slug) !== $brandSlug) {
            continue;
        }
        $labels[] = [
            'slug' => $slug,
            'brand_slug' => partsInferBrandFromModelSlug($slug),
            'name' => partsModelLabelFromSlug($slug),
        ];
    }
    return $labels;
}

function partsLoadBrandsForSelect(): array
{
    $path = dirname(__DIR__) . '/assets/data/brands.json';
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['brands']) || !is_array($decoded['brands'])) {
        return [];
    }
    return $decoded['brands'];
}

function partsAddModel(PDO $pdo, string $brandSlug, string $name, string $slug = ''): array
{
    $brandSlug = partsSlugify($brandSlug);
    $name = trim($name);
    if ($brandSlug === '' || $name === '') {
        return ['ok' => false, 'error' => 'Укажите марку и название модели.'];
    }
    if ($slug === '') {
        $slug = partsSlugify($brandSlug . '-' . $name);
    } else {
        $slug = partsSlugify($slug);
    }
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Не удалось сформировать код модели.'];
    }

    $check = $pdo->prepare('SELECT id FROM part_models WHERE slug = :slug LIMIT 1');
    $check->execute([':slug' => $slug]);
    if ($check->fetch()) {
        return ['ok' => false, 'error' => 'Модель с таким кодом уже есть.'];
    }

    $sortStmt = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM part_models');
    $nextSort = 0;
    if ($sortStmt) {
        $sortRow = $sortStmt->fetch(PDO::FETCH_ASSOC);
        $nextSort = (int)($sortRow['next_sort'] ?? 0);
    }

    $stmt = $pdo->prepare('
        INSERT INTO part_models (slug, brand_slug, name, sort_order, is_active, created_at)
        VALUES (:slug, :brand_slug, :name, :sort_order, 1, :created_at)
    ');
    $stmt->execute([
        ':slug' => $slug,
        ':brand_slug' => $brandSlug,
        ':name' => $name,
        ':sort_order' => $nextSort,
        ':created_at' => time(),
    ]);

    return ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'slug' => $slug];
}

function partsDeleteModel(PDO $pdo, int $modelId): bool
{
    $stmt = $pdo->prepare('DELETE FROM part_models WHERE id = :id LIMIT 1');
    return $stmt->execute([':id' => $modelId]);
}

function partsUpdateModel(PDO $pdo, int $modelId, string $brandSlug, string $modelName): array
{
    if ($modelId <= 0) {
        return ['ok' => false, 'error' => 'Некорректная модель.'];
    }
    $brandSlug = partsSlugify($brandSlug);
    $modelName = trim($modelName);
    if ($brandSlug === '' || $modelName === '') {
        return ['ok' => false, 'error' => 'Укажите марку и название модели.'];
    }

    $stmt = $pdo->prepare('
        UPDATE part_models
        SET brand_slug = :brand_slug, name = :name
        WHERE id = :id LIMIT 1
    ');
    $stmt->execute([
        ':brand_slug' => $brandSlug,
        ':name' => $modelName,
        ':id' => $modelId,
    ]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM part_models WHERE id = :id LIMIT 1');
        $check->execute([':id' => $modelId]);
        if (!$check->fetch()) {
            return ['ok' => false, 'error' => 'Модель не найдена.'];
        }
    }

    return ['ok' => true];
}

function partsBrandLabelMap(): array
{
    $map = [];
    foreach (partsLoadBrandsForSelect() as $brand) {
        $slug = (string)($brand['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $map[$slug] = (string)($brand['name'] ?? $slug);
    }
    return $map;
}

function partsResolveModelId(PDO $pdo, string $modelKey): ?int
{
    $modelKey = trim($modelKey);
    if ($modelKey === '') {
        return null;
    }
    if (ctype_digit($modelKey)) {
        return (int)$modelKey;
    }
    $stmt = $pdo->prepare('SELECT id FROM part_models WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $modelKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return (int)$row['id'];
}

function partsAddPart(PDO $pdo, string $modelKey, array $fields): array
{
    $modelId = partsResolveModelId($pdo, $modelKey);
    if ($modelId === null) {
        return ['ok' => false, 'error' => 'Модель не найдена.'];
    }
    $title = trim((string)($fields['title'] ?? ''));
    $price = trim((string)($fields['price'] ?? ''));
    if ($title === '' || $price === '') {
        return ['ok' => false, 'error' => 'Название и цена обязательны.'];
    }

    $now = time();
    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM catalog_parts WHERE model_id = :model_id');
    $sortStmt->execute([':model_id' => $modelId]);
    $sortRow = $sortStmt->fetch(PDO::FETCH_ASSOC);
    $nextSort = (int)($sortRow['next_sort'] ?? 0);

    $stmt = $pdo->prepare('
        INSERT INTO catalog_parts (model_id, title, description, meta, price, image_url, sort_order, is_active, created_at, updated_at)
        VALUES (:model_id, :title, :description, :meta, :price, :image_url, :sort_order, 1, :created_at, :updated_at)
    ');
    $stmt->execute([
        ':model_id' => $modelId,
        ':title' => $title,
        ':description' => trim((string)($fields['desc'] ?? '')),
        ':meta' => trim((string)($fields['meta'] ?? '')),
        ':price' => $price,
        ':image_url' => trim((string)($fields['image'] ?? '')),
        ':sort_order' => $nextSort,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
}

function partsUpdatePart(PDO $pdo, int $partId, array $fields): array
{
    if ($partId <= 0) {
        return ['ok' => false, 'error' => 'Некорректная запчасть.'];
    }
    $title = trim((string)($fields['title'] ?? ''));
    $price = trim((string)($fields['price'] ?? ''));
    if ($title === '' || $price === '') {
        return ['ok' => false, 'error' => 'Название и цена обязательны.'];
    }

    $modelId = null;
    $modelKey = trim((string)($fields['model_key'] ?? ''));
    if ($modelKey !== '') {
        $modelId = partsResolveModelId($pdo, $modelKey);
        if ($modelId === null) {
            return ['ok' => false, 'error' => 'Модель не найдена.'];
        }
    }

    $now = time();
    if ($modelId !== null) {
        $stmt = $pdo->prepare('
            UPDATE catalog_parts
            SET model_id = :model_id, title = :title, description = :description, meta = :meta,
                price = :price, image_url = :image_url, updated_at = :updated_at
            WHERE id = :id LIMIT 1
        ');
        $stmt->execute([
            ':model_id' => $modelId,
            ':title' => $title,
            ':description' => trim((string)($fields['desc'] ?? '')),
            ':meta' => trim((string)($fields['meta'] ?? '')),
            ':price' => $price,
            ':image_url' => trim((string)($fields['image'] ?? '')),
            ':updated_at' => $now,
            ':id' => $partId,
        ]);
    } else {
        $stmt = $pdo->prepare('
            UPDATE catalog_parts
            SET title = :title, description = :description, meta = :meta,
                price = :price, image_url = :image_url, updated_at = :updated_at
            WHERE id = :id LIMIT 1
        ');
        $stmt->execute([
            ':title' => $title,
            ':description' => trim((string)($fields['desc'] ?? '')),
            ':meta' => trim((string)($fields['meta'] ?? '')),
            ':price' => $price,
            ':image_url' => trim((string)($fields['image'] ?? '')),
            ':updated_at' => $now,
            ':id' => $partId,
        ]);
    }

    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Запчасть не найдена.'];
    }
    return ['ok' => true];
}

function partsDeletePart(PDO $pdo, int $partId): bool
{
    $stmt = $pdo->prepare('DELETE FROM catalog_parts WHERE id = :id LIMIT 1');
    return $stmt->execute([':id' => $partId]);
}

function partsAdminList(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT p.id, p.model_id, p.title, p.description, p.meta, p.price, p.image_url,
               m.slug AS model_slug, m.name AS model_name, m.brand_slug
        FROM catalog_parts p
        INNER JOIN part_models m ON m.id = p.model_id
        ORDER BY m.sort_order ASC, m.name ASC, p.sort_order ASC, p.title ASC
    ');
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    return is_array($rows) ? $rows : [];
}

function partsSyncJsonFromDatabase(PDO $pdo): void
{
    partsSaveJsonFile(partsFetchGrouped($pdo, null));
}

function partsJsonAddPart(string $modelKey, array $fields): array
{
    $parts = partsLoadJsonFile();
    if (!isset($parts[$modelKey]) || !is_array($parts[$modelKey])) {
        $parts[$modelKey] = [];
    }
    $title = trim((string)($fields['title'] ?? ''));
    $price = trim((string)($fields['price'] ?? ''));
    if ($title === '' || $price === '') {
        return ['ok' => false, 'error' => 'Название и цена обязательны.'];
    }
    $parts[$modelKey][] = [
        'id' => uniqid('part_', true),
        'title' => $title,
        'desc' => trim((string)($fields['desc'] ?? '')),
        'meta' => trim((string)($fields['meta'] ?? '')),
        'price' => $price,
        'image' => trim((string)($fields['image'] ?? '')),
    ];
    if (!partsSaveJsonFile($parts)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл.'];
    }
    return ['ok' => true];
}

function partsJsonDeletePart(string $modelKey, string $partId): bool
{
    $parts = partsLoadJsonFile();
    if (!isset($parts[$modelKey]) || !is_array($parts[$modelKey])) {
        return false;
    }
    $parts[$modelKey] = array_values(array_filter($parts[$modelKey], static function ($item) use ($partId): bool {
        return (string)($item['id'] ?? '') !== $partId;
    }));
    return partsSaveJsonFile($parts);
}

function partsJsonUpdatePart(string $modelKey, string $partId, array $fields): array
{
    $parts = partsLoadJsonFile();
    if (!isset($parts[$modelKey]) || !is_array($parts[$modelKey])) {
        return ['ok' => false, 'error' => 'Модель не найдена.'];
    }
    $found = false;
    foreach ($parts[$modelKey] as &$item) {
        if ((string)($item['id'] ?? '') !== $partId) {
            continue;
        }
        $title = trim((string)($fields['title'] ?? ''));
        $price = trim((string)($fields['price'] ?? ''));
        if ($title === '' || $price === '') {
            return ['ok' => false, 'error' => 'Название и цена обязательны.'];
        }
        $item['title'] = $title;
        $item['desc'] = trim((string)($fields['desc'] ?? ''));
        $item['meta'] = trim((string)($fields['meta'] ?? ''));
        $item['price'] = $price;
        $item['image'] = trim((string)($fields['image'] ?? ''));
        $found = true;
        break;
    }
    unset($item);
    if (!$found) {
        return ['ok' => false, 'error' => 'Запчасть не найдена.'];
    }
    if (!partsSaveJsonFile($parts)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл.'];
    }
    return ['ok' => true];
}

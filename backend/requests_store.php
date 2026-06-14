<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const REQUEST_STATUS_NEW = 'new';
const REQUEST_STATUS_PROCESSING = 'processing';
const REQUEST_STATUS_COMPLETED = 'completed';
const REQUEST_STATUS_CANCELLED = 'cancelled';

function requestsAllowedStatuses(): array
{
    return [
        REQUEST_STATUS_NEW,
        REQUEST_STATUS_PROCESSING,
        REQUEST_STATUS_COMPLETED,
        REQUEST_STATUS_CANCELLED,
    ];
}

function requestsJsonPath(): string
{
    return __DIR__ . '/data/requests.json';
}

function requestsLoadJsonRaw(): array
{
    $path = requestsJsonPath();
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

function requestsSaveJsonRaw(array $requests): bool
{
    $json = json_encode($requests, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    return file_put_contents(requestsJsonPath(), $json, LOCK_EX) !== false;
}

function requestsNormalizeStatus(?string $status): string
{
    $status = trim((string)$status);
    return in_array($status, requestsAllowedStatuses(), true) ? $status : REQUEST_STATUS_NEW;
}

function requestStatusLabel(string $status): string
{
    switch (requestsNormalizeStatus($status)) {
        case REQUEST_STATUS_PROCESSING:
            return 'В работе';
        case REQUEST_STATUS_COMPLETED:
            return 'Выполнена';
        case REQUEST_STATUS_CANCELLED:
            return 'Отменена';
        default:
            return 'Принята';
    }
}

function requestStatusCssClass(string $status): string
{
    return 'request-status request-status-' . requestsNormalizeStatus($status);
}

function requestsNormalizeRow(array $row): array
{
    $row['status'] = requestsNormalizeStatus($row['status'] ?? REQUEST_STATUS_NEW);
    $row['status_note'] = trim((string)($row['status_note'] ?? ''));
    $row['status_updated_at'] = (int)($row['status_updated_at'] ?? 0);
    return $row;
}

function requestsTableHasColumn(PDO $pdo, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM requests LIKE :column_name');
        $stmt->execute([':column_name' => $column]);
        $row = $stmt->fetch();
        return is_array($row) && count($row) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function requestsEnsureStatusSchema(PDO $pdo): void
{
    if (!requestsTableHasColumn($pdo, 'status')) {
        $pdo->exec("ALTER TABLE requests ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'new' AFTER comment");
    }
    if (!requestsTableHasColumn($pdo, 'status_note')) {
        $pdo->exec('ALTER TABLE requests ADD COLUMN status_note TEXT NULL AFTER status');
    }
    if (!requestsTableHasColumn($pdo, 'status_updated_at')) {
        $pdo->exec('ALTER TABLE requests ADD COLUMN status_updated_at INT UNSIGNED NOT NULL DEFAULT 0 AFTER status_note');
    }
}

function requestsDbRowToAdmin(array $row): array
{
    $dbId = (int)($row['id'] ?? 0);
    return requestsNormalizeRow([
        'id' => 'db_' . $dbId,
        'db_id' => $dbId,
        'ts' => (int)($row['ts'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'phone' => (string)($row['phone'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'service' => (string)($row['service'] ?? ''),
        'comment' => (string)($row['comment'] ?? ''),
        'status' => $row['status'] ?? REQUEST_STATUS_NEW,
        'status_note' => $row['status_note'] ?? '',
        'status_updated_at' => (int)($row['status_updated_at'] ?? 0),
    ]);
}

function requestsMergeJsonDb(array $jsonItem, array $dbRow): array
{
    $merged = $jsonItem;
    $merged['db_id'] = (int)($dbRow['id'] ?? $jsonItem['db_id'] ?? 0);
    $merged['status'] = $dbRow['status'] ?? $jsonItem['status'] ?? REQUEST_STATUS_NEW;
    $merged['status_note'] = $dbRow['status_note'] ?? $jsonItem['status_note'] ?? '';
    $merged['status_updated_at'] = (int)($dbRow['status_updated_at'] ?? $jsonItem['status_updated_at'] ?? 0);
    return requestsNormalizeRow($merged);
}

function requestsLoadFromDatabase(PDO $pdo): array
{
    requestsEnsureStatusSchema($pdo);
    $hasEmail = requestsTableHasColumn($pdo, 'email');
    $cols = $hasEmail
        ? 'id, ts, name, phone, email, service, comment, status, status_note, status_updated_at'
        : 'id, ts, name, phone, service, comment, status, status_note, status_updated_at';
    $stmt = $pdo->query("SELECT {$cols} FROM requests ORDER BY ts DESC");
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        if ($hasEmail) {
            $out[] = requestsDbRowToAdmin($row);
        } else {
            $row['email'] = '';
            $out[] = requestsDbRowToAdmin($row);
        }
    }
    return $out;
}

function requestsLoadForAdmin(?PDO $pdo): array
{
    if ($pdo !== null) {
        return requestsLoadFromDatabase($pdo);
    }

    $jsonItems = requestsLoadJsonRaw();
    $out = [];
    foreach (array_reverse($jsonItems) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $out[] = requestsNormalizeRow($item);
    }
    return $out;
}

function requestsFindJsonIndex(array $requests, string $requestId): ?int
{
    foreach ($requests as $i => $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['id'] ?? '') === $requestId) {
            return $i;
        }
    }
    return null;
}

function requestsUpdateStatus(?PDO $pdo, string $requestId, string $status, string $statusNote = ''): array
{
    $status = requestsNormalizeStatus($status);
    if (!in_array($status, requestsAllowedStatuses(), true)) {
        return ['ok' => false, 'error' => 'Некорректный статус.'];
    }

    $statusNote = trim($statusNote);
    if (mb_strlen($statusNote) > 500) {
        return ['ok' => false, 'error' => 'Комментарий к статусу слишком длинный.'];
    }

    $now = time();
    $requests = requestsLoadJsonRaw();
    $index = requestsFindJsonIndex($requests, $requestId);

    if ($requestId !== '' && strpos($requestId, 'db_') === 0) {
        $dbId = (int)substr($requestId, 3);
        if ($pdo !== null && $dbId > 0) {
            requestsEnsureStatusSchema($pdo);
            $stmt = $pdo->prepare('
                UPDATE requests
                SET status = :status, status_note = :status_note, status_updated_at = :status_updated_at
                WHERE id = :id LIMIT 1
            ');
            $stmt->execute([
                ':status' => $status,
                ':status_note' => $statusNote,
                ':status_updated_at' => $now,
                ':id' => $dbId,
            ]);
            if ($stmt->rowCount() > 0) {
                return ['ok' => true];
            }
        }
    }

    if ($index === null) {
        return ['ok' => false, 'error' => 'Заявка не найдена.'];
    }

    $requests[$index]['status'] = $status;
    $requests[$index]['status_note'] = $statusNote;
    $requests[$index]['status_updated_at'] = $now;

    $dbId = isset($requests[$index]['db_id']) ? (int)$requests[$index]['db_id'] : 0;
    if ($pdo !== null && $dbId > 0) {
        try {
            requestsEnsureStatusSchema($pdo);
            $stmt = $pdo->prepare('
                UPDATE requests
                SET status = :status, status_note = :status_note, status_updated_at = :status_updated_at
                WHERE id = :id LIMIT 1
            ');
            $stmt->execute([
                ':status' => $status,
                ':status_note' => $statusNote,
                ':status_updated_at' => $now,
                ':id' => $dbId,
            ]);
        } catch (Throwable $e) {
        }
    }

    if (!requestsSaveJsonRaw($requests)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить статус.'];
    }

    return ['ok' => true];
}

function requestsCreateDefaults(): array
{
    return [
        'status' => REQUEST_STATUS_NEW,
        'status_note' => '',
        'status_updated_at' => time(),
    ];
}

<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/requests_store.php';
require_once __DIR__ . '/users_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$service = trim((string)($_POST['service'] ?? ''));
$comment = trim((string)($_POST['comment'] ?? ''));

function normalizePhone(string $value): string
{
    return usersNormalizePhone($value);
}

function requestHasEmailColumn(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM requests LIKE :column_name');
        $stmt->execute([':column_name' => 'email']);
        $row = $stmt->fetch();
        return is_array($row) && count($row) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// Если пользователь залогинен — подставляем его данные из сессии,
// чтобы не требовать повторно вводить имя/телефон.
$hasSession = isset($_SESSION['user_id'], $_SESSION['user_phone'], $_SESSION['user_name'])
    && is_numeric($_SESSION['user_id']);
if ($hasSession) {
    $name = trim((string)($_SESSION['user_name'] ?? ''));
    $phone = trim((string)($_SESSION['user_phone'] ?? ''));
}

// Простая валидация для демо/учебного проекта
if ($service === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Услуга обязательна'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Имя и телефон обязательны'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!$hasSession) {
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Введите корректный email'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email введен некорректно'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!usersPhoneValid($phone)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Телефон введен некорректно'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dataFile = __DIR__ . '/data/requests.json';

$requests = [];
if (file_exists($dataFile)) {
    $raw = file_get_contents($dataFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $requests = $decoded;
        }
    }
}

if (!is_array($requests)) {
    $requests = [];
}

$id = uniqid('req_', true);
$dbId = null;
$statusDefaults = requestsCreateDefaults();

$pdo = db();
$userId = null;
$autoRegistered = false;
if ($pdo !== null && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
}

$phoneNorm = normalizePhone($phone);

if ($pdo !== null && $userId === null) {
    $ensured = usersEnsureFromRequest($pdo, $name, $phoneNorm, $email);
    if ($ensured !== null) {
        $userId = (int)$ensured['user_id'];
        $autoRegistered = (bool)$ensured['created'];
        $name = (string)$ensured['name'];
        $phoneNorm = normalizePhone((string)$ensured['phone']);
    }
}

if ($pdo !== null) {
    try {
        requestsEnsureStatusSchema($pdo);
        if (requestHasEmailColumn($pdo)) {
            $stmt = $pdo->prepare('
                INSERT INTO requests (user_id, ts, name, phone, email, service, comment, status, status_note, status_updated_at)
                VALUES (:user_id, :ts, :name, :phone, :email, :service, :comment, :status, :status_note, :status_updated_at)
            ');
            $stmt->execute([
                ':user_id' => $userId,
                ':ts' => time(),
                ':name' => $name,
                ':phone' => $phoneNorm,
                ':email' => $email,
                ':service' => $service,
                ':comment' => $comment,
                ':status' => $statusDefaults['status'],
                ':status_note' => $statusDefaults['status_note'],
                ':status_updated_at' => $statusDefaults['status_updated_at'],
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO requests (user_id, ts, name, phone, service, comment, status, status_note, status_updated_at)
                VALUES (:user_id, :ts, :name, :phone, :service, :comment, :status, :status_note, :status_updated_at)
            ');
            $stmt->execute([
                ':user_id' => $userId,
                ':ts' => time(),
                ':name' => $name,
                ':phone' => $phoneNorm,
                ':service' => $service,
                ':comment' => $comment,
                ':status' => $statusDefaults['status'],
                ':status_note' => $statusDefaults['status_note'],
                ':status_updated_at' => $statusDefaults['status_updated_at'],
            ]);
        }
        $dbId = (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        $dbId = null;
    }
}

function submitSuccessPayload(string $id, array $statusDefaults, bool $autoRegistered): array
{
    $payload = [
        'ok' => true,
        'id' => $id,
        'status' => $statusDefaults['status'],
        'status_label' => requestStatusLabel($statusDefaults['status']),
        'loggedIn' => isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']),
        'autoRegistered' => $autoRegistered,
    ];
    if ($payload['loggedIn']) {
        $payload['user'] = [
            'name' => (string)($_SESSION['user_name'] ?? ''),
            'phone' => (string)($_SESSION['user_phone'] ?? ''),
        ];
    }
    return $payload;
}

if ($pdo !== null && $dbId !== null && $dbId > 0) {
    echo json_encode(submitSuccessPayload('db_' . $dbId, $statusDefaults, $autoRegistered), JSON_UNESCAPED_UNICODE);
    exit;
}

$requests[] = array_merge([
    'id' => $id,
    'ts' => time(),
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'service' => $service,
    'comment' => $comment,
    'db_id' => $dbId,
], $statusDefaults);

$json = json_encode($requests, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Не удалось сформировать данные'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (file_put_contents($dataFile, $json, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить заявку'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(submitSuccessPayload($id, $statusDefaults, $autoRegistered), JSON_UNESCAPED_UNICODE);
exit;


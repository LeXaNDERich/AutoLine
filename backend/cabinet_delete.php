<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

function normalizePhone(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = substr($phone, 0, 1) === '+';
    $digits = preg_replace('/\D+/', '', $phone);
    $digits = $digits ?? '';
    return $hasPlus ? ('+' . $digits) : $digits;
}

$pdo = db();
$requestsFile = __DIR__ . '/data/requests.json';

if (!isset($_SESSION['user_phone'])) {
    header('Location: auth.php');
    exit;
}

if (!isAdminSession()) {
    http_response_code(403);
    header('Location: cabinet.php');
    exit;
}

$userPhone = normalizePhone((string)$_SESSION['user_phone']);
$requestId = trim((string)($_POST['request_id'] ?? ''));

if ($requestId === '' || $pdo === null) {
    header('Location: cabinet.php');
    exit;
}

// Удаляем только по ID И телефону владельца (чтобы другой пользователь не смог удалить чужую заявку).
$stmt = $pdo->prepare('DELETE FROM requests WHERE id = :id AND phone = :phone');
$stmt->execute([':id' => (int)$requestId, ':phone' => $userPhone]);

// Параллельно чистим requests.json (для совместимости с текущей админкой).
if (file_exists($requestsFile)) {
    $raw = file_get_contents($requestsFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $decoded = array_values(array_filter($decoded, static function ($item) use ($requestId): bool {
                return ((string)($item['id'] ?? '')) !== (string)$requestId;
            }));
            $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json !== false) {
                file_put_contents($requestsFile, $json, LOCK_EX);
            }
        }
    }
}

header('Location: cabinet.php');
exit;


<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = db();
$err = dbLastError();
$dbName = defined('DB_NAME') ? (string)DB_NAME : '';
$dbUser = defined('DB_USER') ? (string)DB_USER : '';
$dbHost = defined('DB_HOST') ? (string)DB_HOST : '';
$tables = [];

if ($pdo !== null) {
    $stmt = $pdo->query('SHOW TABLES');
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            if (isset($row[0])) {
                $tables[] = (string)$row[0];
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Проверка БД | AutoLine</title>
    <style>
        body { font-family: Segoe UI, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 16px; }
        .ok { color: #0f7a3f; }
        .bad { color: #b42318; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Проверка подключения к БД</h1>
    <p>Хост: <code><?= htmlspecialchars($dbHost, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p>База: <code><?= htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p>Пользователь: <code><?= htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8') ?></code></p>
    <?php if ($pdo !== null): ?>
        <p class="ok"><strong>Подключение успешно.</strong></p>
        <p>Таблицы:</p>
        <ul>
            <?php foreach ($tables as $table): ?>
                <li><code><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></code></li>
            <?php endforeach; ?>
        </ul>
        <p><a href="auth.php">Перейти к входу</a></p>
    <?php else: ?>
        <p class="bad"><strong>Подключение не удалось.</strong></p>
        <p><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
        <p>Проверьте <code>backend/config.php</code> на сервере: логин, пароль и имя базы из панели Reg.ru.</p>
    <?php endif; ?>
    <p>После проверки удалите файл <code>backend/db_check.php</code> с сервера.</p>
</body>
</html>

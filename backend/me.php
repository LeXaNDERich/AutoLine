<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$loggedIn = isset($_SESSION['user_id'], $_SESSION['user_phone'], $_SESSION['user_name'])
    && is_numeric($_SESSION['user_id']);

if (!$loggedIn) {
    echo json_encode(['loggedIn' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'loggedIn' => true,
    'id' => (int)$_SESSION['user_id'],
    'name' => (string)$_SESSION['user_name'],
    'phone' => (string)$_SESSION['user_phone'],
    'isAdmin' => isAdminSession(),
], JSON_UNESCAPED_UNICODE);
exit;

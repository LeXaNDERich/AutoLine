<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}

function dbLastError(): string
{
    return isset($GLOBALS['AUTOLINE_DB_LAST_ERROR']) ? (string)$GLOBALS['AUTOLINE_DB_LAST_ERROR'] : '';
}

function dbSetLastError(string $message): void
{
    $GLOBALS['AUTOLINE_DB_LAST_ERROR'] = $message;
}

function dbHostsToTry(): array
{
    $primary = defined('DB_HOST') ? trim((string)DB_HOST) : '';
    if ($primary === '') {
        return [];
    }
    $hosts = [$primary];
    if ($primary === 'localhost') {
        $hosts[] = '127.0.0.1';
    } elseif ($primary === '127.0.0.1') {
        $hosts[] = 'localhost';
    }
    return array_values(array_unique($hosts));
}

function normalizePhoneValue(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = substr($phone, 0, 1) === '+';
    $digits = preg_replace('/\D+/', '', $phone);
    $digits = $digits ?? '';
    return $hasPlus ? ('+' . $digits) : $digits;
}

function db(): ?PDO
{
    $name = defined('DB_NAME') ? trim((string)DB_NAME) : '';
    $user = defined('DB_USER') ? trim((string)DB_USER) : '';
    $pass = defined('DB_PASS') ? (string)DB_PASS : '';

    if ($name === '' || $user === '') {
        dbSetLastError('Не заполнены DB_NAME или DB_USER в config.php');
        return null;
    }

    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_mysql')) {
        dbSetLastError('На хостинге не включено расширение pdo_mysql');
        return null;
    }

    $charset = defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4';
    $port = defined('DB_PORT') ? (int)DB_PORT : 0;
    $errors = [];

    foreach (dbHostsToTry() as $host) {
        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        if ($port > 0) {
            $dsn .= ";port={$port}";
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            dbSetLastError('');
            return $pdo;
        } catch (PDOException $e) {
            $errors[] = $host . ': ' . $e->getMessage();
        }
    }

    dbSetLastError($errors !== [] ? implode(' | ', $errors) : 'Не удалось подключиться к MySQL');
    return null;
}

function serviceLabel(string $service): string
{
    switch ($service) {
        case 'order':
            return 'Автомобиль под заказ';
        case 'maintenance':
            return 'Техническое обслуживание';
        case 'repair':
            return 'Ремонт';
        case 'parts':
            return 'Подбор автозапчастей';
        default:
            return $service;
    }
}

function isAdminPhone(string $phone): bool
{
    if (!defined('ADMIN_PHONES') || !is_array(ADMIN_PHONES)) {
        return false;
    }
    $needle = normalizePhoneValue($phone);
    foreach (ADMIN_PHONES as $adminPhone) {
        if (!is_string($adminPhone)) {
            continue;
        }
        if (normalizePhoneValue($adminPhone) === $needle) {
            return true;
        }
    }
    return false;
}

function isAdminSession(): bool
{
    if (!isset($_SESSION['user_phone'])) {
        return false;
    }
    return isAdminPhone((string)$_SESSION['user_phone']);
}


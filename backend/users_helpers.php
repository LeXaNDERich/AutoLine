<?php
declare(strict_types=1);

function usersNormalizePhone(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = substr($phone, 0, 1) === '+';
    $digits = preg_replace('/\D+/', '', $phone);
    $digits = $digits ?? '';
    if ($digits !== '' && substr($digits, 0, 1) === '8' && strlen($digits) === 11) {
        $digits = '7' . substr($digits, 1);
    }
    if ($digits !== '' && substr($digits, 0, 1) !== '7' && strlen($digits) === 10) {
        $digits = '7' . $digits;
    }
    return $hasPlus || $digits !== '' ? ('+' . $digits) : '';
}

function usersPhoneValid(string $phone): bool
{
    $norm = usersNormalizePhone($phone);
    $digits = preg_replace('/\D+/', '', $norm);
    $digits = $digits ?? '';
    return strlen($digits) === 11 && substr($digits, 0, 1) === '7';
}

function usersHasEmailColumn(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM users LIKE :column_name');
        $stmt->execute([':column_name' => 'email']);
        $row = $stmt->fetch();
        return is_array($row) && count($row) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function usersEnsureEmailColumn(PDO $pdo): void
{
    if (usersHasEmailColumn($pdo)) {
        return;
    }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER phone");
    } catch (Throwable $e) {
    }
}

function usersLoginSession(array $user): void
{
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_phone'] = (string)$user['phone'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_email'] = isset($user['email']) ? (string)$user['email'] : '';
}

function usersFindByPhone(PDO $pdo, string $phoneNorm): ?array
{
    $stmt = $pdo->prepare('SELECT id, phone, name, email, password_hash FROM users WHERE phone = :phone LIMIT 1');
    $stmt->execute([':phone' => $phoneNorm]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function usersEnsureFromRequest(PDO $pdo, string $name, string $phoneNorm, string $email): ?array
{
    usersEnsureEmailColumn($pdo);

    $user = usersFindByPhone($pdo, $phoneNorm);
    $created = false;

    if ($user !== null) {
        if ($name !== '' && $name !== (string)$user['name']) {
            $stmt = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
            $stmt->execute([':name' => $name, ':id' => (int)$user['id']]);
            $user['name'] = $name;
        }
        if ($email !== '' && usersHasEmailColumn($pdo) && empty($user['email'])) {
            $emailLower = strtolower($email);
            try {
                $stmt = $pdo->prepare('UPDATE users SET email = :email WHERE id = :id');
                $stmt->execute([':email' => $emailLower, ':id' => (int)$user['id']]);
                $user['email'] = $emailLower;
            } catch (PDOException $e) {
            }
        }
    } else {
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $created = true;
        try {
            if (usersHasEmailColumn($pdo) && $email !== '') {
                $stmt = $pdo->prepare('INSERT INTO users (phone, email, name, password_hash, created_at) VALUES (:phone, :email, :name, :hash, :ts)');
                $stmt->execute([
                    ':phone' => $phoneNorm,
                    ':email' => strtolower($email),
                    ':name' => $name,
                    ':hash' => $hash,
                    ':ts' => time(),
                ]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (phone, name, password_hash, created_at) VALUES (:phone, :name, :hash, :ts)');
                $stmt->execute([
                    ':phone' => $phoneNorm,
                    ':name' => $name,
                    ':hash' => $hash,
                    ':ts' => time(),
                ]);
            }
            $user = usersFindByPhone($pdo, $phoneNorm);
        } catch (PDOException $e) {
            $user = usersFindByPhone($pdo, $phoneNorm);
            $created = false;
            if ($user === null) {
                return null;
            }
        }
    }

    if ($user === null) {
        return null;
    }

    usersLoginSession($user);

    return [
        'user_id' => (int)$user['id'],
        'created' => $created,
        'name' => (string)$user['name'],
        'phone' => (string)$user['phone'],
    ];
}

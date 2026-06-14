<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/users_helpers.php';
require_once __DIR__ . '/partials/account-layout.php';

header('Content-Type: text/html; charset=utf-8');

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalizePhone(string $phone): string
{
    return usersNormalizePhone($phone);
}

function phoneValid(string $phone): bool
{
    return usersPhoneValid($phone);
}

function emailValid(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

$pdo = db();
$flash = '';
$flashType = 'ok';
$showRegister = false;

// Если DB не настроена — авторизацию отключаем, но сайт не падает
if ($pdo === null) {
    $flash = 'Не удалось подключиться к базе данных. Проверьте backend/config.php на сервере.';
    if (defined('DB_DEBUG') && DB_DEBUG && dbLastError() !== '') {
        $flash .= ' ' . dbLastError();
    }
    $flashType = 'error';
} else {
    // Пытаемся автоматически подготовить колонку для входа/регистрации по email.
    usersEnsureEmailColumn($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'register') {
        $showRegister = true;
        $phoneRaw = trim((string)($_POST['phone'] ?? ''));
        $emailRaw = trim((string)($_POST['email'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        if ($name === '' || !phoneValid($phoneRaw)) {
            $flash = 'Введите имя и телефон полностью: +7 (XXX) XXX-XX-XX.';
            $flashType = 'error';
        } elseif ($emailRaw === '' || !emailValid($emailRaw)) {
            $flash = 'Введите корректный email.';
            $flashType = 'error';
        } elseif ($password === '' || $password !== $password2 || mb_strlen($password) < 6) {
            $flash = 'Пароли должны совпадать и быть не короче 6 символов.';
            $flashType = 'error';
        } else {
            $phone = normalizePhone($phoneRaw);
            $email = strtolower($emailRaw);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                if (usersHasEmailColumn($pdo)) {
                    $stmt = $pdo->prepare('INSERT INTO users (phone, email, name, password_hash) VALUES (:phone, :email, :name, :hash)');
                    $stmt->execute([':phone' => $phone, ':email' => $email, ':name' => $name, ':hash' => $hash]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (phone, name, password_hash) VALUES (:phone, :name, :hash)');
                    $stmt->execute([':phone' => $phone, ':name' => $name, ':hash' => $hash]);
                }
                $flash = 'Регистрация прошла успешно. Можно войти.';
                $flashType = 'ok';
                $showRegister = false;
            } catch (PDOException $e) {
                $flash = 'Этот телефон или email уже зарегистрирован.';
                $flashType = 'error';
            }
        }
    } elseif ($action === 'login') {
        $showRegister = false;
        $phoneRaw = trim((string)($_POST['phone'] ?? ''));
        $emailRaw = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $hasPhone = $phoneRaw !== '';
        $hasEmail = $emailRaw !== '';

        if ($password === '' || (!$hasPhone && !$hasEmail)) {
            $flash = 'Введите телефон или email, а также пароль.';
            $flashType = 'error';
        } else {
            $user = false;
            if ($hasEmail) {
                if (!emailValid($emailRaw)) {
                    $flash = 'Введите корректный email.';
                    $flashType = 'error';
                } elseif (!usersHasEmailColumn($pdo)) {
                    $flash = 'В базе пока нет колонки email для входа по email.';
                    $flashType = 'error';
                } else {
                    $email = strtolower($emailRaw);
                    $stmt = $pdo->prepare('SELECT id, password_hash, name, phone, email FROM users WHERE email = :email LIMIT 1');
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch();
                }
            } else {
                if (!phoneValid($phoneRaw)) {
                    $flash = 'Введите телефон полностью: +7 (XXX) XXX-XX-XX.';
                    $flashType = 'error';
                } else {
                    $phone = normalizePhone($phoneRaw);
                    $stmt = $pdo->prepare('SELECT id, password_hash, name, phone FROM users WHERE phone = :phone LIMIT 1');
                    $stmt->execute([':phone' => $phone]);
                    $user = $stmt->fetch();
                }
            }

            if ($flash === '' && (!$user || !password_verify($password, (string)$user['password_hash']))) {
                $flash = 'Неверный логин или пароль.';
                $flashType = 'error';
            } elseif ($flash === '') {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_phone'] = (string)$user['phone'];
                $_SESSION['user_name'] = (string)$user['name'];
                $_SESSION['user_email'] = isset($user['email']) ? (string)$user['email'] : '';
                header('Location: cabinet.php');
                exit;
            }
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: auth.php');
    exit;
}

$userPhone = isset($_SESSION['user_phone']) ? (string)$_SESSION['user_phone'] : '';
$userEmail = isset($_SESSION['user_email']) ? (string)$_SESSION['user_email'] : '';
$userName = isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : '';
account_render_head('Вход | AutoLine');
account_render_header('Вход в кабинет', 'Авторизация и регистрация', 'auth', !empty($userPhone));
?>
    <?php if ($flash !== ''): ?>
        <div class="account-alert <?= $flashType === 'error' ? 'account-alert-error' : 'account-alert-ok' ?>"><?= esc($flash) ?></div>
    <?php endif; ?>

    <div class="account-auth-grid">
        <div class="account-card" id="loginCard" style="<?= $showRegister ? 'display:none;' : '' ?>">
            <h2>Вход</h2>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <label class="account-field">
                    <span>Телефон</span>
                    <input class="account-input" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7 (___) ___-__-__" value="<?= esc($userPhone) ?>">
                </label>
                <label class="account-field">
                    <span>Email</span>
                    <input class="account-input" name="email" type="email" placeholder="you@example.com" value="<?= esc($userEmail) ?>">
                </label>
                <label class="account-field">
                    <span>Пароль</span>
                    <input class="account-input" name="password" type="password" autocomplete="current-password" required>
                </label>
                <button class="account-btn account-btn-accent" type="submit" <?= $pdo === null ? 'disabled' : '' ?>>Войти</button>
                <p class="account-muted">Нет аккаунта? Сначала зарегистрируйтесь.</p>
            </form>
        </div>

        <div class="account-card" id="registerCard" style="<?= $showRegister ? '' : 'display:none;' ?>">
            <h2>Регистрация</h2>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <label class="account-field">
                    <span>Телефон</span>
                    <input class="account-input" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7 (___) ___-__-__" value="<?= esc($userPhone) ?>" required>
                </label>
                <label class="account-field">
                    <span>Email</span>
                    <input class="account-input" name="email" type="email" placeholder="you@example.com" value="<?= esc($userEmail) ?>" required>
                </label>
                <label class="account-field">
                    <span>Имя</span>
                    <input class="account-input" name="name" type="text" value="<?= esc($userName) ?>" required>
                </label>
                <label class="account-field">
                    <span>Пароль</span>
                    <input class="account-input" name="password" type="password" autocomplete="new-password" required>
                </label>
                <label class="account-field">
                    <span>Подтвердите пароль</span>
                    <input class="account-input" name="password2" type="password" autocomplete="new-password" required>
                </label>
                <button class="account-btn account-btn-accent" type="submit" <?= $pdo === null ? 'disabled' : '' ?>>Зарегистрироваться</button>
            </form>
            <div class="account-auth-toggle">
                <button type="button" class="account-btn account-btn-outline" id="backToLogin">Уже есть аккаунт? Вход</button>
            </div>
        </div>
    </div>

    <div class="account-auth-toggle" id="toggleRegisterWrap" style="<?= $showRegister ? 'display:none;' : '' ?>">
        <button type="button" class="account-btn account-btn-outline" id="toggleRegister">Регистрация</button>
    </div>
<?php
account_render_footer([
    'assets/js/phone-mask.js?v=4',
    'assets/js/auth-form.js?v=2',
]);


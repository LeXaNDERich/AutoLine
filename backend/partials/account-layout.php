<?php
declare(strict_types=1);

function account_render_head(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/account.css?v=4">
</head>
<body class="account-page">
    <?php
}

function account_render_header(string $heading, string $meta = '', string $active = '', bool $loggedIn = true): void
{
    $showAdmin = function_exists('isAdminSession') && isAdminSession();
    ?>
    <header class="site-header account-site-header">
        <div class="header-phones container">
            <a class="phone-link" href="tel:+79001234567">
                <span class="phone-num">+7 (900) 123-45-67</span>
                <span class="phone-label">Заказ авто</span>
            </a>
            <a class="phone-link" href="tel:+79001234568">
                <span class="phone-num">+7 (900) 123-45-68</span>
                <span class="phone-label">Сервис и ремонт</span>
            </a>
        </div>
        <div class="header-bar">
            <div class="container header-bar-inner account-header-bar-inner">
                <a class="account-header-logo" href="index.php">Auto<span>Line</span></a>
                <nav class="account-header-nav" aria-label="Навигация кабинета">
                    <a href="index.php">На сайт</a>
                    <a href="brands.php">Марки</a>
                    <?php if ($loggedIn): ?>
                        <a href="cabinet.php"<?= $active === 'cabinet' ? ' class="is-active"' : '' ?>>Кабинет</a>
                    <?php endif; ?>
                    <?php if ($showAdmin): ?>
                        <a href="admin.php"<?= $active === 'admin' ? ' class="is-active"' : '' ?>>Админ</a>
                    <?php endif; ?>
                    <?php if ($loggedIn): ?>
                        <a href="auth.php?logout=1">Выйти</a>
                    <?php else: ?>
                        <a href="auth.php"<?= $active === 'auth' ? ' class="is-active"' : '' ?>>Вход</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>
    <main class="account-main">
        <div class="container">
            <div class="account-page-head">
                <div>
                    <h1 class="title-accent"><?= htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
                    <?php if ($meta !== ''): ?>
                        <p class="account-page-meta"><?= htmlspecialchars($meta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>
    <?php
}

function account_render_footer(array $extraScripts = []): void
{
    foreach ($extraScripts as $src) {
        if (!is_string($src) || $src === '') {
            continue;
        }
        echo '<script src="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>' . "\n";
    }
    ?>
        </div>
    </main>
    <footer class="site-footer account-footer">
        <div class="container site-footer-inner">
            <p>© <?= (int)date('Y') ?> AutoLine. Все права защищены.</p>
            <p class="footer-note">Сайт носит информационный характер и не является публичной офертой.</p>
        </div>
    </footer>
</body>
</html>
    <?php
}

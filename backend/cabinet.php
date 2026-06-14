<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/partials/account-layout.php';
require_once __DIR__ . '/requests_store.php';

header('Content-Type: text/html; charset=utf-8');

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

$pdo = db();

if ($pdo === null) {
    account_render_head('Кабинет AutoLine');
    account_render_header('Личный кабинет', '', 'cabinet', false);
    $dbErr = dbLastError();
    ?>
    <div class="account-alert account-alert-error">Не удалось подключиться к базе данных. Проверьте backend/config.php на сервере.</div>
    <?php if (defined('DB_DEBUG') && DB_DEBUG && $dbErr !== ''): ?>
        <p class="account-muted"><strong>Детали:</strong> <?= esc($dbErr) ?></p>
    <?php endif; ?>
    <p class="account-muted"><a href="db_check.php">Проверить подключение к БД</a> · <a href="index.php">На главную</a></p>
    <?php
    account_render_footer();
    exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['user_phone'])) {
    header('Location: auth.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userPhone = (string)$_SESSION['user_phone'];
$userName = isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : '';

requestsEnsureStatusSchema($pdo);

$selectSql = '
    SELECT id, ts, name, phone, service, comment, status, status_note, status_updated_at
    FROM requests
    WHERE user_id = :uid OR phone = :phone
    ORDER BY ts DESC
';
if (requestHasEmailColumn($pdo)) {
    $selectSql = '
        SELECT id, ts, name, phone, email, service, comment, status, status_note, status_updated_at
        FROM requests
        WHERE user_id = :uid OR phone = :phone
        ORDER BY ts DESC
    ';
}
$stmt = $pdo->prepare($selectSql);
$stmt->execute([':uid' => $userId, ':phone' => $userPhone]);
$requests = $stmt->fetchAll();

$service = $_GET['service'] ?? '';
$serviceFilter = is_string($service) ? trim($service) : '';
if ($serviceFilter !== '') {
    $requests = array_values(array_filter($requests, static function ($r) use ($serviceFilter): bool {
        return (($r['service'] ?? '') === $serviceFilter);
    }));
}

account_render_head('Кабинет | AutoLine');
account_render_header('Личный кабинет', $userName . ' · ' . $userPhone, 'cabinet');
?>
    <div class="account-card">
        <h2>Мои заявки</h2>

        <form method="get" class="account-filter-form">
                <span class="account-label">Фильтр:</span>
                <select class="account-select" name="service">
                    <option value="">Все</option>
                    <option value="order" <?= $serviceFilter === 'order' ? 'selected' : '' ?>>Автомобиль под заказ</option>
                    <option value="maintenance" <?= $serviceFilter === 'maintenance' ? 'selected' : '' ?>>Техническое обслуживание</option>
                    <option value="repair" <?= $serviceFilter === 'repair' ? 'selected' : '' ?>>Ремонт</option>
                    <option value="parts" <?= $serviceFilter === 'parts' ? 'selected' : '' ?>>Подбор автозапчастей</option>
                </select>
                <button class="account-btn account-btn-accent" type="submit">Применить</button>
        </form>

        <?php if (count($requests) === 0): ?>
            <p class="account-muted">Пока нет заявок.</p>
        <?php else: ?>
            <div class="account-table-wrap">
                <table class="account-table">
                    <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Услуга</th>
                        <th>Статус</th>
                        <th>Email</th>
                        <th>Комментарий</th>
                        <th>ID</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $r): ?>
                        <?php
                        $id = (int)($r['id'] ?? 0);
                        $ts = (int)($r['ts'] ?? 0);
                        $comment = (string)($r['comment'] ?? '');
                        $serviceLabelText = serviceLabel((string)($r['service'] ?? ''));
                        $reqStatus = requestsNormalizeStatus((string)($r['status'] ?? REQUEST_STATUS_NEW));
                        $statusNote = trim((string)($r['status_note'] ?? ''));
                        ?>
                        <tr>
                            <td><?= esc($ts > 0 ? date('d.m.Y H:i', $ts) : '-') ?></td>
                            <td><span class="account-tag"><?= esc($serviceLabelText) ?></span></td>
                            <td>
                                <span class="<?= esc(requestStatusCssClass($reqStatus)) ?>"><?= esc(requestStatusLabel($reqStatus)) ?></span>
                                <?php if ($statusNote !== ''): ?>
                                    <div class="account-status-note"><?= esc($statusNote) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="account-muted"><?= esc((string)($r['email'] ?? '')) ?></td>
                            <td>
                                <?php if ($comment !== ''): ?>
                                    <div class="account-comment"><?= esc($comment) ?></div>
                                <?php else: ?>
                                    <span class="account-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="account-small"><?= esc((string)$id) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <p class="account-muted">
        Если вы отправляли заявки без регистрации, они появятся здесь после входа по тому же телефону.
    </p>
<?php
account_render_footer();


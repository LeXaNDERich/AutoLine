<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=utf-8');

$pdo = null;
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/partials/account-layout.php';
require_once __DIR__ . '/requests_store.php';

if (!isset($_SESSION['user_id']) || !isAdminSession()) {
    http_response_code(403);
    account_render_head('Доступ запрещён | AutoLine');
    account_render_header('Доступ запрещён', 'Админ-панель только для администратора', 'admin', false);
    ?>
    <div class="account-card">
        <p class="account-muted">Войдите под учётной записью администратора.</p>
        <div class="account-actions">
            <a class="account-btn account-btn-accent" href="auth.php">Войти</a>
            <a class="account-btn account-btn-outline" href="index.php">На сайт</a>
        </div>
    </div>
    <?php
    account_render_footer();
    exit;
}

$requestsFile = __DIR__ . '/data/requests.json';

require_once __DIR__ . '/parts_store.php';

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function loadJsonArray(string $path): array
{
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

function saveJsonArray(string $path, array $data): bool
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

$flash = '';
$flashType = 'ok';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'update_request_status') {
        $requestId = trim((string)($_POST['request_id'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $statusNote = trim((string)($_POST['status_note'] ?? ''));
        $result = requestsUpdateStatus($pdo, $requestId, $status, $statusNote);
        if ($result['ok'] === true) {
            header('Location: admin.php?request_id=' . rawurlencode($requestId) . '&updated=1');
            exit;
        } else {
            $flash = (string)($result['error'] ?? 'Ошибка при обновлении статуса.');
            $flashType = 'error';
        }
    } elseif ($action === 'delete_request') {
        $requestId = trim((string)($_POST['request_id'] ?? ''));
        $allRequests = loadJsonArray($requestsFile);
        $matched = null;
        foreach ($allRequests as $item) {
            if ((string)($item['id'] ?? '') === $requestId) {
                $matched = $item;
                break;
            }
        }

        $requests = array_values(array_filter($allRequests, static function ($item) use ($requestId): bool {
            return (($item['id'] ?? '') !== $requestId);
        }));
        if (saveJsonArray($requestsFile, $requests)) {
            // Если заявка добавлялась в БД — удалим и там по db_id
            if ($pdo !== null && $matched !== null && isset($matched['db_id']) && is_numeric((string)$matched['db_id'])) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM requests WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => (int)$matched['db_id']]);
                } catch (Throwable $e) {
                    // Безопасно игнорируем ошибку удаления из БД
                }
            }
            $flash = 'Заявка удалена.';
        } else {
            $flash = 'Ошибка при удалении заявки.';
            $flashType = 'error';
        }
    } elseif ($action === 'delete_part') {
        $modelKey = trim((string)($_POST['model_key'] ?? ''));
        $partIdRaw = trim((string)($_POST['part_id'] ?? ''));
        if ($pdo !== null) {
            partsEnsureSchema($pdo);
            $partId = (int)$partIdRaw;
            if ($partId > 0 && partsDeletePart($pdo, $partId)) {
                partsSyncJsonFromDatabase($pdo);
                $flash = 'Запчасть удалена.';
            } else {
                $flash = 'Ошибка при удалении запчасти.';
                $flashType = 'error';
            }
        } elseif ($modelKey !== '' && $partIdRaw !== '' && partsJsonDeletePart($modelKey, $partIdRaw)) {
            $flash = 'Запчасть удалена.';
        } else {
            $flash = 'Ошибка при удалении запчасти.';
            $flashType = 'error';
        }
    } elseif ($action === 'add_part') {
        $fields = [
            'title' => trim((string)($_POST['title'] ?? '')),
            'desc' => trim((string)($_POST['desc'] ?? '')),
            'meta' => trim((string)($_POST['meta'] ?? '')),
            'price' => trim((string)($_POST['price'] ?? '')),
            'image' => trim((string)($_POST['image'] ?? '')),
        ];
        $modelKey = trim((string)($_POST['model_key'] ?? ''));
        if ($pdo !== null) {
            partsEnsureSchema($pdo);
            $result = partsAddPart($pdo, $modelKey, $fields);
            if ($result['ok'] === true) {
                partsSyncJsonFromDatabase($pdo);
                $flash = 'Запчасть добавлена.';
            } else {
                $flash = (string)($result['error'] ?? 'Ошибка при добавлении запчасти.');
                $flashType = 'error';
            }
        } else {
            $result = partsJsonAddPart($modelKey, $fields);
            if ($result['ok'] === true) {
                $flash = 'Запчасть добавлена.';
            } else {
                $flash = (string)($result['error'] ?? 'Ошибка при добавлении запчасти.');
                $flashType = 'error';
            }
        }
    } elseif ($action === 'update_part') {
        $partIdRaw = trim((string)($_POST['part_id'] ?? ''));
        $modelKey = trim((string)($_POST['model_key'] ?? ''));
        $fields = [
            'title' => trim((string)($_POST['title'] ?? '')),
            'desc' => trim((string)($_POST['desc'] ?? '')),
            'meta' => trim((string)($_POST['meta'] ?? '')),
            'price' => trim((string)($_POST['price'] ?? '')),
            'image' => trim((string)($_POST['image'] ?? '')),
            'model_key' => $modelKey,
        ];
        if ($pdo !== null) {
            partsEnsureSchema($pdo);
            $partId = (int)$partIdRaw;
            $result = partsUpdatePart($pdo, $partId, $fields);
            if ($result['ok'] === true) {
                partsSyncJsonFromDatabase($pdo);
                $flash = 'Запчасть обновлена.';
            } else {
                $flash = (string)($result['error'] ?? 'Ошибка при сохранении.');
                $flashType = 'error';
            }
        } else {
            $result = partsJsonUpdatePart($modelKey, $partIdRaw, $fields);
            if ($result['ok'] === true) {
                $flash = 'Запчасть обновлена.';
            } else {
                $flash = (string)($result['error'] ?? 'Ошибка при сохранении.');
                $flashType = 'error';
            }
        }
    } elseif ($action === 'add_model') {
        $brandSlug = trim((string)($_POST['brand_slug'] ?? ''));
        $modelName = trim((string)($_POST['model_name'] ?? ''));
        $modelSlug = trim((string)($_POST['model_slug'] ?? ''));
        if ($pdo === null) {
            $flash = 'Добавление моделей доступно при подключённой базе MySQL.';
            $flashType = 'error';
        } else {
            partsEnsureSchema($pdo);
            $result = partsAddModel($pdo, $brandSlug, $modelName, $modelSlug);
            if ($result['ok'] === true) {
                partsSyncJsonFromDatabase($pdo);
                $flash = 'Модель добавлена.';
            } else {
                $flash = (string)($result['error'] ?? 'Ошибка при добавлении модели.');
                $flashType = 'error';
            }
        }
    } elseif ($action === 'update_model') {
        $modelId = (int)($_POST['model_id'] ?? 0);
        $brandSlug = trim((string)($_POST['brand_slug'] ?? ''));
        $modelName = trim((string)($_POST['model_name'] ?? ''));
        if ($pdo === null || $modelId <= 0) {
            $flash = 'Изменение модели доступно при подключённой базе MySQL.';
            $flashType = 'error';
        } else {
            partsEnsureSchema($pdo);
            $result = partsUpdateModel($pdo, $modelId, $brandSlug, $modelName);
            if ($result['ok'] === true) {
                partsSyncJsonFromDatabase($pdo);
                $flash = 'Модель обновлена.';
            } else {
                $flash = (string)($result['error'] ?? 'Ошибка при сохранении модели.');
                $flashType = 'error';
            }
        }
    } elseif ($action === 'delete_model') {
        $modelId = (int)($_POST['model_id'] ?? 0);
        if ($pdo === null || $modelId <= 0) {
            $flash = 'Удаление модели доступно при подключённой базе MySQL.';
            $flashType = 'error';
        } else {
            partsEnsureSchema($pdo);
            if (partsDeleteModel($pdo, $modelId)) {
                partsSyncJsonFromDatabase($pdo);
                $flash = 'Модель и её запчасти удалены.';
            } else {
                $flash = 'Ошибка при удалении модели.';
                $flashType = 'error';
            }
        }
    }
}

$requests = requestsLoadForAdmin($pdo);

$partsStorage = partsStorageMode();
$partsBrands = partsLoadBrandsForSelect();
$partsModels = partsGetModelsList();
$partsGrouped = partsGetCatalog();
$partsAdminRows = $pdo !== null ? partsAdminList($pdo) : [];

$brandFilter = partsSlugify(trim((string)($_GET['brand'] ?? '')));
$modelFilter = partsSlugify(trim((string)($_GET['model'] ?? '')));
$brandFilterKey = $brandFilter !== '' ? str_replace('-', '', $brandFilter) : '';
$partsBrandLabels = partsBrandLabelMap();

$partsAdminGrouped = [];
if ($pdo !== null && $brandFilter !== '') {
    foreach ($partsModels as $modelRow) {
        $modelId = (int)($modelRow['id'] ?? 0);
        if ($modelId <= 0) {
            continue;
        }
        $modelBrandSlug = partsSlugify((string)($modelRow['brand_slug'] ?? ''));
        $modelBrandKey = str_replace('-', '', $modelBrandSlug);
        if ($modelBrandKey !== $brandFilterKey) {
            continue;
        }
        $modelSlug = partsSlugify((string)($modelRow['slug'] ?? ''));
        if ($modelFilter !== '' && $modelSlug !== $modelFilter) {
            continue;
        }
        $groupKey = $modelSlug . ':' . $modelId;
        $partsAdminGrouped[$groupKey] = [
            'model_name' => (string)($modelRow['name'] ?? ''),
            'model_slug' => $modelSlug,
            'brand_slug' => $modelBrandSlug,
            'model_id' => $modelId,
            'parts' => [],
        ];
    }

    foreach ($partsAdminRows as $partRow) {
        $modelId = (int)($partRow['model_id'] ?? 0);
        if ($modelId <= 0) {
            continue;
        }
        $modelBrandSlug = partsSlugify((string)($partRow['brand_slug'] ?? ''));
        $modelBrandKey = str_replace('-', '', $modelBrandSlug);
        if ($modelBrandKey !== $brandFilterKey) {
            continue;
        }
        $modelSlug = partsSlugify((string)($partRow['model_slug'] ?? ''));
        if ($modelFilter !== '' && $modelSlug !== $modelFilter) {
            continue;
        }
        $groupKey = $modelSlug . ':' . $modelId;
        if (!isset($partsAdminGrouped[$groupKey])) {
            $partsAdminGrouped[$groupKey] = [
                'model_name' => (string)($partRow['model_name'] ?? ''),
                'model_slug' => $modelSlug,
                'brand_slug' => $modelBrandSlug,
                'model_id' => $modelId,
                'parts' => [],
            ];
        }
        $partsAdminGrouped[$groupKey]['parts'][] = $partRow;
    }
    $partsAdminGrouped = array_values($partsAdminGrouped);
}

$partsModelsForJs = [];
foreach ($partsModels as $model) {
    $partsModelsForJs[] = [
        'slug' => (string)($model['slug'] ?? ''),
        'brand_slug' => (string)($model['brand_slug'] ?? ''),
        'name' => (string)($model['name'] ?? ''),
        'id' => (int)($model['id'] ?? 0),
    ];
}

$editPartId = (int)($_GET['edit_part'] ?? 0);
$editPartRow = null;
if ($pdo !== null && $editPartId > 0) {
    foreach ($partsAdminRows as $row) {
        if ((int)($row['id'] ?? 0) === $editPartId) {
            $editPartRow = $row;
            break;
        }
    }
}
$editPartBrand = $editPartRow !== null ? (string)($editPartRow['brand_slug'] ?? '') : '';
$editPartModelSlug = $editPartRow !== null ? (string)($editPartRow['model_slug'] ?? '') : '';

$selectedRequestId = trim((string)($_GET['request_id'] ?? ''));
$selectedRequest = null;
foreach ($requests as $requestItem) {
    if ((string)($requestItem['id'] ?? '') === $selectedRequestId) {
        $selectedRequest = $requestItem;
        break;
    }
}

account_render_head('Админ-панель | AutoLine');
account_render_header('Админ-панель', 'Управление заявками и каталогом запчастей', 'admin');
?>
    <?php if ($flash !== ''): ?>
        <div class="account-alert <?= $flashType === 'error' ? 'account-alert-error' : 'account-alert-ok' ?>"><?= esc($flash) ?></div>
    <?php elseif (isset($_GET['updated']) && $_GET['updated'] === '1' && $selectedRequest !== null): ?>
        <div class="account-alert account-alert-ok">Статус заявки сохранён.</div>
    <?php endif; ?>

    <section class="account-card">
        <h2>Заявки клиентов</h2>
        <?php if (count($requests) === 0): ?>
            <p class="account-muted">Заявок пока нет.</p>
        <?php else: ?>
            <div class="account-table-wrap">
            <table class="account-table">
                <thead>
                <tr>
                    <th>Дата</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Услуга</th>
                    <th>Статус</th>
                    <th>ID</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <?php
                    $requestId = (string)($r['id'] ?? '');
                    $timestamp = (int)($r['ts'] ?? 0);
                    $reqStatus = requestsNormalizeStatus((string)($r['status'] ?? REQUEST_STATUS_NEW));
                    ?>
                    <tr>
                        <td><?= esc($timestamp > 0 ? date('d.m.Y H:i', $timestamp) : '-') ?></td>
                        <td><?= esc((string)($r['name'] ?? '')) ?></td>
                        <td><?= esc((string)($r['phone'] ?? '')) ?></td>
                        <td><?= esc((string)($r['email'] ?? '')) ?></td>
                        <td><?= esc(serviceLabel((string)($r['service'] ?? ''))) ?></td>
                        <td><span class="<?= esc(requestStatusCssClass($reqStatus)) ?>"><?= esc(requestStatusLabel($reqStatus)) ?></span></td>
                        <td class="account-small"><?= esc($requestId) ?></td>
                        <td>
                            <div class="account-actions account-actions-stack">
                                <a class="account-btn account-btn-outline" href="?request_id=<?= urlencode($requestId) ?>">Подробнее</a>
                                <form method="post" class="request-status-quick">
                                    <input type="hidden" name="action" value="update_request_status">
                                    <input type="hidden" name="request_id" value="<?= esc($requestId) ?>">
                                    <input type="hidden" name="status" value="<?= esc(REQUEST_STATUS_COMPLETED) ?>">
                                    <button class="account-btn account-btn-accent" type="submit">Выполнена</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Удалить заявку?');">
                                    <input type="hidden" name="action" value="delete_request">
                                    <input type="hidden" name="request_id" value="<?= esc($requestId) ?>">
                                    <button class="account-btn account-btn-danger" type="submit">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <?php if ($selectedRequest !== null): ?>
            <?php
            $selId = (string)($selectedRequest['id'] ?? '');
            $selStatus = requestsNormalizeStatus((string)($selectedRequest['status'] ?? REQUEST_STATUS_NEW));
            $selStatusAt = (int)($selectedRequest['status_updated_at'] ?? 0);
            ?>
            <div class="account-detail-block">
                <h3>Подробно по заявке</h3>
                <p class="account-small">ID: <?= esc($selId) ?></p>
                <p><strong>Статус:</strong> <span class="<?= esc(requestStatusCssClass($selStatus)) ?>"><?= esc(requestStatusLabel($selStatus)) ?></span></p>
                <?php if ($selStatusAt > 0): ?>
                    <p class="account-small">Обновлён: <?= esc(date('d.m.Y H:i', $selStatusAt)) ?></p>
                <?php endif; ?>
                <?php if ((string)($selectedRequest['status_note'] ?? '') !== ''): ?>
                    <p><strong>Комментарий для клиента:</strong></p>
                    <div class="account-comment"><?= esc((string)$selectedRequest['status_note']) ?></div>
                <?php endif; ?>
                <p><strong>Имя:</strong> <?= esc((string)($selectedRequest['name'] ?? '')) ?></p>
                <p><strong>Телефон:</strong> <?= esc((string)($selectedRequest['phone'] ?? '')) ?></p>
                <p><strong>Email:</strong> <?= esc((string)($selectedRequest['email'] ?? '')) ?></p>
                <p><strong>Услуга:</strong> <span class="account-tag"><?= esc(serviceLabel((string)($selectedRequest['service'] ?? ''))) ?></span></p>
                <p><strong>Комментарий клиента:</strong></p>
                <div class="account-comment"><?= esc((string)($selectedRequest['comment'] ?? '')) ?></div>

                <form method="post" class="request-status-form">
                    <input type="hidden" name="action" value="update_request_status">
                    <input type="hidden" name="request_id" value="<?= esc($selId) ?>">
                    <h4>Изменить статус</h4>
                    <label class="account-field">
                        <span>Статус</span>
                        <select class="account-select" name="status" required>
                            <?php foreach (requestsAllowedStatuses() as $statusCode): ?>
                                <option value="<?= esc($statusCode) ?>"<?= $statusCode === $selStatus ? ' selected' : '' ?>><?= esc(requestStatusLabel($statusCode)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="account-field">
                        <span>Сообщение клиенту</span>
                        <textarea class="account-textarea" name="status_note" rows="2" placeholder="Например: Запчасти готовы к выдаче"><?= esc((string)($selectedRequest['status_note'] ?? '')) ?></textarea>
                    </label>
                    <button class="account-btn account-btn-accent" type="submit">Сохранить статус</button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <section class="account-card">
        <div class="account-part-toolbar">
            <h2>Каталог запчастей</h2>
        </div>
        <p class="account-muted">Позиции сразу отображаются на сайте через <code>parts-data.php</code> и в каталоге марок.</p>

        <div class="account-grid account-grid-parts">
            <div>
                <?php if ($editPartRow !== null): ?>
                    <h3>Редактировать запчасть</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="update_part">
                        <input type="hidden" name="part_id" value="<?= (int)($editPartRow['id'] ?? 0) ?>">
                        <div class="parts-picker" data-parts-picker data-initial-brand="<?= esc($editPartBrand) ?>" data-initial-model="<?= esc($editPartModelSlug) ?>">
                            <label class="account-field">
                                <span>Марка</span>
                                <select class="account-select" data-parts-brand-select required>
                                    <option value="">Выберите марку</option>
                                    <?php foreach ($partsBrands as $brand): ?>
                                        <?php $bSlug = (string)($brand['slug'] ?? ''); ?>
                                        <option value="<?= esc($bSlug) ?>"<?= $bSlug === $editPartBrand ? ' selected' : '' ?>><?= esc((string)($brand['name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="account-field">
                                <span>Модель</span>
                                <select class="account-select" name="model_key" data-parts-model-select required></select>
                            </label>
                        </div>
                        <label class="account-field">
                            <span>Название</span>
                            <input class="account-input" type="text" name="title" value="<?= esc((string)($editPartRow['title'] ?? '')) ?>" required>
                        </label>
                        <label class="account-field">
                            <span>Описание</span>
                            <textarea class="account-textarea" name="desc" rows="3"><?= esc((string)($editPartRow['description'] ?? '')) ?></textarea>
                        </label>
                        <label class="account-field">
                            <span>Наличие / срок</span>
                            <input class="account-input" type="text" name="meta" value="<?= esc((string)($editPartRow['meta'] ?? '')) ?>">
                        </label>
                        <label class="account-field">
                            <span>Цена</span>
                            <input class="account-input" type="text" name="price" value="<?= esc((string)($editPartRow['price'] ?? '')) ?>" required>
                        </label>
                        <label class="account-field">
                            <span>Фото (URL)</span>
                            <input class="account-input" type="url" name="image" value="<?= esc((string)($editPartRow['image_url'] ?? '')) ?>" placeholder="https://...">
                        </label>
                        <div class="account-actions">
                            <button class="account-btn account-btn-accent" type="submit">Сохранить</button>
                            <a class="account-btn account-btn-outline" href="admin.php">Отмена</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h3>Добавить запчасть</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_part">
                        <div class="parts-picker" data-parts-picker>
                            <label class="account-field">
                                <span>Марка</span>
                                <select class="account-select" data-parts-brand-select required>
                                    <option value="">Выберите марку</option>
                                    <?php foreach ($partsBrands as $brand): ?>
                                        <option value="<?= esc((string)($brand['slug'] ?? '')) ?>"<?= (string)($brand['slug'] ?? '') === $brandFilter ? ' selected' : '' ?>><?= esc((string)($brand['name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="account-field">
                                <span>Модель</span>
                                <select class="account-select" name="model_key" data-parts-model-select required disabled>
                                    <option value="">Сначала выберите марку</option>
                                </select>
                            </label>
                        </div>
                        <label class="account-field">
                            <span>Название</span>
                            <input class="account-input" type="text" name="title" required>
                        </label>
                        <label class="account-field">
                            <span>Описание</span>
                            <textarea class="account-textarea" name="desc" rows="3"></textarea>
                        </label>
                        <label class="account-field">
                            <span>Наличие / срок</span>
                            <input class="account-input" type="text" name="meta" placeholder="Например: Срок: 1-2 дня">
                        </label>
                        <label class="account-field">
                            <span>Цена</span>
                            <input class="account-input" type="text" name="price" placeholder="от 2 500 ₽" required>
                        </label>
                        <label class="account-field">
                            <span>Фото (URL)</span>
                            <input class="account-input" type="url" name="image" placeholder="https://...">
                        </label>
                        <button class="account-btn account-btn-accent" type="submit">Добавить</button>
                    </form>
                <?php endif; ?>

                <?php if ($partsStorage === 'database'): ?>
                    <h3 class="account-subhead">Добавить модель</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_model">
                        <label class="account-field">
                            <span>Марка</span>
                            <select class="account-select" name="brand_slug" required>
                                <option value="">Выберите марку</option>
                                <?php foreach ($partsBrands as $brand): ?>
                                    <option value="<?= esc((string)($brand['slug'] ?? '')) ?>"><?= esc((string)($brand['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="account-field">
                            <span>Модель</span>
                            <input class="account-input" type="text" name="model_name" placeholder="Например: Solaris" required>
                        </label>
                        <label class="account-field">
                            <span>Код (необязательно)</span>
                            <input class="account-input" type="text" name="model_slug" placeholder="hyundai-solaris">
                        </label>
                        <button class="account-btn account-btn-outline" type="submit">Добавить модель</button>
                    </form>
                <?php endif; ?>
            </div>

            <div>
                <div class="account-part-list-toolbar">
                    <h3>Текущий список</h3>
                    <form class="account-filter-form" method="get" data-parts-filter-form data-initial-brand="<?= esc($brandFilter) ?>" data-initial-model="<?= esc($modelFilter) ?>">
                        <?php if ($editPartId > 0): ?>
                            <input type="hidden" name="edit_part" value="<?= $editPartId ?>">
                        <?php endif; ?>
                        <label class="account-field account-field-inline">
                            <span>Марка</span>
                            <select class="account-select" name="brand" data-parts-filter-brand required>
                                <option value="">Выберите марку</option>
                                <?php foreach ($partsBrands as $brand): ?>
                                    <?php $bSlug = partsSlugify((string)($brand['slug'] ?? '')); ?>
                                    <option value="<?= esc($bSlug) ?>"<?= $bSlug === $brandFilter ? ' selected' : '' ?>><?= esc((string)($brand['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="account-field account-field-inline">
                            <span>Модель</span>
                            <select class="account-select" name="model" data-parts-filter-model<?= $brandFilter === '' ? ' disabled' : '' ?>>
                                <option value=""><?= $brandFilter !== '' ? 'Все модели марки' : 'Сначала выберите марку' ?></option>
                            </select>
                        </label>
                        <button class="account-btn account-btn-accent" type="submit">Показать</button>
                        <?php if ($brandFilter !== '' || $modelFilter !== ''): ?>
                            <a class="account-btn account-btn-outline" href="admin.php<?= $editPartId > 0 ? '?edit_part=' . $editPartId : '' ?>">Сброс</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if ($partsStorage === 'database' && $brandFilter === ''): ?>
                    <p class="account-muted">Выберите марку и нажмите «Показать». При необходимости уточните модель — так отображаются только нужные машины, а не весь каталог.</p>
                <?php elseif ($partsStorage === 'database' && $partsAdminGrouped !== []): ?>
                    <?php foreach ($partsAdminGrouped as $modelGroup): ?>
                        <div class="account-model-block">
                            <div class="account-model-head">
                                <div class="account-model-title">
                                    <h4><?= esc((string)$modelGroup['model_name']) ?></h4>
                                    <?php
                                    $gBrand = (string)$modelGroup['brand_slug'];
                                    $gBrandLabel = $partsBrandLabels[$gBrand] ?? $gBrand;
                                    ?>
                                    <span class="account-model-brand"><?= esc($gBrandLabel) ?></span>
                                </div>
                                <div class="account-model-head-actions">
                                    <button type="button" class="account-btn account-btn-outline account-btn-sm" data-model-edit-toggle aria-expanded="false">Изменить</button>
                                    <form class="account-model-delete" method="post" onsubmit="return confirm('Удалить модель и все запчасти?');">
                                        <input type="hidden" name="action" value="delete_model">
                                        <input type="hidden" name="model_id" value="<?= (int)$modelGroup['model_id'] ?>">
                                        <button class="account-btn account-btn-danger account-btn-sm" type="submit">Удалить</button>
                                    </form>
                                </div>
                            </div>
                            <form class="account-model-edit" method="post" hidden>
                                <input type="hidden" name="action" value="update_model">
                                <input type="hidden" name="model_id" value="<?= (int)$modelGroup['model_id'] ?>">
                                <div class="account-model-edit-grid">
                                    <label class="account-field">
                                        <span>Марка</span>
                                        <select class="account-select" name="brand_slug" required>
                                            <?php foreach ($partsBrands as $brand): ?>
                                                <?php $bSlug = (string)($brand['slug'] ?? ''); ?>
                                                <option value="<?= esc($bSlug) ?>"<?= $bSlug === $gBrand ? ' selected' : '' ?>><?= esc((string)($brand['name'] ?? '')) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="account-field">
                                        <span>Название модели</span>
                                        <input class="account-input" type="text" name="model_name" value="<?= esc((string)$modelGroup['model_name']) ?>" required>
                                    </label>
                                </div>
                                <button class="account-btn account-btn-accent account-btn-sm" type="submit">Сохранить модель</button>
                            </form>
                            <div class="account-part-list">
                                <?php foreach ($modelGroup['parts'] as $partRow): ?>
                                    <article class="account-part-card">
                                        <div class="account-part-body">
                                            <div class="account-part-info">
                                                <h5 class="account-part-title"><?= esc((string)($partRow['title'] ?? '')) ?></h5>
                                                <div class="account-part-meta-row">
                                                    <?php if ((string)($partRow['price'] ?? '') !== ''): ?>
                                                        <span class="account-part-price"><?= esc((string)$partRow['price']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ((string)($partRow['meta'] ?? '') !== ''): ?>
                                                        <span class="account-part-meta"><?= esc((string)$partRow['meta']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ((string)($partRow['description'] ?? '') !== ''): ?>
                                                    <p class="account-part-desc"><?= esc((string)$partRow['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="account-part-actions">
                                                <a class="account-btn account-btn-outline account-btn-sm" href="?edit_part=<?= (int)($partRow['id'] ?? 0) ?><?= $brandFilter !== '' ? '&brand=' . urlencode($brandFilter) : '' ?><?= $modelFilter !== '' ? '&model=' . urlencode($modelFilter) : '' ?>">Изменить</a>
                                                <form method="post" onsubmit="return confirm('Удалить запчасть?');">
                                                    <input type="hidden" name="action" value="delete_part">
                                                    <input type="hidden" name="part_id" value="<?= (int)($partRow['id'] ?? 0) ?>">
                                                    <button class="account-btn account-btn-danger account-btn-sm" type="submit">Удалить</button>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($modelGroup['parts']) === 0): ?>
                                <p class="account-muted">Для этой модели пока нет запчастей.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($partsStorage === 'database' && $partsAdminGrouped === []): ?>
                    <p class="account-muted"><?= $brandFilter !== '' ? 'Для этой марки пока нет моделей и запчастей.' : 'Каталог пуст. Добавьте модель и запчасти.' ?></p>
                <?php else: ?>
                    <?php if ($brandFilter === ''): ?>
                        <p class="account-muted">Выберите марку и нажмите «Показать».</p>
                    <?php endif; ?>
                    <?php foreach ($partsModels as $model): ?>
                        <?php
                        if ($brandFilter === '') {
                            continue;
                        }
                        $modelBrandSlug = partsSlugify((string)($model['brand_slug'] ?? ''));
                        if (str_replace('-', '', $modelBrandSlug) !== $brandFilterKey) {
                            continue;
                        }
                        $modelSlug = partsSlugify((string)($model['slug'] ?? ''));
                        if ($modelFilter !== '' && $modelSlug !== $modelFilter) {
                            continue;
                        }
                        ?>
                        <?php $key = (string)($model['slug'] ?? ''); ?>
                        <div class="account-model-block">
                            <div class="account-model-head">
                                <div class="account-model-title">
                                    <h4><?= esc((string)($model['name'] ?? $key)) ?></h4>
                                    <span class="account-model-brand"><?= esc((string)($model['brand_slug'] ?? '')) ?></span>
                                </div>
                            </div>
                        <?php
                        $modelParts = $partsGrouped[$key] ?? [];
                        if (!is_array($modelParts) || count($modelParts) === 0):
                            ?>
                            <p class="account-muted">Нет позиций.</p>
                        <?php else: ?>
                            <div class="account-part-list">
                            <?php foreach ($modelParts as $part): ?>
                                <article class="account-part-card">
                                    <div class="account-part-body">
                                        <div class="account-part-info">
                                            <h5 class="account-part-title"><?= esc((string)($part['title'] ?? '')) ?></h5>
                                            <div class="account-part-meta-row">
                                                <?php if ((string)($part['price'] ?? '') !== ''): ?>
                                                    <span class="account-part-price"><?= esc((string)$part['price']) ?></span>
                                                <?php endif; ?>
                                                <?php if ((string)($part['meta'] ?? '') !== ''): ?>
                                                    <span class="account-part-meta"><?= esc((string)$part['meta']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ((string)($part['desc'] ?? '') !== ''): ?>
                                                <p class="account-part-desc"><?= esc((string)$part['desc']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="account-part-actions">
                                            <form method="post" onsubmit="return confirm('Удалить запчасть?');">
                                                <input type="hidden" name="action" value="delete_part">
                                                <input type="hidden" name="model_key" value="<?= esc($key) ?>">
                                                <input type="hidden" name="part_id" value="<?= esc((string)($part['id'] ?? '')) ?>">
                                                <button class="account-btn account-btn-danger account-btn-sm" type="submit">Удалить</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <script>
        window.AUTOLINE_PARTS_MODELS = <?= json_encode($partsModelsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <script src="assets/js/admin-parts.js?v=2"></script>
<?php
account_render_footer();

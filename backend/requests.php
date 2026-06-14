<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

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

$serviceFilter = trim((string)($_GET['service'] ?? ''));

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Новые заявки наверху
$requests = array_reverse($requests);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки AutoLine</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", Arial, sans-serif; background: #f4f7fb; color: #162336; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
        h1 { margin: 0 0 14px; }
        .bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
        .input { padding: 10px 12px; border-radius: 10px; border: 1px solid #c6d3e6; background: #fff; }
        .btn { padding: 10px 14px; border-radius: 10px; border: none; cursor: pointer; font-weight: 700; background: #1f74ff; color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 22px rgba(20, 43, 73, 0.06); }
        th, td { padding: 12px 10px; border-bottom: 1px solid #eef2f8; vertical-align: top; }
        th { background: #eef5ff; text-align: left; }
        .muted { color: #5c6f8f; }
        .tag { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #eaf0f8; border: 1px solid #dce7fb; }
        .empty { background: #fff; border-radius: 14px; padding: 18px; box-shadow: 0 10px 22px rgba(20, 43, 73, 0.06); }
        .right { text-align: right; }
        a { color: #1f74ff; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Заявки AutoLine</h1>
    <div class="bar">
        <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <label class="muted">Фильтр по услуге:</label>
            <select name="service" class="input">
                <option value="">Все</option>
                <option value="order" <?= $serviceFilter === 'order' ? 'selected' : '' ?>>Автомобиль под заказ</option>
                <option value="maintenance" <?= $serviceFilter === 'maintenance' ? 'selected' : '' ?>>Техническое обслуживание</option>
                <option value="repair" <?= $serviceFilter === 'repair' ? 'selected' : '' ?>>Ремонт</option>
                <option value="parts" <?= $serviceFilter === 'parts' ? 'selected' : '' ?>>Подбор автозапчастей</option>
            </select>
            <button class="btn" type="submit">Применить</button>
        </form>
        <a href="index.html">Назад на сайт</a>
    </div>

    <?php
    $filtered = [];
    foreach ($requests as $r) {
        if ($serviceFilter !== '' && (($r['service'] ?? '') !== $serviceFilter)) continue;
        $filtered[] = $r;
    }

    if (count($filtered) === 0) {
        echo '<div class="empty"><div class="muted">Пока нет заявок.</div></div>';
    } else {
        echo '<table>';
        echo '<thead><tr>';
        echo '<th>Дата</th><th>Имя</th><th>Телефон</th><th>Услуга</th><th>Комментарий</th><th class="right">ID</th>';
        echo '</tr></thead><tbody>';

        foreach ($filtered as $r) {
            $ts = (int)($r['ts'] ?? 0);
            $dt = $ts > 0 ? date('d.m.Y H:i', $ts) : '-';
            $service = (string)($r['service'] ?? '');
            switch ($service) {
                case 'order':
                    $serviceLabel = 'Автомобиль под заказ';
                    break;
                case 'maintenance':
                    $serviceLabel = 'Техническое обслуживание';
                    break;
                case 'repair':
                    $serviceLabel = 'Ремонт';
                    break;
                case 'parts':
                    $serviceLabel = 'Подбор автозапчастей';
                    break;
                default:
                    $serviceLabel = $service;
            }

            echo '<tr>';
            echo '<td>' . esc($dt) . '</td>';
            echo '<td>' . esc((string)($r['name'] ?? '')) . '</td>';
            echo '<td>' . esc((string)($r['phone'] ?? '')) . '</td>';
            echo '<td><span class="tag">' . esc($serviceLabel) . '</span></td>';
            echo '<td class="muted">' . nl2br(esc((string)($r['comment'] ?? ''))) . '</td>';
            echo '<td class="right muted">' . esc((string)($r['id'] ?? '')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
    ?>
</div>
</body>
</html>


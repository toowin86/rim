<?php
/**
 * /shablon/ajax/news.php
 */

header('Content-Type: application/json; charset=UTF-8');

$cur_key = (int)_GP('cur_key');
if ($cur_key <= 0 || !isset($struktura['id'][$cur_key])) {
    echo json_encode(['status' => 'error', 'html' => '<div class="mod-news__empty">Страница не найдена.</div>'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$s_struktura_id = (int)$struktura['id'][$cur_key];

$sql = "SELECT id, name, mini_desc, data_change, data_create
        FROM s_news
        WHERE chk_active = 1 AND s_struktura_id = ?
        ORDER BY COALESCE(data_change, data_create) DESC, id DESC
        LIMIT 100";
$res = _DB($sql, [$s_struktura_id]);

$items = [];
if ($res) {
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $items[] = $row;
    }
}

if (empty($items)) {
    echo json_encode(['status' => 'ok', 'html' => '<div class="mod-news__empty">Пока нет новостей.</div>'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$html = '';
foreach ($items as $row) {
    $id = isset($row['id']) ? (int)$row['id'] : 0;
    if ($id <= 0) { continue; }

    $name = htmlspecialchars(trim((string)($row['name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $mini_desc = htmlspecialchars(trim((string)($row['mini_desc'] ?? '')), ENT_QUOTES, 'UTF-8');

    $date_raw = '';
    if (!empty($row['data_change']) && $row['data_change'] !== '0000-00-00 00:00:00') {
        $date_raw = $row['data_change'];
    } elseif (!empty($row['data_create']) && $row['data_create'] !== '0000-00-00 00:00:00') {
        $date_raw = $row['data_create'];
    }

    $date_view = '';
    if ($date_raw !== '') {
        $ts = strtotime($date_raw);
        if ($ts !== false) {
            $date_view = date('d.m.Y', $ts);
        }
    }

    $html .= '<article class="mod-news__item">';
    if ($date_view !== '') {
        $html .= '<div class="mod-news__date">' . $date_view . '</div>';
    }
    $html .= '<a class="mod-news__name" href="/?com=news&id=' . $id . '">' . $name . '</a>';
    if ($mini_desc !== '') {
        $html .= '<div class="mod-news__desc">' . nl2br($mini_desc) . '</div>';
    }
    $html .= '</article>';
}

echo json_encode(['status' => 'ok', 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

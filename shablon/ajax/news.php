<?php
/**
 * /shablon/ajax/news.php
 */

header('Content-Type: application/json; charset=UTF-8');

if (!function_exists('news_json_exit')) {
    function news_json_exit($arr)
    {
        if (ob_get_length()) {
            @ob_clean();
        }

        $json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"status":"error","html":"<div class=\"mod-news__empty\">Ошибка JSON ответа.</div>","tags_html":""}';
        }

        echo $json;
        exit;
    }
}

$cur_key = (int)_GP('cur_key');
$q = trim((string)_GP('q'));
$tip_id = (int)_GP('tip_id');
$sort = trim((string)_GP('sort'));

if ($cur_key <= 0 || !isset($struktura['id'][$cur_key])) {
    news_json_exit(['status' => 'error', 'html' => '<div class="mod-news__empty">Страница не найдена.</div>', 'tags_html' => '']);
}

$s_struktura_id = (int)$struktura['id'][$cur_key];

$tip_sql = "SELECT t.id, t.name, COUNT(n.id) AS cnt
            FROM i_news_tip t
            LEFT JOIN s_news n ON n.i_news_tip_id = t.id AND n.chk_active = 1 AND n.s_struktura_id = ?
            GROUP BY t.id, t.name
            ORDER BY t.name ASC";
$tip_res = _DB($tip_sql, [$s_struktura_id]);

$tags = [];
if ($tip_res) {
    while ($tip_row = $tip_res->fetch(PDO::FETCH_ASSOC)) {
        $tag_id = isset($tip_row['id']) ? (int)$tip_row['id'] : 0;
        if ($tag_id <= 0) { continue; }
        $tags[] = [
            'id' => $tag_id,
            'name' => trim((string)($tip_row['name'] ?? '')),
            'cnt' => isset($tip_row['cnt']) ? (int)$tip_row['cnt'] : 0,
        ];
    }
}

$order_sql = 'COALESCE(NULLIF(n.data_publish,\'\'), n.data_change, n.data_create) DESC, n.id DESC';
if ($sort === 'old') {
    $order_sql = 'COALESCE(NULLIF(n.data_publish,\'\'), n.data_change, n.data_create) ASC, n.id ASC';
}

$where = ['n.chk_active = 1', 'n.s_struktura_id = ?'];
$params = [$s_struktura_id];

if ($tip_id > 0) {
    $where[] = 'n.i_news_tip_id = ?';
    $params[] = $tip_id;
}

if ($q !== '') {
    $where[] = '(n.name LIKE ? OR n.description LIKE ? OR n.html_code LIKE ?)';
    $q_like = '%' . $q . '%';
    $params[] = $q_like;
    $params[] = $q_like;
    $params[] = $q_like;
}

$sql = "SELECT n.id, n.name, n.description, n.html_code, n.data_publish, n.data_change, n.data_create, n.i_news_tip_id,
               (
                   SELECT p.id
                   FROM a_photo p
                   WHERE p.a_menu_id = 8
                     AND p.row_id = n.id
                     AND p.tip = 'Основное'
                   ORDER BY p.sid ASC, p.id ASC
                   LIMIT 1
               ) AS photo_id,
               t.name AS tip_name
        FROM s_news n
        LEFT JOIN i_news_tip t ON t.id = n.i_news_tip_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $order_sql
        LIMIT 100";
$res = _DB($sql, $params);

$items = [];
if ($res) {
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $items[] = $row;
    }
}

$tags_html = '<button class="mod-news2__tag is-active js-news2-tag" type="button" data-tip-id="0">Все</button>';
foreach ($tags as $tag) {
    if ($tag['cnt'] <= 0) { continue; }
    $tags_html .= '<button class="mod-news2__tag js-news2-tag" type="button" data-tip-id="' . (int)$tag['id'] . '">' . htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8') . '</button>';
}

if (empty($items)) {
    news_json_exit(['status' => 'ok', 'html' => '<div class="mod-news__empty">Новости не найдены.</div>', 'tags_html' => $tags_html]);
}

$html = '';
foreach ($items as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) { continue; }

    $name = htmlspecialchars(trim((string)($row['name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $tip_name = htmlspecialchars(trim((string)($row['tip_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $photo_id = isset($row['photo_id']) ? (int)$row['photo_id'] : 0;

    $text = trim((string)($row['description'] ?? ''));
    if ($text === '') {
        $text = trim((string)($row['html_code'] ?? ''));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim((string)$text);
    }

    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 220) {
        $text = mb_substr($text, 0, 220, 'UTF-8') . '...';
    }

    $date_raw = trim((string)($row['data_publish'] ?? ''));
    if ($date_raw === '' || $date_raw === '0000-00-00 00:00:00') {
        $date_raw = trim((string)($row['data_change'] ?? ''));
    }
    if ($date_raw === '' || $date_raw === '0000-00-00 00:00:00') {
        $date_raw = trim((string)($row['data_create'] ?? ''));
    }

    $date_view = '';
    if ($date_raw !== '') {
        $ts = strtotime($date_raw);
        if ($ts !== false) { $date_view = date('Y-m-d', $ts); }
    }

    $html .= '<article class="mod-news2__item">';
    $html .= '<div class="mod-news2__media">';
    if ($photo_id > 0) {
        $html .= '<img class="mod-news2__img" src="/?com=i&id=' . $photo_id . '" alt="' . $name . '">';
    }
    $html .= '</div>';
    $html .= '<div class="mod-news2__content">';
    $html .= '<div class="mod-news2__meta">';
    if ($tip_name !== '') { $html .= '<span class="mod-news2__chip">' . $tip_name . '</span>'; }
    if ($date_view !== '') { $html .= '<span class="mod-news2__date">📅 ' . htmlspecialchars($date_view, ENT_QUOTES, 'UTF-8') . '</span>'; }
    $html .= '</div>';
    $html .= '<a class="mod-news2__name" href="/?com=news&id=' . $id . '">' . $name . '</a>';
    if ($text !== '') { $html .= '<div class="mod-news2__desc">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>'; }
    $html .= '</div>';
    $html .= '</article>';
}

news_json_exit(['status' => 'ok', 'html' => $html, 'tags_html' => $tags_html]);

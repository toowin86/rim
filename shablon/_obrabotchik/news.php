<?php
/**
 * /shablon/_obrabotchik/news.php
 */

$news_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($news_id <= 0) {
    err_404('Не указана новость', 'news | id');
}

$res_news = _DB(
    "SELECT id, name, description, html_code, data_change, data_create
     FROM s_news
     WHERE id = ? AND chk_active = 1
     LIMIT 1",
    [$news_id]
);

if (!$res_news) {
    err_404('Ошибка загрузки новости', 'news | sql');
}

$news_row = $res_news->fetch(PDO::FETCH_ASSOC);
if (!$news_row) {
    err_404('Новость не найдена', 'news | not found');
}

$news_title = trim((string)($news_row['name'] ?? ''));
$_obrabotchik['title'] = ($news_title !== '') ? $news_title : $_obrabotchik['title'];
$_obrabotchik['description'] = trim((string)($news_row['description'] ?? ''));
$_obrabotchik['keywords'] = '';
$_obrabotchik['canonical'] = $protacol . ($_SERVER['HTTP_HOST'] ?? '') . '/?com=news&id=' . (int)$news_row['id'];
$_obrabotchik['og_url'] = $_obrabotchik['canonical'];

$GLOBALS['news_row'] = $news_row;
?>

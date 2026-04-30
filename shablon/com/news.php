<?php
/**
 * /shablon/com/news.php
 */

$news_row = isset($GLOBALS['news_row']) && is_array($GLOBALS['news_row']) ? $GLOBALS['news_row'] : [];
$news_name = htmlspecialchars(trim((string)($news_row['name'] ?? '')), ENT_QUOTES, 'UTF-8');
$news_mini_desc = trim((string)($news_row['mini_desc'] ?? ''));
$news_html = (string)($news_row['html_code'] ?? '');

$news_date = '';
if (!empty($news_row['data_change']) && $news_row['data_change'] !== '0000-00-00 00:00:00') {
    $news_date = $news_row['data_change'];
} elseif (!empty($news_row['data_create']) && $news_row['data_create'] !== '0000-00-00 00:00:00') {
    $news_date = $news_row['data_create'];
}

$news_date_view = '';
if ($news_date !== '') {
    $ts = strtotime($news_date);
    if ($ts !== false) {
        $news_date_view = date('d.m.Y', $ts);
    }
}
?>
<section class="news-page">
    <div class="container">
        <article class="news-page__card">
            <?php if ($news_date_view !== ''): ?><div class="news-page__date"><?= htmlspecialchars($news_date_view, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <h1 class="news-page__title"><?= $news_name ?></h1>
            <?php if ($news_mini_desc !== ''): ?><div class="news-page__mini"><?= nl2br(htmlspecialchars($news_mini_desc, ENT_QUOTES, 'UTF-8')) ?></div><?php endif; ?>
            <?php if ($news_html !== ''): ?><div class="news-page__html"><?= $news_html ?></div><?php endif; ?>
        </article>
    </div>
</section>

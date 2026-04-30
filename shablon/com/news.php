<?php
/**
 * /shablon/com/news.php
 */

$news_row = isset($GLOBALS['news_row']) && is_array($GLOBALS['news_row']) ? $GLOBALS['news_row'] : [];
$news_name = htmlspecialchars(trim((string)($news_row['name'] ?? '')), ENT_QUOTES, 'UTF-8');
$news_mini_desc = trim((string)($news_row['description'] ?? ''));
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

$news_id = isset($news_row['id']) ? (int)$news_row['id'] : 0;
$news_photos = [];
if ($news_id > 0) {
    $res_photos = _DB(
        "SELECT id
         FROM a_photo
         WHERE a_menu_id = 8
           AND row_id = ?
         ORDER BY sid ASC, id ASC",
        [$news_id]
    );
    if ($res_photos) {
        while ($photo_row = $res_photos->fetch(PDO::FETCH_ASSOC)) {
            $photo_id = isset($photo_row['id']) ? (int)$photo_row['id'] : 0;
            if ($photo_id > 0) {
                $news_photos[] = $photo_id;
            }
        }
    }
}
?>
<section class="news-page">
    <div class="container">
        <article class="news-page__card">
            <?php if ($news_date_view !== ''): ?><div class="news-page__date"><?= htmlspecialchars($news_date_view, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <h1 class="news-page__title"><?= $news_name ?></h1>
            <?php if ($news_mini_desc !== ''): ?><div class="news-page__mini"><?= nl2br(htmlspecialchars($news_mini_desc, ENT_QUOTES, 'UTF-8')) ?></div><?php endif; ?>
            <?php if (!empty($news_photos)): ?>
                <div class="news-gallery js-news-gallery" data-index="0">
                    <div class="news-gallery__viewport">
                        <?php foreach ($news_photos as $photo_k => $photo_id): ?>
                            <img class="news-gallery__img<?= $photo_k === 0 ? ' is-active' : '' ?>" src="/?com=i&id=<?= (int)$photo_id ?>" alt="<?= $news_name ?>" data-full-src="/?com=i&id=<?= (int)$photo_id ?>">
                        <?php endforeach; ?>
                        <?php if (count($news_photos) > 1): ?>
                            <button class="news-gallery__nav news-gallery__nav_prev js-news-gallery-prev" type="button">‹</button>
                            <button class="news-gallery__nav news-gallery__nav_next js-news-gallery-next" type="button">›</button>
                        <?php endif; ?>
                    </div>
                    <div class="news-gallery__thumbs">
                        <?php foreach ($news_photos as $photo_k => $photo_id): ?>
                            <button class="news-gallery__thumb<?= $photo_k === 0 ? ' is-active' : '' ?> js-news-gallery-thumb" type="button" data-index="<?= (int)$photo_k ?>">
                                <img src="/?com=i&id=<?= (int)$photo_id ?>" alt="<?= $news_name ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($news_html !== ''): ?><div class="news-page__html"><?= $news_html ?></div><?php endif; ?>
        </article>
    </div>
</section>
<div class="news-lightbox js-news-lightbox"><div class="news-lightbox__back js-news-lightbox-close"></div><img class="news-lightbox__img js-news-lightbox-img" src="" alt=""><button class="news-lightbox__close js-news-lightbox-close" type="button">×</button></div>

<?php
/**
 * /shablon/s_mod/news.php
 */
$news_cur_key = isset($cur_key) ? (int)$cur_key : 0;
?>
<section class="mod-news">
    <div class="container">
        <div class="mod-news__head">
            <h2 class="mod-news__title">Новости</h2>
        </div>
        <div class="mod-news__list js-news-list" data-cur-key="<?= $news_cur_key ?>">
            <div class="mod-news__loading">Загрузка новостей...</div>
        </div>
    </div>
</section>

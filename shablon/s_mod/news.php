<?php
$news_cur_key = isset($cur_key) ? (int)$cur_key : 0;
?>
<section class="mod-news2">
  <div class="container mod-news2__grid">
    <aside class="mod-news2__sidebar js-news2-box" data-cur-key="<?= $news_cur_key ?>" data-tip-id="0" data-sort="new" data-q="">
      <div class="mod-news2__filter_title">Фильтры</div>
      <input class="mod-news2__search js-news2-search" type="text" placeholder="Поиск новостей...">
      <div class="mod-news2__label">Теги</div>
      <div class="mod-news2__tags js-news2-tags"><button class="mod-news2__tag is-active" type="button">Все</button></div>
      <div class="mod-news2__label">Сортировка</div>
      <select class="mod-news2__sort js-news2-sort">
        <option value="new">Сначала Новые</option>
        <option value="old">Сначала Старые</option>
      </select>
    </aside>
    <div class="mod-news2__main">
      <h2 class="mod-news2__title">Новости сервера</h2>
      <div class="mod-news2__list js-news2-list"><div class="mod-news__loading">Загрузка новостей...</div></div>
    </div>
  </div>
</section>

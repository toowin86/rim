<?php
/**
    * /shablon/s_mod/events.php
    * $cur_key - key текущей страницы в структуре $struktura['name'][$cur_key]
    * $cur_key_mod - key текущего модуля в текущей страницы в структуре $struktura['modules'][$cur_key][$cur_key_mod]
*/

$mod_html = '';

if (isset($struktura)
and isset($struktura['modules'])
and isset($struktura['modules'][$cur_key])
and isset($struktura['modules'][$cur_key][$cur_key_mod])
and isset($struktura['modules'][$cur_key][$cur_key_mod]['html_code'])){
    $mod_html = $struktura['modules'][$cur_key][$cur_key_mod]['html_code'];
}

$event_images = array();

if (isset($struktura) && function_exists('get_struktura_images_by_comment_prefix')) {
    $event_images = get_struktura_images_by_comment_prefix($struktura, (int)$cur_key, 'event:', 'Прочее');

    if (empty($event_images)) {
        $event_images = get_struktura_images_by_comment_prefix($struktura, (int)$cur_key, '', 'Прочее');
        if (count($event_images) > 3) {
            $event_images = array_slice($event_images, 0, 3);
        }
    }
}
?>
<section id="mod-events" class="mod-media mod-events">
    <div class="container">
        <div class="mod-media__grid mod-media__grid_event">
            <div class="mod-media__media">
                <?php if (!empty($event_images)): ?>
                    <div class="mod-media-slider js-mod-media-slider" data-index="0">
                        <div class="mod-media-slider__slides">
                            <?php foreach ($event_images as $event_key => $event_img): ?>
                                <div class="mod-media-slider__slide<?= $event_key === 0 ? ' is-active' : '' ?>" aria-hidden="<?= $event_key === 0 ? 'false' : 'true' ?>">
                                    <img src="<?= htmlspecialchars($event_img['url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($event_img['caption'] !== '' ? $event_img['caption'] : 'Ивенты и квесты', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($event_images) > 1): ?>
                            <button class="mod-media-slider__nav mod-media-slider__nav_prev js-mod-media-prev" type="button" aria-label="Предыдущее изображение">‹</button>
                            <button class="mod-media-slider__nav mod-media-slider__nav_next js-mod-media-next" type="button" aria-label="Следующее изображение">›</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mod-media__empty">Для блока ивентов добавьте фото типа «Прочее» с комментарием, начинающимся на event:</div>
                <?php endif; ?>
            </div>

            <div class="mod-media__content mod-media__content_event">
                <?= $mod_html; ?>
            </div>
        </div>
    </div>
</section>

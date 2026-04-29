<?php
/**
    * /shablon/s_mod/gameplay.php
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

$gameplay_images = array();

if (isset($struktura) && function_exists('get_struktura_images_by_comment_prefix')) {
    $gameplay_images = get_struktura_images_by_comment_prefix($struktura, (int)$cur_key, 'gameplay:', 'Прочее');

    if (empty($gameplay_images)) {
        $fallback_gameplay_images = get_struktura_images_by_comment_prefix($struktura, (int)$cur_key, '', 'Прочее');
        if (!empty($fallback_gameplay_images)) {
            $gameplay_images[] = end($fallback_gameplay_images);
        }
    }
}
?>
<section id="mod-gameplay" class="mod-media mod-gameplay">
    <div class="container">
        <div class="mod-media__grid mod-media__grid_gameplay">
            <div class="mod-media__content mod-media__content_gameplay">
                <?= $mod_html; ?>
            </div>

            <div class="mod-media__media">
                <?php if (!empty($gameplay_images)): ?>
                    <?php $gameplay_img = reset($gameplay_images); ?>
                    <div class="mod-media__frame mod-media__frame_gameplay">
                        <img src="<?= htmlspecialchars($gameplay_img['url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($gameplay_img['caption'] !== '' ? $gameplay_img['caption'] : 'Геймплей', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                <?php else: ?>
                    <div class="mod-media__empty">Для блока геймплея добавьте фото типа «Прочее» с комментарием, начинающимся на gameplay:</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php
/**
    * /shablon/s_mod/about.php
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



?>
<section id="mod-about" class="mod-about">
    <?=$mod_html;?>
</section>
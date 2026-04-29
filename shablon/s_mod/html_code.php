<?php
/**
    * /shablon/s_mod/html_code.php

 * Модуль вывода HTML-кода страницы.
 */

$mod_html = '';

if (isset($struktura)
and isset($struktura['html_code'])
and isset($struktura['html_code'][$cur_key])
){
    $mod_html = $struktura['html_code'][$cur_key];
}
?>

<section id="mod-html_code" class="mod-html_code">
    <div class="mod-html_code__inner"><?=$mod_html;?></div>
</section>

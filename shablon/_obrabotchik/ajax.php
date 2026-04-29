<?php
/**
 * /shablon/_obrabotchik/ajax.php

 */

$ajax=_GP('ajax');
if ($ajax !== '') {
    // Базовая защита от инъекций: разрешаем только латиницу, цифры, дефис и подчеркивание
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $ajax)) {
        err_404('Некорректное имя модуля: ' . htmlspecialchars($ajax, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'ajax | invalid');
    }
    
    // Проверяем физическое наличие файлов модуля (в обработчиках)
    $file_ajax = __DIR__ . '/../ajax/' . $ajax . '.php';

    if (is_file($file_ajax) && is_readable($file_ajax)) {
        require_once $file_ajax;
    }
}else{
    err_404('$ajax=""'  , 'ajax | null');
}
exit;
?>
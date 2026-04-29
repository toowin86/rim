<?php
/**
 * index.php — входная точка сайта.
 * - роутинг ЧПУ /{struktura_url}/ и /{struktura_url}/{s_cat_id}
 * - роутинг модулей через ?com=...
 *
 * Подключает functions.php, ddos.php, db.php; создаёт сессию;
 * формирует $_SESSION['a_options'] и $_SESSION['s_words']; получает $s_struktura_id, $s_cat_id, $com.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ddos.php';
require_once __DIR__ . '/db.php';
session_start();
// Формируем массив глобальных настроек сайта в сессии

//проверка заполненности сайта
$res_cnt = _DB("SELECT COUNT(*) AS cnt FROM s_struktura");
if ($res_cnt) {
    $row = $res_cnt->fetch(PDO::FETCH_ASSOC);
    if ($row['cnt']==0){
        err_404('Не заполнена структура сайта', 'struktura | none');
    }
}else{
    err_404('Не заполнена база данных', 'db | none');
}

$_SESSION['a_options'] = [];
$res_options = _DB("SELECT name, val FROM a_options");
if ($res_options) {
    while ($row = $res_options->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['a_options'][$row['name']] = $row['val'];
    }
}

// Формируем словарь локализации
$_SESSION['s_words'] = [];
// ИСПРАВЛЕНИЕ: берем html_code вместо val
$res_words = _DB("SELECT name, html_code FROM s_words"); 
if ($res_words) {
    while ($row = $res_words->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['s_words'][$row['name']] = $row['html_code'];
    }
}


// Проверка и выполнение постоянных редиректов
check_redirects();
    
//Получаем массив филиалов
$i_tp_arr = get_i_tp_arr();

// Получаем полную структуру сайта из БД до обработки URL
$struktura = get_base_strurkura();
//print_rf($struktura);exit;

// Выполняем роутинг, валидацию и редиректы (включая проверку com), получаем город для использования при формировании $srtuktura[full_url]
$route_params = parse_url_logic($struktura, $i_tp_arr);

// Извлекаем финальные валидные переменные

$s_struktura_id = $route_params['s_struktura_id'];
$s_cat_id       = $route_params['s_cat_id'];
$com            = $route_params['com'];
$i_tp_id        = $route_params['i_tp_id']; // ID текущего филиала!
$is_first_page = ($route_params['true_url'] == '/' && (int)$s_cat_id === 0);


// Базовый URL сайта для <base href="...">
$protacol = get_protocol();

$host = '';
if (!empty($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
} elseif (!empty($_SERVER['SERVER_NAME'])) {
    $host = $_SERVER['SERVER_NAME'];
}

$site_url = '/';
if ($host !== '') {
    $site_url = rtrim($protacol . $host, '/') . '/';
}


$cur_key = $struktura['id_to_key'][$s_struktura_id]; //текущий ключ страницы структуры

// Генерируем full_url с учетом активного города и добавляем в массив структуры
$struktura['full_url'] = create_full_url($struktura, $i_tp_arr, $i_tp_id);
$struktura['hleb'] = create_hleb($struktura);

// =========================================================================
// ПОДКЛЮЧЕНИЕ ОБРАБОТЧИКА
// =========================================================================


//общий обработчик
$file_obrabotchik =  __DIR__ . '/shablon/_obrabotchik.php';
if (is_file($file_obrabotchik) && is_readable($file_obrabotchik)) {
    require_once $file_obrabotchik;
}else{
    err_404('Не найден файл '.$file_obrabotchik, '_obrabotchik | none');
}

// =========================================================================
// ПОДКЛЮЧЕНИЕ ШАБЛОНА
// =========================================================================
$file_shablon =  __DIR__ . '/shablon/shablon.php';
if (is_file($file_shablon) && is_readable($file_shablon)) {
    require_once $file_shablon;
}else{
    err_404('Не найден файл '.$file_shablon, 'shablon | none');
}



?>
<?php
/**
 * /shablon/_obrabotchik.php
 */

// 1. Инициализация глобального массива обработчика
$site_name = $_SESSION['a_options']['Название сайта'] ?? '';

// Канонический URL: строим из эталонного пути (из роутера)
$canonical_url = $protacol . ($_SERVER['HTTP_HOST'] ?? '') . ($route_params['true_url'] ?? $_SERVER['REQUEST_URI']);

$_obrabotchik = [
    'site_name'         => $site_name,
    'title'             => $site_name,
    'description'       => '',
    'keywords'          => '',
    'robots'            => 'index,follow',
    'og_url'            => $canonical_url,
    'canonical'         => $canonical_url,

    // Служебные флаги для шаблона
    'com_file'          => '',
    'load_default_page' => 1
];


// 2. Логика ?com=
if ($com !== '') {

    // По ТЗ: com только цифры, английские буквы и _
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $com)) {
        err_404(
            'Некорректное имя модуля: ' . htmlspecialchars($com, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'com | invalid'
        );
    }

    $file_obrabotchik = __DIR__ . '/_obrabotchik/' . $com . '.php';
    $file_com = __DIR__ . '/com/' . $com . '.php';

    $has_obrabotchik = is_file($file_obrabotchik) && is_readable($file_obrabotchik);
    $has_com_file = is_file($file_com) && is_readable($file_com);

    // Если нет ни обработчика, ни файла компонента
    if (!$has_obrabotchik && !$has_com_file) {
        err_404(
            'Отсутствует модуль: ' . htmlspecialchars($com, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'com | no file'
        );
    }

    // По твоему условию: shablon/com/имя.php без _obrabotchik/имя.php быть не может
    if (!$has_obrabotchik && $has_com_file) {
        err_404(
            'Для модуля отсутствует обработчик: ' . htmlspecialchars($com, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'com | no obrabotchik'
        );
    }

    // Сначала всегда подключаем обработчик
    if ($has_obrabotchik) {
        require_once $file_obrabotchik;
    }


    // Если есть shablon/com/имя.php — это отдельная com-страница
    if ($has_com_file) {
        $_obrabotchik['com_file'] = $file_com;
        $_obrabotchik['load_default_page'] = 0;
    }

    // Если файла shablon/com/имя.php нет,
    // то остается load_default_page = 1,
    // и ниже SEO будет взят от обычной страницы структуры/товара
}


// 3. SEO для обычной страницы
// Сюда попадаем:
// - если com вообще нет
// - если com есть, но shablon/com/имя.php нет, и надо грузить обычную страницу
if ($_obrabotchik['load_default_page'] == 1) {

    if ($s_cat_id > 0) {
        $seo_data = get_seo_s_cat($s_cat_id);
        $product_name = trim((string)($seo_data['name'] ?? ''));

        $_obrabotchik['title'] = $product_name !== '' ? $product_name : 'Товар';
        $_obrabotchik['description'] = $product_name !== '' ? 'Купить ' . $product_name . ' по выгодной цене.' : '';
        $_obrabotchik['keywords'] = $product_name !== '' ? mb_strtolower($product_name, 'UTF-8') . ', купить, заказать' : '';

        if (
            isset($_SESSION['a_options']['SEO: Добавлять основные свойства товара или услуги в конец заголовка']) &&
            $_SESSION['a_options']['SEO: Добавлять основные свойства товара или услуги в конец заголовка'] == '1'
        ) {
            $props_str = get_product_properties_string($s_cat_id);
            if ($props_str !== '') {
                $_obrabotchik['title'] .= ' - ' . $props_str;
            }
        }
    }
    elseif ($s_struktura_id > 0) {
        $seo_data = get_seo_struktura($s_struktura_id);

        if (!empty($seo_data['title'])) {
            $_obrabotchik['title'] = $seo_data['title'];
        }
        elseif (!empty($seo_data['page_name'])) {
            $_obrabotchik['title'] = $seo_data['page_name'];
        }
        else {
            $_obrabotchik['title'] = $seo_data['name'] ?? $site_name;
        }

        $_obrabotchik['description'] = $seo_data['description'] ?? '';
        $_obrabotchik['keywords'] = $seo_data['keywords'] ?? '';
    }
}


// 4. Единая нормализация ВСЕХ SEO-данных перед выводом в атрибуты
$_obrabotchik['site_name'] = htmlspecialchars(strip_tags(trim((string)$_obrabotchik['site_name'])), ENT_QUOTES, 'UTF-8');
$_obrabotchik['title'] = htmlspecialchars(strip_tags(trim((string)$_obrabotchik['title'])), ENT_QUOTES, 'UTF-8');
$_obrabotchik['description'] = htmlspecialchars(strip_tags(trim((string)$_obrabotchik['description'])), ENT_QUOTES, 'UTF-8');
$_obrabotchik['keywords'] = htmlspecialchars(strip_tags(trim((string)$_obrabotchik['keywords'])), ENT_QUOTES, 'UTF-8');

$_obrabotchik['robots'] = (isset($_obrabotchik['robots']) && trim((string)$_obrabotchik['robots']) !== '')
    ? htmlspecialchars(trim((string)$_obrabotchik['robots']), ENT_QUOTES, 'UTF-8')
    : 'index,follow';

// Обязательное экранирование URL для вставки в атрибуты
$_obrabotchik['canonical'] = htmlspecialchars(trim((string)$_obrabotchik['canonical']), ENT_QUOTES, 'UTF-8');
$_obrabotchik['og_url'] = htmlspecialchars(trim((string)$_obrabotchik['og_url']), ENT_QUOTES, 'UTF-8');


// 5. Подготовка контактов и футера
$_obrabotchik['contact_city'] = $i_tp_arr['i_city_name'][$i_tp_id] ?? '';
$_obrabotchik['contact_address'] = $i_tp_arr['adress'][$i_tp_id] ?? '';
$_obrabotchik['contact_phone'] = $i_tp_arr['phone'][$i_tp_id] ?? '';
$_obrabotchik['contact_worktime'] = $i_tp_arr['worktime'][$i_tp_id] ?? '';

$_obrabotchik['footer_text'] = $_SESSION['s_words']['Текст в футере'] ?? '';
?>
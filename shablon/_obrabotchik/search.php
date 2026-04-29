<?php
/**
 * /shablon/_obrabotchik/search.php
 */

$search_query = trim((string)_GP('q')); // Сырой запрос

if ($search_query !== '') {
    // Никакого экранирования здесь!
    $_obrabotchik['title']       = 'Поиск: ' . $search_query;
    $_obrabotchik['description'] = 'Результаты поиска по запросу «' . $search_query . '» в каталоге товаров.';
} else {
    $_obrabotchik['title']       = 'Поиск по сайту';
    $_obrabotchik['description'] = 'Результаты поиска по каталогу и страницам сайта.';
}

$_obrabotchik['keywords'] = 'поиск, каталог, найти';
$_obrabotchik['robots']   = 'noindex,follow';


?>
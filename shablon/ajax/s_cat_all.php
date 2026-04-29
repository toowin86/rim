<?php
/**
 * /shablon/ajax/s_cat_all.php
 * Универсальная ajax-загрузка товаров для s_cat_all и s_cat_slider
 */

if (!function_exists('s_cat_all_json_exit')) {
    function s_cat_all_json_exit($arr)
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('s_cat_all_h')) {
    function s_cat_all_h($str)
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('s_cat_all_parse_float')) {
    function s_cat_all_parse_float($value)
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', $value);

        if (!is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }
}

if (!function_exists('s_cat_all_parse_int_array')) {
    function s_cat_all_parse_int_array($value)
    {
        $result = [];

        if (is_array($value)) {
            foreach ($value as $v) {
                $v = (int)$v;
                if ($v > 0) {
                    $result[] = $v;
                }
            }
        } else {
            $value = trim((string)$value);
            if ($value !== '') {
                $parts = explode(',', $value);
                foreach ($parts as $v) {
                    $v = (int)trim($v);
                    if ($v > 0) {
                        $result[] = $v;
                    }
                }
            }
        }

        $result = array_values(array_unique($result));
        return $result;
    }
}

if (!function_exists('s_cat_all_get_sort_sql')) {
    function s_cat_all_get_sort_sql($sort)
    {
        $sort = trim((string)$sort);

        $map = [
            'price_asc' => 'c.price ASC, c.id ASC',
            'price_desc' => 'c.price DESC, c.id DESC',
            'name_asc' => 'c.name ASC, c.id ASC',
            'name_desc' => 'c.name DESC, c.id DESC',
            'date_asc' => 'COALESCE(c.data_change, c.data_create) ASC, c.id ASC',
            'date_desc' => 'COALESCE(c.data_change, c.data_create) DESC, c.id DESC',
        ];

        return isset($map[$sort]) ? $map[$sort] : $map['date_desc'];
    }
}

if (!function_exists('s_cat_all_get_section_ids_tree')) {
    function s_cat_all_get_section_ids_tree($root_id, $struktura)
    {
        $root_id = (int)$root_id;
        if ($root_id <= 0) {
            return [];
        }

        if (
            !isset($struktura['id_to_key'])
            || !is_array($struktura['id_to_key'])
            || !isset($struktura['id_to_key'][$root_id])
        ) {
            return [$root_id];
        }

        $children_map = [];
        if (isset($struktura['pid']) && is_array($struktura['pid']) && isset($struktura['id']) && is_array($struktura['id'])) {
            foreach ($struktura['pid'] as $key => $pid) {
                $pid = (int)$pid;
                if (!isset($struktura['id'][$key])) {
                    continue;
                }

                $id = (int)$struktura['id'][$key];
                if ($id <= 0) {
                    continue;
                }

                if (!isset($children_map[$pid])) {
                    $children_map[$pid] = [];
                }

                $children_map[$pid][] = $id;
            }
        }

        $result = [];
        $queue = [$root_id];
        $guard = 0;

        while (!empty($queue) && $guard < 10000) {
            $guard++;
            $current = array_shift($queue);
            $current = (int)$current;

            if ($current <= 0 || isset($result[$current])) {
                continue;
            }

            $result[$current] = $current;

            if (isset($children_map[$current]) && is_array($children_map[$current])) {
                foreach ($children_map[$current] as $child_id) {
                    $child_id = (int)$child_id;
                    if ($child_id > 0 && !isset($result[$child_id])) {
                        $queue[] = $child_id;
                    }
                }
            }
        }

        return array_values($result);
    }
}

if (!function_exists('s_cat_all_get_prop_groups')) {
    function s_cat_all_get_prop_groups($prop_val_ids)
    {
        $prop_val_ids = s_cat_all_parse_int_array($prop_val_ids);
        if (empty($prop_val_ids)) {
            return [];
        }

        $in = implode(',', array_fill(0, count($prop_val_ids), '?'));
        $sql = "SELECT id, s_prop_id FROM s_prop_val WHERE id IN ($in)";
        $res = _DB($sql, $prop_val_ids);

        $groups = [];
        if ($res) {
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $val_id = isset($row['id']) ? (int)$row['id'] : 0;
                $prop_id = isset($row['s_prop_id']) ? (int)$row['s_prop_id'] : 0;

                if ($val_id <= 0 || $prop_id <= 0) {
                    continue;
                }

                if (!isset($groups[$prop_id])) {
                    $groups[$prop_id] = [];
                }

                $groups[$prop_id][] = $val_id;
            }
        }

        foreach ($groups as $prop_id => $vals) {
            $groups[$prop_id] = array_values(array_unique($vals));
        }

        return $groups;
    }
}

if (!function_exists('s_cat_all_build_item_url')) {
    function s_cat_all_build_item_url($item_id, $section_ids_csv, $struktura)
    {
        $item_id = (int)$item_id;
        if ($item_id <= 0) {
            return '/';
        }

        $section_ids = [];
        $section_ids_csv = trim((string)$section_ids_csv);

        if ($section_ids_csv !== '') {
            $tmp = explode(',', $section_ids_csv);
            foreach ($tmp as $sid) {
                $sid = (int)trim($sid);
                if ($sid > 0) {
                    $section_ids[] = $sid;
                }
            }
        }

        $section_ids = array_values(array_unique($section_ids));

        if (
            empty($section_ids)
            || !isset($struktura['id_to_key'])
            || !is_array($struktura['id_to_key'])
        ) {
            return '?com=market&buy&id=' . $item_id;
        }

        $best_key = false;
        $best_depth = -1;

        foreach ($section_ids as $sid) {
            if (!isset($struktura['id_to_key'][$sid])) {
                continue;
            }

            $key = $struktura['id_to_key'][$sid];

            if (!isset($struktura['chk_active'][$key]) || (int)$struktura['chk_active'][$key] !== 1) {
                continue;
            }

            $full_url = isset($struktura['full_url'][$key]) ? (string)$struktura['full_url'][$key] : '';
            if ($full_url === '' || mb_strpos($full_url, '#') === 0 || preg_match('/^(https?:)?\/\//iu', $full_url)) {
                continue;
            }

            $depth = function_exists('get_section_depth') ? (int)get_section_depth($sid, $struktura) : 0;
            if ($depth > $best_depth) {
                $best_depth = $depth;
                $best_key = $key;
            }
        }

        if ($best_key === false) {
            return '?com=market&buy&id=' . $item_id;
        }

        $url = isset($struktura['full_url'][$best_key]) ? (string)$struktura['full_url'][$best_key] : '/';
        $url = rtrim($url, '/');

        if ($url === '') {
            $url = '';
        }

        return $url . '/' . $item_id;
    }
}

$action = trim((string)_GP('_t'));
if ($action !== 'list') {
    s_cat_all_json_exit([
        'status_' => 'error',
        'message' => 'Неизвестное действие s_cat_all'
    ]);
}

$view = trim((string)_GP('view'));
if ($view === '') {
    $view = 'grid';
}

$page = (int)_GP('page');
if ($page < 1) {
    $page = 1;
}

$per_page = (int)_GP('per_page');
if ($per_page < 1) {
    $per_page = 12;
}
if ($per_page > 50) {
    $per_page = 50;
}

$sort = trim((string)_GP('sort'));
$sort_field = trim((string)_GP('sort_field'));
$sort_dir = trim((string)_GP('sort_dir'));

if ($sort === '' && $sort_field !== '') {
    if ($sort_field === 'price') {
        $sort = ($sort_dir === 'asc') ? 'price_asc' : 'price_desc';
    } elseif ($sort_field === 'name') {
        $sort = ($sort_dir === 'desc') ? 'name_desc' : 'name_asc';
    } elseif ($sort_field === 'date_change') {
        $sort = ($sort_dir === 'asc') ? 'date_asc' : 'date_desc';
    }
}

$price_from = s_cat_all_parse_float(_GP('price_from'));
$price_to = s_cat_all_parse_float(_GP('price_to'));

$s_struktura_filter = _GP('s_struktura_id');
if ($s_struktura_filter === '' && isset($s_struktura_id)) {
    $s_struktura_filter = (int)$s_struktura_id;
}
$s_struktura_filter = (int)$s_struktura_filter;

$prop_val_ids = s_cat_all_parse_int_array(_GP('prop_val_id', []));
if (empty($prop_val_ids)) {
    $prop_val_ids = s_cat_all_parse_int_array(_GP('prop_val_ids', []));
}

$where = [];
$params = [];

$where[] = 'c.chk_active = 1';

if ($price_from !== null) {
    $where[] = 'c.price >= ?';
    $params[] = $price_from;
}

if ($price_to !== null) {
    $where[] = 'c.price <= ?';
    $params[] = $price_to;
}

if ($s_struktura_filter !== -1) {
    $section_ids = [];

    if (isset($struktura) && is_array($struktura)) {
        $section_ids = s_cat_all_get_section_ids_tree($s_struktura_filter, $struktura);
    } else {
        if ($s_struktura_filter > 0) {
            $section_ids = [$s_struktura_filter];
        }
    }

    if (!empty($section_ids)) {
        $in = implode(',', array_fill(0, count($section_ids), '?'));
        $where[] = "EXISTS (
            SELECT 1
            FROM s_cat_s_struktura ss
            WHERE ss.id1 = c.id
              AND ss.id2 IN ($in)
        )";

        foreach ($section_ids as $sid) {
            $params[] = (int)$sid;
        }
    }
}

$prop_groups = s_cat_all_get_prop_groups($prop_val_ids);
if (!empty($prop_groups)) {
    foreach ($prop_groups as $prop_id => $vals) {
        if (empty($vals)) {
            continue;
        }

        $in = implode(',', array_fill(0, count($vals), '?'));
        $where[] = "EXISTS (
            SELECT 1
            FROM s_cat_s_prop_val link
            WHERE link.id1 = c.id
              AND link.id2 IN ($in)
        )";

        foreach ($vals as $val_id) {
            $params[] = (int)$val_id;
        }
    }
}

$where_sql = implode(' AND ', $where);
$order_sql = s_cat_all_get_sort_sql($sort);
$offset = ($page - 1) * $per_page;
$offset = (int)$offset;

$sql_count = "
    SELECT COUNT(*)
    FROM s_cat c
    WHERE $where_sql
";

$res_count = _DB($sql_count, $params);
$total = 0;

if ($res_count) {
    $total = (int)$res_count->fetchColumn();
}

$sql_list = "
    SELECT
        c.id,
        c.name,
        c.price,
        c.mini_desc,
        c.tip,
        c.kolvo,
        c.data_create,
        c.data_change,
         (
            SELECT v.val
            FROM s_cat_s_prop_val link
            JOIN s_prop_val v ON v.id = link.id2
            JOIN s_prop p ON p.id = v.s_prop_id
            WHERE link.id1 = c.id
              AND p.name = 'Количество устройств'
            LIMIT 1
        ) AS device_count,
        (
            SELECT ap.id
            FROM a_photo ap
            WHERE ap.a_menu_id = 7
              AND ap.row_id = c.id
            ORDER BY ap.sid ASC, ap.id ASC
            LIMIT 1
        ) AS img_id,
        (
            SELECT GROUP_CONCAT(ss.id2 ORDER BY ss.id2 ASC SEPARATOR ',')
            FROM s_cat_s_struktura ss
            WHERE ss.id1 = c.id
        ) AS section_ids
    FROM s_cat c
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT $per_page OFFSET $offset
";

$res_list = _DB($sql_list, $params);

ob_start();

if ($res_list && $total > 0) {
    while ($row = $res_list->fetch(PDO::FETCH_ASSOC)) {
        $item_id = isset($row['id']) ? (int)$row['id'] : 0;
        $item_name = isset($row['name']) ? trim((string)$row['name']) : '';
        $item_tip = isset($row['tip']) ? trim((string)$row['tip']) : '';
        $item_desc = isset($row['mini_desc']) ? trim((string)$row['mini_desc']) : '';
        $item_price = isset($row['price']) && is_numeric($row['price']) ? (float)$row['price'] : 0;
        $item_img_id = isset($row['img_id']) ? (int)$row['img_id'] : 0;
        $item_kolvo = isset($row['kolvo']) ? (float)$row['kolvo'] : 0;
        $item_url = s_cat_all_build_item_url($item_id, isset($row['section_ids']) ? $row['section_ids'] : '', isset($struktura) && is_array($struktura) ? $struktura : []);
        $item_buy_url = '?com=market&buy&id=' . $item_id . '&kolvo=1';
        $item_price_html = ($item_price > 0) ? number_format($item_price, 0, '.', ' ') . ' <span>₽</span>' : 'По запросу';
        $item_stock_text = ($item_kolvo > 0) ? 'В наличии' : 'Под заказ';
        $item_img_class = 's_cat_all-card__img';
        if ($item_img_id <= 0) {
            $item_img_class .= ' s_cat_all-card__img--empty';
        }
        $item_device_count = isset($row['device_count']) ? trim((string)$row['device_count']) : '';
        
        
        if ($view === 'slider') {
            $item_title_url = $item_url;
            $fallback_detail_url = '';
        
            if (
                isset($s_struktura_filter)
                && (int)$s_struktura_filter > 0
                && isset($struktura)
                && is_array($struktura)
                && isset($struktura['id_to_key'])
                && isset($struktura['id_to_key'][(int)$s_struktura_filter])
            ) {
                $section_key = $struktura['id_to_key'][(int)$s_struktura_filter];
                $section_url = isset($struktura['full_url'][$section_key]) ? trim((string)$struktura['full_url'][$section_key]) : '';
        
                if ($section_url !== '' && mb_strpos($section_url, '#') !== 0 && !preg_match('/^(https?:)?\/\//iu', $section_url)) {
                    $fallback_detail_url = rtrim($section_url, '/') . '/' . $item_id;
                }
            }
        
            if (
                $item_title_url === ''
                || mb_strpos($item_title_url, '?com=market&buy&id=') === 0
            ) {
                if ($fallback_detail_url !== '') {
                    $item_title_url = $fallback_detail_url;
                }
            }
        
        
            $price_num = ($item_price > 0) ? number_format($item_price, 0, '.', ' ') : '0';
            ?>
            <div class="s_cat_slider__slide">
                <div class="s_cat_slider__card" data-id="<?= $item_id ?>">
      
                    <?php if ($item_device_count !== '') { ?>
                        <div class="s_cat_slider__badge">
                            <?= s_cat_all_h($item_device_count).' устройств'.end_word($item_device_count,'','о','а') ?>
                       </div>
                    <?php } ?>
                    
        
                    <a class="s_cat_slider__title js-s_cat_slider_more" href="<?= s_cat_all_h($item_title_url) ?>" data-no-drag="1"><?= s_cat_all_h($item_name) ?></a>
        
                    <div class="s_cat_slider__price">
                        <span class="s_cat_slider__price_num"><?= $price_num ?></span>
                        <span class="s_cat_slider__price_currency">руб.</span>
                    </div>
        
                    <?php if ($item_desc !== '') { ?>
                        <div class="s_cat_slider__desc"><?= nl2br(s_cat_all_h($item_desc)) ?></div>
                    <?php } else { ?>
                        <div class="s_cat_slider__desc">&nbsp;</div>
                    <?php } ?>
        
                    <div class="s_cat_slider__actions">
                        <a class="btn s_cat_slider__buy js-s_cat_slider_buy" href="<?= s_cat_all_h($item_buy_url) ?>" data-id="<?= $item_id ?>" data-no-drag="1">КУПИТЬ</a>
                        <a class="s_cat_slider__more js-s_cat_slider_more" href="<?= s_cat_all_h($item_title_url) ?>" data-no-drag="1">Подробнее</a>
                    </div>
                </div>
            </div>
            <?php
        }  else {
            ?>
            <div class="s_cat_all-card" data-id="<?= $item_id ?>">
                <?php if ($item_tip !== '') { ?>
                    <div class="s_cat_all-card__type"><?= s_cat_all_h($item_tip) ?></div>
                <?php } ?>

                <a class="<?= $item_img_class ?>" href="<?= s_cat_all_h($item_url) ?>">
                    <?php if ($item_img_id > 0) { ?>
                        <img src="?com=i&id=<?= $item_img_id ?>" alt="<?= s_cat_all_h($item_name) ?>">
                    <?php } else { ?>
                        Нет изображения
                    <?php } ?>
                </a>

                <a class="s_cat_all-card__title" href="<?= s_cat_all_h($item_url) ?>"><?= s_cat_all_h($item_name) ?></a>
                <div class="s_cat_all-card__meta"><?= s_cat_all_h($item_stock_text) ?></div>
                <div class="s_cat_all-price"><?= $item_price_html ?></div>

                <?php if ($item_desc !== '') { ?>
                    <div class="s_cat_all-card__desc"><?= nl2br(s_cat_all_h($item_desc)) ?></div>
                <?php } else { ?>
                    <div class="s_cat_all-card__desc"></div>
                <?php } ?>

                <a class="btn" href="<?= s_cat_all_h($item_url) ?>">Подробнее</a>
            </div>
            <?php
        }
    }
} else {
    if ($view === 'slider') {
        echo '<div class="s_cat_slider__empty">Товары не найдены</div>';
    } else {
        echo '<div class="s_cat_all-empty">Товары не найдены</div>';
    }
}

$html = ob_get_clean();

$loaded_to = $offset + $per_page;
if ($loaded_to > $total) {
    $loaded_to = $total;
}

s_cat_all_json_exit([
    'status_' => 'ok',
    'view' => $view,
    'html' => $html,
    'page' => $page,
    'per_page' => $per_page,
    'total' => $total,
    'loaded_to' => $loaded_to,
    'has_more' => ($loaded_to < $total) ? 1 : 0
]);
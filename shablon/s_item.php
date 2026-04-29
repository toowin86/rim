<?php
/**
 * /s_item.php
 * Шаблон карточки товара / услуги.
 */

if (!isset($s_cat_id) || (int)$s_cat_id <= 0) {
    return;
}

$res_s_item = _DB(
    "SELECT c.id, c.name, c.price, c.html_code, c.mini_desc, c.tip, c.article, c.kolvo
     FROM s_cat c
     WHERE c.id = ? AND c.chk_active = 1
     LIMIT 1",
    [(int)$s_cat_id]
);

if (!$res_s_item) {
    return;
}

$s_item_row = $res_s_item->fetch(PDO::FETCH_ASSOC);
if (!$s_item_row) {
    return;
}

$s_item_name = isset($s_item_row['name']) ? trim((string)$s_item_row['name']) : '';
$s_item_tip = isset($s_item_row['tip']) ? trim((string)$s_item_row['tip']) : '';
$s_item_article = isset($s_item_row['article']) ? trim((string)$s_item_row['article']) : '';
$s_item_mini_desc = isset($s_item_row['mini_desc']) ? trim((string)$s_item_row['mini_desc']) : '';
$s_item_html_code = isset($s_item_row['html_code']) ? (string)$s_item_row['html_code'] : '';
$s_item_kolvo = isset($s_item_row['kolvo']) ? (float)$s_item_row['kolvo'] : 0;
$s_item_price = isset($s_item_row['price']) && is_numeric($s_item_row['price']) ? (float)$s_item_row['price'] : 0;
$s_item_price_view = $s_item_price > 0 ? number_format($s_item_price, 0, '.', ' ') : '';
$s_item_is_service = (mb_strtolower($s_item_tip, 'UTF-8') === 'услуга');

$s_item_props = [];
$res_s_item_props = _DB(
    "SELECT p.name AS prop_name, v.val AS prop_val
     FROM s_cat_s_prop_val link
     JOIN s_prop_val v ON v.id = link.id2
     JOIN s_prop p ON p.id = v.s_prop_id
     WHERE link.id1 = ?
       AND p.chk_active = 1
       AND p.chk_view = 1
     ORDER BY p.chk_main DESC, p.sid ASC, p.id ASC
     LIMIT 12",
    [(int)$s_cat_id]
);

if ($res_s_item_props) {
    while ($row_prop = $res_s_item_props->fetch(PDO::FETCH_ASSOC)) {
        $prop_name = isset($row_prop['prop_name']) ? trim((string)$row_prop['prop_name']) : '';
        $prop_val = isset($row_prop['prop_val']) ? trim((string)$row_prop['prop_val']) : '';

        if ($prop_name === '' || $prop_val === '') {
            continue;
        }

        $s_item_props[] = [
            'name' => $prop_name,
            'val' => $prop_val,
        ];
    }
}

$s_item_hleb = [];

if (isset($struktura) && is_array($struktura) && function_exists('hleb_add_item') && function_exists('render_hleb')) {
    $s_item_key = null;

    if (isset($s_struktura_id) && isset($struktura['id_to_key'][(int)$s_struktura_id])) {
        $s_item_key = $struktura['id_to_key'][(int)$s_struktura_id];
    }
    elseif (isset($cur_key)) {
        $s_item_key = $cur_key;
    }

    if ($s_item_key !== null && isset($struktura['hleb'][$s_item_key]) && is_array($struktura['hleb'][$s_item_key])) {
        $s_item_hleb = $struktura['hleb'][$s_item_key];
    }

    $s_item_hleb = hleb_add_item($s_item_hleb, $s_item_name);
}

?>
<div class="s_item<?= $s_item_is_service ? ' s_item_service' : '' ?>">
    <div class="container">
        <?php if (!empty($s_item_hleb)) { ?>
            <?= render_hleb($s_item_hleb, false) ?>
        <?php } ?>

        <div class="s_item__grid<?= $s_item_is_service ? ' s_item__grid_service' : '' ?>">
            <div class="s_item__content">
                <div class="s_item__head">
                    <?php if ($s_item_tip !== '') { ?>
                        <div class="s_item__type"><?= htmlspecialchars($s_item_tip, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>

                    <?php if ($s_item_article !== '') { ?>
                        <div class="s_item__article">Артикул: <span><?= htmlspecialchars($s_item_article, ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php } ?>
                </div>

                <h1 class="s_item__title"><?= htmlspecialchars($s_item_name, ENT_QUOTES, 'UTF-8') ?></h1>

                <div class="s_item__purchase">
                    <div class="s_item__purchase_top">
                        <?php if ($s_item_price_view !== '') { ?>
                            <div class="s_item__price">
                                <div class="s_item__price_label">Цена</div>
                                <div class="s_item__price_val"><?= $s_item_price_view ?> ₽</div>
                                <div class="s_item__price_note">Актуальная цена на сайте</div>
                            </div>
                        <?php } ?>

                        <div class="s_item__availability">
                            <?php if ($s_item_kolvo > 0) { ?>
                                <div class="s_item__stock">В наличии</div>
                            <?php } else {
                                if ($s_item_tip == 'Услуга') {
                            ?>
                                <div class="s_item__stock">Доступна</div>
                            <?php
                                }
                                else {
                            ?>
                                <div class="s_item__stock s_item__stock_out">Под заказ</div>
                            <?php
                                }
                            } ?>
                        </div>
                    </div>

                    <div class="s_item__actions">
                        <a href="?com=market&buy=1&id=<?= (int)$s_cat_id ?>" class="s_item__buy_btn" onclick="if(typeof market_buy==='function'){market_buy(<?= (int)$s_cat_id ?>,1);return false;}">Купить</a>
                        <div class="s_item__buy_note">Быстрое добавление в корзину и переход к оформлению заказа.</div>
                    </div>
                </div>

                <?php if (!empty($s_item_props)) { ?>
                    <div class="s_item__props">
                        <?php foreach ($s_item_props as $prop) { ?>
                            <div class="s_item__prop">
                                <div class="s_item__prop_name"><?= htmlspecialchars($prop['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="s_item__prop_val"><?= htmlspecialchars($prop['val'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if ($s_item_html_code !== '') { ?>
                    <div class="s_item__html"><?= $s_item_html_code ?></div>
                <?php } elseif ($s_item_mini_desc !== '') { ?>
                    <div class="s_item__html"><p><?= nl2br(htmlspecialchars($s_item_mini_desc, ENT_QUOTES, 'UTF-8')) ?></p></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

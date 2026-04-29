<?php
/**
 * /shablon/shablon.php
 */
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= $site_url ?>" data-protacol="<?= $protacol ?>" />
    
    <title><?= $_obrabotchik['title'] ?></title>
    <?php if ($_obrabotchik['description'] !== ''): ?>
    <meta name="description" content="<?= $_obrabotchik['description'] ?>">
    <?php endif; ?>
    <?php if ($_obrabotchik['keywords'] !== ''): ?>
    <meta name="keywords" content="<?= $_obrabotchik['keywords'] ?>">
    <?php endif; ?>
    
    <meta name="robots" content="<?= $_obrabotchik['robots'] ?>">
    <link rel="canonical" href="<?= $_obrabotchik['canonical'] ?>">
    
    <meta property="og:title" content="<?= $_obrabotchik['title'] ?>">
    <?php if ($_obrabotchik['description'] !== ''): ?>
    <meta property="og:description" content="<?= $_obrabotchik['description'] ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?= $_obrabotchik['og_url'] ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= $_obrabotchik['site_name'] ?>">
    <meta property="og:locale" content="ru_RU">
    <meta name="twitter:card" content="summary_large_image">
    
    <link rel="stylesheet" href="shablon/css/style.css?rand<?= rand(100,999) ?>" type="text/css" />
    <link rel="stylesheet" href="shablon/css/market.css?rand<?= rand(100,999) ?>" type="text/css" />
    <link rel="stylesheet" href="shablon/css/font-awesome.css" type="text/css" />
    <link rel="stylesheet" href="shablon/css/arcticmodal.css" type="text/css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" />

    <?= $_SESSION['s_words']['Скрипты в шапку сайта'] ?? '' ?>
</head>
<body>

    <header class="main-header">
        <div class="container">
            <div class="logo">
                <?= !empty($_SESSION['s_words']['Логотип']) ? $_SESSION['s_words']['Логотип'] : '<a href="/">'.$_obrabotchik['site_name'].'</a>' ?>
            </div>
    
            <nav class="header-nav" id="site-menu">
                <?php echo create_tree_menu($struktura, 0, 'Верхнее меню', false); ?>
            </nav>
    
            <div class="main-header__controls">
                <div class="header-user-panel">
                    <?php if (!empty($_SESSION['market']['i_contr_id'])): ?>
     
                        <a href="?com=market&profile" class="header-user-link" title="Профиль">
                            <i class="fa fa-user"></i>
                            <span>Профиль</span>
                        </a>
    
                        <a href="?com=market&logout" class="header-user-link js-market-logout" title="Выйти">
                            <i class="fa fa-sign-out"></i>
                            <span>Выйти</span>
                        </a>
                    <?php else: ?>

                        <a href="?com=market&cart" class="header-user-link" title="Войти">
                            <i class="fa fa-user"></i>
                            <span>Войти</span>
                        </a>
                    <?php endif; ?>
                </div>
    
                <button class="header-burger js-header-burger" type="button" aria-label="Открыть меню" aria-controls="site-menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <main class="main-content">
        <?php

        // 1. Если модуль задал отдельный файл страницы — выводим его
        if (!empty($_obrabotchik['com_file']) && is_file($_obrabotchik['com_file']) && is_readable($_obrabotchik['com_file'])) {
            require $_obrabotchik['com_file'];
        }

        // 2. Иначе грузим обычную страницу по текущему URL
        elseif (!isset($_obrabotchik['load_default_page']) || $_obrabotchik['load_default_page'] == 1) {

            // 2.1. Карточка товара
            if ($s_cat_id > 0) {
                $item_file = __DIR__ . '/s_item.php';

                if (is_file($item_file) && is_readable($item_file)) {
                    require $item_file;
                } else {
                    
                }
            }

            // 2.2. Страница структуры
            elseif ($s_struktura_id > 0) {
                $page_modules = [];
                $page_hleb = [];
                
                if (isset($struktura['hleb'][$cur_key]) && is_array($struktura['hleb'][$cur_key])) {
                    $page_hleb = $struktura['hleb'][$cur_key];
                }

                if (!empty($page_hleb) && $is_first_page==false) {
                    echo render_hleb($page_hleb);
                }
                
                if (isset($struktura['modules'][$cur_key]) && is_array($struktura['modules'][$cur_key])) {
                    $page_modules = $struktura['modules'][$cur_key];
                }

                $loaded_modules_cnt = 0;

                if (!empty($page_modules)) {
                    foreach ($page_modules as $cur_key_mod => $module_data) {
                        $module_file_name = trim((string)($module_data['file_name'] ?? ''));

                        if ($module_file_name === '') {
                            continue;
                        }

                        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $module_file_name)) {
                            continue;
                        }

                        $module_file = __DIR__ . '/s_mod/' . $module_file_name . '.php';

                        if (!is_file($module_file) || !is_readable($module_file)) {
                            continue;
                        }

                        $s_mod = $module_data;
                        $s_mod_index = $cur_key_mod;
                        $loaded_modules_cnt++;

                        require $module_file;
                    }
                }
                //Если к странице не подключены модули
                if ($loaded_modules_cnt === 0) {
                    $default_mod_file = __DIR__ . '/s_mod/_default_mod.php';

                    if (is_file($default_mod_file) && is_readable($default_mod_file)) {
                        require $default_mod_file;
                    } else {
                        echo '<!-- Не найден файл модуля по умолчанию: ' . htmlspecialchars($default_mod_file, ENT_QUOTES, 'UTF-8') . ' -->';
                    }
                }
            }
        }

        ?>
    </main>

    <footer class="main-footer">
        <div class="container">
            <?php
            $footer_social = array();
            if (isset($i_tp_arr['social'][$i_tp_id]) && is_array($i_tp_arr['social'][$i_tp_id])) {
                $footer_social = $i_tp_arr['social'][$i_tp_id];
            }
            ?>

            <?php if (!empty($footer_social)): ?>
                <div class="main-footer__social" aria-label="Социальные сети">
                    <?php foreach ($footer_social as $social_name => $social_data): ?>
                        <?php
                        $social_link = '';
                        $social_fa_class = '';
                        $social_photo_id = 0;
                        $social_title = trim((string)$social_name);

                        if (is_array($social_data)) {
                            $social_link = trim((string)($social_data['link'] ?? ''));
                            $social_fa_class = trim((string)($social_data['fa_class'] ?? ''));
                            $social_photo_id = (int)($social_data['photo_id'] ?? 0);
                            if (!empty($social_data['name'])) {
                                $social_title = trim((string)$social_data['name']);
                            }
                        } else {
                            $social_link = trim((string)$social_data);
                        }

                        if ($social_link === '') {
                            continue;
                        }
                        ?>
                        <a class="main-footer__social_link" href="<?= htmlspecialchars($social_link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer nofollow" aria-label="<?= htmlspecialchars($social_title, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($social_title, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($social_photo_id > 0): ?>
                                <img class="main-footer__social_img" src="<?= htmlspecialchars(get_a_photo_proxy_url($social_photo_id), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($social_title, ENT_QUOTES, 'UTF-8') ?>">
                            <?php elseif ($social_fa_class !== ''): ?>
                                <i class="<?= htmlspecialchars($social_fa_class, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            <?php else: ?>
                                <span class="main-footer__social_text"><?= htmlspecialchars($social_title, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($_obrabotchik['footer_text'] !== ''): ?>
                <div class="main-footer__text">
                    <?= $_obrabotchik['footer_text'] ?>
                </div>
            <?php endif; ?>

            <?php if ($_obrabotchik['contact_city'] !== '' || $_obrabotchik['contact_address'] !== '' || $_obrabotchik['contact_phone'] !== '' || $_obrabotchik['contact_worktime'] !== ''): ?>
                <div class="main-footer__contacts" itemscope itemtype="https://schema.org/LocalBusiness">
                    <meta itemprop="name" content="<?= $_obrabotchik['site_name'] ?>">

                    <?php if ($_obrabotchik['contact_city'] !== '' || $_obrabotchik['contact_address'] !== ''): ?>
                        <div class="main-footer__contact_line" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                            <i class="fa fa-map-marker" aria-hidden="true"></i>
                            <span itemprop="addressLocality"><?= htmlspecialchars($_obrabotchik['contact_city'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span itemprop="streetAddress"><?= htmlspecialchars($_obrabotchik['contact_address'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($_obrabotchik['contact_phone'] !== ''): ?>
                        <div class="main-footer__contact_line">
                            <i class="fa fa-phone" aria-hidden="true"></i>
                            <span itemprop="telephone"><?= htmlspecialchars($_obrabotchik['contact_phone'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($_obrabotchik['contact_worktime'] !== ''): ?>
                        <div class="main-footer__contact_line">
                            <i class="fa fa-clock-o" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($_obrabotchik['contact_worktime'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </footer>
    
    <script src="shablon/js/jquery-4.0.0.min.js"></script>
    <script src="shablon/js/jquery-ui.min.js"></script>
    <script src="shablon/js/jquery.arcticmodal-0.3.min.js"></script>
    <script src="shablon/js/jquery.history.js"></script>
    <script src="shablon/js/site.js?<?=rand(100,999);?>"></script>
    <script src="shablon/js/market.js?<?=rand(100,999);?>"></script>

    <?= $_SESSION['s_words']['Скрипты в футер сайта'] ?? '' ?>
</body>
</html>
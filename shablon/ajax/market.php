<?php
/**
 * /shablon/ajax/market.php

 */
// <a href="?com=market&cart">Корзина / Войти</a>
// <a href="?com=market&orders">Заказы</a>
// <a href="?com=market&profile">Профиль</a>
// <a href="?com=market&logout">Выйти</a>
// <a href="?com=market&buy&id=12&kolvo=1">Купить</a>
// <a href="?com=market&login&email=test@test.ru&hash=">Авторизация</a> // для входа с админки

// toowin86 2026-03-18 v2
$_t  = _GP('_t');
$data_=array();
if (!isset($_SESSION['market'])){$_SESSION['market']=array();}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////
//отрисовка формы авторизации
function market_auth_form_create($tip_,$email='',$code='',$password=''){
    global $db;
    $data_=array();
    
    //глаз для пароля
    $eye_svg_hide = '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>';
    $eye_svg_show = '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.1 3.51 3.5 2.1 21.9 20.5l-1.41 1.41-3.06-3.06A11.7 11.7 0 0 1 12 20C5 20 2 13 2 13a20.6 20.6 0 0 1 5.06-6.3L2.1 3.5Zm7.44 7.44A3 3 0 0 0 12 15c.35 0 .69-.06 1-.17l-3.46-3.46ZM12 6c7 0 10 7 10 7a20.3 20.3 0 0 1-3.3 4.46l-2.18-2.18A5 5 0 0 0 9.72 8.5L8.08 6.86A11.8 11.8 0 0 1 12 6Z"/></svg>';
    
    if ($tip_=='step1'){//нет авторизации
        $data_['t']='Вход';
        $data_['i']='Введите свой email';
        $data_['q']='<form class="market_page_form market_page_form_auth"><label class="market_open__label" for="iEmail">Email</label><input class="market_open__input" id="iEmail" name="email" type="email" placeholder="Email" required></form>';
        $data_['b']='<button type="button" class="market_open__btn market_open__btn-primary market_chk_email">Далее</button>';
    }
    if ($tip_=='step2'){//email не зарегистрирован (регистрация) -> ввод кода // восстановление пароля -> ввод кода
        
        $data_['t']='Проверка email';
        $data_['i']='Введите код, полученный на email <strong>'.$email.'</strong>.<br />Если письмо не пришло, то проверьте в спаме.';
        $data_['q']='<form class="market_page_form market_page_form_auth"><label class="market_open__label" for="iCode">Код, полученный на email</label><input class="market_open__input" id="iCode" name="code" type="number" placeholder="Код" required><input type="hidden" name="email" value="'._IN($email).'" /></form>';
        $data_['b']='<button type="button" class="market_open__btn market_open__btn-primary market_chk_code">Далее</button>
        <button type="button" class="market_open__btn market_open__btn-secondary market_back_to_email">Назад к вводу email</button>';
    }
    if ($tip_=='step3'){//email найден (авторизация) -> ввод пароля
        
        $data_['t']='Вход в аккаунт';
        $data_['i']='Введите пароль от аккаунта <strong>'.$email.'</strong>,<br />если не помните, нажмите кнопку "Восстановить пароль"';
        $data_['q']='<form class="market_page_form market_page_form_auth"><label class="market_open__label" for="iPassword">Пароль</label><div class="market_open__inputwrap"><input class="market_open__input market_open__input--withicon" id="iPassword" name="password" type="password" placeholder="Пароль" required><button type="button" class="market_open__iconbtn market_pass_eye" aria-label="Показать пароль" data-eye="0" data-svg-hide="'._IN($eye_svg_hide).'" data-svg-show="'._IN($eye_svg_show).'">'.$eye_svg_hide.'</button></div><input type="hidden" name="email" value="'._IN($email).'" /></form>';
        $data_['b']='<button type="button" class="market_open__btn market_open__btn-primary market_chk_password">Войти</button>
                    <button type="button" class="market_open__btn market_open__btn-secondary market_recovery_password">Восстановить пароль</button>
                    <button type="button" class="market_open__btn market_open__btn-secondary market_back_to_email">Назад к вводу email</button>';
  
    }
    if ($tip_=='step4'){//email не зарегистрирован (регистрация или при восстановлении пароля), код совпадает -> создание пароля 
        
        $data_['i']='Придумайте пароль';
        $data_['q']='<label class="market_open__label" for="iPassCreate">Новый пароль</label><div class="market_open__inputwrap"><input class="market_open__input market_open__input--withicon" id="iPassCreate" name="passCreate" type="password" placeholder="Пароль" required><button type="button" class="market_open__iconbtn market_pass_eye" aria-label="Показать пароль" data-eye="0" data-svg-hide="'._IN($eye_svg_hide).'" data-svg-show="'._IN($eye_svg_show).'">'.$eye_svg_hide.'</button></div><input type="hidden" name="code" value="'._IN($code).'" /><input type="hidden" name="email" value="'._IN($email).'"  />';
        $data_['b']='<button type="button" class="market_open__btn market_open__btn-primary market_create_update_user">Далее</button><button type="button" class="market_open__btn market_open__btn-secondary market_back_to_email">Назад к вводу email</button>';
        
        //проверка, регистрация или восстановление пароля
        $sql = "SELECT COUNT(*) 
                FROM i_contr
                WHERE i_contr.email = ?";
        $res = _DB($sql, [$email]);
        if ($res === false){ die('SQL error'); }
        
        $row = $res->fetch(PDO::FETCH_NUM);
        
        if ((int)$row[0] === 0){
            //проверка дополнительных полей
            
            if (isset($_SESSION['a_options']['Market: телефон дополнительно при регистрации']) and $_SESSION['a_options']['Market: телефон дополнительно при регистрации']=='1'){
                //проверка обязательности полей
    
                if (isset($_SESSION['a_options']['Market: телефон обязательно при регистрации']) and $_SESSION['a_options']['Market: телефон обязательно при регистрации']=='1'){
                    $data_['i'].=', обязательно укажите телефон';
                    $data_['q'].='<label class="market_open__label" for="iPhoneCreate">Телефон</label><input class="market_open__input" id="iPhoneCreate" name="phone" type="phone" placeholder="+7(XXX)XXX-XX-XX" required>';
                }
                else{    
                    $data_['i'].=', укажите телефон';
                    $data_['q'].='<label class="market_open__label" for="iPhoneCreate">Телефон</label><input class="market_open__input" id="iPhoneCreate" name="phone" type="phone" placeholder="+7(XXX)XXX-XX-XX">';
                }
                
            }
            
            if (isset($_SESSION['a_options']['Market: имя дополнительно при регистрации']) and $_SESSION['a_options']['Market: имя дополнительно при регистрации']=='1'){
                $name_label = $_SESSION['a_options']['Market: поле ИМЯ как отображается при регистрации (имя,логин,ФИО)'];
                $name_required = '';
            
                if (isset($_SESSION['a_options']['Market: имя обязательно при регистрации']) and $_SESSION['a_options']['Market: имя обязательно при регистрации']=='1'){
                    $data_['i'].=', обязательно напишите '.$name_label;
                    $name_required = ' required';
                }else{
                    $data_['i'].=', напишите '.$name_label;
                }
            
                $data_['q'].='<label class="market_open__label" for="iNameCreate">'.$name_label.'</label><input class="market_open__input" id="iNameCreate" name="name" type="text" placeholder="'._IN($name_label).'"'.$name_required.'>';
            }
            
            //Привязка телеграм аккаунта
            if (market_telegram_enabled()){
                $telegram_req = market_telegram_required() ? '1' : '0';
                $telegram_auth = market_telegram_get_session_auth();
                $telegram_id = isset($telegram_auth['id']) ? (int)$telegram_auth['id'] : 0;
                $telegram_username = isset($telegram_auth['username']) ? trim((string)$telegram_auth['username']) : '';
            
                if (market_telegram_required()){
                    $data_['i'].=', обязательно привяжите Telegram';
                }
                else{
                    $data_['i'].=', при желании привяжите Telegram';
                }
            
                $tg_status = 'Telegram не привязан';
                if ($telegram_id > 0){
                    $tg_status = 'Telegram привязан';
                    if ($telegram_username !== ''){
                        $tg_status .= ' (@' . $telegram_username . ')';
                    }
                }
            
                $data_['q'].='
                    <div class="market_tg_box" style="margin-top:16px;">
                        <label class="market_open__label">Telegram</label>
                        <div class="market_tg_status js-market-tg-status" style="margin:8px 0 12px;color:#6b7280;">'._IN($tg_status).'</div>
            
                        <input
                            type="hidden"
                            name="telegram_id"
                            value="'._IN($telegram_id).'"
                            data-market-required="'.$telegram_req.'"
                            data-market-title="Telegram аккаунт"
                        >
            
                        <div
                            class="js-market-tg-auth"
                            data-bot="'._IN(market_telegram_bot_username()).'"
                            data-required="'.$telegram_req.'"
                            data-ready="'.(market_telegram_widget_ready() ? '1' : '0').'"
                        ></div>
                ';
            
                if ($telegram_id > 0){
                    $data_['q'].='<button type="button" class="market_open__btn market_open__btn-secondary market_tg_reset">Отвязать Telegram</button>';
                }
            
                if (!market_telegram_widget_ready()){
                    $data_['q'].='<div class="market_tg_hint" style="margin-top:10px;color:#b91c1c;">Для привязки Telegram заполните опции <strong>Market: telegram bot username</strong> и <strong>Market: telegram bot token</strong>.</div>';
                }
            
                $data_['q'].='</div>';
            }
            
            $data_['t']='Регистрация';
            $data_['b']='<p>*Нажимая <strong>Далее</strong>, Вы принимаете пользовательское соглашение!</p>'.$data_['b'];
        }
        else{
            $data_['t']='Создайте пароль';
        }
        
    }
    $data_['q']='<form class="market_page_form market_page_form_auth">'.$data_['q'].'</form>';
    
    if ($data_['i']!=''){$data_['i']='<div class="m_auth_i">'.$data_['i'].'</div>';}
    
    return $data_;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Отрисовка формы оплаты
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function market_yoomoney_pay_form_inline_create($m_zakaz_id, $summa)
{
    global $protacol;

    $m_zakaz_id = (int)$m_zakaz_id;
    $summa = round((float)$summa, 2);

    if ($m_zakaz_id <= 0 || $summa <= 0) {
        return '';
    }

    $receiver = '';
    if (isset($_SESSION['a_options']) && isset($_SESSION['a_options']['Market: оплата: номер счета кошелька yoomoney'])) {
        $receiver = trim((string)$_SESSION['a_options']['Market: оплата: номер счета кошелька yoomoney']);
    }

    if ($receiver === '') {
        return '';
    }

    $host = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
    } elseif (!empty($_SERVER['SERVER_NAME'])) {
        $host = $_SERVER['SERVER_NAME'];
    }

    $success_url = '/?com=market&orders';
    if ($host !== '') {
        $success_url = rtrim($protacol . $host, '/') . '/?com=market&orders';
    }
    
    // формируем label - добавляем хост и номер заказа
    $host_label = '';
    if ($host !== '') {
        $host_label = strtolower(trim((string)$host));
        $host_label = preg_replace('/:\d+$/', '', $host_label);
    }
    
    if ($host_label === '') {
        return '';
    }
    
    $label = $host_label . '|' . $m_zakaz_id;

    return '
        <form class="m_order_pay_form_inline" method="POST" action="https://yoomoney.ru/quickpay/confirm">
            <input type="hidden" name="receiver" value="'._IN($receiver).'">
            <input type="hidden" name="label" value="'._IN($label).'">
            <input type="hidden" name="quickpay-form" value="button">
            <input type="hidden" name="sum" value="'._IN(number_format($summa, 2, '.', '')).'">
            <input type="hidden" name="paymentType" value="AC">
            <input type="hidden" name="successURL" value="'._IN($success_url).'">
            <button type="submit" class="m_order_pay_btn_inline">Оплатить</button>
        </form>';
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Проверка товара для корзины
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function market_get_cart_item_info($s_cat_id){
    $s_cat_id = (int)$s_cat_id;

    if ($s_cat_id <= 0){
        return array(
            'status_' => 'error',
            'message' => 'Некорректный ID товара'
        );
    }

    $sql_item = "SELECT id, name, chk_active, kolvo, price, tip
                 FROM s_cat
                 WHERE id = ?
                 LIMIT 1";
    $res_item = _DB($sql_item, array($s_cat_id));
    if ($res_item === false){ die('SQL error'); }

    $row_item = $res_item->fetch(PDO::FETCH_ASSOC);

    if (!$row_item){
        return array(
            'status_' => 'error',
            'message' => 'Товар не найден'
        );
    }

    if (!isset($row_item['chk_active']) || (string)$row_item['chk_active'] !== '1'){
        return array(
            'status_' => 'error',
            'message' => 'Товар "'.$row_item['name'].'" недоступен для заказа'
        );
    }

    $tip = isset($row_item['tip']) ? trim((string)$row_item['tip']) : '';
    $tip_l = function_exists('mb_strtolower') ? mb_strtolower($tip, 'UTF-8') : strtolower($tip);

    
    $is_service = in_array($tip_l, array('Услуга', 'услуга'), true);

    $stock_kolvo = isset($row_item['kolvo']) ? (float)$row_item['kolvo'] : 0;

    return array(
        'status_' => 'ok',
        'row' => $row_item,
        'stock_kolvo' => $stock_kolvo,
        'is_service' => $is_service
    );
}
// формирование корзины/кабинета
function market_cart_form_create($tip_='cart'){
    global $db;

    $data_ = array();
    $email = isset($_SESSION['market']['email']) ? $_SESSION['market']['email'] : '';
    $i_contr_id = isset($_SESSION['market']['i_contr_id']) ? (int)$_SESSION['market']['i_contr_id'] : 0;
        if ($i_contr_id==0){echo 'no i_contr.id';exit;}
        
    // ---------- tabs ----------
    $cart_items_cnt = 0;
    if (!empty($_SESSION['market']['items']) && is_array($_SESSION['market']['items'])) {
        foreach ($_SESSION['market']['items'] as $tmp_s_cat_id => $tmp_kolvo) {
            if ((float)$tmp_kolvo > 0) {
                $cart_items_cnt++;
            }
        }
    }
    $has_cart_items = ($cart_items_cnt > 0);
    
    $sql_orders_cnt = "SELECT COUNT(*)
                       FROM m_zakaz
                       WHERE i_contr_id = ?
                         AND chk_active = 1
                         AND status <> 'Отменен'";
    $res_orders_cnt = _DB($sql_orders_cnt, array($i_contr_id));
    if ($res_orders_cnt === false){ die('SQL error'); }
    
    $row_orders_cnt = $res_orders_cnt->fetch(PDO::FETCH_NUM);
    $active_orders_cnt = isset($row_orders_cnt[0]) ? (int)$row_orders_cnt[0] : 0;
    $has_active_orders = ($active_orders_cnt > 0);
    
    $tabs = array();
    if ($has_cart_items) {
        $tabs['cart'] = 'Моя корзина';
    }
    if ($has_active_orders) {
        $tabs['orders'] = 'Мои заказы';
    }
    $tabs['profile'] = 'Профиль';
    
    //табы для данного сайта
    $custom_tabs = market_get_market_tabs();
    if (!empty($custom_tabs)) {
        foreach ($custom_tabs as $custom_tab_key => $custom_tab_data) {
            $tabs[$custom_tab_key] = $custom_tab_data['title'];
        }
    }
    
    if (!isset($tabs[$tip_])) {
        if ($has_cart_items) {
            $tip_ = 'cart';
        } elseif ($has_active_orders) {
            $tip_ = 'orders';
        } else {
            $tip_ = 'profile';
        }
    }
    
    $tabs_html = '<div class="m_tabs">';
    foreach ($tabs as $k => $v){
        $active = ($k === $tip_) ? ' m_tab_active' : '';
        $tabs_html .= '<a href="?com=market&'.$k.'" class="m_tab'.$active.'" data-market-tab="'.$k.'">'.$v.'</a>';
    }
    $tabs_html .= '</div>';
    
    // ---------- common header ----------
    $data_['t'] = 'Корзина';
    $data_['i'] = $tabs_html;
    $data_['b'] = '';

    if (isset($custom_tabs[$tip_])) {
        $data_['t'] = $custom_tabs[$tip_]['title'];
        $data_['q'] = market_render_market_tab($tip_, array(
            'i_contr_id' => $i_contr_id,
            'tabs' => $tabs,
            'tip_' => $tip_
        ));

        if ($data_['q'] === '') {
            $data_['q'] = '<div class="m_page"><div class="m_vpn_card"><div class="m_vpn_empty">Вкладка временно недоступна.</div></div></div>';
        }

        return $data_;
    }
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////// ПРОФИЛЬ //////////////
    //////////////////////////////////////////////////////////
   if ($tip_ === 'profile'){
        
        $sql = "SELECT i_contr.name
                        , i_contr.email
                        , i_contr.phone
                        , i_contr.data_create
                        , i_contr.i_city_id
                        , i_contr.adress
                        , i_contr.chk_notification
                        , i_contr.telegram_id
                FROM i_contr
                WHERE i_contr.id = ?";
        $res = _DB($sql, [$i_contr_id]);
        if ($res === false){ die('SQL error'); }
        
        $row = $res->fetch(PDO::FETCH_NUM);
        $i_contr_name=$row[0];
        $i_contr_email=$row[1];
        
        $i_contr_chk_notification = isset($row[6]) ? $row[6] : 0; // Получаем значение ползунка
        $chk_notif_str = ($i_contr_chk_notification == 1) ? 'checked="checked"' : ''; // Формируем атрибут
        $i_contr_telegram_id = isset($row[7]) ? (int)$row[7] : 0;
        
        
        $i_contr_data_create='';
        if ($row[3]!='0000-00-00 00:00:00'){
            $i_contr_data_create=date('d.m.Y H:i:s',strtotime($row[3]));
        }
        
        $i_contr_phone='';
        if($row[2]!=''){$i_contr_phone=conv_('phone_from_db',$row[2]);}
        //if ($i_contr_name==''){$i_contr_name=$i_contr_email;}
        $i_contr_i_city_id=$row[4];
        $i_contr_adress=$row[5];
        
        //город
        $i_contr_i_city_name='';
        if ($i_contr_i_city_id!=''){
            $sql_i_city = "SELECT i_city.name
                           FROM i_city
                           WHERE i_city.id = ?";
            $res_i_city = _DB($sql_i_city, [$i_contr_i_city_id]);
            if ($res_i_city === false){ die('SQL error'); }
            
            $row_i_city = $res_i_city->fetch(PDO::FETCH_NUM);
            $i_contr_i_city_name = $row_i_city[0] ?? '';
        }
        
        
        //Фото
        $sql = "SELECT a_photo.img
                FROM a_photo
                WHERE a_photo.a_menu_id = '25'
                  AND a_photo.row_id = ?
                ORDER BY a_photo.sid
                LIMIT 1";
        $res = _DB($sql, [$i_contr_id]);
        if ($res === false){ die('SQL error'); }
        
        $row = $res->fetch(PDO::FETCH_NUM);

        if (isset($row[0]) and $row[0]!='' and file_exists('i/i_contr/original/'.$row[0])){
            $i_contr_img='i/i_contr/original/'.$row[0].'';
        }else{
            $i_contr_img='shablon/i/market_profile_null.webp';
        }

        $name_html='';
        if (isset($_SESSION['a_options']['Market: имя дополнительно при регистрации']) and (int)$_SESSION['a_options']['Market: имя дополнительно при регистрации']==1){
            $name_html='
            <div class="p_f">
                <label class="p_lbl">'.$_SESSION['a_options']['Market: поле ИМЯ как отображается при регистрации (имя,логин,ФИО)'].'</label>
                <div class="p_inpwrap">
                  <span class="p_ic"><i class="fa fa-user"></i></span>
                  <input class="p_inp" name="name" type="text" placeholder="'._IN($_SESSION['a_options']['Market: поле ИМЯ как отображается при регистрации (имя,логин,ФИО)']).'" value="'._IN($i_contr_name).'">
                </div>
              </div>
            ';
        }
        
        $phone_html='';
        if (isset($_SESSION['a_options']['Market: телефон дополнительно при регистрации']) and (int)$_SESSION['a_options']['Market: телефон дополнительно при регистрации']==1){
            $req='';if (isset($_SESSION['a_options']['Market: телефон обязательно при регистрации']) and (int)$_SESSION['a_options']['Market: телефон обязательно при регистрации']==1){$req=' required';}
            $phone_html.='
            <div class="p_f">
              <label class="p_lbl">Телефон</label>
              <div class="p_inpwrap">
                <span class="p_ic"><i class="fa fa-phone"></i></span>
                <input class="p_inp" name="phone" type="phone" placeholder="Телефон" value="'._IN($i_contr_phone).'" '.$req.'>
              </div>
            </div>';
        }
        
        $telegram_html = '';
        if (
            isset($_SESSION['a_options']['Market: telegram дополнительно при регистрации'])
            && (int)$_SESSION['a_options']['Market: telegram дополнительно при регистрации'] == 1
        ){
            $telegram_bot_username = isset($_SESSION['a_options']['Market: telegram bot username'])
                ? trim((string)$_SESSION['a_options']['Market: telegram bot username'])
                : '';
        
            $telegram_bot_token = isset($_SESSION['a_options']['Market: telegram bot token'])
                ? trim((string)$_SESSION['a_options']['Market: telegram bot token'])
                : '';
        
            $telegram_widget_ready = ($telegram_bot_username !== '' && $telegram_bot_token !== '');
        
            $telegram_html = '
            <div class="p_row1">
                <div class="p_f">
                    <label class="p_lbl">Telegram</label>
                    <div class="p_inpwrap">
                        <span class="p_ic"><i class="fa fa-telegram"></i></span>
                        <input class="p_inp" name="telegram_id_view" type="text" placeholder="Telegram" value="'._IN($i_contr_telegram_id > 0 ? ('ID ' . $i_contr_telegram_id) : 'Не привязан').'" disabled="disabled">
                    </div>
                </div>
            </div>';
        
            if ($i_contr_telegram_id > 0){
                if (
                    isset($_SESSION['a_options']['Market: telegram обязательно при регистрации'])
                    && (int)$_SESSION['a_options']['Market: telegram обязательно при регистрации'] == 0
                ){
                    $telegram_html .= '
                    <div class="p_actions" style="padding-top:0;">
                        <button type="button" class="p_btn p_btn_soft market_telegram_unlink">
                            <i class="fa fa-unlink"></i> Отвязать Telegram
                        </button>
                    </div>';
                }
            }
            else{
                if ($telegram_widget_ready){
                    $telegram_html .= '
                    <div class="p_actions" style="padding-top:0;">
                        <button type="button" class="p_btn p_btn_dark market_telegram_bind">
                            <i class="fa fa-telegram"></i> Привязать Telegram
                        </button>
                    </div>
                    <div class="market_tg_profile_widget" style="display:none; padding:0 24px 24px 24px;">
                        <div style="margin:0 0 12px 0; color:#6b7280;">Подтвердите привязку через Telegram</div>
                        <div class="js-market-tg-auth-profile" data-bot="'._IN($telegram_bot_username).'"></div>
                    </div>';
                }
                else{
                    $telegram_html .= '
                    <div class="p_actions" style="padding-top:0;">
                        <div style="color:#b91c1c;">Не заполнены настройки Telegram Bot Username / Token</div>
                    </div>';
                }
            }
        }

        $data_['t'] = 'Профиль';
        $row_cnt='2'; if ($phone_html==''){$row_cnt='1';}
        $data_['q'] ='
        <div class="p_grid">
            <aside class="p_left">

                <div class="p_card p_user">
                    <div class="p_avatar">
                        <img class="p_avatar_img" src="'.$i_contr_img.'?rand='.rand(1000,9999).'" alt="Аватар">
                        <button type="button" class="p_avatar_btn" title="Изменить фото"><i class="fa fa-camera"></i></button>
                    </div>

                    <div class="p_name">'.$i_contr_name.'</div>
                    <div class="p_sub"><i class="fa fa-calendar"></i> Участник с '.$i_contr_data_create.'</div>

                    <div class="p_div"></div>

                    <a href="/?com=market&logout" class="p_logout"><i class="fa fa-sign-out"></i> Выйти из аккаунта</a>
                </div>

                <div class="p_card">
                    <div class="p_h"><i class="fa fa-shield"></i> Безопасность и настройки</div>

                    <div class="p_set">
                        <div class="p_set_i"><i class="fa fa-envelope"></i></div>
                        <div class="p_set_t">
                            <div class="p_set_h">Оповещения</div>
                            <div class="p_set_s">Включить оповещения</div>
                        </div>
                        <label class="p_sw">
                            <input type="checkbox" name="chk_notification" class="market_chk_notification" '.$chk_notif_str.'>
                            <span class="p_sw_ui"></span>
                        </label>
                    </div>';
                    
                    //кнопка смены пароля доступна, если пользователь входил через пароль
                    if (isset($_SESSION['market']['password']) and $_SESSION['market']['password']!=''){
                        $data_['q'] .='<button type="button" class="p_btn p_btn_soft market_password_open">
                                    <i class="fa fa-key"></i> Сменить пароль <span class="p_chev"><i class="fa fa-angle-right"></i></span>
                                </button>';
                    }

                    $data_['q'] .='
                    
                </div>

            </aside>

            <section class="p_right">
                <form class="market_page_form market_page_form_profile">
                <div class="p_card">
                    <div class="p_title"><i class="fa fa-id-card"></i> Личные данные</div>

                    '.$name_html.'

                    <div class="p_row'.$row_cnt.'">
                        <div class="p_f">
                            <label class="p_lbl">Email</label>
                            <div class="p_inpwrap">
                                <span class="p_ic"><i class="fa fa-envelope"></i></span>
                                <input class="p_inp" name="email" type="text" placeholder="Email" value="'._IN($email).'" disabled="disabled">
                            </div>
                        </div>

                        '.$phone_html.'
                    </div>
                    '.$telegram_html.'
                    ';
                    if (isset($_SESSION['a_options']['Market: доставка']) and (int)$_SESSION['a_options']['Market: доставка']==1){
                          $data_['q'].='
                            <div class="p_title2"><i class="fa fa-truck"></i> Адрес доставки по умолчанию</div>
                            <div class="p_row1">
                                <div class="p_f">
                                    <label class="p_lbl">Город</label>
                                    <div class="p_inpwrap city_div">
                                        <span class="p_ic"><i class="fa fa-map-marker"></i></span>
                                        <input class="p_inp" name="city" type="text" placeholder="Город" value="'._IN($i_contr_i_city_name).'">
                                    </div>
                                </div>
                            </div>
                            <div class="p_row1">
                                <div class="p_f">
                                    <label class="p_lbl">Улица, дом, кв.</label>
                                    <div class="p_inpwrap">
                                        <span class="p_ic"><i class="fa fa-home"></i></span>
                                        <input class="p_inp" name="addr" type="text" placeholder="Адрес" value="'._IN($i_contr_adress).'">
                                    </div>
                                </div>
                            </div>';
                    }
                     $data_['q'].='
                    <div class="p_actions">
                        <button type="button" class="p_btn p_btn_dark market_profile_save"><i class="fa fa-save"></i> Сохранить изменения</button>
                    </div>
                </div>
                </form>

            </section>
        </div>';

        return $data_;
    }//end profile
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////// ЗАКАЗЫ //////////////
    //////////////////////////////////////////////////////////
    if ($tip_ === 'orders'){
        $data_['t'] = 'Мои заказы';
        $i_contr_id = (int)$_SESSION['market']['i_contr_id'];
    
        $sql_orders = "SELECT id, data_create, status
                       FROM m_zakaz
                       WHERE i_contr_id = ?
                         AND chk_active = 1
                         AND status <> 'Отменен'
                       ORDER BY data_create DESC, id DESC";
        $res_orders = _DB($sql_orders, array($i_contr_id));
        if ($res_orders === false){ die('SQL error'); }
        $orders = $res_orders->fetchAll(PDO::FETCH_ASSOC);
    
        $orders_html = '';
    
        if (!empty($orders)) {
    
            $orders_html .= '<div class="m_orders">';
    
            foreach ($orders as $row_order) {
                $order_id = (int)$row_order['id'];
                $order_date = '';
                if (!empty($row_order['data_create']) && $row_order['data_create'] !== '0000-00-00 00:00:00') {
                    $order_date = date('d.m.Y H:i', strtotime($row_order['data_create']));
                }
                $order_status = isset($row_order['status']) ? (string)$row_order['status'] : '';
    
                $badge_class = 'm_badge_info';
                if ($order_status == 'Выполнен') {
                    $badge_class = 'm_badge_ok';
                } elseif ($order_status == 'Отменен') {
                    $badge_class = 'm_badge_error';
                }
                
                //статусы заказов годные для отмены
                $can_cancel_order = !in_array($order_status, array('Отменен', 'Выполнен', 'Частично выполнен', 'Доставлен', 'Отправлен'), true);
    
                $items_html = '<div class="m_order_details" style="display:none;">';
                $order_total = 0;
    
                $sql_items = "SELECT m.kolvo, m.price, s.name, s.id AS s_cat_id
                              FROM m_zakaz_s_cat m
                              LEFT JOIN s_cat s ON s.id = m.s_cat_id
                              WHERE m.m_zakaz_id = ?";
                $res_items = _DB($sql_items, array($order_id));
                if ($res_items === false){ die('SQL error'); }
    
                $items = $res_items->fetchAll(PDO::FETCH_ASSOC);
    
                foreach ($items as $row_item) {
                    $item_name = htmlspecialchars((string)$row_item['name'], ENT_QUOTES, 'UTF-8');
                    $item_kolvo = (float)$row_item['kolvo'];
                    $item_price = (float)$row_item['price'];
                    $item_sum = $item_kolvo * $item_price;
                    $order_total += $item_sum;
    
                    $img_html = '<div class="m_order_item_noimg"><i class="fa fa-image" style="color:#ccc; font-size:20px;"></i></div>';
    
                    $sql_photo = "SELECT img
                                  FROM a_photo
                                  WHERE row_id = ?
                                    AND a_menu_id = '7'
                                  ORDER BY sid
                                  LIMIT 1";
                    $res_photo = _DB($sql_photo, array((int)$row_item['s_cat_id']));
                    if ($res_photo === false){ die('SQL error'); }
    
                    $row_photo = $res_photo->fetch(PDO::FETCH_ASSOC);
    
                    if (is_array($row_photo) && isset($row_photo['img']) && $row_photo['img'] !== '') {
                        $photo_url = 'i/s_cat/original/' . $row_photo['img'];
                        $img_html = '<img src="' . $photo_url . '" alt="">';
                    }
    
                    $items_html .= '
                        <div class="m_order_item">
                            <div class="m_order_item_img">' . $img_html . '</div>
                            <div class="m_order_item_info">
                                <div class="m_order_item_name">' . $item_name . '</div>
                                <div class="m_order_item_price">' . $item_kolvo . ' шт. x ' . number_format($item_price, 0, '', ' ') . ' ₽ = <b>' . number_format($item_sum, 0, '', ' ') . ' ₽</b></div>
                            </div>
                        </div>';
                }
    
                /*
                 * Платежи по заказу:
                 * m_platezi.id_z_p_p = m_zakaz.id
                 * m_platezi.a_menu_id = 16
                 */
                $sql_pay = "SELECT id,
                                   COALESCE(data, data_create) AS pay_date,
                                   summa,
                                   tip,
                                   comments
                            FROM m_platezi
                            WHERE id_z_p_p = ?
                              AND a_menu_id = 16
                            ORDER BY COALESCE(data, data_create) DESC, id DESC";
                $res_pay = _DB($sql_pay, array($order_id));
                if ($res_pay === false){ die('SQL error'); }
    
                $payments = $res_pay->fetchAll(PDO::FETCH_ASSOC);
    
                $paid_total = 0;
                $payments_rows_html = '';
    
                if (!empty($payments)) {
                    $payments_rows_html .= '<div class="m_order_pay_list">';
    
                    foreach ($payments as $row_pay) {
                        $pay_tip = isset($row_pay['tip']) ? (string)$row_pay['tip'] : 'Кредит';
                        $pay_sum = isset($row_pay['summa']) ? (float)$row_pay['summa'] : 0;
                        $pay_comment = isset($row_pay['comments']) ? trim((string)$row_pay['comments']) : '';
    
                        if ($pay_tip === 'Дебет') {
                            $paid_total -= $pay_sum;
                            $pay_sum_txt = '- ' . number_format($pay_sum, 0, '', ' ') . ' ₽';
                        } else {
                            $paid_total += $pay_sum;
                            $pay_sum_txt = '+ ' . number_format($pay_sum, 0, '', ' ') . ' ₽';
                        }
    
                        $pay_date = '';
                        if (!empty($row_pay['pay_date']) && $row_pay['pay_date'] !== '0000-00-00 00:00:00') {
                            $pay_date = date('d.m.Y H:i', strtotime($row_pay['pay_date']));
                        }
    
                        $payments_rows_html .= '
                            <div class="m_order_pay_row">
                                <div class="m_order_pay_row_l">
                                    <div class="m_order_pay_row_date">' . $pay_date . '</div>
                                    <div class="m_order_pay_row_comment">' . ($pay_comment !== '' ? htmlspecialchars($pay_comment, ENT_QUOTES, 'UTF-8') : $pay_tip) . '</div>
                                </div>
                                <div class="m_order_pay_row_r">' . $pay_sum_txt . '</div>
                            </div>';
                    }
    
                    $payments_rows_html .= '</div>';
                }
    
                if ($paid_total < 0) {
                    $paid_total = 0;
                }
    
                $to_pay = $order_total - $paid_total;
                if ($to_pay < 0) {
                    $to_pay = 0;
                }
    
                $pay_badge_class = 'm_badge_pay_wait';
                $pay_badge_text = 'Не оплачен';
                $payment_status_text = 'Оплата не поступала.';
                $pay_form_html = '';
                $pay_form_inline_html = '';
                $cancel_btn_html = '';//кнопка отмены заказа
    
                if ($order_total <= 0) {
                    $pay_badge_class = 'm_badge_info';
                    $pay_badge_text = 'Без оплаты';
                    $payment_status_text = 'Для этого заказа оплата не требуется.';
                } elseif ($to_pay <= 0.009) {
                    $pay_badge_class = 'm_badge_pay_ok';
                    $pay_badge_text = 'Оплачен';
                    $payment_status_text = 'Оплачено ' . number_format($paid_total, 0, '', ' ') . ' ₽ из ' . number_format($order_total, 0, '', ' ') . ' ₽';
                } elseif ($paid_total > 0.009) {
                    $pay_badge_class = 'm_badge_pay_part';
                    $pay_badge_text = 'Частично оплачен';
                    $payment_status_text = 'Оплачено ' . number_format($paid_total, 0, '', ' ') . ' ₽ из ' . number_format($order_total, 0, '', ' ') . ' ₽';
                    $pay_form_inline_html = market_yoomoney_pay_form_inline_create($order_id, $to_pay);
                } else {
                    $pay_badge_class = 'm_badge_pay_wait';
                    $pay_badge_text = 'Не оплачен';
                    $payment_status_text = 'К оплате ' . number_format($order_total, 0, '', ' ') . ' ₽';
                    $pay_form_inline_html = market_yoomoney_pay_form_inline_create($order_id, $order_total);
                }
                
                if ($paid_total > 0.009) {
                    $can_cancel_order = false;
                }

                if ($can_cancel_order) {
                    $cancel_btn_html = '<button type="button" class="m_order_cancel_btn market_order_cancel" data-order-id="' . $order_id . '">Отменить заказ</button>';
                }
                
                $items_html .= '
                    <div class="m_order_payment">
                        <div class="m_order_payment_head">
                            <div class="m_order_payment_title">Оплата</div>
                            <div class="m_order_payment_state">' . $payment_status_text . '</div>
                        </div>
                        ' . ($payments_rows_html !== '' ? $payments_rows_html : '<div class="m_order_pay_empty">Платежей пока не было.</div>') . '
                    </div>';
    
                $items_html .= '</div>';
    
                $orders_html .= '
                <div class="market_page_orders" data-order-id="' . $order_id . '">
                    <div class="m_order_wrapper">
                        <div class="m_order">
                            <div class="m_order_l">
                                <div class="m_order_id">Заказ #' . $order_id . '</div>
                                <div class="m_order_dt">' . $order_date . '</div>
                            </div>
                            <div class="m_order_r">
                                <span class="m_badge ' . $badge_class . '">' . $order_status . '</span>
                                <span class="m_badge ' . $pay_badge_class . '">' . $pay_badge_text . '</span>
                                ' . $pay_form_inline_html . '
                                ' . $cancel_btn_html . '
                                <span class="m_order_sum">' . number_format($order_total, 0, '', ' ') . ' ₽</span>
                                <i class="fa fa-chevron-down m_order_chevron" style="margin-left:10px; color:#999; width:14px; text-align:center;"></i>
                            </div>
                        </div>
                        ' . $items_html . '
                    </div>
                    </div>';
            }
    
            $orders_html .= '</div>';
        } else {
            $orders_html = '<div style="padding:30px 10px; text-align:center; color:#777;">У вас пока нет заказов.</div>';
        }
    
        $data_['q'] =
            '<div class="m_page">'
                .'<div class="m_card">'
                    .'<div class="m_card_h">Мои заказы</div>'
                    .$orders_html
                .'</div>'
            .'</div>';
    
        return $data_;
    }


    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////// Корзина //////////////
    //////////////////////////////////////////////////////////
    $data_['t'] = 'Моя корзина';

    $items_html = '';
    $total_sum = 0;
    
    // Формируем список товаров из сессии
    if (!empty($_SESSION['market']['items'])) {
        $items_html .= '<div class="m_items">';
        foreach ($_SESSION['market']['items'] as $s_cat_id => $kolvo) {
            $s_cat_id = (int)$s_cat_id;
            $kolvo = (float)$kolvo;
            
            if ($kolvo <= 0) continue; // Пропускаем нулевые количества
            
            $sql_item = "SELECT name, price
                         FROM s_cat
                         WHERE id = ?";
            $res_item = _DB($sql_item, [$s_cat_id]);
            if ($res_item === false){ die('SQL error'); }
            $row_item = $res_item->fetch(PDO::FETCH_ASSOC);
            
            if ($row_item) {
                $name = htmlspecialchars($row_item['name']);
                $price = (float)$row_item['price'];
                $sum = $price * $kolvo;
                $total_sum += $sum;
                
                
                $img_html = '<div class="m_item_img" style="display:flex;align-items:center;justify-content:center;"><i class="fa fa-image" style="color:#ccc;"></i></div>';
                                
                $img_html = '';
                $item_class = 'm_item m_item_noimg';
                
                // Получаем фото
                $sql_photo = "SELECT img
                              FROM a_photo
                              WHERE row_id = ?
                                AND a_menu_id = '7'
                              ORDER BY sid
                              LIMIT 1";
                $res_photo = _DB($sql_photo, [(int)$s_cat_id]);
                if ($res_photo === false){ die('SQL error'); }
                
                $row_photo = $res_photo->fetch(PDO::FETCH_ASSOC);
                
                if (is_array($row_photo) && isset($row_photo['img']) && $row_photo['img'] !== '') {
                    $item_class = 'm_item m_item_hasimg';
                    $img_html = '<div class="m_item_media"><div class="m_item_img"><img src="i/s_cat/original/'.$row_photo['img'].'" alt=""></div></div>';
                }
                
                $items_html .= '
                <div class="'.$item_class.'" data-id="'.$s_cat_id.'">
                    '.$img_html.'
                    <div class="m_item_mid">
                        <div class="m_item_t">'.$name.'</div>
                        <div class="m_qty">
                            <button type="button" class="m_qty_btn">-</button>
                            <input type="number" class="m_qty_inp" value="'.$kolvo.'" min="0" step="1" name="cart_items['.$s_cat_id.']" />
                            <button type="button" class="m_qty_btn">+</button>
                        </div>
                    </div>
                    <div class="m_item_pr">'.number_format($sum, 0, '', ' ').' ₽</div>
                </div>';
            }
        }
        $items_html .= '</div>';
    } 
    
    if ($total_sum == 0) {
        $items_html = '<div style="padding: 20px; text-align: center; color: #777;">Ваша корзина пуста</div>';
    }

    $data_['q'] ='';

    // Доставка и Автозаполнение
    if (isset($_SESSION['a_options']['Market: доставка']) and (int)$_SESSION['a_options']['Market: доставка']==1){
        $fio_val = '';
        $phone_val = '';
        $city_val = '';
        $address_val = '';
        
        // Достаем данные пользователя из профиля, если он авторизован
        if (isset($_SESSION['market']['i_contr_id'])) {
            $sql_usr = "SELECT c.name, c.phone, c.adress, city.name as city_name 
                        FROM i_contr c 
                        LEFT JOIN i_city city ON city.id = c.i_city_id 
                        WHERE c.id = ?";
            $res_usr = _DB($sql_usr, [(int)$_SESSION['market']['i_contr_id']]);
            if ($res_usr === false){ die('SQL error'); }
            
            $row_usr = $res_usr->fetch(PDO::FETCH_ASSOC);
            if ($row_usr) {
                $fio_val = _IN($row_usr['name'] ?? '');
                $phone_val = _IN($row_usr['phone'] ?? '');
                $address_val = _IN($row_usr['adress'] ?? '');
                $city_val = _IN($row_usr['city_name'] ?? '');
            }
        }

        $data_['q'].='<div class="m_page m_grid">'
            .'<form class="market_page_form market_page_form_cart">'
            .'<div class="m_left">'
            .'<div class="m_card m_shipping">'
            .'<div class="m_card_h">Доставка</div>'
            .'<div class="m_row1">'
                .'<div class="m_f city_div"><label>Город</label><input type="text" class="m_inp" name="city" placeholder="Город" value="'.$city_val.'"></div>'
            .'</div>'
            .'<div class="m_f"><label>Адрес</label><input type="text" class="m_inp" name="address" placeholder="Адрес доставки" value="'.$address_val.'"></div>'
            .'<div class="m_row2">'
                .'<div class="m_f"><label>ФИО</label><input type="text" class="m_inp" name="fio" placeholder="ФИО" value="'.$fio_val.'"></div>'
                .'<div class="m_f"><label>Телефон</label><input class="m_inp" name="phone" type="phone" placeholder="+7(XXX)XXX-XX-XX" value="'.$phone_val.'"></div>'
            .'</div>'
            .'<div class="m_f"><label>Комментарий</label><textarea class="m_ta" name="comment" placeholder="Комментарий к заказу"></textarea></div>'
        .'</div>'
        .'</form>'
        .'</div>';
    }else{
        $data_['q'] .='<div class="m_page">';
    }

    // Правая колонка: товары и кнопка
    $btn_disabled = ($total_sum == 0) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '';

    $data_['q'].='<div class="m_right">'
        .'<div class="m_card">'
            .'<div class="m_card_h">Товары</div>'
            .$items_html.
        '</div>'
        .'<div class="m_card">'
            .'<div class="m_card_h">Итого</div>'
            .'<div class="m_sum">'
                .'<div class="m_sum_t"><span>К оплате</span><b>'.number_format($total_sum, 0, '', ' ').' ₽</b></div>'
            .'</div>'
        .'</div>'
        .'<div class="m_card m_actions">'
            .'<button type="button" class="market_open__btn market_open__btn-primary market_order_create" '.$btn_disabled.'>Оформить заказ</button>'
        .'</div>'
    .'</div>'
.'</div>';

    return $data_;
}

//////////////////////////////////////////////////////////////
// форма смены пароля (из профиля) - только новый пароль
function market_password_form_create(){
    $data_=array();
    $data_['t']='Сменить пароль';
    $data_['i']='<div class="m_auth_i">Введите новый пароль и сохраните.</div>';

    $eye_svg_hide = '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>';
    $eye_svg_show = '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.1 3.51 3.5 2.1 21.9 20.5l-1.41 1.41-3.06-3.06A11.7 11.7 0 0 1 12 20C5 20 2 13 2 13a20.6 20.6 0 0 1 5.06-6.3L2.1 3.5Zm7.44 7.44A3 3 0 0 0 12 15c.35 0 .69-.06 1-.17l-3.46-3.46ZM12 6c7 0 10 7 10 7a20.3 20.3 0 0 1-3.3 4.46l-2.18-2.18A5 5 0 0 0 9.72 8.5L8.08 6.86A11.8 11.8 0 0 1 12 6Z"/></svg>';

    $data_['q']='
        <form class="market_page_form market_page_form_password">
            <label class="market_open__label" for="iPassNew">Новый пароль</label>
    
            <div class="market_open__inputwrap m_inpwrap">
                <input class="market_open__input market_open__input--withicon" id="iPassNew" name="passNew" type="password" placeholder="Новый пароль" required>
                <button type="button"
                    class="market_open__iconbtn market_pass_eye"
                    aria-label="Показать пароль"
                    data-eye="0"
                    data-svg-hide="'._IN($eye_svg_hide).'"
                    data-svg-show="'._IN($eye_svg_show).'">'.$eye_svg_hide.'</button>
            </div>
        </form>
    ';

    $data_['b']='
        <button type="button" class="market_open__btn market_open__btn-primary market_pass_change">Сохранить</button>
        <button type="button" class="market_open__btn market_open__btn-secondary market_pass_back">Назад</button>
    ';

    return $data_;
}


//Генерация и отправка кода на email
function send_code($email){
    //генерируем код
    $code=rand(1000,9999);
    $_SESSION['market']['code']=md5($code);
    
    $message=$_SESSION['a_options']['Market: отправка кода подтверждения на email'];
    $message=str_replace('@@code@@',$code,$message);
    if (!send_mail_smtp(
        $email,
        'Подтверждение email: '.$_SERVER['SERVER_NAME'],
        $message
    )){
        return 'Ошибка отправки письма через SMTP';exit;
    }
}

//////////////////////////////////////////////////////////////
//создание пользователя
function create_i_contr($email, $password){
    global $db,$protacol;
    
    $phone='';
    $name='';
    //телефон
    if (isset($_SESSION['a_options']['Market: телефон дополнительно при регистрации']) and $_SESSION['a_options']['Market: телефон дополнительно при регистрации']=='1'){
        //проверка обязательности полей
        $phone=_GP('phone');
        
        if ($phone!=''){
            $phone=preg_replace('/[\D]{1,}/s', '',$phone);
        }
    }
    //имя
    if (isset($_SESSION['a_options']['Market: имя дополнительно при регистрации']) and $_SESSION['a_options']['Market: имя дополнительно при регистрации']=='1'){
        $name=clean_person_name(_GP('name'));
    
        if (
            isset($_SESSION['a_options']['Market: имя обязательно при регистрации']) &&
            $_SESSION['a_options']['Market: имя обязательно при регистрации']=='1' &&
            $name==''
        ){
            exit('Имя не может быть пустым');
        }
        
        if (
            isset($_SESSION['a_options']['Market: имя уникально при регистрации']) &&
            $_SESSION['a_options']['Market: имя уникально при регистрации']=='1'
        ){
            $sql = "SELECT COUNT(*)
                                FROM i_contr
                                     WHERE name = ?";
            $res = _DB($sql, [$name]);
            if ($res === false){ die('SQL error'); }
            $row = $res->fetch(PDO::FETCH_NUM);
            if ($row[0]>0){
                exit($_SESSION['a_options']['Market: поле ИМЯ как отображается при регистрации (имя,логин,ФИО)'].' есть в базе. Напишите другое значение');
            }    
        }
        
    }
    
    //привязка телеграм аккаунта
    $telegram_id = 0;
    if (market_telegram_enabled()){
        $telegram_auth = market_telegram_get_session_auth();
        $telegram_id = isset($telegram_auth['id']) ? (int)$telegram_auth['id'] : 0;
    
        if (market_telegram_required() && $telegram_id <= 0){
            echo 'Необходимо привязать Telegram аккаунт';exit;
        }
    
        if ($telegram_id > 0){
            $other_user_id = market_telegram_find_user_id_by_telegram($telegram_id, $email);
            if ($other_user_id > 0){
                echo 'Этот Telegram уже привязан к другому аккаунту';exit;
            }
        }
    }

    
      
    $i_contr_id=0;
    //Реклама
    
    $sql_rek = "SELECT IF(COUNT(*)>0,i_reklama.id,'')
    				FROM i_reklama 
    					WHERE i_reklama.name='Регистрация на сайте' 
                        LIMIT 1";
    $res_rek = _DB($sql_rek);
    if ($res_rek === false){ die('SQL error 11'); }
    $row_rek = $res_rek->fetch(PDO::FETCH_NUM);
            
    $i_reklama_id=$row_rek[0];
    if ($i_reklama_id==''){
        $sql_insert = "INSERT into i_reklama (
        				chk_active,
        				name
        			) VALUES (
        				'1',
        				'Регистрация на сайте'
        )";
        
        $res_insert = _DB($sql_insert);
        if ($res_insert === false){ die('SQL error'); }
        $i_reklama_id = (int)$db->lastInsertId();
    }
    $sql = "SELECT id
            FROM i_contr
            WHERE i_contr.email = ?";
    $res = _DB($sql, [$email]);
    if ($res === false){ die('SQL error 12'); }
    
    $rows = $res->fetchAll(PDO::FETCH_ASSOC);
    $password_md5 = md5($password);
    
    if (count($rows) === 1) {
        $i_contr_id = (int)$rows[0]['id'];
    
        $sql_upp = "UPDATE i_contr 
                    SET password = ?,
                        name = ?,
                        phone = ?,
                        telegram_id = ?,
                        data_change = NOW()
                    WHERE id = ?";
        $res_upp = _DB($sql_upp, array(
            md5($password_md5.$_SESSION['a_options']['secret_key'].$password_md5),
            $name,
            $phone,
            ($telegram_id > 0 ? $telegram_id : null),
            $i_contr_id
        ));
        if ($res_upp === false){ die('SQL error 13'); }
    
    } elseif (count($rows) > 1) {
        die('Ошибка: найдено несколько пользователей с одинаковым email');
    }else{
        //добавление 
        $sql_insert = "INSERT INTO i_contr (
                chk_active,
                name,
                email,
                password,
                i_reklama_id,
                phone,
                telegram_id,
                chk_notification
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        
        $res_insert = _DB($sql_insert, array(
            1,
            $name,
            $email,
            md5($password_md5.$_SESSION['a_options']['secret_key'].$password_md5),
            $i_reklama_id,
            $phone,
            ($telegram_id > 0 ? $telegram_id : null)
        ));
        if ($res_insert === false){ die('SQL error 14'); }
        
        // ВАЖНО: lastInsertId берём у PDO из global $db
        $i_contr_id = (int)$db->lastInsertId();
        
        
        /**
        * Прочая логика регистрации.
        * $_t='create_i_contr'
        * 
        * $i_contr_id
        * $email
        * $password
        * $password_md5
        * $name
        * $phone
        * $telegram_id
        */
        $_market_file = __DIR__ . '/../_include/_market.php';
        if (is_file($_market_file)) {
            $_t='create_i_contr';
            require $_market_file;
        }
        
        //ОПОВЕЩАЕМ ПОЛЬЗОВАТЕЛЯ ОБ УСПЕШНОЙ РЕГИСТРАЦИИ на email
        $message=$_SESSION['a_options']['Market: отправка оповещения об успешной регистрации на email'];
        $message=str_replace('@@email@@',$email,$message);
        $message=str_replace('@@password@@',$password,$message);
        //$message=str_replace('@@link@@',$protacol.$_SERVER['SERVER_NAME'].'/?com=market&market_login&email='.$email.'&hash='.md5(md5($password).$_SESSION['a_options']['secret_key'].md5($password)),$message);
        //убираем авторизацию по хэшу
        $message=str_replace('@@link@@',$protacol.$_SERVER['SERVER_NAME'].'/?com=market&profile',$message);
        if (!send_mail_smtp(
            $email,
            'Успешная регистрация на сайте '.$_SERVER['SERVER_NAME'],
            $message
        )){
            return 'Ошибка отправки письма через SMTP';exit;
        }
    }
    
    
    
    return $i_contr_id;
}

//////////////////////////////////////////////////////////////
//авторизация пользователя
function auth_i_contr($email, $hash, $password=''){
    global $db;
    $result=array();
    $result['status_']='';
 
    
    $sql = "SELECT i_contr.id, i_contr.chk_active
            FROM i_contr
            WHERE i_contr.email = ?";
    $res = _DB($sql, [$email]);
    if ($res === false){ die('SQL error'); }
    
    $row = $res->fetch(PDO::FETCH_ASSOC);
    
    $sql = "SELECT i_contr.id, i_contr.chk_active
            FROM i_contr
            WHERE i_contr.email = ?
              AND i_contr.password = ?";
    $res = _DB($sql, [
        $email,
        $hash
    ]);
    if ($res === false){ die('SQL error'); }
    
    $row = $res->fetch(PDO::FETCH_ASSOC);
    
    
    
    if (isset($row['id']) and (int)$row['id']>0){
        if ($row['chk_active']=='1'){
            $result['status_']='ok';
            
            
            //создаем сессию авторизации
            $_SESSION['market']['i_contr_id']=$row['id'];
            $i_contr_id = $row['id'];
            
            //если вход был с вводом пароля (не через хэш)
            $_SESSION['market']['password']=$password;
            
            
            /**
            * Прочая логика авторизации.
            * $_t='auth_i_contr'
            * 
            * $i_contr_id
            * $email
            * $hash
            * $password //если не вход с админки
    
            */
            $_market_file = __DIR__ . '/../_include/_market.php';
            if (is_file($_market_file)) {
                $_t='auth_i_contr';
                require $_market_file;
            }
            
        }
        else{
            $result['status_']='user not active';
        }
    }else{
        $result['status_']='no correct email or password';
    }
    return $result;
}
///////////////////////////////////////////////////////////////////////////////////////////////
////////// вспомогательные функции ////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////
function market_get_include_dir()
{
    static $dir = null;

    if ($dir !== null) {
        return $dir;
    }

    $dirs = array(
        dirname(__DIR__) . '/_include',
        __DIR__ . '/shablon/_include'
    );

    foreach ($dirs as $tmp_dir) {
        if (is_dir($tmp_dir)) {
            $dir = $tmp_dir;
            return $dir;
        }
    }

    $dir = $dirs[0];
    return $dir;
}

//Дополнительные индивидуальные табы в корзине для разных задач и разных проектов
function market_get_market_tabs()
{
    static $tabs = null;

    if ($tabs !== null) {
        return $tabs;
    }

    $tabs = array();
    $dir = rtrim(market_get_include_dir(), '/\\') . '/market_tabs';

    if (!is_dir($dir)) {
        return $tabs;
    }

    $files = glob($dir . '/_tab_*.php');

    if (!$files) {
        return $tabs;
    }

    sort($files, SORT_NATURAL);

    foreach ($files as $file) {
        $market_tab_mode = 'meta';
        $tab_meta = include $file;

        if (!is_array($tab_meta)) {
            continue;
        }

        $tab_key = isset($tab_meta['key']) ? trim((string)$tab_meta['key']) : '';
        $tab_title = isset($tab_meta['title']) ? trim((string)$tab_meta['title']) : '';

        if ($tab_key === '' || $tab_title === '') {
            continue;
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $tab_key)) {
            continue;
        }

        $tabs[$tab_key] = array(
            'title' => $tab_title,
            'file' => $file
        );
    }

    return $tabs;
}

function market_render_market_tab($tab, $vars = array())
{
    $tabs = market_get_market_tabs();

    if (!isset($tabs[$tab]) || empty($tabs[$tab]['file'])) {
        return '';
    }

    if (!empty($vars) && is_array($vars)) {
        extract($vars, EXTR_SKIP);
    }

    ob_start();
    $market_tab_mode = 'html';
    include $tabs[$tab]['file'];
    return ob_get_clean();
}
function market_telegram_enabled()
{
    return (
        isset($_SESSION['a_options']['Market: telegram дополнительно при регистрации'])
        && (int)$_SESSION['a_options']['Market: telegram дополнительно при регистрации'] === 1
    );
}

function market_telegram_required()
{
    return (
        isset($_SESSION['a_options']['Market: telegram обязательно при регистрации'])
        && (int)$_SESSION['a_options']['Market: telegram обязательно при регистрации'] === 1
    );
}

function market_telegram_bot_username()
{
    if (!isset($_SESSION['a_options']['Market: telegram bot username'])) {
        return '';
    }

    return trim((string)$_SESSION['a_options']['Market: telegram bot username']);
}

function market_telegram_bot_token()
{
    if (!isset($_SESSION['a_options']['Market: telegram bot token'])) {
        return '';
    }

    return trim((string)$_SESSION['a_options']['Market: telegram bot token']);
}

function market_telegram_widget_ready()
{
    return (market_telegram_bot_username() !== '' && market_telegram_bot_token() !== '');
}

function market_telegram_clear_session()
{
    unset($_SESSION['market']['telegram_auth']);
}

function market_telegram_get_session_auth()
{
    if (
        isset($_SESSION['market']['telegram_auth'])
        && is_array($_SESSION['market']['telegram_auth'])
        && !empty($_SESSION['market']['telegram_auth']['id'])
    ) {
        return $_SESSION['market']['telegram_auth'];
    }

    return array();
}

function market_telegram_set_session_auth(array $auth)
{
    $_SESSION['market']['telegram_auth'] = array(
        'id' => isset($auth['id']) ? (int)$auth['id'] : 0,
        'first_name' => isset($auth['first_name']) ? trim((string)$auth['first_name']) : '',
        'last_name' => isset($auth['last_name']) ? trim((string)$auth['last_name']) : '',
        'username' => isset($auth['username']) ? preg_replace('/[^a-zA-Z0-9_]+/', '', (string)$auth['username']) : '',
        'photo_url' => isset($auth['photo_url']) ? trim((string)$auth['photo_url']) : '',
        'auth_date' => isset($auth['auth_date']) ? (int)$auth['auth_date'] : 0
    );
}

function market_telegram_validate_auth(array $auth, &$error = '')
{
    $error = '';

    if (!market_telegram_widget_ready()) {
        $error = 'Не настроен Telegram bot username/token';
        return false;
    }

    $required_fields = array('id', 'auth_date', 'hash');

    foreach ($required_fields as $field) {
        if (!isset($auth[$field]) || trim((string)$auth[$field]) === '') {
            $error = 'Не хватает данных авторизации Telegram';
            return false;
        }
    }

    if (!preg_match('/^\d+$/', (string)$auth['id'])) {
        $error = 'Некорректный Telegram ID';
        return false;
    }

    if (!preg_match('/^\d+$/', (string)$auth['auth_date'])) {
        $error = 'Некорректная дата Telegram авторизации';
        return false;
    }

    $auth_date = (int)$auth['auth_date'];
    if ($auth_date < (time() - 86400)) {
        $error = 'Устаревшая Telegram авторизация';
        return false;
    }

    $check_hash = trim((string)$auth['hash']);
    $data_check_arr = array();

    foreach ($auth as $key => $val) {
        if ($key === 'hash') {
            continue;
        }

        $val = is_scalar($val) ? trim((string)$val) : '';
        if ($val === '') {
            continue;
        }

        $data_check_arr[] = $key . '=' . $val;
    }

    sort($data_check_arr, SORT_STRING);

    $data_check_string = implode("\n", $data_check_arr);
    $secret_key = hash('sha256', market_telegram_bot_token(), true);
    $hash = hash_hmac('sha256', $data_check_string, $secret_key);

    if (!hash_equals($hash, $check_hash)) {
        $error = 'Подпись Telegram не прошла проверку';
        return false;
    }

    return true;
}

function market_telegram_find_user_id_by_telegram($telegram_id, $email = '')
{
    $telegram_id = (int)$telegram_id;
    if ($telegram_id <= 0) {
        return 0;
    }

    $sql = "SELECT id
            FROM i_contr
            WHERE telegram_id = ?";
    $params = array($telegram_id);

    if ($email !== '') {
        $sql .= " AND email <> ?";
        $params[] = $email;
    }

    $sql .= " LIMIT 1";

    $res = _DB($sql, $params);
    if ($res === false){ die('SQL error telegram'); }

    $row = $res->fetch(PDO::FETCH_NUM);

    return isset($row[0]) ? (int)$row[0] : 0;
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/// обработка _t ////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/// ШАГ 1 - ОТКРЫТИЕ ОКНА
if ($_t=='market_load'){
    
    //Есть авторизация
    if (isset($_SESSION['market']['i_contr_id'])){
        $market_page=_GP('market_page','cart');
        $data_=market_cart_form_create($market_page);
        $data_['status_']='ok';
    }
    else{// нет авторизации - проверяем этап
        //есть email
        if (isset($_SESSION['market']['email'])){
            if (isset($_SESSION['market']['code'])){
                $_t='market_chk_code';
            }
            else{
                $_t='market_chk_email';
            }
        }
        else{//нет авторизации
            $data_=market_auth_form_create('step1');
        }
    }
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//очистка market_logout
if ($_t=='market_logout'){
    unset($_SESSION['market']);
    $data_['status_']='ok';
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//ШАГ 2 -проверка email - отправка кода на почту - шаг 2
if ($_t=='market_chk_email'){
    
    //получаем email
    $email = _GP('email');
    //если email пустой - ищем в сессиях (если окно было открыто повторно)
    if ($email==''){
        if (isset($_SESSION['market']['email'])){
            $email=$_SESSION['market']['email'];
        }
    }
    if ($email === '') { echo 'no email'; exit;}
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {echo 'Не корректный email: '.$email;exit;}


    $sql = "SELECT COUNT(*) AS cnt, MIN(chk_active) AS chk_active
                        FROM i_contr
                             WHERE email = ?";
    $res = _DB($sql, [$email]);
    if ($res === false){ die('SQL error'); }
    $row = $res->fetch(PDO::FETCH_ASSOC);

    $cnt = (int)$row['cnt'];
    $chk_active = $row['chk_active']; // корректно только если cnt == 1
  
    //email не зарегистрирован -> регистрация
    if ($cnt === 0) {
        
        //очистка телеграм сессии
        market_telegram_clear_session();
        
        //создаем форму
        $data_=market_auth_form_create('step2',$email);
        $data_['status_'] = 'no_email';
        $_SESSION['market']['email']=$email;
        //отправляем код на email
        send_code($email);

    }
    //email найден
    elseif ($cnt == 1)  {
        if($chk_active == '1'){//включен
        
            //очистка телеграм сессии
            market_telegram_clear_session();
        
            $_SESSION['market']['email']=$email;
            
            //авторизация - ввод пароля
            $data_=market_auth_form_create('step3',$email);
            $data_['status_'] = 'ok';
            
        }else{ //выключен
            $data_['status_'] = 'email_no_active';
        }
    }
    else { //обработка ошибок
        $data_['status_'] = 'many_email';
    }
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//ШАГ 3 - регистрация: проверка кода
if ($_t=='market_chk_code'){
    $email = _GP('email');
    if ($email==''){
        if (isset($_SESSION['market']['email'])){
            $email=$_SESSION['market']['email'];
        }
    }
    if ($email === '') { echo 'no email'; exit;}
    
    $code = _GP('code');
    if ($code==''){
        if (isset($_SESSION['market']['codeValid'])){
            $code=$_SESSION['market']['codeValid'];
        }
    }
    
    if (isset($_SESSION['market'])){
        if ($_SESSION['market']['email']==$email){
            
            if ( $_SESSION['market']['code']==md5($code)){
                $_SESSION['market']['codeValid']=$code;
                
                
                //Создаем пользователя
                $data_=market_auth_form_create('step4',$email,$code);
            
                $data_['status_']='ok';  
            }
            else{
                $data_=market_auth_form_create('step2',$email);
                $data_['status_']='no correct code'; 
            }
        }
        else{
            $data_['status_']='no correct email'; 
        }
        
    }
    else{
      $data_['status_']='no sess';  
    }
}
//
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////ШАГ 4 - регистрация: создание пользователя
if ($_t=='market_create_update_user'){
  
    $email = _GP('email');
    if ($email==''){
        if (isset($_SESSION['market']['email'])){
            $email=$_SESSION['market']['email'];
        }
    }
    if ($email === '') { echo 'Отсутствует email'; exit;}
    
    $code = _GP('code');
    if ($code==''){
        if (isset($_SESSION['market']['codeValid'])){
            $code=$_SESSION['market']['codeValid'];
        }
    }
    $passCreate = _GP('passCreate');
    if ($passCreate==''){echo 'Отсутствует пароль';exit;}
    
    //проверка обязательности заполнения имени
    if (
            isset($_SESSION['a_options']['Market: имя дополнительно при регистрации']) &&
            $_SESSION['a_options']['Market: имя дополнительно при регистрации']=='1' &&
            isset($_SESSION['a_options']['Market: имя обязательно при регистрации']) &&
            $_SESSION['a_options']['Market: имя обязательно при регистрации']=='1'
        ){
            $name = clean_person_name(_GP('name'));
            if ($name==''){
                echo 'Имя не может быть пустым';exit;
            }
        }
        
    //если сессия market открыта
    if (isset($_SESSION['market'])){
        if ($_SESSION['market']['email']==$email){
            
            if ( $_SESSION['market']['code']==md5($code)){
                $_SESSION['market']['codeValid']=$code;
                
                //Создаем пользователя
                $i_contr_id=create_i_contr($email,$passCreate);
                
                if ((int)$i_contr_id>0){
                    
                    //авторизация
                    $result=auth_i_contr($email, md5(md5($passCreate).$_SESSION['a_options']['secret_key'].md5($passCreate)),$passCreate);
                    if ($result['status_']=='ok'){
                        $data_=market_cart_form_create('cart');
                        $data_['status_']='ok';  
                    }else{//выводим статус не успешной авторизации
                    
                        echo $result['status_'];exit;
                    }
                }else{
                    echo 'Ошибка создания пользователя $email='.$email;exit;
                }
            }
            else{
                echo 'Не верный код';exit;
            }
        }
        else{
            echo 'Не корректный email';exit;
        }
    }
    else{
      echo 'Отсутствует сессия';exit;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////ШАГ 5 - вход по email и хэшу
if ($_t=='market_login'){
    
    $email = _GP('email');
    if ($email==''){
        if (isset($_SESSION['market']['email'])){
            $email=$_SESSION['market']['email'];
        }
    }
    if ($email === '') { echo 'Отсутствует email'; exit;}
    
    $hash = _GP('hash');
    if ($hash === '') { echo 'Отсутствует hash'; exit;}
    
    $data_=auth_i_contr($email, $hash);
   
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////ШАГ 6 - восстановление пароля: авторизация - запрос кода
if ($_t=='market_recovery_password'){
    
    $email = _GP('email');
    if ($email==''){
        if (isset($_SESSION['market']['email'])){
            $email=$_SESSION['market']['email'];
        }
    }
    if ($email === '') { echo 'Отсутствует email'; exit;}
    
    //отправляем код на email
    send_code($email);
    $data_=market_auth_form_create('step2',$email);
    $data_['status_']='ok';
   
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ШАГ 7 - авторизация: отправка пароля
if ($_t=='market_chk_password'){
    
    $email = _GP('email');
    if ($email==''){
        if (isset($_SESSION['market']['email'])){
            $email=$_SESSION['market']['email'];
        }
    }
    if ($email === '') { echo 'Отсутствует email'; exit;}
    
    $password = _GP('password');
    
    if ($password==''){
        if (isset($_SESSION['market']['password'])){
            $password=$_SESSION['market']['password'];
        }
    }
    $data_=auth_i_contr($email,md5(md5($password).$_SESSION['a_options']['secret_key'].md5($password)),$password);
    //успешная авторизация
    if ($data_['status_']=='ok'){
        $data_=market_cart_form_create();
        $data_['status_']='ok';
    }
    else{
        
    }
   
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ВКЛАДКИ корзины/кабинета
if ($_t=='market_tab'){
    if (!isset($_SESSION['market']['i_contr_id'])){
        $data_ = market_auth_form_create('step1');
        $data_['status_'] = 'no_auth';
    } else {
        $tab = _GP('tab');
        if ($tab=='') $tab = 'cart';
        $data_ = market_cart_form_create($tab);
        $data_['status_'] = 'ok';
    }
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Отмена заказа
if ($_t=='market_order_cancel'){
    if (!isset($_SESSION['market']['i_contr_id'])){
        $data_ = array('status_' => 'error', 'message' => 'Необходимо авторизоваться');
    } else {
        $i_contr_id = (int)$_SESSION['market']['i_contr_id'];
        $order_id = (int)_GP('order_id');

        if ($order_id <= 0){
            $data_ = array('status_' => 'error', 'message' => 'Некорректный номер заказа');
        } else {
            $res_order_cancel = _DB(
                "SELECT id, status
                 FROM m_zakaz
                 WHERE id = ?
                   AND i_contr_id = ?
                   AND chk_active = 1
                 LIMIT 1",
                array($order_id, $i_contr_id)
            );
            if ($res_order_cancel === false){ die('SQL error'); }

            $row_order_cancel = $res_order_cancel->fetch(PDO::FETCH_ASSOC);

            if (!$row_order_cancel){
                $data_ = array('status_' => 'error', 'message' => 'Заказ не найден');
            } else {
                $order_status = isset($row_order_cancel['status']) ? trim((string)$row_order_cancel['status']) : '';

                if (in_array($order_status, array('Отменен', 'Выполнен', 'Частично выполнен', 'Доставлен', 'Отправлен'), true)) {
                    $data_ = array('status_' => 'error', 'message' => 'Этот заказ уже нельзя отменить');
                } else {
                    $res_pay_cancel = _DB(
                        "SELECT COALESCE(SUM(CASE WHEN tip = 'Дебет' THEN -summa ELSE summa END), 0)
                         FROM m_platezi
                         WHERE id_z_p_p = ?
                           AND a_menu_id = 16",
                        array($order_id)
                    );
                    if ($res_pay_cancel === false){ die('SQL error'); }

                    $row_pay_cancel = $res_pay_cancel->fetch(PDO::FETCH_NUM);
                    $paid_total = isset($row_pay_cancel[0]) ? (float)$row_pay_cancel[0] : 0;

                    if ($paid_total > 0.009){
                        $data_ = array('status_' => 'error', 'message' => 'Нельзя отменить уже оплаченный заказ');
                    } else {
                        $res_cancel = _DB(
                            "UPDATE m_zakaz
                             SET status = ?,
                                 data_end = NOW(),
                                 data_change = NOW()
                             WHERE id = ?
                               AND i_contr_id = ?
                               AND chk_active = 1
                             LIMIT 1",
                            array('Отменен', $order_id, $i_contr_id)
                        );
                        if ($res_cancel === false){ die('SQL error'); }

                        $data_ = market_cart_form_create('orders');
                        $data_['status_'] = 'ok';
                        $data_['message'] = 'Заказ успешно отменен';
                    }
                }
            }
        }
    }
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Telegram auth widget: сохранить привязку в сессию регистрации
if ($_t=='market_tg_auth_save'){
    if (!market_telegram_enabled()){
        echo 'Telegram авторизация отключена';exit;
    }

    if (!market_telegram_widget_ready()){
        echo 'Не настроен Telegram bot username/token';exit;
    }

    $auth = array(
        'id' => _GP('id'),
        'first_name' => _GP('first_name'),
        'last_name' => _GP('last_name'),
        'username' => _GP('username'),
        'photo_url' => _GP('photo_url'),
        'auth_date' => _GP('auth_date'),
        'hash' => _GP('hash')
    );

    $error = '';
    if (!market_telegram_validate_auth($auth, $error)){
        echo $error;exit;
    }

    $telegram_id = (int)$auth['id'];
    $current_email = isset($_SESSION['market']['email']) ? trim((string)$_SESSION['market']['email']) : '';

    $other_user_id = market_telegram_find_user_id_by_telegram($telegram_id, $current_email);
    if ($other_user_id > 0){
        echo 'Этот Telegram уже привязан к другому аккаунту';exit;
    }

    market_telegram_set_session_auth($auth);

    $telegram_username = isset($auth['username']) ? preg_replace('/[^a-zA-Z0-9_]+/', '', (string)$auth['username']) : '';
    $status_text = 'Telegram привязан';
    if ($telegram_username !== ''){
        $status_text .= ' (@' . $telegram_username . ')';
    }

    $data_ = array(
        'status_' => 'ok',
        'telegram_id' => $telegram_id,
        'telegram_username' => $telegram_username,
        'status_text' => $status_text
    );
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Telegram auth widget: получить текущее состояние привязки из сессии
if ($_t=='market_tg_auth_status'){
    $telegram_auth = market_telegram_get_session_auth();
    $telegram_id = isset($telegram_auth['id']) ? (int)$telegram_auth['id'] : 0;
    $telegram_username = isset($telegram_auth['username']) ? trim((string)$telegram_auth['username']) : '';

    $status_text = 'Telegram не привязан';
    if ($telegram_id > 0){
        $status_text = 'Telegram привязан';
        if ($telegram_username !== ''){
            $status_text .= ' (@' . $telegram_username . ')';
        }
    }

    $data_ = array(
        'status_' => 'ok',
        'telegram_id' => $telegram_id,
        'telegram_username' => $telegram_username,
        'status_text' => $status_text
    );
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Telegram auth widget: отвязать текущую привязку из сессии регистрации
if ($_t=='market_tg_auth_reset'){
    market_telegram_clear_session();

    $data_ = array(
        'status_' => 'ok',
        'telegram_id' => 0,
        'telegram_username' => '',
        'status_text' => 'Telegram не привязан'
    );
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Отвязка Telegram из профиля
if ($_t=='market_profile_unlink_telegram'){
    if (!isset($_SESSION['market']['i_contr_id'])){
        echo 'Не найден пользователь';exit;
    }

    if (
        !isset($_SESSION['a_options']['Market: telegram дополнительно при регистрации'])
        || (int)$_SESSION['a_options']['Market: telegram дополнительно при регистрации'] != 1
    ){
        echo 'Telegram отключен в настройках';exit;
    }

    if (
        isset($_SESSION['a_options']['Market: telegram обязательно при регистрации'])
        && (int)$_SESSION['a_options']['Market: telegram обязательно при регистрации'] == 1
    ){
        echo 'Отвязка Telegram запрещена настройками сайта';exit;
    }

    $i_contr_id = (int)$_SESSION['market']['i_contr_id'];

    $sql = "UPDATE i_contr
            SET telegram_id = NULL,
                data_change = NOW()
            WHERE id = ?";
    $res = _DB($sql, array($i_contr_id));
    if ($res === false){ die('SQL error'); }

    $data_ = market_cart_form_create('profile');
    $data_['status_'] = 'ok';
    $data_['message'] = 'Telegram успешно отвязан';
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Привязка Telegram из профиля
if ($_t=='market_profile_bind_telegram'){
    if (!isset($_SESSION['market']['i_contr_id'])){
        echo 'Не найден пользователь';exit;
    }

    if (
        !isset($_SESSION['a_options']['Market: telegram дополнительно при регистрации'])
        || (int)$_SESSION['a_options']['Market: telegram дополнительно при регистрации'] != 1
    ){
        echo 'Telegram отключен в настройках';exit;
    }

    $auth = array(
        'id' => _GP('id'),
        'first_name' => _GP('first_name'),
        'last_name' => _GP('last_name'),
        'username' => _GP('username'),
        'photo_url' => _GP('photo_url'),
        'auth_date' => _GP('auth_date'),
        'hash' => _GP('hash')
    );

    $error = '';
    if (!market_telegram_validate_auth($auth, $error)){
        echo $error;exit;
    }

    $telegram_id = isset($auth['id']) ? (int)$auth['id'] : 0;
    if ($telegram_id <= 0){
        echo 'Некорректный Telegram ID';exit;
    }

    $i_contr_id = (int)$_SESSION['market']['i_contr_id'];

    $sql_cur = "SELECT email
                FROM i_contr
                WHERE id = ?
                LIMIT 1";
    $res_cur = _DB($sql_cur, array($i_contr_id));
    if ($res_cur === false){ die('SQL error'); }

    $row_cur = $res_cur->fetch(PDO::FETCH_ASSOC);
    $current_email = isset($row_cur['email']) ? trim((string)$row_cur['email']) : '';

    $other_user_id = market_telegram_find_user_id_by_telegram($telegram_id, $current_email);
    if ($other_user_id > 0 && $other_user_id != $i_contr_id){
        echo 'Этот Telegram уже привязан к другому аккаунту';exit;
    }

    $sql = "UPDATE i_contr
            SET telegram_id = ?,
                data_change = NOW()
            WHERE id = ?";
    $res = _DB($sql, array($telegram_id, $i_contr_id));
    if ($res === false){ die('SQL error'); }

    market_telegram_set_session_auth($auth);

    $data_ = market_cart_form_create('profile');
    $data_['status_'] = 'ok';
    $data_['message'] = 'Telegram успешно привязан';
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Сохранение профиля
if ($_t=='market_profile_save'){
    //$email = _GP('email');
    if (!isset($_SESSION['market']['i_contr_id'])){
        echo 'Не найден пользователь';exit;
    }

    $SQL_UPP = array();
    $SQL_PARAMS = array();

    if (isset($_SESSION['a_options']['Market: телефон дополнительно при регистрации']) and $_SESSION['a_options']['Market: телефон дополнительно при регистрации']=='1'){
        // проверка обязательности полей
        $phone = _GP('phone');
        if (isset($_SESSION['a_options']['Market: телефон обязательно при регистрации']) and $_SESSION['a_options']['Market: телефон обязательно при регистрации']=='1'){
            $phone = preg_replace('/[\D]{1,}/s', '', $phone);
            if ($phone==''){
                echo 'Телефон не может быть пустым';exit;
            }
        }

        // Запрос на обновление
        $SQL_UPP[] = "phone = ?";
        $SQL_PARAMS[] = $phone;
    }

    if (isset($_SESSION['a_options']['Market: имя дополнительно при регистрации']) and $_SESSION['a_options']['Market: имя дополнительно при регистрации']=='1'){
        $name = clean_person_name(_GP('name'));
    
        if (
            isset($_SESSION['a_options']['Market: имя обязательно при регистрации']) &&
            $_SESSION['a_options']['Market: имя обязательно при регистрации']=='1' &&
            $name==''
        ){
            echo 'Имя не может быть пустым';exit;
        }
    
        // Запрос на обновление
        $SQL_UPP[] = "name = ?";
        $SQL_PARAMS[] = $name;
    }

    if (isset($_SESSION['a_options']['Market: доставка']) and $_SESSION['a_options']['Market: доставка']=='1'){
        $city = clean_person_name(_GP('city'));
        $addr = clean_text_field(_GP('addr'));

        if ($city!=''){
            $sql = "SELECT COUNT(*)
                    FROM i_city
                    WHERE i_city.name = ?";
            $res = _DB($sql, array($city));
            if ($res === false){ die('SQL error'); }

            $row = $res->fetch(PDO::FETCH_NUM);
            if ((int)$row[0]==0){
                echo 'Не верно указан город, выберите с выпадающего списка';exit;
            }
            else{
                $sql = "SELECT i_city.id
                        FROM i_city
                        WHERE i_city.name = ?";
                $res = _DB($sql, array($city));
                if ($res === false){ die('SQL error'); }

                $row = $res->fetch(PDO::FETCH_NUM);

                // Запрос на обновление
                $SQL_UPP[] = "i_city_id = ?";
                $SQL_PARAMS[] = $row[0];
            }
        }
        else{
            if ($addr!=''){
                echo 'Укажите город!';exit;
            }
        }

        // Запрос на обновление
        $SQL_UPP[] = "adress = ?";
        $SQL_PARAMS[] = $addr;
    }

    // Обновляем данные пользователя
    $sql_upp = "UPDATE i_contr 
                SET ";

    if (count($SQL_UPP) > 0){
        $sql_upp .= implode(", ", $SQL_UPP).", ";
    }

    $sql_upp .= "data_change = NOW()
                 WHERE i_contr.id = ?";

    $SQL_PARAMS[] = $_SESSION['market']['i_contr_id'];

    $res_upp = _DB($sql_upp, $SQL_PARAMS);
    if ($res_upp === false){ die('SQL error'); }

    $data_['sql'] = $sql_upp;
    $data_['status_'] = 'ok';
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
if ($_t=='city_autocomplete'){

    
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Content-type: application/json');
    $term=_GP('term');
    $sql = "SELECT i_city.id,
                   i_city.name,
                   i_city.region
            FROM i_city
            WHERE i_city.name LIKE ?
            GROUP BY i_city.name
            ORDER BY i_city.name
            LIMIT 20";
    $data_['items'] = array();
    
    $res = _DB($sql, ['%'.$term.'%']);
    if ($res === false){ die('SQL error'); }
    
    $rows = $res->fetchAll(PDO::FETCH_ASSOC);
    $i = 0;
    foreach ($rows as $myrow){
        $data_['items'][$i]['name'] = $myrow['name'].' ('.$myrow['region'].')';
        $data_['items'][$i]['label'] = $myrow['name'];
        $data_['items'][$i]['id'] = $myrow['id'];
        $i++;
    }

}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Загрузка фото
if ($_t=='market_load_photo'){

    header('Content-Type: application/json; charset=utf-8');

    $resp = ['status'=>'error','message'=>'Ошибка'];

    try {

        if (!isset($_SESSION['market']['i_contr_id']) || (int)$_SESSION['market']['i_contr_id'] <= 0) {
            throw new Exception('Не задан i_contr_id в сессии');
        }

        if (!isset($_FILES['photo'])) {
            throw new Exception('Файл не передан (photo)');
        }

        $f = $_FILES['photo'];

        if (!isset($f['error']) || is_array($f['error'])) {
            throw new Exception('Некорректные данные файла');
        }

        if ($f['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Ошибка загрузки файла (код: '.$f['error'].')');
        }

        // 2) Лимит 1 МБ
        $maxSize = 1024 * 1024*2; // 1mb
        if ((int)$f['size'] > $maxSize) {
            throw new Exception('Файл слишком большой (макс. 2MB)');
        }

        if (!is_uploaded_file($f['tmp_name'])) {
            throw new Exception('Файл не прошёл проверку загрузки');
        }

        // 1) Валидность изображения + MIME
        $imgInfo = @getimagesize($f['tmp_name']);
        if ($imgInfo === false || empty($imgInfo['mime'])) {
            throw new Exception('Файл не является корректным изображением');
        }

        $mimeByImage = $imgInfo['mime'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeByFinfo = $finfo->file($f['tmp_name']);

        // Разрешённые форматы
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        // MIME должен быть разрешён и совпадать по сути (getimagesize + finfo)
        if (!isset($allowed[$mimeByImage]) || !isset($allowed[$mimeByFinfo]) || $allowed[$mimeByImage] !== $allowed[$mimeByFinfo]) {
            throw new Exception('Недопустимый формат. Разрешены JPG, PNG, WEBP');
        }

        $ext = $allowed[$mimeByImage];

        // 4) Имя файла из email
        $sql = "SELECT i_contr.email
                            FROM i_contr
                                 WHERE i_contr.id = ?";
        $res = _DB($sql, [$_SESSION['market']['i_contr_id']]);
        if ($res === false){ die('SQL error'); }
        $row = $res->fetch(PDO::FETCH_NUM);

        if (!isset($row[0]) || trim($row[0])=='') {
            throw new Exception('Не найден email контрагента');
        }

        $file_name = trim($row[0]);

        // замены "(@, ., и другие знаки) -> _"
        $file_name = str_replace(
            ['@','.', ' ', '+','-','=',':',';','!','?','#','$','%','^','&','*','(',')','[',']','{','}','<','>','"',"'","`",'~','|','/','\\',','],
            '_',
            $file_name
        );

        // подчистим повторные _
        $file_name = preg_replace('/_+/', '_', $file_name);
        $file_name = trim($file_name, '_');

        if ($file_name=='') {
            throw new Exception('Не удалось сформировать имя файла');
        }

        // 3) Папка сохранения
        $relDir = '/i/i_contr/original/';
        $absDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/').$relDir;

        if (!is_dir($absDir)) {
            if (!mkdir($absDir, 0755, true)) {
                throw new Exception('Не удалось создать директорию для загрузки');
            }
        }

        // Чтобы не оставались старые расширения (jpg/png/webp) — удалим прежние
        foreach (['jpg','png','webp','jpeg'] as $oldExt) {
            $p = $absDir.$file_name.'.'.$oldExt;
            if (is_file($p)) {
                @unlink($p);
            }
        }

        $newName = $file_name.'.'.$ext;

        $absPath = $absDir.$newName;
        $relPath = $relDir.$newName;

        if (!move_uploaded_file($f['tmp_name'], $absPath)) {
            throw new Exception('Не удалось сохранить файл');
        }
        
        
        //удаляем старые фото
        $sql_del = "DELETE 
            			FROM a_photo 
            				WHERE row_id=?
                            AND a_menu_id='25'";
        $res_del = _DB($sql_del, [$_SESSION['market']['i_contr_id']]);
        if ($res_del === false){ die('SQL error'); }
   
   
        //добавляем новые фото
        $sql_ins = "INSERT INTO a_photo (a_menu_id, row_id, img) 
                                VALUES ('25', ?, ?)";
        $res_ins = _DB($sql_ins, [$_SESSION['market']['i_contr_id'], $newName]);
        if ($res_ins === false){ die('SQL error'); }

        $resp = [
            'status_' => 'ok',
            'url'    => $relPath,
            'name'   => $newName,
            'mime'   => $mimeByImage,
            'size'   => (int)$f['size'],
            'w'      => (int)$imgInfo[0],
            'h'      => (int)$imgInfo[1],
        ];

    } catch (Throwable $e) {
        $resp = ['status_'=>'error','message'=>$e->getMessage()];
    }
    $data_=$resp;

}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Открыть форму смены пароля
if ($_t=='market_password_form'){
    if (!isset($_SESSION['market']['i_contr_id']) || (int)$_SESSION['market']['i_contr_id']<=0){
        $data_ = market_auth_form_create('step1');
        $data_['status_'] = 'no_auth';
    }else{
        $data_ = market_password_form_create();
        $data_['status_'] = 'ok';
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Смена пароля (только новый пароль)
if ($_t=='market_password_change'){

    if (!isset($_SESSION['market']['i_contr_id']) || (int)$_SESSION['market']['i_contr_id']<=0){
        $data_ = array('status_'=>'error','message'=>'Не найден пользователь');
    }else{

        $password = trim((string)_GP('passNew'));

        if ($password===''){
            $data_ = array('status_'=>'error','message'=>'Новый пароль не может быть пустым');
        }
        else{
            $i_contr_id = (int)$_SESSION['market']['i_contr_id'];
            $sql = "SELECT i_contr.email
                    FROM i_contr
                         WHERE i_contr.id = ?";
            $res = _DB($sql, [$i_contr_id]);
            if ($res === false){ die('SQL error'); }
            $row = $res->fetch(PDO::FETCH_NUM);
            $email = $row[0];
        
            $password_md5  = md5($password);
            $new_hash = md5($password_md5.$_SESSION['a_options']['secret_key'].$password_md5);

            // Обновляем пароль
            $sql_upp = "UPDATE i_contr 
                        SET password = ?,
                            data_change = NOW()
                        WHERE id = ?";
            $res_upp = _DB($sql_upp, [$new_hash, $i_contr_id]);
            if ($res_upp === false){ die('SQL error'); }
            
            
            /**
            * Прочая логика смены пароля.
            * $_t='market_password_change'
            * 
            * $i_contr_id
            * $email
            * $password
            * $password_md5
            */
            $_market_file = __DIR__ . '/../_include/_market.php';
            if (is_file($_market_file)) {
                require $_market_file;
            }
            
            
            //ОПОВЕЩАЕМ ПОЛЬЗОВАТЕЛЯ  о смене пароля на email
            $message=$_SESSION['a_options']['Market: отправка оповещения о смене пароля на email'];
            $message=str_replace('@@email@@',$email,$message);
            $message=str_replace('@@password@@',$password,$message);
            $message=str_replace('@@link@@',$protacol.$_SERVER['SERVER_NAME'].'/?com=market&profile',$message);
            
            if (!send_mail_smtp(
                $email,
                'Пароль изменен на сайте '.$_SERVER['SERVER_NAME'],
                $message
            )){
                return 'Ошибка отправки письма через SMTP';exit;
            }
            
            
            $data_ = array('status_'=>'ok');
        }
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Изменение статуса оповещений
if ($_t=='market_change_notification'){
    if (!isset($_SESSION['market']['i_contr_id']) || (int)$_SESSION['market']['i_contr_id'] <= 0){
        $data_ = array('status_'=>'error','message'=>'Не найден пользователь');
    } else {
        $val = (int)_GP('val');
        if ($val !== 1) { $val = 0; } // Строгая типизация
        
        $i_contr_id = (int)$_SESSION['market']['i_contr_id'];
        
        $sql_upp = "UPDATE i_contr 
                    SET chk_notification = ?,
                        data_change = NOW()
                    WHERE id = ?";
        $res_upp = _DB($sql_upp, [$val, $i_contr_id]);
        if ($res_upp === false){ die('SQL error'); }
        
        $data_ = array('status_'=>'ok');
    }
}
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Добавление в корзину
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

if ($_t == 'market_add_to_cart') {
    $s_cat_id = (int)_GP('id');
    $kolvo = (float)_GP('kolvo', 1);

    if ($kolvo <= 0) {
        $data_ = array(
            'status_' => 'error',
            'message' => 'Количество должно быть больше нуля'
        );
    } else {
        $item_info = market_get_cart_item_info($s_cat_id);

        if ($item_info['status_'] !== 'ok') {
            $data_ = $item_info;
        } else {
            if (!isset($_SESSION['market']['items'])) {
                $_SESSION['market']['items'] = array();
            }

            $current_kolvo = isset($_SESSION['market']['items'][$s_cat_id]) ? (float)$_SESSION['market']['items'][$s_cat_id] : 0;
            $new_kolvo = $current_kolvo + $kolvo;

            // Для услуг остаток не проверяем
            if (!empty($item_info['is_service'])) {
                $_SESSION['market']['items'][$s_cat_id] = $new_kolvo;
                $data_['status_'] = 'ok';
            } else {
                $stock_kolvo = (float)$item_info['stock_kolvo'];

                if ($stock_kolvo <= 0) {
                    $data_ = array(
                        'status_' => 'error',
                        'message' => 'Товар закончился'
                    );
                } elseif ($new_kolvo > $stock_kolvo) {
                    $data_ = array(
                        'status_' => 'error',
                        'message' => 'Недостаточно товара на складе. Доступно: '.$stock_kolvo
                    );
                } else {
                    $_SESSION['market']['items'][$s_cat_id] = $new_kolvo;
                    $data_['status_'] = 'ok';
                }
            }
        }
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Обновление количества в корзине
if ($_t == 'market_update_cart_item') {
    $s_cat_id = (int)_GP('s_cat_id');
    $kolvo = (float)_GP('kolvo');

    if ($kolvo <= 0) {
        unset($_SESSION['market']['items'][$s_cat_id]);
        $data_['status_'] = 'ok';
    } else {
        $item_info = market_get_cart_item_info($s_cat_id);

        if ($item_info['status_'] !== 'ok') {
            unset($_SESSION['market']['items'][$s_cat_id]);
            $data_ = $item_info;
        } else {
            // Для услуг остаток не проверяем
            if (!empty($item_info['is_service'])) {
                $_SESSION['market']['items'][$s_cat_id] = $kolvo;
                $data_['status_'] = 'ok';
            } else {
                $stock_kolvo = (float)$item_info['stock_kolvo'];

                if ($stock_kolvo <= 0) {
                    unset($_SESSION['market']['items'][$s_cat_id]);
                    $data_ = array(
                        'status_' => 'error',
                        'message' => 'Товар закончился'
                    );
                } elseif ($kolvo > $stock_kolvo) {
                    $_SESSION['market']['items'][$s_cat_id] = $stock_kolvo;
                    $data_ = array(
                        'status_' => 'error',
                        'message' => 'Недостаточно товара на складе. В корзине оставлено максимально доступное количество: '.$stock_kolvo
                    );
                } else {
                    $_SESSION['market']['items'][$s_cat_id] = $kolvo;
                    $data_['status_'] = 'ok';
                }
            }
        }
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Оформление заказа
if ($_t == 'market_order_create') {
    if (!isset($_SESSION['market']['i_contr_id'])) {
        $data_ = array('status_' => 'error', 'message' => 'Необходимо авторизоваться для оформления заказа');
    } elseif (empty($_SESSION['market']['items'])) {
        $data_ = array('status_' => 'error', 'message' => 'Ваша корзина пуста');
    } else {
        $i_contr_id = (int)$_SESSION['market']['i_contr_id'];
        $comment = clean_text_field(_GP('comment'));
        $fio = clean_person_name(_GP('fio'));
        $phone = clean_phone(_GP('phone'));
        $city = clean_person_name(_GP('city'));
        $address = clean_text_field(_GP('address'));

        $total_sum = 0;
        $items_mail_html = "<ul style='padding-left:20px; font-size:15px;'>";
        $items_to_save = array();
        $order_error = '';

        // 1. Сначала проверяем все товары в корзине
        foreach ($_SESSION['market']['items'] as $s_cat_id => $kolvo) {
            $s_cat_id = (int)$s_cat_id;
            $kolvo = (float)$kolvo;

            if ($kolvo <= 0) {
                continue;
            }

            $item_info = market_get_cart_item_info($s_cat_id);

            if ($item_info['status_'] !== 'ok') {
                $order_error = $item_info['message'];
                break;
            }
            
            $row_item = $item_info['row'];
            $stock_kolvo = (float)$item_info['stock_kolvo'];
            $is_service = !empty($item_info['is_service']);
            $price = isset($row_item['price']) ? (float)$row_item['price'] : 0;
            $name = isset($row_item['name']) ? $row_item['name'] : '';
            
            if (!$is_service) {
                if ($stock_kolvo <= 0) {
                    $order_error = 'Товар "'.$name.'" закончился';
                    break;
                }
            
                if ($kolvo > $stock_kolvo) {
                    $order_error = 'Недостаточно товара "'.$name.'" на складе. Доступно: '.$stock_kolvo;
                    break;
                }
            }
            
            $items_to_save[] = array(
                's_cat_id' => $s_cat_id,
                'kolvo' => $kolvo,
                'price' => $price,
                'name' => $name
            );
            
            $total_sum += ($price * $kolvo);
            $items_mail_html .= "<li><b>".htmlspecialchars($name, ENT_QUOTES, 'UTF-8')."</b> — {$kolvo} шт. х {$price} ₽</li>";
        }

        $items_mail_html .= "</ul>";

        if ($order_error != '') {
            $data_ = array(
                'status_' => 'error',
                'message' => $order_error
            );
        } elseif (empty($items_to_save)) {
            $data_ = array(
                'status_' => 'error',
                'message' => 'Ваша корзина пуста'
            );
        } else {
            // 2. Создаем сам заказ
            $sql_z = "INSERT INTO m_zakaz (i_contr_id, status, data, data_create, comments)
                      VALUES (?, 'В обработке', NOW(), NOW(), ?)";
            $res_z = _DB($sql_z, array($i_contr_id, $comment));
            if ($res_z === false){ die('SQL error 1'); }
            
            $m_zakaz_id = (int)$db->lastInsertId();
            
            $project_name = 'Заказ №'.$m_zakaz_id.' от '.date('d.m.Y H:i');
            
            $sql_project = "UPDATE m_zakaz
                            SET project_name = ?
                            WHERE id = ?";
            $res_project = _DB($sql_project, array($project_name, $m_zakaz_id));
            if ($res_project === false){ die('SQL error 2'); }

            // 3. Переносим товары из массива в БД
            $sid=0;
            foreach ($items_to_save as $item_) {
                $sql_link = "INSERT INTO m_zakaz_s_cat (sid, m_zakaz_id, s_cat_id, kolvo, price)
                             VALUES (?, ?, ?, ?, ?)";
                $res_link = _DB($sql_link, array(
                    $sid,
                    $m_zakaz_id,
                    (int)$item_['s_cat_id'],
                    (float)$item_['kolvo'],
                    (float)$item_['price']
                ));
                if ($res_link === false){ die('SQL error 3'); }
                $sid++;
            }

            // 4. Заполняем доставку
            if (isset($_SESSION['a_options']['Market: доставка']) && $_SESSION['a_options']['Market: доставка'] == '1') {
                $full_address = trim($city . ', ' . $address, ', ');

                $sql_d = "INSERT INTO m_dostavka (m_zakaz_id, fio, phone, adress, data)
                          VALUES (?, ?, ?, ?, NOW())";
                $res_d = _DB($sql_d, array($m_zakaz_id, $fio, $phone, $full_address));
                if ($res_d === false){ die('SQL error 4'); }
            }

            // 5. Очищаем корзину
            $_SESSION['market']['items'] = array();

            // 6. Отправляем письма
            $admin_email = isset($_SESSION['a_options']['email администратора']) ? $_SESSION['a_options']['email администратора'] : '';
            $client_email = isset($_SESSION['market']['email']) ? $_SESSION['market']['email'] : '';
            $domain = $_SERVER['SERVER_NAME'];

            $mail_subj = "Новый заказ №{$m_zakaz_id} на сайте {$domain}";
            $mail_body = "<h2>Здравствуйте! Заказ №{$m_zakaz_id} успешно оформлен.</h2>
                          <p><b>Сумма к оплате:</b> {$total_sum} ₽</p>
                          <h3>Состав заказа:</h3>
                          {$items_mail_html}
                          <hr>";

            if ($comment != '') {
                $mail_body .= "<p><b>Комментарий:</b><br/>".nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'))."</p>";
            }

            // Письмо клиенту
            if ($client_email != '') {
                send_mail_smtp($client_email, $mail_subj, $mail_body, 'noreply@'.$domain, 'Сайт '.$domain, $mail_subj, 1);
            }

            // Письмо администратору
            if ($admin_email != '') {
                send_mail_smtp($admin_email, "АДМИН: ".$mail_subj, $mail_body, 'noreply@'.$domain, 'Сайт '.$domain, $mail_subj, 1);
            }

            $data_['order_id'] = $m_zakaz_id;
            $data_['redirect_tab'] = 'orders';
            $data_['auto_pay'] = 1;
            $data_['status_'] = 'ok';
        }
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
echo json_encode($data_);exit;
?>
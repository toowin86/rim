/*
    *shablon/js/market.js
    * toowin86 2026-01-12 v2
*/
if (typeof $.fn.serializeObject !== 'function'){
    $.fn.serializeObject = function(){
        let result = {};
        let arr = this.serializeArray();

        $.each(arr, function(){
            let name = this.name || '';
            let value = this.value || '';

            if (name === ''){
                return;
            }

            if (typeof result[name] !== 'undefined'){
                if (!Array.isArray(result[name])){
                    result[name] = [result[name]];
                }
                result[name].push(value);
            }else{
                result[name] = value;
            }
        });

        return result;
    };
}

//проверка на json
function is_json(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}
// маска телефона без внешних плагинов
if (typeof window.setPhoneMask !== 'function'){
    window.setPhoneMask = function($fields){
        $fields = $($fields || []);
        if (!$fields.length){
            return;
        }

        function onlyDigits(val){
            return (val || '').replace(/\D/g, '');
        }

        function normalizeDigits(digits){
            digits = digits || '';

            if (digits.length > 0 && digits.charAt(0) === '8'){
                digits = '7' + digits.substring(1);
            }

            return digits;
        }

        function detectFormat(digits){
            digits = normalizeDigits(digits);

            if (digits.length > 0 && digits.charAt(0) === '3'){
                return 'ua';
            }
            if (digits.length > 0 && digits.charAt(0) === '7'){
                return 'ru';
            }
            return 'any';
        }

        function formatPhone(val){
            var digits = onlyDigits(val);
            digits = normalizeDigits(digits);

            var type = detectFormat(digits);

            if (type === 'ru'){
                digits = digits.substring(0, 11);

                var p1 = digits.substring(1, 4);
                var p2 = digits.substring(4, 7);
                var p3 = digits.substring(7, 9);
                var p4 = digits.substring(9, 11);

                var out = '+7';
                if (p1) out += '(' + p1;
                if (p1.length === 3) out += ')';
                if (p2) out += p2;
                if (p3) out += '-' + p3;
                if (p4) out += '-' + p4;

                return out;
            }

            if (type === 'ua'){
                digits = digits.substring(0, 12);

                var c1 = digits.substring(0, 3);
                var c2 = digits.substring(3, 5);
                var c3 = digits.substring(5, 8);
                var c4 = digits.substring(8, 10);
                var c5 = digits.substring(10, 12);

                var out2 = '+' + c1;
                if (c2) out2 += '(' + c2;
                if (c2.length === 2) out2 += ')';
                if (c3) out2 += c3;
                if (c4) out2 += '-' + c4;
                if (c5) out2 += '-' + c5;

                return out2;
            }

            digits = digits.substring(0, 12);
            return digits ? '+' + digits : '';
        }

        $fields.each(function(){
            var $field = $(this);

            if ($field.data('phone-mask-init') === '1'){
                return;
            }

            $field.data('phone-mask-init', '1');

            $field.on('input.marketPhoneMask blur.marketPhoneMask paste.marketPhoneMask', function(){
                var start = this.selectionStart || 0;
                var oldLen = ($(this).val() || '').length;
                var newVal = formatPhone($(this).val() || '');

                $(this).val(newVal);

                try{
                    var newLen = newVal.length;
                    var delta = newLen - oldLen;
                    this.setSelectionRange(start + delta, start + delta);
                }catch(e){}
            });

            $field.trigger('input');
        });
    };
}

//Helper для ajax
function market_ajax_post(_t, data, opts){
    data = data || {};
    opts = opts || {};

    let payload = $.extend({}, data, {
        com: 'ajax',
        ajax: 'market',
        _t: _t
    });

    let use_loading = (typeof opts.loading === 'undefined') ? true : !!opts.loading;

    if (use_loading){
        loading_market(1);
    }

    $.ajax({
        type: 'POST',
        url: $('base').attr('href'),
        dataType: 'text',
        data: payload,
        success: function(response, textStatus){
            if (use_loading){
                loading_market(0);
            }

            if (is_json(response) === true){
                let data_n = JSON.parse(response);

                if (typeof opts.onSuccess === 'function'){
                    opts.onSuccess(data_n, payload, response, textStatus);
                }
            } else {
                if (typeof opts.onInvalidJson === 'function'){
                    opts.onInvalidJson(response, payload, textStatus);
                } else {
                    alert_market(response, 'Ошибка', 'error');
                }
            }
        },
        error: function(xhr, textStatus, errorThrown){
            if (use_loading){
                loading_market(0);
            }

            if (typeof opts.onError === 'function'){
                opts.onError(xhr, textStatus, errorThrown, payload);
            } else {
                alert_market('Ошибка сети/сервера', 'Ошибка', 'error');
            }
        }
    });
}

var jqxhr;
//Загрузка
function loading_market(v, root){
    var $root = root ? $(root).first() : $();
    
    if (!$root.length){
        $root = $('.alert_m.alert_m_auth:last .alert_m_body').last();
    }
    
    if (v==1){
        loading_market(0);
        
        var html_ = '<div class="loadind_div_bg'+($root.length ? ' loadind_div_bg_in' : '')+'">'
                   +    '<div class="loadind_div_bg_div">'
                   +        '<div class="group">'
                   +            '<div class="bigSqr">'
                   +                '<div class="square first"></div>'
                   +                '<div class="square second"></div>'
                   +                '<div class="square third"></div>'
                   +                '<div class="square fourth"></div>'
                   +            '</div>'
                   +            '<div class="text">Загрузка</div>'
                   +        '</div>'
                   +    '</div>'
                   +'</div>';
        
        if ($root.length){
            $root.addClass('loadind_div_host');
            $root.append(html_);
        }
        else{
            $('body').append(html_);
        }
    }
    else{
        $('.loadind_div_bg').remove();
        $('.loadind_div_host').removeClass('loadind_div_host');
    }
}
//////////////////////////////////////////////////////////////////////////////////////////////////
// окно: товар добавлен в корзину
function market_added_to_cart_alert(){

    var html_ = ''
        +'<div class="market_open market_open_success" style="display:block;">'
            +'<div class="market_open__modal">'
                +'<p class="market_open__subtitle">Товар успешно добавлен в корзину.</p>'
                +'<div class="market_open__btn_div">'
                    +'<button type="button" class="market_open__btn market_open__btn-primary js-market-go-cart">В корзину</button>'
                    +'<button type="button" class="market_open__btn market_open__btn-secondary js-market-continue-shopping">Продолжить покупки</button>'
                +'</div>'
            +'</div>'
        +'</div>';

    alert_market(html_, 'Готово', 'auth', 0, '', '', true);
}

///////////////////////////////////////////////////////////////////////////////////////
// ДОСТАВКА ///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////
var jqxhr1=false;

function update_dostavka(){
    
    var $city=$('.city_div input[name="city"]');
    
    if ($city.length<1){
        return false;
    }
    
    if (typeof $.fn.autocomplete!='function'){
        console.error('autocomplete не подключен');
        return false;
    }
    
    $city.each(function(){
        var $inp=$(this);
        
        try{
            if ($inp.data('ui-autocomplete')){
                $inp.autocomplete('destroy');
            }
        }catch(e){}
        
        $inp.autocomplete({
            minLength:0,
            appendTo:'.market_open',
            position:{
                my:'left top',
                at:'left bottom',
                collision:'none'
            },
            source:function(request,response){
                request['_t']='city_autocomplete';
                request['com']='ajax';
                request['ajax']='market';
                
                if (jqxhr1){
                    jqxhr1.abort();
                }
                
                jqxhr1=$.ajax({
                    type:'POST',
                    url:$('base').attr('href'),
                    dataType:'text',
                    data:request,
                    success:function(data,textStatus){
                        $('.r_neispravnosti').removeClass('ui-autocomplete-loading');
                        
                        if (is_json(data)==true){
                            var data_n=JSON.parse(data);
                            
                            response($.map(data_n.items,function(item){
                                return {
                                    label:item.name,
                                    value:item.label,
                                    id:item.id,
                                    name:item.name,
                                    term:request['term']
                                };
                            }));
                            
                            $('.ui-autocomplete:visible').css({'z-index':'1000'});
                            $('.ui-autocomplete:visible li').css({'border-bottom':'1px dotted #900'});
                            $('.ui-autocomplete:visible li:first-child').css({'border-bottom':'1px solid #333','color':'#900','font-weight':'bold'});
                        }
                        else{
                            alert_market(data,'Ошибка','error','none');
                        }
                    }
                });
            },
            select:function(event,ui){
                
            },
            close:function(event,ui){
                market_clear_icons_toggle($(this).closest('.market_open'));
            }
        });
    });
    
    $(document).off('focus.market_city','.m_shipping input[name="city"]');
    $(document).on('focus.market_city','.m_shipping input[name="city"]',function(){
        var $inp=$(this);
        
        if (typeof $.fn.autocomplete!='function'){
            return false;
        }
        
        if (!$inp.data('ui-autocomplete')){
            return false;
        }
        
        $inp.autocomplete('search',$inp.val());
    });
    
    return true;
}


//крестик для очистки окна ввода
function market_clear_icons_init(root){
    var $root = root ? $(root) : $('.market_open');
    if (!$root.length) return;

    $root.find('input').each(function(){
        var $inp = $(this);
        if ($inp.prop('disabled')==true || $inp.closest('.m_inpwrap').length) return;

        // для password крестик не рисуем, там будет глазик
        if ($inp.is('[type="password"]')){
            return;
        }

        if ($inp.is('[type="text"]:visible,[type="phone"]:visible')){
            $inp.wrap('<div class="m_inpwrap"></div>');
            $inp.after('<span class="m_inpclear" title="Очистить" aria-label="Очистить">×</span>');
        }
    });

    market_clear_icons_toggle($root);
}
//крестик для очистки окна ввода
function market_clear_icons_toggle(root){
    var $root = root ? $(root) : $('.market_open');
    $root.find('.m_inpwrap').each(function(){
        var $w = $(this);
        var $i = $w.find('input').first();
        var $c = $w.find('.m_inpclear').first();
        if (!$i.length || !$c.length) return;
        $c.toggleClass('m_inpclear_show', ($i.val() || '').length > 0);
    });
}


// Функция для получения параметра из URL
function getUrlParameter(name) {
    let params = new URLSearchParams(window.location.search);

    if (!params.has(name)) {
        return null;
    }

    return params.get(name);
}

function alert_market(text_,title_,style_,time_,function_after_open,function_after_close,over)
{
    // Проверка подключенных библиотек
    if (typeof $ === 'undefined') { console.error('Ошибка: jQuery не подключен.');return; } // jQuery
    if (typeof $.arcticmodal === 'undefined') {console.error('Ошибка: ArcticModal не подключен.');return; }// ArcticModal
    if (typeof window.History === 'undefined' || typeof History.pushState !== 'function') {console.error('Ошибка: jquery.history.js (History.js) не подключен.'); return;}
    
    title_ = title_ || '';
    style_ = style_ || 'ok';
    time_ = time_ || 0;
    function_after_open=function_after_open || '';
    function_after_close = function_after_close || "";
    over = over || (style_ === 'auth' ? false : true);

    let icon_='';
    if (style_=='ok'){icon_='fa fa-check';}
    if (style_=='error'){icon_='fa fa-warning';}
    if (style_=='info'){icon_='fa fa-info-circle';}
    if (style_=='none'){icon_='';}
    
    if (icon_!=''){icon_='<div class="alert_m_header_icon"><i class="'+icon_+'"></i></div>';}
   
    $.arcticmodal({
        closeOnOverlayClick: over,
        content:    '<div class="alert_m alert_m_'+style_+'">'
                        +'<div class="alert_m_header">'
                            +icon_
                            +'<div class="alert_m_header_h1"><h1>'+title_+'</h1></div>'
                            +'<div class="alert_m_header_com"><div class="alert_m_close" title="Закрыть">X</div></div>'
                        +'</div>'
                        +'<div class="alert_m_body">'
                            +text_
                        +'</div>'
                    +'</div>',
        overlay: {css: {background: '#000',opacity: 0.7}},
        afterOpen: function(data, el) {
            if (typeof function_after_open == 'function'){function_after_open();}
        },
        afterClose: function(data, el) {
            if (typeof function_after_close == 'function'){function_after_close();}
    
        }    
    });
    if (time_>0)
    {
        setTimeout(function(){$('.alert_m_'+style_).arcticmodal('close');}, time_);
    }
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
//открываем форму корзины
function market_open(function_after){
    function_after=function_after || '';
    
    let html_market='<div class="market_open" style="display:none">'
      +'<div class="market_open__modal">'
        +'<p class="market_open__subtitle"></p>'
        +'<div class="market_open__form">'
          +'<div class="market_open__question"></div>'
          +'<div class="market_open__btn_div"></div>'
        +'</div>'
      +'</div></div>';
          
    alert_market(html_market,'Загрузка...','auth',0,'',function(){window.location.href = $('base').attr('href');});//
    if (typeof function_after=='function'){function_after();}
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
//обновление корзины
function market_update(title,info,question,btn){
    $('.alert_m_auth .alert_m_header_h1 h1').text(title);
    $('.alert_m_auth .market_open__subtitle').html(info);
    $('.alert_m_auth .market_open__question').html(question);
    $('.alert_m_auth .market_open__question input:first').focus();
    $('.alert_m_auth .market_open__btn_div').html(btn);
    
    if ($('.market_open__form input[name="phone"]:visible').length>0){
         // маска на телефон
         setPhoneMask($('input[name="phone"]'));
    }
    //обновление формы доставки
    if ($('input[name="city"]:visible').length>0){
        update_dostavka();
    }
    
    market_clear_icons_init($('.market_open'));
    market_tg_widget_init($('.market_open'));
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
//загружаем форму корзины - шаг 1
function market_load(market_page){
    market_page = market_page || 'cart';

    market_ajax_post('market_load', {
        market_page: market_page
    }, {
        onSuccess: function(data_n){
            market_update(data_n.t, data_n.i, data_n.q, data_n.b);
            $('.market_open').css({display: 'block'});
        }
    });
}
//////////////////////////////////////////////////////////////////////////////////////////////////////
//выходим
function market_logout(function_after){
    function_after = function_after || '';

    market_ajax_post('market_logout', {}, {
        onSuccess: function(data_n){
            if (typeof function_after === 'function'){
                function_after();
            }
        }
    });
}

//////////////////////////////////////////////////////////////////////////////////////////////////////
//// Шаг 2 - проверка email
function market_chk_email(email){
    market_ajax_post('market_chk_email', {
        email: email
    }, {
        onSuccess: function(data_n){
            if (data_n.status_ == 'email_no_active'){
                alert_market('Данный email заблокирован!', 'Ошибка', 'error', 1500, '', '', true);
            }
            else if (data_n.status_ == 'many_email'){
                alert_market('Данный email не доступен!', 'Ошибка', 'error', 1500, '', '', true);
            }
            else if (data_n.status_ == 'ok' || data_n.status_ == 'no_email'){
                market_update(data_n.t, data_n.i, data_n.q, data_n.b);
            }
        }
    });
}
//////////////////////////////////////////////////////////////////////////////////////////////////////
//ШАГ 3 - регистрация: проверка кода
function market_chk_code(email, code){
    market_ajax_post('market_chk_code', {
        email: email,
        code: code
    }, {
        onSuccess: function(data_n){
            if (data_n.status_ == 'ok'){
                market_update(data_n.t, data_n.i, data_n.q, data_n.b);
            }
            else if (data_n.status_ == 'no correct code'){
                alert_market('Не верный код', 'Ошибка', 'error', 2000);
                market_update(data_n.t, data_n.i, data_n.q, data_n.b);
            }
            else{
                alert_market('Не верный email', 'Ошибка', 'error', 2000);
                market_logout(function(){
                    market_load();
                });
            }
        }
    });
}
function market_active_form(){
    var $form = $('.market_page_form:visible:last');

    if (!$form.length){
        $form = $('.market_page_form:last');
    }

    return $form;
}

function market_tg_apply_state(data_n, root){
    var $root = root ? $(root) : $('.market_open:visible').last();
    if (!$root.length){
        $root = $('.market_open').last();
    }

    var $status = $root.find('.js-market-tg-status').first();
    var $input = $root.find('input[name="telegram_id"]').first();

    if ($status.length){
        $status.text(data_n.status_text || 'Telegram не привязан');
    }

    if ($input.length){
        $input.val(data_n.telegram_id || '');
    }
}

function market_tg_render_widget($holder){
    if (!$holder.length){
        return;
    }

    var bot = String($holder.attr('data-bot') || '').trim();
    var ready = String($holder.attr('data-ready') || '0');

    $holder.empty();

    if (ready !== '1' || bot === ''){
        return;
    }

    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://telegram.org/js/telegram-widget.js?22';
    script.setAttribute('data-telegram-login', bot);
    script.setAttribute('data-size', 'large');
    script.setAttribute('data-userpic', 'false');
    script.setAttribute('data-request-access', 'write');
    script.setAttribute('data-onauth', 'market_telegram_auth_cb(user)');

    $holder.get(0).appendChild(script);
}

function market_tg_status_load(root){
    var $root = root ? $(root) : $('.market_open:visible').last();

    market_ajax_post('market_tg_auth_status', {}, {
        loading: false,
        onSuccess: function(data_n){
            market_tg_apply_state(data_n, $root);
        }
    });
}

function market_tg_widget_init(root){
    var $root = root ? $(root) : $('.market_open:visible').last();
    if (!$root.length){
        $root = $('.market_open').last();
    }

    var $holder = $root.find('.js-market-tg-auth').first();
    if (!$holder.length){
        return;
    }

    market_tg_render_widget($holder);
    market_tg_status_load($root);
}

function market_telegram_auth_cb(user){
    user = user || {};

    market_ajax_post('market_tg_auth_save', user, {
        onSuccess: function(data_n){
            if (data_n.status_ === 'ok'){
                market_tg_apply_state(data_n, $('.market_open:visible').last());
                alert_market(data_n.status_text || 'Telegram привязан', 'Успешно', 'ok', 1500);
            }
        }
    });
}
//////////////////////////////////////////////////////////////////////////////////////////////////////
////ШАГ 4 - регистрация: создание пользователя / восстановление пароля
function market_create_update_user(){
    let err_text = '';
    let $form = market_active_form();
    let data_ = $form.serializeObject();

    $form.find('input:visible').each(function(){
        if (typeof $(this).attr('required') != 'undefined'){
            if (data_[$(this).attr('name')] == ''){
                err_text += '<p>Не заполнено поле <strong>' + $(this).attr('placeholder') + '</strong></p>';
            }
        }
    });

    $form.find('[data-market-required="1"]').each(function(){
        let name = $(this).attr('name') || '';
        let title = $(this).attr('data-market-title') || name;

        if (name !== '' && (!data_[name] || data_[name] === '0')){
            err_text += '<p>Не заполнено поле <strong>' + title + '</strong></p>';
        }
    });

    if (err_text != ''){
        alert_market(err_text, 'Ошибка', 'error');
        return;
    }

    market_ajax_post('market_create_update_user', data_, {
        onSuccess: function(data_n){
            if (data_n.status_ == 'ok'){
                alert_market('Авторизация <strong>' + data_['email'] + '</strong> выполнена!', 'Успешно', 'ok', 2000);
            }
            market_update(data_n.t, data_n.i, data_n.q, data_n.b);
        }
    });
}


// ШАГ 5 - авторизация по логину и хэшу
function market_login(email, hash){
    market_ajax_post('market_login', {
        email: email,
        hash: hash
    }, {
        onSuccess: function(data_n){
            if (data_n.status_ == 'ok'){
                alert_market(
                    'Пользователь <strong>' + email + '</strong> успешно авторизован',
                    'Успешно',
                    'ok',
                    2000,
                    '',
                    function(){ window.location.href = $('base').attr('href'); }
                );
            }
            else if (data_n.status_ == 'no correct email or password'){
                alert_market('Не верный email или пароль!', 'Ошибка', 'error', 2000);
            }
            else if (data_n.status_ == 'user not active'){
                alert_market('Пользователь <strong>' + email + '</strong> отключен', 'Ошибка', 'error', 2000);
            }
            else{
                alert_market('Ошибка авторизации', 'Ошибка', 'error', 2000);
            }
        }
    });
}
//////////////////////////////////////////////////////////////////////////////////////////////////
// ШАГ 6 - авторизация: восстановление пароля - запрос кода
function market_recovery_password(email){
    market_ajax_post('market_recovery_password', {
        email: email
    }, {
        onSuccess: function(data_n){
            market_update(data_n.t, data_n.i, data_n.q, data_n.b);
        }
    });
}

//////////////////////////////////////////////////////////////////////////////////////////////////
// ШАГ 7 - авторизация: отправка пароля
function market_chk_password(email, password){
    market_ajax_post('market_chk_password', {
        email: email,
        password: password
    }, {
        onSuccess: function(data_n){
            if (data_n.status_ == 'ok'){
                market_update(data_n.t, data_n.i, data_n.q, data_n.b);
            }
            else if (data_n.status_ == 'no correct email or password'){
                alert_market('Не верный email или пароль!', 'Ошибка', 'error', 2000);
            }
            else{
                market_logout(function(){
                    market_load();
                });
            }
        }
    });
}

//////////////////////////////////////////////////////////////////////////////////////////////////
// Вкладки корзины после авторизации
function market_tab(tab, callback){
    tab = tab || 'cart';
    callback = (typeof callback === 'function') ? callback : null;

    market_ajax_post('market_tab', {
        tab: tab
    }, {
        onSuccess: function(data_n){
            market_update(data_n.t, data_n.i, data_n.q, data_n.b);

            if (callback){
                callback(data_n);
            }
        }
    });
}
//////////////////////////////////////////////////////////////////////////////////////////////////
// Сохранение настроек профиля
function market_profile_save(form){
    let err_text = '';
    let data_ = form.serializeObject();

    form.find('input:visible').each(function(){
        if (typeof $(this).attr('required') != 'undefined'){
            if (data_[$(this).attr('name')] == ''){
                err_text += '<p>Не заполнено поле <strong>' + $(this).attr('placeholder') + '</strong></p>';
            }
        }
    });

    if (err_text != ''){
        alert_market(err_text, 'Ошибка', 'error');
        return;
    }

    market_ajax_post('market_profile_save', data_, {
        onSuccess: function(data_n){
            if (data_n.status_ == 'ok'){
                alert_market('Данные профиля сохранены!', 'Успешно', 'ok', 2000);
            }
        }
    });
}

function market_profile_tg_render_widget($holder){
    if (!$holder.length){
        return;
    }

    var bot = String($holder.attr('data-bot') || '').trim();
    if (bot === ''){
        return;
    }

    $holder.empty();

    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://telegram.org/js/telegram-widget.js?22';
    script.setAttribute('data-telegram-login', bot);
    script.setAttribute('data-size', 'large');
    script.setAttribute('data-userpic', 'false');
    script.setAttribute('data-request-access', 'write');
    script.setAttribute('data-onauth', 'market_profile_telegram_auth_cb(user)');

    $holder.get(0).appendChild(script);
}

function market_profile_telegram_auth_cb(user){
    user = user || {};

    market_ajax_post('market_profile_bind_telegram', user, {
        onSuccess: function(data_n){
            if (data_n.status_ == 'ok'){
                market_update(data_n.t, data_n.i, data_n.q, data_n.b);
                alert_market(data_n.message || 'Telegram успешно привязан', 'Успешно', 'ok', 2000);
            }else{
                alert_market(data_n.message || 'Ошибка привязки Telegram', 'Ошибка', 'error');
            }
        }
    });
}


//отвязка телеграм
function market_profile_unlink_telegram(){
    market_ajax_post('market_profile_unlink_telegram', {}, {
        onSuccess: function(data_n){
            if (data_n.status_ == 'ok'){
                market_update(data_n.t, data_n.i, data_n.q, data_n.b);
                alert_market(data_n.message || 'Telegram отвязан', 'Успешно', 'ok', 2000);
            }else{
                alert_market(data_n.message || 'Ошибка отвязки Telegram', 'Ошибка', 'error');
            }
        }
    });
}
// загрузка фото
function market_load_photo(){
    let err_text='';
    let data_=new Object();
    data_['com']='ajax';
    data_['_t']='market_load_photo';
    data_['ajax']='market';

    let fileInput = document.getElementById('market_photo_input');
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
        err_text='Файл не выбран';
    }

    // быстрая клиентская проверка расширения
    if (err_text==='') {
        let f = fileInput.files[0];
        let name = (f.name || '').toLowerCase();
        if (!(/\.(jpg|jpeg|png|webp)$/i.test(name))) {
            err_text='Допустимы только JPG, PNG, WEBP';
        }
    }

    if (err_text!=''){
        alert_market(err_text,'Ошибка','error');
    }else{
        loading_market(1);

        let fd = new FormData();

        // ВАЖНО:
        // раньше com был только в data_, но не попадал в FormData
        // из-за этого backend мог не попасть в ajax-маршрут
        fd.append('com', data_['com']);
        fd.append('_t', data_['_t']);
        fd.append('ajax', data_['ajax']);
        fd.append('photo', fileInput.files[0]);

        $.ajax({
            "type": "POST",
            "url": $('base').attr('href'),
            "dataType": "text",
            "data": fd,
            "processData": false,
            "contentType": false,
            "cache": false,
            "success": function(data,textStatus){
                loading_market(0);

                if (is_json(data)==true){
                    let data_n = JSON.parse(data);

                    if (data_n.status_ === 'ok') {
                        $('.p_avatar_img').attr('src', data_n.url + '?t=' + Date.now());
                        alert_market('Фотография загружена!','Успешно','ok',2000);
                    } else {
                        alert_market((data_n.message ? data_n.message : 'Ошибка загрузки'),'Ошибка','error');
                    }
                }
                else{
                    alert_market(data,'Ошибка','error');
                }
            },
            "error": function(xhr){
                loading_market(0);
                alert_market('Ошибка сети/сервера при загрузке','Ошибка','error');
            }
        });
    }
}

//////////////////////////////////////////////////////////////////////////////////////////////////
// открыть форму смены пароля
function market_password_form(){
    market_ajax_post('market_password_form', {}, {
        onSuccess: function(data_n){
            market_update(data_n.t, data_n.i, data_n.q, data_n.b);
        }
    });
}
//////////////////////////////////////////////////////////////////////////////////////////////////
// сменить пароль (только новый)
function market_password_change(){
    let form = market_active_form();
    let data_ = form.serializeObject();
    let p = (data_['passNew'] || '');

    if (p === ''){
        alert_market('Введите новый пароль', 'Ошибка', 'error', 1500, '', function(){ $('#iPassNew').focus(); }, true);
        return;
    }

    market_ajax_post('market_password_change', data_, {
        onSuccess: function(data_n){
            if (data_n.status_ === 'ok'){
                alert_market('Пароль успешно изменён!', 'Успешно', 'ok', 2000);
                market_tab('profile');
            } else {
                alert_market((data_n.message ? data_n.message : 'Ошибка смены пароля'), 'Ошибка', 'error');
            }
        }
    });
}

//////////////////////////////////////////////////////////////////////////////////////////////////
// добавление товара в корзину
function market_buy(item_id, kolvo){
    item_id = parseInt(item_id, 10) || 0;
    kolvo = parseInt(kolvo, 10) || 1;

    if (item_id <= 0){
        alert_market('Некорректный ID товара', 'Ошибка', 'error');
        return;
    }

    market_ajax_post('market_add_to_cart', {
        id: item_id,
        kolvo: kolvo
    }, {
        onSuccess: function(data_n){
            if (data_n.status_ === 'ok'){
                if (typeof data_n.t !== 'undefined' && typeof data_n.i !== 'undefined' && typeof data_n.q !== 'undefined' && typeof data_n.b !== 'undefined'){
                    market_update(data_n.t, data_n.i, data_n.q, data_n.b);
                }

                market_added_to_cart_alert();
            } else {
                alert_market(data_n.message || 'Ошибка', 'Ошибка', 'error');
            }
        },
        onInvalidJson: function(){
            alert_market('Сервер вернул некорректный ответ', 'Ошибка', 'error');
        },
        onError: function(){
            alert_market('Ошибка запроса к серверу', 'Ошибка', 'error');
        }
    });
}

//////////////////////////////////////////////////////////////////////////////////////////////////
// обработка публичных ссылок корзины вида ?com=market&...
function market_handle_public_url(href, clear_url_after){
    clear_url_after = clear_url_after || false;

    if (!href){
        return false;
    }

    let base_href = $('base').attr('href') || window.location.origin + '/';
    let urlObj = null;

    try{
        urlObj = new URL(href, base_href);
    }catch(e){
        return false;
    }

    if (urlObj.searchParams.get('com') !== 'market'){
        return false;
    }

    let action_handled = false;
    let buy_val = urlObj.searchParams.get('buy');
    let item_id = urlObj.searchParams.get('id');
    let kolvo = urlObj.searchParams.get('kolvo');
    let tab_ = '';

    kolvo = parseInt(kolvo, 10) || 1;

    if (buy_val !== null){
        if (item_id){
            market_buy(item_id, kolvo);
            action_handled = true;
        }
    }
    
    else if (urlObj.searchParams.has('cart')){
        tab_ = 'cart';
    }
    else if (urlObj.searchParams.has('orders')){
        tab_ = 'orders';
    }
    else if (urlObj.searchParams.has('profile')){
        tab_ = 'profile';
    }
    else if (urlObj.searchParams.has('logout')){
        market_logout(function(){
            alert_market('Сессия закрыта', 'Успешно', 'ok', 1500, '', function(){
                window.location.reload();
            });
        });
        action_handled = true;
    }
    else{
        let reserved_params = {
            com: 1,
            buy: 1,
            id: 1,
            kolvo: 1,
            logout: 1,
            market_login: 1,
            email: 1,
            hash: 1
        };

        urlObj.searchParams.forEach(function(value, key){
            if (tab_ !== ''){
                return;
            }

            if (typeof reserved_params[key] !== 'undefined'){
                return;
            }

            tab_ = key;
        });
    }

    if (tab_ !== ''){
        var $opened_market = $('.alert_m.alert_m_auth:visible .market_open:visible').first();

        if ($opened_market.length){
            market_tab(tab_);
        }
        else{
            market_open(function(){
                market_load(tab_);
            });
        }

        action_handled = true;
    }

    if (action_handled && clear_url_after === true){
        let cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
        window.history.replaceState({path: cleanUrl}, '', cleanUrl);
    }

    return action_handled;
}
//////////////////////////////////////////////////////////////////////////////////////////////////
// Отмена заказа
function market_order_cancel(orderId){
    orderId = parseInt(orderId, 10) || 0;

    if (orderId <= 0){
        alert_market('Некорректный номер заказа', 'Ошибка', 'error', 1800);
        return;
    }

    market_ajax_post('market_order_cancel', {
        order_id: orderId
    }, {
        onSuccess: function(data_n){
            if (data_n.status_ === 'ok'){
                market_update(data_n.t, data_n.i, data_n.q, data_n.b);
                alert_market(data_n.message || 'Заказ отменен', 'Готово', 'ok', 1800);
            }else{
                alert_market(data_n.message || 'Не удалось отменить заказ', 'Ошибка', 'error', 2200);
            }
        }
    });
}


//*******************************************************************************************************************
//*******************************************************************************************************************
//*******************************************************************************************************************
$(document).ready(function(){
    
    // Шаг 2 - проверка email
    $(document).delegate('.market_chk_email','click',function(e){
        e.preventDefault();
        let th_=$(this);
        let email=th_.closest('.market_open').find('form input[name="email"]').val();
        if (email==''){alert_market('Введите email','Ошибка','error',1500,'',function(){$('#iEmail').focus();},true);}
        else{
            market_chk_email(email);
        }
    });
    
    
    //ШАГ 3 - регистрация: проверка кода
    $(document).delegate('.market_chk_code','click',function(e){
        e.preventDefault();
        let th_=$(this);
        let code=th_.closest('.market_open').find('form input[name="code"]').val();
        let email=th_.closest('.market_open').find('form input[name="email"]').val();
        if (code==''){alert_market('Введите код','Ошибка','error',1500,'',function(){$('#iCode').focus();},true);}
        else{
            market_chk_code(email,code);
        }
    });
    
        
    //ШАГ 4 - регистрация: создание пользователя
    $(document).delegate('.market_create_update_user','click',function(e){
        e.preventDefault();
        market_create_update_user();
        
    });
    
    //отвязка телеграмм
    $(document).delegate('.market_tg_reset','click',function(e){
        e.preventDefault();
    
        market_ajax_post('market_tg_auth_reset', {}, {
            onSuccess: function(data_n){
                market_tg_apply_state(data_n, $('.market_open:visible').last());
                market_tg_widget_init($('.market_open:visible').last());
            }
        });
    });
        
    //ШАГ 6 - авторизация: восстановление пароля - запрос кода
    $(document).delegate('.market_recovery_password','click',function(e){
        e.preventDefault();
        let th_=$(this);
        let email=th_.closest('.market_open').find('form input[name="email"]').val();
        if (email==''){alert_market('Email не корректный','Ошибка','error',1500,'',function(){market_logout(function(){ market_load();});},true);}
        else{
            market_recovery_password(email);
        }
    });
    
    // ШАГ 7 - авторизация: отправка пароля
    $(document).delegate('.market_chk_password','click',function(e){
        e.preventDefault();
        let th_=$(this);
        let email=th_.closest('.market_open').find('form input[name="email"]').val();
        let password=th_.closest('.market_open').find('form input[name="password"]').val();
        if (email==''){alert_market('Email не корректный','Ошибка','error',1500,'',function(){market_logout(function(){ market_load();});},true);}
        else if (password==''){alert_market('Пароль не может быть пустым','Ошибка','error',1500,'',function(){th_.closest('.market_open').find('form input[name="password"]').focus();},true);}
        else{
            market_chk_password(email,password);
        }
    });
    
        
    //открыть всплывающее окно
    $(document).delegate('.market_open_btn','click',function(e){
        e.preventDefault();
        market_open(function(){market_load();});
    });
    //закрыть всплывающее окно
    $(document).delegate('.alert_m_close','click',function(){
        $(this).arcticmodal('close');
    });
    
    //отменяем отправку формы
    $(document).delegate('.market_page_form', 'submit', function (e) {
        e.preventDefault();
    });
    

    // Enter = следующий шаг (кнопка или поле)
    $(document).delegate('input', 'keyup', function (e) {
        if (e.which !== 13) return;
    
        let $input = $(this);
        if ($input.is(':disabled') || $input.attr('type') === 'hidden') return;
    
        let $wrap = $input.closest('.market_open'); // контейнер окна
        if (!$wrap.length) return;
    
        // 1. приоритет — основная кнопка действия
        let $btn = $wrap.find('.market_open__btn-primary:visible:not(:disabled)').first();
    
        // 2. запасной вариант — любая кнопка
        if (!$btn.length) {
            $btn = $wrap.find('.market_open__btn:visible:not(:disabled)').first();
        }
    
        // 3. если кнопка найдена — нажимаем её
        if ($btn.length) {
            e.preventDefault();
            $btn.trigger('click');
            return;
        }
    
        // 4. иначе — переход к следующему input
        let $inputs = $wrap.find('input:visible:not(:disabled)');
        let index = $inputs.index(this);
    
        if (index > -1 && index + 1 < $inputs.length) {
            $inputs.eq(index + 1).focus();
        }
    });
    
    //назад к выбору email
    $(document).delegate('.market_back_to_email','click',function(e){
        e.preventDefault();
        market_logout(function(){
            market_load();
        });
    });
    

    // Создадим скрытый input один раз
    $(function () {
        if ($('#market_photo_input').length === 0) {
            $('body').append(
                '<input type="file" id="market_photo_input" accept="image/jpeg,image/png,image/webp" style="display:none;">'
            );
        }
    
        // По выбору файла — грузим
        $(document).on('change', '#market_photo_input', function () {
            if (this.files && this.files[0]) {
                market_load_photo();
            }
        });
    });
    
    // Загрузка фото по клику
    $(document).delegate('.p_avatar_btn', 'click', function(e){
        e.preventDefault();
    
        // сброс, чтобы можно было выбрать тот же файл повторно
        let inp = $('#market_photo_input');
        inp.val('');
        inp.trigger('click');
    });
   
    
    // Сохранение настроек профиля
    $(document).delegate('.market_profile_save', 'click', function(e){
        market_profile_save($(this).closest('.market_page_form'));
    });    
    
    //отвязка телеграм
    $(document).delegate('.market_telegram_unlink', 'click', function(e){
        e.preventDefault();
        market_profile_unlink_telegram();
    });
    
    //првязка телеграмм из профиля
    $(document).delegate('.market_telegram_bind', 'click', function(e){
        e.preventDefault();
    
        var $btn = $(this);
        var $wrap = $btn.closest('.p_actions').next('.market_tg_profile_widget');
    
        if (!$wrap.length){
            return false;
        }
    
        $wrap.stop(true, true).slideDown(150);
        market_profile_tg_render_widget($wrap.find('.js-market-tg-auth-profile').first());
    
        return false;
    });

    // выход из профиля/корзины
    $(document).delegate('.market_logout_btn', 'click', function(e){
        e.preventDefault();
        market_logout(function(){ market_load(); });
    });
    
    
    // клик по крестику: очистка + события для масок/валидации
    $(document).delegate('.market_open .m_inpclear', 'click', function(e){
        e.preventDefault();
        var $w = $(this).closest('.m_inpwrap');
        var $i = $w.find('input').first();
        if (!$i.length) return;
    
        $i.val('').trigger('input').trigger('change').trigger('keyup').focus();
    });
    
    // показываем/скрываем крестик при вводе
    $(document).delegate('.market_open input', 'input keyup change', function(){
        market_clear_icons_toggle($(this).closest('.market_open'));
    });
    // глаз: показать/скрыть пароль
    $(document).delegate('.market_pass_eye', 'click', function(e){
        e.preventDefault();
    
        let btn = $(this);
        let wrap = btn.closest('.market_open__inputwrap');
        let inp = wrap.find('input').first();
    
        if (!inp.length) return;
    
        let isShow = (btn.attr('data-eye') === '1');
    
        if (isShow){
            inp.attr('type','password');
            btn.attr('data-eye','0');
            btn.attr('aria-label','Показать пароль');
    
            let svgHide = btn.attr('data-svg-hide') || '';
            if (svgHide!==''){
                btn.html(svgHide);
            }
        }else{
            inp.attr('type','text');
            btn.attr('data-eye','1');
            btn.attr('aria-label','Скрыть пароль');
    
            let svgShow = btn.attr('data-svg-show') || '';
            if (svgShow!==''){
                btn.html(svgShow);
            }
        }
    });

    
   // открыть смену пароля (из профиля)
    $(document).delegate('.market_password_open', 'click', function(e){
        e.preventDefault();
        market_password_form();
    });

    // сохранить новый пароль
    $(document).delegate('.market_pass_change', 'click', function(e){
        e.preventDefault();
        market_password_change();
    });

    // назад в профиль
    $(document).delegate('.market_pass_back', 'click', function(e){
        e.preventDefault();
        market_tab('profile');
    });


    // Изменение статуса оповещений (ползунок)
    $(document).delegate('.market_chk_notification', 'change', function(e){
        let is_checked = $(this).prop('checked') ? 1 : 0;
        
        let data_ = new Object();
        data_['com']='ajax';
        data_['_t'] = 'market_change_notification';
        data_['ajax'] = 'market';
        data_['val'] = is_checked;
        
        loading_market(1);
        $.ajax({
            "type": "POST",
            "url": $('base').attr('href'),
            "dataType": "text",
            "data": data_,
            "success": function(data, textStatus){
                loading_market(0);
                if (is_json(data) == true){
                    let data_n = JSON.parse(data);
                    if (data_n.status_ == 'ok'){
                        alert_market('Настройки оповещений сохранены!','Успешно','ok',1500);
                    } else {
                        alert_market((data_n.message ? data_n.message : 'Ошибка сохранения'),'Ошибка','error');
                    }
                }
                else {
                    alert_market(data,'Ошибка','error');
                }
            },
            "error": function(){
                loading_market(0);
                alert_market('Ошибка сети/сервера','Ошибка','error');
            }
        });
    });
    
    // Раскрытие/сокрытие состава заказа
    $(document).delegate('.m_order', 'click', function(e){
        var target = e.target;
    
        if (target && target.nodeType === 3) {
            target = target.parentNode;
        }
    
        var $target = $(target);
        if (
            $target.closest('.m_order_pay_form_inline').length ||
            $target.closest('.m_order_pay_form').length ||
            $target.closest('.m_order_pay_btn_inline').length ||
            $target.closest('.m_order_pay_btn').length ||
            (($target.attr('class')).split('cancel').length - 1)>0
           
        ) {
            return true;
        }
    
        e.preventDefault();
    
        let details = $(this).next('.m_order_details');
        let icon = $(this).find('.m_order_chevron');
    
        details.slideToggle(200);
    
        if (icon.hasClass('fa-chevron-down')) {
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        } else {
            icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }
    });
    
    
    //отмена заказа
    $(document).delegate('.market_order_cancel', 'click', function(e){
        e.preventDefault();

        var orderId = $(this).attr('data-order-id') || 0;

        market_order_cancel(orderId);
    });


    //Кнопки +/- в самой корзине
    $(document).delegate('.m_qty_btn', 'click', function(e){
        e.preventDefault();
        let inp = $(this).siblings('.m_qty_inp');
        let val = parseFloat(inp.val());
        
        if ($(this).text() === '+') {
            val++;
        } else {
            val--;
        }
        
        if (val < 0) val = 0;
        inp.val(val).trigger('change');
    });

    // Отправка обновленного количества на сервер
    $(document).delegate('.m_qty_inp', 'change', function(){
        let item_div = $(this).closest('.m_item');
        let s_cat_id = item_div.attr('data-id');
        let val = parseFloat($(this).val());

        let data_ = {
            _t: 'market_update_cart_item',
            ajax: 'market',
            com: 'ajax',
            s_cat_id: s_cat_id,
            kolvo: val
        };

        loading_market(1);
        $.ajax({
            type: "POST",
            url: $('base').attr('href'),
            dataType: "text",
            data: data_,
            success: function(data) {
                loading_market(0);
                if (is_json(data)) {
                    // Перезагружаем вкладку корзины для пересчета сумм
                    market_tab('cart');
                }
            }
        });
    });

    // Оформление заказа
    $(document).delegate('.market_order_create', 'click', function(e){
        e.preventDefault();
        let btn = $(this);
        if (btn.prop('disabled')) return;
    
        // Собираем все инпуты (fio, phone, address, city, comment)
        let form_data = market_active_form().serializeObject();
        form_data['com']='ajax';
        form_data['_t'] = 'market_order_create';
        form_data['ajax'] = 'market';
    
        loading_market(1);
        $.ajax({
            type: "POST",
            url: $('base').attr('href'),
            dataType: "text",
            data: form_data,
            success: function(data) {
                loading_market(0);
                if (is_json(data)) {
                    let d = JSON.parse(data);
                    if (d.status_ === 'ok') {
                        alert_market('Ваш заказ успешно оформлен! Переходим к оплате.', 'Заказ оформлен', 'ok', 1200, '', function(){
                            var orderId = parseInt(d.order_id, 10) || 0;
    
                            market_tab('orders', function(){
                                var $orderWrap = $();
                                var $payForm = $();
    
                                if (orderId > 0){
                                    $orderWrap = $('.market_page_orders[data-order-id="' + orderId + '"]').first();
                                    if ($orderWrap.length){
                                        $payForm = $orderWrap.find('.m_order_pay_form_inline').first();
                                    }
                                }
    
                                if (!$payForm.length){
                                    $payForm = $('.m_order_pay_form_inline').first();
                                }
    
                                if ($payForm.length){
                                    var formEl = $payForm.get(0);
                                    if (formEl && typeof formEl.submit === 'function'){
                                        formEl.submit();
                                        return;
                                    }
                                }
    
                                if (orderId > 0){
                                    var $details = $('.market_page_orders[data-order-id="' + orderId + '"] .m_order_details').first();
                                    var $icon = $('.market_page_orders[data-order-id="' + orderId + '"] .m_order_chevron').first();
    
                                    if ($details.length && !$details.is(':visible')){
                                        $details.show();
                                    }
                                    if ($icon.length){
                                        $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                                    }
                                }
                            });
                        });
                    } else {
                        alert_market(d.message || 'Ошибка оформления', 'Ошибка', 'error');
                    }
                } else {
                    alert_market(data, 'Ошибка', 'error');
                }
            }
        });
    });
    
    
    // Обработка прямых ссылок модуля корзины
    market_handle_public_url(window.location.href, true);
    
    //// ШАГ 5 - авторизация по логину и хэшу - авторизация по ссылке 
    let market_login_flag = getUrlParameter('market_login');
    let market_login_email = getUrlParameter('email');
    let market_login_hash = getUrlParameter('hash');
    
    if (market_login_flag !== null && market_login_email !== null && market_login_email !== '' && market_login_hash !== null && market_login_hash !== '') {
        market_login(market_login_email, market_login_hash);
    }
    
    //////////////////////////////////////////////////////////////////////////////////////////////////
    // общий перехват ссылок корзины
    $(document).delegate('a[href*="com=market"]', 'click', function(e){
        let href = $(this).attr('href');
    
        if (!href){
            return true;
        }
    
        e.preventDefault();
        e.stopPropagation();
    
        market_handle_public_url(href, false);
        return false;
    });
    
    //////////////////////////////////////////////////////////////////////////////////////////////////
    // окно "товар добавлен в корзину"
    $(document).delegate('.js-market-go-cart', 'click', function(e){
        e.preventDefault();
    
        $('.alert_m_auth').arcticmodal('close');
    
        setTimeout(function(){
            market_open(function(){
                market_load('cart');
            });
        }, 120);
    
        return false;
    });
    
    $(document).delegate('.js-market-continue-shopping', 'click', function(e){
        e.preventDefault();
    
        $('.alert_m_auth').arcticmodal('close');
    
        return false;
    });
    

});
// toowin86 2026-03-23 v1

///////////////////////////////////////////////////////////////////////////////////////
// SITE MENU //////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////

function site_menu_is_mobile(){
    return window.innerWidth <= 768;
}

function site_menu_get_header(obj){
    var $obj=$(obj);
    var $header=$obj.closest('.main-header');
    
    if ($header.length<1){
        $header=$('.main-header').first();
    }
    
    return $header;
}

function site_menu_get_nav(obj){
    var $header=site_menu_get_header(obj);
    var $nav=$header.find('.header-nav').first();
    
    if ($nav.length<1){
        $nav=$header.find('nav').first();
    }
    
    return $nav;
}

function site_menu_get_burger(obj){
    var $header=site_menu_get_header(obj);
    return $header.find('.js-header-burger, .header-burger').first();
}

function site_menu_open(obj){
    var $header=site_menu_get_header(obj);
    var $nav=site_menu_get_nav(obj);
    var $burger=site_menu_get_burger(obj);
    
    if ($header.length<1 || $nav.length<1){
        return false;
    }
    
    $header.addClass('is-menu-open');
    $burger.attr('aria-expanded','true');
    
    if (site_menu_is_mobile()){
        $nav.stop(true,true).slideDown(200);
    }
    else{
        $nav.stop(true,true).removeAttr('style');
    }
    
    return true;
}

function site_menu_close(obj){
    var $header=site_menu_get_header(obj);
    var $nav=site_menu_get_nav(obj);
    var $burger=site_menu_get_burger(obj);
    
    if ($header.length<1 || $nav.length<1){
        return false;
    }
    
    $header.removeClass('is-menu-open');
    $burger.attr('aria-expanded','false');
    
    if (site_menu_is_mobile()){
        $nav.stop(true,true).slideUp(200);
    }
    else{
        $nav.stop(true,true).removeAttr('style');
    }
    
    return true;
}

function site_menu_toggle(obj){
    var $header=site_menu_get_header(obj);
    
    if ($header.hasClass('is-menu-open')){
        site_menu_close($header);
    }
    else{
        site_menu_open($header);
    }
}

function site_menu_refresh(){
    $('.main-header').each(function(){
        var $header=$(this);
        var $nav=site_menu_get_nav($header);
        var $burger=site_menu_get_burger($header);
        
        if ($nav.length<1){
            return true;
        }
        
        if (site_menu_is_mobile()){
            if ($header.hasClass('is-menu-open')){
                $nav.show();
                $burger.attr('aria-expanded','true');
            }
            else{
                $nav.hide();
                $burger.attr('aria-expanded','false');
            }
        }
        else{
            $header.removeClass('is-menu-open');
            $nav.removeAttr('style');
            $burger.attr('aria-expanded','false');
        }
    });
}

function site_menu_init(){
    $('.main-header').each(function(){
        var $header=$(this);
        var $nav=site_menu_get_nav($header);
        var $burger=site_menu_get_burger($header);
        
        if ($nav.length<1){
            return true;
        }
        
        if (!$nav.hasClass('header-nav')){
            $nav.addClass('header-nav');
        }
        
        if ($burger.length>0){
            if (typeof $burger.attr('aria-expanded')=='undefined'){
                $burger.attr('aria-expanded','false');
            }
        }
    });
    
    site_menu_refresh();
}

//strTrim вместо $.trim
function strTrim(val){
    if (typeof val === 'undefined' || val === null){
        return '';
    }

    return String(val).trim();
}

////////////////АНКОРЫ

///////////////////////////////////////////////////////////////////////////////////////
// ANCHOR SCROLL //////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////

function site_anchor_normalize_hash(hash){
    hash = String(hash || '').trim();

    if (hash === '' || hash === '#'){
        return '';
    }

    if (hash.indexOf('#') !== 0){
        hash = '#' + hash;
    }

    hash = '#' + hash.substring(1).replace(/^\/+/, '').replace(/\/+$/, '');

    if (hash === '#'){
        return '';
    }

    return hash;
}

function site_anchor_get_header_offset(){
    var max_h = 0;

    $('.main-header:visible').each(function(){
        var h = Math.ceil($(this).outerHeight() || 0);
        if (h > max_h){
            max_h = h;
        }
    });

    return max_h + 12;
}

function site_anchor_get_target(hash){
    var clear_hash = site_anchor_normalize_hash(hash);

    if (clear_hash === ''){
        return $();
    }

    var id = clear_hash.substring(1);
    var decoded_id = id;

    try{
        decoded_id = decodeURIComponent(id);
    }catch(e){}

    var $target = $('#' + decoded_id);

    if ($target.length < 1){
        $target = $('[name="' + decoded_id.replace(/"/g, '\\"') + '"]').first();
    }

    return $target.first();
}

function site_anchor_scroll_to(hash, animate, update_hash){
    var clear_hash = site_anchor_normalize_hash(hash);
    var $target = site_anchor_get_target(clear_hash);

    if ($target.length < 1){
        return false;
    }

    var offset = site_anchor_get_header_offset();
    var top = Math.round($target.offset().top - offset);

    if (top < 0){
        top = 0;
    }

    if (animate === false){
        $(window).scrollTop(top);
    }
    else{
        $('html, body').stop(true).animate({scrollTop: top}, 350);
    }

    if (update_hash !== false){
        if (window.history && typeof window.history.replaceState === 'function'){
            window.history.replaceState(null, document.title, window.location.pathname + window.location.search + clear_hash);
        }
        else{
            window.location.hash = clear_hash;
        }
    }

    return true;
}

function site_anchor_is_same_page_link(href){
    if (!href){
        return false;
    }

    var url = null;

    try{
        url = new URL(href, window.location.origin);
    }catch(e){
        return false;
    }

    if (!url.hash || url.hash === '#'){
        return false;
    }

    var current_path = window.location.pathname.replace(/\/+$/, '') || '/';
    var url_path = url.pathname.replace(/\/+$/, '') || '/';

    return (url.origin === window.location.origin && url_path === current_path);
}

///////////////////////////////////////////////////////////////////////////////////////
// MEDIA SLIDER ///////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////

function site_media_slider_show($slider, index)
{
    var $slides = $slider.find('.mod-media-slider__slide');
    var total = $slides.length;

    if (total < 1) {
        return false;
    }

    if (index < 0) {
        index = total - 1;
    }

    if (index >= total) {
        index = 0;
    }

    $slides.removeClass('is-active').attr('aria-hidden', 'true');
    $slides.eq(index).addClass('is-active').attr('aria-hidden', 'false');
    $slider.attr('data-index', index);

    return true;
}
//медиа сладйео
function site_media_slider_init(root)
{
    var $root = root ? $(root) : $(document);

    $root.find('.js-mod-media-slider').each(function(){
        var $slider = $(this);
        var start_index = parseInt($slider.attr('data-index') || '0', 10);

        if (isNaN(start_index)) {
            start_index = 0;
        }

        site_media_slider_show($slider, start_index);
    });
}





///////////////////////////////////////////////////////////////////////////////////////
// READY //////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////





$(document).ready(function(){
    
    site_media_slider_init();
    site_menu_init();

    if (window.location.hash){
        setTimeout(function(){
            site_anchor_scroll_to(window.location.hash, false, true);
        }, 80);
    }
  
    
    ///////////////////////////////////////////////////////////////////////////////////////
    // EVENTS /////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////
    
    // клик по бургеру
    $(document).delegate('.js-header-burger, .header-burger','click',function(e){
        e.preventDefault();
        e.stopPropagation();
        site_menu_toggle($(this));
    });
    
    // клик по ссылке меню на мобилке
    $(document).delegate('.main-header .header-nav a','click',function(e){
        if (site_menu_is_mobile()==false){
            return true;
        }
        
        var $a=$(this);
        var $li=$a.closest('li');
        
        // если внутри есть подменю, то ссылку не блокируем
        // просто закрываем меню только для обычных пунктов
        if ($li.find('ul').first().length<1){
            site_menu_close($a);
        }
    });
    
    // клик вне шапки
    $(document).delegate('body','click',function(e){
        if (site_menu_is_mobile()==false){
            return true;
        }
        
        if ($(e.target).closest('.main-header').length<1){
            site_menu_close($('.main-header'));
        }
    });
    
    // esc
    $(document).delegate(document,'keydown',function(e){
        if (e.keyCode==27){
            site_menu_close($('.main-header'));
        }
    });
    
    // resize
    var site_menu_resize_timer=false;
    $(window).on('resize',function(){
        if (site_menu_resize_timer){
            clearTimeout(site_menu_resize_timer);
        }
        
        site_menu_resize_timer=setTimeout(function(){
            site_menu_refresh();
        },120);
    });


    
    
    $(document).delegate('.js-free-vpn-btn', 'click', function(e){
        e.preventDefault();

        if (site_free_vpn_running === true) {
            return false;
        }

        site_free_vpn_pending = true;

        if ($(this).attr('data-auth') === '1') {
            site_free_vpn_request();
        } else {
            site_free_vpn_start_auth();
        }

        return false;
    });

    $(document).ajaxSuccess(function(event, xhr, settings){
        if (site_free_vpn_pending !== true || site_free_vpn_running === true) {
            return true;
        }

        var request_data = site_free_vpn_parse_request_data(settings && settings.data ? settings.data : '');

        if (!request_data || request_data.ajax !== 'market') {
            return true;
        }

        if ($.inArray(request_data._t, ['market_create_update_user', 'market_chk_password', 'market_login']) === -1) {
            return true;
        }

        var response_json = site_free_vpn_parse_json(xhr.responseText || '');
        if (response_json === false || response_json.status_ !== 'ok') {
            return true;
        }

        setTimeout(function(){
            site_free_vpn_request();
        }, 120);

        return true;
    });

    $(document).delegate('.alert_m_auth .alert_m_close', 'click', function(){
        if (site_free_vpn_running === false) {
            site_free_vpn_pending = false;
        }
    });
    
    
    // клик по якорной ссылке
    $(document).delegate('a[href*="#"]','click',function(e){
        var href = $(this).attr('href') || '';
    
        if (!site_anchor_is_same_page_link(href) && href.indexOf('#') !== 0){
            return true;
        }
    
        var hash = '';
    
        if (href.indexOf('#') === 0){
            hash = href;
        }
        else{
            try{
                hash = new URL(href, window.location.origin).hash || '';
            }catch(err){
                hash = '';
            }
        }
    
        hash = site_anchor_normalize_hash(hash);
    
        if (hash === ''){
            return true;
        }
    
        if (site_anchor_get_target(hash).length < 1){
            return true;
        }
    
        e.preventDefault();
    
        if (site_menu_is_mobile()){
            site_menu_close($('.main-header'));
        }
    
        setTimeout(function(){
            site_anchor_scroll_to(hash, true, true);
        }, 220);
    
        return false;
    });
    
    // если hash изменился вручную
    $(window).on('hashchange', function(){
        if (window.location.hash){
            site_anchor_scroll_to(window.location.hash, false, true);
        }
    });
    
    //слайдер фото
    $(document).delegate('.js-mod-media-prev', 'click', function(e){
        e.preventDefault();
    
        var $slider = $(this).closest('.js-mod-media-slider');
        var current_index = parseInt($slider.attr('data-index') || '0', 10);
    
        if (isNaN(current_index)) {
            current_index = 0;
        }
    
        site_media_slider_show($slider, current_index - 1);
        return false;
    });
    
    $(document).delegate('.js-mod-media-next', 'click', function(e){
        e.preventDefault();
    
        var $slider = $(this).closest('.js-mod-media-slider');
        var current_index = parseInt($slider.attr('data-index') || '0', 10);
    
        if (isNaN(current_index)) {
            current_index = 0;
        }
    
        site_media_slider_show($slider, current_index + 1);
        return false;
    });
});
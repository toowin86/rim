<?php
/**
 * functions.php
 * Обработка данных перед выводом в HTML атрибуты (например, в input)
 */


//файл функций проекта 
$project_functions_file = __DIR__ . '/shablon/_include/_functions.php';
if (is_file($project_functions_file) && is_readable($project_functions_file)) {
    require_once $project_functions_file;
}



function _IN($txt) {
    // Если передали null или массив (по ошибке), приводим к строке
    $txt = is_string($txt) || is_numeric($txt) ? (string)$txt : '';
    
    // htmlspecialchars преобразует спецсимволы в HTML-сущности
    // ENT_QUOTES - обязательно! Экранирует и двойные ("), и одинарные (') кавычки
    // 'UTF-8' - указываем кодировку явно во избежание сюрпризов на разных серверах
    return htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
}

/**
 * Форматированный вывод массива для отладки.
 */
function print_rf(mixed $arr): void
{
    echo '<pre style="white-space:pre-wrap;word-break:break-word;">';
    print_r($arr);
    echo '</pre>';
}

/**
 * Рекурсивная очистка данных (строк и массивов любой вложенности)
 * Удаляет нулевой байт и обрезает пробелы по краям.
 */
function _clean_data($data) {
    // Если это массив, обходим его рекурсивно
    if (is_array($data)) {
        $cleaned_array = [];
        foreach ($data as $key => $value) {
            // Очищаем сам ключ (если он строковый)
            $clean_key = is_string($key) ? trim(str_replace("\0", '', $key)) : $key;
            
            // Рекурсивно очищаем значение и записываем по чистому ключу
            $cleaned_array[$clean_key] = _clean_data($value);
        }
        return $cleaned_array;
    }
    
    // Если это строка, чистим её
    if (is_string($data)) {
        return trim(str_replace("\0", '', $data));
    }
    
    // Если это число, null или boolean — возвращаем как есть
    return $data;
}

function clean_person_name(string $name): string
{
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = trim($name);
    $name = preg_replace('/[\x{FE0E}\x{FE0F}\x{200D}]/u', '', $name);
    $name = preg_replace("/[^\p{L}\p{N}\s\-\'’]/u", '', $name);
    $name = preg_replace('/\s+/u', ' ', $name);

    return trim($name);
}
function clean_text_field($value): string
{
    $value = is_scalar($value) ? (string)$value : '';
    $value = _clean_data($value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/[\x{FE0E}\x{FE0F}\x{200D}]/u', '', $value);
    $value = preg_replace('/[\r\n\t]+/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim($value);
}
function clean_phone($value): string
{
    $value = is_scalar($value) ? (string)$value : '';
    return preg_replace('/\D+/u', '', $value);
}
/**
 * Безопасное получение переменной из запроса (поддерживает массивы)
 */
function _GP($key, $default = '') {
    // Получаем сырые данные или значение по умолчанию
    $value = $_REQUEST[$key] ?? $default;
    
    // Пропускаем через наш рекурсивный фильтр
    return _clean_data($value);
}


/**
 * Универсальная функция для выполнения SQL-запросов через PDO с логированием
 */
function _DB(string $sql, array $params = []) {
    global $db; 
    
    // 1. Фиксируем время старта
    $start_time = microtime(true);
    
    try {
        // 2. ВЫПОЛНЕНИЕ ЗАПРОСА (ОПТИМИЗАЦИЯ)
        // Если параметров нет, используем быстрый метод query(). 
        // Если есть — безопасный prepare() + execute()
        if (empty($params)) {
            $stmt = $db->query($sql);
            // Если PDO работает не в режиме исключений, query может вернуть false
            if ($stmt === false) {
                throw new PDOException("Ошибка выполнения запроса query()");
            }
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
        
    } catch (PDOException $e) {
        // --- ОБРАБОТЧИК ОШИБОК ---
        
        // ИСПРАВЛЕНИЕ: Берем 2 уровня стека вызовов, чтобы узнать, КТО вызвал функцию
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        // Индекс 1 содержит информацию о месте вызова
        $caller_file = isset($backtrace[1]['file']) ? str_replace($_SERVER['DOCUMENT_ROOT'], '', $backtrace[1]['file']) : 'Неизвестный файл';
        $caller_line = $backtrace[1]['line'] ?? '0';
        
        $params_log = !empty($params) ? " | Параметры: " . json_encode($params, JSON_UNESCAPED_UNICODE) : "";
        
        $error_log_entry = sprintf(
            "[%s] ОШИБКА SQL: %s | Файл: %s (строка %d) | Запрос: %s%s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $caller_file,
            $caller_line,
            $sql,
            $params_log
        );
        
        $log_dir = __DIR__ . '/logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        file_put_contents($log_dir . '/sql_errors.log', $error_log_entry, FILE_APPEND | LOCK_EX);
        
        return false;
    }
    
    // --- ЕСЛИ ЗАПРОС УСПЕШЕН ---
    
    // 3. Считаем время выполнения
    $execution_time = round(microtime(true) - $start_time, 5);
    
    // ИСПРАВЛЕНИЕ: Точно так же получаем реальное место вызова для лога успеха
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller_file = isset($backtrace[1]['file']) ? str_replace($_SERVER['DOCUMENT_ROOT'], '', $backtrace[1]['file']) : 'Неизвестный файл';
    $caller_line = $backtrace[1]['line'] ?? '0';

    $params_log = !empty($params) ? " | Параметры: " . json_encode($params, JSON_UNESCAPED_UNICODE) : "";
    
    $log_entry = sprintf(
        "[%s] Время: %s сек. | Файл: %s (строка %d) | Запрос: %s%s\n",
        date('Y-m-d H:i:s'),
        $execution_time,
        $caller_file,
        $caller_line,
        $sql,
        $params_log
    );
    
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    file_put_contents($log_dir . '/sql_queries.log', $log_entry, FILE_APPEND | LOCK_EX);
    
    return $stmt;
}

/**
 * Транслитерация кириллицы в латиницу (создание slug / ЧПУ)
 */
function ru_us(string $str): string {
    // static сохраняет массив в памяти между вызовами функции
    static $map = [
        'А'=>'A', 'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ё'=>'E', 'Ж'=>'ZH', 'З'=>'Z', 'И'=>'I', 'Й'=>'Y',
        'К'=>'K', 'Л'=>'L', 'М'=>'M', 'Н'=>'N', 'О'=>'O', 'П'=>'P', 'Р'=>'R', 'С'=>'S', 'Т'=>'T', 'У'=>'U', 'Ф'=>'F',
        'Х'=>'H', 'Ц'=>'TS', 'Ч'=>'CH', 'Ш'=>'SH', 'Щ'=>'SCH', 'Ъ'=>'', 'Ы'=>'Y', 'Ь'=>'', 'Э'=>'E', 'Ю'=>'YU', 'Я'=>'YA',
        'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'e', 'ж'=>'zh', 'з'=>'z', 'и'=>'i', 'й'=>'y',
        'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n', 'о'=>'o', 'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f',
        'х'=>'h', 'ц'=>'ts', 'ч'=>'ch', 'ш'=>'sh', 'щ'=>'sch', 'ъ'=>'', 'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya'
    ];

    // 1. Транслитерация букв
    $out = strtr($str, $map);

    // 2. Перевод в нижний регистр
     $out = strtolower($out);

    // 3. Заменяем всё, кроме латиницы, цифр, дефиса и подчеркивания, на дефис
    $out = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $out);

    // 4. Удаляем дублирующиеся дефисы (например, если было "слово...еще", станет "слово-еще")
    $out = preg_replace('/-{2,}/', '-', $out);

    // 5. Обрезаем дефисы по краям строки
    return trim($out, '-');
}

function get_protocol() {
    // Проверяем стандартный HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return "https://";
    }
    // Проверяем порт
    if ($_SERVER['SERVER_PORT'] == 443) {
        return "https://";
    }
    // Проверяем заголовки от прокси (например, если сайт за Cloudflare или Nginx)
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
        return "https://";
    }
    return "http://";
}


/**
 * Проверяет текущий URL по таблице a_redirect.
 * Кэширует как найденные редиректы, так и промахи.
 *
 * TTL:
 * - найденный редирект: 600 сек
 * - не найдено: 60 сек
 */
function check_redirects(): void
{
    // Защита от запуска в CLI / некорректного окружения
    if (!isset($_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI'])) {
        return;
    }

    $protocol = get_protocol();
    $serverName = (string)$_SERVER['SERVER_NAME'];
    $requestUri = (string)$_SERVER['REQUEST_URI'];

    if ($serverName === '' || $requestUri === '') {
        return;
    }

    $url_redirect = $protocol . $serverName . $requestUri;
    $url_decoded  = urldecode($url_redirect);

    $cacheKey = redirect_cache_key($url_redirect, $url_decoded);
    $cached = redirect_cache_get($cacheKey);

    // 1. Если есть кэш
    if (is_array($cached)) {
        // Нашли редирект в кэше
        if (!empty($cached['found']) && !empty($cached['id']) && isset($cached['link_out'])) {
            $redirectId = (int)$cached['id'];
            $location = str_replace(["\r", "\n"], '', (string)$cached['link_out']);

            // Счетчик сохраняем в БД на каждом реальном редиректе
            _DB("UPDATE a_redirect SET kol = kol + 1 WHERE id = ?", [$redirectId]);

            header('Location: ' . $location, true, 301);
            exit;
        }

        // В кэше записано, что редиректа нет
        return;
    }

    // 2. Кэша нет — идем в БД
    $sql = "SELECT id, link_out FROM a_redirect WHERE link_in IN (?, ?) LIMIT 1";
    $res = _DB($sql, [$url_redirect, $url_decoded]);

    if ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $redirectId = (int)$row['id'];
        $location   = str_replace(["\r", "\n"], '', (string)$row['link_out']);

        // Кэшируем найденный редирект на 10 минут
        redirect_cache_set($cacheKey, [
            'found'    => 1,
            'id'       => $redirectId,
            'link_out' => $location,
        ], 600);

        // Счетчик переходов
        _DB("UPDATE a_redirect SET kol = kol + 1 WHERE id = ?", [$redirectId]);

        header('Location: ' . $location, true, 301);
        exit;
    }

    // 3. Промах кэшируем коротко
    redirect_cache_set($cacheKey, [
        'found' => 0,
    ], 60);
}
/**
 * Доступен ли APCu для веб-запросов
 */
function redirect_cache_use_apcu(): bool
{
    if (function_exists('apcu_enabled')) {
        return apcu_enabled();
    }

    return function_exists('apcu_fetch') && (bool)ini_get('apc.enabled');
}

/**
 * Ключ кэша для пары URL: исходный + urldecode-вариант
 */
function redirect_cache_key(string $url_raw, string $url_decoded): string
{
    return 'redirect_v1_' . md5($url_raw . "\n" . $url_decoded);
}

/**
 * Папка файлового кэша
 */
function redirect_cache_dir(): string
{
    $dir = __DIR__ . '/cache/redirects/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Получение значения из кэша
 */
function redirect_cache_get(string $key)
{
    // 1. APCu
    if (redirect_cache_use_apcu()) {
        $success = false;
        $data = apcu_fetch($key, $success);
        if ($success) {
            return $data;
        }
    }

    // 2. Файловый кэш
    $file = redirect_cache_dir() . $key . '.json';
    if (!is_file($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload) || !isset($payload['expires_at'])) {
        @unlink($file);
        return null;
    }

    if ((int)$payload['expires_at'] < time()) {
        @unlink($file);
        return null;
    }

    return $payload['data'] ?? null;
}

/**
 * Запись значения в кэш
 */
function redirect_cache_set(string $key, array $data, int $ttl): void
{
    // 1. APCu
    if (redirect_cache_use_apcu()) {
        apcu_store($key, $data, $ttl);
    }

    // 2. Файловый кэш
    $file = redirect_cache_dir() . $key . '.json';
    $payload = [
        'expires_at' => time() + $ttl,
        'data'       => $data,
    ];

    @file_put_contents(
        $file,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

/**
 * Вспомогательная функция для генерации 404 ошибки
 */
function err_404($msg = 'Страница не найдена', $title = '404 | Not Found') {
   
    $message404 = $msg;
    $title404 = $title;
    if (file_exists(__DIR__ . '/404.php')) {
        require_once __DIR__ . '/404.php';
    } else {
        echo "<h1>{$title}</h1><p>{$msg}</p>";
    }
    exit;
}

/**
 * Получает структуру сайта из базы данных и формирует массивы.
 */
/**
 * Получает структуру сайта из базы данных и формирует массивы.
 * Включает id_to_key для O(1) доступа и пакетную загрузку картинок для решения проблемы N+1.
 */
function get_base_strurkura() {
    $struktura = [
        'id'            => [],
        'pid'           => [],
        'tip'           => [],
        'chk_active'    => [],
        'url'           => [],
        'name'          => [],
        'html_code'     => [],
        'id_to_key'     => [],

        // шаблоны страниц
        'shablon_ids'   => [],
        'shablon_names' => [],

        // фото
        'img_id'        => [],
        'img_img'       => [],
        'img_tip'       => [],
        'img_comments'  => [],

        // модули страницы
        'modules'       => [],

        // хлебные крошки
        'hleb'             => []
    ];

    // 1. СТРУКТУРА СТРАНИЦ
    $res = _DB("SELECT id, sid, pid, tip, chk_active, url, name, html_code FROM s_struktura ORDER BY sid ASC");
    if ($res) {
        $key = 0;
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {

            if ($key==0){$row['url']='';}//урл для главной страницы

            $current_id = (int)$row['id'];

            $struktura['id'][$key]         = $current_id;
            $struktura['pid'][$key]        = (int)$row['pid'];
            $struktura['tip'][$key]        = (string)$row['tip'];
            $struktura['chk_active'][$key] = (int)$row['chk_active'];
            $struktura['name'][$key]       = (string)$row['name'];
            $struktura['url'][$key]        = trim((string)$row['url']);
            $struktura['html_code'][$key]  = (string)$row['html_code'];

            $struktura['img_id'][$key]       = [];
            $struktura['img_img'][$key]      = [];
            $struktura['img_tip'][$key]      = [];
            $struktura['img_comments'][$key] = [];

            $struktura['shablon_ids'][$key]   = [];
            $struktura['shablon_names'][$key] = [];
            
            $struktura['hleb'][$key]             = [];

            $struktura['id_to_key'][$current_id] = $key;

            $key++;
        }
    }

    // 2. ВСЕ ФОТО ДЛЯ СТРУКТУРЫ
	// a_menu_id = 6 — это привязка модуля фотографий к структуре сайта
    $sql_photos = "SELECT id, row_id, img, tip, comments FROM a_photo WHERE a_menu_id = 6 ORDER BY sid ASC";
    $res_photos = _DB($sql_photos);

    if ($res_photos) {
        while ($row_photo = $res_photos->fetch(PDO::FETCH_ASSOC)) {
            $row_id = (int)$row_photo['row_id'];

			// Если такой раздел существует в загруженной структуре
            if (isset($struktura['id_to_key'][$row_id])) {
                $key = $struktura['id_to_key'][$row_id];

                $struktura['img_id'][$key][]       = (int)$row_photo['id'];
                $struktura['img_img'][$key][]      = (string)$row_photo['img'];
                $struktura['img_tip'][$key][]      = (string)$row_photo['tip'];
                $struktura['img_comments'][$key][] = (string)$row_photo['comments'];
            }
        }
    }

    // 3. ВСЕ ШАБЛОНЫ СТРАНИЦ
    $sql_shablons = "
        SELECT
            ssh.id1 AS s_struktura_id,
            sh.id AS shablon_id,
            sh.name AS shablon_name
        FROM s_struktura_s_shablon ssh
        INNER JOIN s_shablon sh ON sh.id = ssh.id2
        WHERE sh.chk_active = 1
        ORDER BY ssh.id1 ASC, ssh.id ASC, sh.id ASC
    ";

    $res_shablons = _DB($sql_shablons);
    if ($res_shablons) {
        while ($row_shablon = $res_shablons->fetch(PDO::FETCH_ASSOC)) {
            $s_id = (int)$row_shablon['s_struktura_id'];

            if (!isset($struktura['id_to_key'][$s_id])) {
                continue;
            }

            $key = $struktura['id_to_key'][$s_id];
            $shablon_id = (int)$row_shablon['shablon_id'];
            $shablon_name = trim((string)$row_shablon['shablon_name']);

            $struktура['shablon_ids'][$key][] = $shablon_id;
            $struktura['shablon_names'][$key][] = $shablon_name;
        }
    }

    // 4. ВСЕ ПОДКЛЮЧЕННЫЕ К СТРАНИЦАМ МОДУЛИ
    $sql_mods = "
        SELECT
            sm.id1 AS s_struktura_id,
            m.file_name,
            CASE
                WHEN sm.html_code IS NOT NULL AND TRIM(sm.html_code) <> '' THEN sm.html_code
                ELSE m.html_code
            END AS html_code
        FROM s_struktura_s_mod sm
        INNER JOIN s_mod m ON sm.id2 = m.id
        WHERE m.chk_active = 1
        ORDER BY sm.sid ASC, sm.id ASC
    ";

    $res_mods = _DB($sql_mods);
    if ($res_mods) {
        while ($row_mod = $res_mods->fetch(PDO::FETCH_ASSOC)) {
            $s_id = (int)$row_mod['s_struktura_id'];

            if (isset($struktura['id_to_key'][$s_id])) {
                $key = $struktura['id_to_key'][$s_id];

				// Инициализируем, если еще нет
                if (!isset($struktura['modules'][$key])) {
                    $struktura['modules'][$key] = [];
                }

				// Складываем модули по порядку их sid
                $struktura['modules'][$key][] = [
                    'file_name' => (string)$row_mod['file_name'],
                    'html_code' => (string)$row_mod['html_code']
                ];
            }
        }
    }

    return $struktura;
}


/**
 * Формирует массив хлебных крошек для всех разделов структуры.
 * Вложенность определяется по pid через parents_id().
 *
 * На выходе для каждого ключа структуры формируется массив готовых HTML-элементов
 * вида <li><a href="...">...</a></li>.
 *
 * @param array $struktura
 * @return array
 */
function create_hleb(array $struktura): array {
    $hleb = [];

    if (empty($struktura['id']) || empty($struktura['id_to_key'])) {
        return $hleb;
    }

    foreach ($struktura['id'] as $key => $section_id) {
        $section_id = (int)$section_id;
        $hleb[$key] = [];

        if ($section_id <= 0) {
            continue;
        }

        $chain_ids = array_reverse(parents_id($struktura, $section_id));
        $chain_ids[] = $section_id;

        foreach ($chain_ids as $chain_id) {
            $chain_id = (int)$chain_id;

            if (!isset($struktura['id_to_key'][$chain_id])) {
                continue;
            }

            $chain_key = $struktura['id_to_key'][$chain_id];

            if (!isset($struktura['chk_active'][$chain_key]) || (int)$struktura['chk_active'][$chain_key] !== 1) {
                continue;
            }

            $name = isset($struktura['name'][$chain_key]) ? trim((string)$struktura['name'][$chain_key]) : '';
            $url = isset($struktura['full_url'][$chain_key]) ? trim((string)$struktura['full_url'][$chain_key]) : '';

            if ($name === '') {
                continue;
            }

            $hleb[$key][] = '<li><a href="' . htmlspecialchars($url !== '' ? $url : '/', ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
    }

    return $hleb;
}

/**
 * Добавляет последний пункт в массив хлебных крошек.
 *
 * @param array $hleb_items
 * @param string $name
 * @param string $url
 * @return array
 */
function hleb_add_item(array $hleb_items, string $name, string $url = ''): array {
    $name = trim($name);
    $url = trim($url);

    if ($name === '') {
        return $hleb_items;
    }

    if ($url !== '') {
        $hleb_items[] = '<li><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a></li>';
    } else {
        $hleb_items[] = '<li><span>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></li>';
    }

    return $hleb_items;
}

/**
 * Возвращает HTML хлебных крошек.
 *
 * @param array $hleb_items
 * @param bool $with_container
 * @return string
 */
function render_hleb(array $hleb_items, bool $with_container = true): string {
    if (empty($hleb_items)) {
        return '';
    }

    $html = '<nav class="hleb" aria-label="Хлебные крошки">';

    if ($with_container) {
        $html .= '<div class="container">';
    }

    $html .= '<ul>' . implode('', $hleb_items) . '</ul>';

    if ($with_container) {
        $html .= '</div>';
    }

    $html .= '</nav>';

    return $html;
}
//**********************************************************************************************************
#'0','5','6','7','8','9': write('ок');
#'1': write('ку');
#'2','3','4': write('ки');
//end_word($int_,'ов','','а');
function end_word($int_,$zer='ов',$one='',$two='а')
{
	$int_=$int_.'';
	$arr=str_split($int_);
	$simv=array_pop($arr);
    $simv2=array_pop($arr);// toowin86 12-06-13
    //echo $simv2.' '.$simv.';';
    if ($simv2!='1'){// toowin86 12-06-13
    	if ($simv=='0' or $simv=='5' or $simv=='6' or $simv=='7' or $simv=='8' or $simv=='9')
    		{return($zer);}
    	elseif ($simv=='1')
    		{return($one);}
    	elseif ($simv=='2' or $simv=='3' or $simv=='4')
    		{return($two);}
    	else 
    		{return('');}
            }// toowin86 12-06-13
     else{ // toowin86 12-06-13
        return($zer);// toowin86 12-06-13
     }   // toowin86 12-06-13
}

/**
 * Получает массив ID всех родителей для заданного раздела.
 * Максимально оптимизирована: использует карту ключей и не использует тяжелых функций.
 *
 * @param array $struktura Полный массив структуры сайта
 * @param mixed $id ID текущего раздела
 * @return array Массив ID родителей
 */
function parents_id(array $struktura, $id): array {
    $parents = [];
    $visited = [];

    $current_id = (int)$id;

    // Если id не валидный или структура пуста - возвращаем пустой массив
    if ($current_id <= 0 || empty($struktura['id_to_key'])) {
        return $parents;
    }

    $guard = 0;

    while (true) {
        // Мгновенная проверка и получение ключа за O(1) из глобальной карты
        if (!isset($struktura['id_to_key'][$current_id])) {
            break;
        }

        $key = $struktura['id_to_key'][$current_id];

        $pid = isset($struktura['pid'][$key]) ? (int)$struktura['pid'][$key] : 0;
        
        // Дошли до корня
        if ($pid === 0) {
            break;
        }

        // Защита от логического кольца (категории ссылаются друг на друга)
        if (isset($visited[$pid])) {
            break;
        }

        $parents[] = $pid;
        $visited[$pid] = true;

        $current_id = $pid;

        // Аппаратная страховка от зависания сервера (на случай непредвиденных багов)
        if (++$guard > 10000) { 
            break;
        }
    }

    return $parents;
}
/**
 * Рекурсивно вычисляет глубину вложенности раздела.
 * Работает за O(1) на каждом шаге благодаря карте id_to_key.
 *
 * @param int $s_id ID текущего раздела
 * @param array $struktura Полный массив структуры сайта
 * @param array $visited Массив пройденных ID для защиты от бесконечного цикла
 * @return int Глубина вложенности (0 - если в корне)
 */
function get_section_depth($s_id, array $struktura, array $visited = []): int {
    $current_id = (int)$s_id;

    // Базовый случай: некорректный ID, раздел не найден в структуре или обнаружено зацикливание
    if ($current_id <= 0 || !isset($struktura['id_to_key'][$current_id]) || isset($visited[$current_id])) {
        return 0;
    }

    $key = $struktura['id_to_key'][$current_id];
    $visited[$current_id] = true; // Отмечаем раздел как посещенный (хэш-множество)

    $pid = isset($struktura['pid'][$key]) ? (int)$struktura['pid'][$key] : 0;

    // Шаг рекурсии: если есть родитель, прибавляем 1 и идем к нему
    if ($pid > 0) {
        return 1 + get_section_depth($pid, $struktura, $visited);
    }

    return 0;
}
/**
 * Главный маршрутизатор (роутер) сайта.
 * Выполняет разбор текущего URL, проверяет его по структуре сайта, 
 * обрабатывает мультирегиональность (города-филиалы) и проверяет карточки товаров.
 *
 * @param array $struktura Массив всей структуры сайта
 * @param array $i_tp_arr Массив всех активных филиалов/городов
 * @return array Возвращает массив с валидными ID: структуры, товара, филиала и параметром com.
 */
function parse_url_logic(array $struktura, array $i_tp_arr) {

    $result = [
        's_struktura_id' => 0,
        's_cat_id'       => 0,
        'com'            => '',
        'true_url'       => '',
        'i_tp_id'        => 0
    ];
    
    $protocol = get_protocol();
    $base_url = $protocol . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');

    // --- 1. ПРОВЕРКА ПАРАМЕТРА COM ---
    $com = _GP('com');
    if ($com !== '') {

        // По ТЗ: только цифры, английские буквы и _
        if (!is_string($com) || !preg_match('/^[a-zA-Z0-9_]+$/', $com)) {
            err_404(
                'Некорректное имя модуля: ' . htmlspecialchars((string)$com, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'com | invalid'
            );
        }

        $file_com         = __DIR__ . '/shablon/com/' . $com . '.php';
        $file_obrabotchik = __DIR__ . '/shablon/_obrabotchik/' . $com . '.php';

        $has_com         = is_file($file_com) && is_readable($file_com);
        $has_obrabotchik = is_file($file_obrabotchik) && is_readable($file_obrabotchik);

        // Если нет вообще ни одного файла модуля — 404
        if (!$has_com && !$has_obrabotchik) {
            err_404(
                'Отсутствует модуль: ' . htmlspecialchars($com, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'com | no file'
            );
        }

        // Если есть view модуля, но нет его обработчика — конфигурация невалидна
        if ($has_com && !$has_obrabotchik) {
            err_404(
                'Для модуля отсутствует обработчик: ' . htmlspecialchars($com, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'com | no obrabotchik'
            );
        }

        $result['com'] = $com;
    }

    // --- 2. РАЗБОР ВХОДЯЩЕГО ПУТИ ---
    $request_uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string)parse_url($request_uri, PHP_URL_PATH);

    $path_clean = trim($path, '/');
    $url_parts = $path_clean === '' ? [] : explode('/', $path_clean);

    // --- 3. МУЛЬТИРЕГИОНАЛЬНОСТЬ: ИЗВЛЕЧЕНИЕ ГОРОДА ---
    reset($i_tp_arr['id']);
    $key_i_tp = key($i_tp_arr['id']) ?? (empty($i_tp_arr['id']) ? 0 : array_keys($i_tp_arr['id'])[0]);

    if (count($url_parts) > 0 && $url_parts[0] !== '') {
        $possible_city = $url_parts[0];
        $city_id = array_search($possible_city, $i_tp_arr['url'], true);

        if ($city_id !== false) {
            $key_i_tp = $city_id;
            array_shift($url_parts);
        }
    }

    $result['i_tp_id'] = $key_i_tp;

    // --- 4. ОПРЕДЕЛЕНИЕ ТОВАРА И СТРУКТУРЫ ---
    $true_url_struktura = '';
    $true_url_cat = '';
    $current_struktura_id = 0;

    $s_cat_cur_id = array_pop($url_parts);

    // Главная страница
    if ($s_cat_cur_id === null) {

        foreach ($struktura['id'] as $key => $s_id) {
            if (isset($struktura['chk_active'][$key]) && (int)$struktura['chk_active'][$key] === 1) {
                $current_struktura_id = (int)$s_id;
                $true_url_struktura = (string)$struktura['url'][$key];
                break;
            }
        }

    } else {

        // --- ПРОВЕРКА НА ТОВАР ---
        $sql = "SELECT s_cat.chk_active, GROUP_CONCAT(s_struktura.id SEPARATOR ',') as s_ids
                FROM s_cat, s_cat_s_struktura, s_struktura
                WHERE s_cat.id=?
                AND s_cat_s_struktura.id2=s_struktura.id
                AND s_cat_s_struktura.id1=s_cat.id";

        $res = _DB($sql, [$s_cat_cur_id]);
        $item = $res ? $res->fetch(PDO::FETCH_ASSOC) : null;

        if ($item && $item['chk_active'] !== null) {

            if ((int)$item['chk_active'] === 0) {
                err_404('Товар отключен', 'item | disabled');
            }

            $result['s_cat_id'] = (int)$s_cat_cur_id;
            $true_url_cat = (string)$s_cat_cur_id;

            $allowed_sections = !empty($item['s_ids']) ? explode(',', $item['s_ids']) : [];

            $max_depth = -1;
            $deepest_key = false;

            foreach ($allowed_sections as $s_id) {
                $s_id = (int)$s_id;
                $key_candidate = isset($struktura['id_to_key'][$s_id]) ? $struktura['id_to_key'][$s_id] : false;

                if (
                    $key_candidate !== false &&
                    isset($struktura['chk_active'][$key_candidate]) &&
                    (int)$struktura['chk_active'][$key_candidate] === 1
                ) {
                    $depth = get_section_depth($s_id, $struktura);
                    if ($depth > $max_depth) {
                        $max_depth = $depth;
                        $deepest_key = $key_candidate;
                    }
                }
            }

            if ($deepest_key !== false) {
                $true_url_struktura = (string)$struktura['url'][$deepest_key];
                $current_struktura_id = (int)$struktura['id'][$deepest_key];
            } else {
                err_404('Раздел товара отключен или не существует', 'item | no active category');
            }

        } else {

            // --- ПРОВЕРКА НА РАЗДЕЛ СТРУКТУРЫ ---
            $struktura_cur_url = $s_cat_cur_id;

            if (is_numeric($struktura_cur_url) && isset($struktura['id_to_key'][(int)$struktura_cur_url])) {
                $current_struktura_id = (int)$struktura_cur_url;
                $key = $struktura['id_to_key'][$current_struktura_id];

                if ((int)$struktura['chk_active'][$key] !== 1) {
                    err_404('Раздел отключен', 'struktura | disabled');
                }

                $true_url_struktura = (string)$struktura['url'][$key];

            } else {
                $key = array_search($struktura_cur_url, $struktura['url'], true);

                if ($key !== false) {
                    if ((int)$struktura['chk_active'][$key] !== 1) {
                        err_404('Раздел отключен', 'struktura | disabled');
                    }

                    $true_url_struktura = (string)$struktura_cur_url;
                    $current_struktura_id = (int)$struktura['id'][$key];
                } else {
                    err_404('Не обнаружена страница с указанным id/url', 'parseurl | bad id');
                }
            }
        }
    }

    if ($current_struktura_id > 0) {
        $result['s_struktura_id'] = $current_struktura_id;
    }

    // --- 5. ФОРМИРОВАНИЕ ПРАВИЛЬНОГО ПУТИ ---
    $true_path_parts = [];
    $use_city_in_url = (
        isset($_SESSION['a_options']['Добавить в url город (филиал)']) &&
        $_SESSION['a_options']['Добавить в url город (филиал)'] == '1'
    );

    if ($use_city_in_url && isset($i_tp_arr['url'][$result['i_tp_id']])) {
        $city_url = ltrim((string)$i_tp_arr['url'][$result['i_tp_id']], '/');
        if ($city_url !== '') {
            $true_path_parts[] = $city_url;
        }
    }

    if ($true_url_struktura !== '') {
        $true_path_parts[] = ltrim($true_url_struktura, '/');
    }

    $true_path = implode('/', $true_path_parts);

    if ($true_url_cat !== '') {
        $true_path = ($true_path !== '') ? $true_path . '/' . $true_url_cat : $true_url_cat;
    } else {
        if ($true_path !== '') {
            $true_path .= '/';
        }
    }

    $result['true_url'] = '/' . $true_path;

    // --- 6. ПРОВЕРКА И 301 РЕДИРЕКТ ---
    if ($path !== $result['true_url']) {
        $query_string = parse_url($request_uri, PHP_URL_QUERY);
        $redirect_url = $base_url . $result['true_url'] . ($query_string ? '?' . $query_string : '');

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect_url);
        exit;
    }

    return $result;
}
/**
 * Получает информацию о всех активных филиалах (точках продаж)
 * Оптимизировано: подгрузка соцсетей выполняется одним дополнительным запросом с использованием плейсхолдеров PDO.
 * Формирование URL зависит от глобальных настроек ($_SESSION['a_options']['Русский URL']).
 * Добавлены падежи названий городов (Р.п., Д.п., П.п.).
 * Внедрена строгая защита от дублей ID и "мусорных" данных (фантомных связей).
 *
 * @return array Массив с данными филиалов
 */
function get_i_tp_arr() {
    $i_tp_arr = array(
        'id'               => array(),
        'name'             => array(),
        'phone'            => array(),
        'email'            => array(),
        'worktime'         => array(),
        'adress'           => array(),
        'i_city_id'        => array(),
        'i_city_name'      => array(),
        'i_city_name_kogo' => array(), // Родительный падеж (Кого? Чего? - Москвы)
        'i_city_name_komy' => array(), // Дательный падеж (Кому? Чему? - Москве)
        'i_city_name_gde'  => array(), // Предложный падеж (Где? О ком? О чем? - В Москве)
        'i_city_reg'       => array(),
        'i_city_us'        => array(),
        'img'              => array(),
        'geo'              => array(),
        'url'              => array(),
        'social'           => array()
    );

    // 1. ОСНОВНОЙ ЗАПРОС
    $sql = "SELECT 
                t.id, 
                t.name AS tp_name, 
                t.phone, 
                t.email,
                t.worktime,
                t.adress,
                t.i_city_id,
                c.name AS city_name,
                c.name_r AS city_name_r,
                c.name_d AS city_name_d,
                c.name_p AS city_name_p,
                c.us_name,
                t.geo,
                (SELECT ap.img 
                 FROM a_photo ap 
                 WHERE ap.row_id = t.id 
                   AND ap.a_menu_id = 53 
                   AND ap.tip = 'Меню' 
                 ORDER BY ap.sid 
                 LIMIT 1) AS img,
                c.region AS reg_
            FROM i_tp t
            JOIN i_city c ON t.i_city_id = c.id
            WHERE t.chk_active = 1
            ORDER BY t.sid";

    $res = _DB($sql);
    $fetched_ids = []; 

    $is_russian_url = (isset($_SESSION['a_options']['Русский URL']) && $_SESSION['a_options']['Русский URL'] == '1');

    if ($res) {
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['id'];
            
            // ИСПРАВЛЕНИЕ 1: Собираем уникальные ID как ключи (Set-подход)
            $fetched_ids[$id] = $id; 
            
            $i_tp_arr['id'][$id]               = $id;
            $i_tp_arr['name'][$id]             = $row['tp_name'];
            $i_tp_arr['phone'][$id]            = $row['phone'];
            $i_tp_arr['email'][$id]            = $row['email'];
            $i_tp_arr['worktime'][$id]         = $row['worktime'];
            $i_tp_arr['adress'][$id]           = $row['adress'];
            
            $i_tp_arr['i_city_id'][$id]        = (int)$row['i_city_id'];
            $i_tp_arr['i_city_name'][$id]      = $row['city_name'];
            $i_tp_arr['i_city_name_kogo'][$id] = $row['city_name_r'];
            $i_tp_arr['i_city_name_komy'][$id] = $row['city_name_d'];
            $i_tp_arr['i_city_name_gde'][$id]  = $row['city_name_p'];
            
            $i_tp_arr['i_city_reg'][$id]       = $row['reg_'];
            $i_tp_arr['i_city_us'][$id]        = $row['us_name'];
            
            $i_tp_arr['img'][$id]              = ($row['img'] !== null) ? $row['img'] : '';
            $i_tp_arr['geo'][$id]              = $row['geo'];
            
            if ($is_russian_url) {
                $i_tp_arr['url'][$id] = $row['city_name'];
            } else {
                $i_tp_arr['url'][$id] = $row['us_name'];
            }
            
            $i_tp_arr['social'][$id]           = array(); 
        }
    }

    // 2. ЗАПРОС СОЦСЕТЕЙ
    if (!empty($fetched_ids)) {
        
        // Сбрасываем ключи, чтобы получить простой индексный массив [1, 2, 5...]
        $ids_to_fetch = array_values($fetched_ids);
        
        // Создаем строку с нужным количеством плейсхолдеров
        $placeholders = implode(',', array_fill(0, count($ids_to_fetch), '?'));
        
        $sql_social = "SELECT 
                           sn_link.i_tp_id,
                           sn.id AS social_id,
                           sn.name AS network_name,
                           sn.fa_class,
                           sn_link.name AS val,
                           (
                               SELECT ap.id
                               FROM a_photo ap
                               WHERE ap.row_id = sn.id
                                 AND ap.a_menu_id = 320
                               ORDER BY ap.sid ASC, ap.id ASC
                               LIMIT 1
                           ) AS photo_id,
                           (
                               SELECT ap.img
                               FROM a_photo ap
                               WHERE ap.row_id = sn.id
                                 AND ap.a_menu_id = 320
                               ORDER BY ap.sid ASC, ap.id ASC
                               LIMIT 1
                           ) AS photo_img
                       FROM i_tp_i_social_network sn_link
                       JOIN i_social_network sn ON sn_link.i_social_network_id = sn.id
                       WHERE sn_link.i_tp_id IN ($placeholders)
                       ORDER BY sn_link.sid ASC, sn_link.id ASC";
                       
        // Передаем очищенный массив значений
        $res_social = _DB($sql_social, $ids_to_fetch);
        
        if ($res_social) {
            while ($row_soc = $res_social->fetch(PDO::FETCH_ASSOC)) {
                $tp_id = (int)$row_soc['i_tp_id'];
                $network_name = trim((string)$row_soc['network_name']);
                $val = trim((string)$row_soc['val']);
                
                if ($network_name === '' || $val === '') {
                    continue;
                }
                
                // ИСПРАВЛЕНИЕ 2: Строгая проверка перед записью (защита от мусора)
                if (isset($i_tp_arr['social'][$tp_id])) {
                    $i_tp_arr['social'][$tp_id][$network_name] = array(
                        'id' => (int)$row_soc['social_id'],
                        'name' => $network_name,
                        'link' => $val,
                        'fa_class' => trim((string)$row_soc['fa_class']),
                        'photo_id' => (int)$row_soc['photo_id'],
                        'photo_img' => trim((string)$row_soc['photo_img'])
                    );
                }
            }
        }
    }

    return $i_tp_arr;
}

/**
 * Формирует массив готовых ссылок (full_url) для всей структуры сайта
 * с учетом текущего активного города (филиала) и глобальных настроек.
 * Внешние/прямые ссылки (tip = 'ссылка') остаются без изменений.
 *
 * @param array $struktura Массив структуры сайта
 * @param array $i_tp_arr Массив филиалов
 * @param int $i_tp_id ID текущего филиала
 * @return array Массив готовых ссылок (ключи совпадают с ключами $struktura)
 */
function create_full_url(array $struktura, array $i_tp_arr, int $i_tp_id): array {
    $full_urls = [];
    
    // 1. Определяем префикс города (один раз для всех ссылок)
    $city_prefix = '';
    $use_city = (isset($_SESSION['a_options']['Добавить в url город (филиал)']) && $_SESSION['a_options']['Добавить в url город (филиал)'] == '1');
    
    if ($use_city && isset($i_tp_arr['url'][$i_tp_id])) {
        // Явное приведение к строке (защита от null/int в PHP 8)
        $city_url = trim((string)$i_tp_arr['url'][$i_tp_id], '/');
        if ($city_url !== '') {
            $city_prefix = '/' . $city_url;
        }
    }
    
    // 2. Проходим по всем элементам структуры и формируем финальные ссылки
    if (!empty($struktura['url'])) {
        foreach ($struktura['url'] as $key => $raw_url) {
            
            // НОВОЕ ПРАВИЛО: Если тип "ссылка" (внешняя ссылка, спец-url, якорь), 
            // отдаем url как есть, без слешей и городов
            if (isset($struktura['tip'][$key]) && $struktura['tip'][$key] === 'Ссылка') {
                $full_urls[$key] = (string)$raw_url;
                continue; // Прерываем текущую итерацию и идем к следующему разделу
            }
            
            // --- Стандартная обработка для внутренних страниц ---
            
            // Явное приведение к строке
            $clean_url = trim((string)$raw_url, '/');
            
            // Собираем базовый путь: префикс города + url раздела
            $path = $city_prefix;
            if ($clean_url !== '') {
                $path .= '/' . $clean_url;
            }
            
            // Добавляем закрывающий слеш.
            $final_link = $path . '/';
            
            // Гарантированно схлопываем любое количество слешей (//, ///) в один
            $final_link = preg_replace('#/+#', '/', $final_link);
            
            $full_urls[$key] = $final_link;
        }
    }
    
    return $full_urls;
}

/**
 * Рекурсивно генерирует HTML-дерево меню (ul/li/a/span).
 * ОПТИМИЗИРОВАНО: $O(N) за счет карты потомков, решена проблема N+1 SQL запросов.
 *
 * @param array  $struktura Полный массив структуры сайта
 * @param int    $start_pid ID родителя, с которого начинаем строить ветку (0 - корень)
 * @param string $shablon   Параметр шаблона меню (для CSS или логики)
 * @param bool   $add_img   Флаг добавления картинки с типом 'Меню'
 * @param array  $children_map Скрытый параметр: карта потомков (генерируется автоматически)
 * @return string Сгенерированный HTML-код меню
 */
function create_tree_menu(array $struktura, int $start_pid = 0, string $shablon = 'Левое меню', bool $add_img = false, ?array $children_map = null): string {
    $html = '';
    $is_root_call = ($children_map === null);

    if ($children_map === null) {
        $children_map = [];
        if (!empty($struktura['pid']) && is_array($struktura['pid'])) {
            foreach ($struktura['pid'] as $key => $pid) {
                if (isset($struktura['chk_active'][$key]) && (int)$struktura['chk_active'][$key] === 1) {
                    $pid_int = (int)$pid;
                    $children_map[$pid_int][] = $key;
                }
            }
        }
    }

    $children_keys = isset($children_map[(int)$start_pid]) ? $children_map[(int)$start_pid] : [];

    if (empty($children_keys)) {
        return '';
    }

    if ($is_root_call) {
        $html .= '<ul class="tree-menu" data-shablon="' . htmlspecialchars($shablon, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    } else {
        $html .= "<ul>\n";
    }

    foreach ($children_keys as $key) {
        $id = isset($struktura['id'][$key]) ? (int)$struktura['id'][$key] : 0;
        if ($id <= 0) {
            continue;
        }

        $has_shablon = false;

        if (
            isset($struktura['s_shablon'][$key]) &&
            is_array($struktура['s_shablon'][$key]) &&
            in_array($shablon, $struktura['s_shablon'][$key], true)
        ) {
            $has_shablon = true;
        }
        elseif (
            isset($struktura['shablon_name_map'][$key]) &&
            is_array($struktura['shablon_name_map'][$key]) &&
            isset($struktura['shablon_name_map'][$key][$shablon])
        ) {
            $has_shablon = true;
        }
        elseif (
            isset($struktura['shablon_names'][$key]) &&
            is_array($struktura['shablon_names'][$key]) &&
            in_array($shablon, $struktura['shablon_names'][$key], true)
        ) {
            $has_shablon = true;
        }

        if (!$has_shablon) {
            continue;
        }

        $name = isset($struktura['name'][$key]) ? (string)$struktura['name'][$key] : '';
        $url = isset($struktura['full_url'][$key]) ? (string)$struktura['full_url'][$key] : '#';
        $tip = isset($struktura['tip'][$key]) ? (string)$struktura['tip'][$key] : '';

        $img_html = '';

        if (
            $add_img &&
            isset($struktura['img_tip'][$key]) &&
            is_array($struktura['img_tip'][$key]) &&
            in_array('Меню', $struktura['img_tip'][$key], true)
        ) {
            $img_key = array_search('Меню', $struktura['img_tip'][$key], true);
            if ($img_key !== false && isset($struktura['img_img'][$key][$img_key])) {
                $img_html = '<img src="i/s_struktura/original/' . $struktura['img_img'][$key][$img_key] . '" alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" />';
            }
        }

        $tip_lc = function_exists('mb_strtolower') ? mb_strtolower($tip, 'UTF-8') : strtolower($tip);

        $html .= "  <li>\n";
        $html .= '    <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div><span class="left_menu_span_img">' . $img_html . '</span><span>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></div>';
        $html .= "</a>\n";

        $html .= create_tree_menu($struktura, $id, $shablon, $add_img, $children_map);

        $html .= "  </li>\n";
    }

    $html .= "</ul>\n";

    return $html;
}


/**
 * Получает SEO-данные для страницы структуры
 */
function get_seo_struktura(int $id): array {
    $sql = "SELECT name, page_name, title, description, keywords FROM s_struktura WHERE id = ?";
    $res = _DB($sql, [$id]);
    if ($res && $row = $res->fetch(PDO::FETCH_ASSOC)) {
        return $row;
    }
    return [];
}

/**
 * Получает базовые данные товара (fallback для SEO)
 */
function get_seo_s_cat(int $id): array {
    $sql = "SELECT name FROM s_cat WHERE id = ?";
    $res = _DB($sql, [$id]);
    if ($res && $row = $res->fetch(PDO::FETCH_ASSOC)) {
        return $row;
    }
    return [];
}

/**
 * Собирает строку с основными свойствами товара
 * Учитываем chk_active, chk_main и правильную сортировку
 */
function get_product_properties_string(int $s_cat_id): string {
    $sql = "SELECT p.name AS prop_name, v.val AS prop_val 
            FROM s_cat_s_prop_val link
            JOIN s_prop_val v ON v.id = link.id2
            JOIN s_prop p ON p.id = v.s_prop_id
            WHERE link.id1 = ? 
              AND p.chk_active = 1 
              AND p.chk_main = 1
            ORDER BY p.sid ASC
            LIMIT 3";
            
    $res = _DB($sql, [$s_cat_id]);
    $props = [];
    
    if ($res) {
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $props[] = $row['prop_name'] . ': ' . $row['prop_val'];
        }
    }
    
    return implode(', ', $props);
}



function get_data_smtp($smtp_conn){
    $data = '';

    while ($str = fgets($smtp_conn, 515)) {
        $data .= $str;

        if (isset($str[3]) && $str[3] === ' ') {
            break;
        }
    }

    return $data;
}


/**
 * Отправка почты
 */
function send_mail_smtp($email_to, $subject, $message, $files = array(), $config = array()){
    $email_to = trim((string)$email_to);
    $subject = (string)$subject;
    $message = (string)$message;

    if ($email_to === '' || !filter_var($email_to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (!is_array($files)) {
        $files = array();
    }

    if (!is_array($config) || count($config) === 0) {
        $smtp_host = isset($_SESSION['a_options']['SMTP: сервер']) ? trim((string)$_SESSION['a_options']['SMTP: сервер']) : '';
        $smtp_port = isset($_SESSION['a_options']['SMTP: порт']) ? (int)$_SESSION['a_options']['SMTP: порт'] : 0;
        $smtp_login = isset($_SESSION['a_options']['SMTP: login']) ? trim((string)$_SESSION['a_options']['SMTP: login']) : '';
        $smtp_password = isset($_SESSION['a_options']['SMTP: password']) ? (string)$_SESSION['a_options']['SMTP: password'] : '';

        $config = array(
            'smtp_host' => $smtp_host,
            'smtp_port' => $smtp_port,
            'smtp_login' => $smtp_login,
            'smtp_password' => $smtp_password,
            'smtp_from' => $smtp_login,
            'smtp_charset' => 'UTF-8'
        );
    } else {
        $config['smtp_host'] = isset($config['smtp_host']) ? trim((string)$config['smtp_host']) : '';
        $config['smtp_port'] = isset($config['smtp_port']) ? (int)$config['smtp_port'] : 0;
        $config['smtp_login'] = isset($config['smtp_login']) ? trim((string)$config['smtp_login']) : '';
        $config['smtp_password'] = isset($config['smtp_password']) ? (string)$config['smtp_password'] : '';
        $config['smtp_from'] = isset($config['smtp_from']) && trim((string)$config['smtp_from']) !== '' ? trim((string)$config['smtp_from']) : $config['smtp_login'];
        $config['smtp_charset'] = isset($config['smtp_charset']) && trim((string)$config['smtp_charset']) !== '' ? trim((string)$config['smtp_charset']) : 'UTF-8';
    }

    if (
        $config['smtp_host'] === '' ||
        $config['smtp_port'] <= 0 ||
        $config['smtp_login'] === '' ||
        $config['smtp_password'] === '' ||
        $config['smtp_from'] === ''
    ) {
        return false;
    }

    $charset = $config['smtp_charset'];
    $host = $config['smtp_host'];
    $port = (int)$config['smtp_port'];
    $login = $config['smtp_login'];
    $password = $config['smtp_password'];
    $from_email = $config['smtp_from'];

    $server_name = isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== '' ? $_SERVER['SERVER_NAME'] : 'localhost';
    $ehlo_name = $server_name !== '' ? $server_name : 'localhost';

    $encoded_subject = mb_encode_mimeheader($subject, $charset, 'B', "\r\n");
    $encoded_from_name = mb_encode_mimeheader($server_name, $charset, 'B', "\r\n");

    $headers = '';
    $headers .= 'Date: ' . date('D, d M Y H:i:s O') . "\r\n";
    $headers .= 'From: "' . $encoded_from_name . '" <' . $from_email . '>' . "\r\n";
    $headers .= 'To: <' . $email_to . '>' . "\r\n";
    $headers .= 'Reply-To: <' . $from_email . '>' . "\r\n";
    $headers .= 'Subject: ' . $encoded_subject . "\r\n";
    $headers .= 'Message-ID: <' . uniqid('', true) . '@' . $server_name . '>' . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'X-Mailer: 23go.ru SMTP' . "\r\n";

    $body = '';
    $boundary = '==Multipart_Boundary_x' . md5(uniqid((string)mt_rand(), true)) . 'x';

    $valid_files = array();
    foreach ($files as $file_path) {
        $file_path = (string)$file_path;
        if ($file_path !== '' && is_file($file_path) && is_readable($file_path)) {
            $valid_files[] = $file_path;
        }
    }

    if (count($valid_files) > 0) {
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/html; charset=' . $charset . "\r\n";
        $body .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
        $body .= $message . "\r\n";

        foreach ($valid_files as $file_path) {
            $file_name = basename($file_path);
            $file_content = file_get_contents($file_path);

            if ($file_content === false) {
                continue;
            }

            $mime_type = 'application/octet-stream';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $tmp_mime = finfo_file($finfo, $file_path);
                    if (is_string($tmp_mime) && $tmp_mime !== '') {
                        $mime_type = $tmp_mime;
                    }
                    finfo_close($finfo);
                }
            }

            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Type: ' . $mime_type . '; name="' . $file_name . '"' . "\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $file_name . '"' . "\r\n";
            $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
            $body .= chunk_split(base64_encode($file_content)) . "\r\n";
        }

        $body .= '--' . $boundary . '--' . "\r\n";
    } else {
        $headers .= 'Content-Type: text/html; charset=' . $charset . "\r\n";
        $headers .= 'Content-Transfer-Encoding: 8bit' . "\r\n";
        $body = $message;
    }

    $remote_host = $host;
    $use_starttls = true;

    if ($port === 465) {
        $remote_host = 'ssl://' . $host;
        $use_starttls = false;
    }

    $smtp_conn = @fsockopen($remote_host, $port, $errno, $errstr, 15);
    if (!$smtp_conn) {
        return false;
    }

    stream_set_timeout($smtp_conn, 15);

    $response = get_data_smtp($smtp_conn);
    if ((int)substr($response, 0, 3) !== 220) {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, 'EHLO ' . $ehlo_name . "\r\n");
    $response = get_data_smtp($smtp_conn);
    if ((int)substr($response, 0, 3) !== 250) {
        fclose($smtp_conn);
        return false;
    }

    if ($use_starttls) {
        fputs($smtp_conn, "STARTTLS\r\n");
        $response = get_data_smtp($smtp_conn);
        if ((int)substr($response, 0, 3) !== 220) {
            fclose($smtp_conn);
            return false;
        }

        if (!stream_socket_enable_crypto($smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($smtp_conn);
            return false;
        }

        fputs($smtp_conn, 'EHLO ' . $ehlo_name . "\r\n");
        $response = get_data_smtp($smtp_conn);
        if ((int)substr($response, 0, 3) !== 250) {
            fclose($smtp_conn);
            return false;
        }
    }

    fputs($smtp_conn, "AUTH LOGIN\r\n");
    $response = get_data_smtp($smtp_conn);
    if ((int)substr($response, 0, 3) !== 334) {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, base64_encode($login) . "\r\n");
    $response = get_data_smtp($smtp_conn);
    if ((int)substr($response, 0, 3) !== 334) {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, base64_encode($password) . "\r\n");
    $response = get_data_smtp($smtp_conn);
    if ((int)substr($response, 0, 3) !== 235) {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, 'MAIL FROM:<' . $from_email . '>' . "\r\n");
    $response = get_data_smtp($smtp_conn);
    if ((int)substr($response, 0, 3) !== 250) {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, 'RCPT TO:<' . $email_to . '>' . "\r\n");
    $response = get_data_smtp($smtp_conn);
    $rcpt_code = (int)substr($response, 0, 3);
    if ($rcpt_code !== 250 && $rcpt_code !== 251) {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, "DATA\r\n");
    $response = get_data_smtp($smtp_conn);
    if ((int)substr($response, 0, 3) !== 354) {
        fclose($smtp_conn);
        return false;
    }

    $message_data = $headers . "\r\n" . $body;
    $message_data = preg_replace("/(?m)^\./", '..', $message_data);

    fputs($smtp_conn, $message_data . "\r\n.\r\n");
    $response = get_data_smtp($smtp_conn);
    if ((int)substr($response, 0, 3) !== 250) {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, "QUIT\r\n");
    fclose($smtp_conn);

    return true;
}

// ***********************************************************************************************************
//конвертер времени и телефона
function conv_($tip,$txt){
    if (!isset($txt) or is_array($txt)){echo 'Не верный тип преобразования conv_';exit;}
    
      // Нормализация для телефонов: только цифры
    $phone_digits = function ($v) {
        $v = trim((string)$v);
        if ($v === '') return '';
        $d = preg_replace('/\D+/', '', $v);
        return $d ?: '';
    };

    if ($tip === 'phone_to_db') {
        // В БД храним только цифры.
        // РФ: 8XXXXXXXXXX -> 7XXXXXXXXXX (если строго 11 цифр)
        $d = $phone_digits($txt);
        if ($d === '') return '';

        if (strlen($d) === 11 && $d[0] === '8') {
            $d = '7' . substr($d, 1);
        }

        return $d;
    }

    elseif ($tip === 'phone_from_db') {
        // Из БД -> в поле ввода (под JS маску).
        // - РФ (7 + 10 цифр) форматируем красиво: +7(999)222-11-11
        // - Любые номера, начинающиеся на 3 (375/380/...) -> просто "+<цифры>" (маску переключит JS)
        // - Остальные -> "+<цифры>"
        $d = $phone_digits($txt);
        if ($d === '') return '';

        // на всякий случай: если вдруг еще осталось 8XXXXXXXXXX
        if (strlen($d) === 11 && $d[0] === '8') {
            $d = '7' . substr($d, 1);
        }

        if (strlen($d) === 11 && $d[0] === '7') {
            return '+7('
                . substr($d, 1, 3) . ')'
                . substr($d, 4, 3) . '-'
                . substr($d, 7, 2) . '-'
                . substr($d, 9, 2);
        }

        if ($d[0] === '3') {
            return '+' . $d;
        }

        return '+' . $d;
    }

    elseif ($tip === 'phone_to_whatsapp') {
        // Оставляем текущую бизнес-логику: WhatsApp только для РФ мобильных 79XXXXXXXXX.
        $d = $phone_digits($txt);
        if ($d === '') return '';

        if (strlen($d) === 11 && $d[0] === '8') {
            $d = '7' . substr($d, 1);
        }
        return $d;
    }
    elseif ($tip=='data_to_db'){
        if (strstr($txt,'.')==true){
            if (strlen($txt)<=10){ //дата
                $date = DateTime::createFromFormat('d.m.Y', $txt);
                return $date->format('Y-m-d');            
            }else{ //дата-время
                $date = DateTime::createFromFormat('d.m.Y H:i:s', $txt);
                return $date->format('Y-m-d H:i:s'); 
            }
        }else{
            return $txt;
        }
    }
    elseif ($tip=='data_from_db'){
        if (strstr($txt,'-')==true){
            if (strlen($txt)<=10){ //дата
                $date = DateTime::createFromFormat('Y-m-d', $txt);
                return $date->format('d.m.Y');            
            }else{ //дата-время
                $date = DateTime::createFromFormat('Y-m-d H:i:s', $txt);
                return $date->format('d.m.Y H:i:s'); 
            }
        }else{
            return $txt;
        }
    }
    elseif ($tip=='price_to_db'){
        if (strstr($txt,'.')==true){
            $key=strpos($txt,'.');
            $txt=substr($txt,0,$key);
            $txt=preg_replace('/[\D]{1,}/s', '',$txt);
        }else{
            $txt=preg_replace('/[\D]{1,}/s', '',$txt);
        }
        return $txt;
    }
}



/**
 * Возвращает proxy-url изображения по id записи a_photo.
 * Используем ?com=i&id=... чтобы не зависеть от физического пути.
 *
 * @param int $photo_id
 * @return string
 */
function get_a_photo_proxy_url(int $photo_id): string {
    if ($photo_id <= 0) {
        return '';
    }

    return '?com=i&id=' . $photo_id;
}


/**
 * Получает фото текущей страницы структуры по типу фото и префиксу comments.
 *
 * Пример:
 * - event:
 * - gameplay:
 *
 * Если $comment_prefix пустой, вернет все фото указанного типа.
 *
 * @param array $struktura
 * @param int $cur_key
 * @param string $comment_prefix
 * @param string $tip
 * @return array
 */
function get_struktura_images_by_comment_prefix(array $struktura, int $cur_key, string $comment_prefix = '', string $tip = 'Прочее'): array {
    $result = array();

    if (!isset($struktura['img_id'][$cur_key]) || !is_array($struktura['img_id'][$cur_key])) {
        return $result;
    }

    $tip = trim($tip);
    $tip_l = $tip !== '' ? mb_strtolower($tip, 'UTF-8') : '';
    $prefix = trim($comment_prefix);
    $prefix_l = $prefix !== '' ? mb_strtolower($prefix, 'UTF-8') : '';

    foreach ($struktura['img_id'][$cur_key] as $img_index => $photo_id) {
        $photo_id = (int)$photo_id;
        if ($photo_id <= 0) {
            continue;
        }

        $photo_tip = isset($struktura['img_tip'][$cur_key][$img_index]) ? trim((string)$struktura['img_tip'][$cur_key][$img_index]) : '';
        $photo_comment = isset($struktura['img_comments'][$cur_key][$img_index]) ? trim((string)$struktura['img_comments'][$cur_key][$img_index]) : '';
        $photo_img = isset($struktura['img_img'][$cur_key][$img_index]) ? trim((string)$struktura['img_img'][$cur_key][$img_index]) : '';

        if ($tip_l !== '' && mb_strtolower($photo_tip, 'UTF-8') !== $tip_l) {
            continue;
        }

        $caption = $photo_comment;

        if ($prefix_l !== '') {
            if (mb_strtolower($photo_comment, 'UTF-8') === '') {
                continue;
            }

            if (mb_strtolower($photo_comment, 'UTF-8') !== $prefix_l && mb_stripos($photo_comment, $prefix) !== 0) {
                continue;
            }

            $caption = trim(substr($photo_comment, strlen($prefix)));
            $caption = ltrim($caption, ':;,. -');
        }

        $result[] = array(
            'id' => $photo_id,
            'img' => $photo_img,
            'tip' => $photo_tip,
            'comments' => $photo_comment,
            'caption' => $caption,
            'url' => get_a_photo_proxy_url($photo_id)
        );
    }

    return $result;
}
?>
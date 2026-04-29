<?php
/**
 * /shablon/_include/_market.php
 * Дополнительная индивидуальная логика корзины для разных проектов,
 * которая выполняется при регистрации и авторизации.
 *
 * Логика RimWorld Users API v2:
 * 1) Для RegisterUser получаем Access Token от суперюзера через /api/Users/v2/token.
 * 2) Отправляем RegisterUser с Authorization: Bearer SUPERUSER_TOKEN.
 * 3) ID игрока из ответа сохраняем в i_contr.passport.
 */

if (!function_exists('market_player_word')) {
    function market_player_word($name, $default = '')
    {
        $val = '';

        if (isset($_SESSION['s_words'][$name])) {
            $val = $_SESSION['s_words'][$name];
        }

        $val = html_entity_decode((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $val = strip_tags($val);
        $val = trim($val);

        if ($val === '') {
            $val = $default;
        }

        return $val;
    }
}


if (!function_exists('market_player_token_setting')) {
    function market_player_token_setting($field, $default = '')
    {
        $field = trim((string)$field);

        if ($field === '') {
            return $default;
        }

        $val = market_player_word('Market: игровой сервер: SuperUser Token ' . $field);
        if ($val !== '') {
            return $val;
        }

        return $default;
    }
}

if (!function_exists('market_player_log')) {
    function market_player_log($text, $data = array())
    {
        $root = '';
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $root = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\');
        } else {
            $root = dirname(__DIR__, 2);
        }

        $dir = $root . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $text;
        if (!empty($data)) {
            $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= "\n";

        @file_put_contents($dir . '/market_player_api.log', $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('market_player_clean_login')) {
    function market_player_clean_login($login, $i_contr_id = 0)
    {
        $login = html_entity_decode((string)$login, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $login = strip_tags($login);
        $login = trim($login);

        if (function_exists('ru_us')) {
            $login = ru_us($login);
        }

        $login = preg_replace('/[^a-zA-Z0-9_\-\.]+/u', '_', $login);
        $login = preg_replace('/[_\-\.]{2,}/', '_', $login);
        $login = trim($login, '_-.');

        if ($login === '') {
            $login = 'player' . (int)$i_contr_id;
        }

        if (strlen($login) < 3) {
            $login = 'player' . (int)$i_contr_id;
        }

        return substr($login, 0, 60);
    }
}

if (!function_exists('market_player_make_login')) {
    function market_player_make_login($i_contr_id, $name, $email)
    {
        $login = trim((string)$name);

        if ($login === '') {
            $email_parts = explode('@', (string)$email);
            $login = isset($email_parts[0]) ? $email_parts[0] : '';
        }

        return market_player_clean_login($login, $i_contr_id);
    }
}

if (!function_exists('market_player_response_id')) {
    function market_player_response_id($response)
    {
        $response = trim((string)$response);
        if ($response === '') {
            return '';
        }

        $decoded = json_decode($response, true);

        if (is_scalar($decoded) && trim((string)$decoded) !== '' && trim((string)$decoded) !== '0') {
            return trim((string)$decoded);
        }

        if (is_array($decoded)) {
            $keys = array('id', 'Id', 'ID', 'player_id', 'playerId', 'PlayerId', 'user_id', 'userId', 'UserId');

            foreach ($keys as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key]) && trim((string)$decoded[$key]) !== '' && trim((string)$decoded[$key]) !== '0') {
                    return trim((string)$decoded[$key]);
                }
            }

            foreach (array('data', 'result', 'user', 'player') as $parent_key) {
                if (isset($decoded[$parent_key]) && is_array($decoded[$parent_key])) {
                    foreach ($keys as $key) {
                        if (isset($decoded[$parent_key][$key]) && is_scalar($decoded[$parent_key][$key]) && trim((string)$decoded[$parent_key][$key]) !== '' && trim((string)$decoded[$parent_key][$key]) !== '0') {
                            return trim((string)$decoded[$parent_key][$key]);
                        }
                    }
                }
            }
        }

        $plain = trim($response, "\"' \t\n\r\0\x0B");
        if (preg_match('/^[0-9]+$/', $plain) && $plain !== '0') {
            return $plain;
        }

        if (preg_match('/["\']?(?:id|Id|ID|playerId|PlayerId|userId|UserId)["\']?\s*[:=]\s*["\']?([^"\',}\]\s]+)/u', $response, $m)) {
            $id = trim((string)$m[1]);
            if ($id !== '' && $id !== '0') {
                return $id;
            }
        }

        return '';
    }
}

if (!function_exists('market_player_current_passport')) {
    function market_player_current_passport($i_contr_id)
    {
        $i_contr_id = (int)$i_contr_id;
        if ($i_contr_id <= 0) {
            return '';
        }

        $res = _DB("SELECT passport FROM i_contr WHERE id = ? LIMIT 1", array($i_contr_id));
        if ($res === false) {
            return '';
        }

        $row = $res->fetch(PDO::FETCH_ASSOC);
        return isset($row['passport']) ? trim((string)$row['passport']) : '';
    }
}

if (!function_exists('market_player_token_cache_key')) {
    function market_player_token_cache_key($profile)
    {
        $profile = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', (string)$profile);
        $profile = trim($profile, '_-');

        if ($profile === '') {
            $profile = 'default';
        }

        return 'market_player_api_token_' . $profile;
    }
}

if (!function_exists('market_player_token_clear_cache')) {
    function market_player_token_clear_cache($profile = 'superuser')
    {
        $cache_key = market_player_token_cache_key('superuser');
        if (isset($_SESSION[$cache_key])) {
            unset($_SESSION[$cache_key]);
        }
    }
}

if (!function_exists('market_player_token_from_response')) {
    function market_player_token_from_response($response)
    {
        $result = array(
            'access_token' => '',
            'token_type' => 'Bearer',
            'expires_in' => 0
        );

        $response = trim((string)$response);
        if ($response === '') {
            return $result;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $token_keys = array('access_token', 'accessToken', 'token', 'Token');
            foreach ($token_keys as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key]) && trim((string)$decoded[$key]) !== '') {
                    $result['access_token'] = trim((string)$decoded[$key]);
                    break;
                }
            }

            if (isset($decoded['token_type']) && is_scalar($decoded['token_type']) && trim((string)$decoded['token_type']) !== '') {
                $result['token_type'] = trim((string)$decoded['token_type']);
            }

            if (isset($decoded['tokenType']) && is_scalar($decoded['tokenType']) && trim((string)$decoded['tokenType']) !== '') {
                $result['token_type'] = trim((string)$decoded['tokenType']);
            }

            if (isset($decoded['expires_in']) && is_numeric($decoded['expires_in'])) {
                $result['expires_in'] = (int)$decoded['expires_in'];
            }

            if (isset($decoded['expiresIn']) && is_numeric($decoded['expiresIn'])) {
                $result['expires_in'] = (int)$decoded['expiresIn'];
            }
        } else {
            // На случай если API вернет токен обычной строкой.
            $plain = trim($response, "\"' \t\n\r\0\x0B");
            if ($plain !== '' && strpos($plain, ' ') === false && strlen($plain) > 10) {
                $result['access_token'] = $plain;
            }
        }

        if ($result['token_type'] === '') {
            $result['token_type'] = 'Bearer';
        }

        return $result;
    }
}

if (!function_exists('market_player_get_access_token')) {
    function market_player_get_access_token($force = false)
    {
        $cache_key = market_player_token_cache_key('superuser');
        if (!$force && isset($_SESSION[$cache_key]) && is_array($_SESSION[$cache_key])) {
            $cached_token = isset($_SESSION[$cache_key]['access_token']) ? trim((string)$_SESSION[$cache_key]['access_token']) : '';
            $expires_at = isset($_SESSION[$cache_key]['expires_at']) ? (int)$_SESSION[$cache_key]['expires_at'] : 0;
            $token_type = isset($_SESSION[$cache_key]['token_type']) ? trim((string)$_SESSION[$cache_key]['token_type']) : 'Bearer';

            if ($cached_token !== '' && $expires_at > time() + 30) {
                return array(
                    'access_token' => $cached_token,
                    'token_type' => ($token_type !== '' ? $token_type : 'Bearer')
                );
            }
        }

        $token_url = market_player_token_setting('URL');
        if ($token_url === '') {
            return array('access_token' => '', 'token_type' => 'Bearer');
        }

        if (!function_exists('curl_init')) {
            market_player_log('Не удалось получить Access Token: PHP cURL не установлен');
            return array('access_token' => '', 'token_type' => 'Bearer');
        }

        $grant_type = market_player_token_setting('grant_type', 'password');
        $username = market_player_token_setting('username');
        $password = market_player_token_setting('password');
        $scope = market_player_token_setting('scope');
        $refresh_token = market_player_token_setting('refresh_token');
        $client_id = market_player_token_setting('client_id');
        $client_secret = market_player_token_setting('client_secret');

        if ($grant_type === '') {
            $grant_type = 'password';
        }

        $post = array(
            'grant_type' => $grant_type
        );

        if ($username !== '') {
            $post['username'] = $username;
        }
        if ($password !== '') {
            $post['password'] = $password;
        }
        if ($scope !== '') {
            $post['scope'] = $scope;
        }
        if ($refresh_token !== '') {
            $post['refresh_token'] = $refresh_token;
        }
        if ($client_id !== '') {
            $post['client_id'] = $client_id;
        }
        if ($client_secret !== '') {
            $post['client_secret'] = $client_secret;
        }

        $timeout = (int)market_player_word('Market: игровой сервер: timeout', '15');
        if ($timeout < 3) {
            $timeout = 15;
        }

        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post, '', '&'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $curl_error !== '') {
            market_player_log('Ошибка запроса Access Token', array(
                'profile' => 'superuser',
                'http_code' => $http_code,
                'curl_error' => $curl_error
            ));
            return array('access_token' => '', 'token_type' => 'Bearer');
        }

        if ($http_code < 200 || $http_code >= 300) {
            market_player_log('Access Token вернул неуспешный HTTP код', array(
                'profile' => 'superuser',
                'http_code' => $http_code,
                'username_set' => ($username !== '' ? 1 : 0),
                'scope' => $scope,
                'client_id_set' => ($client_id !== '' ? 1 : 0),
                'client_secret_set' => ($client_secret !== '' ? 1 : 0),
                'response' => mb_substr((string)$response, 0, 1000, 'UTF-8')
            ));
            return array('access_token' => '', 'token_type' => 'Bearer');
        }

        $token_data = market_player_token_from_response($response);
        if ($token_data['access_token'] === '') {
            market_player_log('Access Token не найден в ответе сервера', array(
                'profile' => 'superuser',
                'response' => mb_substr((string)$response, 0, 1000, 'UTF-8')
            ));
            return array('access_token' => '', 'token_type' => 'Bearer');
        }

        $expires_in = isset($token_data['expires_in']) ? (int)$token_data['expires_in'] : 0;
        if ($expires_in <= 0) {
            $expires_in = 1800;
        }

        $_SESSION[$cache_key] = array(
            'access_token' => $token_data['access_token'],
            'token_type' => $token_data['token_type'],
            'expires_at' => time() + $expires_in - 60
        );

        return array(
            'access_token' => $token_data['access_token'],
            'token_type' => $token_data['token_type']
        );
    }
}

if (!function_exists('market_player_api_headers')) {
    function market_player_api_headers($force_token = false)
    {
        $headers = array(
            'accept: text/plain',
            'Content-Type: application/json'
        );

        // Ручной Authorization из s_words имеет приоритет.
        $authorization = market_player_word('Market: игровой сервер: SuperUser Authorization');
        if ($authorization !== '') {
            if (stripos($authorization, 'Authorization:') === 0) {
                $headers[] = $authorization;
            } else {
                $headers[] = 'Authorization: ' . $authorization;
            }

            return $headers;
        }

        // Автоматическое получение Bearer token от SuperUser.
        if (market_player_token_setting('URL') !== '') {
            $token = market_player_get_access_token($force_token);
            $access_token = isset($token['access_token']) ? trim((string)$token['access_token']) : '';
            $token_type = isset($token['token_type']) ? trim((string)$token['token_type']) : 'Bearer';

            if ($access_token !== '') {
                if ($token_type === '') {
                    $token_type = 'Bearer';
                }
                $headers[] = 'Authorization: ' . $token_type . ' ' . $access_token;
            }
        }

        return $headers;
    }
}

if (!function_exists('market_player_register_request')) {
    function market_player_register_request($api_url, $json, $timeout, $force_token = false)
    {
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, market_player_api_headers($force_token));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return array(
            'response' => $response,
            'curl_error' => $curl_error,
            'http_code' => $http_code
        );
    }
}

if (!function_exists('market_player_register_on_server')) {
    function market_player_register_on_server($i_contr_id, $email, $password, $name = '', $phone = '', $telegram_id = 0)
    {
        $i_contr_id = (int)$i_contr_id;
        $email = trim((string)$email);
        $password = trim((string)$password);
        $name = trim((string)$name);
        $telegram_id = (int)$telegram_id;

        if ($i_contr_id <= 0) {
            return false;
        }

        if (market_player_current_passport($i_contr_id) !== '') {
            return true;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            market_player_log('Не удалось создать игрока: некорректный email', array('i_contr_id' => $i_contr_id, 'email' => $email));
            return false;
        }

        if ($password === '') {
            market_player_log('Не удалось создать игрока: пустой пароль', array('i_contr_id' => $i_contr_id, 'email' => $email));
            return false;
        }

        $api_url = market_player_word('Market: игровой сервер: RegisterUser URL');
        if ($api_url === '') {
            market_player_log('Не удалось создать игрока: не заполнена переменная s_words Market: игровой сервер: RegisterUser URL', array('i_contr_id' => $i_contr_id, 'email' => $email));
            return false;
        }

        if (!function_exists('curl_init')) {
            market_player_log('Не удалось создать игрока: PHP cURL не установлен', array('i_contr_id' => $i_contr_id, 'email' => $email));
            return false;
        }

        if (market_player_word('Market: игровой сервер: SuperUser Authorization') === '' && market_player_token_setting('URL') !== '') {
            $token = market_player_get_access_token(false);
            if (empty($token['access_token'])) {
                market_player_log('Не удалось создать игрока: не получен Access Token', array('i_contr_id' => $i_contr_id, 'email' => $email));
                return false;
            }
        }

        $version = (int)market_player_word('Market: игровой сервер: version', '0');
        $timeout = (int)market_player_word('Market: игровой сервер: timeout', '15');
        if ($timeout < 3) {
            $timeout = 15;
        }

        $login = market_player_make_login($i_contr_id, $name, $email);

        $payload = array(
            'login' => $login,
            'password' => $password,
            'email' => $email,
            'version' => $version,
            'discord_username' => $login,
            'telegram_id' => ($telegram_id > 0 ? (string)$telegram_id : '')
        );

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $request = market_player_register_request($api_url, $json, $timeout, false);
        $response = isset($request['response']) ? $request['response'] : '';
        $curl_error = isset($request['curl_error']) ? $request['curl_error'] : '';
        $http_code = isset($request['http_code']) ? (int)$request['http_code'] : 0;

        // Если токен протух или сервер его отклонил — очищаем кэш, получаем новый и пробуем один раз повторно.
        if ($http_code === 401 && market_player_word('Market: игровой сервер: SuperUser Authorization') === '' && market_player_token_setting('URL') !== '') {
            market_player_token_clear_cache('superuser');
            $request = market_player_register_request($api_url, $json, $timeout, true);
            $response = isset($request['response']) ? $request['response'] : '';
            $curl_error = isset($request['curl_error']) ? $request['curl_error'] : '';
            $http_code = isset($request['http_code']) ? (int)$request['http_code'] : 0;
        }

        if ($response === false || $curl_error !== '') {
            market_player_log('Ошибка запроса RegisterUser', array(
                'i_contr_id' => $i_contr_id,
                'email' => $email,
                'curl_error' => $curl_error,
                'http_code' => $http_code
            ));
            return false;
        }

        if ($http_code < 200 || $http_code >= 300) {
            market_player_log('RegisterUser вернул неуспешный HTTP код', array(
                'i_contr_id' => $i_contr_id,
                'email' => $email,
                'http_code' => $http_code,
                'token_url_set' => market_player_token_setting('URL') !== '' ? 1 : 0,
                'superuser_username_set' => market_player_token_setting('username') !== '' ? 1 : 0,
                'superuser_client_secret_set' => market_player_token_setting('client_secret') !== '' ? 1 : 0,
                'manual_authorization_set' => market_player_word('Market: игровой сервер: SuperUser Authorization') !== '' ? 1 : 0,
                'response' => mb_substr((string)$response, 0, 1000, 'UTF-8')
            ));
            return false;
        }

        $player_id = market_player_response_id($response);
        if ($player_id === '') {
            market_player_log('RegisterUser не вернул id игрока', array(
                'i_contr_id' => $i_contr_id,
                'email' => $email,
                'response' => mb_substr((string)$response, 0, 1000, 'UTF-8')
            ));
            return false;
        }

        $res = _DB("UPDATE i_contr SET passport = ?, data_change = NOW() WHERE id = ?", array($player_id, $i_contr_id));
        if ($res === false) {
            market_player_log('Не удалось записать id игрока в i_contr.passport', array(
                'i_contr_id' => $i_contr_id,
                'email' => $email,
                'player_id' => $player_id
            ));
            return false;
        }

        market_player_log('Игрок создан на сервере', array(
            'i_contr_id' => $i_contr_id,
            'email' => $email,
            'login' => $login,
            'player_id' => $player_id
        ));

        return true;
    }
}

if (!function_exists('market_player_register_by_user_id')) {
    function market_player_register_by_user_id($i_contr_id, $password = '')
    {
        $i_contr_id = (int)$i_contr_id;
        if ($i_contr_id <= 0) {
            return false;
        }

        if (market_player_current_passport($i_contr_id) !== '') {
            return true;
        }

        $sql = "SELECT name, email, phone, telegram_id
                FROM i_contr
                WHERE id = ?
                LIMIT 1";
        $res = _DB($sql, array($i_contr_id));
        if ($res === false) {
            return false;
        }

        $row = $res->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        return market_player_register_on_server(
            $i_contr_id,
            isset($row['email']) ? $row['email'] : '',
            $password,
            isset($row['name']) ? $row['name'] : '',
            isset($row['phone']) ? $row['phone'] : '',
            isset($row['telegram_id']) ? (int)$row['telegram_id'] : 0
        );
    }
}

if (isset($_t)){
    //////////////////// Регистрация пользователя

    if ($_t=='create_i_contr'){
    /**
    * $_t='create_i_contr'
    *
    * $i_contr_id
    * $email
    * $password
    * $name
    * $phone
    * $telegram_id
    */
        market_player_register_on_server(
            isset($i_contr_id) ? (int)$i_contr_id : 0,
            isset($email) ? $email : '',
            isset($password) ? $password : '',
            isset($name) ? $name : '',
            isset($phone) ? $phone : '',
            isset($telegram_id) ? (int)$telegram_id : 0
        );

    }
    if ($_t=='auth_i_contr'){
    /**
    * $_t='auth_i_contr'
    *
    * $i_contr_id
    * $email
    * $hash
    * $password
    */
        market_player_register_by_user_id(
            isset($i_contr_id) ? (int)$i_contr_id : 0,
            isset($password) ? $password : ''
        );

    }

    if ($_t=='market_password_change'){
    /**
    * Прочая логика смены пароля.
    * $_t='market_password_change'
    *
    * $i_contr_id
    * $email
    * $password
    * $password_md5
    */


    }
}

?>

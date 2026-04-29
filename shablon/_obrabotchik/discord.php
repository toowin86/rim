<?php
/**
 * /shablon/_obrabotchik/discord.php
 * Discord
 */
//print_rf($_SESSION["discord"]);
if (isset($_REQUEST['discord']) or _GP('com')=='discord'){

// bi-vpn.com — шлюз Discord OAuth

// ==== КОНФИГ ====
$CLIENT_ID      = '1439170258853167114';     // Discord Application
$CLIENT_SECRET  = '1NIBE6cByb08nVDrGkz1RdpVfP8x1eE0';     // Discord Application
$REDIRECT_URI   = 'https://bi-vpn.com/?com=discord'; // или  'https://bi-vpn.com/?com=discord' //https://bi-vpn.com/?discord // ДОЛЖЕН быть добавлен в Redirects в Discord dev portal
$BRIDGE_SECRET  = 'JHdns3KAs-Fas1D*01,(s'; // общий секрет для подписи пакета
$RETURN_URL_DEF = 'https://bionline-server.ru/?com=discord';    // дефолтный адрес возврата

// ==== УТИЛИТЫ ====
function b64url_encode($s) {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function b64url_decode($s) {
    return base64_decode(strtr($s, '-_', '+/'));
}
function sign_payload($payload, $secret) {
    return b64url_encode(hash_hmac('sha256', $payload, $secret, true));
}
function apiRequest($url, $post = null, $headers = array()) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_USERAGENT, 'bi-vpn Discord OAuth');
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    // таймауты
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_NOSIGNAL, true);
    // SSL
    $caPath = __DIR__ . '/cacert.pem'; // скачайте свежий https://curl.se/ca/cacert.pem
    if (file_exists($caPath)) curl_setopt($ch, CURLOPT_CAINFO, $caPath);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    if (defined('CURL_SSLVERSION_TLSv1_2')) curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    // заголовки
    $allHeaders = array('Accept: application/json');
    if (is_array($post)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        $allHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    foreach ($headers?:array() as $h) $allHeaders[] = $h;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $err  = curl_error($ch);
    $info = curl_getinfo($ch);
    //https://bi-vpn.com/?discord

    if ($errno) {
        $o = new stdClass(); $o->error='curl_error'; $o->error_description=$err; $o->curl_info=$info;
        return $o;
    }
    $json = json_decode($resp);
    if ($json === null) {
        $o = new stdClass(); $o->error='decode_failed'; $o->raw=$resp; $o->http=$info['http_code'];
        return $o;
    }
    return $json;
}




// 1) Первый заход — отправляем на Discord
if (!isset($_GET['code'])) {
    // можно принять кастомный return
    $returnUrl = isset($_GET['return']) ? $_GET['return'] : $RETURN_URL_DEF;

    // CSRF state
    $_SESSION['oauth_state']  = bin2hex(openssl_random_pseudo_bytes(16));
    $_SESSION['return_after'] = $returnUrl;

    $params = array(
        'client_id'     => $CLIENT_ID,
        'redirect_uri'  => $REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'identify email', // email опционально придет, если подтвержден
        'state'         => $_SESSION['oauth_state']
    );
    $authUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);
    header('Location: ' . $authUrl);
    exit;
}

// 2) Возврат от Discord — меняем code -> token, берём профиль, шифруем пакет и уходим на bionline
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('HTTP/1.1 400 Bad Request'); echo 'Bad state'; exit;
}
$returnUrl = isset($_SESSION['return_after']) ? $_SESSION['return_after'] : $RETURN_URL_DEF;

// 2.1 токен
$req = array(
    'client_id'     => $CLIENT_ID,
    'client_secret' => $CLIENT_SECRET,
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => $REDIRECT_URI
);
$token = apiRequest('https://discord.com/api/oauth2/token', $req);
if (isset($token->error) || empty($token->access_token)) {
    header('HTTP/1.1 502 Bad Gateway');
   //print_r($req);
    echo 'Token error'; exit;
}

// 2.2 профиль
$me = apiRequest('https://discord.com/api/users/@me', null, array('Authorization: Bearer '.$token->access_token));
if (isset($me->error)) { header('HTTP/1.1 502 Bad Gateway'); echo 'User fetch error'; exit; }

// нормализуем
$avatarUrl = (isset($me->id,$me->avatar) && $me->avatar)
    ? ('https://cdn.discordapp.com/avatars/'.$me->id.'/'.$me->avatar.'.png')
    : null;

$user = array(
    'id'           => isset($me->id) ? (string)$me->id : null,
    'username'     => isset($me->username) ? (string)$me->username : null,
    'global_name'  => isset($me->global_name) ? (string)$me->global_name : null,
    'discriminator'=> isset($me->discriminator) ? (string)$me->discriminator : null,
    'email'        => isset($me->email) ? (string)$me->email : null,
    'avatar'       => isset($me->avatar) ? (string)$me->avatar : null,
    'avatar_url'   => $avatarUrl
);

// 2.3 формируем короткоживущий подписанный пакет (без access_token)
$now = time();
$data = array(
    'iat'  => $now,
    'exp'  => $now + 120, // 2 минуты на доставку
    'user' => $user
);
$payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE);
$payloadB64  = b64url_encode($payloadJson);
$sigB64      = sign_payload($payloadB64, $BRIDGE_SECRET);

// 2.4 редирект на bionline с пакетом p и подписью s
$sep = (strpos($returnUrl, '?') === false) ? '?' : '&';
$redir = $returnUrl . $sep . http_build_query(array('p' => $payloadB64, 's' => $sigB64));
// очистим state
unset($_SESSION['oauth_state'], $_SESSION['return_after']);
header('Location: ' . $redir);

    exit;
}
?>
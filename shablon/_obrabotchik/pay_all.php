<?php
/**
 * /shablon/_obrabotchik/pay_all.php
 *
 * Центральный обработчик HTTP-уведомлений YooMoney.
 *
 * Логика работы:
 * 1. Принимаем POST от YooMoney.
 * 2. Проверяем обязательные поля.
 * 3. Проверяем sha1_hash по secret из настроек.
 * 4. Разбираем label формата: domain|order_id
 * 5. Отправляем отладочное письмо администратору
 *    (только если уведомление прошло валидацию).
 * 6. Форвардим тот же POST на нужный домен:
 *    https://site.ru/?com=pay_this
 * 7. Если форвард не удался — отдаём 500, чтобы YooMoney повторил отправку.
 */

header('Content-Type: text/plain; charset=UTF-8');

/**
 * Получение POST-поля как строки.
 * Если поля нет или пришёл массив — возвращаем пустую строку.
 */
function ym_post_value($key)
{
    return (isset($_POST[$key]) && !is_array($_POST[$key])) ? (string)$_POST[$key] : '';
}

/**
 * Расчёт контрольного sha1_hash по правилам YooMoney.
 */
function ym_calc_hash($notification_type, $operation_id, $amount, $currency, $datetime, $sender, $codepro, $secret, $label)
{
    $hash_string =
        $notification_type . '&' .
        $operation_id . '&' .
        $amount . '&' .
        $currency . '&' .
        $datetime . '&' .
        $sender . '&' .
        $codepro . '&' .
        $secret . '&' .
        $label;

    return sha1($hash_string);
}

/**
 * Нормализация домена:
 * - trim
 * - lower-case
 * - убираем порт
 */
function ym_normalize_host($host)
{
    $host = strtolower(trim((string)$host));
    $host = preg_replace('/:\d+$/', '', $host);
    return $host;
}

/**
 * Отправка debug-email администратору.
 * Ошибка отправки не должна ломать обработку уведомления.
 */
function ym_send_admin_debug_mail($subject, $body)
{
    $admin_email = isset($_SESSION['a_options']['email администратора'])
        ? trim((string)$_SESSION['a_options']['email администратора'])
        : '';

    if ($admin_email === '') {
        return false;
    }

    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    return send_mail_smtp($admin_email, $subject, $body);
}

/**
 * Папка логов.
 */
function ym_log_dir()
{
    return __DIR__ . '/../../logs';
}

/**
 * Подготовка значения для лога.
 */
function ym_log_prepare_value($value)
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value === null) {
        return 'null';
    }

    if (is_array($value) || is_object($value)) {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ($json !== false) ? $json : '[unjsonable]';
    }

    $value = (string)$value;
    $value = str_replace(array("\r", "\n", "\t"), ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim($value);
}

/**
 * Запись строки в лог pay_all.
 */
function ym_write_log($event, $context = array())
{
    $log_dir = ym_log_dir();
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $parts = array(
        '[' . date('Y-m-d H:i:s') . ']',
        'event=' . ym_log_prepare_value($event)
    );

    if (!empty($context) && is_array($context)) {
        foreach ($context as $key => $value) {
            $parts[] = $key . '=' . ym_log_prepare_value($value);
        }
    }

    @file_put_contents(
        $log_dir . '/pay_all.log',
        implode(' | ', $parts) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Универсальный выход с логированием.
 */
function ym_fail($http_code, $message, $context = array(), $send_mail = false, $mail_subject = '', $mail_body = '')
{
    ym_write_log($message, $context);

    if ($send_mail && $mail_subject !== '' && $mail_body !== '') {
        ym_send_admin_debug_mail($mail_subject, $mail_body);
    }

    http_response_code((int)$http_code);
    echo $message;
    exit;
}

$pay_all_host = '';
if (!empty($_SERVER['HTTP_HOST'])) {
    $pay_all_host = ym_normalize_host($_SERVER['HTTP_HOST']);
} elseif (!empty($_SERVER['SERVER_NAME'])) {
    $pay_all_host = ym_normalize_host($_SERVER['SERVER_NAME']);
}

/**
 * Разрешаем только POST.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ym_fail(405, 'METHOD NOT ALLOWED', array(
        'host' => $pay_all_host,
        'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : ''
    ));
}

/**
 * Secret для проверки уведомления YooMoney.
 * Должен быть одинаково настроен на центральном сайте и на доменах-получателях.
 */
$notification_secret = isset($_SESSION['a_options']['Market: оплата: secret HTTP-уведомлений yoomoney'])
    ? trim((string)$_SESSION['a_options']['Market: оплата: secret HTTP-уведомлений yoomoney'])
    : '';

if ($notification_secret === '') {
    ym_fail(500, 'SECRET NOT CONFIGURED', array(
        'host' => $pay_all_host
    ));
}

/**
 * Считываем все основные поля уведомления.
 */
$notification_type = ym_post_value('notification_type');
$operation_id      = ym_post_value('operation_id');
$amount            = ym_post_value('amount');
$withdraw_amount   = ym_post_value('withdraw_amount');
$currency          = ym_post_value('currency');
$datetime          = ym_post_value('datetime');
$sender            = ym_post_value('sender');
$codepro           = ym_post_value('codepro');
$label             = ym_post_value('label');
$sha1_hash         = ym_post_value('sha1_hash');
$unaccepted        = ym_post_value('unaccepted');

ym_write_log('PAY_ALL REQUEST', array(
    'host' => $pay_all_host,
    'notification_type' => $notification_type,
    'operation_id' => $operation_id,
    'amount' => $amount,
    'withdraw_amount' => $withdraw_amount,
    'currency' => $currency,
    'datetime' => $datetime,
    'sender' => $sender,
    'codepro' => $codepro,
    'label' => $label,
    'unaccepted' => $unaccepted,
    'remote_addr' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
    'post' => $_POST
));

/**
 * Базовая проверка обязательных полей.
 */
if (
    $notification_type === '' ||
    $operation_id === '' ||
    $amount === '' ||
    $currency === '' ||
    $datetime === '' ||
    $codepro === '' ||
    $sha1_hash === ''
) {
    ym_fail(400, 'BAD REQUEST', array(
        'host' => $pay_all_host,
        'operation_id' => $operation_id,
        'label' => $label,
        'post' => $_POST
    ));
}

/**
 * Для рублей РФ YooMoney присылает currency=643.
 */
if ($currency !== '643') {
    ym_fail(400, 'BAD CURRENCY', array(
        'host' => $pay_all_host,
        'operation_id' => $operation_id,
        'label' => $label,
        'currency' => $currency
    ));
}

/**
 * Не принимаем удержанные/неподтверждённые платежи.
 */
if ($unaccepted === 'true') {
    ym_fail(400, 'UNACCEPTED PAYMENT', array(
        'host' => $pay_all_host,
        'operation_id' => $operation_id,
        'label' => $label
    ));
}

/**
 * Повторно считаем hash и сравниваем с тем, что прислал YooMoney.
 */
$hash_local = ym_calc_hash(
    $notification_type,
    $operation_id,
    $amount,
    $currency,
    $datetime,
    $sender,
    $codepro,
    $notification_secret,
    $label
);

/**
 * label ожидается в формате:
 * domain|order_id
 * пример:
 * 23go.ru|123
 */
$label_parts = explode('|', $label, 2);
$target_domain = isset($label_parts[0]) ? ym_normalize_host($label_parts[0]) : '';
$order_id = isset($label_parts[1]) ? (int)$label_parts[1] : 0;

if ($target_domain === '' || $order_id <= 0) {
    ym_fail(400, 'BAD LABEL', array(
        'host' => $pay_all_host,
        'operation_id' => $operation_id,
        'label' => $label,
        'target_domain' => $target_domain,
        'order_id' => $order_id
    ));
}

/**
 * Дополнительная защита: домен только из безопасного набора символов.
 */
if (!preg_match('/^[a-z0-9.-]+$/', $target_domain)) {
    ym_fail(400, 'BAD DOMAIN', array(
        'host' => $pay_all_host,
        'operation_id' => $operation_id,
        'label' => $label,
        'target_domain' => $target_domain
    ));
}

/**
 * Сюда будет переслано валидное уведомление.
 */
$target_url = 'https://' . $target_domain . '/?com=pay_this';

$debug_mail_subject = 'YooMoney: платеж ' . $pay_all_host;
$debug_mail_body = ''
    . '<h2>Валидное уведомление YooMoney получено на pay_all</h2>'
    . '<p><b>Центральный домен:</b> ' . htmlspecialchars($pay_all_host, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>Целевой домен:</b> ' . htmlspecialchars($target_domain, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>Заказ:</b> #' . (int)$order_id . '</p>'
    . '<p><b>Сумма amount:</b> ' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>Сумма withdraw_amount:</b> ' . htmlspecialchars($withdraw_amount, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>Операция YooMoney:</b> ' . htmlspecialchars($operation_id, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>Тип уведомления:</b> ' . htmlspecialchars($notification_type, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>Дата/время:</b> ' . htmlspecialchars($datetime, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>Отправитель:</b> ' . htmlspecialchars($sender, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>currency:</b> ' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>codepro:</b> ' . htmlspecialchars($codepro, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>label:</b> ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>sha1_hash (YooMoney):</b> ' . htmlspecialchars($sha1_hash, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>sha1_hash (local):</b> ' . htmlspecialchars($hash_local, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><b>URL форварда:</b> ' . htmlspecialchars($target_url, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<hr>'
    . '<p><b>RAW POST:</b></p>'
    . '<pre style="white-space:pre-wrap;">' . htmlspecialchars(print_r($_POST, true), ENT_QUOTES, 'UTF-8') . '</pre>';

if (!hash_equals($hash_local, $sha1_hash)) {
    ym_fail(
        400,
        'BAD HASH',
        array(
            'host' => $pay_all_host,
            'target_domain' => $target_domain,
            'target_url' => $target_url,
            'order_id' => $order_id,
            'operation_id' => $operation_id,
            'label' => $label,
            'sha1_hash_yoomoney' => $sha1_hash,
            'sha1_hash_local' => $hash_local
        ),
        true,
        $debug_mail_subject . ' !BAD HASH',
        '<h1>ERROR: BAD HASH</h1>' . $debug_mail_body
    );
}

ym_write_log('PAY_ALL VALIDATED', array(
    'host' => $pay_all_host,
    'target_domain' => $target_domain,
    'target_url' => $target_url,
    'order_id' => $order_id,
    'operation_id' => $operation_id,
    'label' => $label
));

ym_send_admin_debug_mail($debug_mail_subject, $debug_mail_body);

/**
 * Форвардим исходный POST на домен из label.
 * На целевом домене pay_this.php ещё раз сам проверит hash.
 */
$forward_data = $_POST;
$forward_data['pay_all_forwarded'] = '1';
$forward_data['pay_all_host'] = $pay_all_host;

ym_write_log('PAY_ALL FORWARD START', array(
    'host' => $pay_all_host,
    'target_url' => $target_url,
    'target_domain' => $target_domain,
    'order_id' => $order_id,
    'operation_id' => $operation_id
));

/**
 * Отправляем POST на целевой домен.
 * curl_close намеренно не вызываю.
 */
$ch = curl_init($target_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($forward_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response_body = curl_exec($ch);
$response_http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$response_err  = curl_error($ch);

/**
 * Если форвард не удался — логируем.
 * И отдаём 500, чтобы YooMoney повторил уведомление позже.
 */
if ($response_body === false || $response_http !== 200) {
    ym_fail(500, 'FORWARD ERROR', array(
        'host' => $pay_all_host,
        'target_url' => $target_url,
        'target_domain' => $target_domain,
        'order_id' => $order_id,
        'operation_id' => $operation_id,
        'response_http' => $response_http,
        'curl_error' => $response_err,
        'response_body' => is_string($response_body) ? $response_body : ''
    ));
}

ym_write_log('PAY_ALL FORWARD OK', array(
    'host' => $pay_all_host,
    'target_url' => $target_url,
    'target_domain' => $target_domain,
    'order_id' => $order_id,
    'operation_id' => $operation_id,
    'response_http' => $response_http,
    'response_body' => is_string($response_body) ? $response_body : ''
));

/**
 * Всё ок:
 * - уведомление валидно
 * - письмо админу отправлено/попытка сделана
 * - форвард на целевой домен успешный
 */
ym_write_log('PAY_ALL OK', array(
    'host' => $pay_all_host,
    'target_domain' => $target_domain,
    'order_id' => $order_id,
    'operation_id' => $operation_id
));

http_response_code(200);
echo 'OK';
exit;

<?php
/**
 * /shablon/_obrabotchik/pay_this.php
 * Локальный обработчик платежа на конкретном домене.
 */

header('Content-Type: text/plain; charset=UTF-8');

/**
 * Безопасное получение POST-поля.
 */
function ym_post_value($key)
{
    return (isset($_POST[$key]) && !is_array($_POST[$key])) ? (string)$_POST[$key] : '';
}

/**
 * Расчёт sha1_hash по правилам YooMoney.
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
 * Нормализация домена.
 */
function ym_normalize_host($host)
{
    $host = strtolower(trim((string)$host));
    $host = preg_replace('/:\d+$/', '', $host);
    return $host;
}

/**
 * Отправка письма админу об ошибке.
 */
function pay_this_send_admin_error_mail($subject, $body)
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
 * Универсальный выход с ошибкой:
 * - шлёт email админу
 * - отдаёт HTTP code
 * - завершает скрипт
 */
function pay_this_fail($http_code, $message, $debug = array())
{
    $current_host = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $current_host = ym_normalize_host($_SERVER['HTTP_HOST']);
    } elseif (!empty($_SERVER['SERVER_NAME'])) {
        $current_host = ym_normalize_host($_SERVER['SERVER_NAME']);
    }

    $operation_id = ym_post_value('operation_id');
    $label = ym_post_value('label');
    $amount = ym_post_value('amount');
    $withdraw_amount = ym_post_value('withdraw_amount');
    $notification_type = ym_post_value('notification_type');
    $datetime = ym_post_value('datetime');

    $mail_subject = 'YooMoney pay_this ERROR: ' . $message;

    $mail_body = ''
        . '<h2>Ошибка обработки платежа в pay_this.php</h2>'
        . '<p><b>Домен:</b> ' . htmlspecialchars($current_host, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>Ошибка:</b> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>HTTP code:</b> ' . (int)$http_code . '</p>'
        . '<p><b>notification_type:</b> ' . htmlspecialchars($notification_type, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>operation_id:</b> ' . htmlspecialchars($operation_id, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>label:</b> ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>amount:</b> ' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>withdraw_amount:</b> ' . htmlspecialchars($withdraw_amount, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>datetime:</b> ' . htmlspecialchars($datetime, ENT_QUOTES, 'UTF-8') . '</p>';

    if (!empty($debug)) {
        $mail_body .= '<hr><p><b>DEBUG:</b></p><pre style="white-space:pre-wrap;">'
            . htmlspecialchars(print_r($debug, true), ENT_QUOTES, 'UTF-8')
            . '</pre>';
    }

    $mail_body .= '<hr><p><b>RAW POST:</b></p><pre style="white-space:pre-wrap;">'
        . htmlspecialchars(print_r($_POST, true), ENT_QUOTES, 'UTF-8')
        . '</pre>';

    pay_this_send_admin_error_mail($mail_subject, $mail_body);

    http_response_code((int)$http_code);
    echo $message;
    exit;
}

/**
 * Разрешаем только POST.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pay_this_fail(405, 'METHOD NOT ALLOWED');
}

/**
 * Secret для проверки уведомления YooMoney.
 */
$notification_secret = isset($_SESSION['a_options']['Market: оплата: secret HTTP-уведомлений yoomoney'])
    ? trim((string)$_SESSION['a_options']['Market: оплата: secret HTTP-уведомлений yoomoney'])
    : '';

if ($notification_secret === '') {
    pay_this_fail(500, 'SECRET NOT CONFIGURED');
}

/**
 * Считываем поля уведомления.
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

if (
    $notification_type === '' ||
    $operation_id === '' ||
    $amount === '' ||
    $currency === '' ||
    $datetime === '' ||
    $codepro === '' ||
    $label === '' ||
    $sha1_hash === ''
) {
    pay_this_fail(400, 'BAD REQUEST');
}

if ($currency !== '643') {
    pay_this_fail(400, 'BAD CURRENCY', array('currency' => $currency));
}

if ($unaccepted === 'true') {
    pay_this_fail(400, 'UNACCEPTED PAYMENT');
}

/**
 * Проверяем hash уведомления.
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

if (!hash_equals($hash_local, $sha1_hash)) {
    pay_this_fail(400, 'BAD HASH', array(
        'sha1_hash_yoomoney' => $sha1_hash,
        'sha1_hash_local' => $hash_local
    ));
}

/**
 * label формат:
 * domain|order_id
 */
$label_parts = explode('|', $label, 2);
$label_domain = isset($label_parts[0]) ? ym_normalize_host($label_parts[0]) : '';
$order_id = isset($label_parts[1]) ? (int)$label_parts[1] : 0;

if ($label_domain === '' || $order_id <= 0) {
    pay_this_fail(400, 'BAD LABEL', array(
        'label' => $label
    ));
}

$current_host = '';
if (!empty($_SERVER['HTTP_HOST'])) {
    $current_host = ym_normalize_host($_SERVER['HTTP_HOST']);
} elseif (!empty($_SERVER['SERVER_NAME'])) {
    $current_host = ym_normalize_host($_SERVER['SERVER_NAME']);
}

if ($current_host === '' || $label_domain !== $current_host) {
    pay_this_fail(400, 'DOMAIN MISMATCH', array(
        'label_domain' => $label_domain,
        'current_host' => $current_host
    ));
}

/**
 * Для закрытия заказа логичнее брать withdraw_amount,
 * если он есть, иначе amount.
 */
$payment_sum = 0;
if ($withdraw_amount !== '' && is_numeric($withdraw_amount)) {
    $payment_sum = (float)$withdraw_amount;
} elseif (is_numeric($amount)) {
    $payment_sum = (float)$amount;
}

if ($payment_sum <= 0) {
    pay_this_fail(400, 'BAD AMOUNT', array(
        'amount' => $amount,
        'withdraw_amount' => $withdraw_amount
    ));
}

$payment_date = date('Y-m-d H:i:s');
if ($datetime !== '') {
    $payment_ts = strtotime($datetime);
    if ($payment_ts !== false) {
        $payment_date = date('Y-m-d H:i:s', $payment_ts);
    }
}

$payment_comment = 'YooMoney operation_id=' . $operation_id;

/**
 * Ищем заказ и email клиента.
 */
$res_order = _DB(
    "SELECT mz.id, mz.project_name, mz.i_contr_id, ic.email
     FROM m_zakaz mz
     LEFT JOIN i_contr ic ON ic.id = mz.i_contr_id
     WHERE mz.id = ?
     LIMIT 1",
    array($order_id)
);

if ($res_order === false) {
    pay_this_fail(500, 'DB ERROR: ORDER SELECT', array(
        'order_id' => $order_id
    ));
}

$row_order = $res_order->fetch(PDO::FETCH_ASSOC);
if (!$row_order) {
    pay_this_fail(404, 'ORDER NOT FOUND', array(
        'order_id' => $order_id
    ));
}

/**
 * Проверяем, не был ли этот платеж уже записан.
 * Если комментарий полностью совпал — это дубль, сразу выходим.
 */
$res_exists = _DB(
    "SELECT id
     FROM m_platezi
     WHERE a_menu_id = 16
       AND comments = ?
     LIMIT 1",
    array($payment_comment)
);

if ($res_exists === false) {
    pay_this_fail(500, 'DB ERROR: DUPLICATE CHECK', array(
        'payment_comment' => $payment_comment
    ));
}

$row_exists = $res_exists->fetch(PDO::FETCH_ASSOC);
if ($row_exists) {
    http_response_code(200);
    echo 'OK';
    exit;
}

/**
 * Пишем платеж в m_platezi.
 */
$res_insert = _DB(
    "INSERT INTO m_platezi (data, summa, tip, a_menu_id, id_z_p_p, comments, data_create)
     VALUES (?, ?, 'Кредит', 16, ?, ?, NOW())",
    array($payment_date, $payment_sum, $order_id, $payment_comment)
);

if ($res_insert === false) {
    pay_this_fail(500, 'INSERT ERROR', array(
        'payment_date' => $payment_date,
        'payment_sum' => $payment_sum,
        'order_id' => $order_id,
        'payment_comment' => $payment_comment
    ));
}

/**
 * Прочая логика.
 */
$pay_this_logic_file = __DIR__ . '/../_include/_pay_this_logic.php';
if (!is_file($pay_this_logic_file)) {
    $pay_this_logic_file = __DIR__ . '/shablon/_include/_pay_this_logic.php';
}
if (!is_file($pay_this_logic_file)) {
    pay_this_fail(500, 'PAY THIS LOGIC FILE NOT FOUND');
}
require $pay_this_logic_file;


/**
 * Письмо клиенту.
 */
$client_email = isset($row_order['email']) ? trim((string)$row_order['email']) : '';
$project_name = isset($row_order['project_name']) && trim((string)$row_order['project_name']) !== ''
    ? trim((string)$row_order['project_name'])
    : ('Заказ №' . $order_id);

if ($client_email !== '' && filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
    $mail_subject = 'Оплата получена: ' . $project_name;
    $mail_body = ''
        . '<h2>Оплата успешно получена</h2>'
        . '<p><b>Заказ:</b> ' . htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><b>Номер заказа:</b> ' . (int)$order_id . '</p>'
        . '<p><b>Сумма оплаты:</b> ' . number_format($payment_sum, 2, '.', ' ') . ' ₽</p>'
        . '<p><b>Операция YooMoney:</b> ' . htmlspecialchars($operation_id, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Спасибо! Ваш платеж подтвержден.</p>';

    $mail_ok = send_mail_smtp($client_email, $mail_subject, $mail_body);

    if (!$mail_ok) {
        pay_this_send_admin_error_mail(
            'YooMoney pay_this WARNING: CLIENT EMAIL SEND FAILED',
            ''
            . '<h2>Платеж обработан, но письмо клиенту не отправилось</h2>'
            . '<p><b>Заказ:</b> #' . (int)$order_id . '</p>'
            . '<p><b>Проект:</b> ' . htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><b>Email клиента:</b> ' . htmlspecialchars($client_email, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><b>operation_id:</b> ' . htmlspecialchars($operation_id, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><b>Сумма:</b> ' . number_format($payment_sum, 2, '.', ' ') . ' ₽</p>'
        );
    }
}

/**
 * Успешное завершение.
 */
http_response_code(200);
echo 'OK';
exit;

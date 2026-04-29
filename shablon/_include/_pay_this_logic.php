<?php
/**
 * /shablon/_include/_pay_this_logic.php
 * Внутренняя дополнительная логика для pay_this.php.
 * Файл специально выполнен без функций — последовательным кодом.
 */

// Нельзя открывать напрямую через ?com=pay_this_logic
if (isset($com) && (string)$com === 'pay_this_logic') {
    if (function_exists('err_404')) {
        err_404('Внутренний файл недоступен для прямого вызова', 'com | internal');
    }

    header('HTTP/1.1 404 Not Found', true, 404);
    exit;
}

// Защита от случайного прямого include вне pay_this.php
if (
    !isset($order_id) ||
    !isset($payment_comment) ||
    !isset($payment_sum) ||
    !function_exists('pay_this_fail')
) {
    return;
}


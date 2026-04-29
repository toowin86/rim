<?php

/**
 * /shablon/_obrabotchik/i.php
 *
 * Быстрый отдающий прокси для изображений без постобработки.
 * URL: https://site.ru/?com=i&id=111
 *
 * Цели:
 * - скрыть реальный путь к файлу
 * - максимальная скорость (304, кэш браузера, минимум DB)
 * - безопасность (path traversal)
 *
 * Реальный путь: /i/[a_menu.inc]/original/[a_photo.img]
 *
 * Особенности:
 * - APCu кеширует ТОЛЬКО path + mime (без size/mtime, чтобы не ломать Content-Length)
 * - если path устарел (файл заменили/переименовали) -> сброс APCu и 1 повторный запрос в БД
 * - mtime/size берём через fstat() по открытому файлу (защита от гонок)
 * - 304 по If-None-Match и If-Modified-Since
 * - max-age=86400 (1 сутки), без immutable (картинки могут меняться)
 */

declare(strict_types=1);

// -------------------- 0) Валидация входа --------------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    exit;
}

// -------------------- 1) Не блокируем сессию --------------------
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

// -------------------- 2) Снимаем буферы --------------------
while (ob_get_level() > 0) {
    @ob_end_clean();
}

$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") : '';
if ($docRoot === '') {
    http_response_code(404);
    exit;
}

// -------------------- 3) APCu: пробуем взять meta --------------------
$cacheKey = 'img_meta_v4_' . $id;
$meta = null;

if (function_exists('apcu_fetch')) {
    $ok = false;
    $tmp = apcu_fetch($cacheKey, $ok);
    if ($ok && is_array($tmp) && !empty($tmp['path'])) {
        $meta = $tmp;
    }
}

// -------------------- 4) Функция загрузки meta из БД --------------------
$loadMetaFromDb = function () use ($id, $docRoot): ?array {
    $sql = "
        SELECT p.img, m.inc
        FROM a_photo p
        INNER JOIN a_menu m ON m.id = p.a_menu_id
        WHERE p.id = ?
        LIMIT 1
    ";
    $res = _DB($sql, [$id]);
    if (!$res) {
        return null;
    }

    $row = $res->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['img']) || empty($row['inc'])) {
        return null;
    }

    // Path traversal protection
    $img = basename((string)$row['img']);
    $inc = preg_replace('~[^a-zA-Z0-9_-]+~', '', (string)$row['inc']);

    if ($img === '' || $inc === '') {
        return null;
    }

    $path = $docRoot . '/i/' . $inc . '/original/' . $img;

    // MIME по расширению (быстро)
    $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    switch ($ext) {
        case 'jpg':
        case 'jpeg': $mime = 'image/jpeg'; break;
        case 'png':  $mime = 'image/png'; break;
        case 'gif':  $mime = 'image/gif'; break;
        case 'webp': $mime = 'image/webp'; break;
        case 'svg':  $mime = 'image/svg+xml'; break;
        case 'bmp':  $mime = 'image/bmp'; break;
        case 'ico':  $mime = 'image/x-icon'; break;
        case 'avif': $mime = 'image/avif'; break;
        default:
            // fallback (чуть медленнее, но только для редких расширений)
            if (function_exists('finfo_open') && is_file($path)) {
                $fi = @finfo_open(FILEINFO_MIME_TYPE);
                if ($fi) {
                    $det = @finfo_file($fi, $path);
                    @finfo_close($fi);
                    if (is_string($det) && $det !== '') {
                        $mime = $det;
                    }
                }
            }
        break;
    }

    return [
        'path' => $path,
        'mime' => $mime,
    ];
};

// -------------------- 5) Достаём meta (APCu -> DB), с 1 ретраем --------------------
for ($attempt = 0; $attempt < 2; $attempt++) {

    if (!is_array($meta)) {
        $meta = $loadMetaFromDb();
        if (!is_array($meta)) {
            http_response_code(404);
            exit;
        }

        if (function_exists('apcu_store')) {
            @apcu_store($cacheKey, $meta, 3600);
        }
    }

    $path = (string)$meta['path'];

    // Если path устарел (файл переименовали/заменили) — сбрасываем APCu и перечитываем БД один раз
    if (!is_file($path)) {
        if ($attempt === 0 && function_exists('apcu_delete')) {
            @apcu_delete($cacheKey);
            $meta = null;
            continue;
        }
        http_response_code(404);
        exit;
    }

    // -------------------- 6) Открываем файл и берём актуальные st (fstat) --------------------
    $fp = @fopen($path, 'rb');
    if ($fp === false) {
        if ($attempt === 0 && function_exists('apcu_delete')) {
            @apcu_delete($cacheKey);
            $meta = null;
            continue;
        }
        http_response_code(404);
        exit;
    }

    $st = @fstat($fp);
    if (!$st || !isset($st['mtime'], $st['size'])) {
        @fclose($fp);
        http_response_code(404);
        exit;
    }

    $mtime = (int)$st['mtime'];
    $size  = (int)$st['size'];

    $etag = dechex($mtime) . '-' . dechex($size);
    $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

    // Общие заголовки кэширования
    $cacheControl = 'public, max-age=86400';

    // -------------------- 7) 304 по If-None-Match --------------------
    if (!empty($_SERVER['HTTP_IF_NONE_MATCH'])) {
        $inm = trim((string)$_SERVER['HTTP_IF_NONE_MATCH']);
        $inm = trim($inm, "\" \t\n\r\0\x0B");
        if (strpos($inm, 'W/') === 0) { // на случай weak etag
            $inm = trim(substr($inm, 2), "\" \t\n\r\0\x0B");
        }

        if ($inm === $etag) {
            header('ETag: "' . $etag . '"');
            header('Last-Modified: ' . $lastModified);
            header('Cache-Control: ' . $cacheControl);
            http_response_code(304);
            @fclose($fp);
            exit;
        }
    }

    // -------------------- 8) 304 по If-Modified-Since --------------------
    if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $ims = strtotime((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']);
        if ($ims !== false && $ims >= $mtime) {
            header('ETag: "' . $etag . '"');
            header('Last-Modified: ' . $lastModified);
            header('Cache-Control: ' . $cacheControl);
            http_response_code(304);
            @fclose($fp);
            exit;
        }
    }

    // -------------------- 9) Заголовки для 200 --------------------
    header('Content-Type: ' . ($meta['mime'] ?? 'application/octet-stream'));
    header('Content-Length: ' . $size);
    header('ETag: "' . $etag . '"');
    header('Last-Modified: ' . $lastModified);
    header('Cache-Control: ' . $cacheControl);

    // -------------------- 10) Отдача --------------------
    @fpassthru($fp);
    @fclose($fp);
    exit;
}

// На всякий случай
http_response_code(404);
exit;
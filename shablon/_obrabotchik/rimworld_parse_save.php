<?php
/**
 * /shablon/_obrabotchik/rimworld_parse_save.php
 *
 * Тестовая версия без FTP и без перебора пользователей.
 * Берёт файл /1.dat1 из корня сайта, парсит пешек игрока и пишет их в i_contr_colonists.
 *
 * Запуск:
 * https://site.ru/?com=rimworld_parse_save&i_contr_id=1
 *
 * Принудительно пересоздать записи:
 * https://site.ru/?com=rimworld_parse_save&i_contr_id=1&force=1
 */

ignore_user_abort(true);
set_time_limit(0);
ini_set('memory_limit', '1024M');

header('Content-Type: application/json; charset=UTF-8');
$RIM_TIME_START = microtime(true);

$RIM_ROOT = dirname(__DIR__, 2);
$RIM_SAVE_FILE = '';
$RIM_SAVE_REMOTE_FILE = '';
$RIM_PLAYER_NAME = '';

$RIM_LOG_DIR = $RIM_ROOT . '/logs';
$RIM_TMP_DIR = $RIM_ROOT . '/tmp/rimworld_saves';

if (!is_dir($RIM_LOG_DIR)) {
    @mkdir($RIM_LOG_DIR, 0755, true);
}

if (!is_dir($RIM_TMP_DIR)) {
    @mkdir($RIM_TMP_DIR, 0755, true);
}

$i_contr_id = isset($_GET['i_contr_id']) ? (int)$_GET['i_contr_id'] : 0;
$force = (isset($_GET['force']) && (string)$_GET['force'] === '1');

if ($i_contr_id <= 0) {
    rimworld_json_exit(array(
        'status' => 'error',
        'message' => 'Не указан i_contr_id'
    ));
}



if (!class_exists('XMLReader')) {
    rimworld_json_exit(array(
        'status' => 'error',
        'message' => 'На сервере не включен XMLReader'
    ));
}

if (!class_exists('ZipArchive')) {
    rimworld_json_exit(array(
        'status' => 'error',
        'message' => 'На сервере не включен ZipArchive'
    ));
}

$lock_file = $RIM_TMP_DIR . '/rimworld_parse_save_' . $i_contr_id . '.lock';
$lock_fp = fopen($lock_file, 'c');

if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB)) {
    rimworld_json_exit(array(
        'status' => 'busy',
        'message' => 'Скрипт уже запущен'
    ));
}

$result = array(
    'status' => 'ok',
    'i_contr_id' => $i_contr_id,
    'player_name' => '',
    'file' => '',
    'remote_file' => '',
    'local_file' => '',
    'save_mtime_ts' => 0,
    'colonists' => 0,
    'inserted_or_updated' => 0,
    'deactivated' => 0,
    'execution_time_sec' => 0,
    'execution_time' => '',
    'message' => ''
);

try {
    rimworld_ensure_table();

    $player_row = rimworld_get_i_contr_player($i_contr_id);
    $RIM_PLAYER_NAME = $player_row['name'];

    $save_info = rimworld_download_player_save_from_ftp($i_contr_id, $RIM_PLAYER_NAME, $RIM_TMP_DIR);

    $RIM_SAVE_FILE = $save_info['local_file'];
    $RIM_SAVE_REMOTE_FILE = $save_info['remote_file'];
    $save_mtime_ts = (int)$save_info['mtime'];

    $result['player_name'] = $RIM_PLAYER_NAME;
    $result['file'] = basename($RIM_SAVE_REMOTE_FILE);
    $result['remote_file'] = $RIM_SAVE_REMOTE_FILE;
    $result['local_file'] = $RIM_SAVE_FILE;
    $result['save_mtime_ts'] = $save_mtime_ts;

    if (!$force && rimworld_is_save_already_processed($i_contr_id, $save_mtime_ts)) {
        $result['status'] = 'skipped';
        $result['message'] = 'Файл уже обработан, дата изменения не изменилась. Для повторного запуска добавь &force=1';
        rimworld_log('Файл пропущен, mtime не изменился', array(
            'i_contr_id' => $i_contr_id,
            'file' => $RIM_SAVE_FILE,
            'execution_time_sec' => rimworld_execution_time_sec()
        ));
    } else {
        $save_data = rimworld_parse_save_full($RIM_SAVE_FILE);

        $colonists = $save_data['colonists'];
        $game_data = $save_data['game_data'];
        
        $save_result = rimworld_save_colonists($i_contr_id, basename($RIM_SAVE_REMOTE_FILE), $save_mtime_ts, $colonists);
        rimworld_save_i_contr_game_data($i_contr_id, $game_data);
        
        $result['colonists'] = count($colonists);
        $result['inserted_or_updated'] = $save_result['inserted_or_updated'];
        $result['deactivated'] = $save_result['deactivated'];
        $result['game_data'] = $game_data;
        $result['message'] = 'Сейв обработан';

        rimworld_log('Сейв обработан', array(
            'i_contr_id' => $i_contr_id,
            'player_name' => $RIM_PLAYER_NAME,
            'remote_file' => $RIM_SAVE_REMOTE_FILE,
            'local_file' => $RIM_SAVE_FILE,
            'colonists' => count($colonists),
            'execution_time_sec' => rimworld_execution_time_sec()
        ));
    }
} catch (Throwable $e) {
    $result['status'] = 'error';
    $result['message'] = $e->getMessage();

    rimworld_log('Ошибка обработки сейва', array(
        'i_contr_id' => $i_contr_id,
        'player_name' => $RIM_PLAYER_NAME,
        'remote_file' => $RIM_SAVE_REMOTE_FILE,
        'local_file' => $RIM_SAVE_FILE,
        'error' => $e->getMessage(),
        'execution_time_sec' => rimworld_execution_time_sec()
    ));
}

flock($lock_fp, LOCK_UN);
fclose($lock_fp);

rimworld_json_exit($result);

function rimworld_json_exit(array $data): void
{
    if (!isset($data['execution_time_sec']) || (float)$data['execution_time_sec'] <= 0) {
        $data['execution_time_sec'] = rimworld_execution_time_sec();
    }

    if (!isset($data['execution_time']) || trim((string)$data['execution_time']) === '') {
        $data['execution_time'] = rimworld_execution_time_text((float)$data['execution_time_sec']);
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
function rimworld_execution_time_sec(): float
{
    global $RIM_TIME_START;

    if (!isset($RIM_TIME_START) || (float)$RIM_TIME_START <= 0) {
        return 0;
    }

    return round(microtime(true) - (float)$RIM_TIME_START, 4);
}

function rimworld_execution_time_text(float $sec): string
{
    if ($sec < 1) {
        return round($sec * 1000, 1) . ' мс';
    }

    if ($sec < 60) {
        return round($sec, 3) . ' сек';
    }

    $min = floor($sec / 60);
    $left_sec = round($sec - ($min * 60), 3);

    return $min . ' мин ' . $left_sec . ' сек';
}
function rimworld_get_i_contr_player(int $i_contr_id): array
{
    $res = _DB("SELECT id, name FROM i_contr WHERE id = ? LIMIT 1", array($i_contr_id));

    if ($res === false) {
        throw new RuntimeException('Ошибка запроса i_contr для i_contr_id=' . $i_contr_id);
    }

    $row = $res->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Пользователь i_contr не найден: id=' . $i_contr_id);
    }

    $name = trim((string)($row['name'] ?? ''));

    if ($name === '') {
        throw new RuntimeException('У пользователя i_contr.id=' . $i_contr_id . ' не заполнено поле name');
    }

    if (preg_match('/[\/\\\\\x00]/u', $name)) {
        throw new RuntimeException('Некорректное имя пользователя для имени файла: ' . $name);
    }

    return array(
        'id' => (int)$row['id'],
        'name' => $name
    );
}

function rimworld_download_player_save_from_ftp(int $i_contr_id, string $player_name, string $tmp_dir): array
{
    if (!function_exists('ftp_connect')) {
        throw new RuntimeException('На сервере не включено PHP-расширение FTP');
    }

    $cfg = rimworld_ftp_config();

    $remote_dir = $cfg['dir'];
    $remote_file_name = $player_name . '.dat1';

    $local_file = rtrim($tmp_dir, '/\\') . '/rimworld_save_' . $i_contr_id . '_' . md5($player_name) . '.dat1';
    $tmp_file = $local_file . '.download';

    if (is_file($tmp_file)) {
        @unlink($tmp_file);
    }

    if ($cfg['ssl'] === true) {
        if (!function_exists('ftp_ssl_connect')) {
            throw new RuntimeException('Включен FTP SSL, но ftp_ssl_connect недоступен');
        }

        $conn = @ftp_ssl_connect($cfg['host'], $cfg['port'], $cfg['timeout']);
    } else {
        $conn = @ftp_connect($cfg['host'], $cfg['port'], $cfg['timeout']);
    }

    if (!$conn) {
        throw new RuntimeException('Не удалось подключиться к FTP: ' . $cfg['host'] . ':' . $cfg['port']);
    }

    if (!@ftp_login($conn, $cfg['login'], $cfg['password'])) {
        @ftp_close($conn);
        //echo '<br />'.$cfg['login'].' '. $cfg['password'].'<br />'.$cfg['host'].' '. $cfg['port'];
        throw new RuntimeException('Не удалось авторизоваться на FTP');
    }

    @ftp_pasv($conn, $cfg['passive']);

    if (!@ftp_chdir($conn, $remote_dir)) {
        @ftp_close($conn);
        throw new RuntimeException('Не удалось открыть FTP-папку: ' . $remote_dir);
    }

    $found_file = rimworld_ftp_find_file($conn, $remote_file_name);

    if ($found_file === '') {
        @ftp_close($conn);
        throw new RuntimeException('Файл сейва не найден на FTP: ' . rtrim($remote_dir, '/') . '/' . $remote_file_name);
    }

    $remote_mtime = @ftp_mdtm($conn, $found_file);

    if (!@ftp_get($conn, $tmp_file, $found_file, FTP_BINARY)) {
        @ftp_close($conn);
        throw new RuntimeException('Не удалось скачать файл сейва с FTP: ' . $found_file);
    }

    @ftp_close($conn);

    if (!is_file($tmp_file) || filesize($tmp_file) <= 0) {
        @unlink($tmp_file);
        throw new RuntimeException('FTP-файл скачан пустым или повреждён: ' . $remote_file_name);
    }

    if (is_file($local_file)) {
        @unlink($local_file);
    }

    if (!@rename($tmp_file, $local_file)) {
        @unlink($tmp_file);
        throw new RuntimeException('Не удалось сохранить локальную копию сейва: ' . $local_file);
    }

    if ($remote_mtime !== false && (int)$remote_mtime > 0) {
        @touch($local_file, (int)$remote_mtime);
        $mtime = (int)$remote_mtime;
    } else {
        $mtime = (int)filemtime($local_file);
    }

    return array(
        'local_file' => $local_file,
        'remote_file' => rtrim($remote_dir, '/') . '/' . $remote_file_name,
        'mtime' => $mtime
    );
}

function rimworld_ftp_find_file($conn, string $file_name): string
{
    $file_name = trim($file_name);

    if ($file_name === '') {
        return '';
    }

    $size = @ftp_size($conn, $file_name);

    if ($size !== -1) {
        return $file_name;
    }

    $list = @ftp_nlist($conn, '.');

    if (!is_array($list)) {
        return '';
    }

    foreach ($list as $file) {
        $file = trim((string)$file);

        if ($file === '') {
            continue;
        }

        $base = basename(str_replace('\\', '/', $file));

        if ($base === $file_name) {
            return $file;
        }
    }

    return '';
}

function rimworld_ftp_config(): array
{
    $host = rimworld_setting('RimWorld FTP: host');
    $port = (int)rimworld_setting('RimWorld FTP: port', '21');
    $login = rimworld_setting('RimWorld FTP: login');
    $password = rimworld_setting('RimWorld FTP: password');
    $dir = rimworld_setting('RimWorld FTP: dir', '/DataPlayers');
    $passive = rimworld_setting_bool('RimWorld FTP: passive', true);
    $ssl = rimworld_setting_bool('RimWorld FTP: ssl', false);
    $timeout = (int)rimworld_setting('RimWorld FTP: timeout', '30');

    if ($host === '') {
        throw new RuntimeException('Не задана настройка RimWorld FTP: host');
    }

    if ($login === '') {
        throw new RuntimeException('Не задана настройка RimWorld FTP: login');
    }

    if ($password === '') {
        throw new RuntimeException('Не задана настройка RimWorld FTP: password');
    }

    if ($port <= 0) {
        $port = 21;
    }

    if ($timeout <= 0) {
        $timeout = 30;
    }

    if ($dir === '') {
        $dir = '/DataPlayers';
    }

    return array(
        'host' => $host,
        'port' => $port,
        'login' => $login,
        'password' => $password,
        'dir' => $dir,
        'passive' => $passive,
        'ssl' => $ssl,
        'timeout' => $timeout
    );
}

function rimworld_setting(string $name, string $default = ''): string
{
    $name = trim($name);

    if ($name === '') {
        return $default;
    }

    if (isset($_SESSION['s_words'][$name])) {
        $value = $_SESSION['s_words'][$name];
    } else {
        return $default;
    }

    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = trim($value);

    return $value !== '' ? $value : $default;
}

function rimworld_setting_bool(string $name, bool $default = false): bool
{
    $value = rimworld_setting($name, $default ? '1' : '0');
    $value = strtolower(trim($value));

    return in_array($value, array('1', 'true', 'yes', 'on', 'да'), true);
}
function rimworld_ensure_table(): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS `i_contr_colonists` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `chk_active` tinyint(1) DEFAULT '1',
          `i_contr_id` bigint(20) NOT NULL,
          `pawn_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,

          `nick` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
          `gender` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
          `age_years` int(11) DEFAULT NULL,
          `story_title` varchar(999) COLLATE utf8_unicode_ci DEFAULT '',
          `hair_desc` varchar(999) COLLATE utf8_unicode_ci DEFAULT '',
          `skills_all` longtext COLLATE utf8_unicode_ci,
          `traits_summary` varchar(999) COLLATE utf8_unicode_ci DEFAULT '',
          `apparel_summary` longtext COLLATE utf8_unicode_ci,
          `weapon_summary` varchar(999) COLLATE utf8_unicode_ci DEFAULT '',
          `xenotype` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
          `ideology_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
          `psycast_level` int(11) DEFAULT NULL,
          `health_all` longtext COLLATE utf8_unicode_ci,
          `prompt` longtext COLLATE utf8_unicode_ci,

          `save_file` varchar(255) COLLATE utf8_unicode_ci DEFAULT '',
          `save_mtime_ts` int(11) DEFAULT NULL,
          `row_hash` char(40) COLLATE utf8_unicode_ci DEFAULT '',

          `data_create` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `data_change` timestamp NULL DEFAULT NULL,
          `data_last_scan` timestamp NULL DEFAULT NULL,

          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_i_contr_pawn` (`i_contr_id`, `pawn_id`),
          KEY `i_contr_id` (`i_contr_id`),
          KEY `nick` (`nick`),
          KEY `data_change` (`data_change`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Пешки игроков RimWorld'
    ";

    $res = _DB($sql);

    if ($res === false) {
        throw new RuntimeException('Не удалось создать таблицу i_contr_colonists');
    }

    rimworld_ensure_i_contr_columns();
}

function rimworld_ensure_i_contr_columns(): void
{
    rimworld_add_column_if_not_exists('i_contr', 'ideology', "`ideology` longtext COLLATE utf8_unicode_ci", 'passport');
    rimworld_add_column_if_not_exists('i_contr', 'cords', "`cords` varchar(255) COLLATE utf8_unicode_ci DEFAULT ''", 'ideology');
    rimworld_add_column_if_not_exists('i_contr', 'start', "`start` longtext COLLATE utf8_unicode_ci", 'cords');
    rimworld_add_column_if_not_exists('i_contr', 'game_days', "`game_days` int(11) DEFAULT NULL", 'start');
    rimworld_add_column_if_not_exists('i_contr', 'colony_name', "`colony_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT ''", 'game_days');
    rimworld_add_column_if_not_exists('i_contr', 'fraction_name', "`fraction_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT ''", 'colony_name');
    rimworld_add_column_if_not_exists('i_contr', 'animal_cnt', "`animal_cnt` int(11) DEFAULT NULL", 'fraction_name');
    rimworld_add_column_if_not_exists('i_contr', 'colonists_cnt', "`colonists_cnt` int(11) DEFAULT NULL", 'animal_cnt');
    rimworld_add_column_if_not_exists('i_contr', 'slaves_cnt', "`slaves_cnt` int(11) DEFAULT NULL", 'colonists_cnt');
    rimworld_add_column_if_not_exists('i_contr', 'prisoners_cnt', "`prisoners_cnt` int(11) DEFAULT NULL", 'slaves_cnt');
    rimworld_add_column_if_not_exists('i_contr', 'settlement_price', "`settlement_price` decimal(15,2) DEFAULT NULL", 'prisoners_cnt');
    rimworld_add_column_if_not_exists('i_contr', 'caravans_cnt', "`caravans_cnt` int(11) DEFAULT NULL", 'settlement_price');
    rimworld_add_column_if_not_exists('i_contr', 'settlements_cnt', "`settlements_cnt` int(11) DEFAULT NULL", 'caravans_cnt');
    rimworld_add_column_if_not_exists('i_contr', 'total_price', "`total_price` decimal(15,2) DEFAULT NULL", 'settlements_cnt');
}

function rimworld_add_column_if_not_exists(string $table, string $column, string $definition, string $after = ''): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new RuntimeException('Некорректное имя таблицы: ' . $table);
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        throw new RuntimeException('Некорректное имя колонки: ' . $column);
    }

    if ($after !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $after)) {
        throw new RuntimeException('Некорректное имя колонки AFTER: ' . $after);
    }

    $res = _DB("SELECT COUNT(*) AS cnt
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?", array($table, $column));

    if ($res === false) {
        throw new RuntimeException('Не удалось проверить колонку ' . $table . '.' . $column);
    }

    $row = $res->fetch(PDO::FETCH_ASSOC);

    if (isset($row['cnt']) && (int)$row['cnt'] > 0) {
        return;
    }

    $sql = "ALTER TABLE `" . $table . "` ADD COLUMN " . $definition;

    if ($after !== '') {
        $sql .= " AFTER `" . $after . "`";
    }

    $res_add = _DB($sql);

    if ($res_add === false) {
        throw new RuntimeException('Не удалось добавить колонку ' . $table . '.' . $column);
    }
}



function rimworld_column_exists(string $table, string $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new RuntimeException('Некорректное имя таблицы: ' . $table);
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        throw new RuntimeException('Некорректное имя колонки: ' . $column);
    }

    $sql = "SELECT COUNT(*) AS cnt
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";

    $res = _DB($sql, array($table, $column));

    if ($res === false) {
        return false;
    }

    $row = $res->fetch(PDO::FETCH_ASSOC);

    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

function rimworld_is_save_already_processed(int $i_contr_id, int $save_mtime_ts): bool
{
    $sql = "SELECT MAX(save_mtime_ts) AS last_mtime
            FROM i_contr_colonists
            WHERE i_contr_id = ?";

    $res = _DB($sql, array($i_contr_id));

    if ($res === false) {
        return false;
    }

    $row = $res->fetch(PDO::FETCH_ASSOC);
    $last_mtime = isset($row['last_mtime']) ? (int)$row['last_mtime'] : 0;

    return $last_mtime >= $save_mtime_ts;
}
function rimworld_save_colonists(int $i_contr_id, string $save_file, int $save_mtime_ts, array $colonists): array
{
    $sql_deactivate = "UPDATE i_contr_colonists
                       SET chk_active = 0,
                           data_change = IF(chk_active = 1, NOW(), data_change),
                           data_last_scan = NOW()
                       WHERE i_contr_id = ?";

    $res_deactivate = _DB($sql_deactivate, array($i_contr_id));

    if ($res_deactivate === false) {
        throw new RuntimeException('Не удалось деактивировать старых пешек');
    }

    $deactivated = method_exists($res_deactivate, 'rowCount') ? (int)$res_deactivate->rowCount() : 0;

    $sql = "INSERT INTO i_contr_colonists (
                chk_active,
                i_contr_id,
                pawn_id,
                nick,
                gender,
                age_years,
                story_title,
                hair_desc,
                skills_all,
                traits_summary,
                apparel_summary,
                weapon_summary,
                xenotype,
                ideology_name,
                psycast_level,
                health_all,
                prompt,
                save_file,
                save_mtime_ts,
                row_hash,
                data_create,
                data_change,
                data_last_scan
            ) VALUES (
                1,
                :i_contr_id,
                :pawn_id,
                :nick,
                :gender,
                :age_years,
                :story_title,
                :hair_desc,
                :skills_all,
                :traits_summary,
                :apparel_summary,
                :weapon_summary,
                :xenotype,
                :ideology_name,
                :psycast_level,
                :health_all,
                :prompt,
                :save_file,
                :save_mtime_ts,
                :row_hash,
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                chk_active = 1,
                data_change = IF(row_hash <> VALUES(row_hash) OR chk_active <> 1, NOW(), data_change),
                nick = VALUES(nick),
                gender = VALUES(gender),
                age_years = VALUES(age_years),
                story_title = VALUES(story_title),
                hair_desc = VALUES(hair_desc),
                skills_all = VALUES(skills_all),
                traits_summary = VALUES(traits_summary),
                apparel_summary = VALUES(apparel_summary),
                weapon_summary = VALUES(weapon_summary),
                xenotype = VALUES(xenotype),
                ideology_name = VALUES(ideology_name),
                psycast_level = VALUES(psycast_level),
                health_all = VALUES(health_all),
                prompt = VALUES(prompt),
                save_file = VALUES(save_file),
                save_mtime_ts = VALUES(save_mtime_ts),
                row_hash = VALUES(row_hash),
                data_last_scan = NOW()";

    $inserted_or_updated = 0;

    foreach ($colonists as $colonist) {
        $compact = rimworld_colonist_compact($colonist);

        $data_for_hash = $compact;
        $data_for_hash['save_file'] = $save_file;

        $row_hash = sha1(json_encode($data_for_hash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $params = array(
            ':i_contr_id' => $i_contr_id,
            ':pawn_id' => $compact['pawn_id'],
            ':nick' => $compact['nick'],
            ':gender' => $compact['gender'],
            ':age_years' => $compact['age_years'],
            ':story_title' => $compact['story_title'],
            ':hair_desc' => $compact['hair_desc'],
            ':skills_all' => $compact['skills_all'],
            ':traits_summary' => $compact['traits_summary'],
            ':apparel_summary' => $compact['apparel_summary'],
            ':weapon_summary' => $compact['weapon_summary'],
            ':xenotype' => $compact['xenotype'],
            ':ideology_name' => $compact['ideology_name'],
            ':psycast_level' => $compact['psycast_level'],
            ':health_all' => $compact['health_all'],
            ':prompt' => $compact['prompt'],
            ':save_file' => $save_file,
            ':save_mtime_ts' => $save_mtime_ts > 0 ? $save_mtime_ts : null,
            ':row_hash' => $row_hash
        );

        $res = _DB($sql, $params);

        if ($res === false) {
            throw new RuntimeException(
                'Ошибка записи пешки: ' . ($compact['nick'] ?? '') . '. Смотри /logs/sql_errors.log'
            );
        }

        $inserted_or_updated++;
    }


    return array(
        'inserted_or_updated' => $inserted_or_updated,
        'deactivated' => $deactivated
    );
}


function rimworld_save_i_contr_game_data(int $i_contr_id, array $game_data): void
{
    $sql = "UPDATE i_contr
            SET ideology = ?,
                cords = ?,
                `start` = ?,
                game_days = ?,
                colony_name = ?,
                fraction_name = ?,
                animal_cnt = ?,
                colonists_cnt = ?,
                slaves_cnt = ?,
                prisoners_cnt = ?,
                settlement_price = ?,
                caravans_cnt = ?,
                settlements_cnt = ?,
                total_price = ?,
                data_change = NOW()
            WHERE id = ?
            LIMIT 1";

    $params = array(
        $game_data['ideology'],
        $game_data['cords'],
        $game_data['start'],
        $game_data['game_days'],
        $game_data['colony_name'],
        $game_data['fraction_name'],
        $game_data['animal_cnt'],
        $game_data['colonists_cnt'],
        $game_data['slaves_cnt'],
        $game_data['prisoners_cnt'],
        $game_data['settlement_price'],
        $game_data['caravans_cnt'],
        $game_data['settlements_cnt'],
        $game_data['total_price'],
        $i_contr_id
    );

    $res = _DB($sql, $params);

    if ($res === false) {
        throw new RuntimeException('Не удалось обновить общие данные i_contr по RimWorld-сейву');
    }
}
function rimworld_colonist_compact(array $colonist): array
{
    $nick = trim((string)($colonist['nick'] ?? ''));

    if ($nick === '') {
        $nick = trim((string)($colonist['pawn_name'] ?? ''));
    }

    if ($nick === '') {
        $nick = trim((string)($colonist['pawn_id'] ?? ''));
    }

    $age_years = null;

    if (isset($colonist['age_years']) && $colonist['age_years'] !== '' && $colonist['age_years'] !== null) {
        $age_years = (int)round((float)$colonist['age_years']);
    }

    $data = array(
        'pawn_id' => trim((string)($colonist['pawn_id'] ?? '')),
        'nick' => $nick,
        'gender' => rimworld_gender_ru((string)($colonist['gender'] ?? '')),
        'age_years' => $age_years,
        'story_title' => trim((string)($colonist['story_title'] ?? '')),
        'hair_desc' => rimworld_hair_desc((string)($colonist['hair'] ?? ''), (string)($colonist['hair_color'] ?? '')),
        'skills_all' => rimworld_skills_all($colonist['skills'] ?? array()),
        'traits_summary' => trim((string)($colonist['traits_summary'] ?? '')),
        'apparel_summary' => trim((string)($colonist['apparel_summary'] ?? '')),
        'weapon_summary' => trim((string)($colonist['weapon_summary'] ?? '')),
        'xenotype' => trim((string)($colonist['xenotype'] ?? '')),
        'ideology_name' => trim((string)($colonist['ideology_name'] ?? '')),
        'psycast_level' => isset($colonist['psycast_level']) && $colonist['psycast_level'] !== '' ? (int)$colonist['psycast_level'] : null,
        'health_all' => rimworld_health_all($colonist),
        'prompt' => ''
    );

    $data['prompt'] = rimworld_colonist_prompt($data);

    return $data;
}

function rimworld_gender_ru(string $gender): string
{
    $gender = trim($gender);

    if ($gender === 'Male') {
        return 'Мужской';
    }

    if ($gender === 'Female') {
        return 'Женский';
    }

    if ($gender === '') {
        return '';
    }

    return $gender;
}

function rimworld_hair_desc(string $hair, string $hair_color): string
{
    $hair = trim($hair);
    $hair_color = trim($hair_color);

    $parts = array();

    if ($hair !== '') {
        $parts[] = 'Прическа: ' . $hair;
    }

    if ($hair_color !== '') {
        $parts[] = 'цвет волос: ' . $hair_color;
    }

    return implode(', ', $parts);
}

function rimworld_skills_all(array $skills): string
{
    $parts = array();

    foreach ($skills as $skill) {
        if (!is_array($skill)) {
            continue;
        }

        $name = trim((string)($skill['name_ru'] ?? $skill['code'] ?? ''));
        $level = $skill['level'] ?? null;
        $passion = trim((string)($skill['passion_ru'] ?? ''));

        if ($name === '') {
            continue;
        }

        $txt = $name;

        if ($level !== null && $level !== '') {
            $txt .= ': ' . (int)$level;
        }

        if ($passion !== '') {
            $txt .= ' (' . $passion . ')';
        }

        $parts[] = $txt;
    }

    return implode('; ', $parts);
}

function rimworld_health_all(array $colonist): string
{
    $parts = array();

    $injuries_text = rimworld_health_items_text($colonist['injuries'] ?? array());
    if ($injuries_text !== '') {
        $parts[] = 'травмы: ' . $injuries_text;
    }

    $missing_parts_text = rimworld_health_items_text($colonist['missing_parts'] ?? array());
    if ($missing_parts_text !== '') {
        $parts[] = 'потерянные части тела: ' . $missing_parts_text;
    }

    $implants_text = rimworld_health_items_text($colonist['implants'] ?? array());
    if ($implants_text !== '') {
        $parts[] = 'импланты/протезы: ' . $implants_text;
    }

    if (!empty($colonist['health']) && is_array($colonist['health'])) {
        foreach ($colonist['health'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $def = trim((string)($item['def'] ?? ''));

            if ($def === '' || $def === 'Nutrients') {
                continue;
            }

            $class = trim((string)($item['class'] ?? ''));

            if ($class === 'Hediff_Injury' || $def === 'MissingBodyPart') {
                continue;
            }

            $txt = rimworld_health_def_ru($def);
            $part = rimworld_health_part_text($item);

            if ($part !== '') {
                $txt .= ' — ' . $part;
            }

            if (!empty($item['severity'])) {
                $txt .= ' / severity: ' . $item['severity'];
            }

            $parts[] = $txt;
        }
    }

    $parts = array_values(array_unique(array_filter($parts)));

    return implode('; ', $parts);
}

function rimworld_colonist_prompt(array $data): string
{
    $parts = array();

    $parts[] = 'Создай детализированное изображение персонажа RimWorld в полный рост, semi-realistic sci-fi style, суровая фронтирная колония, практичная экипировка, ракурс 3/4, качественный кинематографичный свет.';

    if ($data['nick'] !== '') {
        $parts[] = 'Имя персонажа: ' . $data['nick'] . '.';
    }

    $appearance = array();

    if ($data['gender'] !== '') {
        $appearance[] = 'пол: ' . $data['gender'];
    }

    if ($data['age_years'] !== null) {
        $appearance[] = 'возраст: ' . $data['age_years'] . ' лет';
    }

    if ($data['hair_desc'] !== '') {
        $appearance[] = $data['hair_desc'];
    }

    if (!empty($appearance)) {
        $parts[] = 'Внешность: ' . implode(', ', $appearance) . '.';
    }

    if ($data['story_title'] !== '') {
        $parts[] = 'История / роль: ' . $data['story_title'] . '.';
    }

    if ($data['skills_all'] !== '') {
        $parts[] = 'Навыки персонажа: ' . $data['skills_all'] . '. Визуально подчеркни самые сильные навыки через позу, предметы и детали одежды.';
    }

    if ($data['traits_summary'] !== '') {
        $parts[] = 'Черты характера: ' . $data['traits_summary'] . '. Поза и выражение лица должны отражать эти черты.';
    }

    if ($data['apparel_summary'] !== '') {
        $parts[] = 'Одежда: ' . $data['apparel_summary'] . '.';
    }

    if ($data['weapon_summary'] !== '') {
        $parts[] = 'Оружие или инструмент: ' . $data['weapon_summary'] . '.';
    }

    if ($data['xenotype'] !== '') {
        $parts[] = 'Ксенотип: ' . $data['xenotype'] . '.';
    }

    if ($data['ideology_name'] !== '') {
        $parts[] = 'Идеология: ' . $data['ideology_name'] . '.';
    }

    if ($data['psycast_level'] !== null) {
        $parts[] = 'Уровень псикаста: ' . $data['psycast_level'] . '. Можно добавить слабый намек на пси-способности без чрезмерной магии.';
    }

    if ($data['health_all'] !== '') {
        $parts[] = 'Состояние здоровья и травмы: ' . $data['health_all'] . '. Покажи это аккуратно через шрамы, протезы или детали внешности, без чрезмерной жестокости.';
    }

    $parts[] = 'Не добавляй лишних персонажей. Не меняй пол, возраст, ксенотип и основную роль. Изображение должно выглядеть как портрет конкретной пешки, а не случайного колониста.';

    return rimworld_text_normalize(implode(' ', $parts));
}

function rimworld_text_normalize(string $text): string
{
    $text = str_replace(array("\r", "\n", "\t"), ' ', $text);

    while (strpos($text, '  ') !== false) {
        $text = str_replace('  ', ' ', $text);
    }

    return trim($text);
}

function rimworld_json($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function rimworld_skill_level(array $skill_map, string $code): ?int
{
    if ($code === 'Medical' && !isset($skill_map[$code]) && isset($skill_map['Medicine'])) {
        $code = 'Medicine';
    }

    if ($code === 'Medicine' && !isset($skill_map[$code]) && isset($skill_map['Medical'])) {
        $code = 'Medical';
    }

    if (!isset($skill_map[$code])) {
        return null;
    }

    if (!isset($skill_map[$code]['level'])) {
        return null;
    }

    if ($skill_map[$code]['level'] === null || $skill_map[$code]['level'] === '') {
        return null;
    }

    return (int)$skill_map[$code]['level'];
}

function rimworld_parse_save_colonists(string $save_file): array
{
    $data = rimworld_parse_save_full($save_file);

    return $data['colonists'];
}


function rimworld_parse_save_full(string $save_file): array
{
    $xml_file = rimworld_prepare_xml_file($save_file);

    $reader = new XMLReader();
    libxml_use_internal_errors(true);

    if (!$reader->open($xml_file, 'UTF-8', LIBXML_NONET | LIBXML_PARSEHUGE)) {
        throw new RuntimeException('Не удалось открыть XML сейва: ' . $save_file);
    }

    $pawns = array();
    $scenario = array();
    $ticks_game = 0;
    $factions = array();
    $ideos = array();
    $world_objects = array();
    $wealth_values = array();

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT) {
            continue;
        }

        if ($reader->name === 'scenario') {
            $sx = rimworld_reader_simplexml($reader);

            if ($sx) {
                $scenario = rimworld_parse_scenario_info($sx);
            }

            $reader->next();
            continue;
        }

        if ($reader->name === 'tickManager') {
            $sx = rimworld_reader_simplexml($reader);

            if ($sx) {
                $ticks_game = (int)rimworld_sx_text($sx, 'ticksGame');
            }

            $reader->next();
            continue;
        }

        if ($reader->name === 'factionManager') {
            $sx = rimworld_reader_simplexml($reader);

            if ($sx) {
                $factions = rimworld_parse_factions_global($sx);
            }

            $reader->next();
            continue;
        }

        if ($reader->name === 'ideoManager') {
            $sx = rimworld_reader_simplexml($reader);

            if ($sx) {
                $ideos = rimworld_parse_ideos_global($sx);
            }

            $reader->next();
            continue;
        }

        if ($reader->name === 'worldObjects') {
            $sx = rimworld_reader_simplexml($reader);

            if ($sx) {
                $world_objects = rimworld_parse_world_objects_global($sx);
            }

            $reader->next();
            continue;
        }

        if ($reader->name === 'PlayerWealthForStoryteller') {
            $value = trim($reader->readString());

            if ($value !== '' && is_numeric($value)) {
                $wealth_values[] = (float)$value;
            }

            continue;
        }

        if ($reader->name === 'thing' && $reader->getAttribute('Class') === 'Pawn') {
            $sx = rimworld_reader_simplexml($reader);

            if ($sx) {
                $pawn = rimworld_parse_pawn($sx);

                if (!empty($pawn['pawn_id'])) {
                    $pawns[] = $pawn;
                }
            }

            $reader->next();
            continue;
        }
    }

    $reader->close();

    if ($xml_file !== $save_file && is_file($xml_file)) {
        @unlink($xml_file);
    }

    $player_faction = rimworld_detect_player_faction($pawns);

    $colonists = array();

    foreach ($pawns as $pawn) {
        if (($pawn['faction'] ?? '') !== $player_faction) {
            continue;
        }

        if (($pawn['def'] ?? '') !== 'Human') {
            continue;
        }

        if (rimworld_pawn_is_slave($pawn, $player_faction)) {
            continue;
        }

        $colonists[] = $pawn;
    }

    $game_data = rimworld_build_i_contr_game_data(
        $pawns,
        $colonists,
        $player_faction,
        $scenario,
        $ticks_game,
        $factions,
        $ideos,
        $world_objects,
        $wealth_values
    );

    return array(
        'colonists' => $colonists,
        'game_data' => $game_data,
        'player_faction' => $player_faction
    );
}

function rimworld_reader_simplexml(XMLReader $reader): ?SimpleXMLElement
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom_node = $reader->expand($dom);

    if (!$dom_node) {
        return null;
    }

    $sx = simplexml_import_dom($dom_node);

    return $sx ?: null;
}
function rimworld_build_i_contr_game_data(
    array $pawns,
    array $colonists,
    string $player_faction,
    array $scenario,
    int $ticks_game,
    array $factions,
    array $ideos,
    array $world_objects,
    array $wealth_values
): array {
    $player_faction_info = isset($factions[$player_faction]) ? $factions[$player_faction] : array();

    $fraction_name = trim((string)($player_faction_info['name'] ?? ''));
    $primary_ideo = trim((string)($player_faction_info['primary_ideo'] ?? ''));

    $ideology = '';

    if ($primary_ideo !== '' && isset($ideos[$primary_ideo])) {
        $ideology = $ideos[$primary_ideo]['summary'];
    } elseif ($primary_ideo !== '') {
        $ideology = $primary_ideo;
    }

    $player_settlements = array();
    $player_caravans = array();

    foreach ($world_objects as $obj) {
        if (($obj['faction'] ?? '') !== $player_faction) {
            continue;
        }

        $class = trim((string)($obj['class'] ?? ''));
        $def = trim((string)($obj['def'] ?? ''));

        if ($class === 'Settlement' || $def === 'Settlement') {
            $player_settlements[] = $obj;
        }

        if ($class === 'Caravan' || $def === 'Caravan') {
            $player_caravans[] = $obj;
        }
    }

    $first_settlement = !empty($player_settlements) ? $player_settlements[0] : array();

    $colony_name = trim((string)($first_settlement['name'] ?? ''));
    $cords = trim((string)($first_settlement['tile'] ?? ''));

    $animal_cnt = 0;
    $slaves_cnt = 0;
    $prisoners_cnt = 0;

    foreach ($pawns as $pawn) {
        if (($pawn['faction'] ?? '') === $player_faction && ($pawn['def'] ?? '') !== 'Human') {
            $animal_cnt++;
        }

        if (rimworld_pawn_is_slave($pawn, $player_faction)) {
            $slaves_cnt++;
        }

        if (rimworld_pawn_is_prisoner($pawn, $player_faction)) {
            $prisoners_cnt++;
        }
    }

    $settlement_price = null;

    if (!empty($wealth_values)) {
        $settlement_price = max($wealth_values);
    }

    $caravans_price = 0;
    $total_price = null;

    if ($settlement_price !== null) {
        $total_price = (float)$settlement_price + (float)$caravans_price;
    }

    return array(
        'ideology' => $ideology,
        'cords' => $cords,
        'start' => rimworld_scenario_start_short($scenario),
        'game_days' => $ticks_game > 0 ? (int)floor($ticks_game / 60000) : null,
        'colony_name' => $colony_name,
        'fraction_name' => $fraction_name,
        'animal_cnt' => $animal_cnt,
        'colonists_cnt' => count($colonists),
        'slaves_cnt' => $slaves_cnt,
        'prisoners_cnt' => $prisoners_cnt,
        'settlement_price' => $settlement_price,
        'caravans_cnt' => count($player_caravans),
        'settlements_cnt' => count($player_settlements),
        'total_price' => $total_price
    );
}
function rimworld_scenario_start_short(array $scenario): string
{
    $name = '';

    if (!empty($scenario['name'])) {
        $name = (string)$scenario['name'];
    } elseif (!empty($scenario['def'])) {
        $name = (string)$scenario['def'];
    } elseif (!empty($scenario['summary'])) {
        $name = (string)$scenario['summary'];
    }

    $name = rimworld_scenario_name_ru($name);
    $name = rimworld_text_normalize($name);

    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 255, 'UTF-8');
    }

    return substr($name, 0, 255);
}

function rimworld_scenario_name_ru(string $name): string
{
    $name = trim($name);

    $map = array(
        'RichExplorer' => 'Богатый исследователь',
        'Crashlanded' => 'Потерпевшие крушение',
        'LostTribe' => 'Затерянное племя',
        'NakedBrutality' => 'Голая жестокость',
        'TheMechanitor' => 'Механитор',
        'Sanguophage' => 'Сангвофаг',
        'Tutorial' => 'Обучение'
    );

    if (isset($map[$name])) {
        return $map[$name];
    }

    return $name;
}

function rimworld_parse_scenario_info(SimpleXMLElement $sx): array
{
        $scenario_def = rimworld_first_text($sx, array(
            'scenarioDef',
            'def',
            'name'
        ));
        
        $name = rimworld_first_text($sx, array(
            'name',
            'label',
            'title',
            'scenarioDef',
            'def'
        ));
    
        $summary = rimworld_sx_text($sx, 'summary');
        $description = rimworld_sx_text($sx, 'description');
    
        $name = rimworld_scenario_name_ru($name);

    $starting_things = array();
    $starting_research = array();
    $arrive_method = '';

    $parts = $sx->xpath('parts/li');

    if ($parts) {
        foreach ($parts as $part) {
            $part_def = rimworld_sx_text($part, 'def');

            if ($part_def === 'StartingThing_Defined') {
                $thing = rimworld_sx_text($part, 'thingDef');
                $count = rimworld_sx_text($part, 'count');

                if ($thing !== '') {
                    $starting_things[] = $thing . ($count !== '' ? ' x' . $count : '');
                }
            }

            if ($part_def === 'StartingResearch') {
                $project = rimworld_sx_text($part, 'project');

                if ($project !== '') {
                    $starting_research[] = $project;
                }
            }

            if ($part_def === 'PlayerPawnsArriveMethod') {
                $method = rimworld_sx_text($part, 'method');

                if ($method !== '') {
                    $arrive_method = $method;
                }
            }
        }
    }
return array(
    'def' => $part_def,
    'name' => $name,
    'summary' => $summary,
    'description' => $description,
    'arrive_method' => $arrive_method,
    'starting_things' => $starting_things,
    'starting_research' => array_values(array_unique($starting_research))
);
}

function rimworld_scenario_summary_text(array $scenario): string
{
    $parts = array();

    if (!empty($scenario['name'])) {
        $parts[] = 'Сценарий: ' . $scenario['name'];
    }

    if (!empty($scenario['summary'])) {
        $parts[] = 'Кратко: ' . $scenario['summary'];
    }

    if (!empty($scenario['description'])) {
        $parts[] = 'Описание: ' . $scenario['description'];
    }

    if (!empty($scenario['arrive_method'])) {
        $parts[] = 'Метод прибытия: ' . $scenario['arrive_method'];
    }

    if (!empty($scenario['starting_things'])) {
        $parts[] = 'Стартовые предметы: ' . implode(', ', array_slice($scenario['starting_things'], 0, 30));
    }

    if (!empty($scenario['starting_research'])) {
        $parts[] = 'Стартовые исследования: ' . implode(', ', array_slice($scenario['starting_research'], 0, 30));
    }

    return rimworld_text_normalize(implode('; ', $parts));
}
function rimworld_parse_factions_global(SimpleXMLElement $sx): array
{
    $result = array();

    $nodes = $sx->xpath('allFactions/li');

    if (!$nodes) {
        return $result;
    }

    foreach ($nodes as $li) {
        $load_id = rimworld_sx_text($li, 'loadID');
        $def = rimworld_sx_text($li, 'def');
        $name = rimworld_sx_text($li, 'name');
        $primary_ideo = rimworld_sx_text($li, 'ideos/primaryIdeo');

        if ($load_id === '') {
            continue;
        }

        $key = 'Faction_' . $load_id;

        $result[$key] = array(
            'key' => $key,
            'load_id' => (int)$load_id,
            'def' => $def,
            'name' => $name,
            'primary_ideo' => $primary_ideo
        );
    }

    return $result;
}
function rimworld_parse_world_objects_global(SimpleXMLElement $sx): array
{
    $result = array();

    $nodes = $sx->xpath('worldObjects/li');

    if (!$nodes) {
        $nodes = $sx->xpath('li');
    }

    if (!$nodes) {
        return $result;
    }

    foreach ($nodes as $li) {
        $class = isset($li['Class']) ? (string)$li['Class'] : '';
        $def = rimworld_sx_text($li, 'def');
        $faction = rimworld_sx_text($li, 'faction');
        $tile = rimworld_sx_text($li, 'tile');
        $id = rimworld_sx_text($li, 'ID');
        $name = rimworld_first_text($li, array('nameInt', 'name'));

        $result[] = array(
            'class' => $class,
            'def' => $def,
            'id' => $id,
            'tile' => $tile,
            'faction' => $faction,
            'name' => $name
        );
    }

    return $result;
}
function rimworld_parse_ideos_global(SimpleXMLElement $sx): array
{
    $result = array();

    $nodes = $sx->xpath('ideos/li');

    if (!$nodes) {
        return $result;
    }

    foreach ($nodes as $li) {
        $id = rimworld_sx_text($li, 'id');

        if ($id === '') {
            continue;
        }

        $key = 'Ideo_' . $id;

        $name = rimworld_sx_text($li, 'name');
        $description = rimworld_sx_text($li, 'description');

        $memes = rimworld_parse_simple_list($li, 'memes/li');

        $precepts = array();
        $precept_nodes = $li->xpath('precepts/li');

        if ($precept_nodes) {
            foreach ($precept_nodes as $precept) {
                $precept_name = rimworld_sx_text($precept, 'name');
                $precept_def = rimworld_sx_text($precept, 'def');

                if ($precept_name === '' && $precept_def === '') {
                    continue;
                }

                $txt = $precept_name;

                if ($precept_def !== '' && $precept_def !== $precept_name) {
                    $txt .= $txt !== '' ? ' (' . $precept_def . ')' : $precept_def;
                }

                $precepts[] = $txt;
            }
        }

        $summary_parts = array();

        if ($name !== '') {
            $summary_parts[] = 'Название: ' . $name;
        }

        if (!empty($memes)) {
            $summary_parts[] = 'Базовые мемы: ' . implode(', ', $memes);
        }

        if (!empty($precepts)) {
            $summary_parts[] = 'Принципы: ' . implode(', ', array_slice($precepts, 0, 25));
        }

        if ($description !== '') {
            $summary_parts[] = 'Описание: ' . $description;
        }

        $result[$key] = array(
            'key' => $key,
            'id' => (int)$id,
            'name' => $name,
            'description' => $description,
            'memes' => $memes,
            'precepts' => $precepts,
            'summary' => rimworld_text_normalize(implode('; ', $summary_parts))
        );
    }

    return $result;
}
function rimworld_prepare_xml_file(string $save_file): string
{
    if (!is_file($save_file)) {
        throw new RuntimeException('Файл не найден: ' . $save_file);
    }

    $fp = fopen($save_file, 'rb');
    $magic = $fp ? fread($fp, 4) : '';

    if ($fp) {
        fclose($fp);
    }

    if ($magic !== "PK\x03\x04") {
        return $save_file;
    }

    $zip = new ZipArchive();

    if ($zip->open($save_file) !== true) {
        throw new RuntimeException('Не удалось открыть ZIP-сейв: ' . $save_file);
    }

    $entry_name = null;

    if ($zip->locateName('data') !== false) {
        $entry_name = 'data';
    } else {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false && !str_ends_with($name, '/')) {
                $entry_name = $name;
                break;
            }
        }
    }

    if ($entry_name === null) {
        $zip->close();
        throw new RuntimeException('В архиве не найден XML-файл сейва');
    }

    $tmp_xml = sys_get_temp_dir() . '/rimworld_xml_' . uniqid('', true) . '.xml';
    $stream = $zip->getStream($entry_name);

    if (!$stream) {
        $zip->close();
        throw new RuntimeException('Не удалось открыть файл внутри ZIP: ' . $entry_name);
    }

    $out = fopen($tmp_xml, 'wb');

    if (!$out) {
        fclose($stream);
        $zip->close();
        throw new RuntimeException('Не удалось создать временный XML');
    }

    while (!feof($stream)) {
        fwrite($out, fread($stream, 1024 * 1024));
    }

    fclose($stream);
    fclose($out);
    $zip->close();

    return $tmp_xml;
}

function rimworld_parse_pawn(SimpleXMLElement $sx): array
{
    $first = rimworld_sx_text($sx, 'name/first');
    $nick = rimworld_sx_text($sx, 'name/nick');
    $last = rimworld_sx_text($sx, 'name/last');

    $pawn_name = trim(implode(' ', array_filter(array($first, $nick, $last), static function ($v) {
        return trim((string)$v) !== '';
    })));

    $pawn_id = rimworld_sx_text($sx, 'id');
    $def = rimworld_sx_text($sx, 'def');

    if ($pawn_name === '') {
        $pawn_name = trim($def . ' ' . $pawn_id);
    }

    $body_type = rimworld_sx_text($sx, 'story/bodyType');
    $head_type = rimworld_sx_text($sx, 'story/headType');
    $gender = rimworld_sx_text($sx, 'gender');
    
    if ($gender === '') {
        if (str_starts_with($body_type, 'Male') || str_starts_with($head_type, 'Male')) {
            $gender = 'Male';
        } elseif (str_starts_with($body_type, 'Female') || str_starts_with($head_type, 'Female')) {
            $gender = 'Female';
        }
    }

    $age_ticks = rimworld_sx_text($sx, 'ageTracker/ageBiologicalTicks');
    $age_years = null;

    if ($age_ticks !== '' && is_numeric($age_ticks)) {
        $age_years = round(((int)$age_ticks) / 3600000, 2);
    }

    $skills = rimworld_parse_skills($sx);
    $top_skills = array_slice($skills['list'], 0, 3);

    $traits = rimworld_parse_traits($sx);
    $health_data = rimworld_parse_health_extended($sx);

    $apparel = rimworld_parse_thing_inner_list($sx, 'apparel/wornApparel/innerList/li');
    $equipment = rimworld_parse_equipment($sx);
    $inventory_data = rimworld_parse_inventory($sx);

    $abilities = rimworld_parse_abilities($sx);
    $genes_data = rimworld_parse_genes($sx);
    $ideo_data = rimworld_parse_ideo($sx);
    $records_data = rimworld_parse_records($sx);

    return array(
        'pawn_id' => $pawn_id,
        'def' => $def,
        'kindDef' => rimworld_sx_text($sx, 'kindDef'),
        'faction' => rimworld_sx_text($sx, 'faction'),

        'pawn_name' => $pawn_name,
        'first_name' => $first,
        'nick' => $nick,
        'last_name' => $last,
        'gender' => $gender,
        'age_years' => $age_years,

        'story_title' => rimworld_sx_text($sx, 'story/title'),
        'childhood' => rimworld_sx_text($sx, 'story/childhood'),
        'adulthood' => rimworld_sx_text($sx, 'story/adulthood'),
        'body_type' => $body_type,
        'head_type' => $head_type,
        'hair' => rimworld_sx_text($sx, 'story/hairDef'),
        'hair_color' => rimworld_sx_text($sx, 'story/hairColor'),
        'skin_color' => rimworld_sx_text($sx, 'story/skinColorOverride'),
        'favorite_color' => rimworld_sx_text($sx, 'story/favoriteColorDef'),

        'skills' => $skills['list'],
        'skill_map' => $skills['map'],
        'top_skills' => $top_skills,
        'specialization' => rimworld_build_specialization(rimworld_sx_text($sx, 'story/title'), $skills['list']),

        'traits' => $traits,
        'traits_summary' => rimworld_traits_summary($traits),

        'health' => $health_data['all'],
        'health_summary' => $health_data['summary'],
        'injuries' => $health_data['injuries'],
        'missing_parts' => $health_data['missing_parts'],
        'implants' => $health_data['implants'],
        'psycast_level' => $health_data['psycast_level'],

        'apparel' => $apparel,
        'apparel_summary' => rimworld_things_summary($apparel),

        'equipment' => $equipment,
        'weapon_summary' => rimworld_weapon_summary($equipment),

        'inventory' => $inventory_data['items'],
        'ammo' => $inventory_data['ammo'],

        'abilities' => $abilities,

        'xenotype' => $genes_data['xenotype'],
        'genes' => $genes_data,

        'ideology_name' => $ideo_data['ideology_name'],
        'ideo' => $ideo_data,

        'records' => $records_data['records'],
        'kills' => $records_data['kills'],
        'things_crafted' => $records_data['things_crafted'],
        'buildings_built' => $records_data['buildings_built'],
        'medical_tends' => $records_data['medical_tends'],
        'guest_host_faction' => rimworld_sx_text($sx, 'guest/hostFaction'),
        'guest_slave_faction' => rimworld_sx_text($sx, 'guest/slaveFaction'),
        'guest_status' => rimworld_sx_text($sx, 'guest/guestStatus'),
        'guest_join_status' => rimworld_sx_text($sx, 'guest/joinStatus')
    );
}
function rimworld_pawn_is_slave(array $pawn, string $player_faction): bool
{
    $guest_status = trim((string)($pawn['guest_status'] ?? ''));
    $host_faction = trim((string)($pawn['guest_host_faction'] ?? ''));
    $slave_faction = trim((string)($pawn['guest_slave_faction'] ?? ''));

    if ($guest_status === 'Slave' && $host_faction === $player_faction) {
        return true;
    }

    if ($guest_status === 'Slave' && $slave_faction === $player_faction) {
        return true;
    }

    return false;
}

function rimworld_pawn_is_prisoner(array $pawn, string $player_faction): bool
{
    $guest_status = trim((string)($pawn['guest_status'] ?? ''));
    $host_faction = trim((string)($pawn['guest_host_faction'] ?? ''));

    if ($guest_status === 'Prisoner' && $host_faction === $player_faction) {
        return true;
    }

    return false;
}
function rimworld_parse_health_extended(SimpleXMLElement $sx): array
{
    $all = array();
    $injuries = array();
    $missing_parts = array();
    $implants = array();
    $psycast_level = null;

    $nodes = $sx->xpath('healthTracker/hediffSet/hediffs/li');

    if (!$nodes) {
        return array(
            'all' => array(),
            'injuries' => array(),
            'missing_parts' => array(),
            'implants' => array(),
            'psycast_level' => null,
            'summary' => ''
        );
    }

    foreach ($nodes as $li) {
        $def = rimworld_sx_text($li, 'def');

        if ($def === '') {
            continue;
        }

        $class = isset($li['Class']) ? (string)$li['Class'] : '';
        
        $part_body = rimworld_sx_text($li, 'part/body');
        $part_index = rimworld_sx_text($li, 'part/index');
        $part_name = rimworld_body_part_name($part_body, $part_index);
        
        $item = array(
            'class' => $class,
            'def' => $def,
            'severity' => rimworld_sx_text($li, 'severity'),
            'source' => rimworld_sx_text($li, 'source'),
            'sourceLabel' => rimworld_sx_text($li, 'sourceLabel'),
            'combatLogText' => rimworld_clean_text(rimworld_sx_text($li, 'combatLogText')),
            'part_body' => $part_body,
            'part_index' => $part_index,
            'part_name' => $part_name,
            'isPermanent' => rimworld_sx_text($li, 'isPermanent'),
            'tendQuality' => rimworld_sx_text($li, 'tendQuality'),
            'level' => rimworld_sx_text($li, 'level'),
            'ticksToDisappear' => rimworld_sx_text($li, 'ticksToDisappear')
        );

        $all[] = $item;

        if ($def === 'MissingBodyPart') {
            $missing_parts[] = $item;
            continue;
        }

        if (stripos($class, 'Injury') !== false || in_array($def, array('Gunshot', 'Bite', 'Cut', 'Crack', 'Bruise', 'Scratch', 'Burn'), true)) {
            $injuries[] = $item;
        }

        if (stripos($class, 'AddedPart') !== false || preg_match('/(Bionic|Archotech|Implant|Prosthetic|Artificial|PowerClaw|Aesthetic|DrillArm|FieldHand|PsychicAmplifier)/iu', $def)) {
            $implants[] = $item;
        }

        if ($def === 'PsychicAmplifier') {
            $level = rimworld_sx_text($li, 'level');

            if ($level !== '' && is_numeric($level)) {
                $psycast_level = max((int)$psycast_level, (int)$level);
            } else {
                $severity = rimworld_sx_text($li, 'severity');
                if ($severity !== '' && is_numeric($severity)) {
                    $psycast_level = max((int)$psycast_level, (int)$severity);
                }
            }
        }
    }

    $summary_parts = array();
    
    $injuries_text = rimworld_health_items_text($injuries);
    if ($injuries_text !== '') {
        $summary_parts[] = 'травмы: ' . $injuries_text;
    }
    
    $missing_parts_text = rimworld_health_items_text($missing_parts);
    if ($missing_parts_text !== '') {
        $summary_parts[] = 'потерянные части: ' . $missing_parts_text;
    }
    
    $implants_text = rimworld_health_items_text($implants);
    if ($implants_text !== '') {
        $summary_parts[] = 'импланты/усиления: ' . $implants_text;
    }
    
    if ($psycast_level !== null) {
        $summary_parts[] = 'пси-уровень: ' . $psycast_level;
    }

    return array(
        'all' => $all,
        'injuries' => $injuries,
        'missing_parts' => $missing_parts,
        'implants' => $implants,
        'psycast_level' => $psycast_level,
        'summary' => implode('; ', $summary_parts)
    );
}

function rimworld_parse_thing_inner_list(SimpleXMLElement $sx, string $path): array
{
    $result = array();
    $nodes = $sx->xpath($path);

    if (!$nodes) {
        return $result;
    }

    foreach ($nodes as $li) {
        $def = rimworld_sx_text($li, 'def');

        if ($def === '') {
            continue;
        }

        $result[] = array(
            'class' => isset($li['Class']) ? (string)$li['Class'] : '',
            'def' => $def,
            'id' => rimworld_sx_text($li, 'id'),
            'stuff' => rimworld_sx_text($li, 'stuff'),
            'health' => rimworld_sx_text($li, 'health'),
            'stackCount' => rimworld_sx_text($li, 'stackCount'),
            'quality' => rimworld_first_text($li, array('quality', 'comps/li/quality', 'comps/li/qualityInt')),
            'color' => rimworld_sx_text($li, 'color'),
            'remainingCharges' => rimworld_sx_text($li, 'remainingCharges')
        );
    }

    return $result;
}

function rimworld_parse_equipment(SimpleXMLElement $sx): array
{
    return array(
        'equipped' => rimworld_parse_thing_inner_list($sx, 'equipment/equipment/innerList/li'),
        'forced_weapon' => rimworld_sx_text($sx, 'forcedWeapon/thing'),
        'default_ranged_weapon' => rimworld_sx_text($sx, 'defaultRangedWeapon/thing'),
        'preferred_melee_weapon' => rimworld_sx_text($sx, 'preferredMeleeWeapon/thing'),
        'preferred_melee_stuff' => rimworld_sx_text($sx, 'preferredMeleeWeapon/stuff'),
        'primary_weapon_mode' => rimworld_sx_text($sx, 'primaryWeaponMode'),
        'remembered_weapons' => rimworld_parse_simple_list($sx, 'rememberedWeapons/li/thing')
    );
}

function rimworld_parse_inventory(SimpleXMLElement $sx): array
{
    $items = rimworld_parse_thing_inner_list($sx, 'inventory/innerContainer/innerList/li');
    $ammo = array();

    foreach ($items as $item) {
        $class = isset($item['class']) ? (string)$item['class'] : '';
        $def = isset($item['def']) ? (string)$item['def'] : '';

        if (stripos($class, 'AmmoThing') !== false || str_starts_with($def, 'Ammo_')) {
            $ammo[] = $item;
        }
    }

    return array(
        'items' => $items,
        'ammo' => $ammo
    );
}

function rimworld_parse_abilities(SimpleXMLElement $sx): array
{
    $result = array();

    foreach (array('abilities/abilities/li', 'abilities/li') as $path) {
        $nodes = $sx->xpath($path);

        if (!$nodes) {
            continue;
        }

        foreach ($nodes as $li) {
            $def = rimworld_first_text($li, array('def', 'abilityDef'));

            if ($def === '') {
                $raw = rimworld_clean_text((string)$li);
                if ($raw !== '') {
                    $def = $raw;
                }
            }

            if ($def !== '') {
                $result[] = array(
                    'def' => $def
                );
            }
        }
    }

    return $result;
}

function rimworld_parse_genes(SimpleXMLElement $sx): array
{
    $xenotype = rimworld_first_text($sx, array(
        'genes/xenotypeName',
        'genes/customXenotypeName',
        'genes/xenotype',
        'genes/xenotypeDef'
    ));

    $genes = array();

    foreach (array(
        'genes/endogenes/li/def',
        'genes/xenogenes/li/def',
        'genes/genes/li/def',
        'genes/endogenes/li',
        'genes/xenogenes/li'
    ) as $path) {
        $nodes = $sx->xpath($path);

        if (!$nodes) {
            continue;
        }

        foreach ($nodes as $node) {
            $val = rimworld_clean_text((string)$node);

            if ($val !== '' && !in_array($val, $genes, true)) {
                $genes[] = $val;
            }
        }
    }

    return array(
        'xenotype' => $xenotype,
        'genes' => $genes,
        'raw' => rimworld_node_to_array_first($sx, 'genes')
    );
}

function rimworld_parse_ideo(SimpleXMLElement $sx): array
{
    $ideology_name = rimworld_first_text($sx, array(
        'ideo/ideo',
        'ideo/ideoName',
        'ideo/name'
    ));

    return array(
        'ideology_name' => $ideology_name,
        'certainty' => rimworld_sx_text($sx, 'ideo/certainty'),
        'role' => rimworld_first_text($sx, array('ideo/role', 'ideo/roleDef')),
        'raw' => rimworld_node_to_array_first($sx, 'ideo')
    );
}

function rimworld_parse_records(SimpleXMLElement $sx): array
{
    $records = array();

    $keys = $sx->xpath('records/records/keys/li');
    $values = $sx->xpath('records/records/values/li');

    if ($keys && $values) {
        foreach ($keys as $i => $key_node) {
            $key = rimworld_clean_text((string)$key_node);
            $value = isset($values[$i]) ? rimworld_clean_text((string)$values[$i]) : '';

            if ($key !== '') {
                $records[$key] = is_numeric($value) ? (float)$value : $value;
            }
        }
    }

    $nodes = $sx->xpath('records//li');

    if ($nodes) {
        foreach ($nodes as $li) {
            $key = rimworld_first_text($li, array('def', 'key', 'recordDef'));
            $value = rimworld_first_text($li, array('value', 'count', 'val'));

            if ($key !== '' && $value !== '') {
                $records[$key] = is_numeric($value) ? (float)$value : $value;
            }
        }
    }

    return array(
        'records' => $records,
        'kills' => rimworld_record_find($records, array('Kills', 'KillsHumanlikes', 'KillsAnimals', 'KillsMechanoids')),
        'things_crafted' => rimworld_record_find($records, array('ThingsCrafted', 'ProductsFinished', 'ItemsCrafted')),
        'buildings_built' => rimworld_record_find($records, array('ThingsConstructed', 'BuildingsConstructed', 'BuildingsBuilt')),
        'medical_tends' => rimworld_record_find($records, array('TendedPatients', 'TimesTendedOther', 'MedicalTends'))
    );
}

function rimworld_record_find(array $records, array $keys): ?int
{
    foreach ($keys as $key) {
        if (isset($records[$key]) && is_numeric($records[$key])) {
            return (int)$records[$key];
        }
    }

    foreach ($records as $k => $v) {
        foreach ($keys as $key) {
            if (stripos((string)$k, $key) !== false && is_numeric($v)) {
                return (int)$v;
            }
        }
    }

    return null;
}
function rimworld_health_items_text(array $items): string
{
    $parts = array();

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $def = trim((string)($item['def'] ?? ''));

        if ($def === '') {
            continue;
        }

        $txt = rimworld_health_def_ru($def);
        $part = rimworld_health_part_text($item);

        if ($part !== '') {
            $txt .= ' — ' . $part;
        }

        if (!empty($item['severity'])) {
            $txt .= ' / severity: ' . $item['severity'];
        }

        if (!empty($item['isPermanent']) && $item['isPermanent'] === 'True') {
            $txt .= ' / постоянная';
        }

        $parts[] = $txt;
    }

    $parts = array_values(array_unique(array_filter($parts)));

    return implode(', ', $parts);
}

function rimworld_health_part_text(array $item): string
{
    $part_name = trim((string)($item['part_name'] ?? ''));
    $body = trim((string)($item['part_body'] ?? ''));
    $index = trim((string)($item['part_index'] ?? ''));

    if ($part_name !== '') {
        if ($body !== '' && $index !== '') {
            return $part_name . ' (' . $body . ' #' . $index . ')';
        }

        return $part_name;
    }

    if ($body !== '' && $index !== '') {
        return $body . ' #' . $index;
    }

    if ($body !== '') {
        return $body;
    }

    if ($index !== '') {
        return 'часть тела #' . $index;
    }

    return '';
}

function rimworld_health_def_ru(string $def): string
{
    $map = array(
        'Bite' => 'укус',
        'Scratch' => 'царапина',
        'Cut' => 'порез',
        'Gunshot' => 'огнестрельная рана',
        'Bruise' => 'ушиб',
        'Burn' => 'ожог',
        'Crack' => 'трещина/перелом',
        'MissingBodyPart' => 'отсутствующая часть тела',
        'Scarification' => 'шрам',
        'PsychicAmplifier' => 'пси-усилитель'
    );

    return $map[$def] ?? $def;
}

function rimworld_body_part_name(string $body, $index): string
{
    $body = trim($body);
    $index = trim((string)$index);

    if ($body === '' || $index === '' || !is_numeric($index)) {
        return '';
    }

    $index_i = (int)$index;

    if ($body === 'Human') {
        $map = rimworld_human_body_part_map_ru();

        if (isset($map[$index_i])) {
            return $map[$index_i];
        }
    }

    return $body . ' #' . $index_i;
}

function rimworld_human_body_part_map_ru(): array
{
    return array(
        0 => 'туловище',
        1 => 'грудная клетка',
        2 => 'грудина',
        3 => 'таз',
        4 => 'позвоночник',
        5 => 'желудок',
        6 => 'сердце',
        7 => 'левое лёгкое',
        8 => 'правое лёгкое',
        9 => 'левая почка',
        10 => 'правая почка',
        11 => 'печень',
        12 => 'шея',
        13 => 'голова',
        14 => 'череп',
        15 => 'мозг',
        16 => 'левый глаз',
        17 => 'правый глаз',
        18 => 'левое ухо',
        19 => 'правое ухо',
        20 => 'нос',
        21 => 'челюсть',
        22 => 'левое плечо',
        23 => 'левая ключица',
        24 => 'левая рука',
        25 => 'левая плечевая кость',
        26 => 'левая лучевая кость',
        27 => 'левая кисть',
        28 => 'левый мизинец',
        29 => 'левый безымянный палец',
        30 => 'левый средний палец',
        31 => 'левый указательный палец',
        32 => 'левый большой палец',
        33 => 'правое плечо',
        34 => 'правая ключица',
        35 => 'правая рука',
        36 => 'правая плечевая кость',
        37 => 'правая лучевая кость',
        38 => 'правая кисть',
        39 => 'правый мизинец',
        40 => 'правый безымянный палец',
        41 => 'правый средний палец',
        42 => 'правый указательный палец',
        43 => 'правый большой палец',
        44 => 'поясница',
        45 => 'левая нога',
        46 => 'левая бедренная кость',
        47 => 'левая большеберцовая кость',
        48 => 'левая стопа',
        49 => 'левый мизинец ноги',
        50 => 'левый четвёртый палец ноги',
        51 => 'левый средний палец ноги',
        52 => 'левый второй палец ноги',
        53 => 'левый большой палец ноги',
        54 => 'правая нога',
        55 => 'правая бедренная кость',
        56 => 'правая большеберцовая кость',
        57 => 'правая стопа',
        58 => 'правый мизинец ноги',
        59 => 'правый четвёртый палец ноги',
        60 => 'правый средний палец ноги',
        61 => 'правый второй палец ноги',
        62 => 'правый большой палец ноги'
    );
}
function rimworld_parse_simple_list(SimpleXMLElement $sx, string $path): array
{
    $result = array();
    $nodes = $sx->xpath($path);

    if (!$nodes) {
        return $result;
    }

    foreach ($nodes as $node) {
        $val = rimworld_clean_text((string)$node);

        if ($val !== '') {
            $result[] = $val;
        }
    }

    return $result;
}

function rimworld_traits_summary(array $traits): string
{
    $items = array();

    foreach ($traits as $trait) {
        if (!empty($trait['def'])) {
            $items[] = $trait['def'];
        }
    }

    return implode(', ', $items);
}

function rimworld_things_summary(array $items): string
{
    $parts = array();

    foreach ($items as $item) {
        if (empty($item['def'])) {
            continue;
        }

        $txt = $item['def'];

        if (!empty($item['stuff'])) {
            $txt .= ' / ' . $item['stuff'];
        }

        if (!empty($item['quality'])) {
            $txt .= ' / ' . $item['quality'];
        }

        $parts[] = $txt;
    }

    return implode(', ', array_slice($parts, 0, 8));
}

function rimworld_weapon_summary(array $equipment): string
{
    $parts = array();

    if (!empty($equipment['equipped']) && is_array($equipment['equipped'])) {
        foreach ($equipment['equipped'] as $item) {
            if (!empty($item['def'])) {
                $parts[] = $item['def'];
            }
        }
    }

    foreach (array('forced_weapon', 'default_ranged_weapon', 'preferred_melee_weapon') as $key) {
        if (!empty($equipment[$key]) && !in_array($equipment[$key], $parts, true)) {
            $parts[] = $equipment[$key];
        }
    }

    return implode(', ', $parts);
}

function rimworld_node_to_array_first(SimpleXMLElement $sx, string $path): array
{
    $nodes = $sx->xpath($path);

    if (!$nodes || !isset($nodes[0])) {
        return array();
    }

    $value = rimworld_node_to_array($nodes[0], 4);

    if (is_array($value)) {
        return $value;
    }

    $value = rimworld_clean_text((string)$value);

    if ($value === '') {
        return array();
    }

    return array(
        'value' => $value
    );
}

function rimworld_node_to_array(SimpleXMLElement $node, int $depth = 4)
{
    if ($depth <= 0) {
        return rimworld_clean_text((string)$node);
    }

    $children = $node->children();
    $attrs = $node->attributes();

    $result = array();

    foreach ($attrs as $k => $v) {
        $result['@' . $k] = rimworld_clean_text((string)$v);
    }

    if (count($children) === 0) {
        return rimworld_clean_text((string)$node);
    }

    foreach ($children as $child_name => $child) {
        $value = rimworld_node_to_array($child, $depth - 1);

        if (isset($result[$child_name])) {
            if (!is_array($result[$child_name]) || !array_key_exists(0, $result[$child_name])) {
                $result[$child_name] = array($result[$child_name]);
            }

            $result[$child_name][] = $value;
        } else {
            $result[$child_name] = $value;
        }
    }

    return $result;
}

function rimworld_parse_skills(SimpleXMLElement $sx): array
{
    $list = array();
    $map = array();

    $nodes = $sx->xpath('skills/skills/li');

    if (!$nodes) {
        return array(
            'list' => array(),
            'map' => array()
        );
    }

    foreach ($nodes as $li) {
        $code = rimworld_sx_text($li, 'def');

        if ($code === '') {
            continue;
        }

        $level = rimworld_first_text($li, array(
            'levelInt',
            'level',
            'levelRaw'
        ));

        $passion = rimworld_sx_text($li, 'passion');

        $item = array(
            'code' => $code,
            'name_ru' => rimworld_skill_name_ru($code),
            'level' => $level !== '' && is_numeric($level) ? (int)$level : null,
            'passion' => $passion,
            'passion_ru' => rimworld_passion_ru($passion)
        );

        $list[] = $item;
        $map[$code] = $item;
    }

    usort($list, static function (array $a, array $b): int {
        $la = $a['level'] ?? -1;
        $lb = $b['level'] ?? -1;

        if ($la !== $lb) {
            return $lb <=> $la;
        }

        return rimworld_passion_rank((string)$b['passion']) <=> rimworld_passion_rank((string)$a['passion']);
    });

    return array(
        'list' => $list,
        'map' => $map
    );
}

function rimworld_parse_traits(SimpleXMLElement $sx): array
{
    $result = array();
    $nodes = $sx->xpath('story/traits/allTraits/li');

    if (!$nodes) {
        return $result;
    }

    foreach ($nodes as $li) {
        $def = rimworld_sx_text($li, 'def');

        if ($def === '') {
            continue;
        }

        $result[] = array(
            'def' => $def,
            'degree' => rimworld_sx_text($li, 'degree')
        );
    }

    return $result;
}

function rimworld_parse_health(SimpleXMLElement $sx): array
{
    $result = array();
    $nodes = $sx->xpath('healthTracker/hediffSet/hediffs/li');

    if (!$nodes) {
        return $result;
    }

    foreach ($nodes as $li) {
        $def = rimworld_sx_text($li, 'def');

        if ($def === '') {
            continue;
        }

        $part_body = rimworld_sx_text($li, 'part/body');
        $part_index = rimworld_sx_text($li, 'part/index');
        $part_name = rimworld_body_part_name($part_body, $part_index);
        
        $result[] = array(
            'class' => isset($li['Class']) ? (string)$li['Class'] : '',
            'def' => $def,
            'severity' => rimworld_sx_text($li, 'severity'),
            'source' => rimworld_sx_text($li, 'source'),
            'sourceLabel' => rimworld_sx_text($li, 'sourceLabel'),
            'combatLogText' => rimworld_clean_text(rimworld_sx_text($li, 'combatLogText')),
            'part_body' => $part_body,
            'part_index' => $part_index,
            'part_name' => $part_name
        );
    }

    return $result;
}

function rimworld_detect_player_faction(array $pawns): string
{
    $counter = array();

    foreach ($pawns as $pawn) {
        if (($pawn['def'] ?? '') !== 'Human') {
            continue;
        }

        if (($pawn['kindDef'] ?? '') !== 'Colonist') {
            continue;
        }

        $faction = trim((string)($pawn['faction'] ?? ''));

        if ($faction === '') {
            continue;
        }

        if (!isset($counter[$faction])) {
            $counter[$faction] = 0;
        }

        $counter[$faction]++;
    }

    if (empty($counter)) {
        return '';
    }

    arsort($counter);

    return (string)array_key_first($counter);
}

function rimworld_build_specialization(string $story_title, array $skills): string
{
    $parts = array();

    if ($story_title !== '') {
        $parts[] = $story_title;
    }

    $top = array();

    foreach ($skills as $skill) {
        if (!isset($skill['level']) || $skill['level'] === null) {
            continue;
        }

        $txt = $skill['name_ru'] . ' ' . $skill['level'];

        if (!empty($skill['passion'])) {
            $txt .= ' / ' . rimworld_passion_ru((string)$skill['passion']);
        }

        $top[] = $txt;

        if (count($top) >= 3) {
            break;
        }
    }

    if (!empty($top)) {
        $parts[] = implode(', ', $top);
    }

    return implode('; ', $parts);
}

function rimworld_first_text(SimpleXMLElement $sx, array $paths): string
{
    foreach ($paths as $path) {
        $val = rimworld_sx_text($sx, $path);

        if ($val !== '') {
            return $val;
        }
    }

    return '';
}

function rimworld_sx_text(SimpleXMLElement $sx, string $path): string
{
    $nodes = $sx->xpath($path);

    if (!$nodes || !isset($nodes[0])) {
        return '';
    }

    return rimworld_clean_text((string)$nodes[0]);
}

function rimworld_clean_text(string $txt): string
{
    $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = strip_tags($txt);
    $txt = preg_replace('/[\r\n\t]+/u', ' ', $txt);
    $txt = preg_replace('/\s+/u', ' ', $txt);

    return trim((string)$txt);
}

function rimworld_skill_name_ru(string $code): string
{
    $map = array(
        'Shooting' => 'Стрельба',
        'Melee' => 'Ближний бой',
        'Construction' => 'Строительство',
        'Mining' => 'Горное дело',
        'Cooking' => 'Кулинария',
        'Plants' => 'Растения',
        'Animals' => 'Животные',
        'Crafting' => 'Ремесло',
        'Artistic' => 'Искусство',
        'Medicine' => 'Медицина',
        'Social' => 'Общение',
        'Intellectual' => 'Интеллект'
    );

    return $map[$code] ?? $code;
}

function rimworld_passion_rank(string $passion): int
{
    return match ($passion) {
        'Major' => 2,
        'Minor' => 1,
        default => 0
    };
}

function rimworld_passion_ru(string $passion): string
{
    return match ($passion) {
        'Major' => 'сильный интерес',
        'Minor' => 'интерес',
        default => ''
    };
}

function rimworld_log(string $text, array $data = array()): void
{
    global $RIM_LOG_DIR;

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $text;

    if (!empty($data)) {
        $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $line .= "\n";

    @file_put_contents($RIM_LOG_DIR . '/rimworld_parse_save_test.log', $line, FILE_APPEND | LOCK_EX);
}

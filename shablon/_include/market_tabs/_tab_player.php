<?php
/**
 * /shablon/_include/market_tabs/_tab_player.php
 * Индивидуальный таб Market: профиль игрока на игровом сервере.
 */

if (isset($market_tab_mode) && $market_tab_mode === 'meta') {
    return array(
        'key' => 'player',
        'title' => 'Профиль игрока'
    );
}

$i_contr_id = isset($i_contr_id) ? (int)$i_contr_id : 0;

if ($i_contr_id <= 0) {
    echo '<div class="m_page"><div class="m_player_card"><div class="m_player_error">Не найден пользователь.</div></div></div>';
    return;
}

$sql = "SELECT name, email, passport, data_create
        FROM i_contr
        WHERE id = ?
        LIMIT 1";
$res = _DB($sql, array($i_contr_id));
if ($res === false) {
    echo '<div class="m_page"><div class="m_player_card"><div class="m_player_error">Ошибка загрузки профиля игрока.</div></div></div>';
    return;
}

$row = $res->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo '<div class="m_page"><div class="m_player_card"><div class="m_player_error">Пользователь не найден.</div></div></div>';
    return;
}

$player_id = isset($row['passport']) ? trim((string)$row['passport']) : '';
$name = isset($row['name']) ? trim((string)$row['name']) : '';
$email = isset($row['email']) ? trim((string)$row['email']) : '';
$data_create = isset($row['data_create']) ? trim((string)$row['data_create']) : '';

if ($data_create !== '' && $data_create !== '0000-00-00 00:00:00') {
    $data_create = date('d.m.Y H:i:s', strtotime($data_create));
} else {
    $data_create = '';
}
?>
<div class="m_page">
    <div class="m_player_card">
        <div class="m_player_title">Профиль игрока</div>

        <?php if ($player_id === ''): ?>
            <div class="m_player_error">Ошибка создания игрока! Выйдите и зайдите снова, чтобы повторить попытку создания игрока на сервере</div>
        <?php else: ?>
            <div class="m_player_rows">
                <div class="m_player_row"><span>ID игрока на сервере:</span><strong><?= _IN($player_id) ?></strong></div>
                <?php if ($name !== ''): ?>
                    <div class="m_player_row"><span>Имя / логин:</span><strong><?= _IN($name) ?></strong></div>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <div class="m_player_row"><span>Email:</span><strong><?= _IN($email) ?></strong></div>
                <?php endif; ?>
                <?php if ($data_create !== ''): ?>
                    <div class="m_player_row"><span>Дата регистрации на сайте:</span><strong><?= _IN($data_create) ?></strong></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

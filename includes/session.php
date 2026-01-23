<?php
// Session Management
function initUserSession($user_data) {
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['email'] = $user_data['email'];
    $_SESSION['vip_expire'] = $user_data['vip_expire'];
    
    // Load character data
    $db = Database::getInstance();
    $character = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_data['id']]);
    
    if ($character) {
        $_SESSION['character_id'] = $character['id'];
        $_SESSION['level'] = $character['level'];
        $_SESSION['exp'] = $character['exp'];
        $_SESSION['max_exp'] = getExpForLevel($character['level']);
        $_SESSION['current_hp'] = $character['current_hp'];
        $_SESSION['max_hp'] = $character['max_hp'];
        $_SESSION['current_mp'] = $character['current_mp'];
        $_SESSION['max_mp'] = $character['max_mp'];
        $_SESSION['atk'] = $character['atk'];
        $_SESSION['def'] = $character['def'];
        $_SESSION['agi'] = $character['agi'];
        $_SESSION['crit'] = $character['crit'];
        $_SESSION['gold'] = $character['gold'];
        $_SESSION['credits'] = $character['credits'];
        $_SESSION['current_floor'] = $character['current_floor'];
        $_SESSION['energy'] = $character['energy'];
        $_SESSION['energy_regen'] = $character['energy_regen'];
        $_SESSION['class'] = $character['class'];
        $_SESSION['avatar'] = $character['avatar'];
    }
}

function updateSession($user_id) {
    $db = Database::getInstance();
    $user_data = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    
    if ($user_data) {
        initUserSession($user_data);
    }
}

function destroySession() {
    session_unset();
    session_destroy();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function requireVip() {
    requireLogin();
    
    if (!isVipActive($_SESSION['vip_expire'])) {
        $_SESSION['error'] = "This feature requires VIP status.";
        redirect('index.php');
    }
}
?>
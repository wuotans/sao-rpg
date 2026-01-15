<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'get_stats':
        getPlayerStats();
        break;
    case 'heal':
        healPlayer();
        break;
    case 'buff':
        applyBuff();
        break;
    case 'rest':
        restPlayer();
        break;
    case 'daily_reward':
        claimDailyReward();
        break;
    case 'online_players':
        getOnlinePlayers();
        break;
    case 'recent_activity':
        getRecentActivity();
        break;
    case 'active_quests':
        getActiveQuests();
        break;
    case 'notifications':
        getNotifications();
        break;
    case 'regen_energy':
        regenerateEnergy();
        break;
    case 'get_energy':
        getEnergy();
        break;
    case 'get_skills':
        getPlayerSkills();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getPlayerStats() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $player = $db->fetch("
        SELECT c.*, u.username, u.vip_expire 
        FROM characters c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.user_id = ?
    ", [$user_id]);
    
    if (!$player) {
        echo json_encode(['error' => 'Player not found']);
        return;
    }
    
    // Calculate required exp for next level
    $required_exp = getExpForLevel($player['level']);
    
    // Check VIP status
    $is_vip = isVipActive($player['vip_expire']);
    
    // Update session
    $_SESSION['current_hp'] = $player['current_hp'];
    $_SESSION['max_hp'] = $player['max_hp'];
    $_SESSION['current_mp'] = $player['current_mp'];
    $_SESSION['max_mp'] = $player['max_mp'];
    $_SESSION['exp'] = $player['exp'];
    $_SESSION['max_exp'] = $required_exp;
    $_SESSION['energy'] = $player['energy'];
    $_SESSION['max_energy'] = MAX_ENERGY;
    $_SESSION['atk'] = $player['atk'];
    $_SESSION['def'] = $player['def'];
    $_SESSION['agi'] = $player['agi'];
    $_SESSION['crit'] = $player['crit'];
    $_SESSION['gold'] = $player['gold'];
    $_SESSION['credits'] = $player['credits'];
    $_SESSION['current_floor'] = $player['current_floor'];
    $_SESSION['level'] = $player['level'];
    $_SESSION['class'] = $player['class'];
    
    echo json_encode([
        'current_hp' => $player['current_hp'],
        'max_hp' => $player['max_hp'],
        'current_mp' => $player['current_mp'],
        'max_mp' => $player['max_mp'],
        'exp' => $player['exp'],
        'max_exp' => $required_exp,
        'energy' => $player['energy'],
        'max_energy' => MAX_ENERGY,
        'atk' => $player['atk'],
        'def' => $player['def'],
        'agi' => $player['agi'],
        'crit' => $player['crit'],
        'gold' => $player['gold'],
        'credits' => $player['credits'],
        'current_floor' => $player['current_floor'],
        'level' => $player['level'],
        'class' => $player['class'],
        'username' => $player['username'],
        'avatar' => $player['avatar'],
        'is_vip' => $is_vip,
        'vip_expire' => $player['vip_expire']
    ]);
}

function healPlayer() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 50;
    $mp_cost = 10; // MP cost for heal
    
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    // Check MP
    if ($player['current_mp'] < $mp_cost) {
        echo json_encode([
            'success' => false,
            'message' => 'Not enough MP!'
        ]);
        return;
    }
    
    // Calculate heal amount
    $max_heal = $player['max_hp'] - $player['current_hp'];
    $actual_heal = min($amount, $max_heal);
    
    // Update player
    $new_hp = $player['current_hp'] + $actual_heal;
    $new_mp = $player['current_mp'] - $mp_cost;
    
    $db->update('characters', [
        'current_hp' => $new_hp,
        'current_mp' => $new_mp
    ], 'user_id = ?', [$user_id]);
    
    // Update session
    $_SESSION['current_hp'] = $new_hp;
    $_SESSION['current_mp'] = $new_mp;
    
    echo json_encode([
        'success' => true,
        'heal_amount' => $actual_heal,
        'new_hp' => $new_hp,
        'new_mp' => $new_mp,
        'stats' => [
            'current_hp' => $new_hp,
            'max_hp' => $player['max_hp'],
            'current_mp' => $new_mp,
            'max_mp' => $player['max_mp']
        ]
    ]);
}

function applyBuff() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $buff_type = $_POST['buff_type'] ?? 'attack';
    $mp_cost = 20;
    
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    // Check MP
    if ($player['current_mp'] < $mp_cost) {
        echo json_encode([
            'success' => false,
            'message' => 'Not enough MP!'
        ]);
        return;
    }
    
    // Calculate buff amount based on level
    $buff_amount = round($player['level'] * 0.5) + 5;
    $duration = 5; // 5 turns
    
    // Update player MP
    $new_mp = $player['current_mp'] - $mp_cost;
    $db->update('characters', ['current_mp' => $new_mp], 'user_id = ?', [$user_id]);
    
    // Store buff in session
    $_SESSION['current_mp'] = $new_mp;
    $_SESSION['active_buff'] = [
        'type' => $buff_type,
        'amount' => $buff_amount,
        'duration' => $duration,
        'expires' => time() + ($duration * 60) // 5 minutes
    ];
    
    echo json_encode([
        'success' => true,
        'buff_type' => $buff_type,
        'buff_amount' => $buff_amount,
        'duration' => $duration,
        'stats' => [
            'current_mp' => $new_mp,
            'max_mp' => $player['max_mp']
        ]
    ]);
}

function restPlayer() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    // Restore HP and MP
    $db->update('characters', [
        'current_hp' => $player['max_hp'],
        'current_mp' => $player['max_mp']
    ], 'user_id = ?', [$user_id]);
    
    // Update session
    $_SESSION['current_hp'] = $player['max_hp'];
    $_SESSION['current_mp'] = $player['max_mp'];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'current_hp' => $player['max_hp'],
            'max_hp' => $player['max_hp'],
            'current_mp' => $player['max_mp'],
            'max_mp' => $player['max_mp']
        ]
    ]);
}

function claimDailyReward() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $today = date('Y-m-d');
    
    // Check if already claimed today
    $last_claim = $db->fetch(
        "SELECT * FROM daily_rewards WHERE user_id = ? AND claim_date = ?",
        [$user_id, $today]
    );
    
    if ($last_claim) {
        echo json_encode([
            'success' => false,
            'message' => 'Daily reward already claimed today!'
        ]);
        return;
    }
    
    // Calculate reward based on streak
    $streak = $db->fetch(
        "SELECT COUNT(*) as streak FROM daily_rewards 
         WHERE user_id = ? AND claim_date >= DATE_SUB(?, INTERVAL 6 DAY)",
        [$user_id, $today]
    )['streak'] ?? 0;
    
    $streak++;
    
    // Base rewards
    $gold = 100 * $streak;
    $credits = 5;
    
    // Bonus for VIP
    $player = $db->fetch("SELECT vip_expire FROM users WHERE id = ?", [$user_id]);
    if (isVipActive($player['vip_expire'])) {
        $gold = round($gold * 1.5);
        $credits = round($credits * 2);
    }
    
    // Give rewards
    $character = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    $new_gold = $character['gold'] + $gold;
    $new_credits = $character['credits'] + $credits;
    
    $db->update('characters', [
        'gold' => $new_gold,
        'credits' => $new_credits
    ], 'user_id = ?', [$user_id]);
    
    // Record claim
    $db->insert('daily_rewards', [
        'user_id' => $user_id,
        'claim_date' => $today,
        'streak' => $streak,
        'gold_reward' => $gold,
        'credits_reward' => $credits
    ]);
    
    // Update session
    $_SESSION['gold'] = $new_gold;
    $_SESSION['credits'] = $new_credits;
    
    echo json_encode([
        'success' => true,
        'reward' => "{$gold} Gold and {$credits} Credits",
        'streak' => $streak,
        'gold' => $new_gold,
        'credits' => $new_credits
    ]);
}

function getOnlinePlayers() {
    global $db;
    
    // Get players active in last 5 minutes
    $five_minutes_ago = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    $online_players = $db->fetchAll("
        SELECT u.username, c.level, c.class, c.avatar 
        FROM users u 
        JOIN characters c ON u.id = c.user_id 
        WHERE u.last_login > ?
        ORDER BY c.level DESC
        LIMIT 20
    ", [$five_minutes_ago]);
    
    $html = '';
    foreach ($online_players as $player) {
        $html .= '
            <div class="friend-item">
                <img src="images/avatars/' . $player['avatar'] . '" class="friend-avatar">
                <div class="friend-info">
                    <div class="friend-name">' . htmlspecialchars($player['username']) . '</div>
                    <div class="friend-level">Lv. ' . $player['level'] . ' ' . $player['class'] . '</div>
                </div>
            </div>
        ';
    }
    
    echo $html;
}

function getRecentActivity() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo '';
        return;
    }
    
    $activities = $db->fetchAll("
        SELECT * FROM battles 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ", [$user_id]);
    
    $html = '';
    foreach ($activities as $activity) {
        $time_ago = timeAgo($activity['created_at']);
        $result_class = $activity['result'] == 'win' ? 'battle' : 'defeat';
        
        $html .= '
            <div class="activity-item ' . $result_class . '">
                <div class="activity-content">
                    <div class="activity-title">' . $activity['monster_name'] . ' - ' . ucfirst($activity['result']) . '</div>
                    <div class="activity-details">
                        ' . ($activity['exp_gained'] > 0 ? 'EXP: ' . $activity['exp_gained'] . ' • ' : '') . '
                        ' . ($activity['gold_gained'] > 0 ? 'Gold: ' . $activity['gold_gained'] : '') . '
                    </div>
                    <div class="activity-time">' . $time_ago . '</div>
                </div>
            </div>
        ';
    }
    
    echo $html;
}

function getActiveQuests() {
    // Placeholder for quest system
    $quests = [
        ['name' => 'Defeat Frenzy Boars', 'progress' => '3/10', 'reward' => '500 Gold'],
        ['name' => 'Reach Level 10', 'progress' => '7/10', 'reward' => 'Rare Weapon'],
        ['name' => 'Complete Floor 1', 'progress' => 'Complete', 'reward' => '1000 EXP']
    ];
    
    $html = '';
    foreach ($quests as $quest) {
        $html .= '
            <div class="quest-item">
                <div class="quest-name">' . $quest['name'] . '</div>
                <div class="quest-progress">Progress: ' . $quest['progress'] . '</div>
                <div class="quest-reward">Reward: ' . $quest['reward'] . '</div>
            </div>
        ';
    }
    
    echo $html;
}

function getNotifications() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo '';
        return;
    }
    
    $notifications = [];
    
    // Check for unread messages
    $unread_messages = $db->count(
        "messages",
        "receiver_id = ? AND is_read = 0",
        [$user_id]
    );
    
    if ($unread_messages > 0) {
        $notifications[] = [
            'icon' => 'fa-envelope',
            'title' => 'New Messages',
            'message' => "You have {$unread_messages} unread messages"
        ];
    }
    
    // Check for daily reward
    $today = date('Y-m-d');
    $claimed_today = $db->count(
        "daily_rewards",
        "user_id = ? AND claim_date = ?",
        [$user_id, $today]
    );
    
    if (!$claimed_today) {
        $notifications[] = [
            'icon' => 'fa-gift',
            'title' => 'Daily Reward',
            'message' => 'Claim your daily reward!'
        ];
    }
    
    // Check for guild invites
    $guild_invites = $db->count(
        "guild_invites",
        "user_id = ? AND status = 'pending'",
        [$user_id]
    );
    
    if ($guild_invites > 0) {
        $notifications[] = [
            'icon' => 'fa-users',
            'title' => 'Guild Invites',
            'message' => "You have {$guild_invites} pending guild invites"
        ];
    }
    
    // Generate HTML
    $html = '';
    foreach ($notifications as $notification) {
        $html .= '
            <div class="notification">
                <i class="fas ' . $notification['icon'] . ' notification-icon"></i>
                <div class="notification-content">
                    <strong>' . $notification['title'] . '</strong>
                    <span>' . $notification['message'] . '</span>
                </div>
            </div>
        ';
    }
    
    echo $html;
}

function regenerateEnergy() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    // Check if energy regen is needed
    if ($player['energy'] >= MAX_ENERGY) {
        echo json_encode([
            'success' => false,
            'message' => 'Energy already full'
        ]);
        return;
    }
    
    // Calculate energy to regenerate
    $last_regen = strtotime($player['energy_regen'] ?? '2000-01-01');
    $now = time();
    $seconds_passed = $now - $last_regen;
    
    // 1 energy every 4 minutes (240 seconds)
    $energy_to_add = floor($seconds_passed / ENERGY_REGEN_TIME);
    
    if ($energy_to_add > 0) {
        $new_energy = min(MAX_ENERGY, $player['energy'] + $energy_to_add);
        
        $db->update('characters', [
            'energy' => $new_energy,
            'energy_regen' => date('Y-m-d H:i:s')
        ], 'user_id = ?', [$user_id]);
        
        // Update session
        $_SESSION['energy'] = $new_energy;
        
        echo json_encode([
            'success' => true,
            'energy' => $new_energy,
            'max_energy' => MAX_ENERGY,
            'added' => $energy_to_add
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'energy' => $player['energy'],
            'max_energy' => MAX_ENERGY
        ]);
    }
}

function getEnergy() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $player = $db->fetch("SELECT energy FROM characters WHERE user_id = ?", [$user_id]);
    
    echo json_encode([
        'energy' => $player['energy'],
        'max_energy' => MAX_ENERGY
    ]);
}

function getPlayerSkills() {
    $skills = [
        [
            'id' => 4,
            'name' => 'Sword Skills',
            'type' => 'attack',
            'damage' => 40,
            'mp_cost' => 20,
            'cooldown' => 5,
            'description' => 'Advanced sword techniques'
        ],
        [
            'id' => 5,
            'name' => 'Dual Blades',
            'type' => 'attack',
            'damage' => 60,
            'mp_cost' => 30,
            'cooldown' => 10,
            'description' => 'Unleash a flurry of attacks'
        ],
        [
            'id' => 6,
            'name' => 'Healing Circle',
            'type' => 'heal',
            'amount' => 50,
            'mp_cost' => 25,
            'cooldown' => 8,
            'description' => 'Heal yourself and allies'
        ]
    ];
    
    echo json_encode($skills);
}
?>
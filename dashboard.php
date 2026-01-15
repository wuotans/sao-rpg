<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/session.php';

// Require login
requireLogin();

$db = Database::getInstance();

// Get player info with character data
$user_id = $_SESSION['user_id'];
$player = $db->fetch("
    SELECT u.*, c.* 
    FROM users u 
    LEFT JOIN characters c ON u.id = c.user_id 
    WHERE u.id = ? 
    LIMIT 1
", [$user_id]);

if (!$player) {
    header('Location: create_character.php');
    exit;
}

// Calculate stats
function isVipActive($expire_date) {
    if (!$expire_date) return false;
    return strtotime($expire_date) > time();
}

$is_vip = isVipActive($player['vip_expire']);
$days_played = floor((time() - strtotime($player['created_at'])) / (60 * 60 * 24));

// Get battle stats
$battles_won = $db->fetch("
    SELECT COUNT(*) as total 
    FROM battles 
    WHERE user_id = ? AND result = 'win'
", [$user_id])['total'];

$total_battles = $db->fetch("
    SELECT COUNT(*) as total 
    FROM battles 
    WHERE user_id = ?
", [$user_id])['total'];

// Get recent battles
$recent_battles = $db->fetchAll("
    SELECT * FROM battles 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", [$user_id]);

// Get equipped items
$equipment = $db->fetchAll("
    SELECT i.* 
    FROM inventory inv
    JOIN items i ON inv.item_id = i.id
    WHERE inv.user_id = ? AND inv.equipped = 1
    ORDER BY i.type
", [$user_id]);

// Group equipment by slot
$equipped_by_slot = [];
foreach ($equipment as $item) {
    $slot = getSlotFromItemType($item['type']);
    $equipped_by_slot[$slot] = $item;
}

function getSlotFromItemType($type) {
    $slots = [
        'weapon' => 'weapon',
        'armor' => 'armor',
        'consumable' => 'accessory'
    ];
    return $slots[$type] ?? 'accessory';
}

// Calculate EXP for next level
function getExpForLevel($level) {
    return floor(100 * pow($level, 1.5));
}

$exp_needed = getExpForLevel($player['level'] + 1);
$exp_percentage = min(100, ($player['exp'] / $exp_needed) * 100);

// Get daily rewards info
$last_daily_reward = $db->fetch("
    SELECT * FROM daily_rewards 
    WHERE user_id = ? 
    ORDER BY claim_date DESC 
    LIMIT 1
", [$user_id]);

$can_claim_daily = false;
$daily_streak = 1;

if ($last_daily_reward) {
    $last_claim_date = strtotime($last_daily_reward['claim_date']);
    $today = strtotime(date('Y-m-d'));
    
    if (date('Y-m-d', $last_claim_date) == date('Y-m-d')) {
        // Already claimed today
        $can_claim_daily = false;
        $daily_streak = $last_daily_reward['streak'];
    } elseif (date('Y-m-d', $last_claim_date) == date('Y-m-d', strtotime('-1 day'))) {
        // Claimed yesterday, continue streak
        $can_claim_daily = true;
        $daily_streak = $last_daily_reward['streak'] + 1;
    } else {
        // Streak broken
        $can_claim_daily = true;
        $daily_streak = 1;
    }
} else {
    // Never claimed
    $can_claim_daily = true;
    $daily_streak = 1;
}

// Get guild info
$guild = null;
if ($player['guild_id']) {
    $guild = $db->fetch("
        SELECT g.*, gm.rank 
        FROM guilds g
        JOIN guild_members gm ON g.id = gm.guild_id
        WHERE g.id = ? AND gm.user_id = ?
    ", [$player['guild_id'], $user_id]);
}

// Get active buffs
$active_buffs = $db->fetchAll("
    SELECT * FROM character_buffs 
    WHERE user_id = ? AND expires_at > NOW()
    ORDER BY expires_at ASC
", [$user_id]);

// Calculate HP and MP percentages
$hp_percentage = ($player['current_hp'] / $player['max_hp']) * 100;
$mp_percentage = ($player['current_mp'] / $player['max_mp']) * 100;

// Get energy info
$energy_regen = strtotime($player['energy_regen']);
$current_time = time();
$energy = $player['energy'];

// Calculate regenerated energy
if ($energy < 60) {
    $minutes_passed = floor(($current_time - $energy_regen) / 60);
    $regen_amount = floor($minutes_passed / ($is_vip ? 2 : 4)); // VIP regens faster
    
    if ($regen_amount > 0) {
        $new_energy = min(60, $energy + $regen_amount);
        if ($new_energy > $energy) {
            // Update energy in database
            $db->query("UPDATE characters SET energy = ?, energy_regen = NOW() WHERE user_id = ?", 
                      [$new_energy, $user_id]);
            $energy = $new_energy;
        }
    }
}

// Helper functions
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'agora mesmo';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' atrás';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hora' . ($hours > 1 ? 's' : '') . ' atrás';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' dia' . ($days > 1 ? 's' : '') . ' atrás';
    } else {
        return date('d/m/Y', $time);
    }
}

function timeRemaining($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $time - $now;
    
    if ($diff <= 0) return 'Expirado';
    
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'min';
    } else {
        return $minutes . 'min';
    }
}

function getRarityColor($rarity) {
    $colors = [
        'common' => '#757575',
        'uncommon' => '#4CAF50',
        'rare' => '#2196F3',
        'epic' => '#9C27B0',
        'legendary' => '#FF9800'
    ];
    
    return $colors[strtolower($rarity)] ?? '#757575';
}

// Define achievements
$achievements = [
    ['name' => 'Primeiros Passos', 'description' => 'Alcance o nível 10', 'progress' => min(100, ($player['level'] / 10) * 100)],
    ['name' => 'Caçador de Monstros', 'description' => 'Vença 100 batalhas', 'progress' => min(100, ($battles_won / 100) * 100)],
    ['name' => 'Rico', 'description' => 'Acumule 10.000 ouro', 'progress' => min(100, ($player['gold'] / 10000) * 100)],
    ['name' => 'Explorador', 'description' => 'Alcance o andar 10', 'progress' => min(100, ($player['current_floor'] / 10) * 100)],
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SAO RPG</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --sao-blue: #00b0ff;
            --sao-dark: #1a237e;
            --sao-purple: #311b92;
            --sao-red: #c62828;
            --sao-green: #2e7d32;
            --sao-yellow: #ffd600;
        }
        
        .dashboard-hero {
            background: linear-gradient(135deg, rgba(26, 35, 126, 0.9), rgba(49, 27, 146, 0.9));
            border: 3px solid var(--sao-blue);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/dashboard-bg.jpg') center/cover;
            opacity: 0.1;
            z-index: 0;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .hero-avatar {
            width: 150px;
            height: 150px;
            border: 4px solid var(--sao-blue);
            border-radius: 50%;
            background: linear-gradient(135deg, var(--sao-dark), var(--sao-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px rgba(0, 176, 255, 0.5);
            flex-shrink: 0;
            cursor: pointer;
            transition: transform 0.3s;
            color: white;
            font-size: 3rem;
        }
        
        .hero-avatar:hover {
            transform: scale(1.05);
        }
        
        .hero-info {
            flex: 1;
        }
        
        .hero-name {
            font-size: 2.5rem;
            color: #fff;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .hero-title {
            font-size: 1.2rem;
            color: #90caf9;
            margin-bottom: 20px;
        }
        
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card-hero {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid var(--sao-blue);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 176, 255, 0.3);
        }
        
        .stat-value-hero {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--sao-blue);
            margin-bottom: 5px;
        }
        
        .stat-label-hero {
            font-size: 0.9rem;
            color: #bbdefb;
        }
        
        .dashboard-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .section-card {
            background: rgba(26, 35, 126, 0.8);
            border: 2px solid var(--sao-blue);
            border-radius: 10px;
            padding: 25px;
            height: 100%;
            transition: all 0.3s;
        }
        
        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .section-title {
            color: var(--sao-blue);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(0, 176, 255, 0.3);
            padding-bottom: 10px;
        }
        
        .section-title i {
            font-size: 1.2em;
        }
        
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .equipment-slot-dash {
            background: rgba(0, 0, 0, 0.3);
            border: 2px dashed var(--sao-blue);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .equipment-slot-dash:hover {
            background: rgba(0, 176, 255, 0.1);
        }
        
        .equipment-slot-dash.filled {
            border: 2px solid #69f0ae;
            background: rgba(105, 240, 174, 0.1);
        }
        
        .slot-item-dash {
            width: 50px;
            height: 50px;
            border: 2px solid currentColor;
            border-radius: 8px;
            background: linear-gradient(135deg, #37474f, #546e7a);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            color: white;
            font-size: 1.5rem;
        }
        
        .slot-icon {
            font-size: 2rem;
            color: #90caf9;
            margin-bottom: 10px;
        }
        
        .slot-name {
            color: #fff;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .slot-empty {
            color: #90caf9;
            font-size: 0.8rem;
        }
        
        .slot-item-name {
            color: #69f0ae;
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .slot-item-rarity {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 3px;
        }
        
        .achievement-list {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .achievement-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .achievement-list::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 3px;
        }
        
        .achievement-list::-webkit-scrollbar-thumb {
            background: var(--sao-blue);
            border-radius: 3px;
        }
        
        .achievement-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            margin-bottom: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 4px solid var(--sao-yellow);
            transition: all 0.3s;
        }
        
        .achievement-item:hover {
            background: rgba(0, 0, 0, 0.4);
            transform: translateX(5px);
        }
        
        .achievement-icon {
            font-size: 1.5rem;
            color: var(--sao-yellow);
        }
        
        .achievement-progress {
            flex: 1;
        }
        
        .achievement-name {
            color: #fff;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .achievement-description {
            color: #bbdefb;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .progress-bar-achievement {
            height: 8px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .progress-fill-achievement {
            height: 100%;
            background: linear-gradient(90deg, var(--sao-green), #69f0ae);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .achievement-percent {
            color: #69f0ae;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .recent-activity-list {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .activity-item-dash {
            padding: 12px;
            margin-bottom: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
            border-left: 3px solid;
            transition: all 0.3s;
        }
        
        .activity-item-dash:hover {
            background: rgba(0, 0, 0, 0.4);
            transform: translateX(5px);
        }
        
        .activity-item-dash.win {
            border-left-color: #69f0ae;
        }
        
        .activity-item-dash.lose {
            border-left-color: var(--sao-red);
        }
        
        .activity-content {
            color: #fff;
        }
        
        .activity-title {
            font-weight: bold;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-result {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 3px;
        }
        
        .activity-result.win {
            background: rgba(105, 240, 174, 0.2);
            color: #69f0ae;
        }
        
        .activity-result.lose {
            background: rgba(239, 83, 80, 0.2);
            color: var(--sao-red);
        }
        
        .activity-details {
            display: flex;
            gap: 15px;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .activity-exp {
            color: #69f0ae;
        }
        
        .activity-gold {
            color: var(--sao-yellow);
        }
        
        .activity-time {
            color: #90caf9;
            font-size: 0.8rem;
        }
        
        .no-activity {
            text-align: center;
            padding: 30px;
            color: #90caf9;
        }
        
        .no-activity i {
            margin-bottom: 15px;
            display: block;
        }
        
        .dashboard-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .action-card {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            color: white;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .action-card.battle {
            border-color: var(--sao-red);
            background: linear-gradient(135deg, rgba(198, 40, 40, 0.1), rgba(239, 83, 80, 0.2));
        }
        
        .action-card.shop {
            border-color: var(--sao-yellow);
            background: linear-gradient(135deg, rgba(255, 214, 0, 0.1), rgba(255, 215, 64, 0.2));
        }
        
        .action-card.inventory {
            border-color: #69f0ae;
            background: linear-gradient(135deg, rgba(46, 125, 50, 0.1), rgba(105, 240, 174, 0.2));
        }
        
        .action-card.guild {
            border-color: #9c27b0;
            background: linear-gradient(135deg, rgba(156, 39, 176, 0.1), rgba(171, 71, 188, 0.2));
        }
        
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .action-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .action-desc {
            font-size: 0.9rem;
            color: #bbdefb;
        }
        
        .player-badges {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
            transition: transform 0.3s;
        }
        
        .badge:hover {
            transform: scale(1.05);
        }
        
        .badge.vip {
            background: linear-gradient(135deg, #ff6f00, #ffa000);
            color: white;
        }
        
        .badge.founder {
            background: linear-gradient(135deg, #ab47bc, #ce93d8);
            color: white;
        }
        
        .badge.legend {
            background: linear-gradient(135deg, var(--sao-yellow), #ffca28);
            color: #000;
        }
        
        .level-badge-large {
            background: linear-gradient(135deg, #2196F3, var(--sao-blue));
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: 0 3px 10px rgba(33, 150, 243, 0.3);
        }
        
        .stat-distribution {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-dist-item {
            text-align: center;
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--sao-blue);
            transition: all 0.3s;
        }
        
        .stat-dist-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 176, 255, 0.2);
        }
        
        .stat-dist-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .stat-dist-label {
            font-size: 0.9rem;
            color: #90caf9;
        }
        
        /* Resource Bars */
        .resource-bars {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .resource-bar {
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid;
            border-radius: 10px;
            padding: 15px;
        }
        
        .hp-bar {
            border-color: var(--sao-red);
        }
        
        .mp-bar {
            border-color: #2196F3;
        }
        
        .energy-bar {
            border-color: var(--sao-green);
        }
        
        .resource-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: white;
            font-weight: bold;
        }
        
        .progress-bar {
            height: 20px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.5s ease;
            position: relative;
        }
        
        .hp-bar .progress-fill {
            background: linear-gradient(90deg, #c62828, var(--sao-red));
        }
        
        .mp-bar .progress-fill {
            background: linear-gradient(90deg, #1565c0, #2196f3);
        }
        
        .energy-bar .progress-fill {
            background: linear-gradient(90deg, var(--sao-green), #4caf50);
        }
        
        /* Buffs section */
        .buffs-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .buff-item {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--sao-blue);
            border-radius: 5px;
            padding: 8px 12px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }
        
        .buff-icon {
            color: var(--sao-yellow);
        }
        
        /* Daily Reward */
        .daily-reward-section {
            margin-top: 20px;
            text-align: center;
        }
        
        .daily-reward-btn {
            background: linear-gradient(135deg, #FF9800, #FFB74D);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .daily-reward-btn:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.4);
        }
        
        .daily-reward-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Guild Info */
        .guild-info {
            background: rgba(171, 71, 188, 0.1);
            border: 1px solid #ab47bc;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            color: white;
        }
        
        .guild-name {
            color: #ce93d8;
            font-weight: bold;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .guild-tag {
            background: #ab47bc;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-name {
                justify-content: center;
            }
            
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            
            .resource-bars {
                grid-template-columns: 1fr;
            }
            
            .equipment-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .dashboard-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-actions {
                grid-template-columns: 1fr;
            }
            
            .equipment-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hero-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body class="sao-theme">
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <!-- Hero Section -->
        <div class="dashboard-hero">
            <div class="hero-content">
                <div class="hero-avatar" onclick="window.location='avatar.php'">
                    <?php 
                    $avatar_icon = 'fa-user';
                    if (strpos($player['class'], 'Swordsman') !== false) $avatar_icon = 'fa-swords';
                    elseif (strpos($player['class'], 'Mage') !== false) $avatar_icon = 'fa-hat-wizard';
                    elseif (strpos($player['class'], 'Archer') !== false) $avatar_icon = 'fa-bow-arrow';
                    ?>
                    <i class="fas <?php echo $avatar_icon; ?>"></i>
                </div>
                
                <div class="hero-info">
                    <h1 class="hero-name">
                        <?php echo htmlspecialchars($player['username']); ?>
                        <span class="level-badge-large">Nv. <?php echo $player['level']; ?></span>
                        <?php if($is_vip): ?>
                            <span class="badge vip">VIP</span>
                        <?php endif; ?>
                    </h1>
                    
                    <p class="hero-title">
                        <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($player['class']); ?> | 
                        <i class="fas fa-layer-group"></i> Andar <?php echo $player['current_floor']; ?> | 
                        <i class="fas fa-calendar-alt"></i> <?php echo $days_played; ?> dia<?php echo $days_played != 1 ? 's' : ''; ?> de jogo
                        <?php if($guild): ?>
                            | <i class="fas fa-users"></i> <span class="guild-tag">[<?php echo $guild['tag']; ?>]</span>
                        <?php endif; ?>
                    </p>
                    
                    <div class="player-badges">
                        <?php if($player['level'] >= 50): ?>
                            <span class="badge legend">Lendário</span>
                        <?php endif; ?>
                        <?php if($days_played >= 365): ?>
                            <span class="badge founder">Veterano</span>
                        <?php endif; ?>
                        <?php if($battles_won >= 1000): ?>
                            <span class="badge" style="background: linear-gradient(135deg, #9C27B0, #CE93D8); color: white;">Guerreiro</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hero-stats">
                        <div class="stat-card-hero">
                            <div class="stat-value-hero"><?php echo $battles_won; ?></div>
                            <div class="stat-label-hero">Batalhas Vencidas</div>
                        </div>
                        
                        <div class="stat-card-hero">
                            <div class="stat-value-hero"><?php echo number_format($player['exp']); ?></div>
                            <div class="stat-label-hero">EXP Total</div>
                        </div>
                        
                        <div class="stat-card-hero">
                            <div class="stat-value-hero"><?php echo number_format($player['gold']); ?></div>
                            <div class="stat-label-hero">Ouro</div>
                        </div>
                        
                        <div class="stat-card-hero">
                            <div class="stat-value-hero"><?php echo $player['credits']; ?></div>
                            <div class="stat-label-hero">Créditos</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resource Bars -->
            <div class="resource-bars">
                <div class="resource-bar hp-bar">
                    <div class="resource-label">
                        <span><i class="fas fa-heart"></i> Vida</span>
                        <span><?php echo $player['current_hp']; ?>/<?php echo $player['max_hp']; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $hp_percentage; ?>%"></div>
                    </div>
                </div>
                
                <div class="resource-bar mp-bar">
                    <div class="resource-label">
                        <span><i class="fas fa-bolt"></i> Mana</span>
                        <span><?php echo $player['current_mp']; ?>/<?php echo $player['max_mp']; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $mp_percentage; ?>%"></div>
                    </div>
                </div>
                
                <div class="resource-bar energy-bar">
                    <div class="resource-label">
                        <span><i class="fas fa-running"></i> Energia</span>
                        <span><?php echo $energy; ?>/60</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($energy / 60) * 100; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <!-- EXP Bar -->
            <div style="margin-top: 20px;">
                <div class="resource-label">
                    <span><i class="fas fa-star"></i> EXP para Próximo Nível</span>
                    <span><?php echo $player['exp']; ?>/<?php echo $exp_needed; ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $exp_percentage; ?>%; background: linear-gradient(90deg, var(--sao-green), #69f0ae);"></div>
                </div>
            </div>
        </div>
        
        <!-- Main Sections -->
        <div class="dashboard-sections">
            <!-- Equipment -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-shield-alt"></i> Equipamento
                </h3>
                
                <div class="equipment-grid">
                    <?php
                    $slots = [
                        'weapon' => ['name' => 'Arma', 'icon' => 'fa-swords'],
                        'armor' => ['name' => 'Armadura', 'icon' => 'fa-vest'],
                        'helmet' => ['name' => 'Capacete', 'icon' => 'fa-hard-hat'],
                        'gloves' => ['name' => 'Luvas', 'icon' => 'fa-hand-paper'],
                        'boots' => ['name' => 'Botas', 'icon' => 'fa-walking'],
                        'accessory' => ['name' => 'Acessório', 'icon' => 'fa-ring']
                    ];
                    
                    foreach ($slots as $slot_id => $slot):
                        $equipped = isset($equipped_by_slot[$slot_id]) ? $equipped_by_slot[$slot_id] : null;
                    ?>
                        <div class="equipment-slot-dash <?php echo $equipped ? 'filled' : ''; ?>" 
                             onclick="window.location='inventory.php?slot=<?php echo $slot_id; ?>'">
                            <?php if($equipped): ?>
                                <div class="slot-item-dash" 
                                     style="border-color: <?php echo getRarityColor($equipped['rarity']); ?>">
                                    <i class="fas fa-<?php echo $slot_id == 'weapon' ? 'sword' : ($slot_id == 'armor' ? 'shield' : 'gem'); ?>"></i>
                                </div>
                                <div class="slot-item-name"><?php echo htmlspecialchars($equipped['name']); ?></div>
                                <div class="slot-item-rarity" style="background: <?php echo getRarityColor($equipped['rarity']); ?>; color: white;">
                                    <?php echo ucfirst($equipped['rarity']); ?>
                                </div>
                            <?php else: ?>
                                <div class="slot-icon">
                                    <i class="fas <?php echo $slot['icon']; ?>"></i>
                                </div>
                                <div class="slot-name"><?php echo $slot['name']; ?></div>
                                <div class="slot-empty">Clique para equipar</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="stat-distribution">
                    <div class="stat-dist-item">
                        <div class="stat-dist-value"><?php echo $player['atk']; ?></div>
                        <div class="stat-dist-label">ATAQUE</div>
                    </div>
                    <div class="stat-dist-item">
                        <div class="stat-dist-value"><?php echo $player['def']; ?></div>
                        <div class="stat-dist-label">DEFESA</div>
                    </div>
                    <div class="stat-dist-item">
                        <div class="stat-dist-value"><?php echo $player['crit']; ?>%</div>
                        <div class="stat-dist-label">CRÍTICO</div>
                    </div>
                    <div class="stat-dist-item">
                        <div class="stat-dist-value"><?php echo $player['agi']; ?></div>
                        <div class="stat-dist-label">AGILIDADE</div>
                    </div>
                </div>
            </div>
            
            <!-- Achievements -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-trophy"></i> Conquistas
                </h3>
                
                <div class="achievement-list">
                    <?php foreach ($achievements as $achievement): ?>
                        <div class="achievement-item">
                            <div class="achievement-icon">
                                <i class="fas fa-medal"></i>
                            </div>
                            <div class="achievement-progress">
                                <div class="achievement-name"><?php echo $achievement['name']; ?></div>
                                <div class="achievement-description"><?php echo $achievement['description']; ?></div>
                                <div class="progress-bar-achievement">
                                    <div class="progress-fill-achievement" 
                                         style="width: <?php echo $achievement['progress']; ?>%"></div>
                                </div>
                            </div>
                            <div class="achievement-percent">
                                <?php echo round($achievement['progress']); ?>%
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="daily-reward-section">
                    <button class="daily-reward-btn" 
                            onclick="claimDailyReward()" 
                            <?php echo $can_claim_daily ? '' : 'disabled'; ?>>
                        <i class="fas fa-gift"></i>
                        <?php echo $can_claim_daily ? 'Recompensa Diária!' : 'Já Recebida Hoje'; ?>
                    </button>
                    <?php if($can_claim_daily): ?>
                        <p style="color: #90caf9; margin-top: 10px; font-size: 0.9rem;">
                            Sequência: <strong><?php echo $daily_streak; ?></strong> dia<?php echo $daily_streak != 1 ? 's' : ''; ?>
                        </p>
                    <?php else: ?>
                        <p style="color: #90caf9; margin-top: 10px; font-size: 0.9rem;">
                            Próxima recompensa em: <span id="daily-timer">--:--:--</span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-history"></i> Atividade Recente
                </h3>
                
                <div class="recent-activity-list">
                    <?php if(empty($recent_battles)): ?>
                        <div class="no-activity">
                            <i class="fas fa-clock fa-2x"></i>
                            <p>Nenhuma atividade recente</p>
                            <p><small>Comece batalhando contra NPCs!</small></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_battles as $battle): ?>
                            <div class="activity-item-dash <?php echo $battle['result']; ?>">
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($battle['monster_name']); ?> 
                                        <span class="activity-result <?php echo $battle['result']; ?>">
                                            <?php 
                                            if($battle['result'] == 'win') echo 'Vitória';
                                            elseif($battle['result'] == 'lose') echo 'Derrota';
                                            else echo 'Fuga';
                                            ?>
                                        </span>
                                    </div>
                                    <div class="activity-details">
                                        <?php if($battle['exp_gained'] > 0): ?>
                                            <span class="activity-exp">
                                                <i class="fas fa-star"></i> +<?php echo $battle['exp_gained']; ?> EXP
                                            </span>
                                        <?php endif; ?>
                                        <?php if($battle['gold_gained'] > 0): ?>
                                            <span class="activity-gold">
                                                <i class="fas fa-coins"></i> +<?php echo $battle['gold_gained']; ?> Ouro
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <i class="far fa-clock"></i> <?php echo timeAgo($battle['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Active Buffs -->
                <?php if(!empty($active_buffs)): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="color: var(--sao-yellow); margin-bottom: 10px; border-bottom: 1px solid rgba(255, 215, 64, 0.3); padding-bottom: 5px;">
                            <i class="fas fa-magic"></i> Buffs Ativos
                        </h4>
                        <div class="buffs-container">
                            <?php foreach($active_buffs as $buff): ?>
                                <div class="buff-item" title="<?php echo htmlspecialchars($buff['buff_type']); ?>">
                                    <span class="buff-icon"><i class="fas fa-arrow-up"></i></span>
                                    <span><?php echo ucfirst($buff['buff_type']); ?> +<?php echo $buff['buff_value']; ?>%</span>
                                    <small style="color: #90caf9; font-size: 0.8rem;">
                                        <?php echo timeRemaining($buff['expires_at']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Guild Info -->
        <?php if($guild): ?>
            <div class="section-card" style="grid-column: 1 / -1; margin-bottom: 20px;">
                <h3 class="section-title">
                    <i class="fas fa-users"></i> Informações da Guilda
                </h3>
                <div class="guild-info">
                    <div class="guild-name">
                        <?php echo htmlspecialchars($guild['name']); ?>
                        <span class="guild-tag">[<?php echo $guild['tag']; ?>]</span>
                        <span style="margin-left: auto; font-size: 0.9rem; color: #ce93d8;">
                            <?php echo ucfirst($guild['member_rank']); ?>
                        </span>
                    </div>
                    <p style="color: #bbdefb; margin: 10px 0;">
                        Nível: <strong><?php echo $guild['level']; ?></strong> | 
                        Membros: <strong><?php 
                            $member_count = $db->fetch("SELECT COUNT(*) as total FROM guild_members WHERE guild_id = ?", [$guild['id']])['total'];
                            echo $member_count . '/' . $guild['max_members'];
                        ?></strong> | 
                        EXP: <strong><?php echo number_format($guild['exp']); ?></strong>
                    </p>
                    <a href="guild.php" class="action-card" style="border-color: #ab47bc; margin-top: 10px; padding: 10px; text-align: center;">
                        <i class="fas fa-door-open"></i> Ir para a Guilda
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="dashboard-actions">
            <a href="battle.php" class="action-card battle">
                <div class="action-icon">
                    <i class="fas fa-crosshairs"></i>
                </div>
                <div class="action-title">Batalhar NPCs</div>
                <div class="action-desc">Lute contra monstros e ganhe recompensas</div>
            </a>
            
            <a href="pvp.php" class="action-card" style="border-color: #2196F3; background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(33, 150, 243, 0.2));">
                <div class="action-icon">
                    <i class="fas fa-user-ninja"></i>
                </div>
                <div class="action-title">Arena PvP</div>
                <div class="action-desc">Desafie outros jogadores</div>
            </a>
            
            <a href="shop.php" class="action-card shop">
                <div class="action-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="action-title">Loja VIP</div>
                <div class="action-desc">Itens exclusivos e bônus</div>
            </a>
            
            <a href="inventory.php" class="action-card inventory">
                <div class="action-icon">
                    <i class="fas fa-backpack"></i>
                </div>
                <div class="action-title">Inventário</div>
                <div class="action-desc">Gerencie seus itens</div>
            </a>
            
            <?php if($guild): ?>
                <a href="guild.php" class="action-card guild">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-title">Guilda</div>
                    <div class="action-desc">Atividades da guilda</div>
                </a>
            <?php else: ?>
                <a href="guild.php?action=create" class="action-card" style="border-color: #4CAF50; background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.2));">
                    <div class="action-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="action-title">Criar/Entrar Guilda</div>
                    <div class="action-desc">Junte-se a outros jogadores</div>
                </a>
            <?php endif; ?>
            
            <a href="skills.php" class="action-card" style="border-color: #9C27B0; background: linear-gradient(135deg, rgba(156, 39, 176, 0.1), rgba(156, 39, 176, 0.2));">
                <div class="action-icon">
                    <i class="fas fa-book-spells"></i>
                </div>
                <div class="action-title">Habilidades</div>
                <div class="action-desc">Aprimore suas habilidades</div>
            </a>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    // Timer for daily reward
    <?php if(!$can_claim_daily): ?>
        function updateDailyTimer() {
            const now = new Date();
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(0, 0, 0, 0);
            
            const diff = tomorrow - now;
            
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            document.getElementById('daily-timer').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        updateDailyTimer();
        setInterval(updateDailyTimer, 1000);
    <?php endif; ?>
    
    // Claim daily reward
    function claimDailyReward() {
        $.ajax({
            url: 'ajax/claim_daily.php',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    showNotification('Recompensa diária recebida! Você ganhou: ' + response.rewards.join(', '), 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(response.message || 'Falha ao receber recompensa diária', 'error');
                }
            },
            error: function() {
                showNotification('Erro de rede, por favor tente novamente', 'error');
            }
        });
    }
    
    // Show notification function
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease;
            max-width: 400px;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    
    // Auto-update energy
    function updateEnergy() {
        $.ajax({
            url: 'ajax/get_energy.php',
            method: 'GET',
            success: function(data) {
                if(data.energy !== undefined) {
                    $('.energy-bar .progress-fill').css('width', (data.energy / 60) * 100 + '%');
                    $('.energy-bar .resource-label span:last-child').text(data.energy + '/60');
                }
            }
        });
    }
    
    // Update energy every minute
    setInterval(updateEnergy, 60000);
    
    // Tooltips for equipment
    $('.equipment-slot-dash').hover(function() {
        const slot = $(this).find('.slot-name').text();
        const title = $(this).find('.slot-item-name').text() || 'Slot Vazio';
        const rarity = $(this).find('.slot-item-rarity').text() || '';
        
        let tooltip = `<strong>${title}</strong><br>`;
        if(rarity) tooltip += `<small>${rarity}</small><br>`;
        tooltip += `<em>${slot}</em>`;
        
        showTooltip($(this), tooltip);
    }, function() {
        hideTooltip();
    });
    
    // Simple tooltip functions
    let tooltip = null;
    
    function showTooltip(element, content) {
        if(tooltip) tooltip.remove();
        
        tooltip = $('<div class="custom-tooltip"></div>')
            .html(content)
            .css({
                position: 'absolute',
                background: 'rgba(0, 0, 0, 0.9)',
                color: 'white',
                padding: '10px',
                borderRadius: '5px',
                zIndex: 1000,
                border: '1px solid #00b0ff',
                maxWidth: '200px',
                fontSize: '12px'
            });
        
        const offset = element.offset();
        tooltip.css({
            top: offset.top - tooltip.outerHeight() - 10,
            left: offset.left + (element.outerWidth() / 2) - (tooltip.outerWidth() / 2)
        });
        
        $('body').append(tooltip);
    }
    
    function hideTooltip() {
        if(tooltip) {
            tooltip.remove();
            tooltip = null;
        }
    }
    
    // Show welcome notification if first visit today
    <?php
    $last_visit = $_SESSION['last_visit'] ?? null;
    $today = date('Y-m-d');
    
    if(!$last_visit || date('Y-m-d', strtotime($last_visit)) != $today):
    ?>
    $(document).ready(function() {
        setTimeout(() => {
            showNotification('Bem-vindo de volta, <?php echo htmlspecialchars($player['username']); ?>! Recompensas diárias aguardam!', 'info');
        }, 1000);
    });
    <?php 
    $_SESSION['last_visit'] = date('Y-m-d H:i:s');
    endif; 
    ?>
    </script>
</body>
</html>
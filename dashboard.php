<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// Require login
requireLogin();

// Get player info
$db = Database::getInstance();
$player = $db->fetch("
    SELECT c.*, u.username, u.vip_expire, u.created_at as join_date 
    FROM characters c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.user_id = ?
", [$_SESSION['user_id']]);

// Calculate stats
$is_vip = isVipActive($player['vip_expire']);
$days_played = floor((time() - strtotime($player['join_date'])) / (60 * 60 * 24));
$battles_won = $db->count('battles', 'user_id = ? AND result = "win"', [$_SESSION['user_id']]);
$total_exp = $player['exp'] + array_sum(array_map(function($lvl) {
    return getExpForLevel($lvl);
}, range(1, $player['level'] - 1)));

// Get recent activity
$recent_battles = $db->fetchAll("
    SELECT * FROM battles 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", [$_SESSION['user_id']]);

// Get equipment
$equipment = $db->fetchAll("
    SELECT i.equipped_slot, it.name, it.rarity, it.image
    FROM inventory i
    JOIN items it ON i.item_id = it.id
    WHERE i.user_id = ? AND i.equipped = 1
", [$_SESSION['user_id']]);

// Get achievements
$achievements = [
    ['name' => 'First Steps', 'description' => 'Reach level 10', 'progress' => min(100, ($player['level'] / 10) * 100)],
    ['name' => 'Monster Slayer', 'description' => 'Win 100 battles', 'progress' => min(100, ($battles_won / 100) * 100)],
    ['name' => 'Wealthy', 'description' => 'Earn 10,000 gold', 'progress' => min(100, ($player['gold'] / 10000) * 100)],
    ['name' => 'Explorer', 'description' => 'Reach floor 10', 'progress' => min(100, ($player['current_floor'] / 10) * 100)],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Dashboard | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/sao-theme.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .dashboard-hero {
            background: linear-gradient(135deg, rgba(26, 35, 126, 0.9), rgba(13, 71, 161, 0.9));
            border: 3px solid #00b0ff;
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
            background: url('images/interface/dashboard-bg.png') center/cover;
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
            border: 4px solid #00b0ff;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            box-shadow: 0 0 20px rgba(0, 176, 255, 0.5);
            flex-shrink: 0;
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
            border: 1px solid #00b0ff;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value-hero {
            font-size: 1.8rem;
            font-weight: bold;
            color: #00b0ff;
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
            border: 2px solid #00b0ff;
            border-radius: 10px;
            padding: 25px;
            height: 100%;
        }
        
        .section-title {
            color: #00b0ff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(0, 176, 255, 0.3);
            padding-bottom: 10px;
        }
        
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .equipment-slot-dash {
            background: rgba(0, 0, 0, 0.3);
            border: 2px dashed #00b0ff;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
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
            background-size: cover;
            background-position: center;
            margin-bottom: 10px;
        }
        
        .achievement-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .achievement-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            margin-bottom: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 4px solid #ffd740;
        }
        
        .achievement-icon {
            font-size: 1.5rem;
            color: #ffd740;
        }
        
        .achievement-progress {
            flex: 1;
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
            background: linear-gradient(90deg, #00c853, #69f0ae);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .recent-activity-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .activity-item-dash {
            padding: 12px;
            margin-bottom: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
            border-left: 3px solid;
        }
        
        .activity-item-dash.win {
            border-left-color: #69f0ae;
        }
        
        .activity-item-dash.lose {
            border-left-color: #ef5350;
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
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .action-card.battle {
            border-color: #ef5350;
        }
        
        .action-card.shop {
            border-color: #ffd740;
        }
        
        .action-card.inventory {
            border-color: #69f0ae;
        }
        
        .action-card.guild {
            border-color: #ab47bc;
        }
        
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
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
            background: linear-gradient(135deg, #ffd740, #ffca28);
            color: #000;
        }
        
        .stat-distribution {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-dist-item {
            text-align: center;
        }
        
        .stat-dist-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .stat-dist-label {
            font-size: 0.8rem;
            color: #90caf9;
        }
    </style>
</head>
<body class="sao-theme">
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <!-- Hero Section -->
        <div class="dashboard-hero">
            <div class="hero-content">
                <div class="hero-avatar" 
                     style="background-image: url('images/avatars/<?php echo $player['avatar']; ?>')"></div>
                
                <div class="hero-info">
                    <h1 class="hero-name">
                        <?php echo htmlspecialchars($player['username']); ?>
                        <span class="level-badge-large">Lv. <?php echo $player['level']; ?></span>
                        <?php if($is_vip): ?>
                            <span class="badge vip">VIP</span>
                        <?php endif; ?>
                    </h1>
                    
                    <p class="hero-title">
                        <?php echo $player['class']; ?> | Floor <?php echo $player['current_floor']; ?> | 
                        Member for <?php echo $days_played; ?> days
                    </p>
                    
                    <div class="player-badges">
                        <?php if($player['level'] >= 50): ?>
                            <span class="badge legend">Legend</span>
                        <?php endif; ?>
                        <?php if($days_played >= 365): ?>
                            <span class="badge founder">1 Year Veteran</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hero-stats">
                        <div class="stat-card-hero">
                            <div class="stat-value-hero"><?php echo $battles_won; ?></div>
                            <div class="stat-label-hero">Battles Won</div>
                        </div>
                        
                        <div class="stat-card-hero">
                            <div class="stat-value-hero"><?php echo number_format($total_exp); ?></div>
                            <div class="stat-label-hero">Total EXP</div>
                        </div>
                        
                        <div class="stat-card-hero">
                            <div class="stat-value-hero"><?php echo number_format($player['gold']); ?></div>
                            <div class="stat-label-hero">Gold</div>
                        </div>
                        
                        <div class="stat-card-hero">
                            <div class="stat-value-hero"><?php echo $player['credits']; ?></div>
                            <div class="stat-label-hero">Credits</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Sections -->
        <div class="dashboard-sections">
            <!-- Equipment -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-shield-alt"></i> Equipment
                </h3>
                
                <div class="equipment-grid">
                    <?php
                    $slots = [
                        'weapon' => ['name' => 'Weapon', 'icon' => 'fa-swords'],
                        'armor' => ['name' => 'Armor', 'icon' => 'fa-vest'],
                        'helmet' => ['name' => 'Helmet', 'icon' => 'fa-hard-hat'],
                        'gloves' => ['name' => 'Gloves', 'icon' => 'fa-hand-paper'],
                        'boots' => ['name' => 'Boots', 'icon' => 'fa-walking'],
                        'accessory' => ['name' => 'Accessory', 'icon' => 'fa-ring']
                    ];
                    
                    foreach ($slots as $slot_id => $slot):
                        $equipped = array_filter($equipment, function($item) use ($slot_id) {
                            return $item['equipped_slot'] == $slot_id;
                        });
                        $equipped = reset($equipped);
                    ?>
                        <div class="equipment-slot-dash <?php echo $equipped ? 'filled' : ''; ?>">
                            <?php if($equipped): ?>
                                <div class="slot-item-dash" 
                                     style="background-image: url('images/items/<?php echo $equipped['image']; ?>')"></div>
                                <div class="slot-item-name"><?php echo $equipped['name']; ?></div>
                                <div class="slot-item-rarity rarity-<?php echo $equipped['rarity']; ?>">
                                    <?php echo $equipped['rarity']; ?>
                                </div>
                            <?php else: ?>
                                <div class="slot-icon">
                                    <i class="fas <?php echo $slot['icon']; ?>"></i>
                                </div>
                                <div class="slot-name"><?php echo $slot['name']; ?></div>
                                <div class="slot-empty">Empty</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="stat-distribution">
                    <div class="stat-dist-item">
                        <div class="stat-dist-value"><?php echo $player['atk']; ?></div>
                        <div class="stat-dist-label">ATK</div>
                    </div>
                    <div class="stat-dist-item">
                        <div class="stat-dist-value"><?php echo $player['def']; ?></div>
                        <div class="stat-dist-label">DEF</div>
                    </div>
                    <div class="stat-dist-item">
                        <div class="stat-dist-value"><?php echo $player['crit']; ?>%</div>
                        <div class="stat-dist-label">CRIT</div>
                    </div>
                    <div class="stat-dist-item">
                        <div class="stat-dist-value"><?php echo $player['agi']; ?></div>
                        <div class="stat-dist-label">AGI</div>
                    </div>
                </div>
            </div>
            
            <!-- Achievements -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-trophy"></i> Achievements
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
            </div>
            
            <!-- Recent Activity -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-history"></i> Recent Activity
                </h3>
                
                <div class="recent-activity-list">
                    <?php if(empty($recent_battles)): ?>
                        <div class="no-activity">
                            <i class="fas fa-clock fa-2x"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_battles as $battle): ?>
                            <div class="activity-item-dash <?php echo $battle['result']; ?>">
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo $battle['monster_name']; ?> - 
                                        <span class="activity-result <?php echo $battle['result']; ?>">
                                            <?php echo ucfirst($battle['result']); ?>
                                        </span>
                                    </div>
                                    <div class="activity-details">
                                        <?php if($battle['exp_gained'] > 0): ?>
                                            <span class="activity-exp">+<?php echo $battle['exp_gained']; ?> EXP</span>
                                        <?php endif; ?>
                                        <?php if($battle['gold_gained'] > 0): ?>
                                            <span class="activity-gold">+<?php echo $battle['gold_gained']; ?> Gold</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo timeAgo($battle['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php
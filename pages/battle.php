<?php
// pages/battle.php - VERSÃO COM LAYOUT LADO A LADO

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Require login
requireLogin();

// Iniciar sessão se não estiver
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current floor
$floor = $_SESSION['current_floor'] ?? 1;

// Load player info
$db = Database::getInstance();
$player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$_SESSION['user_id']]);

if (!$player) {
    header('Location: create_character.php');
    exit();
}

// OBTER STATUS TOTAIS (PERSONAGEM + EQUIPAMENTOS)
$playerTotalStats = getPlayerTotalStats($_SESSION['user_id']);

// Usar playerTotalStats se disponível, senão usar player básico
$displayPlayer = $playerTotalStats ?: $player;

// Inicializar progresso do monstro
if (!isset($_SESSION['monster_progress'][$floor])) {
    $_SESSION['monster_progress'][$floor] = 1;
}

$currentMonsterNumber = $_SESSION['monster_progress'][$floor];

// Gerar monstro atual
$monster = generateSpecificMonster($floor, $currentMonsterNumber);

// Se não tiver monstro na sessão, criar um
if (!isset($_SESSION['current_monster']) || 
    $_SESSION['current_monster']['monster_number'] != $currentMonsterNumber) {
    
    $_SESSION['current_monster'] = $monster;
    $_SESSION['current_monster']['current_hp'] = $monster['hp'];
    $_SESSION['current_monster']['max_hp'] = $monster['hp'];
}

// Verificar se jogador está morto
if ($player['current_hp'] <= 0) {
    header('Location: town.php?action=hospital');
    exit();
}

// Carregar habilidades do jogador
$skills = $db->fetchAll("
    SELECT s.*, us.level as skill_level 
    FROM skills s 
    LEFT JOIN user_skills us ON s.id = us.skill_id AND us.user_id = ?
    WHERE s.available = 1 
    ORDER BY s.required_level ASC, s.id ASC
", [$_SESSION['user_id']]);

// Se não tiver habilidades, pegar as básicas
if (empty($skills)) {
    $skills = $db->fetchAll("
        SELECT * FROM skills 
        WHERE required_level = 1 
        AND available = 1 
        ORDER BY id ASC 
        LIMIT 4
    ");
}

// Calcular informações das habilidades
foreach ($skills as &$skill) {
    $skillLevel = $skill['skill_level'] ?? 1;
    $skillInfo = getSkillDisplayInfo($skill, $playerTotalStats ?: $player, $skillLevel);
    
    $skill['display_info'] = $skillInfo;
    $skill['calculated_damage'] = [
        'min' => $skillInfo['damage_min'],
        'max' => $skillInfo['damage_max'],
        'crit_min' => $skillInfo['crit_damage_min'],
        'crit_max' => $skillInfo['crit_damage_max']
    ];
    $skill['crit_chance'] = $skillInfo['crit_chance'];
    $skill['actual_mp_cost'] = $skillInfo['mp_cost'];
}

// Determinar ATK e DEF para exibição
$displayATK = $playerTotalStats ? $playerTotalStats['atk'] : $player['atk'];
$displayDEF = $playerTotalStats ? $playerTotalStats['def'] : $player['def'];
$displayAGI = $playerTotalStats ? $playerTotalStats['agi'] : $player['agi'];
$displayCRIT = $playerTotalStats ? $playerTotalStats['crit'] : $player['crit'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Battle - Floor <?php echo $floor; ?> | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/sao-theme.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/combat.js" defer></script>
    <style>
        /* Battle-specific styles */
        .battle-container {
            min-height: 80vh;
        }
        
        .combat-effects {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
        }
        
        .damage-popup {
            position: absolute;
            font-family: 'Orbitron', sans-serif;
            font-weight: bold;
            font-size: 24px;
            text-shadow: 2px 2px 0 #000;
            animation: popup 1s ease-out;
            pointer-events: none;
            z-index: 100;
        }
        
        @keyframes popup {
            0% {
                transform: translateY(0);
                opacity: 1;
            }
            100% {
                transform: translateY(-100px);
                opacity: 0;
            }
        }
        
        .damage-popup.critical {
            color: #ffd740;
            font-size: 32px;
            animation: critical-popup 1.2s ease-out;
        }
        
        @keyframes critical-popup {
            0% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
            50% {
                transform: translateY(-50px) scale(1.5);
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) scale(1);
                opacity: 0;
            }
        }
        
        .heal-popup {
            color: #69f0ae;
            animation: heal-popup 1s ease-out;
        }
        
        @keyframes heal-popup {
            0% {
                transform: translateY(0);
                opacity: 1;
            }
            100% {
                transform: translateY(-80px);
                opacity: 0;
            }
        }
        
        /* NOVO: Layout lado a lado */
        .battle-arena-sidebyside {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 40px;
            margin: 30px 0;
            padding: 20px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 15px;
            border: 2px solid #00b0ff;
            box-shadow: 0 0 20px rgba(0, 176, 255, 0.3);
        }
        
        .combatant-side {
            flex: 1;
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .player-side {
            background: linear-gradient(135deg, rgba(0, 176, 255, 0.1), rgba(0, 176, 255, 0.05));
            border: 2px solid #00b0ff;
        }
        
        .monster-side {
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.1), rgba(244, 67, 54, 0.05));
            border: 2px solid #f44336;
        }
        
        .vs-separator {
            width: 100px;
            text-align: center;
            font-size: 2em;
            color: #ffd740;
            font-weight: bold;
            text-shadow: 0 0 10px #ffd740;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .combatant-avatar-large {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            border: 4px solid;
            background-size: cover;
            background-position: center;
            background-color: #222;
        }
        
        .player-avatar-large {
            border-color: #00b0ff;
            background-image: url('../images/avatars/<?php echo $player['avatar']; ?>');
            box-shadow: 0 0 20px rgba(0, 176, 255, 0.5);
        }
        
        .monster-avatar-large {
            border-color: #f44336;
            background-image: url('../images/monsters/monster_<?php echo $floor; ?>.png');
            box-shadow: 0 0 20px rgba(244, 67, 54, 0.5);
        }
        
        .combatant-name-large {
            font-size: 1.8em;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .player-name-large {
            color: #00b0ff;
            text-shadow: 0 0 10px rgba(0, 176, 255, 0.5);
        }
        
        .monster-name-large {
            color: #f44336;
            text-shadow: 0 0 10px rgba(244, 67, 54, 0.5);
        }
        
        .combatant-level-large {
            font-size: 1.2em;
            color: #ffd740;
            margin-bottom: 20px;
        }
        
        .health-bar-large {
            height: 25px;
            background: #222;
            border-radius: 12px;
            overflow: hidden;
            margin: 10px 0;
            position: relative;
        }
        
        .hp-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff0000, #ff6666);
            transition: width 0.5s ease;
        }
        
        .mp-fill {
            height: 100%;
            background: linear-gradient(90deg, #0066ff, #66aaff);
            transition: width 0.5s ease;
        }
        
        .hp-text, .mp-text {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 2px #000;
            font-size: 0.9em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 20px;
            text-align: left;
        }
        
        .stat-item {
            padding: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
            font-size: 0.9em;
        }
        
        .stat-label {
            color: #90caf9;
            font-size: 0.8em;
        }
        
        .stat-value {
            color: white;
            font-weight: bold;
        }
        
        /* Skills Grid melhorada */
        .skills-grid-enhanced {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .skill-card-enhanced {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(102, 126, 234, 0.05));
            border: 1px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .skill-card-enhanced:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            border-color: #ffd740;
        }
        
        .skill-card-enhanced.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: linear-gradient(135deg, rgba(100, 100, 100, 0.1), rgba(100, 100, 100, 0.05));
            border-color: #666;
        }
        
        .skill-card-enhanced.disabled:hover {
            transform: none;
            box-shadow: none;
            border-color: #666;
        }
        
        .skill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .skill-name {
            font-size: 1.3em;
            color: #667eea;
            font-weight: bold;
        }
        
        .skill-level {
            background: #ffd740;
            color: #000;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .skill-type {
            display: inline-block;
            padding: 3px 8px;
            background: rgba(102, 126, 234, 0.2);
            border-radius: 5px;
            font-size: 0.8em;
            margin-bottom: 10px;
        }
        
        .skill-type.attack { background: rgba(244, 67, 54, 0.2); color: #f44336; }
        .skill-type.heal { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        .skill-type.buff { background: rgba(255, 152, 0, 0.2); color: #FF9800; }
        .skill-type.debuff { background: rgba(156, 39, 176, 0.2); color: #9C27B0; }
        
        .skill-description {
            color: #bbdefb;
            margin-bottom: 15px;
            font-size: 0.95em;
            line-height: 1.4;
        }
        
        .skill-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
        }
        
        .skill-stat {
            text-align: center;
        }
        
        .stat-title {
            color: #90caf9;
            font-size: 0.8em;
            margin-bottom: 3px;
        }
        
        .stat-number {
            color: white;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .crit-stat {
            color: #ffd740;
        }
        
        .skill-cost {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .mp-cost {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #66aaff;
            font-weight: bold;
        }
        
        .cooldown {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #ff9800;
            font-weight: bold;
        }
        
        .skill-unlock {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 215, 64, 0.9);
            color: #000;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        /* Monsters grid */
        .floor-monsters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin: 20px 0;
            padding: 15px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            border: 1px solid #444;
        }
        
        .monster-slot {
            text-align: center;
            padding: 10px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 5px;
            border: 2px solid #444;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .monster-slot:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 176, 255, 0.3);
        }
        
        .monster-slot.current {
            border-color: #00b0ff;
            background: rgba(0, 176, 255, 0.2);
            box-shadow: 0 0 10px rgba(0, 176, 255, 0.5);
        }
        
        .monster-slot.defeated {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.2);
        }
        
        .monster-slot.boss {
            border-color: #f44336;
            background: rgba(244, 67, 54, 0.2);
        }
        
        .monster-slot.boss.current {
            border-color: #ffd740;
            background: rgba(255, 215, 64, 0.2);
            box-shadow: 0 0 15px rgba(255, 215, 64, 0.5);
        }
        
        .monster-number {
            font-size: 0.9em;
            color: #aaa;
            margin-bottom: 5px;
        }
        
        .monster-name-small {
            font-size: 0.9em;
            margin: 5px 0;
            font-weight: bold;
        }
        
        .boss-label {
            color: #f44336;
            font-weight: bold;
            font-size: 0.8em;
        }
        
        .checkmark {
            position: absolute;
            top: 5px;
            right: 5px;
            color: #4CAF50;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .battle-arena-sidebyside {
                flex-direction: column;
                gap: 20px;
            }
            
            .vs-separator {
                width: 100%;
                height: 60px;
                order: 2;
            }
            
            .player-side, .monster-side {
                width: 100%;
                min-height: 350px;
            }
        }
        
        @media (max-width: 768px) {
            .skills-grid-enhanced {
                grid-template-columns: 1fr;
            }
            
            .floor-monsters-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>
</head>
<body class="sao-theme">
    <?php include '../includes/header.php'; ?>
    
    <main class="container">
        <div class="battle-container">
            <!-- Floor Navigation -->
            <div class="floor-info">
                <div>
                    <h2>Floor <?php echo $floor; ?> - Dungeon</h2>
                    <p class="floor-description">Battle monsters to earn experience and loot</p>
                    <p>Progress: <?php echo getFloorCompletion($floor); ?>% complete</p>
                </div>
                <div class="floor-rewards">
                    <div class="reward-item">
                        <div class="reward-icon gold">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="reward-amount">+<?php echo $monster['gold']; ?> Gold</div>
                    </div>
                    <div class="reward-item">
                        <div class="reward-icon exp">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="reward-amount">+<?php echo $monster['exp']; ?> EXP</div>
                    </div>
                    <?php if ($currentMonsterNumber == 11): ?>
                    <div class="reward-item">
                        <div class="reward-icon" style="color: #ffd740;">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="reward-amount" style="color: #ffd740;">BOSS REWARDS</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Energy Warning -->
            <?php if (($player['energy'] ?? 0) < 5): ?>
                <div class="energy-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Low Energy! You have <?php echo $player['energy'] ?? 0; ?> energy remaining.
                </div>
            <?php endif; ?>
            
            <!-- Lista de monstros do piso -->
            <div class="floor-monsters-grid">
                <?php for ($i = 1; $i <= 11; $i++):
                    $isCurrent = ($i == $currentMonsterNumber);
                    $isDefeated = isset($_SESSION['monster_progress'][$floor]) && $i < $_SESSION['monster_progress'][$floor];
                    $isBoss = ($i == 11);
                    
                    $monsterName = generateMonsterName($floor, $i);
                    $monsterClass = '';
                    if ($isBoss) $monsterClass = 'boss';
                    if ($isCurrent) $monsterClass .= ' current';
                    if ($isDefeated) $monsterClass .= ' defeated';
                ?>
                <div class="monster-slot <?php echo trim($monsterClass); ?>" 
                     onclick="selectMonster(<?php echo $i; ?>)"
                     title="<?php echo $isBoss ? 'Floor Boss - Special Rewards' : 'Monster ' . $i . ' - ' . $monsterName; ?>">
                    
                    <div class="monster-number">
                        <?php if ($isBoss): ?>
                            <span class="boss-label">BOSS</span>
                        <?php else: ?>
                            #<?php echo $i; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="monster-name-small">
                        <?php echo $monsterName; ?>
                    </div>
                    
                    <?php if ($isDefeated): ?>
                        <div class="checkmark">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isCurrent): ?>
                        <div style="color: #00b0ff; margin-top: 5px; font-size: 0.8em;">
                            <i class="fas fa-crosshairs"></i> Current
                        </div>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            
            <!-- Battle Arena LADO A LADO -->
            <div class="battle-header">
                <h2><i class="fas fa-crosshairs"></i> Battle Arena</h2>
                <p class="floor-indicator">Current Floor: <strong><?php echo $floor; ?></strong> | Monster: <strong id="current-monster-number"><?php echo $currentMonsterNumber; ?></strong>/11</p>
            </div>
            
            <div class="battle-arena-sidebyside">
                <!-- Player Side -->
                <div class="combatant-side player-side">
                    <div>
                        <div class="combatant-avatar-large player-avatar-large"></div>
                        <div class="combatant-name-large player-name-large"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="combatant-level-large">Level <?php echo $player['level']; ?> <?php echo $player['class']; ?></div>
                        
                        <!-- HP Bar -->
                        <div class="health-info">
                            <div style="color: #90caf9; margin-bottom: 5px;">HP</div>
                            <div class="health-bar-large">
                                <div class="hp-fill" id="player-hp-fill" style="width: <?php echo ($player['current_hp'] / $player['max_hp']) * 100; ?>%"></div>
                                <div class="hp-text" id="player-hp-text"><?php echo $player['current_hp']; ?>/<?php echo $player['max_hp']; ?></div>
                            </div>
                        </div>
                        
                        <!-- MP Bar -->
                        <div class="mana-info" style="margin-top: 15px;">
                            <div style="color: #90caf9; margin-bottom: 5px;">MP</div>
                            <div class="health-bar-large">
                                <div class="mp-fill" id="player-mp-fill" style="width: <?php echo ($player['current_mp'] / $player['max_mp']) * 100; ?>%"></div>
                                <div class="hp-text" id="player-mp-text"><?php echo $player['current_mp']; ?>/<?php echo $player['max_mp']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Player Stats -->
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-label">ATK</div>
                            <div class="stat-value"><?php echo $displayATK; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">DEF</div>
                            <div class="stat-value"><?php echo $displayDEF; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">AGI</div>
                            <div class="stat-value"><?php echo $displayAGI; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">CRIT</div>
                            <div class="stat-value"><?php echo number_format($displayCRIT, 1); ?>%</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Energy</div>
                            <div class="stat-value" id="player-energy-display"><?php echo $player['energy']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Floor</div>
                            <div class="stat-value"><?php echo $player['current_floor']; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- VS Separator -->
                <div class="vs-separator">
                    <div>VS</div>
                </div>
                
                <!-- Monster Side -->
                <div class="combatant-side monster-side">
                    <div>
                        <div class="combatant-avatar-large monster-avatar-large"></div>
                        <div class="combatant-name-large monster-name-large"><?php echo htmlspecialchars($monster['name']); ?></div>
                        <div class="combatant-level-large">Floor <?php echo $monster['floor']; ?><?php echo $currentMonsterNumber == 11 ? ' (BOSS)' : ''; ?></div>
                        
                        <!-- Monster HP Bar -->
                        <div class="health-info">
                            <div style="color: #90caf9; margin-bottom: 5px;">HP</div>
                            <div class="health-bar-large">
                                <div class="hp-fill" id="monster-hp-fill" style="width: <?php echo ($monster['current_hp'] / $monster['max_hp']) * 100; ?>%"></div>
                                <div class="hp-text" id="monster-hp-text"><?php echo $monster['current_hp']; ?>/<?php echo $monster['max_hp']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monster Stats -->
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-label">ATK</div>
                            <div class="stat-value"><?php echo $monster['atk']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">DEF</div>
                            <div class="stat-value"><?php echo $monster['def']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">EXP</div>
                            <div class="stat-value"><?php echo $monster['exp']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Gold</div>
                            <div class="stat-value"><?php echo $monster['gold']; ?></div>
                        </div>
                        <div class="stat-item" style="grid-column: span 2;">
                            <div class="stat-label">Type</div>
                            <div class="stat-value"><?php echo $currentMonsterNumber == 11 ? 'BOSS' : 'Regular'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Combat Effects Layer -->
            <div class="combat-effects" id="combatEffects"></div>
            
            <!-- Skills Grid Melhorada -->
            <div class="skills-container">
                <h3><i class="fas fa-magic"></i> Skills & Abilities</h3>
                <div class="skills-grid-enhanced" id="skillsGrid">
                    <?php foreach ($skills as $skill): 
                        $canUse = $player['current_mp'] >= $skill['actual_mp_cost'];
                        $skillLevel = $skill['skill_level'] ?? 1;
                        $maxLevel = 10;
                        $isUnlocked = ($player['level'] >= $skill['required_level']);
                        $isMaxLevel = ($skillLevel >= $maxLevel);
                        $skillDisplay = $skill['display_info'] ?? [];
                    ?>
                    <div class="skill-card-enhanced <?php echo !$canUse ? 'disabled' : ''; ?> <?php echo !$isUnlocked ? 'disabled' : ''; ?>" 
                         onclick="<?php echo $isUnlocked && $canUse ? 'useSkill(' . $skill['id'] . ')' : ''; ?>"
                         title="<?php echo !$isUnlocked ? 'Requires Level ' . $skill['required_level'] : ($canUse ? 'Use ' . $skill['name'] : 'Not enough MP'); ?>">
                        
                        <?php if (!$isUnlocked): ?>
                            <div class="skill-unlock">
                                <i class="fas fa-lock"></i> Level <?php echo $skill['required_level']; ?>+
                            </div>
                        <?php endif; ?>
                        
                        <div class="skill-header">
                            <div class="skill-name"><?php echo $skill['name']; ?></div>
                            <div class="skill-level">Lv. <?php echo $skillLevel; ?><?php echo $isMaxLevel ? ' (MAX)' : ''; ?></div>
                        </div>
                        
                        <div class="skill-type <?php echo strtolower($skill['type']); ?>">
                            <?php echo strtoupper($skill['type']); ?>
                        </div>
                        
                        <div class="skill-description">
                            <?php echo $skill['description']; ?>
                        </div>
                        
                        <div class="skill-stats">
                            <?php if ($skill['type'] == 'attack' || $skill['type'] == 'heal'): ?>
                            <div class="skill-stat">
                                <div class="stat-title">DAMAGE/HEAL</div>
                                <div class="stat-number">
                                    <?php echo $skill['display_info']['damage_min'] ?? 0; ?>-<?php echo $skill['display_info']['damage_max'] ?? 0; ?>
                                </div>
                            </div>
                            
                            <div class="skill-stat">
                                <div class="stat-title">CRITICAL</div>
                                <div class="stat-number crit-stat">
                                    <?php echo $skill['display_info']['crit_damage_min'] ?? 0; ?>-<?php echo $skill['display_info']['crit_damage_max'] ?? 0; ?>
                                    <small>(<?php echo number_format($skill['display_info']['crit_chance'] ?? 0, 1); ?>%)</small>
                                </div>
                            </div>
                            
                            <div class="skill-stat">
                                <div class="stat-title">CRIT MULTIPLIER</div>
                                <div class="stat-number">
                                    <?php echo number_format($skill['display_info']['crit_multiplier'] ?? 2.0, 1); ?>x
                                </div>
                            </div>
                            
                            <div class="skill-stat">
                                <div class="stat-title">ACCURACY</div>
                                <div class="stat-number">
                                    <?php echo $skill['display_info']['accuracy'] ?? 95; ?>%
                                </div>
                            </div>
                            
                            <?php if (($skill['element'] ?? 'physical') != 'physical'): ?>
                            <div class="skill-stat" style="grid-column: span 2;">
                                <div class="stat-title">ELEMENT</div>
                                <div class="stat-number" style="color: <?php 
                                    $elementColor = '#ffffff';
                                    switch($skill['element'] ?? 'physical') {
                                        case 'fire': $elementColor = '#ff4444'; break;
                                        case 'ice': $elementColor = '#44aaff'; break;
                                        case 'lightning': $elementColor = '#ffdd44'; break;
                                        case 'holy': $elementColor = '#ffff88'; break;
                                        case 'dark': $elementColor = '#aa44ff'; break;
                                        default: $elementColor = '#ffffff';
                                    }
                                    echo $elementColor;
                                ?>;">
                                    <?php echo strtoupper($skill['element'] ?? 'PHYSICAL'); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php elseif ($skill['type'] == 'buff' || $skill['type'] == 'debuff'): ?>
                            <div class="skill-stat" style="grid-column: span 2;">
                                <div class="stat-title">EFFECT</div>
                                <div class="stat-number">
                                    <?php echo $skill['buff_amount'] ?? $skill['effect_amount'] ?? 0; ?>% <?php echo $skill['buff_stat'] ?? $skill['effect_stat'] ?? ''; ?> for <?php echo $skill['buff_duration'] ?? $skill['effect_duration'] ?? 0; ?> turns
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="skill-cost">
                            <div class="mp-cost">
                                <i class="fas fa-bolt"></i>
                                <span id="skill-mp-<?php echo $skill['id']; ?>"><?php echo $skill['actual_mp_cost']; ?></span> MP
                            </div>
                            
                            <?php if ($skill['cooldown'] > 0): ?>
                            <div class="cooldown">
                                <i class="fas fa-clock"></i>
                                <?php echo $skill['cooldown']; ?> turns
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Skill Trainer Link -->
                    <div class="skill-card-enhanced" onclick="window.location.href='skill_trainer.php'" style="cursor: pointer; border-color: #ffd740;">
                        <div style="text-align: center; padding: 40px 20px;">
                            <div style="font-size: 3em; color: #ffd740; margin-bottom: 20px;">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div style="font-size: 1.5em; color: #ffd740; margin-bottom: 10px;">
                                Learn New Skills
                            </div>
                            <div style="color: #bbdefb;">
                                Visit the Skill Trainer in town to unlock powerful new abilities!
                            </div>
                            <div style="margin-top: 20px;">
                                <button style="background: #ffd740; color: #000; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold;">
                                    <i class="fas fa-map-marker-alt"></i> Go to Trainer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Battle Controls -->
            <div class="battle-controls" id="battleControls">
                <button class="battle-btn attack-btn" onclick="performAttack()">
                    <i class="fas fa-swords"></i> Basic Attack
                    <small>(No MP cost)</small>
                </button>
                <button class="battle-btn defend-btn" onclick="performDefend()">
                    <i class="fas fa-shield-alt"></i> Defend
                    <small>(Reduce damage)</small>
                </button>
                <button class="battle-btn flee-btn" onclick="performFlee()">
                    <i class="fas fa-running"></i> Flee
                    <small>(70% chance)</small>
                </button>
                <button class="battle-btn auto-btn" onclick="toggleAutoBattle()">
                    <i class="fas fa-robot"></i> Auto Battle
                    <small>(Uses Basic Attack)</small>
                </button>
            </div>
            
            <!-- Battle Log -->
            <div class="battle-log-container">
                <h3><i class="fas fa-scroll"></i> Battle Log</h3>
                <div class="battle-log" id="battleLog">
                    <div class="log-entry system">
                        <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                        Battle started against <?php echo $monster['name']; ?>!
                    </div>
                </div>
            </div>
            
            <!-- Floor Navigation -->
            <div class="floor-navigation">
                <?php if ($floor > 1): ?>
                    <button class="floor-btn" onclick="changeFloor(<?php echo $floor - 1; ?>)">
                        <i class="fas fa-arrow-left"></i> Floor <?php echo $floor - 1; ?>
                    </button>
                <?php endif; ?>
                
                <?php 
                // Mostrar pisos disponíveis
                $maxFloor = $player['current_floor'];
                $start = max(1, $floor - 2);
                $end = min($maxFloor, $floor + 2);
                
                for ($i = $start; $i <= $end; $i++):
                    if ($i != $floor):
                ?>
                        <button class="floor-btn" onclick="changeFloor(<?php echo $i; ?>)">
                            Floor <?php echo $i; ?>
                        </button>
                    <?php else: ?>
                        <button class="floor-btn active">
                            Floor <?php echo $i; ?>
                        </button>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($floor < $maxFloor): ?>
                    <button class="floor-btn" onclick="changeFloor(<?php echo $floor + 1; ?>)">
                        Floor <?php echo $floor + 1; ?> <i class="fas fa-arrow-right"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Battle Help Modal -->
    <div class="modal" id="battleHelpModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2><i class="fas fa-question-circle"></i> Battle Help</h2>
            <div class="help-content">
                <h3>Combat Basics</h3>
                <p>Battles in SAO RPG are turn-based. You and the monster take turns attacking.</p>
                
                <h3>Skill System</h3>
                <ul>
                    <li><strong>Skill Levels:</strong> Skills can be leveled up to increase their power</li>
                    <li><strong>MP Cost:</strong> Each skill costs MP to use</li>
                    <li><strong>Critical Chance:</strong> Based on your CRIT stat + skill bonus</li>
                    <li><strong>Damage Range:</strong> Skills have minimum and maximum damage</li>
                    <li><strong>Cooldowns:</strong> Some skills have turn cooldowns</li>
                </ul>
                
                <h3>Stats Explanation</h3>
                <ul>
                    <li><strong>ATK:</strong> Increases your damage with attacks and skills</li>
                    <li><strong>DEF:</strong> Reduces damage taken from enemy attacks</li>
                    <li><strong>AGI:</strong> Increases your chance to dodge attacks</li>
                    <li><strong>CRIT:</strong> Increases critical hit chance for all attacks</li>
                    <li><strong>MP:</strong> Used to cast skills, regenerates over time</li>
                </ul>
                
                <h3>Floor System</h3>
                <ul>
                    <li>Each floor has 10 regular monsters + 1 boss</li>
                    <li>Defeat all 11 to unlock the next floor</li>
                    <li>Bosses drop special rewards and have better stats</li>
                    <li>You can replay any floor you've unlocked</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Help Button -->
    <div class="battle-help" onclick="$('#battleHelpModal').fadeIn()">
        <i class="fas fa-question"></i>
    </div>
    
    <script>
        // Variáveis globais
        let autoBattleActive = false;
        let autoBattleInterval;
        let currentMonsterNumber = <?php echo $currentMonsterNumber; ?>;
        let playerMaxHP = <?php echo $player['max_hp']; ?>;
        let playerMaxMP = <?php echo $player['max_mp']; ?>;
        let monsterMaxHP = <?php echo $monster['max_hp']; ?>;
        
        // Inicializar quando a página carregar
        $(document).ready(function() {
            // Verificar energia
            if (parseInt($('#player-energy-display').text()) <= 0) {
                addLog('Warning: You have no energy! Visit town to regenerate.', 'system');
            }
            
            // Inicializar tooltips para habilidades
            initializeSkillTooltips();
        });
        
        // Inicializar tooltips para habilidades
        function initializeSkillTooltips() {
            $('.skill-card-enhanced').hover(
                function() {
                    // Mostrar tooltip com informações detalhadas
                    const $card = $(this);
                    if (!$card.hasClass('disabled')) {
                        $card.css('transform', 'translateY(-5px)');
                    }
                },
                function() {
                    const $card = $(this);
                    $card.css('transform', '');
                }
            );
        }
        
        // Ataque básico
        function performAttack() {
            if (autoBattleActive) return;
            
            $.ajax({
                url: '../api/battle.php?action=attack',
                method: 'POST',
                data: { skill_id: 0 },
                success: function(data) {
                    try {
                        handleBattleResult(JSON.parse(data));
                    } catch (e) {
                        console.error('Error in attack:', e);
                        addLog('Error in battle system', 'system');
                    }
                },
                error: function() {
                    addLog('Failed to connect to battle server', 'system');
                }
            });
        }
        
        // Usar habilidade
        function useSkill(skillId) {
            if (autoBattleActive) return;
            
            // Verificar se tem MP suficiente
            const mpCost = parseInt($('#skill-mp-' + skillId).text());
            const currentMP = parseInt($('#player-mp-text').text().split('/')[0]);
            
            if (currentMP < mpCost) {
                addLog('Not enough MP to use this skill!', 'system');
                return;
            }
            
            $.ajax({
                url: '../api/battle.php?action=attack',
                method: 'POST',
                data: { skill_id: skillId },
                success: function(data) {
                    try {
                        handleBattleResult(JSON.parse(data));
                    } catch (e) {
                        console.error('Error using skill:', e);
                        addLog('Error using skill', 'system');
                    }
                },
                error: function() {
                    addLog('Failed to connect to battle server', 'system');
                }
            });
        }
        
        // Defender
        function performDefend() {
            if (autoBattleActive) return;
            
            addLog('You take a defensive stance! Damage reduced for next attack.', 'player');
            // Implementar lógica de defesa no servidor
        }
        
        // Fugir
        function performFlee() {
            if (confirm('Are you sure you want to flee from battle?')) {
                $.ajax({
                    url: '../api/battle.php?action=flee',
                    method: 'POST',
                    success: function(data) {
                        try {
                            const result = JSON.parse(data);
                            if (result.success) {
                                addLog('Successfully fled from battle!', 'system');
                                setTimeout(() => {
                                    window.location.href = 'map.php';
                                }, 1500);
                            } else {
                                addLog(result.message || 'Failed to flee!', 'system');
                                if (result.new_hp) {
                                    updatePlayerHP(result.new_hp);
                                }
                            }
                        } catch (e) {
                            console.error('Error fleeing:', e);
                        }
                    }
                });
            }
        }
        
        // Batalha automática
        function toggleAutoBattle() {
            if (autoBattleActive) {
                stopAutoBattle();
            } else {
                startAutoBattle();
            }
        }
        
        function startAutoBattle() {
            autoBattleActive = true;
            $('.auto-btn').html('<i class="fas fa-stop"></i> STOP AUTO');
            addLog('Auto battle started!', 'system');
            
            autoBattleInterval = setInterval(() => {
                if (!autoBattleActive) return;
                
                performAttack();
            }, 2000);
        }
        
        function stopAutoBattle() {
            autoBattleActive = false;
            clearInterval(autoBattleInterval);
            $('.auto-btn').html('<i class="fas fa-robot"></i> Auto Battle');
            addLog('Auto battle stopped.', 'system');
        }
        
        // Processar resultado da batalha
        function handleBattleResult(result) {
            if (!result.success) {
                addLog(result.message || 'Battle failed', 'system');
                return;
            }
            
            // Adicionar logs
            if (result.log && result.log.length > 0) {
                result.log.forEach(log => {
                    addLog(log.text, log.type);
                });
            }
            
            // Atualizar jogador
            if (result.player) {
                updatePlayerStats(result.player);
            }
            
            // Atualizar monstro
            if (result.monster_dead) {
                // Monstro morto
                addLog('Monster defeated!', 'victory');
                
                // Atualizar recompensas
                if (result.exp_gained) {
                    addLog(`Gained ${result.exp_gained} EXP and ${result.gold_gained} Gold!`, 'victory');
                }
                
                // Mostrar drops
                if (result.drops && result.drops.length > 0) {
                    showDrops(result.drops);
                }
                
                // Verificar se derrotou o boss
                if (result.is_boss) {
                    addLog('FLOOR BOSS DEFEATED! Floor completed!', 'victory');
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    // Carregar próximo monstro
                    setTimeout(loadNextMonster, 2000);
                }
            } else if (result.monster_hp !== undefined) {
                // Atualizar HP do monstro
                updateMonsterHP(result.monster_hp);
            }
            
            // Verificar se jogador morreu
            if (result.player_dead) {
                addLog('You were defeated!', 'defeat');
                stopAutoBattle();
                setTimeout(() => {
                    window.location.href = 'town.php?action=hospital';
                }, 3000);
            }
        }
        
        // Carregar próximo monstro
        function loadNextMonster() {
            $.ajax({
                url: '../api/battle.php?action=next_monster',
                method: 'POST',
                success: function(data) {
                    try {
                        const result = JSON.parse(data);
                        if (result.success) {
                            currentMonsterNumber = result.monster_number;
                            $('#current-monster-number').text(currentMonsterNumber);
                            updateMonsterDisplay(result.monster);
                            addLog(`Next monster: ${result.monster.name}!`, 'system');
                            
                            // Se derrotou boss, mostrar mensagem especial
                            if (result.boss_defeated) {
                                addLog('CONGRATULATIONS! New floor unlocked!', 'victory');
                            }
                        }
                    } catch (e) {
                        console.error('Error loading next monster:', e);
                    }
                }
            });
        }
        
        // Selecionar monstro específico
        function selectMonster(monsterNumber) {
            if (monsterNumber > <?php echo $currentMonsterNumber; ?>) {
                addLog('You must defeat previous monsters first!', 'system');
                return;
            }
            
            if (confirm(`Fight monster #${monsterNumber}?`)) {
                $.ajax({
                    url: '../api/battle.php?action=select_monster',
                    method: 'POST',
                    data: { monster_number: monsterNumber },
                    success: function(data) {
                        try {
                            const result = JSON.parse(data);
                            if (result.success) {
                                currentMonsterNumber = monsterNumber;
                                $('#current-monster-number').text(currentMonsterNumber);
                                updateMonsterDisplay(result.monster);
                                addLog(`Selected monster #${monsterNumber}: ${result.monster.name}`, 'system');
                            }
                        } catch (e) {
                            console.error('Error selecting monster:', e);
                        }
                    }
                });
            }
        }
        
        // Atualizar exibição do monstro
        function updateMonsterDisplay(monster) {
            $('.monster-name-large').text(monster.name);
            $('.monster-level-large').text(`Floor ${monster.floor}${monster.is_boss ? ' (BOSS)' : ''}`);
            
            // Atualizar HP
            const hpPercent = (monster.current_hp / monster.max_hp) * 100;
            $('#monster-hp-fill').css('width', hpPercent + '%');
            $('#monster-hp-text').text(monster.current_hp + '/' + monster.max_hp);
            
            // Atualizar stats
            $('.monster-side .stat-item:nth-child(1) .stat-value').text(monster.atk);
            $('.monster-side .stat-item:nth-child(2) .stat-value').text(monster.def);
            $('.monster-side .stat-item:nth-child(3) .stat-value').text(monster.exp);
            $('.monster-side .stat-item:nth-child(4) .stat-value').text(monster.gold);
            $('.monster-side .stat-item:nth-child(5) .stat-value').text(monster.is_boss ? 'BOSS' : 'Regular');
        }
        
        // Atualizar stats do jogador
        function updatePlayerStats(playerData) {
            if (playerData.current_hp !== undefined) {
                updatePlayerHP(playerData.current_hp);
            }
            
            if (playerData.current_mp !== undefined) {
                updatePlayerMP(playerData.current_mp);
            }
            
            if (playerData.energy !== undefined) {
                $('#player-energy-display').text(playerData.energy);
            }
            
            if (playerData.level_up) {
                addLog(`LEVEL UP! You are now level ${playerData.new_level}!`, 'victory');
                playerMaxHP = playerData.max_hp || playerMaxHP;
                playerMaxMP = playerData.max_mp || playerMaxMP;
            }
        }
        
        function updatePlayerHP(hp) {
            const hpPercent = (hp / playerMaxHP) * 100;
            $('#player-hp-fill').css('width', hpPercent + '%');
            $('#player-hp-text').text(hp + '/' + playerMaxHP);
            
            // Animação
            $('#player-hp-fill').addClass('damage-animation');
            setTimeout(() => {
                $('#player-hp-fill').removeClass('damage-animation');
            }, 300);
        }
        
        function updatePlayerMP(mp) {
            const mpPercent = (mp / playerMaxMP) * 100;
            $('#player-mp-fill').css('width', mpPercent + '%');
            $('#player-mp-text').text(mp + '/' + playerMaxMP);
            
            // Atualizar estado das habilidades
            updateSkillsAvailability(mp);
        }
        
        function updateMonsterHP(hp) {
            const hpPercent = (hp / monsterMaxHP) * 100;
            $('#monster-hp-fill').css('width', hpPercent + '%');
            $('#monster-hp-text').text(hp + '/' + monsterMaxHP);
            
            // Animação
            $('#monster-hp-fill').addClass('damage-animation');
            setTimeout(() => {
                $('#monster-hp-fill').removeClass('damage-animation');
            }, 300);
        }
        
        // Atualizar disponibilidade das habilidades baseado no MP
        function updateSkillsAvailability(currentMP) {
            $('.skill-card-enhanced').each(function() {
                const $card = $(this);
                const mpCost = parseInt($card.find('.mp-cost span').text());
                
                if (mpCost > currentMP) {
                    $card.addClass('disabled');
                    $card.attr('onclick', '');
                } else {
                    $card.removeClass('disabled');
                    const skillId = $card.attr('onclick')?.match(/useSkill\((\d+)\)/)?.[1];
                    if (skillId) {
                        $card.attr('onclick', `useSkill(${skillId})`);
                    }
                }
            });
        }
        
        // Mostrar drops
        function showDrops(drops) {
            if (!drops || drops.length === 0) return;
            
            let dropList = 'Items dropped: ';
            drops.forEach((drop, index) => {
                dropList += `${drop.name}${index < drops.length - 1 ? ', ' : ''}`;
            });
            
            addLog(dropList, 'loot');
        }
        
        // Adicionar entrada no log
        function addLog(message, type = 'system') {
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const entry = $(`
                <div class="log-entry ${type}">
                    <span class="log-time">[${time}]</span>
                    <span class="log-text">${message}</span>
                </div>
            `);
            
            $('#battleLog').append(entry);
            
            // Scroll para baixo
            const logContainer = $('#battleLog');
            logContainer.scrollTop(logContainer[0].scrollHeight);
        }
        
        // Mudar de floor
        function changeFloor(floor) {
            if (confirm(`Change to floor ${floor}?`)) {
                $.ajax({
                    url: '../api/battle.php?action=change_floor',
                    method: 'POST',
                    data: { floor: floor },
                    success: function(data) {
                        try {
                            const result = JSON.parse(data);
                            if (result.success) {
                                // Reload page to show new floor
                                location.reload();
                            }
                        } catch (e) {
                            console.error('Error changing floor:', e);
                        }
                    }
                });
            }
        }
        
        // Global helper functions
        window.changeFloor = changeFloor;
        window.selectMonster = selectMonster;
    </script>
</body>
</html>
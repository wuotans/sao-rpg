<?php
// pages/battle.php - VERSÃO CORRIGIDA E MELHORADA

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

// Gerar monstro para o piso atual
$monster = generateMonster($floor);

// Se não tiver monstro na sessão, criar um
if (!isset($_SESSION['current_monster']) || $_SESSION['current_monster']['floor'] != $floor) {
    $_SESSION['current_monster'] = $monster;
    $_SESSION['current_monster']['current_hp'] = $monster['hp'];
    $_SESSION['current_monster']['max_hp'] = $monster['hp'];
}

// Verificar se jogador está morto
if ($player['current_hp'] <= 0) {
    header('Location: town.php?action=hospital');
    exit();
}
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
        
        .battle-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
            padding: 20px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            border: 1px solid #00b0ff;
        }
        
        .stat-comparison {
            text-align: center;
        }
        
        .stat-label {
            display: block;
            color: #90caf9;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .stat-values {
            display: flex;
            justify-content: space-around;
            align-items: center;
        }
        
        .player-stat {
            color: #00b0ff;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .monster-stat {
            color: #ff5252;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .vs-stat {
            color: #ffd740;
            font-size: 0.8rem;
        }
        
        .battle-tips {
            background: rgba(255, 215, 64, 0.1);
            border: 1px solid #ffd740;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .battle-tips h4 {
            color: #ffd740;
            margin-bottom: 10px;
        }
        
        .battle-tips ul {
            list-style: none;
            padding-left: 20px;
        }
        
        .battle-tips li {
            margin-bottom: 8px;
            color: #bbdefb;
        }
        
        .battle-tips li:before {
            content: '⚔️';
            margin-right: 10px;
        }
        
        .energy-warning {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid #f44336;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            color: #ffcdd2;
        }
        
        .energy-warning i {
            color: #f44336;
            margin-right: 10px;
        }
        
        .floor-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            border: 1px solid #00b0ff;
        }
        
        .floor-rewards {
            text-align: center;
        }
        
        .reward-item {
            display: inline-block;
            margin: 0 10px;
            text-align: center;
        }
        
        .reward-icon {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .reward-icon.gold {
            color: #ffd740;
        }
        
        .reward-icon.exp {
            color: #69f0ae;
        }
        
        .reward-amount {
            font-size: 0.9rem;
            color: #fff;
        }
        
        .battle-help {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 176, 255, 0.9);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            z-index: 100;
            box-shadow: 0 0 10px rgba(0, 176, 255, 0.5);
        }
        
        .battle-help:hover {
            background: rgba(41, 121, 255, 0.9);
            transform: scale(1.1);
        }
        
        /* Estilo para lista de monstros por piso */
        .floor-monsters {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin: 20px 0;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
        }
        
        .monster-slot {
            text-align: center;
            padding: 10px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 5px;
            border: 1px solid #444;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .monster-slot:hover {
            border-color: #00b0ff;
            transform: translateY(-2px);
        }
        
        .monster-slot.current {
            border-color: #ffd740;
            background: rgba(255, 215, 64, 0.1);
        }
        
        .monster-slot.defeated {
            opacity: 0.6;
            border-color: #4CAF50;
        }
        
        .monster-slot.boss {
            border-color: #f44336;
            background: rgba(244, 67, 54, 0.1);
        }
        
        .monster-number {
            font-size: 0.8rem;
            color: #aaa;
        }
        
        .monster-name-small {
            font-size: 0.9rem;
            margin: 5px 0;
        }
        
        .boss-label {
            color: #f44336;
            font-weight: bold;
            font-size: 0.8rem;
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
            <div class="floor-monsters">
                <?php
                // Gerar lista de 10 monstros + 1 boss
                for ($i = 1; $i <= 11; $i++):
                    $isCurrent = ($i == ($_SESSION['monster_progress'][$floor] ?? 1));
                    $isDefeated = isset($_SESSION['monster_progress'][$floor]) && $i < $_SESSION['monster_progress'][$floor];
                    $isBoss = ($i == 11);
                    
                    $monsterName = $isBoss ? 'Floor Boss' : generateMonsterName($floor, $i);
                    $monsterClass = '';
                    if ($isBoss) $monsterClass = 'boss';
                    elseif ($isCurrent) $monsterClass = 'current';
                    elseif ($isDefeated) $monsterClass = 'defeated';
                ?>
                <div class="monster-slot <?php echo $monsterClass; ?>" 
                     onclick="selectMonster(<?php echo $i; ?>)"
                     title="<?php echo $isBoss ? 'Floor Boss - Special Rewards' : 'Monster ' . $i; ?>">
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
                        <i class="fas fa-check" style="color: #4CAF50;"></i>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            
            <!-- Battle Arena -->
            <div class="battle-header">
                <h2><i class="fas fa-crosshairs"></i> Battle Arena</h2>
                <p class="floor-indicator">Current Floor: <strong><?php echo $floor; ?></strong> | Monster: <strong id="current-monster-number">1</strong>/11</p>
            </div>
            
            <div class="battle-arena" id="battleArena">
                <!-- Rendered by JavaScript -->
            </div>
            
            <!-- Combat Effects Layer -->
            <div class="combat-effects" id="combatEffects"></div>
            
            <!-- Battle Stats Comparison -->
            <div class="battle-stats">
                <div class="stat-comparison">
                    <span class="stat-label">Attack Power</span>
                    <div class="stat-values">
                        <span class="player-stat" id="player-atk"><?php echo $player['atk']; ?></span>
                        <span class="vs-stat">VS</span>
                        <span class="monster-stat" id="monster-atk"><?php echo $monster['atk']; ?></span>
                    </div>
                </div>
                <div class="stat-comparison">
                    <span class="stat-label">Defense</span>
                    <div class="stat-values">
                        <span class="player-stat" id="player-def"><?php echo $player['def']; ?></span>
                        <span class="vs-stat">VS</span>
                        <span class="monster-stat" id="monster-def"><?php echo $monster['def']; ?></span>
                    </div>
                </div>
                <div class="stat-comparison">
                    <span class="stat-label">Health</span>
                    <div class="stat-values">
                        <span class="player-stat" id="player-hp"><?php echo $player['current_hp']; ?>/<?php echo $player['max_hp']; ?></span>
                        <span class="vs-stat">VS</span>
                        <span class="monster-stat" id="monster-hp"><?php echo $_SESSION['current_monster']['current_hp']; ?>/<?php echo $_SESSION['current_monster']['max_hp']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Skills -->
            <div class="skills-container" id="skillsContainer">
                <!-- Rendered by JavaScript -->
            </div>
            
            <!-- Battle Controls -->
            <div class="battle-controls" id="battleControls">
                <button class="battle-btn attack-btn" onclick="performAttack()">
                    <i class="fas fa-swords"></i> Basic Attack
                </button>
                <button class="battle-btn defend-btn" onclick="performDefend()">
                    <i class="fas fa-shield-alt"></i> Defend
                </button>
                <button class="battle-btn flee-btn" onclick="performFlee()">
                    <i class="fas fa-running"></i> Flee
                </button>
                <button class="battle-btn auto-btn" onclick="toggleAutoBattle()">
                    <i class="fas fa-robot"></i> Auto Battle
                </button>
            </div>
            
            <!-- Battle Log -->
            <div class="battle-log-container">
                <h3><i class="fas fa-scroll"></i> Battle Log</h3>
                <div class="battle-log" id="battleLog">
                    <!-- Battle log entries will be added here -->
                </div>
            </div>
            
            <!-- Battle Tips -->
            <div class="battle-tips">
                <h4><i class="fas fa-lightbulb"></i> Battle Tips</h4>
                <ul>
                    <li>Use skills strategically to conserve MP</li>
                    <li>Defending reduces damage taken on the next attack</li>
                    <li>Critical hits deal 50% more damage</li>
                    <li>Auto-battle is great for farming lower floors</li>
                    <li>Make sure to heal when HP is low</li>
                </ul>
            </div>
            
            <!-- Floor Navigation -->
            <div class="floor-navigation">
                <?php if ($floor > 1): ?>
                    <button class="floor-btn" onclick="changeFloor(<?php echo $floor - 1; ?>)">
                        <i class="fas fa-arrow-left"></i> Floor <?php echo $floor - 1; ?>
                    </button>
                <?php endif; ?>
                
                <?php 
                // Mostrar pisos disponíveis (1-10 ou até o máximo desbloqueado)
                $maxFloor = min(10, $player['current_floor']);
                for ($i = max(1, $floor - 2); $i <= min($maxFloor, $floor + 2); $i++):
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
                
                <h3>Piso System</h3>
                <ul>
                    <li>Each floor has 10 regular monsters + 1 boss</li>
                    <li>Defeat all 11 to unlock the next floor</li>
                    <li>Bosses drop special rewards (equipment, skills)</li>
                    <li>You can replay any floor you've unlocked</li>
                </ul>
                
                <h3>Actions</h3>
                <ul>
                    <li><strong>Basic Attack:</strong> A simple attack that costs no MP</li>
                    <li><strong>Skills:</strong> Special attacks that cost MP but deal more damage or have special effects</li>
                    <li><strong>Defend:</strong> Reduces damage taken on the next attack</li>
                    <li><strong>Flee:</strong> Escape from battle (not always successful)</li>
                    <li><strong>Auto Battle:</strong> Automatically fight using basic attacks</li>
                </ul>
                
                <h3>Stats</h3>
                <ul>
                    <li><strong>HP:</strong> Health Points. When it reaches 0, you lose</li>
                    <li><strong>MP:</strong> Magic Points. Used for skills</li>
                    <li><strong>ATK:</strong> Attack Power. Higher = more damage</li>
                    <li><strong>DEF:</strong> Defense. Reduces damage taken</li>
                    <li><strong>CRIT:</strong> Critical Hit Chance. Higher = more critical hits</li>
                </ul>
                
                <h3>Rewards</h3>
                <p>After winning a battle, you receive:</p>
                <ul>
                    <li>Experience Points (EXP)</li>
                    <li>Gold</li>
                    <li>Chance to drop items (bosses have better drops)</li>
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
        let currentMonsterNumber = <?php echo $_SESSION['monster_progress'][$floor] ?? 1; ?>;
        
        // Initialize combat system
        $(document).ready(function() {
            // Get player and monster data
            const player = {
                id: <?php echo $_SESSION['user_id']; ?>,
                username: '<?php echo $_SESSION['username']; ?>',
                level: <?php echo $player['level']; ?>,
                current_hp: <?php echo $player['current_hp']; ?>,
                max_hp: <?php echo $player['max_hp']; ?>,
                current_mp: <?php echo $player['current_mp']; ?>,
                max_mp: <?php echo $player['max_mp']; ?>,
                atk: <?php echo $player['atk']; ?>,
                def: <?php echo $player['def']; ?>,
                crit: <?php echo $player['crit']; ?>,
                avatar: '<?php echo $_SESSION['avatar']; ?>',
                energy: <?php echo $player['energy']; ?>
            };
            
            const monster = {
                id: 'monster_<?php echo $floor; ?>_<?php echo $_SESSION['monster_progress'][$floor] ?? 1; ?>',
                name: '<?php echo $_SESSION['current_monster']['name']; ?>',
                floor: <?php echo $floor; ?>,
                current_hp: <?php echo $_SESSION['current_monster']['current_hp']; ?>,
                max_hp: <?php echo $_SESSION['current_monster']['max_hp']; ?>,
                atk: <?php echo $_SESSION['current_monster']['atk']; ?>,
                def: <?php echo $_SESSION['current_monster']['def']; ?>,
                exp: <?php echo $_SESSION['current_monster']['exp']; ?>,
                gold: <?php echo $_SESSION['current_monster']['gold']; ?>,
                is_boss: <?php echo (($_SESSION['monster_progress'][$floor] ?? 1) == 11) ? 'true' : 'false'; ?>
            };
            
            // Atualizar número do monstro atual
            $('#current-monster-number').text(currentMonsterNumber);
            
            // Initialize combat
            initCombat(player, monster);
            
            // Start energy warning check
            setInterval(checkEnergy, 30000);
        });
        
        // Inicializar sistema de combate
        function initCombat(player, monster) {
            // Renderizar arena
            renderArena(player, monster);
            addLog(`Battle started against ${monster.name}!`, 'system');
            
            // Carregar habilidades
            loadSkills();
        }
        
        // Renderizar arena
        function renderArena(player, monster) {
            const arenaHTML = `
                <div class="arena-content">
                    <div class="combatant player">
                        <div class="combatant-avatar" style="background-image: url('../images/avatars/${player.avatar}')"></div>
                        <div class="combatant-name">${player.username}</div>
                        <div class="combatant-level">Level ${player.level}</div>
                        
                        <div class="combatant-stats">
                            <div class="stat-bar">
                                <div class="stat-label">HP</div>
                                <div class="bar-container">
                                    <div class="bar-fill hp-bar" style="width: ${(player.current_hp / player.max_hp) * 100}%"></div>
                                </div>
                                <div class="stat-value">${player.current_hp}/${player.max_hp}</div>
                            </div>
                            
                            <div class="stat-bar">
                                <div class="stat-label">MP</div>
                                <div class="bar-container">
                                    <div class="bar-fill mp-bar" style="width: ${(player.current_mp / player.max_mp) * 100}%"></div>
                                </div>
                                <div class="stat-value">${player.current_mp}/${player.max_mp}</div>
                            </div>
                            
                            <div class="quick-stats">
                                <div>Energy: ${player.energy}</div>
                                <div>ATK: ${player.atk} | DEF: ${player.def} | CRIT: ${player.crit}%</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vs-separator">
                        <span>VS</span>
                    </div>
                    
                    <div class="combatant monster">
                        <div class="combatant-avatar" style="background-image: url('../images/monsters/monster_${monster.floor}.png')"></div>
                        <div class="combatant-name">${monster.name}</div>
                        <div class="combatant-level">Floor ${monster.floor} ${monster.is_boss ? '(BOSS)' : ''}</div>
                        
                        <div class="combatant-stats">
                            <div class="stat-bar">
                                <div class="stat-label">HP</div>
                                <div class="bar-container">
                                    <div class="bar-fill hp-bar" style="width: ${(monster.current_hp / monster.max_hp) * 100}%"></div>
                                </div>
                                <div class="stat-value">${monster.current_hp}/${monster.max_hp}</div>
                            </div>
                            
                            <div class="quick-stats">
                                <div>ATK: ${monster.atk} | DEF: ${monster.def}</div>
                                <div>Reward: ${monster.exp} EXP, ${monster.gold} Gold</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#battleArena').html(arenaHTML);
        }
        
        // Carregar habilidades
        function loadSkills() {
            $.ajax({
                url: '../api/player.php?action=get_skills',
                method: 'GET',
                success: function(data) {
                    try {
                        const skills = JSON.parse(data);
                        renderSkills(skills);
                    } catch (e) {
                        console.error('Error loading skills:', e);
                        renderSkills([]);
                    }
                },
                error: function() {
                    renderSkills([]);
                }
            });
        }
        
        function renderSkills(skills) {
            const container = $('#skillsContainer');
            
            if (!skills || skills.length === 0) {
                container.html(`
                    <div class="no-skills">
                        <p>No skills available.</p>
                        <p><small>Visit the skill trainer in town to learn new skills!</small></p>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="skills-grid">';
            skills.forEach(skill => {
                html += `
                    <div class="skill-card" onclick="useSkill(${skill.id})">
                        <div class="skill-icon">
                            <i class="fas ${getSkillIcon(skill.type)}"></i>
                        </div>
                        <div class="skill-info">
                            <div class="skill-name">${skill.name}</div>
                            <div class="skill-description">${skill.description}</div>
                            <div class="skill-cost">
                                <i class="fas fa-bolt"></i> ${skill.mp_cost} MP
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            container.html(html);
        }
        
        function getSkillIcon(type) {
            switch(type) {
                case 'attack': return 'fa-swords';
                case 'heal': return 'fa-heart';
                case 'buff': return 'fa-arrow-up';
                case 'debuff': return 'fa-arrow-down';
                default: return 'fa-star';
            }
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
                        const result = JSON.parse(data);
                        handleBattleResult(result);
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
        
        // Defender
        function performDefend() {
            if (autoBattleActive) return;
            
            addLog('You take a defensive stance!', 'player');
            // Implementar lógica de defesa
        }
        
        // Usar habilidade
        function useSkill(skillId) {
            if (autoBattleActive) return;
            
            $.ajax({
                url: '../api/battle.php?action=attack',
                method: 'POST',
                data: { skill_id: skillId },
                success: function(data) {
                    try {
                        const result = JSON.parse(data);
                        handleBattleResult(result);
                    } catch (e) {
                        console.error('Error using skill:', e);
                        addLog('Error using skill', 'system');
                    }
                }
            });
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
            }, 2000); // A cada 2 segundos
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
                if (result.boss_defeated) {
                    addLog('FLOOR BOSS DEFEATED! Floor unlocked!', 'victory');
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
                        }
                    } catch (e) {
                        console.error('Error loading next monster:', e);
                    }
                }
            });
        }
        
        // Selecionar monstro específico
        function selectMonster(monsterNumber) {
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
            $('.monster .combatant-name').text(monster.name);
            $('.monster .combatant-level').text(`Floor ${monster.floor} ${monster.is_boss ? '(BOSS)' : ''}`);
            
            // Atualizar HP
            const hpPercent = (monster.current_hp / monster.max_hp) * 100;
            $('.monster .hp-bar').css('width', hpPercent + '%');
            $('.monster .stat-value').first().text(`${monster.current_hp}/${monster.max_hp}`);
            
            // Atualizar stats
            $('.monster .quick-stats').html(`
                <div>ATK: ${monster.atk} | DEF: ${monster.def}</div>
                <div>Reward: ${monster.exp} EXP, ${monster.gold} Gold</div>
            `);
            
            // Atualizar estatísticas comparativas
            $('#monster-atk').text(monster.atk);
            $('#monster-def').text(monster.def);
            $('#monster-hp').text(`${monster.current_hp}/${monster.max_hp}`);
        }
        
        // Atualizar stats do jogador
        function updatePlayerStats(player) {
            if (player.current_hp !== undefined) {
                updatePlayerHP(player.current_hp, player.max_hp);
            }
            
            if (player.current_mp !== undefined) {
                updatePlayerMP(player.current_mp, player.max_mp);
            }
            
            if (player.energy !== undefined) {
                $('.player .quick-stats').find('div:first-child').text(`Energy: ${player.energy}`);
            }
            
            if (player.exp !== undefined) {
                // Opcional: mostrar EXP atualizado
            }
            
            if (player.gold !== undefined) {
                // Opcional: mostrar Gold atualizado
            }
            
            if (player.level_up) {
                addLog(`LEVEL UP! You are now level ${player.new_level}!`, 'victory');
            }
        }
        
        function updatePlayerHP(hp, maxHp = <?php echo $player['max_hp']; ?>) {
            const hpPercent = (hp / maxHp) * 100;
            $('.player .hp-bar').css('width', hpPercent + '%');
            $('.player .stat-value').first().text(`${hp}/${maxHp}`);
            $('#player-hp').text(`${hp}/${maxHp}`);
            
            // Animação
            $('.player .hp-bar').addClass('damage-animation');
            setTimeout(() => {
                $('.player .hp-bar').removeClass('damage-animation');
            }, 300);
        }
        
        function updatePlayerMP(mp, maxMp = <?php echo $player['max_mp']; ?>) {
            const mpPercent = (mp / maxMp) * 100;
            $('.player .mp-bar').css('width', mpPercent + '%');
            $('.player .stat-value').last().text(`${mp}/${maxMp}`);
        }
        
        function updateMonsterHP(hp) {
            const maxHp = <?php echo $_SESSION['current_monster']['max_hp']; ?>;
            const hpPercent = (hp / maxHp) * 100;
            $('.monster .hp-bar').css('width', hpPercent + '%');
            $('.monster .stat-value').first().text(`${hp}/${maxHp}`);
            $('#monster-hp').text(`${hp}/${maxHp}`);
            
            // Animação
            $('.monster .hp-bar').addClass('damage-animation');
            setTimeout(() => {
                $('.monster .hp-bar').removeClass('damage-animation');
            }, 300);
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
        
        // Check energy level
        function checkEnergy() {
            $.ajax({
                url: '../api/player.php?action=get_energy',
                method: 'GET',
                success: function(data) {
                    try {
                        const result = JSON.parse(data);
                        if (result.energy < 5) {
                            showLowEnergyWarning(result.energy);
                        }
                    } catch (e) {
                        console.error('Error checking energy:', e);
                    }
                }
            });
        }
        
        // Show low energy warning
        function showLowEnergyWarning(energy) {
            // Remove existing warning
            $('.energy-warning').remove();
            
            const $warning = $(`
                <div class="energy-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Low Energy! You have ${energy} energy remaining.
                    <br>
                    <small>Energy regenerates 1 point every 4 minutes.</small>
                </div>
            `);
            
            $('.battle-container').prepend($warning);
            
            // Remove warning after 10 seconds
            setTimeout(() => {
                $warning.fadeOut(() => $warning.remove());
            }, 10000);
        }
        
        // Show damage popup
        function showDamagePopup(damage, isCritical, isHeal, position) {
            const $popup = $(`
                <div class="damage-popup ${isCritical ? 'critical' : ''} ${isHeal ? 'heal-popup' : ''}">
                    ${isHeal ? '+' : ''}${damage}${isCritical ? '!' : ''}
                </div>
            `);
            
            $popup.css({
                left: position.x + 'px',
                top: position.y + 'px'
            });
            
            $('#combatEffects').append($popup);
            
            // Remove after animation
            setTimeout(() => {
                $popup.remove();
            }, 1000);
        }
        
        // Global helper functions
        window.changeFloor = changeFloor;
        window.showDamagePopup = showDamagePopup;
        window.selectMonster = selectMonster;
    </script>
</body>
</html>
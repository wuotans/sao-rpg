<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Require login
requireLogin();

// Get current floor
$floor = $_SESSION['current_floor'] ?? 1;

// Load player info
$db = Database::getInstance();
$player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$_SESSION['user_id']]);

// Generate monster for this floor
$monster = generateMonster($floor);
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
            
            <!-- Battle Arena -->
            <div class="battle-header">
                <h2><i class="fas fa-crosshairs"></i> Battle Arena</h2>
                <p class="floor-indicator">Current Floor: <strong><?php echo $floor; ?></strong></p>
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
                        <span class="monster-stat" id="monster-hp"><?php echo $monster['current_hp']; ?>/<?php echo $monster['max_hp']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Skills -->
            <div class="skills-container" id="skillsContainer">
                <!-- Rendered by JavaScript -->
            </div>
            
            <!-- Battle Controls -->
            <div class="battle-controls" id="battleControls">
                <button class="battle-btn attack-btn" onclick="combatSystem.basicAttack()">
                    <i class="fas fa-swords"></i> Basic Attack
                </button>
                <button class="battle-btn defend-btn" onclick="combatSystem.defend()">
                    <i class="fas fa-shield-alt"></i> Defend
                </button>
                <button class="battle-btn flee-btn" onclick="combatSystem.flee()">
                    <i class="fas fa-running"></i> Flee
                </button>
                <button class="battle-btn auto-btn" onclick="combatSystem.toggleAutoBattle()">
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
                
                <?php for ($i = max(1, $floor - 2); $i <= min(10, $floor + 2); $i++): ?>
                    <?php if ($i != $floor): ?>
                        <button class="floor-btn" onclick="changeFloor(<?php echo $i; ?>)">
                            Floor <?php echo $i; ?>
                        </button>
                    <?php else: ?>
                        <button class="floor-btn active">
                            Floor <?php echo $i; ?>
                        </button>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($floor < 10): ?>
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
                    <li>Chance to drop items</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Help Button -->
    <div class="battle-help" onclick="$('#battleHelpModal').fadeIn()">
        <i class="fas fa-question"></i>
    </div>
    
    <script>
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
                avatar: '<?php echo $_SESSION['avatar']; ?>'
            };
            
            const monster = {
                id: 'monster_<?php echo $floor; ?>',
                name: '<?php echo $monster['name']; ?>',
                floor: <?php echo $floor; ?>,
                current_hp: <?php echo $monster['hp']; ?>,
                max_hp: <?php echo $monster['hp']; ?>,
                atk: <?php echo $monster['atk']; ?>,
                def: <?php echo $monster['def']; ?>,
                exp: <?php echo $monster['exp']; ?>,
                gold: <?php echo $monster['gold']; ?>
            };
            
            // Initialize combat
            combatSystem.init(player, monster);
            
            // Start energy warning check
            setInterval(checkEnergy, 30000);
        });
        
        // Check energy level
        function checkEnergy() {
            $.ajax({
                url: '../api/player.php?action=get_energy',
                method: 'GET',
                success: function(data) {
                    try {
                        const result = JSON.parse(data);
                        if (result.energy < 10) {
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
                $warning.fadeOut(() => $(this).remove());
            }, 10000);
        }
        
        // Change floor
        function changeFloor(floor) {
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
    </script>
</body>
</html>
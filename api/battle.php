<?php
// api/battle.php - VERSÃO COMPLETA COM SISTEMA DE 10+1 MONSTROS

// Iniciar sessão PRIMEIRO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Incluir arquivos
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'get_monster':
        getMonster();
        break;
    case 'attack':
        processAttack();
        break;
    case 'auto':
        autoBattle();
        break;
    case 'victory':
        processVictory();
        break;
    case 'defeat':
        processDefeat();
        break;
    case 'flee':
        processFlee();
        break;
    case 'change_floor':
        changeFloor();
        break;
    case 'next_monster':
        nextMonster();
        break;
    case 'select_monster':
        selectMonster();
        break;
    case 'reset_hp':
        resetPlayerHP();
        break;
    case 'boss_defeat':
        processBossDefeat();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getMonster() {
    global $db;
    
    $floor = isset($_GET['floor']) ? (int)$_GET['floor'] : 1;
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    // Inicializar progresso do piso se não existir
    if (!isset($_SESSION['monster_progress'][$floor])) {
        $_SESSION['monster_progress'][$floor] = 1;
    }
    
    $currentMonsterNumber = $_SESSION['monster_progress'][$floor];
    
    // Get current monster from session or generate new one
    if (!isset($_SESSION['current_monster']) || 
        $_SESSION['current_monster']['floor'] != $floor ||
        $_SESSION['current_monster']['monster_number'] != $currentMonsterNumber) {
        
        $_SESSION['current_monster'] = generateSpecificMonster($floor, $currentMonsterNumber);
        $_SESSION['current_monster']['current_hp'] = $_SESSION['current_monster']['hp'];
        $_SESSION['current_monster']['max_hp'] = $_SESSION['current_monster']['hp'];
    }
    
    // Add ID for reference
    $_SESSION['current_monster']['id'] = 'monster_' . $floor . '_' . $currentMonsterNumber;
    
    echo json_encode($_SESSION['current_monster']);
}

function processAttack() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    // Check energy
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    if ($player['energy'] < 1) {
        echo json_encode([
            'success' => false,
            'message' => 'Not enough energy!'
        ]);
        return;
    }
    
    $skill_id = isset($_POST['skill_id']) ? (int)$_POST['skill_id'] : 0;
    $floor = $_SESSION['current_floor'] ?? 1;
    $currentMonsterNumber = $_SESSION['monster_progress'][$floor] ?? 1;
    
    // Get monster
    if (!isset($_SESSION['current_monster'])) {
        $_SESSION['current_monster'] = generateSpecificMonster($floor, $currentMonsterNumber);
        $_SESSION['current_monster']['current_hp'] = $_SESSION['current_monster']['hp'];
        $_SESSION['current_monster']['max_hp'] = $_SESSION['current_monster']['hp'];
    }
    
    $monster = $_SESSION['current_monster'];
    $isBoss = ($currentMonsterNumber == 11);
    
    $log = [];
    $drops = [];
    $level_up = null;
    
    // Player's attack
    $damage = calculateDamage($player['atk'], $monster['def'], $player['crit']);
    $monster['current_hp'] -= $damage['damage'];
    
    $log[] = [
        'time' => date('H:i:s'),
        'text' => "You hit {$monster['name']} for {$damage['damage']} damage" . ($damage['critical'] ? ' (CRITICAL!)' : ''),
        'type' => 'player',
        'critical' => $damage['critical']
    ];
    
    // Check if monster is dead
    if ($monster['current_hp'] <= 0) {
        $monster['current_hp'] = 0;
        
        // Calculate rewards
        $exp_gained = $monster['exp'];
        $gold_gained = $monster['gold'];
        
        // Apply VIP bonus
        $is_vip = isVipActive($_SESSION['vip_expire'] ?? null);
        if ($is_vip) {
            $exp_gained = round($exp_gained * VIP_EXP_MULTIPLIER);
            $gold_gained = round($gold_gained * VIP_GOLD_MULTIPLIER);
        }
        
        // BOSS gives extra rewards
        if ($isBoss) {
            $exp_gained *= 2;
            $gold_gained *= 3;
            $log[] = [
                'time' => date('H:i:s'),
                'text' => "BOSS DEFEATED! Extra rewards received!",
                'type' => 'victory'
            ];
        }
        
        // Update player
        $new_exp = $player['exp'] + $exp_gained;
        
        // Check for level up
        $level_up_result = checkLevelUp($user_id, $exp_gained);
        
        // Update gold
        $new_gold = $player['gold'] + $gold_gained;
        
        // Update energy (deduct 1 for battle)
        $new_energy = max(0, $player['energy'] - 1);
        
        // Get drops - BOSS has better drop rate
        $drop_multiplier = $isBoss ? 3 : 1;
        $drops = getRandomDrop($player['current_floor'], $is_vip, $drop_multiplier);
        
        // Add drops to inventory
        foreach ($drops as $drop) {
            addItemToInventory($user_id, $drop['id'], 1);
        }
        
        // Update player in database
        $db->update('characters', [
            'exp' => $new_exp,
            'gold' => $new_gold,
            'energy' => $new_energy
        ], 'user_id = ?', [$user_id]);
        
        // Record battle
        $db->insert('battles', [
            'user_id' => $user_id,
            'monster_name' => $monster['name'],
            'monster_number' => $currentMonsterNumber,
            'floor' => $floor,
            'result' => 'win',
            'exp_gained' => $exp_gained,
            'gold_gained' => $gold_gained,
            'items_dropped' => json_encode(array_column($drops, 'name')),
            'is_boss' => $isBoss ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $log[] = [
            'time' => date('H:i:s'),
            'text' => "You defeated {$monster['name']}! Gained {$exp_gained} EXP and {$gold_gained} Gold",
            'type' => 'victory'
        ];
        
        if (!empty($drops)) {
            $item_names = array_column($drops, 'name');
            $log[] = [
                'time' => date('H:i:s'),
                'text' => "Items dropped: " . implode(', ', $item_names),
                'type' => 'loot'
            ];
        }
        
        // Update monster progress
        $_SESSION['monster_progress'][$floor] = $currentMonsterNumber + 1;
        
        // Clear current monster
        unset($_SESSION['current_monster']);
        
        echo json_encode([
            'success' => true,
            'monster_dead' => true,
            'exp_gained' => $exp_gained,
            'gold_gained' => $gold_gained,
            'drops' => $drops,
            'log' => $log,
            'level_up' => $level_up_result,
            'is_boss' => $isBoss,
            'next_monster_number' => $currentMonsterNumber + 1,
            'player' => [
                'exp' => $new_exp,
                'gold' => $new_gold,
                'energy' => $new_energy,
                'level' => $level_up_result['level_up'] ? $level_up_result['new_level'] : $player['level']
            ]
        ]);
        return;
    }
    
    // Monster's attack
    $monster_damage = calculateDamage($monster['atk'], $player['def'], 5); // 5% crit for monsters
    $new_player_hp = max(0, $player['current_hp'] - $monster_damage['damage']);
    
    // Update player HP
    $db->update('characters', [
        'current_hp' => $new_player_hp,
        'energy' => max(0, $player['energy'] - 1)
    ], 'user_id = ?', [$user_id]);
    
    $log[] = [
        'time' => date('H:i:s'),
        'text' => "{$monster['name']} hit you for {$monster_damage['damage']} damage" . ($monster_damage['critical'] ? ' (CRITICAL!)' : ''),
        'type' => 'monster',
        'critical' => $monster_damage['critical']
    ];
    
    // Check if player is dead
    if ($new_player_hp <= 0) {
        $log[] = [
            'time' => date('H:i:s'),
            'text' => "You were defeated by {$monster['name']}!",
            'type' => 'defeat'
        ];
        
        // Record defeat
        $db->insert('battles', [
            'user_id' => $user_id,
            'monster_name' => $monster['name'],
            'monster_number' => $currentMonsterNumber,
            'floor' => $floor,
            'result' => 'lose',
            'exp_gained' => 0,
            'gold_gained' => 0,
            'is_boss' => $isBoss ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Reset player HP to 10% (hospital)
        $new_hp_after_defeat = round($player['max_hp'] * 0.1);
        $db->update('characters', [
            'current_hp' => $new_hp_after_defeat
        ], 'user_id = ?', [$user_id]);
        
        // Update session
        $_SESSION['current_hp'] = $new_hp_after_defeat;
        
        echo json_encode([
            'success' => true,
            'player_dead' => true,
            'log' => $log,
            'player' => [
                'current_hp' => $new_hp_after_defeat,
                'energy' => max(0, $player['energy'] - 1)
            ]
        ]);
        return;
    }
    
    // Update monster in session
    $_SESSION['current_monster'] = $monster;
    
    // Get updated player info
    $updated_player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    echo json_encode([
        'success' => true,
        'monster_dead' => false,
        'monster_hp' => $monster['current_hp'],
        'player_hp' => $new_player_hp,
        'player_mp' => $updated_player['current_mp'],
        'player_energy' => $updated_player['energy'],
        'log' => $log,
        'player' => [
            'current_hp' => $new_player_hp,
            'current_mp' => $updated_player['current_mp'],
            'energy' => $updated_player['energy']
        ]
    ]);
}

function autoBattle() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    // Check energy
    if ($player['energy'] < 1) {
        echo json_encode([
            'success' => false,
            'message' => 'Not enough energy!'
        ]);
        return;
    }
    
    $floor = $_SESSION['current_floor'] ?? 1;
    $currentMonsterNumber = $_SESSION['monster_progress'][$floor] ?? 1;
    
    $monster = $_SESSION['current_monster'] ?? generateSpecificMonster($floor, $currentMonsterNumber);
    $log = [];
    $drops = [];
    $level_up = null;
    
    // Auto battle logic (simplified for performance)
    $battles_won = 0;
    $total_exp = 0;
    $total_gold = 0;
    $all_drops = [];
    
    // Simulate battles or until energy runs out
    $battles_to_simulate = min(10, $player['energy']);
    
    for ($i = 0; $i < $battles_to_simulate; $i++) {
        // Player wins automatically in auto-battle (for simplicity)
        $is_vip = isVipActive($_SESSION['vip_expire'] ?? null);
        
        $exp_gained = $monster['exp'];
        $gold_gained = $monster['gold'];
        
        if ($is_vip) {
            $exp_gained = round($exp_gained * VIP_EXP_MULTIPLIER);
            $gold_gained = round($gold_gained * VIP_GOLD_MULTIPLIER);
        }
        
        $total_exp += $exp_gained;
        $total_gold += $gold_gained;
        
        // Get drops
        $battle_drops = getRandomDrop($player['current_floor'], $is_vip);
        foreach ($battle_drops as $drop) {
            addItemToInventory($user_id, $drop['id'], 1);
            $all_drops[] = $drop;
        }
        
        $battles_won++;
        
        // Small chance to get hit
        if (rand(1, 10) == 1) { // 10% chance to take damage
            $damage_taken = rand(1, 10);
            $new_hp = max(1, $player['current_hp'] - $damage_taken);
            $db->update('characters', ['current_hp' => $new_hp], 'user_id = ?', [$user_id]);
            $player['current_hp'] = $new_hp;
        }
        
        // Update monster progress
        $_SESSION['monster_progress'][$floor] = $currentMonsterNumber + 1;
        $currentMonsterNumber++;
        
        // If passed monster 11, reset to 1 and increase floor progress
        if ($currentMonsterNumber > 11) {
            $currentMonsterNumber = 1;
            
            // Check if this was a boss defeat
            if ($i == $battles_to_simulate - 1) {
                // Last battle was a boss
                $log[] = [
                    'time' => date('H:i:s'),
                    'text' => "BOSS defeated! Floor completed!",
                    'type' => 'victory'
                ];
            }
        }
    }
    
    // Update player stats
    $new_exp = $player['exp'] + $total_exp;
    $new_gold = $player['gold'] + $total_gold;
    $new_energy = max(0, $player['energy'] - $battles_won);
    
    // Check for level up
    $level_up_result = checkLevelUp($user_id, $total_exp);
    
    $db->update('characters', [
        'exp' => $new_exp,
        'gold' => $new_gold,
        'energy' => $new_energy
    ], 'user_id = ?', [$user_id]);
    
    // Record battles
    for ($i = 0; $i < $battles_won; $i++) {
        $isBossBattle = ($currentMonsterNumber + $i == 11) ? 1 : 0;
        $db->insert('battles', [
            'user_id' => $user_id,
            'monster_name' => $monster['name'],
            'floor' => $floor,
            'result' => 'win',
            'exp_gained' => round($total_exp / $battles_won),
            'gold_gained' => round($total_gold / $battles_won),
            'is_boss' => $isBossBattle,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    $log[] = [
        'time' => date('H:i:s'),
        'text' => "Auto-battle completed {$battles_won} battles",
        'type' => 'system'
    ];
    
    $log[] = [
        'time' => date('H:i:s'),
        'text' => "Gained {$total_exp} EXP and {$total_gold} Gold",
        'type' => 'reward'
    ];
    
    if (!empty($all_drops)) {
        $unique_drops = [];
        foreach ($all_drops as $drop) {
            $name = $drop['name'];
            $unique_drops[$name] = ($unique_drops[$name] ?? 0) + 1;
        }
        
        foreach ($unique_drops as $name => $count) {
            $log[] = [
                'time' => date('H:i:s'),
                'text' => "Dropped {$name} x{$count}",
                'type' => 'loot'
            ];
        }
    }
    
    // Get updated player
    $updated_player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    echo json_encode([
        'success' => true,
        'battles_won' => $battles_won,
        'exp_gained' => $total_exp,
        'gold_gained' => $total_gold,
        'drops' => $all_drops,
        'log' => $log,
        'level_up' => $level_up_result,
        'player' => [
            'exp' => $new_exp,
            'gold' => $new_gold,
            'energy' => $new_energy,
            'current_hp' => $updated_player['current_hp'],
            'current_mp' => $updated_player['current_mp'],
            'level' => $level_up_result['level_up'] ? $level_up_result['new_level'] : $player['level']
        ]
    ]);
}

function processVictory() {
    echo json_encode(['success' => true, 'message' => 'Victory processed']);
}

function processDefeat() {
    echo json_encode(['success' => true, 'message' => 'Defeat processed']);
}

function processFlee() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    // 70% chance to successfully flee
    $success = rand(1, 100) <= 70;
    
    if ($success) {
        // Clear current monster
        unset($_SESSION['current_monster']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully fled from battle!'
        ]);
    } else {
        // Take damage when fleeing fails
        $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
        $floor = $_SESSION['current_floor'] ?? 1;
        $currentMonsterNumber = $_SESSION['monster_progress'][$floor] ?? 1;
        $monster = $_SESSION['current_monster'] ?? generateSpecificMonster($floor, $currentMonsterNumber);
        
        $damage = rand(5, 15);
        $new_hp = max(1, $player['current_hp'] - $damage);
        
        $db->update('characters', ['current_hp' => $new_hp], 'user_id = ?', [$user_id]);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to flee! Took ' . $damage . ' damage.',
            'damage' => $damage,
            'new_hp' => $new_hp
        ]);
    }
}

function changeFloor() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $floor = isset($_POST['floor']) ? (int)$_POST['floor'] : 1;
    
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    // Check if floor is unlocked
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    if ($floor > $player['current_floor']) {
        echo json_encode([
            'success' => false,
            'message' => 'Floor not unlocked yet!'
        ]);
        return;
    }
    
    // Update current floor
    $_SESSION['current_floor'] = $floor;
    $db->update('characters', ['current_floor' => $floor], 'user_id = ?', [$user_id]);
    
    // Reset monster progress for this floor if not already set
    if (!isset($_SESSION['monster_progress'][$floor])) {
        $_SESSION['monster_progress'][$floor] = 1;
    }
    
    // Clear current monster for new floor
    unset($_SESSION['current_monster']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Moved to floor ' . $floor,
        'floor' => $floor,
        'monster_progress' => $_SESSION['monster_progress'][$floor]
    ]);
}

function nextMonster() {
    $floor = $_SESSION['current_floor'] ?? 1;
    
    // Increment monster progress
    if (!isset($_SESSION['monster_progress'][$floor])) {
        $_SESSION['monster_progress'][$floor] = 1;
    }
    
    $currentMonsterNumber = $_SESSION['monster_progress'][$floor];
    $nextMonsterNumber = $currentMonsterNumber + 1;
    
    // Check if we defeated the boss (monster 11)
    $bossDefeated = false;
    if ($currentMonsterNumber == 11) {
        $bossDefeated = true;
        
        // Unlock next floor if current floor < max floor
        $db = Database::getInstance();
        $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$_SESSION['user_id']]);
        $maxFloor = $player['current_floor'];
        
        if ($floor == $maxFloor) {
            $newFloor = $floor + 1;
            $db->update('characters', 
                ['current_floor' => $newFloor], 
                'user_id = ?', 
                [$_SESSION['user_id']]
            );
            $_SESSION['current_floor'] = $newFloor;
        }
        
        // Reset to monster 1 for the current floor
        $nextMonsterNumber = 1;
    }
    
    // If next monster is beyond 11, reset to 1
    if ($nextMonsterNumber > 11) {
        $nextMonsterNumber = 1;
    }
    
    $_SESSION['monster_progress'][$floor] = $nextMonsterNumber;
    
    // Generate new monster
    $monster = generateSpecificMonster($floor, $nextMonsterNumber);
    $_SESSION['current_monster'] = $monster;
    $_SESSION['current_monster']['current_hp'] = $monster['hp'];
    $_SESSION['current_monster']['max_hp'] = $monster['hp'];
    
    echo json_encode([
        'success' => true,
        'monster_number' => $nextMonsterNumber,
        'monster' => $monster,
        'is_boss' => ($nextMonsterNumber == 11),
        'boss_defeated' => $bossDefeated,
        'message' => $bossDefeated ? 'BOSS DEFEATED! Next floor unlocked!' : 'Next monster loaded'
    ]);
}

function selectMonster() {
    $floor = $_SESSION['current_floor'] ?? 1;
    $monsterNumber = isset($_POST['monster_number']) ? (int)$_POST['monster_number'] : 1;
    
    // Validar número do monstro (1-11)
    if ($monsterNumber < 1 || $monsterNumber > 11) {
        echo json_encode(['success' => false, 'message' => 'Invalid monster number']);
        return;
    }
    
    // Check if monster is unlocked (can only select up to current progress)
    $currentProgress = $_SESSION['monster_progress'][$floor] ?? 1;
    if ($monsterNumber > $currentProgress) {
        echo json_encode([
            'success' => false, 
            'message' => 'Monster not unlocked yet! Defeat previous monsters first.'
        ]);
        return;
    }
    
    // Generate specific monster
    $monster = generateSpecificMonster($floor, $monsterNumber);
    $_SESSION['current_monster'] = $monster;
    $_SESSION['current_monster']['current_hp'] = $monster['hp'];
    $_SESSION['current_monster']['max_hp'] = $monster['hp'];
    
    echo json_encode([
        'success' => true,
        'monster' => $monster,
        'is_boss' => ($monsterNumber == 11),
        'message' => 'Selected monster #' . $monsterNumber
    ]);
}

function resetPlayerHP() {
    global $db;
    
    $user_id = $_SESSION['user_id'];
    
    // Carregar jogador
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    if (!$player) {
        echo json_encode(['success' => false, 'message' => 'Player not found']);
        return;
    }
    
    // Resetar HP para 10% do máximo (quando morre)
    $new_hp = round($player['max_hp'] * 0.1);
    
    $db->update('characters', 
        ['current_hp' => $new_hp], 
        'user_id = ?', 
        [$user_id]
    );
    
    // Atualizar sessão
    $_SESSION['current_hp'] = $new_hp;
    
    echo json_encode([
        'success' => true,
        'new_hp' => $new_hp,
        'message' => 'HP reset after defeat'
    ]);
}

function processBossDefeat() {
    global $db;
    
    $user_id = $_SESSION['user_id'];
    $floor = $_SESSION['current_floor'] ?? 1;
    
    // Unlock next floor
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    $newFloor = $player['current_floor'] + 1;
    
    $db->update('characters', 
        ['current_floor' => $newFloor], 
        'user_id = ?', 
        [$user_id]
    );
    
    $_SESSION['current_floor'] = $newFloor;
    
    // Reset monster progress for new floor
    $_SESSION['monster_progress'][$newFloor] = 1;
    
    // Give special boss reward
    $bossReward = getBossReward($floor);
    if ($bossReward) {
        addItemToInventory($user_id, $bossReward['id'], 1);
    }
    
    echo json_encode([
        'success' => true,
        'new_floor' => $newFloor,
        'boss_reward' => $bossReward,
        'message' => 'BOSS DEFEATED! Floor ' . $floor . ' completed! Floor ' . $newFloor . ' unlocked!'
    ]);
}

// Helper function to add item to inventory
function addItemToInventory($user_id, $item_id, $quantity = 1) {
    global $db;
    
    // Check if item already exists in inventory
    $existing = $db->fetch(
        "SELECT * FROM inventory WHERE user_id = ? AND item_id = ?",
        [$user_id, $item_id]
    );
    
    if ($existing) {
        // Update quantity
        $db->update('inventory', 
            ['quantity' => $existing['quantity'] + $quantity],
            'user_id = ? AND item_id = ?',
            [$user_id, $item_id]
        );
    } else {
        // Add new item
        $db->insert('inventory', [
            'user_id' => $user_id,
            'item_id' => $item_id,
            'quantity' => $quantity,
            'added_at' => date('Y-m-d H:i:s')
        ]);
    }
}

// Generate specific monster for floor and number
function generateSpecificMonster($floor, $monsterNumber) {
    $isBoss = ($monsterNumber == 11);
    
    // Lista de monstros por piso
    $monsters = [
        1 => [
            'Frenzy Boar', 'Dire Wolf', 'Kobold Trooper', 'Goblin Scout', 
            'Lesser Bat', 'Wild Dog', 'Cave Rat', 'Forest Spider',
            'Swamp Slime', 'Rock Golem', 'Floor 1 Boss: Giant Boar King'
        ],
        2 => [
            'Kobold Sentinel', 'Goblin Warrior', 'Cave Bear', 'Giant Spider',
            'Skeleton Soldier', 'Zombie', 'Ghost', 'Wraith',
            'Shadow Wolf', 'Stone Guardian', 'Floor 2 Boss: Kobold King'
        ],
        3 => [
            'Orc Warrior', 'Troll', 'Harpy', 'Minotaur',
            'Cyclops', 'Basilisk', 'Chimera', 'Hydra',
            'Griffin', 'Dragon Whelp', 'Floor 3 Boss: Troll King'
        ],
        4 => [
            'Dark Knight', 'Death Knight', 'Lich', 'Vampire',
            'Werewolf', 'Banshee', 'Specter', 'Phantom',
            'Wight', 'Mummy', 'Floor 4 Boss: Vampire Lord'
        ],
        5 => [
            'Fire Elemental', 'Water Elemental', 'Earth Elemental', 'Air Elemental',
            'Lightning Elemental', 'Ice Elemental', 'Shadow Elemental', 'Light Elemental',
            'Chaos Elemental', 'Void Elemental', 'Floor 5 Boss: Elemental Lord'
        ]
    ];
    
    // Usar lista padrão se piso não existir
    $floorMonsters = $monsters[$floor] ?? $monsters[1];
    $monsterName = $floorMonsters[$monsterNumber - 1] ?? 'Unknown Monster';
    
    // Stats base
    $baseHP = 50 + ($floor * 20) + ($monsterNumber * 10);
    $baseATK = 8 + ($floor * 3) + round($monsterNumber / 2);
    $baseDEF = 3 + $floor + round($monsterNumber / 3);
    $baseEXP = 10 + ($floor * 8) + ($monsterNumber * 2);
    $baseGOLD = 5 + ($floor * 5) + $monsterNumber;
    
    // Boss gets bonus stats
    if ($isBoss) {
        $baseHP *= 3;
        $baseATK *= 2;
        $baseDEF *= 2;
        $baseEXP *= 5;
        $baseGOLD *= 10;
    }
    
    return [
        'id' => 'monster_' . $floor . '_' . $monsterNumber,
        'name' => $monsterName,
        'floor' => $floor,
        'monster_number' => $monsterNumber,
        'hp' => $baseHP,
        'max_hp' => $baseHP,
        'current_hp' => $baseHP,
        'atk' => $baseATK,
        'def' => $baseDEF,
        'exp' => $baseEXP,
        'gold' => $baseGOLD,
        'is_boss' => $isBoss
    ];
}

// Get special boss reward
function getBossReward($floor) {
    $db = Database::getInstance();
    
    // Boss rewards by floor
    $bossRewards = [
        1 => ['name' => 'Boar King Trophy', 'type' => 'trophy', 'rarity' => 'rare'],
        2 => ['name' => 'Kobold King Armor', 'type' => 'armor', 'rarity' => 'rare'],
        3 => ['name' => 'Troll King Hammer', 'type' => 'weapon', 'rarity' => 'epic'],
        4 => ['name' => 'Vampire Lord Cloak', 'type' => 'armor', 'rarity' => 'epic'],
        5 => ['name' => 'Elemental Lord Staff', 'type' => 'weapon', 'rarity' => 'legendary']
    ];
    
    $reward = $bossRewards[$floor] ?? null;
    
    if ($reward) {
        // Check if item exists in database
        $item = $db->fetch("SELECT * FROM items WHERE name = ? AND rarity = ?", 
            [$reward['name'], $reward['rarity']]);
        
        if ($item) {
            return $item;
        }
    }
    
    return null;
}
?>
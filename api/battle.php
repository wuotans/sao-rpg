<?php
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
    
    // Get current monster from session or generate new one
    if (!isset($_SESSION['current_monster']) || $_SESSION['current_monster']['floor'] != $floor) {
        $_SESSION['current_monster'] = generateMonster($floor);
        $_SESSION['current_monster']['current_hp'] = $_SESSION['current_monster']['hp'];
    }
    
    // Add ID for reference
    $_SESSION['current_monster']['id'] = 'monster_' . $floor;
    
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
    $monster = $_SESSION['current_monster'] ?? generateMonster($player['current_floor']);
    
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
        
        // Update player
        $new_exp = $player['exp'] + $exp_gained;
        
        // Check for level up
        $level_up_result = checkLevelUp($user_id, $exp_gained);
        
        // Update gold
        $new_gold = $player['gold'] + $gold_gained;
        
        // Update energy (deduct 1 for battle)
        $new_energy = max(0, $player['energy'] - 1);
        
        // Get drops
        $drops = getRandomDrop($player['current_floor'], $is_vip);
        
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
            'result' => 'win',
            'exp_gained' => $exp_gained,
            'gold_gained' => $gold_gained,
            'items_dropped' => json_encode(array_column($drops, 'name')),
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
            'result' => 'lose',
            'exp_gained' => 0,
            'gold_gained' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Reset player HP (send to hospital)
        $db->update('characters', [
            'current_hp' => round($player['max_hp'] * 0.1) // 10% HP after defeat
        ], 'user_id = ?', [$user_id]);
        
        echo json_encode([
            'success' => true,
            'player_dead' => true,
            'log' => $log,
            'player' => [
                'current_hp' => round($player['max_hp'] * 0.1),
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
    
    $monster = $_SESSION['current_monster'] ?? generateMonster($player['current_floor']);
    $log = [];
    $drops = [];
    $level_up = null;
    
    // Auto battle logic (simplified for performance)
    $battles_won = 0;
    $total_exp = 0;
    $total_gold = 0;
    $all_drops = [];
    
    // Simulate 10 battles or until energy runs out
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
        $db->insert('battles', [
            'user_id' => $user_id,
            'monster_name' => $monster['name'],
            'result' => 'win',
            'exp_gained' => round($total_exp / $battles_won),
            'gold_gained' => round($total_gold / $battles_won),
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
    // Already handled in processAttack
    echo json_encode(['success' => true]);
}

function processDefeat() {
    // Already handled in processAttack
    echo json_encode(['success' => true]);
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
        $monster = $_SESSION['current_monster'] ?? generateMonster($player['current_floor']);
        
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
    
    // Clear current monster for new floor
    unset($_SESSION['current_monster']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Moved to floor ' . $floor,
        'floor' => $floor
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
?>
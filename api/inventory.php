<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'get_items':
        getInventoryItems();
        break;
    case 'get_equipment':
        getEquippedItems();
        break;
    case 'equip':
        equipItem();
        break;
    case 'unequip':
        unequipItem();
        break;
    case 'use':
        useItem();
        break;
    case 'sell':
        sellItem();
        break;
    case 'preview':
        getInventoryPreview();
        break;
    case 'sort':
        sortInventory();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getInventoryItems() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $category = $_GET['category'] ?? 'all';
    $sort = $_GET['sort'] ?? 'name';
    $order = $_GET['order'] ?? 'asc';
    
    $where = "i.user_id = ?";
    $params = [$user_id];
    
    if ($category !== 'all') {
        $where .= " AND it.type = ?";
        $params[] = $category;
    }
    
    // Get inventory items with item details
    $items = $db->fetchAll("
        SELECT 
            i.id as inventory_id,
            i.item_id,
            i.quantity,
            i.equipped,
            i.equipped_slot,
            i.added_at,
            it.*
        FROM inventory i
        JOIN items it ON i.item_id = it.id
        WHERE $where
        ORDER BY 
            CASE WHEN ? = 'rarity' THEN
                CASE it.rarity
                    WHEN 'legendary' THEN 1
                    WHEN 'epic' THEN 2
                    WHEN 'rare' THEN 3
                    WHEN 'uncommon' THEN 4
                    ELSE 5
                END
            ELSE it.?
            END $order
        LIMIT 100
    ", array_merge($params, [$sort, $sort]));
    
    // Format items for display
    $formatted_items = [];
    foreach ($items as $item) {
        $formatted_items[] = [
            'id' => $item['inventory_id'],
            'item_id' => $item['item_id'],
            'name' => $item['name'],
            'type' => $item['type'],
            'rarity' => $item['rarity'],
            'image' => $item['image'],
            'quantity' => $item['quantity'],
            'equipped' => $item['equipped'],
            'equipped_slot' => $item['equipped_slot'],
            'stats' => [
                'atk' => $item['atk'],
                'def' => $item['def'],
                'hp' => $item['hp_bonus'],
                'mp' => $item['mp_bonus'],
                'crit' => $item['crit_bonus'],
                'agi' => $item['agi_bonus']
            ],
            'required_level' => $item['required_level'],
            'required_class' => $item['required_class'],
            'description' => $item['description'],
            'sell_price' => floor($item['buy_price'] * 0.5)
        ];
    }
    
    echo json_encode($formatted_items);
}

function getEquippedItems() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    // Get equipped items
    $equipped_items = $db->fetchAll("
        SELECT 
            i.equipped_slot,
            it.*
        FROM inventory i
        JOIN items it ON i.item_id = it.id
        WHERE i.user_id = ? AND i.equipped = 1
        ORDER BY i.equipped_slot
    ", [$user_id]);
    
    // Format by slot
    $equipment = [];
    foreach ($equipped_items as $item) {
        $equipment[$item['equipped_slot']] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'type' => $item['type'],
            'rarity' => $item['rarity'],
            'image' => $item['image'],
            'stats' => [
                'atk' => $item['atk'],
                'def' => $item['def'],
                'hp' => $item['hp_bonus'],
                'mp' => $item['mp_bonus'],
                'crit' => $item['crit_bonus'],
                'agi' => $item['agi_bonus']
            ]
        ];
    }
    
    echo json_encode($equipment);
}

function equipItem() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    
    if ($item_id <= 0) {
        echo json_encode(['error' => 'Invalid item']);
        return;
    }
    
    // Get inventory item
    $inventory_item = $db->fetch("
        SELECT i.*, it.type, it.required_level, it.required_class
        FROM inventory i
        JOIN items it ON i.item_id = it.id
        WHERE i.user_id = ? AND i.item_id = ?
    ", [$user_id, $item_id]);
    
    if (!$inventory_item) {
        echo json_encode(['error' => 'Item not found in inventory']);
        return;
    }
    
    // Check requirements
    $player = $db->fetch("SELECT level, class FROM characters WHERE user_id = ?", [$user_id]);
    
    if ($inventory_item['required_level'] > $player['level']) {
        echo json_encode(['error' => 'Level requirement not met']);
        return;
    }
    
    if ($inventory_item['required_class'] && $inventory_item['required_class'] !== $player['class']) {
        echo json_encode(['error' => 'Class requirement not met']);
        return;
    }
    
    // Determine slot based on item type
    $slot = getSlotForItemType($inventory_item['type']);
    
    // Unequip any item in that slot first
    $db->update('inventory', 
        ['equipped' => 0, 'equipped_slot' => null],
        'user_id = ? AND equipped_slot = ?',
        [$user_id, $slot]
    );
    
    // Equip new item
    $db->update('inventory', 
        ['equipped' => 1, 'equipped_slot' => $slot],
        'user_id = ? AND item_id = ?',
        [$user_id, $item_id]
    );
    
    // Update character stats
    updateCharacterStats($user_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Item equipped successfully',
        'slot' => $slot
    ]);
}

function unequipItem() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $slot = $_POST['slot'] ?? null;
    
    if ($item_id <= 0 && !$slot) {
        echo json_encode(['error' => 'Invalid parameters']);
        return;
    }
    
    if ($slot) {
        // Unequip by slot
        $db->update('inventory', 
            ['equipped' => 0, 'equipped_slot' => null],
            'user_id = ? AND equipped_slot = ?',
            [$user_id, $slot]
        );
    } else {
        // Unequip by item id
        $db->update('inventory', 
            ['equipped' => 0, 'equipped_slot' => null],
            'user_id = ? AND item_id = ?',
            [$user_id, $item_id]
        );
    }
    
    // Update character stats
    updateCharacterStats($user_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Item unequipped successfully'
    ]);
}

function useItem() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    
    if ($item_id <= 0) {
        echo json_encode(['error' => 'Invalid item']);
        return;
    }
    
    // Get item details
    $item = $db->fetch("
        SELECT i.*, it.type, it.effect, it.effect_value
        FROM inventory i
        JOIN items it ON i.item_id = it.id
        WHERE i.user_id = ? AND i.item_id = ? AND i.quantity > 0
    ", [$user_id, $item_id]);
    
    if (!$item) {
        echo json_encode(['error' => 'Item not found or out of stock']);
        return;
    }
    
    if ($item['type'] !== 'consumable') {
        echo json_encode(['error' => 'Item is not usable']);
        return;
    }
    
    // Apply effect based on item type
    $effect = $item['effect'];
    $value = $item['effect_value'];
    $message = '';
    
    $character = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    switch ($effect) {
        case 'heal_hp':
            $new_hp = min($character['max_hp'], $character['current_hp'] + $value);
            $heal_amount = $new_hp - $character['current_hp'];
            
            $db->update('characters', 
                ['current_hp' => $new_hp],
                'user_id = ?',
                [$user_id]
            );
            
            $message = "Healed for $heal_amount HP";
            break;
            
        case 'restore_mp':
            $new_mp = min($character['max_mp'], $character['current_mp'] + $value);
            $restore_amount = $new_mp - $character['current_mp'];
            
            $db->update('characters', 
                ['current_mp' => $new_mp],
                'user_id = ?',
                [$user_id]
            );
            
            $message = "Restored $restore_amount MP";
            break;
            
        case 'buff_atk':
            // Apply temporary buff
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $db->insert('character_buffs', [
                'user_id' => $user_id,
                'buff_type' => 'atk',
                'buff_value' => $value,
                'expires_at' => $expires
            ]);
            
            $message = "Attack increased by $value for 1 hour";
            break;
            
        case 'buff_def':
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $db->insert('character_buffs', [
                'user_id' => $user_id,
                'buff_type' => 'def',
                'buff_value' => $value,
                'expires_at' => $expires
            ]);
            
            $message = "Defense increased by $value for 1 hour";
            break;
            
        default:
            echo json_encode(['error' => 'Unknown item effect']);
            return;
    }
    
    // Reduce quantity
    if ($item['quantity'] > 1) {
        $db->update('inventory', 
            ['quantity' => $item['quantity'] - 1],
            'user_id = ? AND item_id = ?',
            [$user_id, $item_id]
        );
    } else {
        $db->delete('inventory', 
            'user_id = ? AND item_id = ?',
            [$user_id, $item_id]
        );
    }
    
    // Update session
    $_SESSION['current_hp'] = $new_hp ?? $_SESSION['current_hp'];
    $_SESSION['current_mp'] = $new_mp ?? $_SESSION['current_mp'];
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'effect' => $effect,
        'value' => $value
    ]);
}

function sellItem() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($item_id <= 0 || $quantity <= 0) {
        echo json_encode(['error' => 'Invalid parameters']);
        return;
    }
    
    // Get inventory item
    $inventory_item = $db->fetch("
        SELECT i.*, it.buy_price, it.name
        FROM inventory i
        JOIN items it ON i.item_id = it.id
        WHERE i.user_id = ? AND i.item_id = ?
    ", [$user_id, $item_id]);
    
    if (!$inventory_item) {
        echo json_encode(['error' => 'Item not found in inventory']);
        return;
    }
    
    if ($inventory_item['quantity'] < $quantity) {
        echo json_encode(['error' => 'Not enough items to sell']);
        return;
    }
    
    // Calculate sell price (50% of buy price)
    $sell_price_per_item = floor($inventory_item['buy_price'] * 0.5);
    $total_sell_price = $sell_price_per_item * $quantity;
    
    // Update inventory
    if ($inventory_item['quantity'] > $quantity) {
        $db->update('inventory', 
            ['quantity' => $inventory_item['quantity'] - $quantity],
            'user_id = ? AND item_id = ?',
            [$user_id, $item_id]
        );
    } else {
        $db->delete('inventory', 
            'user_id = ? AND item_id = ?',
            [$user_id, $item_id]
        );
    }
    
    // Add gold to character
    $character = $db->fetch("SELECT gold FROM characters WHERE user_id = ?", [$user_id]);
    $new_gold = $character['gold'] + $total_sell_price;
    
    $db->update('characters', 
        ['gold' => $new_gold],
        'user_id = ?',
        [$user_id]
    );
    
    // Record transaction
    $db->insert('market_transactions', [
        'user_id' => $user_id,
        'item_id' => $item_id,
        'quantity' => $quantity,
        'price' => $total_sell_price,
        'type' => 'sell',
        'transaction_date' => date('Y-m-d H:i:s')
    ]);
    
    // Update session
    $_SESSION['gold'] = $new_gold;
    
    echo json_encode([
        'success' => true,
        'message' => 'Item sold successfully',
        'item_name' => $inventory_item['name'],
        'quantity' => $quantity,
        'price' => $total_sell_price,
        'new_gold' => $new_gold
    ]);
}

function getInventoryPreview() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo '';
        return;
    }
    
    // Get 6 most recent or valuable items
    $items = $db->fetchAll("
        SELECT i.*, it.name, it.rarity, it.image
        FROM inventory i
        JOIN items it ON i.item_id = it.id
        WHERE i.user_id = ? AND i.quantity > 0
        ORDER BY 
            CASE it.rarity
                WHEN 'legendary' THEN 1
                WHEN 'epic' THEN 2
                WHEN 'rare' THEN 3
                WHEN 'uncommon' THEN 4
                ELSE 5
            END,
            i.added_at DESC
        LIMIT 6
    ", [$user_id]);
    
    $html = '';
    foreach ($items as $item) {
        $quantity_display = $item['quantity'] > 1 ? "x{$item['quantity']}" : '';
        
        $html .= '
            <div class="inventory-item rarity-' . $item['rarity'] . '" 
                 title="' . htmlspecialchars($item['name']) . ' ' . $quantity_display . '">
                <div class="item-icon" style="background-image: url(\'images/items/' . $item['image'] . '\')"></div>
                ' . ($item['equipped'] ? '<div class="equipped-badge">E</div>' : '') . '
            </div>
        ';
    }
    
    // Fill empty slots
    $empty_slots = 6 - count($items);
    for ($i = 0; $i < $empty_slots; $i++) {
        $html .= '<div class="inventory-item empty"></div>';
    }
    
    echo $html;
}

function sortInventory() {
    // Sorting is handled in getInventoryItems
    echo json_encode(['success' => true]);
}

// Helper functions
function getSlotForItemType($item_type) {
    $slot_map = [
        'weapon' => 'weapon',
        'armor' => 'armor',
        'helmet' => 'helmet',
        'gloves' => 'gloves',
        'boots' => 'boots',
        'accessory' => 'accessory1'
    ];
    
    return $slot_map[$item_type] ?? 'misc';
}

function updateCharacterStats($user_id) {
    global $db;
    
    // Get base stats from character
    $character = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    
    // Get equipped items
    $equipped_items = $db->fetchAll("
        SELECT it.*
        FROM inventory i
        JOIN items it ON i.item_id = it.id
        WHERE i.user_id = ? AND i.equipped = 1
    ", [$user_id]);
    
    // Calculate total bonuses
    $total_atk = $character['atk'];
    $total_def = $character['def'];
    $total_hp = $character['max_hp'];
    $total_mp = $character['max_mp'];
    $total_crit = $character['crit'];
    $total_agi = $character['agi'];
    
    foreach ($equipped_items as $item) {
        $total_atk += $item['atk'];
        $total_def += $item['def'];
        $total_hp += $item['hp_bonus'];
        $total_mp += $item['mp_bonus'];
        $total_crit += $item['crit_bonus'];
        $total_agi += $item['agi_bonus'];
    }
    
    // Update character with new stats
    $db->update('characters', [
        'atk' => $total_atk,
        'def' => $total_def,
        'max_hp' => $total_hp,
        'max_mp' => $total_mp,
        'crit' => $total_crit,
        'agi' => $total_agi
    ], 'user_id = ?', [$user_id]);
    
    // Update session
    $_SESSION['atk'] = $total_atk;
    $_SESSION['def'] = $total_def;
    $_SESSION['max_hp'] = $total_hp;
    $_SESSION['max_mp'] = $total_mp;
    $_SESSION['crit'] = $total_crit;
    $_SESSION['agi'] = $total_agi;
    
    // Ensure current HP/MP doesn't exceed new max
    $current_hp = min($character['current_hp'], $total_hp);
    $current_mp = min($character['current_mp'], $total_mp);
    
    if ($current_hp != $character['current_hp'] || $current_mp != $character['current_mp']) {
        $db->update('characters', [
            'current_hp' => $current_hp,
            'current_mp' => $current_mp
        ], 'user_id = ?', [$user_id]);
        
        $_SESSION['current_hp'] = $current_hp;
        $_SESSION['current_mp'] = $current_mp;
    }
}
?>
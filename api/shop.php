<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'get_items':
        getShopItems();
        break;
    case 'buy':
        buyItem();
        break;
    case 'get_categories':
        getCategories();
        break;
    case 'get_vip_items':
        getVipItems();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getShopItems() {
    global $db;
    
    $category = $_GET['category'] ?? 'all';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $where = "1=1";
    $params = [];
    
    if ($category !== 'all') {
        $where = "category = ?";
        $params[] = $category;
    }
    
    // Get items
    $items = $db->fetchAll("
        SELECT * FROM shop_items 
        WHERE $where AND available = 1
        ORDER BY 
            CASE rarity 
                WHEN 'legendary' THEN 1
                WHEN 'epic' THEN 2
                WHEN 'rare' THEN 3
                WHEN 'uncommon' THEN 4
                ELSE 5
            END,
            price_credits DESC,
            price_gold DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));
    
    // Get total count
    $total = $db->count("shop_items", "$where AND available = 1", $params);
    
    echo json_encode([
        'items' => $items,
        'total' => $total,
        'pages' => ceil($total / $limit),
        'current_page' => $page
    ]);
}

function buyItem() {
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
    
    // Get item details
    $item = $db->fetch("SELECT * FROM shop_items WHERE id = ? AND available = 1", [$item_id]);
    if (!$item) {
        echo json_encode(['error' => 'Item not found']);
        return;
    }
    
    // Get player info
    $player = $db->fetch("
        SELECT c.*, u.vip_expire 
        FROM characters c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.user_id = ?
    ", [$user_id]);
    
    // Check requirements
    if ($item['required_level'] > $player['level']) {
        echo json_encode(['error' => 'Level requirement not met']);
        return;
    }
    
    if ($item['required_class'] && $item['required_class'] !== $player['class']) {
        echo json_encode(['error' => 'Class requirement not met']);
        return;
    }
    
    // Check if VIP item
    if ($item['vip_only'] && !isVipActive($player['vip_expire'])) {
        echo json_encode(['error' => 'VIP item - VIP required']);
        return;
    }
    
    // Calculate total cost
    $total_gold = $item['price_gold'] * $quantity;
    $total_credits = $item['price_credits'] * $quantity;
    
    // Check if player can afford
    if ($player['gold'] < $total_gold) {
        echo json_encode(['error' => 'Not enough gold']);
        return;
    }
    
    if ($player['credits'] < $total_credits) {
        echo json_encode(['error' => 'Not enough credits']);
        return;
    }
    
    // Start transaction
    $db->getConnection()->beginTransaction();
    
    try {
        // Deduct currency
        $new_gold = $player['gold'] - $total_gold;
        $new_credits = $player['credits'] - $total_credits;
        
        $db->update('characters', [
            'gold' => $new_gold,
            'credits' => $new_credits
        ], 'user_id = ?', [$user_id]);
        
        // Add item to inventory
        $existing_item = $db->fetch(
            "SELECT * FROM inventory WHERE user_id = ? AND item_id = ?",
            [$user_id, $item_id]
        );
        
        if ($existing_item) {
            // Update quantity
            $db->update('inventory', 
                ['quantity' => $existing_item['quantity'] + $quantity],
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
        
        // Record transaction
        $db->insert('shop_transactions', [
            'user_id' => $user_id,
            'item_id' => $item_id,
            'quantity' => $quantity,
            'total_gold' => $total_gold,
            'total_credits' => $total_credits,
            'transaction_date' => date('Y-m-d H:i:s')
        ]);
        
        $db->getConnection()->commit();
        
        // Update session
        $_SESSION['gold'] = $new_gold;
        $_SESSION['credits'] = $new_credits;
        
        echo json_encode([
            'success' => true,
            'message' => 'Purchase successful!',
            'item_name' => $item['name'],
            'quantity' => $quantity,
            'total_gold' => $total_gold,
            'total_credits' => $total_credits,
            'new_gold' => $new_gold,
            'new_credits' => $new_credits
        ]);
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        echo json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]);
    }
}

function getCategories() {
    $categories = [
        ['id' => 'all', 'name' => 'All Items', 'icon' => 'fa-box'],
        ['id' => 'weapons', 'name' => 'Weapons', 'icon' => 'fa-swords'],
        ['id' => 'armor', 'name' => 'Armor', 'icon' => 'fa-shield-alt'],
        ['id' => 'consumables', 'name' => 'Consumables', 'icon' => 'fa-potion-bottle'],
        ['id' => 'materials', 'name' => 'Materials', 'icon' => 'fa-gem'],
        ['id' => 'skills', 'name' => 'Skill Books', 'icon' => 'fa-scroll'],
        ['id' => 'vip', 'name' => 'VIP Items', 'icon' => 'fa-crown']
    ];
    
    echo json_encode($categories);
}

function getVipItems() {
    global $db;
    
    $vip_items = $db->fetchAll("
        SELECT * FROM shop_items 
        WHERE vip_only = 1 AND available = 1
        ORDER BY price_credits DESC
    ");
    
    echo json_encode($vip_items);
}
?>
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'get_plans':
        getVipPlans();
        break;
    case 'purchase':
        purchaseVip();
        break;
    case 'check_status':
        checkVipStatus();
        break;
    case 'get_benefits':
        getVipBenefits();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getVipPlans() {
    $plans = [
        [
            'id' => 1,
            'name' => 'VIP 7 Days',
            'description' => 'Perfect for trying out VIP benefits',
            'duration' => 7,
            'price_gold' => 50000,
            'price_credits' => 500,
            'price_cash' => 4.99,
            'benefits' => [
                '+30% EXP Gain',
                '+20% Drop Rate',
                'Access to VIP Shop',
                'No Ads'
            ],
            'popular' => false
        ],
        [
            'id' => 2,
            'name' => 'VIP 30 Days',
            'description' => 'Best value for dedicated players',
            'duration' => 30,
            'price_gold' => 180000,
            'price_credits' => 1800,
            'price_cash' => 14.99,
            'benefits' => [
                '+50% EXP Gain',
                '+30% Drop Rate',
                'Access to VIP Shop',
                'VIP Skills',
                'No Ads',
                'Priority Support'
            ],
            'popular' => true
        ],
        [
            'id' => 3,
            'name' => 'VIP 90 Days',
            'description' => 'Ultimate package for hardcore players',
            'duration' => 90,
            'price_gold' => 450000,
            'price_credits' => 4500,
            'price_cash' => 34.99,
            'benefits' => [
                '+75% EXP Gain',
                '+50% Drop Rate',
                'Access to VIP Shop',
                'VIP Skills',
                'Exclusive Items',
                'Priority Support',
                'No Ads',
                'Monthly Gift Box'
            ],
            'popular' => false
        ]
    ];
    
    // Generate HTML for plans
    $html = '';
    foreach ($plans as $plan) {
        $popular_badge = $plan['popular'] ? '<div class="popular-badge">Most Popular</div>' : '';
        
        $html .= '
            <div class="vip-plan ' . ($plan['popular'] ? 'popular' : '') . '">
                ' . $popular_badge . '
                <h4>' . $plan['name'] . '</h4>
                <div class="plan-description">' . $plan['description'] . '</div>
                <div class="plan-duration">' . $plan['duration'] . ' Days</div>
                
                <div class="plan-prices">
                    <div class="price-option">
                        <div class="price-amount">' . number_format($plan['price_gold']) . '</div>
                        <div class="price-currency"><i class="fas fa-coins"></i> Gold</div>
                    </div>
                    <div class="price-option">
                        <div class="price-amount">' . number_format($plan['price_credits']) . '</div>
                        <div class="price-currency"><i class="fas fa-gem"></i> Credits</div>
                    </div>
                    <div class="price-option highlight">
                        <div class="price-amount">$' . $plan['price_cash'] . '</div>
                        <div class="price-currency">USD</div>
                    </div>
                </div>
                
                <div class="plan-benefits">
                    <ul>';
        
        foreach ($plan['benefits'] as $benefit) {
            $html .= '<li><i class="fas fa-check"></i> ' . $benefit . '</li>';
        }
        
        $html .= '
                    </ul>
                </div>
                
                <button class="btn-buy" data-plan="' . $plan['id'] . '">
                    <i class="fas fa-shopping-cart"></i> Purchase
                </button>
            </div>
        ';
    }
    
    echo $html;
}

function purchaseVip() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
    $payment_method = $_POST['payment_method'] ?? 'credits';
    
    if ($plan_id <= 0) {
        echo json_encode(['error' => 'Invalid plan']);
        return;
    }
    
    // Define plans (same as above)
    $plans = [
        1 => ['duration' => 7, 'price_gold' => 50000, 'price_credits' => 500, 'price_cash' => 4.99],
        2 => ['duration' => 30, 'price_gold' => 180000, 'price_credits' => 1800, 'price_cash' => 14.99],
        3 => ['duration' => 90, 'price_gold' => 450000, 'price_credits' => 4500, 'price_cash' => 34.99]
    ];
    
    if (!isset($plans[$plan_id])) {
        echo json_encode(['error' => 'Plan not found']);
        return;
    }
    
    $plan = $plans[$plan_id];
    
    // Get player info
    $player = $db->fetch("
        SELECT c.*, u.vip_expire 
        FROM characters c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.user_id = ?
    ", [$user_id]);
    
    // Check payment method and process
    if ($payment_method === 'gold') {
        if ($player['gold'] < $plan['price_gold']) {
            echo json_encode(['error' => 'Not enough gold']);
            return;
        }
        
        // Deduct gold
        $new_gold = $player['gold'] - $plan['price_gold'];
        $db->update('characters', ['gold' => $new_gold], 'user_id = ?', [$user_id]);
        
    } elseif ($payment_method === 'credits') {
        if ($player['credits'] < $plan['price_credits']) {
            echo json_encode(['error' => 'Not enough credits']);
            return;
        }
        
        // Deduct credits
        $new_credits = $player['credits'] - $plan['price_credits'];
        $db->update('characters', ['credits' => $new_credits], 'user_id = ?', [$user_id]);
        
    } elseif ($payment_method === 'cash') {
        // In a real application, integrate with payment gateway here
        // For demo, we'll simulate successful payment
        // $payment_result = processPayment($plan['price_cash']);
        // if (!$payment_result) {
        //     echo json_encode(['error' => 'Payment failed']);
        //     return;
        // }
    } else {
        echo json_encode(['error' => 'Invalid payment method']);
        return;
    }
    
    // Calculate new VIP expiration
    $current_expire = $player['vip_expire'] ? new DateTime($player['vip_expire']) : new DateTime();
    $now = new DateTime();
    
    if ($current_expire > $now) {
        // Extend from current expiration
        $new_expire = $current_expire->add(new DateInterval('P' . $plan['duration'] . 'D'));
    } else {
        // Start from now
        $new_expire = $now->add(new DateInterval('P' . $plan['duration'] . 'D'));
    }
    
    // Update VIP status
    $db->update('users', [
        'vip_expire' => $new_expire->format('Y-m-d H:i:s')
    ], 'id = ?', [$user_id]);
    
    // Record transaction
    $db->insert('vip_transactions', [
        'user_id' => $user_id,
        'plan_id' => $plan_id,
        'duration' => $plan['duration'],
        'payment_method' => $payment_method,
        'amount' => $payment_method === 'cash' ? $plan['price_cash'] : 0,
        'transaction_date' => date('Y-m-d H:i:s')
    ]);
    
    // Give VIP welcome gifts for first-time VIPs
    if (!$player['vip_expire'] || new DateTime($player['vip_expire']) < $now) {
        giveVipWelcomeGifts($user_id);
    }
    
    // Update session
    $_SESSION['vip_expire'] = $new_expire->format('Y-m-d H:i:s');
    if ($payment_method === 'gold') {
        $_SESSION['gold'] = $new_gold;
    } elseif ($payment_method === 'credits') {
        $_SESSION['credits'] = $new_credits;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'VIP activated successfully!',
        'expires' => $new_expire->format('Y-m-d H:i:s'),
        'plan_duration' => $plan['duration'] . ' days'
    ]);
}

function checkVipStatus() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $user = $db->fetch("SELECT vip_expire FROM users WHERE id = ?", [$user_id]);
    
    $is_active = isVipActive($user['vip_expire']);
    $expires = $user['vip_expire'];
    
    if ($is_active) {
        $expire_date = new DateTime($expires);
        $now = new DateTime();
        $remaining = $now->diff($expire_date);
        
        $remaining_days = $remaining->days;
        $remaining_hours = $remaining->h;
        
        $message = "VIP active - Expires in {$remaining_days} days, {$remaining_hours} hours";
    } else {
        $message = "VIP inactive";
    }
    
    echo json_encode([
        'active' => $is_active,
        'expires' => $expires,
        'message' => $message
    ]);
}

function getVipBenefits() {
    $benefits = [
        [
            'title' => 'Experience Boost',
            'description' => 'Earn up to 75% more experience points from battles',
            'icon' => 'fa-star',
            'tiers' => [
                '7 Days' => '+30% EXP',
                '30 Days' => '+50% EXP',
                '90 Days' => '+75% EXP'
            ]
        ],
        [
            'title' => 'Drop Rate Increase',
            'description' => 'Get better loot with increased drop rates',
            'icon' => 'fa-gift',
            'tiers' => [
                '7 Days' => '+20% Drops',
                '30 Days' => '+30% Drops',
                '90 Days' => '+50% Drops'
            ]
        ],
        [
            'title' => 'Exclusive Access',
            'description' => 'Access VIP-only items, skills, and areas',
            'icon' => 'fa-crown',
            'tiers' => [
                '7 Days' => 'VIP Shop Access',
                '30 Days' => '+VIP Skills',
                '90 Days' => '+Exclusive Items'
            ]
        ],
        [
            'title' => 'Quality of Life',
            'description' => 'Enjoy an ad-free experience with premium support',
            'icon' => 'fa-gem',
            'tiers' => [
                '7 Days' => 'No Ads',
                '30 Days' => '+Priority Support',
                '90 Days' => '+Monthly Gift Box'
            ]
        ]
    ];
    
    echo json_encode($benefits);
}

function giveVipWelcomeGifts($user_id) {
    global $db;
    
    $welcome_items = [
        ['item_id' => 1001, 'quantity' => 1], // Elucidator
        ['item_id' => 1003, 'quantity' => 1], // Coat of Midnight
        ['item_id' => 2001, 'quantity' => 5], // Large HP Potion
        ['item_id' => 2002, 'quantity' => 5], // Large MP Potion
    ];
    
    foreach ($welcome_items as $gift) {
        addItemToInventory($user_id, $gift['item_id'], $gift['quantity']);
    }
    
    // Add VIP skill
    $db->insert('player_skills', [
        'user_id' => $user_id,
        'skill_id' => 100,
        'unlocked_at' => date('Y-m-d H:i:s')
    ]);
}

// Helper function (also used in battle.php)
function addItemToInventory($user_id, $item_id, $quantity = 1) {
    global $db;
    
    $existing = $db->fetch(
        "SELECT * FROM inventory WHERE user_id = ? AND item_id = ?",
        [$user_id, $item_id]
    );
    
    if ($existing) {
        $db->update('inventory', 
            ['quantity' => $existing['quantity'] + $quantity],
            'user_id = ? AND item_id = ?',
            [$user_id, $item_id]
        );
    } else {
        $db->insert('inventory', [
            'user_id' => $user_id,
            'item_id' => $item_id,
            'quantity' => $quantity,
            'added_at' => date('Y-m-d H:i:s')
        ]);
    }
}
?>
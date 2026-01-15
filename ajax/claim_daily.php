<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Check last claim
$last_claim = $db->fetch("
    SELECT * FROM daily_rewards 
    WHERE user_id = ? 
    ORDER BY claim_date DESC 
    LIMIT 1
", [$user_id]);

// Determine streak
$streak = 1;
$can_claim = false;

if ($last_claim) {
    $last_claim_date = strtotime($last_claim['claim_date']);
    $yesterday = strtotime('-1 day');
    $today = strtotime('today');
    
    if (date('Y-m-d', $last_claim_date) == date('Y-m-d')) {
        // Already claimed today
        echo json_encode(['success' => false, 'message' => 'Você já recebeu sua recompensa diária hoje']);
        exit;
    } elseif (date('Y-m-d', $last_claim_date) == date('Y-m-d', $yesterday)) {
        // Claimed yesterday, continue streak
        $streak = $last_claim['streak'] + 1;
        $can_claim = true;
    } else {
        // Streak broken
        $streak = 1;
        $can_claim = true;
    }
} else {
    // First claim
    $can_claim = true;
}

if (!$can_claim) {
    echo json_encode(['success' => false, 'message' => 'Não é possível receber recompensa agora']);
    exit;
}

// Calculate rewards based on streak
$base_gold = 100;
$base_credits = 5;

// Streak bonus
$gold_bonus = min(500, $base_gold * ($streak * 0.2)); // +20% per day, max 500
$credits_bonus = min(50, $base_credits * ($streak * 0.1)); // +10% per day, max 50

$total_gold = $base_gold + $gold_bonus;
$total_credits = $base_credits + $credits_bonus;

// VIP bonus
$user = $db->fetch("SELECT vip_expire FROM users WHERE id = ?", [$user_id]);
$is_vip = (strtotime($user['vip_expire']) > time());

if ($is_vip) {
    $total_gold = floor($total_gold * 1.5); // +50% for VIP
    $total_credits = floor($total_credits * 1.5);
}

// Chance for rare item on 7+ day streak
$rare_item = null;
if ($streak >= 7) {
    $rare_chance = min(50, ($streak - 6) * 10); // 10% per day after day 7, max 50%
    
    if (rand(1, 100) <= $rare_chance) {
        $rare_item = $db->fetch("
            SELECT * FROM items 
            WHERE rarity IN ('rare', 'epic') 
            ORDER BY RAND() 
            LIMIT 1
        ");
        
        if ($rare_item) {
            // Add to inventory
            $db->insert('inventory', [
                'user_id' => $user_id,
                'item_id' => $rare_item['id'],
                'quantity' => 1
            ]);
        }
    }
}

// Update player gold and credits
$db->query("
    UPDATE characters 
    SET gold = gold + ?, credits = credits + ? 
    WHERE user_id = ?
", [$total_gold, $total_credits, $user_id]);

// Record claim
$db->insert('daily_rewards', [
    'user_id' => $user_id,
    'claim_date' => date('Y-m-d'),
    'streak' => $streak,
    'gold_reward' => $total_gold,
    'credits_reward' => $total_credits,
    'item_reward_id' => $rare_item ? $rare_item['id'] : null
]);

// Prepare response
$rewards = [
    $total_gold . ' ouro',
    $total_credits . ' créditos'
];

if ($rare_item) {
    $rewards[] = $rare_item['name'];
}

echo json_encode([
    'success' => true,
    'streak' => $streak,
    'rewards' => $rewards,
    'gold' => $total_gold,
    'credits' => $total_credits,
    'item' => $rare_item ? $rare_item['name'] : null,
    'message' => 'Recompensa diária recebida com sucesso!'
]);
<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Get character energy
$character = $db->fetch("
    SELECT energy, energy_regen, vip_expire 
    FROM characters c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.user_id = ?
", [$user_id]);

if (!$character) {
    echo json_encode(['error' => 'Personagem não encontrado']);
    exit;
}

$energy = $character['energy'];
$energy_regen = strtotime($character['energy_regen']);
$current_time = time();
$is_vip = (strtotime($character['vip_expire']) > time());

// Calculate regenerated energy
if ($energy < 60) {
    $minutes_passed = floor(($current_time - $energy_regen) / 60);
    $regen_rate = $is_vip ? 2 : 4; // VIP regens faster (2 min per energy vs 4 min)
    $regen_amount = floor($minutes_passed / $regen_rate);
    
    if ($regen_amount > 0) {
        $new_energy = min(60, $energy + $regen_amount);
        if ($new_energy > $energy) {
            // Update energy in database
            $db->query("
                UPDATE characters 
                SET energy = ?, energy_regen = DATE_ADD(NOW(), INTERVAL -MOD(?, ?) MINUTE) 
                WHERE user_id = ?
            ", [$new_energy, $minutes_passed, $regen_rate, $user_id]);
            $energy = $new_energy;
        }
    }
}

echo json_encode([
    'energy' => $energy,
    'max_energy' => 60,
    'percentage' => ($energy / 60) * 100,
    'is_vip' => $is_vip,
    'regen_rate' => $is_vip ? '2 minutos por energia' : '4 minutos por energia'
]);
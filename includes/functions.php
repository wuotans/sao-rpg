<?php
// Utility Functions for SAO RPG

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Hash password
function hashPassword($password) {
    return password_hash($password . SALT, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password . SALT, $hash);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Get player info
function getPlayerInfo($user_id) {
    $db = Database::getInstance();
    
    $player = $db->fetch("
        SELECT c.*, u.username, u.vip_expire 
        FROM characters c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.user_id = ?
    ", [$user_id]);
    
    return $player ?: null;
}

// Calculate required exp for level
function getExpForLevel($level) {
    return round(BASE_EXP * pow(EXP_MULTIPLIER, $level - 1));
}

// Check for level up
function checkLevelUp($player_id, $exp_gained) {
    $db = Database::getInstance();
    
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$player_id]);
    if (!$player) return false;
    
    $new_exp = $player['exp'] + $exp_gained;
    $required_exp = getExpForLevel($player['level']);
    
    if ($new_exp >= $required_exp) {
        // Level up!
        $new_level = $player['level'] + 1;
        $remaining_exp = $new_exp - $required_exp;
        
        // Update player stats
        $stat_increase = [
            'level' => $new_level,
            'exp' => $remaining_exp,
            'max_hp' => $player['max_hp'] + round($player['max_hp'] * 0.1),
            'max_mp' => $player['max_mp'] + round($player['max_mp'] * 0.1),
            'atk' => $player['atk'] + 2,
            'def' => $player['def'] + 1,
            'agi' => $player['agi'] + 1,
            'current_hp' => $player['max_hp'] + round($player['max_hp'] * 0.1),
            'current_mp' => $player['max_mp'] + round($player['max_mp'] * 0.1)
        ];
        
        $db->update('characters', $stat_increase, 'user_id = ?', [$player_id]);
        
        // Update session
        $_SESSION['level'] = $new_level;
        $_SESSION['exp'] = $remaining_exp;
        $_SESSION['max_hp'] = $stat_increase['max_hp'];
        $_SESSION['max_mp'] = $stat_increase['max_mp'];
        $_SESSION['current_hp'] = $stat_increase['current_hp'];
        $_SESSION['current_mp'] = $stat_increase['current_mp'];
        $_SESSION['atk'] = $stat_increase['atk'];
        $_SESSION['def'] = $stat_increase['def'];
        $_SESSION['agi'] = $stat_increase['agi'];
        
        return [
            'level_up' => true,
            'old_level' => $player['level'],
            'new_level' => $new_level,
            'remaining_exp' => $remaining_exp,
            'new_stats' => $stat_increase
        ];
    } else {
        // Just add exp
        $db->update('characters', ['exp' => $new_exp], 'user_id = ?', [$player_id]);
        $_SESSION['exp'] = $new_exp;
        return ['level_up' => false];
    }
}

// Get drop item based on rates
function getRandomDrop($floor, $is_vip = false) {
    $db = Database::getInstance();
    
    // Adjust drop rate if VIP
    $drop_rate_multiplier = $is_vip ? DROP_RATE_VIP_MULTIPLIER : 1;
    
    // Get items for this floor
    $items = $db->fetchAll("
        SELECT * FROM items 
        WHERE floor_available <= ? 
        AND drop_rate > 0
        ORDER BY rarity DESC
    ", [$floor]);
    
    $drops = [];
    
    foreach ($items as $item) {
        $chance = $item['drop_rate'] * $drop_rate_multiplier;
        
        // Adjust chance by rarity
        switch ($item['rarity']) {
            case 'common': $chance *= 1; break;
            case 'uncommon': $chance *= 0.5; break;
            case 'rare': $chance *= 0.25; break;
            case 'epic': $chance *= 0.1; break;
            case 'legendary': $chance *= 0.01; break;
        }
        
        if (mt_rand(1, 1000) / 1000 <= $chance) {
            $drops[] = $item;
        }
    }
    
    return $drops;
}

// Calculate battle damage
function calculateDamage($attacker_atk, $defender_def, $crit_chance = 5) {
    $base_damage = max(1, $attacker_atk - $defender_def);
    
    // Critical hit chance
    $is_critical = mt_rand(1, 100) <= $crit_chance;
    
    if ($is_critical) {
        $damage = round($base_damage * 1.5);
        return ['damage' => $damage, 'critical' => true];
    }
    
    // Normal damage with some variance
    $variance = mt_rand(90, 110) / 100;
    $damage = round($base_damage * $variance);
    
    return ['damage' => max(1, $damage), 'critical' => false];
}

// Check if VIP is active
function isVipActive($vip_expire) {
    if (!$vip_expire) return false;
    $expire_date = new DateTime($vip_expire);
    $now = new DateTime();
    return $now < $expire_date;
}

// Format number with commas
function formatNumber($number) {
    return number_format($number);
}

// Get time ago string
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $time);
    }
}

// Generate random monster for floor
function generateMonster($floor) {
    $monsters = [
        1 => [
            ['name' => 'Frenzy Boar', 'hp' => 50, 'atk' => 8, 'def' => 3, 'exp' => 10, 'gold' => 5],
            ['name' => 'Dire Wolf', 'hp' => 80, 'atk' => 12, 'def' => 5, 'exp' => 15, 'gold' => 8],
        ],
        2 => [
            ['name' => 'Kobold Sentinel', 'hp' => 120, 'atk' => 15, 'def' => 8, 'exp' => 25, 'gold' => 15],
            ['name' => 'Lesser Dragon', 'hp' => 200, 'atk' => 25, 'def' => 12, 'exp' => 40, 'gold' => 25],
        ],
        // Add more floors...
    ];
    
    $floor_monsters = $monsters[min($floor, count($monsters))] ?? $monsters[1];
    $random_monster = $floor_monsters[array_rand($floor_monsters)];
    
    // Scale with floor
    $multiplier = pow(1.2, $floor - 1);
    
    return [
        'name' => $random_monster['name'],
        'hp' => round($random_monster['hp'] * $multiplier),
        'max_hp' => round($random_monster['hp'] * $multiplier),
        'atk' => round($random_monster['atk'] * $multiplier),
        'def' => round($random_monster['def'] * $multiplier),
        'exp' => round($random_monster['exp'] * $multiplier),
        'gold' => round($random_monster['gold'] * $multiplier),
        'floor' => $floor
    ];
}
?>
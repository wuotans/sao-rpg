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

// Initialize user session after login/register
function initSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['vip_expire'] = $user['vip_expire'];
    $_SESSION['is_admin'] = isset($user['is_admin']) ? $user['is_admin'] : false;
    
    // Get character data
    $db = Database::getInstance();
    $character = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user['id']]);
    
    if ($character) {
        $_SESSION['character_id'] = $character['id'];
        $_SESSION['character_name'] = $character['name'];
        $_SESSION['character_class'] = $character['class'];
        $_SESSION['level'] = $character['level'];
        $_SESSION['exp'] = $character['exp'];
        $_SESSION['max_exp'] = $character['max_exp'];
        $_SESSION['hp'] = $character['hp'];
        $_SESSION['max_hp'] = $character['max_hp'];
        $_SESSION['current_hp'] = $character['current_hp'];
        $_SESSION['mp'] = $character['mp'];
        $_SESSION['max_mp'] = $character['max_mp'];
        $_SESSION['current_mp'] = $character['current_mp'];
        $_SESSION['atk'] = $character['atk'];
        $_SESSION['def'] = $character['def'];
        $_SESSION['agi'] = $character['agi'];
        $_SESSION['crit'] = $character['crit'];
        $_SESSION['gold'] = $character['gold'];
        $_SESSION['credits'] = $character['credits'];
        $_SESSION['current_floor'] = $character['current_floor'];
        $_SESSION['energy'] = $character['energy'];
        $_SESSION['energy_regen'] = $character['energy_regen'];
        $_SESSION['avatar'] = $character['avatar'];
        $_SESSION['created_at'] = $character['created_at'];
        
        // Initialize monster progress for current floor
        if (!isset($_SESSION['monster_progress'][$character['current_floor']])) {
            $_SESSION['monster_progress'][$character['current_floor']] = 1;
        }
    }
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

// Obter status totais do jogador (personagem + equipamentos)
function getPlayerTotalStats($user_id) {
    $db = Database::getInstance();
    
    // Status base do personagem
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    if (!$player) return null;
    
    // Inicializar status totais
    $totalStats = [
        'hp' => $player['max_hp'],
        'max_hp' => $player['max_hp'],
        'current_hp' => $player['current_hp'],
        'mp' => $player['max_mp'],
        'max_mp' => $player['max_mp'],
        'current_mp' => $player['current_mp'],
        'atk' => $player['atk'],
        'def' => $player['def'],
        'agi' => $player['agi'],
        'crit' => $player['crit'],
        'dodge' => 5.0, // Base 5%
        'accuracy' => 95.0, // Base 95%
        'damage_min' => $player['atk'], // Dano mínimo base
        'damage_max' => $player['atk'] + 5, // Dano máximo base
        'elemental_resistance' => [
            'fire' => 0,
            'ice' => 0,
            'lightning' => 0,
            'holy' => 0,
            'dark' => 0
        ],
        'equipped_items' => []
    ];
    
    // Obter itens equipados
    $equipped = $db->fetchAll("
        SELECT i.*, ei.slot 
        FROM equipped_items ei
        JOIN items i ON ei.item_id = i.id
        WHERE ei.user_id = ?
    ", [$user_id]);
    
    // Calcular bônus dos equipamentos
    foreach ($equipped as $item) {
        $totalStats['equipped_items'][$item['slot']] = $item;
        
        // HP/MP
        $totalStats['max_hp'] += $item['hp_bonus'];
        $totalStats['max_mp'] += $item['mp_bonus'];
        $totalStats['hp'] += $item['hp_bonus'];
        $totalStats['mp'] += $item['mp_bonus'];
        
        // Status básicos
        $totalStats['atk'] += $item['atk_bonus'];
        $totalStats['def'] += $item['def_bonus'];
        $totalStats['agi'] += $item['agi_bonus'];
        $totalStats['crit'] += $item['crit_bonus'];
        $totalStats['dodge'] += $item['dodge_bonus'];
        $totalStats['accuracy'] += $item['accuracy_bonus'];
        
        // Dano da arma
        if ($item['weapon_damage_min'] > 0) {
            $totalStats['damage_min'] = $item['weapon_damage_min'];
            $totalStats['damage_max'] = $item['weapon_damage_max'];
        } else {
            // Para não-armas, adicionar ao dano base
            $totalStats['damage_min'] += $item['atk_bonus'];
            $totalStats['damage_max'] += $item['atk_bonus'];
        }
        
        // Resistências elementais
        if ($item['elemental_resistance'] > 0) {
            $element = $item['damage_type'];
            if ($element != 'physical') {
                $totalStats['elemental_resistance'][$element] += $item['elemental_resistance'];
            }
        }
    }
    
    // Ajustar valores máximos
    $totalStats['crit'] = min(50.0, max(0, $totalStats['crit']));
    $totalStats['dodge'] = min(40.0, max(0, $totalStats['dodge']));
    $totalStats['accuracy'] = min(100.0, max(0, $totalStats['accuracy']));
    
    // Garantir que HP/MP atuais não excedam máximos
    $totalStats['current_hp'] = min($totalStats['current_hp'], $totalStats['max_hp']);
    $totalStats['current_mp'] = min($totalStats['current_mp'], $totalStats['max_mp']);
    
    return $totalStats;
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
        
        // Calculate stat increases
        $hp_increase = round($player['max_hp'] * 0.15);
        $mp_increase = round($player['max_mp'] * 0.15);
        
        // Update player stats
        $stat_increase = [
            'level' => $new_level,
            'exp' => $remaining_exp,
            'max_hp' => $player['max_hp'] + $hp_increase,
            'max_mp' => $player['max_mp'] + $mp_increase,
            'atk' => $player['atk'] + 3,
            'def' => $player['def'] + 2,
            'agi' => $player['agi'] + 2,
            'crit' => min(30, $player['crit'] + 0.5), // Cap crit at 30%
            'current_hp' => $player['max_hp'] + $hp_increase,
            'current_mp' => $player['max_mp'] + $mp_increase
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
        $_SESSION['crit'] = $stat_increase['crit'];
        
        return [
            'level_up' => true,
            'old_level' => $player['level'],
            'new_level' => $new_level,
            'remaining_exp' => $remaining_exp,
            'new_stats' => $stat_increase,
            'message' => "LEVEL UP! You are now level $new_level!"
        ];
    } else {
        // Just add exp
        $db->update('characters', ['exp' => $new_exp], 'user_id = ?', [$player_id]);
        $_SESSION['exp'] = $new_exp;
        return ['level_up' => false];
    }
}

// Get drop item based on rates
function getRandomDrop($floor, $is_vip = false, $multiplier = 1) {
    $db = Database::getInstance();
    
    // Adjust drop rate if VIP
    $drop_rate_multiplier = $is_vip ? DROP_RATE_VIP_MULTIPLIER : 1;
    $drop_rate_multiplier *= $multiplier; // For boss drops
    
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
            case 'common': $chance *= 1.5; break;
            case 'uncommon': $chance *= 0.8; break;
            case 'rare': $chance *= 0.4; break;
            case 'epic': $chance *= 0.15; break;
            case 'legendary': $chance *= 0.02; break;
        }
        
        // Add floor bonus (higher floors have better drops)
        $floor_bonus = 1 + ($floor * 0.1);
        $chance *= $floor_bonus;
        
        if (mt_rand(1, 1000) / 1000 <= $chance) {
            $drops[] = $item;
            
            // Limit drops to 3 items max per battle
            if (count($drops) >= 3) {
                break;
            }
        }
    }
    
    return $drops;
}

// Calculate battle damage
function calculateDamage($attacker_atk, $defender_def, $crit_chance = 5) {
    $base_damage = max(1, $attacker_atk - ($defender_def * 0.7));
    
    // Add random variance (85-115%)
    $variance = mt_rand(85, 115) / 100;
    $base_damage = round($base_damage * $variance);
    
    // Critical hit chance
    $is_critical = mt_rand(1, 100) <= $crit_chance;
    
    if ($is_critical) {
        $damage = round($base_damage * 2.0); // Critical hits do 2x damage
        return ['damage' => $damage, 'critical' => true];
    }
    
    return ['damage' => max(1, $base_damage), 'critical' => false];
}

// Calcular dano do ataque básico
function calculateBasicAttackDamage($playerStats, $isCritical = false) {
    // Dano base da arma ou ATK
    $minDamage = $playerStats['damage_min'];
    $maxDamage = $playerStats['damage_max'];
    
    // Dano aleatório dentro do range
    $damage = rand($minDamage, $maxDamage);
    
    // Variação adicional (90-110%)
    $variation = rand(90, 110) / 100;
    $damage = floor($damage * $variation);
    
    // Aplicar crítico
    if ($isCritical) {
        $damage = floor($damage * 2.0); // Crítico básico 2x
    }
    
    return max(1, $damage);
}

// Calcular chance de acerto
function calculateHitChance($attackerAccuracy, $defenderDodge) {
    $baseChance = 95.0; // Chance base de acerto
    $accuracyBonus = $attackerAccuracy - 95.0;
    $dodgePenalty = $defenderDodge;
    
    $hitChance = $baseChance + $accuracyBonus - $dodgePenalty;
    
    // Limites
    $hitChance = max(10, min(100, $hitChance));
    
    return $hitChance;
}

// Calcular redução de dano pela defesa
function calculateDefenseReduction($damage, $defenderDef, $ignoreDefense = 0) {
    $effectiveDef = $defenderDef * (1 - ($ignoreDefense / 100));
    
    // Fórmula: redução = defesa / (defesa + 100)
    $reduction = $effectiveDef / ($effectiveDef + 100);
    $reducedDamage = $damage * (1 - $reduction);
    
    return max(1, floor($reducedDamage));
}

// Calcular chance de crítico (habilidade + personagem)
function calculateTotalCritChance($skill, $playerStats, $skillLevel = 1) {
    $playerCrit = $playerStats['crit'] ?? 5.0;
    $skillCritBonus = $skill['crit_bonus'] ?? 0;
    
    // Bônus de nível da habilidade (1% por nível)
    $levelBonus = ($skillLevel - 1) * 1.0;
    
    // Chance total
    $totalCrit = $playerCrit + $skillCritBonus + $levelBonus;
    
    // Limites
    return min(75.0, max(0, $totalCrit));
}

// Verificar se acertou
function checkHit($attackerAccuracy, $defenderDodge) {
    $hitChance = calculateHitChance($attackerAccuracy, $defenderDodge);
    return (rand(1, 100) <= $hitChance);
}

// Verificar se é crítico
function checkCritical($critChance) {
    return (rand(1, 100) <= $critChance);
}

// Calcular dano da habilidade (VERSÃO COMPLETA)
function calculateSkillDamage($skill, $playerStats, $skillLevel = 1, $isCritical = false) {
    // Fatores de escala
    $atkScaling = $skill['atk_scaling'] ?? 1.0;
    $weaponScaling = $skill['weapon_scaling'] ?? 0.5;
    $damageMultiplier = $skill['damage_multiplier'] ?? 1.0;
    
    // Dano base da habilidade
    $baseSkillDamage = $skill['base_damage'] ?? 0;
    
    // Contribuição do ATK do personagem
    $atkContribution = $playerStats['atk'] * $atkScaling;
    
    // Contribuição da arma (média do dano)
    $weaponAvgDamage = ($playerStats['damage_min'] + $playerStats['damage_max']) / 2;
    $weaponContribution = $weaponAvgDamage * $weaponScaling;
    
    // Multiplicador de nível (5% por nível)
    $levelMultiplier = 1 + (($skillLevel - 1) * 0.05);
    
    // Calcular dano base
    $baseDamage = ($baseSkillDamage + $atkContribution + $weaponContribution) * $damageMultiplier * $levelMultiplier;
    
    // Variação (80-120%)
    $variation = rand(80, 120) / 100;
    $damage = floor($baseDamage * $variation);
    
    // Aplicar crítico
    if ($isCritical) {
        $critMultiplier = $skill['crit_multiplier'] ?? 2.0;
        $damage = floor($damage * $critMultiplier);
    }
    
    // Dano elemental
    if (!empty($skill['element']) && $skill['element'] != 'physical') {
        $elementalPower = $skill['elemental_power'] ?? 0;
        if ($elementalPower > 0) {
            $elementalDamage = floor($damage * ($elementalPower / 100));
            $damage += $elementalDamage;
        }
    }
    
    return max(1, $damage);
}

// Calcular chance de crítico da habilidade (LEGACY - mantida para compatibilidade)
function calculateSkillCritChanceLegacy($skill, $player, $skillLevel = 1) {
    $baseCrit = $player['crit'] ?? 5.0;
    $skillCritBonus = $skill['crit_bonus'] ?? 0;
    
    // Bônus de nível da habilidade (1% por nível)
    $levelBonus = ($skillLevel - 1) * 1.0;
    
    // Chance total de crítico
    $totalCrit = $baseCrit + $skillCritBonus + $levelBonus;
    
    // Limitar a 50% no máximo
    return min(50.0, $totalCrit);
}

// Calcular custo de MP da habilidade
function calculateMpCost($skill, $skillLevel = 1) {
    $baseMpCost = $skill['mp_cost'] ?? 10;
    
    // Custo aumenta 10% por nível
    $levelMultiplier = 1 + (($skillLevel - 1) * 0.1);
    
    return floor($baseMpCost * $levelMultiplier);
}

// Calcular dano real (usada no processamento da batalha) - LEGACY
function calculateSkillRealDamage($skill, $player, $skillLevel = 1, $isCritical = false) {
    // Para compatibilidade, usar a nova função com stats simplificados
    $playerStats = [
        'atk' => $player['atk'],
        'damage_min' => $player['atk'],
        'damage_max' => $player['atk'] + 5
    ];
    
    return calculateSkillDamage($skill, $playerStats, $skillLevel, $isCritical);
}

// Verificar se acertou (accuracy check)
function checkAccuracy($skill, $skillLevel = 1) {
    $baseAccuracy = $skill['accuracy'] ?? 95;
    
    // Accuracy aumenta 0.5% por nível
    $accuracyBonus = ($skillLevel - 1) * 0.5;
    
    $totalAccuracy = min(100, $baseAccuracy + $accuracyBonus);
    
    return (rand(1, 100) <= $totalAccuracy);
}

// Obter informações da habilidade para exibição
function getSkillDisplayInfo($skill, $playerStats, $skillLevel = 1) {
    // Calcular dano mínimo e máximo
    $minDamage = calculateSkillDamage($skill, $playerStats, $skillLevel, false);
    
    // Para estimar máximo, usar 120% do mínimo (devido à variação)
    $maxDamage = floor($minDamage * 1.2);
    
    // Recalcular com possível crítico para mostrar range
    $minDamageWithCrit = calculateSkillDamage($skill, $playerStats, $skillLevel, true);
    $maxDamageWithCrit = floor($minDamageWithCrit * 1.2);
    
    // Chance de crítico
    $critChance = calculateTotalCritChance($skill, $playerStats, $skillLevel);
    
    // Custo de MP
    $mpCost = calculateMpCost($skill, $skillLevel);
    
    return [
        'damage_min' => $minDamage,
        'damage_max' => $maxDamage,
        'crit_damage_min' => $minDamageWithCrit,
        'crit_damage_max' => $maxDamageWithCrit,
        'crit_chance' => $critChance,
        'mp_cost' => $mpCost,
        'accuracy' => $skill['accuracy'] ?? 95,
        'crit_multiplier' => $skill['crit_multiplier'] ?? 2.0,
        'description' => $skill['description'],
        'type' => $skill['type'],
        'element' => $skill['element'] ?? 'physical'
    ];
}

// Equipar item
function equipItem($user_id, $inventory_id, $slot) {
    $db = Database::getInstance();
    
    // Verificar se item existe no inventário
    $item = $db->fetch("
        SELECT i.*, inv.id as inventory_id 
        FROM inventory inv
        JOIN items i ON inv.item_id = i.id
        WHERE inv.id = ? AND inv.user_id = ?
    ", [$inventory_id, $user_id]);
    
    if (!$item) {
        return ['success' => false, 'message' => 'Item not found in inventory'];
    }
    
    // Verificar tipo do item para slot correto
    $itemType = $db->fetch("SELECT * FROM item_types WHERE id = ?", [$item['type_id']]);
    $validSlot = false;
    
    switch ($itemType['slot']) {
        case 'weapon':
            $validSlot = ($slot == 'weapon');
            break;
        case 'armor':
            $validSlot = ($slot == 'armor');
            break;
        case 'helmet':
            $validSlot = ($slot == 'helmet');
            break;
        case 'gloves':
            $validSlot = ($slot == 'gloves');
            break;
        case 'boots':
            $validSlot = ($slot == 'boots');
            break;
        case 'accessory':
            $validSlot = ($slot == 'accessory1' || $slot == 'accessory2');
            break;
    }
    
    if (!$validSlot) {
        return ['success' => false, 'message' => 'Invalid slot for this item type'];
    }
    
    // Desequipar item atual no slot
    $db->delete('equipped_items', 'user_id = ? AND slot = ?', [$user_id, $slot]);
    
    // Equipar novo item
    $db->insert('equipped_items', [
        'user_id' => $user_id,
        'slot' => $slot,
        'item_id' => $item['id'],
        'inventory_id' => $inventory_id
    ]);
    
    // Marcar como equipado no inventário
    $db->update('inventory', ['equipped' => 1], 'id = ?', [$inventory_id]);
    
    return ['success' => true, 'message' => 'Item equipped successfully'];
}

// Desequipar item
function unequipItem($user_id, $slot) {
    $db = Database::getInstance();
    
    // Obter item equipado
    $equipped = $db->fetch("
        SELECT ei.*, inv.id as inventory_id
        FROM equipped_items ei
        JOIN inventory inv ON ei.inventory_id = inv.id
        WHERE ei.user_id = ? AND ei.slot = ?
    ", [$user_id, $slot]);
    
    if (!$equipped) {
        return ['success' => false, 'message' => 'No item equipped in this slot'];
    }
    
    // Remover do equipado
    $db->delete('equipped_items', 'id = ?', [$equipped['id']]);
    
    // Marcar como não equipado no inventário
    $db->update('inventory', ['equipped' => 0], 'id = ?', [$equipped['inventory_id']]);
    
    return ['success' => true, 'message' => 'Item unequipped successfully'];
}

// Obter itens equipados
function getEquippedItems($user_id) {
    $db = Database::getInstance();
    
    return $db->fetchAll("
        SELECT ei.slot, i.*, it.name as type_name
        FROM equipped_items ei
        JOIN items i ON ei.item_id = i.id
        JOIN item_types it ON i.type_id = it.id
        WHERE ei.user_id = ?
        ORDER BY 
            CASE ei.slot
                WHEN 'weapon' THEN 1
                WHEN 'armor' THEN 2
                WHEN 'helmet' THEN 3
                WHEN 'gloves' THEN 4
                WHEN 'boots' THEN 5
                WHEN 'accessory1' THEN 6
                WHEN 'accessory2' THEN 7
                ELSE 8
            END
    ", [$user_id]);
}

// Check if VIP is active
function isVipActive($vip_expire) {
    if (!$vip_expire || $vip_expire == '0000-00-00 00:00:00') return false;
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

// Generate random monster for floor (legacy function for backward compatibility)
function generateMonster($floor, $monsterNumber = null) {
    if ($monsterNumber === null) {
        // If no specific monster number, use current progress or random
        $monsterNumber = isset($_SESSION['monster_progress'][$floor]) 
            ? $_SESSION['monster_progress'][$floor] 
            : rand(1, 10);
    }
    
    return generateSpecificMonster($floor, $monsterNumber);
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
    
    // Stats base com scaling
    $baseHP = 50 + ($floor * 25) + ($monsterNumber * 15);
    $baseATK = 10 + ($floor * 4) + round($monsterNumber * 1.5);
    $baseDEF = 5 + ($floor * 2) + round($monsterNumber / 2);
    $baseEXP = 15 + ($floor * 10) + ($monsterNumber * 3);
    $baseGOLD = 8 + ($floor * 6) + ($monsterNumber * 2);
    
    // Boss gets significant bonus
    if ($isBoss) {
        $baseHP *= 4;
        $baseATK *= 2.5;
        $baseDEF *= 2;
        $baseEXP *= 8;
        $baseGOLD *= 15;
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

// Generate monster name (for display in monster list)
function generateMonsterName($floor, $monsterNumber) {
    $monsters = [
        1 => [
            'Frenzy Boar', 'Dire Wolf', 'Kobold Trooper', 'Goblin Scout', 
            'Lesser Bat', 'Wild Dog', 'Cave Rat', 'Forest Spider',
            'Swamp Slime', 'Rock Golem', 'Giant Boar King'
        ],
        2 => [
            'Kobold Sentinel', 'Goblin Warrior', 'Cave Bear', 'Giant Spider',
            'Skeleton Soldier', 'Zombie', 'Ghost', 'Wraith',
            'Shadow Wolf', 'Stone Guardian', 'Kobold King'
        ],
        3 => [
            'Orc Warrior', 'Troll', 'Harpy', 'Minotaur',
            'Cyclops', 'Basilisk', 'Chimera', 'Hydra',
            'Griffin', 'Dragon Whelp', 'Troll King'
        ],
        4 => [
            'Dark Knight', 'Death Knight', 'Lich', 'Vampire',
            'Werewolf', 'Banshee', 'Specter', 'Phantom',
            'Wight', 'Mummy', 'Vampire Lord'
        ],
        5 => [
            'Fire Elemental', 'Water Elemental', 'Earth Elemental', 'Air Elemental',
            'Lightning Elemental', 'Ice Elemental', 'Shadow Elemental', 'Light Elemental',
            'Chaos Elemental', 'Void Elemental', 'Elemental Lord'
        ]
    ];
    
    $floorMonsters = $monsters[$floor] ?? $monsters[1];
    return $floorMonsters[$monsterNumber - 1] ?? 'Unknown Monster';
}

// Get floor completion percentage
function getFloorCompletion($floor) {
    if (!isset($_SESSION['monster_progress'][$floor])) {
        return 0;
    }
    
    $currentMonster = $_SESSION['monster_progress'][$floor];
    return round(($currentMonster - 1) / 11 * 100, 1);
}

// Get next floor unlock requirements
function getNextFloorRequirements($currentFloor) {
    $requirements = [
        1 => 'Defeat Giant Boar King (Floor 1 Boss)',
        2 => 'Defeat Kobold King (Floor 2 Boss)',
        3 => 'Defeat Troll King (Floor 3 Boss)',
        4 => 'Defeat Vampire Lord (Floor 4 Boss)',
        5 => 'Defeat Elemental Lord (Floor 5 Boss)',
        6 => 'Reach Level 20',
        7 => 'Reach Level 30',
        8 => 'Reach Level 40',
        9 => 'Reach Level 50',
        10 => 'Defeat All Previous Bosses'
    ];
    
    return $requirements[$currentFloor] ?? 'Unknown requirements';
}

// Calculate boss drop chance
function getBossDropChance($floor, $is_vip = false) {
    $baseChance = 0.3 + ($floor * 0.1); // 30% base + 10% per floor
    if ($is_vip) {
        $baseChance *= VIP_DROP_MULTIPLIER;
    }
    return min(0.9, $baseChance); // Cap at 90%
}

// Restore player HP/MP (used after battle or in town)
function restorePlayerHealth($user_id, $hp_percent = 100, $mp_percent = 100) {
    $db = Database::getInstance();
    
    $player = $db->fetch("SELECT * FROM characters WHERE user_id = ?", [$user_id]);
    if (!$player) return false;
    
    $new_hp = round($player['max_hp'] * ($hp_percent / 100));
    $new_mp = round($player['max_mp'] * ($mp_percent / 100));
    
    $db->update('characters', [
        'current_hp' => $new_hp,
        'current_mp' => $new_mp
    ], 'user_id = ?', [$user_id]);
    
    // Update session
    $_SESSION['current_hp'] = $new_hp;
    $_SESSION['current_mp'] = $new_mp;
    
    return true;
}

// Check if player can fight boss
function canFightBoss($floor) {
    if (!isset($_SESSION['monster_progress'][$floor])) {
        return false;
    }
    
    // Can only fight boss if monsters 1-10 are defeated
    return $_SESSION['monster_progress'][$floor] == 11;
}

// Get total monsters defeated on floor
function getMonstersDefeated($floor) {
    if (!isset($_SESSION['monster_progress'][$floor])) {
        return 0;
    }
    
    // Progress shows next monster to fight, so defeated = progress - 1
    return $_SESSION['monster_progress'][$floor] - 1;
}
?>
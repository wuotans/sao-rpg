<?php
// pages/equipment.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

// Ações
$action = $_GET['action'] ?? '';

if ($action == 'equip' && isset($_GET['id'])) {
    $slot = $_POST['slot'] ?? '';
    $result = equipItem($user_id, $_GET['id'], $slot);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'error';
}

if ($action == 'unequip' && isset($_GET['slot'])) {
    $result = unequipItem($user_id, $_GET['slot']);
    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['success'] ? 'success' : 'error';
}

// Obter status totais
$totalStats = getPlayerTotalStats($user_id);
$equippedItems = getEquippedItems($user_id);

// Obter inventário
$inventory = $db->fetchAll("
    SELECT inv.*, i.*, it.name as type_name, it.slot as item_slot
    FROM inventory inv
    JOIN items i ON inv.item_id = i.id
    JOIN item_types it ON i.type_id = it.id
    WHERE inv.user_id = ? AND inv.quantity > 0
    ORDER BY i.rarity DESC, i.name ASC
", [$user_id]);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Equipment | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/sao-theme.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .equipment-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        .character-paperdoll {
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
            border: 2px solid #00b0ff;
        }
        
        .slot {
            margin: 15px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border: 1px solid #444;
            min-height: 80px;
        }
        
        .slot.empty {
            border: 2px dashed #666;
            color: #666;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .slot-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .item-icon {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            background: #222;
            border: 2px solid;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
        }
        
        .item-icon.common { border-color: #aaa; }
        .item-icon.uncommon { border-color: #4CAF50; }
        .item-icon.rare { border-color: #2196F3; }
        .item-icon.epic { border-color: #9C27B0; }
        .item-icon.legendary { border-color: #FF9800; }
        
        .item-stats {
            font-size: 0.9em;
            color: #90caf9;
        }
        
        .stats-panel {
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #00b0ff;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .inventory-item {
            background: rgba(0, 0, 0, 0.5);
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #444;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .inventory-item:hover {
            border-color: #00b0ff;
            transform: translateY(-3px);
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .item-rarity {
            font-size: 0.8em;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .rarity-common { background: #333; color: #aaa; }
        .rarity-uncommon { background: #1B5E20; color: #A5D6A7; }
        .rarity-rare { background: #0D47A1; color: #90CAF9; }
        .rarity-epic { background: #4A148C; color: #CE93D8; }
        .rarity-legendary { background: #E65100; color: #FFCC80; }
    </style>
</head>
<body class="sao-theme">
    <?php include '../includes/header.php'; ?>
    
    <main class="container">
        <h1><i class="fas fa-tshirt"></i> Equipment</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php echo $_SESSION['message']; ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
        
        <div class="equipment-container">
            <!-- Painel de Personagem -->
            <div>
                <div class="character-paperdoll">
                    <h3>Equipment Slots</h3>
                    
                    <?php
                    $slots = [
                        'weapon' => 'Weapon',
                        'armor' => 'Armor', 
                        'helmet' => 'Helmet',
                        'gloves' => 'Gloves',
                        'boots' => 'Boots',
                        'accessory1' => 'Accessory 1',
                        'accessory2' => 'Accessory 2'
                    ];
                    
                    foreach ($slots as $slotKey => $slotName):
                        $equipped = null;
                        foreach ($equippedItems as $item) {
                            if ($item['slot'] == $slotKey) {
                                $equipped = $item;
                                break;
                            }
                        }
                    ?>
                    <div class="slot <?php echo !$equipped ? 'empty' : ''; ?>">
                        <?php if ($equipped): ?>
                            <div class="slot-item">
                                <div class="item-icon <?php echo $equipped['rarity']; ?>">
                                    <?php 
                                    $icons = [
                                        'weapon' => '⚔️',
                                        'armor' => '🛡️',
                                        'helmet' => '⛑️',
                                        'gloves' => '🧤',
                                        'boots' => '👢',
                                        'accessory' => '💍'
                                    ];
                                    echo $icons[$equipped['type_name']] ?? '📦';
                                    ?>
                                </div>
                                <div>
                                    <div class="item-name"><?php echo $equipped['name']; ?></div>
                                    <div class="item-stats">
                                        <?php if ($equipped['atk_bonus'] > 0): ?>ATK +<?php echo $equipped['atk_bonus']; ?> <?php endif; ?>
                                        <?php if ($equipped['def_bonus'] > 0): ?>DEF +<?php echo $equipped['def_bonus']; ?> <?php endif; ?>
                                        <?php if ($equipped['crit_bonus'] > 0): ?>CRIT +<?php echo $equipped['crit_bonus']; ?>% <?php endif; ?>
                                    </div>
                                    <a href="?action=unequip&slot=<?php echo $slotKey; ?>" class="btn-small">Unequip</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div><?php echo $slotName; ?> (Empty)</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="stats-panel">
                    <h3>Total Stats</h3>
                    <div class="stat-row">
                        <span>HP:</span>
                        <span><?php echo $totalStats['current_hp']; ?>/<?php echo $totalStats['max_hp']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span>MP:</span>
                        <span><?php echo $totalStats['current_mp']; ?>/<?php echo $totalStats['max_mp']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span>ATK:</span>
                        <span><?php echo $totalStats['atk']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span>DEF:</span>
                        <span><?php echo $totalStats['def']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span>AGI:</span>
                        <span><?php echo $totalStats['agi']; ?></span>
                    </div>
                    <div class="stat-row">
                        <span>CRIT:</span>
                        <span><?php echo number_format($totalStats['crit'], 1); ?>%</span>
                    </div>
                    <div class="stat-row">
                        <span>DODGE:</span>
                        <span><?php echo number_format($totalStats['dodge'], 1); ?>%</span>
                    </div>
                    <div class="stat-row">
                        <span>ACCURACY:</span>
                        <span><?php echo number_format($totalStats['accuracy'], 1); ?>%</span>
                    </div>
                    <div class="stat-row">
                        <span>DAMAGE:</span>
                        <span><?php echo $totalStats['damage_min']; ?>-<?php echo $totalStats['damage_max']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Inventário -->
            <div>
                <h3>Inventory</h3>
                
                <div class="inventory-grid">
                    <?php foreach ($inventory as $item): 
                        $itemType = $db->fetch("SELECT * FROM item_types WHERE id = ?", [$item['type_id']]);
                    ?>
                    <div class="inventory-item" onclick="showItemModal(<?php echo $item['id']; ?>)">
                        <div class="item-name"><?php echo $item['name']; ?></div>
                        <div class="item-rarity rarity-<?php echo $item['rarity']; ?>">
                            <?php echo strtoupper($item['rarity']); ?>
                        </div>
                        <div class="item-stats">
                            <?php if ($item['atk_bonus'] > 0): ?><div>ATK +<?php echo $item['atk_bonus']; ?></div><?php endif; ?>
                            <?php if ($item['def_bonus'] > 0): ?><div>DEF +<?php echo $item['def_bonus']; ?></div><?php endif; ?>
                            <?php if ($item['crit_bonus'] > 0): ?><div>CRIT +<?php echo $item['crit_bonus']; ?>%</div><?php endif; ?>
                            <?php if ($item['dodge_bonus'] > 0): ?><div>DODGE +<?php echo $item['dodge_bonus']; ?>%</div><?php endif; ?>
                            <?php if ($item['weapon_damage_min'] > 0): ?><div>DMG <?php echo $item['weapon_damage_min']; ?>-<?php echo $item['weapon_damage_max']; ?></div><?php endif; ?>
                        </div>
                        <div style="margin-top: 10px;">
                            <small>Type: <?php echo $itemType['name']; ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Modal para equipar item -->
    <div class="modal" id="itemModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div id="itemModalContent"></div>
        </div>
    </div>
    
    <script>
        function showItemModal(itemId) {
            $.ajax({
                url: '../api/items.php?action=get_item&id=' + itemId,
                method: 'GET',
                success: function(data) {
                    try {
                        const item = JSON.parse(data);
                        let modalContent = `
                            <h3>${item.name}</h3>
                            <div class="item-rarity rarity-${item.rarity}">
                                ${item.rarity.toUpperCase()}
                            </div>
                            <p>${item.description || 'No description'}</p>
                            
                            <div class="item-stats">
                                ${item.atk_bonus > 0 ? `<div>ATK +${item.atk_bonus}</div>` : ''}
                                ${item.def_bonus > 0 ? `<div>DEF +${item.def_bonus}</div>` : ''}
                                ${item.crit_bonus > 0 ? `<div>CRIT +${item.crit_bonus}%</div>` : ''}
                                ${item.dodge_bonus > 0 ? `<div>DODGE +${item.dodge_bonus}%</div>` : ''}
                                ${item.weapon_damage_min > 0 ? `<div>Damage: ${item.weapon_damage_min}-${item.weapon_damage_max}</div>` : ''}
                                ${item.hp_bonus > 0 ? `<div>HP +${item.hp_bonus}</div>` : ''}
                                ${item.mp_bonus > 0 ? `<div>MP +${item.mp_bonus}</div>` : ''}
                            </div>
                        `;
                        
                        // Se for equipável, mostrar opções de slot
                        if (item.can_equip) {
                            modalContent += `
                                <hr>
                                <h4>Equip to:</h4>
                                <form action="?action=equip&id=${itemId}" method="POST">
                                    <select name="slot" required>
                                        <option value="">Select Slot</option>
                                        ${getSlotOptions(item.type_name)}
                                    </select>
                                    <button type="submit" class="btn">Equip</button>
                                </form>
                            `;
                        }
                        
                        $('#itemModalContent').html(modalContent);
                        $('#itemModal').fadeIn();
                    } catch (e) {
                        console.error('Error loading item:', e);
                    }
                }
            });
        }
        
        function getSlotOptions(itemType) {
            const slotMap = {
                'Sword': ['weapon'],
                'Armor': ['armor'],
                'Helmet': ['helmet'],
                'Gloves': ['gloves'],
                'Boots': ['boots'],
                'Ring': ['accessory1', 'accessory2'],
                'Necklace': ['accessory1', 'accessory2']
            };
            
            const slots = slotMap[itemType] || [];
            return slots.map(slot => `<option value="${slot}">${slot}</option>`).join('');
        }
        
        // Fechar modal
        $('.close-modal').click(function() {
            $('#itemModal').fadeOut();
        });
    </script>
</body>
</html>
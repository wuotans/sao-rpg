<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Require login
requireLogin();

// Get player info
$db = Database::getInstance();
$player = $db->fetch("
    SELECT c.*, u.vip_expire 
    FROM characters c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.user_id = ?
", [$_SESSION['user_id']]);

// Calculate total stats with equipment
$equipped_items = $db->fetchAll("
    SELECT it.*
    FROM inventory i
    JOIN items it ON i.item_id = it.id
    WHERE i.user_id = ? AND i.equipped = 1
", [$_SESSION['user_id']]);

$total_atk = $player['atk'];
$total_def = $player['def'];
$total_hp = $player['max_hp'];
$total_mp = $player['max_mp'];
$total_crit = $player['crit'];
$total_agi = $player['agi'];

foreach ($equipped_items as $item) {
    $total_atk += $item['atk'];
    $total_def += $item['def'];
    $total_hp += $item['hp_bonus'];
    $total_mp += $item['mp_bonus'];
    $total_crit += $item['crit_bonus'];
    $total_agi += $item['agi_bonus'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/sao-theme.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/inventory.js" defer></script>
    <style>
        .inventory-welcome {
            background: rgba(0, 176, 255, 0.1);
            border: 2px solid #00b0ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .inventory-stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #00b0ff;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #90caf9;
        }
        
        .inventory-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .summary-card.items {
            border-color: #69f0ae;
        }
        
        .summary-card.value {
            border-color: #ffd740;
        }
        
        .summary-card.slots {
            border-color: #42a5f5;
        }
        
        .summary-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .summary-items .summary-icon {
            color: #69f0ae;
        }
        
        .summary-value .summary-icon {
            color: #ffd740;
        }
        
        .summary-slots .summary-icon {
            color: #42a5f5;
        }
        
        .summary-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .inventory-quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .quick-action-btn {
            padding: 12px 25px;
            background: rgba(0, 176, 255, 0.2);
            border: 1px solid #00b0ff;
            border-radius: 5px;
            color: #bbdefb;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-action-btn:hover {
            background: rgba(0, 176, 255, 0.4);
            color: #fff;
            transform: translateY(-3px);
        }
        
        .equipment-comparison {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid #00b0ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .comparison-row {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 20px;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-change {
            font-weight: bold;
        }
        
        .stat-change.positive {
            color: #69f0ae;
        }
        
        .stat-change.negative {
            color: #ef5350;
        }
        
        .inventory-tutorial {
            background: rgba(255, 215, 64, 0.1);
            border: 2px solid #ffd740;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .tutorial-step {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .step-number {
            background: #ffd740;
            color: #000;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .item-drag-drop {
            background: rgba(0, 176, 255, 0.1);
            border: 2px dashed #00b0ff;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin-top: 20px;
        }
        
        .drag-drop-icon {
            font-size: 3rem;
            color: #00b0ff;
            margin-bottom: 15px;
        }
        
        .inventory-search {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-box {
            flex: 1;
            padding: 10px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #00b0ff;
            border-radius: 5px;
            color: #fff;
        }
        
        .clear-search {
            padding: 10px 20px;
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            border-radius: 5px;
            color: #ffcdd2;
            cursor: pointer;
        }
        
        .clear-search:hover {
            background: rgba(244, 67, 54, 0.4);
        }
        
        .inventory-help {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 176, 255, 0.9);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            z-index: 100;
            box-shadow: 0 0 10px rgba(0, 176, 255, 0.5);
        }
        
        .inventory-help:hover {
            background: rgba(41, 121, 255, 0.9);
            transform: scale(1.1);
        }
    </style>
</head>
<body class="sao-theme">
    <?php include '../includes/header.php'; ?>
    
    <main class="container">
        <div class="inventory-container">
            <!-- Inventory Header -->
            <div class="inventory-header">
                <h2><i class="fas fa-backpack"></i> Inventory</h2>
                <p>Manage your items and equipment</p>
            </div>
            
            <!-- Welcome Message -->
            <div class="inventory-welcome">
                <h2>Welcome to your Inventory, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <p>Here you can manage your items, equip gear, and use consumables.</p>
            </div>
            
            <!-- Stats Overview -->
            <div class="inventory-stats-overview">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $player['current_hp']; ?>/<?php echo $total_hp; ?></div>
                    <div class="stat-label">Health</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $player['current_mp']; ?>/<?php echo $total_mp; ?></div>
                    <div class="stat-label">Mana</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_atk; ?></div>
                    <div class="stat-label">Attack</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_def; ?></div>
                    <div class="stat-label">Defense</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_crit; ?>%</div>
                    <div class="stat-label">Critical</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_agi; ?></div>
                    <div class="stat-label">Agility</div>
                </div>
            </div>
            
            <!-- Inventory Summary -->
            <div class="inventory-summary">
                <div class="summary-card items">
                    <div class="summary-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="summary-amount" id="total-items">0</div>
                    <div class="summary-label">Total Items</div>
                </div>
                
                <div class="summary-card value">
                    <div class="summary-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="summary-amount"><?php echo number_format($player['gold']); ?></div>
                    <div class="summary-label">Inventory Value</div>
                </div>
                
                <div class="summary-card slots">
                    <div class="summary-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="summary-amount">30</div>
                    <div class="summary-label">Total Slots</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="inventory-quick-actions">
                <button class="quick-action-btn" onclick="inventorySystem.sortInventory('name')">
                    <i class="fas fa-sort-alpha-down"></i> Sort by Name
                </button>
                <button class="quick-action-btn" onclick="inventorySystem.sortInventory('rarity')">
                    <i class="fas fa-gem"></i> Sort by Rarity
                </button>
                <button class="quick-action-btn" onclick="inventorySystem.autoEquip()">
                    <i class="fas fa-robot"></i> Auto-Equip
                </button>
                <button class="quick-action-btn" onclick="inventorySystem.sellAllCommon()">
                    <i class="fas fa-coins"></i> Sell Common Items
                </button>
                <button class="quick-action-btn" onclick="window.location.href='shop.php'">
                    <i class="fas fa-store"></i> Visit Shop
                </button>
            </div>
            
            <!-- Equipment Comparison -->
            <div class="equipment-comparison">
                <h3><i class="fas fa-balance-scale"></i> Equipment Comparison</h3>
                <div class="comparison-stats" id="comparisonStats">
                    <!-- Stats comparison will be loaded via JavaScript -->
                </div>
            </div>
            
            <!-- Inventory Content -->
            <div class="inventory-content">
                <!-- Sidebar -->
                <aside class="inventory-sidebar">
                    <!-- Equipment Slots -->
                    <div class="equipment-slots" id="equipmentSlots">
                        <!-- Equipment slots loaded via JavaScript -->
                    </div>
                    
                    <!-- Character Stats -->
                    <div class="character-stats">
                        <h3><i class="fas fa-chart-line"></i> Character Stats</h3>
                        <div class="stat-category">
                            <div class="category-title">Combat</div>
                            <div class="stat-row">
                                <span class="stat-name">Attack:</span>
                                <span class="stat-value"><?php echo $total_atk; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-name">Defense:</span>
                                <span class="stat-value"><?php echo $total_def; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-name">Critical:</span>
                                <span class="stat-value"><?php echo $total_crit; ?>%</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-name">Agility:</span>
                                <span class="stat-value"><?php echo $total_agi; ?></span>
                            </div>
                        </div>
                        
                        <div class="stat-category">
                            <div class="category-title">Vitality</div>
                            <div class="stat-row">
                                <span class="stat-name">Health:</span>
                                <span class="stat-value"><?php echo $player['current_hp']; ?>/<?php echo $total_hp; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-name">Mana:</span>
                                <span class="stat-value"><?php echo $player['current_mp']; ?>/<?php echo $total_mp; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-name">Energy:</span>
                                <span class="stat-value"><?php echo $player['energy']; ?>/<?php echo MAX_ENERGY; ?></span>
                            </div>
                        </div>
                        
                        <div class="stat-category">
                            <div class="category-title">Progress</div>
                            <div class="stat-row">
                                <span class="stat-name">Level:</span>
                                <span class="stat-value"><?php echo $player['level']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-name">EXP:</span>
                                <span class="stat-value"><?php echo $player['exp']; ?>/<?php echo getExpForLevel($player['level']); ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-name">Floor:</span>
                                <span class="stat-value"><?php echo $player['current_floor']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-name">Class:</span>
                                <span class="stat-value"><?php echo $player['class']; ?></span>
                            </div>
                        </div>
                    </div>
                </aside>
                
                <!-- Main Inventory -->
                <main class="inventory-main">
                    <!-- Search and Filters -->
                    <div class="inventory-search">
                        <input type="text" class="search-box" id="inventorySearch" 
                               placeholder="Search items...">
                        <button class="clear-search" onclick="inventorySystem.clearSearch()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                    
                    <!-- Inventory Tabs -->
                    <div class="inventory-tabs" id="inventoryTabs">
                        <!-- Tabs loaded via JavaScript -->
                    </div>
                    
                    <!-- Inventory Grid -->
                    <div class="inventory-grid" id="inventoryGrid">
                        <!-- Items loaded via JavaScript -->
                    </div>
                    
                    <!-- Inventory Controls -->
                    <div class="inventory-controls">
                        <button class="control-btn sort-btn" onclick="inventorySystem.toggleSort()">
                            <i class="fas fa-sort-amount-down"></i> Sort
                        </button>
                        <button class="control-btn use-btn" onclick="inventorySystem.useSelected()">
                            <i class="fas fa-vial"></i> Use
                        </button>
                        <button class="control-btn sell-btn" onclick="inventorySystem.sellSelected()">
                            <i class="fas fa-coins"></i> Sell
                        </button>
                    </div>
                </main>
            </div>
            
            <!-- Tutorial -->
            <div class="inventory-tutorial">
                <h3><i class="fas fa-graduation-cap"></i> Inventory Tips</h3>
                <div class="tutorial-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <strong>Click on items</strong> to view details and options
                    </div>
                </div>
                <div class="tutorial-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <strong>Drag items</strong> to equipment slots to equip them
                    </div>
                </div>
                <div class="tutorial-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <strong>Right-click items</strong> for quick actions
                    </div>
                </div>
                <div class="tutorial-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        Use <strong>Auto-Equip</strong> to quickly equip your best gear
                    </div>
                </div>
            </div>
            
            <!-- Drag & Drop Area -->
            <div class="item-drag-drop" id="dragDropArea">
                <div class="drag-drop-icon">
                    <i class="fas fa-arrows-alt"></i>
                </div>
                <h3>Drag & Drop</h3>
                <p>Drag items here to sell multiple items at once</p>
                <div class="drag-drop-items" id="dragDropItems"></div>
                <button class="btn-sell-all" onclick="inventorySystem.sellDraggedItems()">
                    <i class="fas fa-coins"></i> Sell All
                </button>
            </div>
        </div>
    </main>
    
    <!-- Item Details Modal -->
    <div class="modal" id="itemDetailsModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div id="itemDetailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Sell Modal -->
    <div class="modal" id="sellModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2><i class="fas fa-coins"></i> Sell Items</h2>
            <div id="sellModalContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Help Button -->
    <div class="inventory-help" onclick="showInventoryHelp()">
        <i class="fas fa-question"></i>
    </div>
    
    <script>
        // Initialize inventory system
        $(document).ready(function() {
            inventorySystem.init();
            
            // Load initial data
            updateInventorySummary();
            
            // Set up drag and drop
            setupDragAndDrop();
        });
        
        // Update inventory summary
        function updateInventorySummary() {
            $.ajax({
                url: '../api/inventory.php?action=get_summary',
                method: 'GET',
                success: function(data) {
                    const summary = JSON.parse(data);
                    $('#total-items').text(summary.total_items);
                }
            });
        }
        
        // Set up drag and drop
        function setupDragAndDrop() {
            const dragDropArea = document.getElementById('dragDropArea');
            
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dragDropArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            
            // Highlight drop area
            ['dragenter', 'dragover'].forEach(eventName => {
                dragDropArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dragDropArea.addEventListener(eventName, unhighlight, false);
            });
            
            // Handle drop
            dragDropArea.addEventListener('drop', handleDrop, false);
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            function highlight() {
                dragDropArea.style.backgroundColor = 'rgba(0, 176, 255, 0.2)';
            }
            
            function unhighlight() {
                dragDropArea.style.backgroundColor = '';
            }
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const itemId = dt.getData('text/plain');
                
                if (itemId) {
                    inventorySystem.addToSellQueue(itemId);
                }
            }
        }
        
        // Show inventory help
        function showInventoryHelp() {
            const helpContent = `
                <div class="help-modal">
                    <h2><i class="fas fa-question-circle"></i> Inventory Help</h2>
                    
                    <h3>Basic Controls</h3>
                    <ul>
                        <li><strong>Left-click:</strong> Select item</li>
                        <li><strong>Right-click:</strong> Quick actions menu</li>
                        <li><strong>Drag & Drop:</strong> Move items between slots</li>
                        <li><strong>Double-click:</strong> Use/Equip item</li>
                    </ul>
                    
                    <h3>Item Rarities</h3>
                    <ul>
                        <li><span class="rarity-common">Common:</span> Basic items</li>
                        <li><span class="rarity-uncommon">Uncommon:</span> Better stats</li>
                        <li><span class="rarity-rare">Rare:</span> Good equipment</li>
                        <li><span class="rarity-epic">Epic:</span> Powerful items</li>
                        <li><span class="rarity-legendary">Legendary:</span> Best in game</li>
                    </ul>
                    
                    <h3>Tips</h3>
                    <ul>
                        <li>Always equip your highest rarity items</li>
                        <li>Keep consumables for difficult battles</li>
                        <li>Sell common items you don't need</li>
                        <li>Use Auto-Equip for quick optimization</li>
                        <li>Check item requirements before equipping</li>
                    </ul>
                    
                    <h3>Shortcuts</h3>
                    <ul>
                        <li><strong>Ctrl+F:</strong> Search items</li>
                        <li><strong>Ctrl+S:</strong> Sort inventory</li>
                        <li><strong>Ctrl+E:</strong> Auto-equip</li>
                        <li><strong>Ctrl+X:</strong> Sell selected</li>
                    </ul>
                </div>
            `;
            
            $('#itemDetailsContent').html(helpContent);
            $('#itemDetailsModal').fadeIn();
        }
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl+F for search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                $('#inventorySearch').focus();
            }
            
            // Ctrl+S for sort
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                inventorySystem.toggleSort();
            }
            
            // Ctrl+E for auto-equip
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                inventorySystem.autoEquip();
            }
            
            // Ctrl+X for sell
            if (e.ctrlKey && e.key === 'x') {
                e.preventDefault();
                inventorySystem.sellSelected();
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                $('.modal').fadeOut();
            }
        });
        
        // Global functions
        window.showInventoryHelp = showInventoryHelp;
        window.inventorySystem = inventorySystem;
    </script>
</body>
</html>
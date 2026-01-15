<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/sao-theme.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/main.js" defer></script>
</head>
<body class="sao-theme">
    <!-- Header SAO -->
    <header class="sao-header">
        <div class="container">
            <div class="header-top">
                <div class="logo">
                    <h1><i class="fas fa-swords"></i> SWORD ART ONLINE RPG</h1>
                    <p class="server-status">Aincrad - Floor 1 | <span class="online">● Online</span></p>
                </div>
                <div class="header-actions">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <div class="user-menu">
                            <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <a href="dashboard.php" class="btn-small"><i class="fas fa-user"></i> Dashboard</a>
                            <a href="logout.php" class="btn-small logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    <?php else: ?>
                        <div class="auth-buttons">
                            <a href="login.php" class="btn-small"><i class="fas fa-sign-in-alt"></i> Login</a>
                            <a href="register.php" class="btn-small register"><i class="fas fa-user-plus"></i> Register</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="pages/battle.php"><i class="fas fa-crosshairs"></i> Dungeon</a></li>
                    <li><a href="pages/shop.php"><i class="fas fa-store"></i> Shop</a></li>
                    <li><a href="pages/inventory.php"><i class="fas fa-backpack"></i> Inventory</a></li>
                    <li><a href="pages/ranking.php"><i class="fas fa-trophy"></i> Ranking</a></li>
                    <li><a href="#"><i class="fas fa-users"></i> Guild</a></li>
                    <li><a href="#"><i class="fas fa-calendar-alt"></i> Events</a></li>
                    <li><a href="#"><i class="fas fa-question-circle"></i> Help</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="container">
        <div class="game-container">
            <!-- Sidebar Esquerda -->
            <aside class="sidebar-left">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- Player Stats -->
                    <div class="player-card">
                        <div class="player-header">
                            <div class="avatar-container">
                                <img src="images/avatars/<?php echo $_SESSION['avatar'] ?? 'default.png'; ?>" 
                                     alt="Avatar" class="player-avatar">
                                <div class="level-badge">Lv. <?php echo $_SESSION['level'] ?? 1; ?></div>
                            </div>
                            <h3 class="player-name"><?php echo htmlspecialchars($_SESSION['username']); ?></h3>
                            <p class="player-class"><?php echo $_SESSION['class'] ?? 'Swordsman'; ?></p>
                        </div>
                        
                        <div class="player-stats">
                            <div class="stat-bar">
                                <div class="stat-label">
                                    <i class="fas fa-heart"></i> HP
                                    <span id="current-hp"><?php echo $_SESSION['current_hp'] ?? 100; ?></span>/<span id="max-hp"><?php echo $_SESSION['max_hp'] ?? 100; ?></span>
                                </div>
                                <div class="bar-container">
                                    <div class="bar-fill hp-bar" 
                                         style="width: <?php echo (($_SESSION['current_hp'] ?? 100) / ($_SESSION['max_hp'] ?? 100)) * 100; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="stat-bar">
                                <div class="stat-label">
                                    <i class="fas fa-bolt"></i> MP
                                    <span id="current-mp"><?php echo $_SESSION['current_mp'] ?? 50; ?></span>/<span id="max-mp"><?php echo $_SESSION['max_mp'] ?? 50; ?></span>
                                </div>
                                <div class="bar-container">
                                    <div class="bar-fill mp-bar" 
                                         style="width: <?php echo (($_SESSION['current_mp'] ?? 50) / ($_SESSION['max_mp'] ?? 50)) * 100; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="stat-bar">
                                <div class="stat-label">
                                    <i class="fas fa-star"></i> EXP
                                    <span id="current-exp"><?php echo $_SESSION['exp'] ?? 0; ?></span>/<span id="max-exp"><?php echo $_SESSION['max_exp'] ?? 100; ?></span>
                                </div>
                                <div class="bar-container">
                                    <div class="bar-fill exp-bar" 
                                         style="width: <?php echo (($_SESSION['exp'] ?? 0) / ($_SESSION['max_exp'] ?? 100)) * 100; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="stat-bar">
                                <div class="stat-label">
                                    <i class="fas fa-battery-full"></i> Energy
                                    <span id="current-energy"><?php echo $_SESSION['energy'] ?? 60; ?></span>/<span id="max-energy"><?php echo MAX_ENERGY; ?></span>
                                </div>
                                <div class="bar-container">
                                    <div class="bar-fill energy-bar" 
                                         style="width: <?php echo (($_SESSION['energy'] ?? 60) / MAX_ENERGY) * 100; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="quick-stats">
                            <div class="stat-item">
                                <span class="stat-name">ATK</span>
                                <span class="stat-value" id="stat-atk"><?php echo $_SESSION['atk'] ?? 10; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-name">DEF</span>
                                <span class="stat-value" id="stat-def"><?php echo $_SESSION['def'] ?? 5; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-name">AGI</span>
                                <span class="stat-value" id="stat-agi"><?php echo $_SESSION['agi'] ?? 8; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-name">CRIT</span>
                                <span class="stat-value" id="stat-crit"><?php echo $_SESSION['crit'] ?? 5; ?>%</span>
                            </div>
                        </div>
                        
                        <div class="player-currency">
                            <div class="currency-item">
                                <i class="fas fa-coins"></i>
                                <span id="gold"><?php echo number_format($_SESSION['gold'] ?? 0); ?></span> Gold
                            </div>
                            <div class="currency-item">
                                <i class="fas fa-gem"></i>
                                <span id="credits"><?php echo $_SESSION['credits'] ?? 0; ?></span> Credits
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                        <button class="btn-action heal" onclick="quickHeal()">
                            <i class="fas fa-heart"></i> Heal (10 MP)
                        </button>
                        <button class="btn-action buff" onclick="useBuff()">
                            <i class="fas fa-arrow-up"></i> ATK Buff
                        </button>
                        <button class="btn-action rest" onclick="rest()">
                            <i class="fas fa-bed"></i> Rest
                        </button>
                        <button class="btn-action vip" onclick="openVipShop()">
                            <i class="fas fa-crown"></i> VIP Shop
                        </button>
                    </div>
                    
                    <!-- Online Friends -->
                    <div class="online-friends">
                        <h3><i class="fas fa-user-friends"></i> Online Players</h3>
                        <div class="friends-list" id="friends-list">
                            <!-- Loaded via AJAX -->
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
            
            <!-- Conteúdo Central -->
            <section class="main-content">
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <!-- Welcome Screen -->
                    <div class="welcome-screen">
                        <div class="welcome-hero">
                            <h2>Welcome to Sword Art Online RPG</h2>
                            <p class="tagline">Enter the world of Aincrad and become the strongest player!</p>
                            
                            <div class="features-grid">
                                <div class="feature">
                                    <i class="fas fa-swords fa-3x"></i>
                                    <h3>Epic Battles</h3>
                                    <p>Fight monsters, complete quests, and climb the floors of Aincrad</p>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-treasure-chest fa-3x"></i>
                                    <h3>Rare Loot</h3>
                                    <p>Collect legendary weapons and armor with unique abilities</p>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-users fa-3x"></i>
                                    <h3>Join Guilds</h3>
                                    <p>Team up with other players to defeat powerful bosses</p>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-crown fa-3x"></i>
                                    <h3>VIP Benefits</h3>
                                    <p>Get exclusive items and bonuses with VIP status</p>
                                </div>
                            </div>
                            
                            <div class="auth-options">
                                <a href="login.php" class="btn-login-large">
                                    <i class="fas fa-sign-in-alt"></i> Login to Aincrad
                                </a>
                                <a href="register.php" class="btn-register-large">
                                    <i class="fas fa-user-plus"></i> Create Free Account
                                </a>
                            </div>
                            
                            <div class="stats-overview">
                                <div class="stat-box">
                                    <span class="stat-number">1,247</span>
                                    <span class="stat-label">Players Online</span>
                                </div>
                                <div class="stat-box">
                                    <span class="stat-number">75</span>
                                    <span class="stat-label">Floors Cleared</span>
                                </div>
                                <div class="stat-box">
                                    <span class="stat-number">892</span>
                                    <span class="stat-label">Bosses Defeated</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Dashboard -->
                    <div class="game-dashboard">
                        <div class="dashboard-header">
                            <h2><i class="fas fa-gamepad"></i> Game Dashboard</h2>
                            <div class="dashboard-actions">
                                <button class="btn-auto-battle" onclick="startAutoBattle()">
                                    <i class="fas fa-robot"></i> Auto Battle
                                </button>
                                <button class="btn-daily-reward" onclick="claimDailyReward()">
                                    <i class="fas fa-gift"></i> Daily Reward
                                </button>
                            </div>
                        </div>
                        
                        <div class="dashboard-grid">
                            <!-- Battle Arena -->
                            <div class="dashboard-card battle-card">
                                <h3><i class="fas fa-crosshairs"></i> Battle Arena</h3>
                                <p>Fight monsters and earn experience</p>
                                <div class="floor-selector">
                                    <span>Current Floor: <strong id="current-floor"><?php echo $_SESSION['current_floor'] ?? 1; ?></strong></span>
                                    <div class="floor-buttons">
                                        <?php for($i = 1; $i <= min($_SESSION['current_floor'] ?? 1, 10); $i++): ?>
                                            <button class="floor-btn <?php echo $i == ($_SESSION['current_floor'] ?? 1) ? 'active' : ''; ?>"
                                                    onclick="changeFloor(<?php echo $i; ?>)">
                                                F<?php echo $i; ?>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <a href="pages/battle.php" class="btn-battle">Enter Dungeon</a>
                            </div>
                            
                            <!-- Shop -->
                            <div class="dashboard-card shop-card">
                                <h3><i class="fas fa-store"></i> Town Shop</h3>
                                <p>Buy weapons, armor, and consumables</p>
                                <div class="shop-featured">
                                    <div class="featured-item">
                                        <img src="images/items/weapons/elucidator.png" alt="Elucidator">
                                        <span class="item-name">Elucidator</span>
                                        <span class="item-price">5,000 Credits</span>
                                    </div>
                                </div>
                                <a href="pages/shop.php" class="btn-shop">Visit Shop</a>
                            </div>
                            
                            <!-- Inventory -->
                            <div class="dashboard-card inventory-card">
                                <h3><i class="fas fa-backpack"></i> Inventory</h3>
                                <p>Manage your items and equipment</p>
                                <div class="inventory-preview" id="inventory-preview">
                                    <!-- Loaded via AJAX -->
                                </div>
                                <a href="pages/inventory.php" class="btn-inventory">Open Inventory</a>
                            </div>
                            
                            <!-- Quests -->
                            <div class="dashboard-card quests-card">
                                <h3><i class="fas fa-scroll"></i> Active Quests</h3>
                                <div class="quest-list" id="quest-list">
                                    <!-- Loaded via AJAX -->
                                </div>
                                <button class="btn-quests" onclick="viewAllQuests()">View All Quests</button>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="recent-activity">
                            <h3><i class="fas fa-history"></i> Recent Activity</h3>
                            <div class="activity-log" id="activity-log">
                                <!-- Loaded via AJAX -->
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Global Chat -->
                <div class="global-chat">
                    <div class="chat-header">
                        <h3><i class="fas fa-comments"></i> Global Chat</h3>
                        <div class="chat-controls">
                            <button onclick="toggleChat()"><i class="fas fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="chat-messages" id="chat-messages">
                        <!-- Messages loaded via AJAX -->
                    </div>
                    <div class="chat-input">
                        <input type="text" id="chat-input" placeholder="Type your message..." 
                               <?php echo !isset($_SESSION['user_id']) ? 'disabled' : ''; ?>>
                        <button onclick="sendMessage()" <?php echo !isset($_SESSION['user_id']) ? 'disabled' : ''; ?>>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </section>
            
            <!-- Sidebar Direita -->
            <aside class="sidebar-right">
                <!-- Notifications -->
                <div class="notifications">
                    <h3><i class="fas fa-bell"></i> Notifications</h3>
                    <div class="notification-list" id="notification-list">
                        <div class="notification">
                            <i class="fas fa-gift notification-icon"></i>
                            <div class="notification-content">
                                <strong>Daily Reward Available!</strong>
                                <span>Claim your free reward</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Events -->
                <div class="active-events">
                    <h3><i class="fas fa-calendar-star"></i> Active Events</h3>
                    <div class="event-item boss-event">
                        <div class="event-header">
                            <i class="fas fa-dragon"></i>
                            <h4>BOSS RAID: Gleam Eyes</h4>
                        </div>
                        <p>Defeat the floor boss with other players</p>
                        <div class="event-timer" id="boss-timer">Starts in: 01:30:15</div>
                        <button class="btn-join-raid">Join Raid Party</button>
                    </div>
                    
                    <div class="event-item pvp-event">
                        <div class="event-header">
                            <i class="fas fa-trophy"></i>
                            <h4>PVP Tournament</h4>
                        </div>
                        <p>Compete for rare rewards</p>
                        <div class="event-timer">Ends in: 2 days</div>
                        <button class="btn-join-pvp">Enter Tournament</button>
                    </div>
                </div>
                
                <!-- VIP Benefits -->
                <div class="vip-benefits">
                    <h3><i class="fas fa-crown"></i> VIP Benefits</h3>
                    <ul class="vip-list">
                        <li><i class="fas fa-check"></i> +50% EXP Gain</li>
                        <li><i class="fas fa-check"></i> +30% Drop Rate</li>
                        <li><i class="fas fa-check"></i> Exclusive Skills</li>
                        <li><i class="fas fa-check"></i> VIP Only Items</li>
                        <li><i class="fas fa-check"></i> No Ads</li>
                        <li><i class="fas fa-check"></i> Priority Support</li>
                    </ul>
                    <button class="btn-become-vip" onclick="openVipPurchase()">
                        <i class="fas fa-gem"></i> Become VIP
                    </button>
                </div>
                
                <!-- Server Stats -->
                <div class="server-stats">
                    <h3><i class="fas fa-server"></i> Server Stats</h3>
                    <div class="stats-grid">
                        <div class="server-stat">
                            <span class="stat-label">Online Players</span>
                            <span class="stat-value" id="online-count">1,247</span>
                        </div>
                        <div class="server-stat">
                            <span class="stat-label">Total Players</span>
                            <span class="stat-value">58,921</span>
                        </div>
                        <div class="server-stat">
                            <span class="stat-label">Battles Today</span>
                            <span class="stat-value">24,589</span>
                        </div>
                        <div class="server-stat">
                            <span class="stat-label">Server Uptime</span>
                            <span class="stat-value">99.8%</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <!-- Footer -->
    <footer class="sao-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <h3>SWORD ART ONLINE RPG</h3>
                    <p>The ultimate browser-based SAO experience</p>
                </div>
                
                <div class="footer-links">
                    <div class="link-column">
                        <h4>Game</h4>
                        <a href="#">About</a>
                        <a href="#">Features</a>
                        <a href="#">Updates</a>
                        <a href="#">Roadmap</a>
                    </div>
                    <div class="link-column">
                        <h4>Community</h4>
                        <a href="#">Forums</a>
                        <a href="#">Discord</a>
                        <a href="#">Facebook</a>
                        <a href="#">Twitter</a>
                    </div>
                    <div class="link-column">
                        <h4>Support</h4>
                        <a href="#">Help Center</a>
                        <a href="#">Contact Us</a>
                        <a href="#">FAQ</a>
                        <a href="#">Report Bug</a>
                    </div>
                    <div class="link-column">
                        <h4>Legal</h4>
                        <a href="#">Terms of Service</a>
                        <a href="#">Privacy Policy</a>
                        <a href="#">Cookie Policy</a>
                        <a href="#">Refund Policy</a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 Sword Art Online RPG. All rights reserved.</p>
                <p>This is a fan-made game. Sword Art Online is owned by Reki Kawahara and ASCII Media Works.</p>
            </div>
        </div>
    </footer>

    <!-- VIP Modal -->
    <div id="vip-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2><i class="fas fa-crown"></i> Become a VIP Player</h2>
            <div class="vip-plans" id="vip-plans">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Global Variables
        let currentFloor = <?php echo $_SESSION['current_floor'] ?? 1; ?>;
        let playerHP = <?php echo $_SESSION['current_hp'] ?? 100; ?>;
        let playerMaxHP = <?php echo $_SESSION['max_hp'] ?? 100; ?>;
        let playerMP = <?php echo $_SESSION['current_mp'] ?? 50; ?>;
        let playerMaxMP = <?php echo $_SESSION['max_mp'] ?? 50; ?>;
        let playerEnergy = <?php echo $_SESSION['energy'] ?? 60; ?>;
        let isAutoBattle = false;
        
        // Initialize Game
        $(document).ready(function() {
            <?php if(isset($_SESSION['user_id'])): ?>
                loadGameData();
                startChatPolling();
                updatePlayerStats();
                loadOnlinePlayers();
                loadInventoryPreview();
                loadRecentActivity();
            <?php endif; ?>
            
            // Initialize tooltips
            $('[data-tooltip]').hover(function() {
                showTooltip($(this).attr('data-tooltip'), $(this));
            });
        });
        
        // Load Game Data
        function loadGameData() {
            $.ajax({
                url: 'api/player.php?action=get_stats',
                method: 'GET',
                success: function(data) {
                    const stats = JSON.parse(data);
                    updateUIStats(stats);
                }
            });
        }
        
        // Update UI Stats
        function updateUIStats(stats) {
            $('#current-hp').text(stats.current_hp);
            $('#max-hp').text(stats.max_hp);
            $('#current-mp').text(stats.current_mp);
            $('#max-mp').text(stats.max_mp);
            $('#current-exp').text(stats.exp);
            $('#max-exp').text(stats.max_exp);
            $('#current-energy').text(stats.energy);
            
            $('.hp-bar').css('width', (stats.current_hp / stats.max_hp) * 100 + '%');
            $('.mp-bar').css('width', (stats.current_mp / stats.max_mp) * 100 + '%');
            $('.exp-bar').css('width', (stats.exp / stats.max_exp) * 100 + '%');
            $('.energy-bar').css('width', (stats.energy / <?php echo MAX_ENERGY; ?>) * 100 + '%');
            
            $('#stat-atk').text(stats.atk);
            $('#stat-def').text(stats.def);
            $('#stat-agi').text(stats.agi);
            $('#stat-crit').text(stats.crit + '%');
            $('#gold').text(stats.gold.toLocaleString());
            $('#credits').text(stats.credits);
            $('#current-floor').text(stats.current_floor);
        }
        
        // Quick Actions
        function quickHeal() {
            if(playerMP < 10) {
                showNotification('Not enough MP!', 'error');
                return;
            }
            
            $.ajax({
                url: 'api/player.php?action=heal',
                method: 'POST',
                success: function(data) {
                    const result = JSON.parse(data);
                    if(result.success) {
                        playerHP = result.new_hp;
                        playerMP = result.new_mp;
                        updateUIStats(result);
                        showNotification('Healed for ' + result.heal_amount + ' HP!', 'success');
                    }
                }
            });
        }
        
        function useBuff() {
            // Implement buff logic
            showNotification('Attack buff activated!', 'success');
        }
        
        function rest() {
            $.ajax({
                url: 'api/player.php?action=rest',
                method: 'POST',
                success: function(data) {
                    const result = JSON.parse(data);
                    if(result.success) {
                        updateUIStats(result);
                        showNotification('Fully rested! HP and MP restored.', 'success');
                    }
                }
            });
        }
        
        // VIP Functions
        function openVipShop() {
            $('#vip-modal').fadeIn();
            loadVipPlans();
        }
        
        function loadVipPlans() {
            $.ajax({
                url: 'api/vip.php?action=get_plans',
                method: 'GET',
                success: function(data) {
                    $('#vip-plans').html(data);
                }
            });
        }
        
        // Chat Functions
        function sendMessage() {
            const message = $('#chat-input').val().trim();
            if(message === '') return;
            
            $.ajax({
                url: 'api/chat.php?action=send',
                method: 'POST',
                data: { message: message },
                success: function() {
                    $('#chat-input').val('');
                }
            });
        }
        
        function startChatPolling() {
            setInterval(function() {
                loadChatMessages();
            }, 2000);
        }
        
        function loadChatMessages() {
            $.ajax({
                url: 'api/chat.php?action=get_messages',
                method: 'GET',
                success: function(data) {
                    $('#chat-messages').html(data);
                    scrollChatToBottom();
                }
            });
        }
        
        // Utility Functions
        function showNotification(message, type = 'info') {
            const notification = $('<div class="notification-alert ' + type + '">' + message + '</div>');
            $('body').append(notification);
            
            notification.fadeIn().delay(3000).fadeOut(function() {
                $(this).remove();
            });
        }
        
        function showTooltip(text, element) {
            // Implement tooltip display
        }
    </script>
</body>
</html>
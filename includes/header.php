<?php
// Este arquivo é incluído nas páginas que precisam do cabeçalho
?>
<header class="sao-header">
    <div class="container">
        <div class="header-top">
            <div class="logo">
                <h1><i class="fas fa-swords"></i> SWORD ART ONLINE RPG</h1>
                <p class="server-status">Aincrad - Floor <?php echo $_SESSION['current_floor'] ?? 1; ?> | 
                   <span class="online">● Online</span> | 
                   <span class="player-count">Players: <span id="online-count">1,247</span></span>
                </p>
            </div>
            <div class="header-actions">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="user-menu">
                        <span class="username">
                            <img src="images/avatars/<?php echo $_SESSION['avatar'] ?? 'default.png'; ?>" 
                                 class="header-avatar">
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                            <?php if(isVipActive($_SESSION['vip_expire'] ?? null)): ?>
                                <span class="vip-badge-small">VIP</span>
                            <?php endif; ?>
                        </span>
                        <a href="dashboard.php" class="btn-small"><i class="fas fa-user"></i> Profile</a>
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
                <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Home</a></li>
                <li><a href="pages/battle.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'battle.php' ? 'active' : ''; ?>">
                    <i class="fas fa-crosshairs"></i> Dungeon</a></li>
                <li><a href="pages/shop.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop.php' ? 'active' : ''; ?>">
                    <i class="fas fa-store"></i> Shop</a></li>
                <li><a href="pages/inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-backpack"></i> Inventory</a></li>
                <li><a href="pages/ranking.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'ranking.php' ? 'active' : ''; ?>">
                    <i class="fas fa-trophy"></i> Ranking</a></li>
                <li><a href="#" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guild.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Guild</a></li>
                <li><a href="#" class="<?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Events</a></li>
                <li><a href="#" class="<?php echo basename($_SERVER['PHP_SELF']) == 'help.php' ? 'active' : ''; ?>">
                    <i class="fas fa-question-circle"></i> Help</a></li>
            </ul>
        </nav>
    </div>
</header>

<style>
    .header-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 2px solid #00b0ff;
        vertical-align: middle;
        margin-right: 8px;
    }
    
    .vip-badge-small {
        background: linear-gradient(135deg, #ff6f00, #ffa000);
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 3px;
        margin-left: 5px;
    }
</style>
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

$is_vip = isVipActive($player['vip_expire']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Town Shop | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/sao-theme.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/shop.js" defer></script>
    <style>
        .shop-welcome {
            background: rgba(255, 215, 64, 0.1);
            border: 2px solid #ffd740;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .shop-welcome h2 {
            color: #ffd740;
            margin-bottom: 10px;
        }
        
        .currency-display-large {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .currency-card {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .currency-card.gold {
            border-color: #ffd740;
        }
        
        .currency-card.credits {
            border-color: #2979ff;
        }
        
        .currency-icon-large {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .currency-amount-large {
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .add-currency-section {
            background: rgba(0, 176, 255, 0.1);
            border: 2px solid #00b0ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .add-currency-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .currency-option {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #00b0ff;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .currency-option:hover {
            background: rgba(0, 176, 255, 0.2);
            transform: translateY(-3px);
        }
        
        .currency-option .amount {
            font-size: 1.3rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .currency-option .price {
            color: #69f0ae;
            font-weight: bold;
        }
        
        .shop-featured {
            margin-bottom: 40px;
        }
        
        .featured-slider {
            display: flex;
            overflow-x: auto;
            gap: 20px;
            padding: 10px 0;
            scrollbar-width: thin;
            scrollbar-color: #00b0ff rgba(0, 0, 0, 0.3);
        }
        
        .featured-slider::-webkit-scrollbar {
            height: 8px;
        }
        
        .featured-slider::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 4px;
        }
        
        .featured-slider::-webkit-scrollbar-thumb {
            background: #00b0ff;
            border-radius: 4px;
        }
        
        .featured-item-large {
            min-width: 300px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid #ffd740;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .featured-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .item-image-large {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border: 3px solid #ffd740;
            border-radius: 10px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .item-discount {
            position: absolute;
            top: -10px;
            right: -10px;
            background: linear-gradient(135deg, #00c853, #69f0ae);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .shop-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .category-card {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid #00b0ff;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .category-card:hover {
            background: rgba(0, 176, 255, 0.2);
            transform: translateY(-5px);
        }
        
        .category-icon {
            font-size: 2rem;
            color: #00b0ff;
            margin-bottom: 10px;
        }
        
        .shop-pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-btn {
            padding: 8px 15px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #00b0ff;
            border-radius: 5px;
            color: #bbdefb;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .page-btn:hover {
            background: rgba(0, 176, 255, 0.3);
            color: #fff;
        }
        
        .page-btn.active {
            background: rgba(0, 176, 255, 0.5);
            color: #fff;
            border-color: #2979ff;
        }
        
        .shop-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-select {
            padding: 8px 15px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #00b0ff;
            border-radius: 5px;
            color: #bbdefb;
            min-width: 150px;
        }
        
        .vip-section {
            background: linear-gradient(135deg, rgba(255, 111, 0, 0.1), rgba(255, 160, 0, 0.1));
            border: 2px solid #ffa000;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .vip-section h3 {
            color: #ffa000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .vip-notice {
            background: rgba(255, 215, 64, 0.1);
            border: 1px solid #ffd740;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            color: #ffd740;
        }
    </style>
</head>
<body class="sao-theme">
    <?php include '../includes/header.php'; ?>
    
    <main class="container">
        <div class="shop-container">
            <!-- Shop Header -->
            <div class="shop-header">
                <h2><i class="fas fa-store"></i> Town Shop</h2>
                <p>Purchase weapons, armor, consumables, and more!</p>
            </div>
            
            <!-- Welcome Message -->
            <div class="shop-welcome">
                <h2>Welcome to the Shop, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <p>Browse our selection of items to enhance your adventure in Aincrad.</p>
                <?php if ($is_vip): ?>
                    <div class="vip-notice">
                        <i class="fas fa-crown"></i> VIP Discount Active: You receive 10% off all purchases!
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Currency Display -->
            <div class="currency-display-large">
                <div class="currency-card gold">
                    <div class="currency-icon-large">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="currency-amount-large" id="gold-amount">
                        <?php echo number_format($player['gold']); ?>
                    </div>
                    <div class="currency-label">Gold</div>
                </div>
                
                <div class="currency-card credits">
                    <div class="currency-icon-large">
                        <i class="fas fa-gem"></i>
                    </div>
                    <div class="currency-amount-large" id="credits-amount">
                        <?php echo number_format($player['credits']); ?>
                    </div>
                    <div class="currency-label">Credits</div>
                </div>
            </div>
            
            <!-- Add Currency Section -->
            <div class="add-currency-section">
                <h3><i class="fas fa-plus-circle"></i> Add Currency</h3>
                <p>Purchase credits to buy exclusive items!</p>
                
                <div class="add-currency-options">
                    <div class="currency-option" data-amount="100" data-price="0.99">
                        <div class="amount">100 Credits</div>
                        <div class="price">$0.99</div>
                    </div>
                    <div class="currency-option" data-amount="500" data-price="4.99">
                        <div class="amount">500 Credits</div>
                        <div class="price">$4.99</div>
                    </div>
                    <div class="currency-option" data-amount="1200" data-price="9.99">
                        <div class="amount">1,200 Credits</div>
                        <div class="price">$9.99</div>
                        <div class="bonus">+20% Bonus!</div>
                    </div>
                    <div class="currency-option" data-amount="2500" data-price="19.99">
                        <div class="amount">2,500 Credits</div>
                        <div class="price">$19.99</div>
                        <div class="bonus">+25% Bonus!</div>
                    </div>
                </div>
            </div>
            
            <!-- VIP Section -->
            <?php if (!$is_vip): ?>
                <div class="vip-section">
                    <h3><i class="fas fa-crown"></i> VIP Exclusive Items</h3>
                    <p>Become a VIP to access exclusive items and receive discounts!</p>
                    <button class="btn-become-vip" onclick="openVipShop()">
                        <i class="fas fa-gem"></i> Become VIP
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Shop Categories -->
            <div class="shop-categories" id="shopCategories">
                <!-- Categories loaded via JavaScript -->
            </div>
            
            <!-- Shop Tabs -->
            <div class="shop-tabs" id="shopTabs">
                <!-- Tabs loaded via JavaScript -->
            </div>
            
            <!-- Shop Filters -->
            <div class="shop-filters">
                <select class="filter-select" id="sortSelect">
                    <option value="name">Sort by Name</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                    <option value="rarity">Rarity</option>
                    <option value="level">Required Level</option>
                </select>
                
                <select class="filter-select" id="rarityFilter">
                    <option value="all">All Rarities</option>
                    <option value="common">Common</option>
                    <option value="uncommon">Uncommon</option>
                    <option value="rare">Rare</option>
                    <option value="epic">Epic</option>
                    <option value="legendary">Legendary</option>
                </select>
                
                <input type="text" class="search-input" id="itemSearch" placeholder="Search items...">
                
                <button class="btn-primary" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
            
            <!-- Shop Items Grid -->
            <div class="items-grid" id="itemsGrid">
                <!-- Items loaded via JavaScript -->
            </div>
            
            <!-- Shop Pagination -->
            <div class="shop-pagination" id="shopPagination">
                <!-- Pagination loaded via JavaScript -->
            </div>
            
            <!-- Featured Items -->
            <div class="shop-featured">
                <h3><i class="fas fa-star"></i> Featured Items</h3>
                <div class="featured-slider" id="featuredSlider">
                    <!-- Featured items loaded via JavaScript -->
                </div>
            </div>
        </div>
    </main>
    
    <!-- Buy Modal -->
    <div class="modal" id="buyModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2><i class="fas fa-shopping-cart"></i> Purchase Item</h2>
            <div id="buyModalContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Purchase Credits Modal -->
    <div class="modal" id="purchaseModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2><i class="fas fa-credit-card"></i> Purchase Credits</h2>
            <div id="purchaseModalContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        // Shop System
        const ShopSystem = {
            currentCategory: 'all',
            currentPage: 1,
            itemsPerPage: 12,
            currentSort: 'name',
            currentRarity: 'all',
            searchQuery: '',
            
            init: function() {
                this.loadCategories();
                this.loadItems();
                this.loadFeaturedItems();
                this.setupEventListeners();
            },
            
            loadCategories: function() {
                $.ajax({
                    url: '../api/shop.php?action=get_categories',
                    method: 'GET',
                    success: function(data) {
                        const categories = JSON.parse(data);
                        let categoriesHTML = '';
                        let tabsHTML = '';
                        
                        categories.forEach(category => {
                            // Category cards
                            categoriesHTML += `
                                <div class="category-card" data-category="${category.id}" 
                                     onclick="ShopSystem.selectCategory('${category.id}')">
                                    <div class="category-icon">
                                        <i class="fas ${category.icon}"></i>
                                    </div>
                                    <div class="category-name">${category.name}</div>
                                </div>
                            `;
                            
                            // Tabs
                            tabsHTML += `
                                <button class="shop-tab ${category.id === 'all' ? 'active' : ''}" 
                                        data-category="${category.id}"
                                        onclick="ShopSystem.selectCategory('${category.id}')">
                                    <i class="fas ${category.icon}"></i> ${category.name}
                                </button>
                            `;
                        });
                        
                        $('#shopCategories').html(categoriesHTML);
                        $('#shopTabs').html(tabsHTML);
                    }
                });
            },
            
            loadItems: function() {
                const params = new URLSearchParams({
                    category: this.currentCategory,
                    page: this.currentPage,
                    sort: this.currentSort,
                    rarity: this.currentRarity,
                    search: this.searchQuery
                });
                
                $.ajax({
                    url: `../api/shop.php?action=get_items&${params}`,
                    method: 'GET',
                    success: function(data) {
                        const response = JSON.parse(data);
                        ShopSystem.renderItems(response.items);
                        ShopSystem.renderPagination(response.total, response.pages);
                    }
                });
            },
            
            loadFeaturedItems: function() {
                // For demo, use hardcoded featured items
                const featuredItems = [
                    {
                        id: 1001,
                        name: 'Elucidator',
                        type: 'weapon',
                        rarity: 'legendary',
                        image: 'elucidator.png',
                        price_gold: 50000,
                        price_credits: 5000,
                        discount: 20,
                        description: 'The black sword wielded by the Black Swordsman'
                    },
                    {
                        id: 1003,
                        name: 'Coat of Midnight',
                        type: 'armor',
                        rarity: 'epic',
                        image: 'midnight_coat.png',
                        price_gold: 30000,
                        price_credits: 3000,
                        discount: 15,
                        description: 'A black coat that enhances agility'
                    },
                    {
                        id: 2001,
                        name: 'Large HP Potion',
                        type: 'consumable',
                        rarity: 'uncommon',
                        image: 'large_hp_potion.png',
                        price_gold: 500,
                        price_credits: 50,
                        discount: 10,
                        description: 'Restores 200 HP'
                    },
                    {
                        id: 3001,
                        name: 'Dragon Scale',
                        type: 'material',
                        rarity: 'rare',
                        image: 'dragon_scale.png',
                        price_gold: 1000,
                        price_credits: 100,
                        discount: 0,
                        description: 'Rare material for crafting'
                    }
                ];
                
                let sliderHTML = '';
                featuredItems.forEach(item => {
                    const discountBadge = item.discount > 0 ? 
                        `<div class="item-discount">-${item.discount}%</div>` : '';
                    
                    sliderHTML += `
                        <div class="featured-item-large">
                            ${discountBadge}
                            <div class="item-image-large" 
                                 style="background-image: url('../images/items/${item.image}')"></div>
                            <h4 class="item-name rarity-${item.rarity}">${item.name}</h4>
                            <p class="item-description">${item.description}</p>
                            <div class="item-prices">
                                <div class="price-option">
                                    <i class="fas fa-coins"></i> ${item.price_gold.toLocaleString()} Gold
                                </div>
                                <div class="price-option">
                                    <i class="fas fa-gem"></i> ${item.price_credits} Credits
                                </div>
                            </div>
                            <button class="btn-buy" onclick="ShopSystem.showBuyModal(${item.id})">
                                <i class="fas fa-shopping-cart"></i> Buy Now
                            </button>
                        </div>
                    `;
                });
                
                $('#featuredSlider').html(sliderHTML);
            },
            
            renderItems: function(items) {
                let itemsHTML = '';
                
                if (items.length === 0) {
                    itemsHTML = `
                        <div class="no-items">
                            <i class="fas fa-box-open fa-3x"></i>
                            <h3>No items found</h3>
                            <p>Try changing your filters or check back later!</p>
                        </div>
                    `;
                } else {
                    items.forEach(item => {
                        const vipBadge = item.vip_only ? 
                            `<div class="vip-badge">VIP</div>` : '';
                        
                        itemsHTML += `
                            <div class="item-card ${item.rarity}">
                                ${vipBadge}
                                <div class="item-header">
                                    <div class="item-image" 
                                         style="background-image: url('../images/items/${item.image}')"></div>
                                    <div class="item-info">
                                        <div class="item-name">${item.name}</div>
                                        <div class="item-type">${ShopSystem.getItemTypeName(item.type)}</div>
                                        <div class="item-rarity rarity-${item.rarity}">${item.rarity}</div>
                                    </div>
                                </div>
                                <div class="item-description">
                                    ${item.description || 'No description available.'}
                                </div>
                                <div class="item-requirements">
                                    ${item.required_level ? `<span>Lv. ${item.required_level}+</span>` : ''}
                                    ${item.required_class ? `<span>${item.required_class}</span>` : ''}
                                </div>
                                <div class="item-price">
                                    <div class="price-amount">
                                        ${item.price_gold > 0 ? 
                                            `<i class="fas fa-coins"></i> ${item.price_gold.toLocaleString()}` : 
                                            `<i class="fas fa-gem"></i> ${item.price_credits}`
                                        }
                                    </div>
                                    <button class="buy-btn" onclick="ShopSystem.showBuyModal(${item.id})"
                                            ${item.vip_only && !<?php echo $is_vip ? 'true' : 'false'; ?> ? 'disabled' : ''}>
                                        ${item.vip_only && !<?php echo $is_vip ? 'true' : 'false'; ?> ? 
                                            'VIP Only' : 'Buy'}
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                }
                
                $('#itemsGrid').html(itemsHTML);
            },
            
            renderPagination: function(total, pages) {
                let paginationHTML = '';
                
                if (pages > 1) {
                    // Previous button
                    paginationHTML += `
                        <button class="page-btn ${this.currentPage === 1 ? 'disabled' : ''}" 
                                onclick="ShopSystem.changePage(${this.currentPage - 1})"
                                ${this.currentPage === 1 ? 'disabled' : ''}>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    `;
                    
                    // Page numbers
                    const maxPages = 5;
                    let startPage = Math.max(1, this.currentPage - Math.floor(maxPages / 2));
                    let endPage = Math.min(pages, startPage + maxPages - 1);
                    
                    if (endPage - startPage + 1 < maxPages) {
                        startPage = Math.max(1, endPage - maxPages + 1);
                    }
                    
                    for (let i = startPage; i <= endPage; i++) {
                        paginationHTML += `
                            <button class="page-btn ${i === this.currentPage ? 'active' : ''}" 
                                    onclick="ShopSystem.changePage(${i})">
                                ${i}
                            </button>
                        `;
                    }
                    
                    // Next button
                    paginationHTML += `
                        <button class="page-btn ${this.currentPage === pages ? 'disabled' : ''}" 
                                onclick="ShopSystem.changePage(${this.currentPage + 1})"
                                ${this.currentPage === pages ? 'disabled' : ''}>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    `;
                }
                
                $('#shopPagination').html(paginationHTML);
            },
            
            selectCategory: function(category) {
                this.currentCategory = category;
                this.currentPage = 1;
                
                // Update active tab
                $('.shop-tab').removeClass('active');
                $(`.shop-tab[data-category="${category}"]`).addClass('active');
                
                // Update active category card
                $('.category-card').removeClass('active');
                $(`.category-card[data-category="${category}"]`).addClass('active');
                
                this.loadItems();
            },
            
            changePage: function(page) {
                this.currentPage = page;
                this.loadItems();
                
                // Scroll to top of items
                $('#itemsGrid')[0].scrollIntoView({ behavior: 'smooth' });
            },
            
            applyFilters: function() {
                this.currentSort = $('#sortSelect').val();
                this.currentRarity = $('#rarityFilter').val();
                this.searchQuery = $('#itemSearch').val();
                this.currentPage = 1;
                
                this.loadItems();
            },
            
            showBuyModal: function(itemId) {
                // Load item details and show modal
                $.ajax({
                    url: `../api/shop.php?action=get_item&id=${itemId}`,
                    method: 'GET',
                    success: function(data) {
                        const item = JSON.parse(data);
                        const modalContent = ShopSystem.createBuyModalContent(item);
                        $('#buyModalContent').html(modalContent);
                        $('#buyModal').fadeIn();
                    }
                });
            },
            
            createBuyModalContent: function(item) {
                const playerGold = <?php echo $player['gold']; ?>;
                const playerCredits = <?php echo $player['credits']; ?>;
                const isVip = <?php echo $is_vip ? 'true' : 'false'; ?>;
                
                // Apply VIP discount
                const discount = isVip ? 0.1 : 0;
                const finalGoldPrice = Math.floor(item.price_gold * (1 - discount));
                const finalCreditsPrice = Math.floor(item.price_credits * (1 - discount));
                
                const canAffordGold = playerGold >= finalGoldPrice;
                const canAffordCredits = playerCredits >= finalCreditsPrice;
                
                let statsHTML = '';
                if (item.stats) {
                    for (const [stat, value] of Object.entries(item.stats)) {
                        if (value !== 0) {
                            const statName = stat.toUpperCase();
                            const statValue = value > 0 ? `+${value}` : value;
                            statsHTML += `<div class="stat-row"><span>${statName}:</span> <span>${statValue}</span></div>`;
                        }
                    }
                }
                
                return `
                    <div class="buy-modal-content">
                        <div class="item-header">
                            <div class="item-image-large" 
                                 style="background-image: url('../images/items/${item.image}')"></div>
                            <div class="item-details">
                                <h3 class="item-name ${item.rarity}">${item.name}</h3>
                                <div class="item-type">${ShopSystem.getItemTypeName(item.type)}</div>
                                <div class="item-rarity rarity-${item.rarity}">${item.rarity.toUpperCase()}</div>
                            </div>
                        </div>
                        
                        <div class="item-description">
                            <p>${item.description || 'No description available.'}</p>
                        </div>
                        
                        ${statsHTML ? `
                            <div class="item-stats">
                                <h4>Stats</h4>
                                ${statsHTML}
                            </div>
                        ` : ''}
                        
                        <div class="item-requirements">
                            ${item.required_level ? `<div>Required Level: ${item.required_level}</div>` : ''}
                            ${item.required_class ? `<div>Class: ${item.required_class}</div>` : ''}
                        </div>
                        
                        <div class="purchase-options">
                            <h4>Purchase Options</h4>
                            
                            ${item.price_gold > 0 ? `
                                <div class="purchase-option ${!canAffordGold ? 'disabled' : ''}">
                                    <div class="option-header">
                                        <i class="fas fa-coins"></i>
                                        <span class="option-price">${finalGoldPrice.toLocaleString()} Gold</span>
                                        ${discount > 0 ? `<span class="discount-badge">-${discount * 100}%</span>` : ''}
                                    </div>
                                    <div class="option-balance">
                                        Your Balance: ${playerGold.toLocaleString()} Gold
                                        ${!canAffordGold ? '<span class="insufficient">Insufficient Funds</span>' : ''}
                                    </div>
                                    <button class="btn-buy-option" onclick="ShopSystem.purchaseItem(${item.id}, 'gold')"
                                            ${!canAffordGold ? 'disabled' : ''}>
                                        Buy with Gold
                                    </button>
                                </div>
                            ` : ''}
                            
                            ${item.price_credits > 0 ? `
                                <div class="purchase-option ${!canAffordCredits ? 'disabled' : ''}">
                                    <div class="option-header">
                                        <i class="fas fa-gem"></i>
                                        <span class="option-price">${finalCreditsPrice} Credits</span>
                                        ${discount > 0 ? `<span class="discount-badge">-${discount * 100}%</span>` : ''}
                                    </div>
                                    <div class="option-balance">
                                        Your Balance: ${playerCredits} Credits
                                        ${!canAffordCredits ? '<span class="insufficient">Insufficient Funds</span>' : ''}
                                    </div>
                                    <button class="btn-buy-option" onclick="ShopSystem.purchaseItem(${item.id}, 'credits')"
                                            ${!canAffordCredits ? 'disabled' : ''}>
                                        Buy with Credits
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            },
            
            purchaseItem: function(itemId, currency) {
                $.ajax({
                    url: '../api/shop.php?action=buy',
                    method: 'POST',
                    data: {
                        item_id: itemId,
                        currency: currency,
                        quantity: 1
                    },
                    success: function(data) {
                        const result = JSON.parse(data);
                        
                        if (result.success) {
                            showNotification('Purchase successful!', 'success');
                            $('#buyModal').fadeOut();
                            
                            // Update currency display
                            ShopSystem.updateCurrencyDisplay(result.new_gold, result.new_credits);
                            
                            // Update player stats in session
                            if (typeof updatePlayerStats === 'function') {
                                updatePlayerStats();
                            }
                        } else {
                            showNotification(result.error || 'Purchase failed!', 'error');
                        }
                    }
                });
            },
            
            updateCurrencyDisplay: function(gold, credits) {
                $('#gold-amount').text(gold.toLocaleString());
                $('#credits-amount').text(credits.toLocaleString());
            },
            
            getItemTypeName: function(type) {
                const typeNames = {
                    'weapon': 'Weapon',
                    'armor': 'Armor',
                    'consumable': 'Consumable',
                    'material': 'Material',
                    'skill': 'Skill Book'
                };
                return typeNames[type] || type;
            },
            
            setupEventListeners: function() {
                // Search on Enter key
                $('#itemSearch').keypress(function(e) {
                    if (e.which === 13) {
                        ShopSystem.applyFilters();
                    }
                });
                
                // Currency purchase options
                $('.currency-option').click(function() {
                    const amount = $(this).data('amount');
                    const price = $(this).data('price');
                    
                    $('#purchaseModalContent').html(`
                        <div class="purchase-modal-content">
                            <h3>Purchase ${amount} Credits</h3>
                            <p>Price: $${price}</p>
                            <div class="payment-methods">
                                <button class="payment-method" onclick="ShopSystem.processPayment('paypal')">
                                    <i class="fab fa-paypal"></i> PayPal
                                </button>
                                <button class="payment-method" onclick="ShopSystem.processPayment('stripe')">
                                    <i class="fab fa-cc-stripe"></i> Credit Card
                                </button>
                                <button class="payment-method" onclick="ShopSystem.processPayment('crypto')">
                                    <i class="fas fa-coins"></i> Cryptocurrency
                                </button>
                            </div>
                            <p class="note">After payment, credits will be added to your account within minutes.</p>
                        </div>
                    `);
                    
                    $('#purchaseModal').fadeIn();
                });
            },
            
            processPayment: function(method) {
                // In a real application, integrate with payment gateway
                showNotification('Payment integration required', 'info');
                $('#purchaseModal').fadeOut();
            }
        };
        
        // Initialize shop on page load
        $(document).ready(function() {
            ShopSystem.init();
            
            // Global functions
            window.ShopSystem = ShopSystem;
            window.openVipShop = function() {
                $('#vip-modal').fadeIn();
                loadVipPlans();
            };
        });
    </script>
</body>
</html>
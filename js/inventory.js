// SAO RPG - Inventory System

class InventorySystem {
    constructor() {
        this.items = [];
        this.equipment = {};
        this.selectedItem = null;
        this.currentFilter = 'all';
        this.currentSort = 'name';
        this.sortAscending = true;
    }
    
    // Initialize inventory
    init() {
        this.loadInventory();
        this.loadEquipment();
        this.setupEventListeners();
        this.renderFilters();
    }
    
    // Load inventory from server
    loadInventory() {
        $.ajax({
            url: 'api/inventory.php?action=get_items',
            method: 'GET',
            success: (data) => {
                try {
                    this.items = JSON.parse(data);
                    this.renderInventory();
                } catch (e) {
                    console.error('Error loading inventory:', e);
                }
            }
        });
    }
    
    // Load equipped items
    loadEquipment() {
        $.ajax({
            url: 'api/inventory.php?action=get_equipment',
            method: 'GET',
            success: (data) => {
                try {
                    this.equipment = JSON.parse(data);
                    this.renderEquipment();
                } catch (e) {
                    console.error('Error loading equipment:', e);
                }
            }
        });
    }
    
    // Setup event listeners
    setupEventListeners() {
        // Filter buttons
        $(document).on('click', '.inventory-tab', (e) => {
            const filter = $(e.target).data('filter');
            this.setFilter(filter);
        });
        
        // Sort dropdown
        $('#sort-select').change((e) => {
            this.currentSort = $(e.target).val();
            this.sortAscending = true;
            this.renderInventory();
        });
        
        // Search input
        $('#inventory-search').on('input', (e) => {
            this.filterItems($(e.target).val());
        });
        
        // Control buttons
        $('.sort-btn').click(() => this.toggleSort());
        $('.sell-btn').click(() => this.sellSelected());
        $('.use-btn').click(() => this.useSelected());
    }
    
    // Set filter
    setFilter(filter) {
        this.currentFilter = filter;
        
        // Update active tab
        $('.inventory-tab').removeClass('active');
        $(`.inventory-tab[data-filter="${filter}"]`).addClass('active');
        
        // Render inventory with filter
        this.renderInventory();
    }
    
    // Toggle sort order
    toggleSort() {
        this.sortAscending = !this.sortAscending;
        this.renderInventory();
    }
    
    // Filter items by search term
    filterItems(searchTerm) {
        const $items = $('.inventory-slot');
        const term = searchTerm.toLowerCase();
        
        $items.each((index, item) => {
            const $item = $(item);
            const itemName = $item.data('name') || '';
            const itemType = $item.data('type') || '';
            const itemRarity = $item.data('rarity') || '';
            
            const matches = itemName.toLowerCase().includes(term) ||
                           itemType.toLowerCase().includes(term) ||
                           itemRarity.toLowerCase().includes(term);
            
            $item.toggle(matches);
        });
    }
    
    // Render inventory grid
    renderInventory() {
        const $grid = $('#inventoryGrid');
        if ($grid.length === 0) return;
        
        // Filter items
        let filteredItems = this.items;
        if (this.currentFilter !== 'all') {
            filteredItems = this.items.filter(item => item.type === this.currentFilter);
        }
        
        // Sort items
        filteredItems.sort((a, b) => {
            let aValue, bValue;
            
            switch(this.currentSort) {
                case 'name':
                    aValue = a.name;
                    bValue = b.name;
                    break;
                case 'rarity':
                    const rarityOrder = { 'legendary': 5, 'epic': 4, 'rare': 3, 'uncommon': 2, 'common': 1 };
                    aValue = rarityOrder[a.rarity] || 0;
                    bValue = rarityOrder[b.rarity] || 0;
                    break;
                case 'level':
                    aValue = a.required_level || 0;
                    bValue = b.required_level || 0;
                    break;
                default:
                    aValue = a[this.currentSort] || 0;
                    bValue = b[this.currentSort] || 0;
            }
            
            if (aValue < bValue) return this.sortAscending ? -1 : 1;
            if (aValue > bValue) return this.sortAscending ? 1 : -1;
            return 0;
        });
        
        // Clear grid
        $grid.empty();
        
        // Add items to grid
        filteredItems.forEach(item => {
            const $slot = this.createInventorySlot(item);
            $grid.append($slot);
        });
        
        // Add empty slots
        const emptySlots = 30 - filteredItems.length; // 30 total slots
        for (let i = 0; i < emptySlots; i++) {
            const $emptySlot = $('<div class="inventory-slot empty"></div>');
            $grid.append($emptySlot);
        }
        
        // Update item count
        this.updateItemCount(filteredItems.length);
    }
    
    // Create inventory slot
    createInventorySlot(item) {
        const $slot = $(`
            <div class="inventory-slot filled" 
                 data-id="${item.id}"
                 data-name="${item.name}"
                 data-type="${item.type}"
                 data-rarity="${item.rarity}">
                <div class="item-in-slot" style="background-image: url('images/items/${item.image || 'default.png'}')">
                    ${item.quantity > 1 ? `<span class="item-quantity">${item.quantity}</span>` : ''}
                    ${item.equipped ? '<span class="item-equipped">EQ</span>' : ''}
                </div>
            </div>
        `);
        
        // Add click handler
        $slot.click(() => {
            this.selectItem(item);
        });
        
        // Add rarity class
        $slot.addClass(`rarity-${item.rarity}`);
        
        return $slot;
    }
    
    // Render equipment slots
    renderEquipment() {
        const equipmentSlots = [
            { id: 'weapon', name: 'Weapon', icon: 'fa-swords' },
            { id: 'armor', name: 'Armor', icon: 'fa-shield-alt' },
            { id: 'helmet', name: 'Helmet', icon: 'fa-hard-hat' },
            { id: 'gloves', name: 'Gloves', icon: 'fa-hand-paper' },
            { id: 'boots', name: 'Boots', icon: 'fa-walking' },
            { id: 'accessory1', name: 'Accessory', icon: 'fa-ring' },
            { id: 'accessory2', name: 'Accessory', icon: 'fa-ring' }
        ];
        
        const $slots = $('.equipment-slots');
        $slots.empty();
        
        equipmentSlots.forEach(slot => {
            const equippedItem = this.equipment[slot.id];
            const $slot = this.createEquipmentSlot(slot, equippedItem);
            $slots.append($slot);
        });
    }
    
    // Create equipment slot
    createEquipmentSlot(slot, item) {
        const isFilled = !!item;
        
        const $slot = $(`
            <div class="equipment-slot ${isFilled ? 'filled' : ''}" data-slot="${slot.id}">
                <div class="slot-icon">
                    <i class="fas ${slot.icon}"></i>
                </div>
                <div class="slot-name">${slot.name}</div>
                ${isFilled ? `
                    <div class="slot-item" style="background-image: url('images/items/${item.image}')"></div>
                    <div class="slot-item-name">${item.name}</div>
                ` : ''}
            </div>
        `);
        
        // Add click handler for unequip
        if (isFilled) {
            $slot.click(() => {
                this.unequipItem(slot.id, item.id);
            });
        }
        
        return $slot;
    }
    
    // Render filters
    renderFilters() {
        const filters = [
            { id: 'all', name: 'All Items', icon: 'fa-box' },
            { id: 'weapon', name: 'Weapons', icon: 'fa-swords' },
            { id: 'armor', name: 'Armor', icon: 'fa-shield-alt' },
            { id: 'consumable', name: 'Consumables', icon: 'fa-potion-bottle' },
            { id: 'material', name: 'Materials', icon: 'fa-gem' }
        ];
        
        const $filters = $('.inventory-tabs');
        if ($filters.length === 0) return;
        
        filters.forEach(filter => {
            const $filter = $(`
                <button class="inventory-tab ${filter.id === this.currentFilter ? 'active' : ''}" 
                        data-filter="${filter.id}">
                    <i class="fas ${filter.icon}"></i> ${filter.name}
                </button>
            `);
            $filters.append($filter);
        });
    }
    
    // Select item
    selectItem(item) {
        this.selectedItem = item;
        
        // Highlight selected item
        $('.inventory-slot').removeClass('selected');
        $(`.inventory-slot[data-id="${item.id}"]`).addClass('selected');
        
        // Show item details
        this.showItemDetails(item);
        
        // Update control buttons
        this.updateControlButtons(item);
    }
    
    // Show item details
    showItemDetails(item) {
        const $details = $('#itemDetailsPanel');
        if ($details.length === 0) {
            this.createItemDetailsPanel();
        }
        
        // Build stats HTML
        let statsHTML = '';
        if (item.stats) {
            for (const [stat, value] of Object.entries(item.stats)) {
                if (value !== 0) {
                    const statName = this.getStatName(stat);
                    const statValue = value > 0 ? `+${value}` : value;
                    const statClass = value > 0 ? 'positive' : value < 0 ? 'negative' : '';
                    
                    statsHTML += `
                        <div class="stat-row">
                            <span class="stat-name">${statName}:</span>
                            <span class="stat-value ${statClass}">${statValue}</span>
                        </div>
                    `;
                }
            }
        }
        
        // Build details HTML
        const detailsHTML = `
            <div class="item-details-header">
                <div class="item-image-large">
                    <img src="images/items/${item.image || 'default.png'}" alt="${item.name}">
                </div>
                <div class="item-info-large">
                    <h3 class="item-name ${item.rarity}">${item.name}</h3>
                    <div class="item-type">${this.getItemTypeName(item.type)}</div>
                    <div class="item-rarity-badge rarity-${item.rarity}">${item.rarity.toUpperCase()}</div>
                </div>
            </div>
            
            <div class="item-description">
                <p>${item.description || 'No description available.'}</p>
            </div>
            
            ${statsHTML ? `
                <div class="item-stats-section">
                    <h4>Stats</h4>
                    <div class="item-stats">
                        ${statsHTML}
                    </div>
                </div>
            ` : ''}
            
            <div class="item-requirements">
                ${item.required_level ? `<div class="requirement">Required Level: ${item.required_level}</div>` : ''}
                ${item.required_class ? `<div class="requirement">Class: ${item.required_class}</div>` : ''}
            </div>
            
            <div class="item-actions">
                ${item.equipped ? 
                    `<button class="btn-action unequip-btn" onclick="inventorySystem.unequipItem('${item.equipped_slot}', ${item.id})">
                        <i class="fas fa-times"></i> Unequip
                    </button>` : 
                    (item.type === 'weapon' || item.type === 'armor' ? 
                        `<button class="btn-action equip-btn" onclick="inventorySystem.equipItem(${item.id})">
                            <i class="fas fa-check"></i> Equip
                        </button>` : 
                        `<button class="btn-action use-btn" onclick="inventorySystem.useItem(${item.id})">
                            <i class="fas fa-vial"></i> Use
                        </button>`
                    )
                }
                <button class="btn-action sell-btn" onclick="inventorySystem.sellItem(${item.id})">
                    <i class="fas fa-coins"></i> Sell
                </button>
            </div>
        `;
        
        $('#itemDetailsContent').html(detailsHTML);
        $('#itemDetailsPanel').fadeIn();
    }
    
    // Create item details panel
    createItemDetailsPanel() {
        const $panel = $(`
            <div id="itemDetailsPanel" class="item-details-panel">
                <div class="close-details">&times;</div>
                <div id="itemDetailsContent"></div>
            </div>
        `);
        
        $('body').append($panel);
        
        // Close panel when clicking X
        $('.close-details').click(() => {
            $('#itemDetailsPanel').fadeOut();
        });
        
        // Close panel when clicking outside
        $(window).click((e) => {
            if ($(e.target).hasClass('item-details-panel')) {
                $(e.target).fadeOut();
            }
        });
    }
    
    // Update control buttons
    updateControlButtons(item) {
        const $useBtn = $('.use-btn');
        const $sellBtn = $('.sell-btn');
        
        if (item.type === 'consumable') {
            $useBtn.show().prop('disabled', false);
        } else {
            $useBtn.hide();
        }
        
        $sellBtn.prop('disabled', false);
    }
    
    // Equip item
    equipItem(itemId) {
        $.ajax({
            url: 'api/inventory.php?action=equip',
            method: 'POST',
            data: { item_id: itemId },
            success: (data) => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        showNotification('Item equipped!', 'success');
                        
                        // Reload inventory and equipment
                        this.loadInventory();
                        this.loadEquipment();
                        
                        // Close details panel
                        $('#itemDetailsPanel').fadeOut();
                    } else {
                        showNotification(result.message || 'Cannot equip item!', 'error');
                    }
                } catch (e) {
                    console.error('Error equipping item:', e);
                }
            }
        });
    }
    
    // Unequip item
    unequipItem(slot, itemId) {
        $.ajax({
            url: 'api/inventory.php?action=unequip',
            method: 'POST',
            data: { 
                item_id: itemId,
                slot: slot 
            },
            success: (data) => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        showNotification('Item unequipped!', 'success');
                        
                        // Reload inventory and equipment
                        this.loadInventory();
                        this.loadEquipment();
                        
                        // Close details panel
                        $('#itemDetailsPanel').fadeOut();
                    }
                } catch (e) {
                    console.error('Error unequipping item:', e);
                }
            }
        });
    }
    
    // Use item
    useItem(itemId) {
        if (!this.selectedItem) return;
        
        if (this.selectedItem.type !== 'consumable') {
            showNotification('This item cannot be used!', 'error');
            return;
        }
        
        $.ajax({
            url: 'api/inventory.php?action=use',
            method: 'POST',
            data: { item_id: itemId },
            success: (data) => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        showNotification(result.message || 'Item used!', 'success');
                        
                        // Reload inventory
                        this.loadInventory();
                        
                        // Update player stats if needed
                        if (window.updatePlayerStats) {
                            window.updatePlayerStats();
                        }
                        
                        // Close details panel
                        $('#itemDetailsPanel').fadeOut();
                    } else {
                        showNotification(result.message || 'Cannot use item!', 'error');
                    }
                } catch (e) {
                    console.error('Error using item:', e);
                }
            }
        });
    }
    
    // Sell item
    sellItem(itemId) {
        if (!this.selectedItem) return;
        
        const price = this.selectedItem.sell_price || Math.floor(this.selectedItem.buy_price * 0.5);
        
        if (!confirm(`Sell ${this.selectedItem.name} for ${price} Gold?`)) {
            return;
        }
        
        $.ajax({
            url: 'api/inventory.php?action=sell',
            method: 'POST',
            data: { 
                item_id: itemId,
                quantity: 1
            },
            success: (data) => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        showNotification(`Sold for ${price} Gold!`, 'success');
                        
                        // Reload inventory
                        this.loadInventory();
                        
                        // Update player gold if on index page
                        if (window.updatePlayerStats) {
                            window.updatePlayerStats();
                        }
                        
                        // Close details panel
                        $('#itemDetailsPanel').fadeOut();
                    }
                } catch (e) {
                    console.error('Error selling item:', e);
                }
            }
        });
    }
    
    // Sell selected item (from control button)
    sellSelected() {
        if (!this.selectedItem) {
            showNotification('Select an item first!', 'error');
            return;
        }
        
        this.sellItem(this.selectedItem.id);
    }
    
    // Use selected item (from control button)
    useSelected() {
        if (!this.selectedItem) {
            showNotification('Select an item first!', 'error');
            return;
        }
        
        this.useItem(this.selectedItem.id);
    }
    
    // Update item count display
    updateItemCount(count) {
        $('#item-count').text(`${count}/30`);
    }
    
    // Utility functions
    getStatName(stat) {
        const statNames = {
            'atk': 'Attack',
            'def': 'Defense',
            'hp': 'HP',
            'mp': 'MP',
            'agi': 'Agility',
            'crit': 'Critical',
            'str': 'Strength',
            'int': 'Intelligence',
            'dex': 'Dexterity'
        };
        
        return statNames[stat] || stat.toUpperCase();
    }
    
    getItemTypeName(type) {
        const typeNames = {
            'weapon': 'Weapon',
            'armor': 'Armor',
            'consumable': 'Consumable',
            'material': 'Material',
            'skill': 'Skill Book'
        };
        
        return typeNames[type] || type;
    }
}

// Create global inventory system instance
const inventorySystem = new InventorySystem();

// Initialize on page load
$(document).ready(() => {
    if ($('#inventoryGrid').length > 0) {
        inventorySystem.init();
    }
});

// Global functions for HTML onclick
window.inventorySystem = inventorySystem;
window.showNotification = showNotification;
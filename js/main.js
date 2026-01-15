// SAO RPG - Main JavaScript File

// Game State
const GameState = {
    player: null,
    currentMonster: null,
    battleInterval: null,
    autoBattle: false,
    chatPolling: null,
    energyRegenInterval: null,
    notifications: []
};

// Initialize Game
$(document).ready(function() {
    console.log('SAO RPG Initialized');
    
    // Check if user is logged in
    if (typeof playerHP !== 'undefined') {
        initGame();
    } else {
        initGuestMode();
    }
    
    // Initialize tooltips
    initTooltips();
    
    // Initialize modals
    initModals();
    
    // Initialize chat if logged in
    if (typeof playerHP !== 'undefined') {
        initChat();
    }
    
    // Initialize event listeners
    initEventListeners();
});

// Initialize Game Functions
function initGame() {
    console.log('Initializing game for logged in user');
    
    // Start energy regeneration timer
    startEnergyRegen();
    
    // Load initial data
    loadInitialData();
    
    // Start background updates
    startBackgroundUpdates();
}

function initGuestMode() {
    console.log('Initializing guest mode');
    
    // Hide game-specific features
    $('.player-card, .quick-actions, .online-friends').hide();
    
    // Show welcome screen
    $('.welcome-screen').show();
}

function initTooltips() {
    // Initialize tooltips for elements with data-tooltip attribute
    $(document).on('mouseenter', '[data-tooltip]', function(e) {
        const tooltipText = $(this).data('tooltip');
        if (!tooltipText) return;
        
        // Remove existing tooltip
        $('.custom-tooltip').remove();
        
        // Create tooltip
        const tooltip = $('<div class="custom-tooltip"></div>')
            .text(tooltipText)
            .css({
                position: 'absolute',
                left: e.pageX + 10,
                top: e.pageY + 10,
                background: 'rgba(0, 0, 0, 0.9)',
                border: '1px solid #00b0ff',
                borderRadius: '5px',
                padding: '8px 12px',
                color: '#fff',
                fontSize: '12px',
                zIndex: '10000',
                pointerEvents: 'none',
                maxWidth: '200px',
                whiteSpace: 'normal'
            });
        
        $('body').append(tooltip);
    });
    
    $(document).on('mouseleave', '[data-tooltip]', function() {
        $('.custom-tooltip').remove();
    });
    
    $(document).on('mousemove', '[data-tooltip]', function(e) {
        $('.custom-tooltip').css({
            left: e.pageX + 10,
            top: e.pageY + 10
        });
    });
}

function initModals() {
    // Close modal when clicking X
    $('.close-modal').click(function() {
        $(this).closest('.modal').fadeOut();
    });
    
    // Close modal when clicking outside
    $(window).click(function(e) {
        if ($(e.target).hasClass('modal')) {
            $(e.target).fadeOut();
        }
    });
    
    // Escape key closes modal
    $(document).keydown(function(e) {
        if (e.key === 'Escape') {
            $('.modal').fadeOut();
        }
    });
}

function initChat() {
    // Load initial chat messages
    loadChatMessages();
    
    // Start polling for new messages
    GameState.chatPolling = setInterval(loadChatMessages, 3000);
    
    // Send message on Enter key
    $('#chat-input').keypress(function(e) {
        if (e.which === 13) { // Enter key
            sendMessage();
        }
    });
}

function initEventListeners() {
    // Quick action buttons
    $('.btn-action.heal').click(quickHeal);
    $('.btn-action.buff').click(useBuff);
    $('.btn-action.rest').click(rest);
    $('.btn-action.vip').click(openVipShop);
    
    // VIP button
    $('.btn-become-vip').click(openVipPurchase);
    
    // Auto battle button
    $('.btn-auto-battle').click(toggleAutoBattle);
    
    // Daily reward button
    $('.btn-daily-reward').click(claimDailyReward);
    
    // Chat controls
    $('.chat-controls button').click(function() {
        toggleChat();
    });
    
    // Battle floor buttons
    $('.floor-btn').click(function() {
        const floor = $(this).data('floor') || parseInt($(this).text().replace('F', ''));
        changeFloor(floor);
    });
}

// Game Functions
function loadInitialData() {
    // Load player stats
    updatePlayerStats();
    
    // Load online players
    loadOnlinePlayers();
    
    // Load inventory preview
    loadInventoryPreview();
    
    // Load recent activity
    loadRecentActivity();
    
    // Load active quests
    loadActiveQuests();
    
    // Load notifications
    loadNotifications();
}

function updatePlayerStats() {
    $.ajax({
        url: 'api/player.php?action=get_stats',
        method: 'GET',
        success: function(data) {
            try {
                const stats = JSON.parse(data);
                updateUIStats(stats);
                
                // Update game state
                GameState.player = stats;
            } catch (e) {
                console.error('Error parsing player stats:', e);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading player stats:', error);
        }
    });
}

function updateUIStats(stats) {
    // Update HP
    $('#current-hp').text(stats.current_hp);
    $('#max-hp').text(stats.max_hp);
    $('.hp-bar').css('width', (stats.current_hp / stats.max_hp) * 100 + '%');
    
    // Update MP
    $('#current-mp').text(stats.current_mp);
    $('#max-mp').text(stats.max_mp);
    $('.mp-bar').css('width', (stats.current_mp / stats.max_mp) * 100 + '%');
    
    // Update EXP
    $('#current-exp').text(stats.exp);
    $('#max-exp').text(stats.max_exp);
    $('.exp-bar').css('width', (stats.exp / stats.max_exp) * 100 + '%');
    
    // Update Energy
    $('#current-energy').text(stats.energy);
    $('.energy-bar').css('width', (stats.energy / stats.max_energy) * 100 + '%');
    
    // Update stats
    $('#stat-atk').text(stats.atk);
    $('#stat-def').text(stats.def);
    $('#stat-agi').text(stats.agi);
    $('#stat-crit').text(stats.crit + '%');
    
    // Update currency
    $('#gold').text(formatNumber(stats.gold));
    $('#credits').text(stats.credits);
    
    // Update floor
    $('#current-floor').text(stats.current_floor);
}

function quickHeal() {
    if (!GameState.player || GameState.player.current_mp < 10) {
        showNotification('Not enough MP!', 'error');
        return;
    }
    
    $.ajax({
        url: 'api/player.php?action=heal',
        method: 'POST',
        data: { amount: 50 },
        success: function(data) {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    updateUIStats(result.stats);
                    GameState.player = result.stats;
                    showNotification('Healed for ' + result.heal_amount + ' HP!', 'success');
                } else {
                    showNotification(result.message || 'Heal failed!', 'error');
                }
            } catch (e) {
                console.error('Error parsing heal response:', e);
                showNotification('Heal failed!', 'error');
            }
        },
        error: function() {
            showNotification('Network error. Please try again.', 'error');
        }
    });
}

function useBuff() {
    if (!GameState.player || GameState.player.current_mp < 20) {
        showNotification('Not enough MP!', 'error');
        return;
    }
    
    $.ajax({
        url: 'api/player.php?action=buff',
        method: 'POST',
        data: { buff_type: 'attack' },
        success: function(data) {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    updateUIStats(result.stats);
                    GameState.player = result.stats;
                    showNotification('Attack increased by ' + result.buff_amount + ' for ' + result.duration + ' turns!', 'success');
                    
                    // Start buff timer
                    startBuffTimer(result.duration);
                }
            } catch (e) {
                console.error('Error parsing buff response:', e);
            }
        }
    });
}

function rest() {
    $.ajax({
        url: 'api/player.php?action=rest',
        method: 'POST',
        success: function(data) {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    updateUIStats(result.stats);
                    GameState.player = result.stats;
                    showNotification('Fully rested! HP and MP restored.', 'success');
                }
            } catch (e) {
                console.error('Error parsing rest response:', e);
            }
        }
    });
}

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
            
            // Add click handlers to buy buttons
            $('.btn-buy').click(function() {
                const planId = $(this).data('plan');
                purchaseVipPlan(planId);
            });
        }
    });
}

function purchaseVipPlan(planId) {
    if (!confirm('Purchase VIP plan? You will be redirected to payment.')) {
        return;
    }
    
    $.ajax({
        url: 'api/vip.php?action=purchase',
        method: 'POST',
        data: { plan_id: planId },
        success: function(data) {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    showNotification('VIP purchase successful!', 'success');
                    $('#vip-modal').fadeOut();
                    
                    // Update player stats to reflect VIP status
                    updatePlayerStats();
                } else {
                    showNotification(result.message || 'Purchase failed!', 'error');
                }
            } catch (e) {
                console.error('Error parsing purchase response:', e);
            }
        }
    });
}

function openVipPurchase() {
    openVipShop();
}

function toggleAutoBattle() {
    if (GameState.autoBattle) {
        stopAutoBattle();
    } else {
        startAutoBattle();
    }
}

function startAutoBattle() {
    if (!GameState.player || GameState.player.energy < 1) {
        showNotification('Not enough energy!', 'error');
        return;
    }
    
    GameState.autoBattle = true;
    $('.btn-auto-battle').html('<i class="fas fa-stop"></i> Stop Auto Battle')
        .removeClass('btn-auto-battle')
        .addClass('btn-stop-battle');
    
    showNotification('Auto battle started!', 'success');
    
    // Start auto battle loop
    GameState.battleInterval = setInterval(function() {
        if (!GameState.player || GameState.player.energy < 1) {
            stopAutoBattle();
            showNotification('Auto battle stopped: No energy left!', 'warning');
            return;
        }
        
        performAutoBattle();
    }, 3000); // Battle every 3 seconds
}

function stopAutoBattle() {
    GameState.autoBattle = false;
    $('.btn-stop-battle').html('<i class="fas fa-robot"></i> Auto Battle')
        .removeClass('btn-stop-battle')
        .addClass('btn-auto-battle');
    
    if (GameState.battleInterval) {
        clearInterval(GameState.battleInterval);
        GameState.battleInterval = null;
    }
    
    showNotification('Auto battle stopped.', 'info');
}

function performAutoBattle() {
    $.ajax({
        url: 'api/battle.php?action=auto',
        method: 'POST',
        success: function(data) {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    // Update player stats
                    updateUIStats(result.player);
                    GameState.player = result.player;
                    
                    // Show battle result
                    if (result.battle_log) {
                        addBattleLog(result.battle_log);
                    }
                    
                    // Show drops if any
                    if (result.drops && result.drops.length > 0) {
                        showDrops(result.drops);
                    }
                    
                    // Check for level up
                    if (result.level_up) {
                        showLevelUp(result.level_up);
                    }
                }
            } catch (e) {
                console.error('Error parsing auto battle response:', e);
            }
        }
    });
}

function claimDailyReward() {
    $.ajax({
        url: 'api/player.php?action=daily_reward',
        method: 'POST',
        success: function(data) {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    showNotification('Daily reward claimed! ' + result.reward, 'success');
                    
                    // Update player stats
                    updatePlayerStats();
                    
                    // Disable button for today
                    $('.btn-daily-reward').prop('disabled', true)
                        .html('<i class="fas fa-check"></i> Reward Claimed');
                } else {
                    showNotification(result.message || 'Already claimed today!', 'warning');
                }
            } catch (e) {
                console.error('Error parsing daily reward response:', e);
            }
        }
    });
}

// Chat Functions
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

function sendMessage() {
    const message = $('#chat-input').val().trim();
    if (message === '') return;
    
    $.ajax({
        url: 'api/chat.php?action=send',
        method: 'POST',
        data: { message: message },
        success: function() {
            $('#chat-input').val('');
            loadChatMessages();
        }
    });
}

function toggleChat() {
    const $chat = $('.global-chat');
    const $messages = $('.chat-messages');
    const $input = $('.chat-input');
    
    if ($messages.is(':visible')) {
        $messages.slideUp();
        $input.slideUp();
        $('.chat-controls button i').removeClass('fa-minus').addClass('fa-plus');
    } else {
        $messages.slideDown();
        $input.slideDown();
        $('.chat-controls button i').removeClass('fa-plus').addClass('fa-minus');
        scrollChatToBottom();
    }
}

function scrollChatToBottom() {
    const $chatMessages = $('#chat-messages');
    $chatMessages.scrollTop($chatMessages[0].scrollHeight);
}

// Battle Functions
function changeFloor(floor) {
    if (!GameState.player || floor > GameState.player.current_floor) {
        showNotification('Floor not unlocked yet!', 'error');
        return;
    }
    
    $.ajax({
        url: 'api/battle.php?action=change_floor',
        method: 'POST',
        data: { floor: floor },
        success: function(data) {
            try {
                const result = JSON.parse(data);
                if (result.success) {
                    // Update UI
                    $('#current-floor').text(floor);
                    $('.floor-btn').removeClass('active');
                    $(`.floor-btn:contains("F${floor}")`).addClass('active');
                    
                    // Update game state
                    if (GameState.player) {
                        GameState.player.current_floor = floor;
                    }
                    
                    showNotification('Moved to floor ' + floor, 'success');
                    
                    // If on battle page, reload monster
                    if (window.location.pathname.includes('battle.php')) {
                        loadMonster();
                    }
                }
            } catch (e) {
                console.error('Error changing floor:', e);
            }
        }
    });
}

function loadMonster() {
    const floor = GameState.player ? GameState.player.current_floor : 1;
    
    $.ajax({
        url: `api/battle.php?action=get_monster&floor=${floor}`,
        method: 'GET',
        success: function(data) {
            try {
                GameState.currentMonster = JSON.parse(data);
                renderBattleArena();
            } catch (e) {
                console.error('Error loading monster:', e);
            }
        }
    });
}

function renderBattleArena() {
    if (!GameState.currentMonster || !GameState.player) return;
    
    const $arena = $('#battleArena');
    const monster = GameState.currentMonster;
    const player = GameState.player;
    
    $arena.html(`
        <div class="combatant player-combatant">
            <div class="combatant-avatar player-avatar" 
                 style="background-image: url('images/avatars/${player.avatar}')"></div>
            <div class="combatant-name player-name">${player.username}</div>
            <div class="combatant-level">Level ${player.level}</div>
            <div class="health-display">
                <div class="stat-label">
                    <i class="fas fa-heart"></i> HP
                    <span>${player.current_hp}</span>/<span>${player.max_hp}</span>
                </div>
                <div class="bar-container">
                    <div class="bar-fill hp-bar" 
                         style="width: ${(player.current_hp / player.max_hp) * 100}%"></div>
                </div>
            </div>
        </div>
        
        <div class="vs-text">VS</div>
        
        <div class="combatant monster-combatant">
            <div class="combatant-avatar monster-avatar"
                 style="background-image: url('images/monsters/${monster.id || 'default'}.png')"></div>
            <div class="combatant-name monster-name">${monster.name}</div>
            <div class="combatant-level">Floor ${monster.floor}</div>
            <div class="health-display">
                <div class="stat-label">
                    <i class="fas fa-heart"></i> HP
                    <span>${monster.current_hp}</span>/<span>${monster.max_hp}</span>
                </div>
                <div class="bar-container">
                    <div class="bar-fill hp-bar" 
                         style="width: ${(monster.current_hp / monster.max_hp) * 100}%"></div>
                </div>
            </div>
        </div>
    `);
}

function attack(skillId = 0) {
    if (!GameState.currentMonster || !GameState.player) return;
    
    if (GameState.player.energy < 1) {
        showNotification('Not enough energy!', 'error');
        return;
    }
    
    $.ajax({
        url: 'api/battle.php?action=attack',
        method: 'POST',
        data: {
            monster_id: GameState.currentMonster.id,
            skill_id: skillId
        },
        success: function(data) {
            try {
                const result = JSON.parse(data);
                handleBattleResult(result);
            } catch (e) {
                console.error('Error parsing battle result:', e);
            }
        }
    });
}

function handleBattleResult(result) {
    // Update player stats
    if (result.player) {
        updateUIStats(result.player);
        GameState.player = result.player;
    }
    
    // Update monster or load new one
    if (result.monster_dead) {
        // Show drops
        if (result.drops && result.drops.length > 0) {
            showDrops(result.drops);
        }
        
        // Load new monster after delay
        setTimeout(() => {
            loadMonster();
        }, 1500);
    } else {
        // Update monster HP
        GameState.currentMonster.current_hp = result.monster_hp;
        renderBattleArena();
    }
    
    // Add to battle log
    if (result.log) {
        addBattleLog(result.log);
    }
    
    // Check for level up
    if (result.level_up) {
        showLevelUp(result.level_up);
    }
}

function addBattleLog(logEntries) {
    const $log = $('#battle-log');
    
    logEntries.forEach(entry => {
        const $entry = $('<div class="log-entry"></div>')
            .addClass(entry.type)
            .addClass(entry.critical ? 'critical' : '')
            .html(`<span class="log-time">[${entry.time}]</span> <span class="log-text">${entry.text}</span>`);
        
        $log.append($entry);
    });
    
    // Scroll to bottom
    $log.scrollTop($log[0].scrollHeight);
}

function showDrops(drops) {
    let dropHtml = '<div class="drop-notification">';
    dropHtml += '<h4><i class="fas fa-gift"></i> Items Dropped!</h4>';
    
    drops.forEach(drop => {
        dropHtml += `
            <div class="drop-item">
                <i class="fas fa-${getItemIcon(drop.type)}"></i>
                <strong>${drop.name}</strong> (${drop.rarity})
                <span class="drop-amount">x${drop.quantity || 1}</span>
            </div>
        `;
    });
    
    dropHtml += '</div>';
    
    // Create and show notification
    const $notification = $(dropHtml)
        .css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: 'rgba(26, 35, 126, 0.95)',
            border: '2px solid #ffd740',
            borderRadius: '10px',
            padding: '15px',
            zIndex: '1000',
            maxWidth: '300px'
        });
    
    $('body').append($notification);
    
    // Remove after 5 seconds
    setTimeout(() => {
        $notification.fadeOut(() => $(this).remove());
    }, 5000);
}

function showLevelUp(levelUpData) {
    const $levelUp = $(`
        <div class="level-up-notification">
            <div class="level-up-content">
                <h2><i class="fas fa-star"></i> LEVEL UP!</h2>
                <p>Congratulations! You reached level <strong>${levelUpData.new_level}</strong>!</p>
                <div class="level-up-stats">
                    <div class="stat-increase">
                        <span>HP:</span>
                        <span>+${levelUpData.hp_increase}</span>
                    </div>
                    <div class="stat-increase">
                        <span>MP:</span>
                        <span>+${levelUpData.mp_increase}</span>
                    </div>
                    <div class="stat-increase">
                        <span>ATK:</span>
                        <span>+${levelUpData.atk_increase}</span>
                    </div>
                    <div class="stat-increase">
                        <span>DEF:</span>
                        <span>+${levelUpData.def_increase}</span>
                    </div>
                </div>
            </div>
        </div>
    `).css({
        position: 'fixed',
        top: '0',
        left: '0',
        width: '100%',
        height: '100%',
        background: 'rgba(0, 0, 0, 0.9)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: '10000'
    });
    
    $('body').append($levelUp);
    
    // Play level up sound if available
    try {
        const audio = new Audio('sounds/level_up.mp3');
        audio.play();
    } catch (e) {
        console.log('Level up sound not available');
    }
    
    // Remove after 5 seconds
    setTimeout(() => {
        $levelUp.fadeOut(() => $(this).remove());
    }, 5000);
}

// Data Loading Functions
function loadOnlinePlayers() {
    $.ajax({
        url: 'api/player.php?action=online_players',
        method: 'GET',
        success: function(data) {
            $('#friends-list').html(data);
        }
    });
}

function loadInventoryPreview() {
    $.ajax({
        url: 'api/inventory.php?action=preview',
        method: 'GET',
        success: function(data) {
            $('#inventory-preview').html(data);
        }
    });
}

function loadRecentActivity() {
    $.ajax({
        url: 'api/player.php?action=recent_activity',
        method: 'GET',
        success: function(data) {
            $('#activity-log').html(data);
        }
    });
}

function loadActiveQuests() {
    $.ajax({
        url: 'api/player.php?action=active_quests',
        method: 'GET',
        success: function(data) {
            $('#quest-list').html(data);
        }
    });
}

function loadNotifications() {
    $.ajax({
        url: 'api/player.php?action=notifications',
        method: 'GET',
        success: function(data) {
            $('#notification-list').html(data);
        }
    });
}

// Utility Functions
function showNotification(message, type = 'info') {
    // Remove existing notifications of same type
    $(`.notification-alert.${type}`).remove();
    
    const $notification = $(`
        <div class="notification-alert ${type}">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            ${message}
        </div>
    `);
    
    $('body').append($notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        $notification.fadeOut(() => $(this).remove());
    }, 5000);
}

function getNotificationIcon(type) {
    switch(type) {
        case 'success': return 'check-circle';
        case 'error': return 'exclamation-circle';
        case 'warning': return 'exclamation-triangle';
        default: return 'info-circle';
    }
}

function getItemIcon(itemType) {
    switch(itemType) {
        case 'weapon': return 'swords';
        case 'armor': return 'shield-alt';
        case 'consumable': return 'potion-bottle';
        case 'material': return 'gem';
        default: return 'box';
    }
}

function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function startEnergyRegen() {
    // Check energy regeneration every minute
    GameState.energyRegenInterval = setInterval(() => {
        $.ajax({
            url: 'api/player.php?action=regen_energy',
            method: 'POST',
            success: function(data) {
                try {
                    const result = JSON.parse(data);
                    if (result.success && result.energy > 0) {
                        // Update energy display
                        $('#current-energy').text(result.energy);
                        $('.energy-bar').css('width', (result.energy / result.max_energy) * 100 + '%');
                        
                        // Update game state
                        if (GameState.player) {
                            GameState.player.energy = result.energy;
                        }
                    }
                } catch (e) {
                    console.error('Error parsing energy regen:', e);
                }
            }
        });
    }, 60000); // Check every minute
}

function startBuffTimer(duration) {
    let remaining = duration;
    
    const timer = setInterval(() => {
        remaining--;
        
        if (remaining <= 0) {
            clearInterval(timer);
            showNotification('Buff has worn off!', 'info');
        }
    }, 60000); // Assume 1 minute per turn
}

function startBackgroundUpdates() {
    // Update online players every 30 seconds
    setInterval(loadOnlinePlayers, 30000);
    
    // Update notifications every minute
    setInterval(loadNotifications, 60000);
    
    // Update recent activity every 2 minutes
    setInterval(loadRecentActivity, 120000);
}

// Global helper functions (accessible from HTML onclick)
window.quickHeal = quickHeal;
window.useBuff = useBuff;
window.rest = rest;
window.openVipShop = openVipShop;
window.openVipPurchase = openVipPurchase;
window.toggleAutoBattle = toggleAutoBattle;
window.claimDailyReward = claimDailyReward;
window.changeFloor = changeFloor;
window.sendMessage = sendMessage;
window.toggleChat = toggleChat;

// Export for debugging
window.GameState = GameState;
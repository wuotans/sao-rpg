-- SAO RPG Database Structure

CREATE DATABASE IF NOT EXISTS sao_rpg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sao_rpg;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    vip_expire DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    is_banned BOOLEAN DEFAULT FALSE,
    ban_reason TEXT NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_vip (vip_expire)
);

-- Tabela de tipos de item
CREATE TABLE item_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slot ENUM('weapon', 'armor', 'helmet', 'gloves', 'boots', 'accessory', 'consumable') NOT NULL,
    can_equip BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de itens (ATUALIZADA COM STATUS)
CREATE TABLE items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type_id INT NOT NULL,
    rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
    floor_available INT DEFAULT 1,
    price INT DEFAULT 0,
    sell_price INT DEFAULT 0,
    max_stack INT DEFAULT 1,
    drop_rate DECIMAL(5,4) DEFAULT 0.01,
    
    -- STATS DO ITEM
    hp_bonus INT DEFAULT 0,
    mp_bonus INT DEFAULT 0,
    atk_bonus INT DEFAULT 0,
    def_bonus INT DEFAULT 0,
    agi_bonus INT DEFAULT 0,
    crit_bonus DECIMAL(5,2) DEFAULT 0,
    dodge_bonus DECIMAL(5,2) DEFAULT 0,
    accuracy_bonus DECIMAL(5,2) DEFAULT 0,
    
    -- BÔNUS ESPECIAIS
    damage_type ENUM('physical', 'magical', 'fire', 'ice', 'lightning', 'holy', 'dark') DEFAULT 'physical',
    elemental_damage INT DEFAULT 0,
    elemental_resistance DECIMAL(5,2) DEFAULT 0,
    
    -- PARA ARMAS
    weapon_damage_min INT DEFAULT 0,
    weapon_damage_max INT DEFAULT 0,
    weapon_speed DECIMAL(5,2) DEFAULT 1.0,
    
    -- PARA CONSUMÍVEIS
    consume_effect VARCHAR(100),
    consume_value INT DEFAULT 0,
    consume_duration INT DEFAULT 0,
    
    image VARCHAR(255),
    available BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES item_types(id),
    INDEX idx_type_id (type_id),
    INDEX idx_rarity (rarity)
);

-- Characters table
CREATE TABLE characters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    class VARCHAR(50) DEFAULT 'Swordsman',
    level INT DEFAULT 1,
    exp INT DEFAULT 0,
    max_exp INT DEFAULT 100,
    hp INT DEFAULT 100,
    max_hp INT DEFAULT 100,
    current_hp INT DEFAULT 100,
    mp INT DEFAULT 50,
    max_mp INT DEFAULT 50,
    current_mp INT DEFAULT 50,
    atk INT DEFAULT 10,
    def INT DEFAULT 5,
    agi INT DEFAULT 8,
    crit DECIMAL(5,2) DEFAULT 5.00,
    gold INT DEFAULT 100,
    credits INT DEFAULT 10,
    current_floor INT DEFAULT 1,
    energy INT DEFAULT 60,
    energy_regen DATETIME DEFAULT CURRENT_TIMESTAMP,
    avatar VARCHAR(100) DEFAULT 'default.png',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_level (level),
    INDEX idx_class (class)
);

-- Tabela de inventário (ATUALIZADA)
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    equipped BOOLEAN DEFAULT 0,
    durability INT DEFAULT 100,
    enhancement_level INT DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_equipped (equipped)
);

-- Tabela de equipamentos ativos
CREATE TABLE equipped_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    slot ENUM('weapon', 'armor', 'helmet', 'gloves', 'boots', 'accessory1', 'accessory2') NOT NULL,
    item_id INT,
    inventory_id INT,
    equipped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_slot (user_id, slot),
    INDEX idx_user_id (user_id)
);

-- Shop items table
CREATE TABLE shop_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    price_gold INT NOT NULL,
    price_credits INT NOT NULL,
    available BOOLEAN DEFAULT TRUE,
    stock INT DEFAULT -1, -- -1 for unlimited
    daily_limit INT DEFAULT 0,
    vip_only BOOLEAN DEFAULT FALSE,
    discount_percent INT DEFAULT 0,
    featured BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_category (category),
    INDEX idx_featured (featured),
    INDEX idx_vip_shop (vip_only)
);

-- Shop transactions
CREATE TABLE shop_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    total_gold INT NOT NULL,
    total_credits INT NOT NULL,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_user_date (user_id, transaction_date)
);

-- VIP plans
CREATE TABLE vip_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    duration INT NOT NULL, -- in days
    price_gold INT NOT NULL,
    price_credits INT NOT NULL,
    price_cash DECIMAL(10,2) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    features JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- VIP transactions
CREATE TABLE vip_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    duration INT NOT NULL,
    payment_method ENUM('gold', 'credits', 'cash') NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0.00,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (plan_id) REFERENCES vip_plans(id),
    INDEX idx_user_vip (user_id, transaction_date)
);

-- Tabela de habilidades (ATUALIZADA COM FÓRMULAS)
CREATE TABLE skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('attack', 'heal', 'buff', 'debuff') NOT NULL,
    
    -- FÓRMULA DE DANO
    base_damage INT DEFAULT 0,
    damage_multiplier DECIMAL(5,2) DEFAULT 1.00,
    atk_scaling DECIMAL(5,2) DEFAULT 1.00, -- Quanto do ATK do personagem é usado
    weapon_scaling DECIMAL(5,2) DEFAULT 0.50, -- Quanto do dano da arma é usado
    
    -- CUSTOS
    mp_cost INT DEFAULT 10,
    hp_cost INT DEFAULT 0,
    
    -- CRÍTICO
    crit_bonus DECIMAL(5,2) DEFAULT 0,
    crit_multiplier DECIMAL(5,2) DEFAULT 2.00,
    
    -- PRECISÃO
    accuracy INT DEFAULT 95,
    ignore_defense DECIMAL(5,2) DEFAULT 0, -- % de defesa ignorada
    
    -- COOLDOWN
    cooldown INT DEFAULT 0,
    
    -- ELEMENTO
    element ENUM('physical', 'fire', 'ice', 'lightning', 'holy', 'dark') DEFAULT 'physical',
    elemental_power DECIMAL(5,2) DEFAULT 0,
    
    -- REQUISITOS
    required_level INT DEFAULT 1,
    required_class VARCHAR(50),
    max_level INT DEFAULT 10,
    
    -- EFEITOS DE BUFF/DEBUFF
    buff_stat VARCHAR(50),
    buff_amount DECIMAL(5,2),
    buff_duration INT,
    
    available BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_required_level (required_level)
);

-- Tabela de habilidades aprendidas pelos jogadores
CREATE TABLE user_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    level INT DEFAULT 1,
    experience INT DEFAULT 0,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_skill (user_id, skill_id),
    INDEX idx_user_id (user_id),
    INDEX idx_skill_id (skill_id)
);

-- Battles table
CREATE TABLE battles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    monster_name VARCHAR(100) NOT NULL,
    result ENUM('win', 'lose', 'flee') NOT NULL,
    exp_gained INT DEFAULT 0,
    gold_gained INT DEFAULT 0,
    items_dropped TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_battles (user_id, created_at)
);

-- Chat messages
CREATE TABLE chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    channel VARCHAR(50) DEFAULT 'global',
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_channel (channel, created_at),
    INDEX idx_user_chat (user_id, created_at)
);

-- Daily rewards
CREATE TABLE daily_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    claim_date DATE NOT NULL,
    streak INT DEFAULT 1,
    gold_reward INT NOT NULL,
    credits_reward INT NOT NULL,
    item_reward_id INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_reward_id) REFERENCES items(id),
    UNIQUE KEY unique_daily (user_id, claim_date),
    INDEX idx_user_daily (user_id, claim_date)
);

-- Friends list
CREATE TABLE friends (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (friend_id) REFERENCES users(id),
    UNIQUE KEY unique_friendship (user_id, friend_id),
    INDEX idx_user_friends (user_id, status)
);

-- Guilds
CREATE TABLE guilds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    tag VARCHAR(10) UNIQUE NOT NULL,
    description TEXT NULL,
    leader_id INT NOT NULL,
    level INT DEFAULT 1,
    exp INT DEFAULT 0,
    max_members INT DEFAULT 50,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leader_id) REFERENCES users(id),
    INDEX idx_name (name),
    INDEX idx_leader (leader_id)
);

-- Guild members (CORRIGIDO: rank -> member_rank)
CREATE TABLE guild_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    guild_id INT NOT NULL,
    user_id INT NOT NULL,
    member_rank ENUM('leader', 'officer', 'member', 'recruit') DEFAULT 'recruit',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    contribution INT DEFAULT 0,
    FOREIGN KEY (guild_id) REFERENCES guilds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_member (guild_id, user_id),
    INDEX idx_guild_members (guild_id, member_rank)
);

-- Market (player-to-player trading)
CREATE TABLE market_listings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    price_gold INT NOT NULL,
    price_credits INT DEFAULT 0,
    listed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    sold BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (seller_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_seller (seller_id, sold),
    INDEX idx_market (item_id, price_gold, expires_at)
);

-- Quests
CREATE TABLE quests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('kill', 'collect', 'explore', 'level', 'boss') NOT NULL,
    required_level INT DEFAULT 1,
    required_item_id INT NULL,
    required_item_quantity INT DEFAULT 1,
    required_monster VARCHAR(100) NULL,
    required_monster_count INT DEFAULT 1,
    required_floor INT NULL,
    reward_exp INT NOT NULL,
    reward_gold INT NOT NULL,
    reward_item_id INT NULL,
    reward_item_quantity INT DEFAULT 1,
    repeatable BOOLEAN DEFAULT FALSE,
    daily BOOLEAN DEFAULT FALSE,
    vip_only BOOLEAN DEFAULT FALSE,
    INDEX idx_type (type),
    INDEX idx_required_level (required_level)
);

-- Player quests
CREATE TABLE player_quests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    quest_id INT NOT NULL,
    progress INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    claimed BOOLEAN DEFAULT FALSE,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (quest_id) REFERENCES quests(id),
    UNIQUE KEY unique_quest (user_id, quest_id, completed),
    INDEX idx_user_quests (user_id, completed)
);

-- Character buffs
CREATE TABLE character_buffs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    buff_type VARCHAR(50) NOT NULL,
    buff_value INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_buffs (user_id, expires_at)
);

-- System logs
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_action (action, created_at),
    INDEX idx_user_logs (user_id, created_at)
);

-- Insert initial data

-- Inserir tipos básicos
INSERT INTO item_types (name, slot) VALUES
('Sword', 'weapon'),
('Armor', 'armor'),
('Helmet', 'helmet'),
('Gloves', 'gloves'),
('Boots', 'boots'),
('Ring', 'accessory'),
('Necklace', 'accessory'),
('Potion', 'consumable');

-- Inserir alguns itens de exemplo
INSERT INTO items (name, description, type_id, rarity, floor_available, price, 
                   atk_bonus, def_bonus, crit_bonus, weapon_damage_min, weapon_damage_max) VALUES
('Iron Sword', 'A basic iron sword', 1, 'common', 1, 100, 
 5, 0, 1.0, 8, 12),
('Steel Sword', 'A sturdy steel sword', 1, 'uncommon', 3, 500,
 10, 0, 2.0, 15, 20),
('Leather Armor', 'Basic leather armor', 2, 'common', 1, 80,
 0, 8, 0, 0, 0),
('Chainmail', 'Protective chainmail', 2, 'uncommon', 3, 400,
 0, 15, 0, 0, 0),
('Health Potion', 'Restores 50 HP', 8, 'common', 1, 20,
 0, 0, 0, 0, 0),
('Critical Ring', 'Increases critical chance', 6, 'rare', 5, 1000,
 0, 0, 5.0, 0, 0),
('Dodge Boots', 'Increases dodge chance', 5, 'uncommon', 4, 300,
 0, 5, 0, 0, 0);

-- Inserir habilidades básicas (nova estrutura)
INSERT INTO skills (name, description, type, base_damage, mp_cost, crit_bonus, accuracy, required_level) VALUES
('Slash', 'A basic sword slash', 'attack', 15, 5, 0, 95, 1),
('Heal', 'Restores HP', 'heal', 20, 10, 0, 100, 1),
('Power Strike', 'A powerful strike that deals extra damage', 'attack', 25, 15, 5, 90, 3),
('Defense Boost', 'Increases defense for 3 turns', 'buff', 0, 20, 0, 100, 5),
('Fireball', 'Launches a fireball at the enemy', 'attack', 30, 25, 10, 85, 7),
('Greater Heal', 'Restores a large amount of HP', 'heal', 40, 30, 0, 100, 10),
('Critical Focus', 'Greatly increases critical chance for next attack', 'buff', 0, 35, 0, 100, 12),
('Lightning Strike', 'A lightning-fast attack with high critical chance', 'attack', 35, 40, 15, 80, 15),
('Weaken', 'Reduces enemy defense', 'debuff', 0, 25, 0, 90, 8),
('Dual Slash', 'Two quick slashes in succession', 'attack', 45, 50, 20, 85, 20);

-- Insert VIP plans
INSERT INTO vip_plans (name, description, duration, price_gold, price_credits, price_cash, features) VALUES
('VIP 7 Days', 'Perfect for trying out VIP benefits', 7, 50000, 500, 4.99, '["+30% EXP Gain", "+20% Drop Rate", "Access to VIP Shop", "No Ads"]'),
('VIP 30 Days', 'Best value for dedicated players', 30, 180000, 1800, 14.99, '["+50% EXP Gain", "+30% Drop Rate", "Access to VIP Shop", "VIP Skills", "No Ads", "Priority Support"]'),
('VIP 90 Days', 'Ultimate package for hardcore players', 90, 450000, 4500, 34.99, '["+75% EXP Gain", "+50% Drop Rate", "Access to VIP Shop", "VIP Skills", "Exclusive Items", "Priority Support", "No Ads", "Monthly Gift Box"]');

-- Insert shop items (usando os novos IDs de itens)
INSERT INTO shop_items (item_id, category, price_gold, price_credits, stock, vip_only) VALUES
-- Weapons
(1, 'weapons', 100, 0, -1, FALSE),
(2, 'weapons', 500, 0, -1, FALSE),
-- Armor
(3, 'armor', 80, 0, -1, FALSE),
(4, 'armor', 400, 0, -1, FALSE),
-- Consumables
(5, 'consumables', 20, 0, -1, FALSE),
-- Accessories
(6, 'accessories', 1000, 0, -1, FALSE),
(7, 'accessories', 300, 0, -1, FALSE);

-- Insert quests
INSERT INTO quests (title, description, type, required_level, required_monster, required_monster_count, reward_exp, reward_gold, reward_item_id) VALUES
('First Steps', 'Defeat 5 Frenzy Boars', 'kill', 1, 'Frenzy Boar', 5, 100, 50, 1),
('Gathering Materials', 'Collect 10 Iron Ore', 'collect', 2, NULL, NULL, 150, 75, 5),
('Wolf Hunter', 'Defeat 8 Dire Wolves', 'kill', 3, 'Dire Wolf', 8, 200, 100, 2),
('Exploring Floor 1', 'Reach level 5', 'level', 1, NULL, NULL, 300, 150, 3),
('Kobold Extermination', 'Defeat 15 Kobold Sentinels', 'kill', 5, 'Kobold Sentinel', 15, 400, 200, 4),
('Dragon Slayer', 'Defeat a Lesser Dragon', 'boss', 10, 'Lesser Dragon', 1, 1000, 500, 6);

-- Create admin user (password: admin123)
INSERT INTO users (username, email, password_hash, vip_expire) VALUES 
('admin', 'admin@sao-rpg.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2030-01-01 00:00:00');

INSERT INTO characters (user_id, name, level, gold, credits) VALUES 
(1, 'Administrator', 100, 1000000, 10000);
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

-- Characters table
CREATE TABLE characters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    class VARCHAR(50) DEFAULT 'Swordsman',
    level INT DEFAULT 1,
    exp INT DEFAULT 0,
    current_hp INT DEFAULT 100,
    max_hp INT DEFAULT 100,
    current_mp INT DEFAULT 50,
    max_mp INT DEFAULT 50,
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

-- Items table
CREATE TABLE items (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('weapon', 'armor', 'consumable', 'material', 'skill') NOT NULL,
    category VARCHAR(50) NULL,
    rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
    image VARCHAR(100) DEFAULT 'default.png',
    description TEXT NULL,
    atk INT DEFAULT 0,
    def INT DEFAULT 0,
    hp_bonus INT DEFAULT 0,
    mp_bonus INT DEFAULT 0,
    crit_bonus DECIMAL(5,2) DEFAULT 0,
    agi_bonus INT DEFAULT 0,
    required_level INT DEFAULT 1,
    required_class VARCHAR(50) NULL,
    buy_price INT DEFAULT 0,
    sell_price INT DEFAULT 0,
    vip_only BOOLEAN DEFAULT FALSE,
    drop_rate DECIMAL(5,4) DEFAULT 0.0,
    floor_available INT DEFAULT 1,
    max_stack INT DEFAULT 99,
    effect VARCHAR(50) NULL,
    effect_value INT DEFAULT 0,
    INDEX idx_type (type),
    INDEX idx_rarity (rarity),
    INDEX idx_vip (vip_only)
);

-- Inventory table
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    equipped BOOLEAN DEFAULT FALSE,
    equipped_slot VARCHAR(50) NULL,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id),
    UNIQUE KEY unique_item (user_id, item_id),
    INDEX idx_user_item (user_id, item_id),
    INDEX idx_equipped (equipped)
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

-- Skills table
CREATE TABLE skills (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('attack', 'heal', 'buff', 'debuff') NOT NULL,
    description TEXT NULL,
    damage INT DEFAULT 0,
    heal_amount INT DEFAULT 0,
    mp_cost INT NOT NULL,
    cooldown INT DEFAULT 0,
    effect VARCHAR(50) NULL,
    effect_value INT DEFAULT 0,
    required_level INT DEFAULT 1,
    required_class VARCHAR(50) NULL,
    vip_only BOOLEAN DEFAULT FALSE,
    INDEX idx_type (type),
    INDEX idx_required (required_level)
);

-- Player skills
CREATE TABLE player_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    unlocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (skill_id) REFERENCES skills(id),
    UNIQUE KEY unique_skill (user_id, skill_id)
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

-- Guild members
CREATE TABLE guild_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    guild_id INT NOT NULL,
    user_id INT NOT NULL,
    rank ENUM('leader', 'officer', 'member', 'recruit') DEFAULT 'recruit',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    contribution INT DEFAULT 0,
    FOREIGN KEY (guild_id) REFERENCES guilds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_member (guild_id, user_id),
    INDEX idx_guild_members (guild_id, rank)
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

-- Insert default items
INSERT INTO items (id, name, type, rarity, image, description, atk, def, required_level, buy_price, sell_price, drop_rate) VALUES
-- Weapons
(101, 'Bronze Sword', 'weapon', 'common', 'bronze_sword.png', 'A basic bronze sword', 5, 0, 1, 100, 50, 0.3),
(102, 'Iron Sword', 'weapon', 'uncommon', 'iron_sword.png', 'A sturdy iron sword', 10, 0, 5, 300, 150, 0.15),
(103, 'Steel Sword', 'weapon', 'rare', 'steel_sword.png', 'A well-crafted steel sword', 20, 2, 10, 800, 400, 0.08),
(104, 'Crystal Sword', 'weapon', 'epic', 'crystal_sword.png', 'A sword made of magical crystal', 35, 5, 20, 2000, 1000, 0.04),
(105, 'Dragon Slayer', 'weapon', 'legendary', 'dragon_slayer.png', 'A sword that can slay dragons', 50, 10, 30, 5000, 2500, 0.01),

-- Armor
(201, 'Leather Armor', 'armor', 'common', 'leather_armor.png', 'Basic leather protection', 0, 5, 1, 150, 75, 0.25),
(202, 'Chainmail', 'armor', 'uncommon', 'chainmail.png', 'Interlocking metal rings', 0, 12, 5, 400, 200, 0.12),
(203, 'Plate Armor', 'armor', 'rare', 'plate_armor.png', 'Heavy plate armor', 0, 25, 10, 1000, 500, 0.06),
(204, 'Magic Robes', 'armor', 'epic', 'magic_robes.png', 'Robes infused with magic', 5, 20, 15, 2500, 1250, 0.03),
(205, 'Dragon Scale Armor', 'armor', 'legendary', 'dragon_scale.png', 'Armor made from dragon scales', 10, 40, 25, 6000, 3000, 0.01),

-- Consumables
(301, 'Small HP Potion', 'consumable', 'common', 'small_hp_potion.png', 'Restores 50 HP', 0, 0, 1, 50, 25, 0.4),
(302, 'Medium HP Potion', 'consumable', 'uncommon', 'medium_hp_potion.png', 'Restores 100 HP', 0, 0, 5, 100, 50, 0.2),
(303, 'Large HP Potion', 'consumable', 'rare', 'large_hp_potion.png', 'Restores 200 HP', 0, 0, 10, 200, 100, 0.1),
(304, 'Small MP Potion', 'consumable', 'common', 'small_mp_potion.png', 'Restores 30 MP', 0, 0, 1, 50, 25, 0.4),
(305, 'Medium MP Potion', 'consumable', 'uncommon', 'medium_mp_potion.png', 'Restores 60 MP', 0, 0, 5, 100, 50, 0.2),

-- Materials
(401, 'Iron Ore', 'material', 'common', 'iron_ore.png', 'Used for crafting', 0, 0, 1, 10, 5, 0.5),
(402, 'Silver Ore', 'material', 'uncommon', 'silver_ore.png', 'Used for crafting', 0, 0, 5, 25, 12, 0.3),
(403, 'Gold Ore', 'material', 'rare', 'gold_ore.png', 'Used for crafting', 0, 0, 10, 50, 25, 0.15),
(404, 'Diamond', 'material', 'epic', 'diamond.png', 'Rare gemstone', 0, 0, 15, 200, 100, 0.05),
(405, 'Dragon Scale', 'material', 'legendary', 'dragon_scale_mat.png', 'Scale from a dragon', 0, 0, 20, 500, 250, 0.01),

-- VIP Items
(1001, 'Elucidator', 'weapon', 'legendary', 'elucidator.png', 'The black sword of the Black Swordsman', 50, 15, 20, 50000, 25000, 0.005),
(1002, 'Dark Repulser', 'weapon', 'legendary', 'dark_repulser.png', 'A sword that repels darkness', 45, 20, 20, 45000, 22500, 0.005),
(1003, 'Coat of Midnight', 'armor', 'epic', 'midnight_coat.png', 'A black coat that enhances agility', 10, 30, 15, 30000, 15000, 0.01),
(1004, 'Lambent Light', 'weapon', 'legendary', 'lambent_light.png', 'Asuna rapier', 40, 10, 25, 40000, 20000, 0.005);

-- Insert VIP plans
INSERT INTO vip_plans (name, description, duration, price_gold, price_credits, price_cash, features) VALUES
('VIP 7 Days', 'Perfect for trying out VIP benefits', 7, 50000, 500, 4.99, '["+30% EXP Gain", "+20% Drop Rate", "Access to VIP Shop", "No Ads"]'),
('VIP 30 Days', 'Best value for dedicated players', 30, 180000, 1800, 14.99, '["+50% EXP Gain", "+30% Drop Rate", "Access to VIP Shop", "VIP Skills", "No Ads", "Priority Support"]'),
('VIP 90 Days', 'Ultimate package for hardcore players', 90, 450000, 4500, 34.99, '["+75% EXP Gain", "+50% Drop Rate", "Access to VIP Shop", "VIP Skills", "Exclusive Items", "Priority Support", "No Ads", "Monthly Gift Box"]');

-- Insert skills
INSERT INTO skills (id, name, type, description, damage, mp_cost, cooldown, required_level) VALUES
(1, 'Slash', 'attack', 'A basic sword slash', 15, 5, 0, 1),
(2, 'Heavy Strike', 'attack', 'A powerful strike', 25, 10, 2, 5),
(3, 'Heal', 'heal', 'Restores HP', 0, 15, 3, 10),
(4, 'Double Slash', 'attack', 'Two rapid strikes', 20, 12, 2, 15),
(5, 'Power Attack', 'attack', 'A devastating attack', 40, 25, 5, 20),
(6, 'Healing Circle', 'heal', 'Heals more HP', 0, 30, 8, 25),
(7, 'Sword Skills', 'attack', 'Advanced sword techniques', 60, 40, 10, 30),
(8, 'Dual Blades', 'attack', 'Unleash a flurry of attacks', 80, 50, 15, 35);

-- Insert shop items
INSERT INTO shop_items (item_id, category, price_gold, price_credits, stock, vip_only) VALUES
-- Weapons
(101, 'weapons', 100, 0, -1, FALSE),
(102, 'weapons', 300, 0, -1, FALSE),
(103, 'weapons', 800, 0, -1, FALSE),
(104, 'weapons', 2000, 0, -1, FALSE),
(105, 'weapons', 5000, 0, -1, FALSE),

-- Armor
(201, 'armor', 150, 0, -1, FALSE),
(202, 'armor', 400, 0, -1, FALSE),
(203, 'armor', 1000, 0, -1, FALSE),
(204, 'armor', 2500, 0, -1, FALSE),
(205, 'armor', 6000, 0, -1, FALSE),

-- Consumables
(301, 'consumables', 50, 0, -1, FALSE),
(302, 'consumables', 100, 0, -1, FALSE),
(303, 'consumables', 200, 0, -1, FALSE),
(304, 'consumables', 50, 0, -1, FALSE),
(305, 'consumables', 100, 0, -1, FALSE),

-- VIP Items
(1001, 'vip', 0, 5000, -1, TRUE),
(1002, 'vip', 0, 4500, -1, TRUE),
(1003, 'vip', 0, 3000, -1, TRUE),
(1004, 'vip', 0, 4000, -1, TRUE);

-- Insert quests
INSERT INTO quests (title, description, type, required_level, required_monster, required_monster_count, reward_exp, reward_gold, reward_item_id) VALUES
('First Steps', 'Defeat 5 Frenzy Boars', 'kill', 1, 'Frenzy Boar', 5, 100, 50, 101),
('Gathering Materials', 'Collect 10 Iron Ore', 'collect', 2, NULL, NULL, 150, 75, 301),
('Wolf Hunter', 'Defeat 8 Dire Wolves', 'kill', 3, 'Dire Wolf', 8, 200, 100, 102),
('Exploring Floor 1', 'Reach level 5', 'level', 1, NULL, NULL, 300, 150, 201),
('Kobold Extermination', 'Defeat 15 Kobold Sentinels', 'kill', 5, 'Kobold Sentinel', 15, 400, 200, 103),
('Dragon Slayer', 'Defeat a Lesser Dragon', 'boss', 10, 'Lesser Dragon', 1, 1000, 500, 105);

-- Create admin user (password: admin123)
INSERT INTO users (username, email, password_hash, vip_expire) VALUES 
('admin', 'admin@sao-rpg.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2030-01-01 00:00:00');

INSERT INTO characters (user_id, name, level, gold, credits) VALUES 
(1, 'Administrator', 100, 1000000, 10000);
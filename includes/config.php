<?php
// SAO RPG Configuration
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'sao_rpg');
define('DB_USER', 'root');
define('DB_PASS', '');

// Game Settings
define('SITE_NAME', 'SAO RPG - Aincrad Online');
define('SITE_URL', 'http://localhost/sao-rpg');
define('SITE_ROOT', dirname(__DIR__));

// Game Mechanics
define('MAX_LEVEL', 100);
define('BASE_HP', 100);
define('BASE_MP', 50);
define('BASE_ATK', 10);
define('BASE_DEF', 5);
define('BASE_AGI', 8);
define('BASE_CRIT', 5.00);

// Experience System
define('BASE_EXP', 100);
define('EXP_MULTIPLIER', 1.5);

// Energy System
define('MAX_ENERGY', 60);
define('ENERGY_REGEN_TIME', 240); // 4 minutes in seconds
define('ENERGY_PER_BATTLE', 1);

// Drop Rates
define('DROP_RATE_COMMON', 0.30);
define('DROP_RATE_UNCOMMON', 0.15);
define('DROP_RATE_RARE', 0.08);
define('DROP_RATE_EPIC', 0.04);
define('DROP_RATE_LEGENDARY', 0.01);
define('DROP_RATE_VIP_MULTIPLIER', 1.3);

// VIP Benefits
define('VIP_EXP_MULTIPLIER', 1.5);
define('VIP_GOLD_MULTIPLIER', 1.2);
define('VIP_DROP_MULTIPLIER', 1.3);

// Security
define('SALT', 'sao_rpg_security_salt_2024_aincrad');
define('TOKEN_EXPIRE', 3600); // 1 hour

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Error Reporting (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include other required files
require_once 'database.php';
require_once 'functions.php';
?>
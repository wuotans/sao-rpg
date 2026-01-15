<?php
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        $db = Database::getInstance();
        
        // Check if username exists
        if ($db->count('users', 'username = ?', [$username]) > 0) {
            $error = 'Username already taken';
        }
        // Check if email exists
        elseif ($db->count('users', 'email = ?', [$email]) > 0) {
            $error = 'Email already registered';
        } else {
            // Create user
            $user_id = $db->insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => hashPassword($password),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Create character
            $db->insert('characters', [
                'user_id' => $user_id,
                'name' => $username,
                'class' => 'Swordsman',
                'level' => 1,
                'exp' => 0,
                'max_exp' => getExpForLevel(1),
                'hp' => BASE_HP,
                'max_hp' => BASE_HP,
                'current_hp' => BASE_HP,
                'mp' => BASE_MP,
                'max_mp' => BASE_MP,
                'current_mp' => BASE_MP,
                'atk' => BASE_ATK,
                'def' => BASE_DEF,
                'agi' => BASE_AGI,
                'crit' => BASE_CRIT,
                'gold' => 100, // Starting gold
                'credits' => 10, // Starting credits
                'current_floor' => 1,
                'energy' => MAX_ENERGY,
                'energy_regen' => date('Y-m-d H:i:s'),
                'avatar' => 'default.png',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Give starting items
            $starting_items = [101, 102, 103]; // Bronze Sword, Leather Armor, Small HP Potion
            foreach ($starting_items as $item_id) {
                $db->insert('inventory', [
                    'user_id' => $user_id,
                    'item_id' => $item_id,
                    'quantity' => 1
                ]);
            }
            
            // Auto login
            $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
            initSession($user);
            
            $success = 'Account created successfully! Redirecting...';
            echo "<script>setTimeout(function() { window.location.href = 'index.php'; }, 2000);</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/sao-theme.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .register-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a2a 0%, #1a1a4a 100%);
        }
        
        .register-box {
            background: rgba(26, 35, 126, 0.9);
            border: 2px solid #00b0ff;
            border-radius: 10px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 0 30px rgba(0, 176, 255, 0.3);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: #00b0ff;
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #90caf9;
        }
        
        .starting-bonus {
            background: rgba(255, 215, 64, 0.1);
            border: 1px solid #ffd740;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .starting-bonus h4 {
            color: #ffd740;
            margin-top: 0;
        }
        
        .bonus-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .bonus-item {
            display: flex;
            align-items: center;
            color: #fff;
        }
        
        .bonus-item i {
            color: #69f0ae;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="register-header">
                <h2><i class="fas fa-user-plus"></i> CREATE ACCOUNT</h2>
                <p>Start your adventure in Aincrad</p>
            </div>
            
            <div class="starting-bonus">
                <h4><i class="fas fa-gift"></i> Starting Bonus</h4>
                <div class="bonus-list">
                    <div class="bonus-item">
                        <i class="fas fa-coins"></i> 100 Gold
                    </div>
                    <div class="bonus-item">
                        <i class="fas fa-gem"></i> 10 Credits
                    </div>
                    <div class="bonus-item">
                        <i class="fas fa-swords"></i> Bronze Sword
                    </div>
                    <div class="bonus-item">
                        <i class="fas fa-shield-alt"></i> Leather Armor
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="input-group">
                    <label for="username"><i class="fas fa-user"></i> Username *</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <small>3-20 characters, letters and numbers only</small>
                </div>
                
                <div class="input-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="input-group">
                    <label for="password"><i class="fas fa-lock"></i> Password *</label>
                    <input type="password" id="password" name="password" required>
                    <small>Minimum 6 characters</small>
                </div>
                
                <div class="input-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="input-group">
                    <label>
                        <input type="checkbox" name="terms" required>
                        I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-play-circle"></i> START ADVENTURE
                </button>
            </form>
            
            <div class="login-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
                <p><a href="index.php"><i class="fas fa-home"></i> Return to Home</a></p>
            </div>
        </div>
    </div>
</body>
</html>
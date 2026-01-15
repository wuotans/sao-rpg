<?php
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = Database::getInstance();
        
        $user = $db->fetch("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
        
        if ($user && verifyPassword($password, $user['password_hash'])) {
            initSession($user);
            
            // Update last login
            $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
            
            redirect('index.php');
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/sao-theme.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a2a 0%, #1a1a4a 100%);
        }
        
        .login-box {
            background: rgba(26, 35, 126, 0.9);
            border: 2px solid #00b0ff;
            border-radius: 10px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 0 30px rgba(0, 176, 255, 0.3);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #00b0ff;
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #90caf9;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            color: #bbdefb;
            margin-bottom: 5px;
        }
        
        .input-group input {
            width: 100%;
            padding: 12px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #00b0ff;
            border-radius: 5px;
            color: #fff;
            font-size: 16px;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #2979ff;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.5);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #00b0ff, #2979ff);
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #2979ff, #2962ff);
            box-shadow: 0 0 15px rgba(0, 176, 255, 0.7);
        }
        
        .login-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-links a {
            color: #90caf9;
            text-decoration: none;
        }
        
        .login-links a:hover {
            color: #00b0ff;
            text-decoration: underline;
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #ffcdd2;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
            color: #c8e6c9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h2><i class="fas fa-sign-in-alt"></i> LOGIN TO AINCRAD</h2>
                <p>Enter your credentials to continue your journey</p>
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
                    <label for="username"><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="input-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-play-circle"></i> CONNECT TO GAME
                </button>
            </form>
            
            <div class="login-links">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
                <p><a href="forgot-password.php">Forgot your password?</a></p>
                <p><a href="index.php"><i class="fas fa-home"></i> Return to Home</a></p>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();

// Verificar se já está instalado
if(file_exists('includes/config.php')) {
    die('O sistema já foi instalado!');
}

// Processar instalação
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'];
    $dbname = $_POST['dbname'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $site_url = $_POST['site_url'];
    $admin_username = $_POST['admin_username'];
    $admin_email = $_POST['admin_email'];
    $admin_password = $_POST['admin_password'];
    
    try {
        // Testar conexão
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Criar banco de dados e tabelas usando seu arquivo
        $sql = file_get_contents('database.sql');
        
        // Separar as queries
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($queries as $query) {
            if (!empty($query)) {
                $pdo->exec($query);
            }
        }
        
        // Criar arquivo de configuração
        $config = "<?php
// Database Configuration
define('DB_HOST', '" . addslashes($host) . "');
define('DB_NAME', '" . addslashes($dbname) . "');
define('DB_USER', '" . addslashes($username) . "');
define('DB_PASS', '" . addslashes($password) . "');

// Site Configuration
define('SITE_URL', '" . addslashes($site_url) . "');
define('SITE_NAME', 'SAO RPG');
define('SITE_DESC', 'Sword Art Online RPG Game');

// Game Configuration
define('BASE_EXP_MULTIPLIER', 100);
define('BASE_GOLD_MULTIPLIER', 10);
define('DROP_RATE_NORMAL', 0.3); // 30%
define('DROP_RATE_VIP', 0.5); // 50% para VIP
define('DROP_RATE_EVENT', 0.8); // 80% durante eventos

// VIP Configuration
define('VIP_EXP_BONUS', 1.5); // +50% EXP
define('VIP_GOLD_BONUS', 1.3); // +30% Gold
define('VIP_DROP_BONUS', 1.2); // +20% Drop Rate

// Energy/Stamina System
define('MAX_ENERGY', 60);
define('ENERGY_REGEN_TIME', 4); // minutos para regenerar 1 energia
define('ENERGY_REGEN_VIP', 2); // minutos para regenerar 1 energia (VIP)

// Security
define('SALT', '" . bin2hex(random_bytes(32)) . "');
define('SESSION_TIMEOUT', 3600); // 1 hora

// Debug Mode (false in production)
define('DEBUG_MODE', true);

// Payment Gateways (configure later)
define('PAGSEGURO_EMAIL', '');
define('PAGSEGURO_TOKEN', '');
define('PAGSEGURO_SANDBOX', true);

define('MERCADOPAGO_TOKEN', '');
define('MERCADOPAGO_SANDBOX', true);
?>
";
        
        file_put_contents('includes/config.php', $config);
        
        // Atualizar senha do admin para a informada
        $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
        $stmt->execute([$admin_password_hash]);
        
        // Atualizar email do admin se fornecido
        if($admin_email) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE username = 'admin'");
            $stmt->execute([$admin_email]);
        }
        
        // Criar diretórios necessários
        $dirs = [
            'assets/avatars',
            'assets/items',
            'assets/icons',
            'assets/skills',
            'uploads/items',
            'uploads/avatars',
            'css',
            'js'
        ];
        
        foreach($dirs as $dir) {
            if(!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // Copiar arquivos padrão se existirem
        $default_files = [
            'assets/default_avatars/default.png' => 'assets/avatars/default.png',
            'assets/default_items/bronze_sword.png' => 'assets/items/bronze_sword.png',
            'assets/default_items/iron_sword.png' => 'assets/items/iron_sword.png'
        ];
        
        foreach($default_files as $src => $dest) {
            if(file_exists($src) && !file_exists($dest)) {
                copy($src, $dest);
            }
        }
        
        echo "<div style='padding: 20px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px;'>
                <h3>✅ Instalação concluída com sucesso!</h3>
                <p>O sistema SAO RPG foi instalado corretamente.</p>
                <p><strong>URL do Site:</strong> $site_url</p>
                <p><strong>Usuário Admin:</strong> $admin_username</p>
                <p><strong>Senha:</strong> [A senha que você definiu]</p>
                <p><strong>Próximos passos:</strong></p>
                <ol>
                    <li>Delete o arquivo install.php por segurança</li>
                    <li>Acesse <a href='$site_url'>$site_url</a></li>
                    <li>Configure os gateways de pagamento em includes/config.php</li>
                </ol>
                <a href='index.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Acessar o Site</a>
              </div>";
        
        // Não redirecionar automaticamente para mostrar as informações
        exit;
        
    } catch(PDOException $e) {
        $error = "Erro na instalação: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - SAO RPG</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Arial', sans-serif; 
            background: linear-gradient(135deg, #1a237e 0%, #311b92 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 3px solid #00b0ff;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #1a237e;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 0 #00b0ff;
        }
        .logo p {
            color: #666;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #00b0ff;
            outline: none;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .install-btn {
            background: linear-gradient(135deg, #00b0ff, #2979ff);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s;
        }
        .install-btn:hover {
            transform: translateY(-2px);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .requirements {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .requirements h3 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        .requirements ul {
            list-style: none;
        }
        .requirements li {
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
        }
        .requirements li:before {
            content: '✓';
            color: #4caf50;
            position: absolute;
            left: 0;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="logo">
            <h1>SAO RPG</h1>
            <p>Sword Art Online RPG - Instalação</p>
        </div>
        
        <?php if(isset($error)): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="requirements">
            <h3>Requisitos do Sistema</h3>
            <ul>
                <li>PHP 7.4 ou superior</li>
                <li>MySQL 5.7 ou MariaDB 10.3</li>
                <li>PDO MySQL Extension</li>
                <li>GD Library (para manipulação de imagens)</li>
                <li>JSON Support</li>
                <li>CURL (para gateways de pagamento)</li>
            </ul>
        </div>
        
        <form method="POST" action="">
            <h3 style="color: #1a237e; margin-bottom: 20px; border-bottom: 2px solid #00b0ff; padding-bottom: 10px;">
                Configuração do Banco de Dados
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="host">Host do MySQL:</label>
                    <input type="text" id="host" name="host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label for="dbname">Nome do Banco:</label>
                    <input type="text" id="dbname" name="dbname" value="sao_rpg" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Usuário MySQL:</label>
                    <input type="text" id="username" name="username" value="root" required>
                </div>
                <div class="form-group">
                    <label for="password">Senha MySQL:</label>
                    <input type="password" id="password" name="password" value="">
                </div>
            </div>
            
            <h3 style="color: #1a237e; margin: 30px 0 20px 0; border-bottom: 2px solid #00b0ff; padding-bottom: 10px;">
                Configuração do Site
            </h3>
            
            <div class="form-group">
                <label for="site_url">URL do Site (ex: http://localhost/sao-rpg):</label>
                <input type="text" id="site_url" name="site_url" value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); ?>" required>
            </div>
            
            <h3 style="color: #1a237e; margin: 30px 0 20px 0; border-bottom: 2px solid #00b0ff; padding-bottom: 10px;">
                Conta Administrativa
            </h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="admin_username">Nome de Usuário:</label>
                    <input type="text" id="admin_username" name="admin_username" value="admin" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Email:</label>
                    <input type="email" id="admin_email" name="admin_email" value="admin@localhost" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="admin_password">Senha:</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Senha:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required oninput="checkPassword()">
                </div>
            </div>
            
            <div id="password-error" style="color: #f44336; display: none; margin-bottom: 20px;">
                As senhas não coincidem!
            </div>
            
            <button type="submit" class="install-btn" onclick="return validateForm()">
                Instalar SAO RPG
            </button>
        </form>
    </div>
    
    <script>
        function checkPassword() {
            var password = document.getElementById('admin_password').value;
            var confirm = document.getElementById('confirm_password').value;
            var error = document.getElementById('password-error');
            
            if(password !== confirm) {
                error.style.display = 'block';
                return false;
            } else {
                error.style.display = 'none';
                return true;
            }
        }
        
        function validateForm() {
            if(!checkPassword()) {
                alert('Por favor, confirme sua senha corretamente.');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
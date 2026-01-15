<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'send':
        sendMessage();
        break;
    case 'get_messages':
        getMessages();
        break;
    case 'get_channels':
        getChannels();
        break;
    case 'get_online_users':
        getOnlineUsers();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function sendMessage() {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    if ($user_id == 0) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $message = trim($_POST['message'] ?? '');
    $channel = $_POST['channel'] ?? 'global';
    
    if (empty($message)) {
        echo json_encode(['error' => 'Message cannot be empty']);
        return;
    }
    
    if (strlen($message) > 500) {
        echo json_encode(['error' => 'Message too long']);
        return;
    }
    
    // Check for spam
    $last_message = $db->fetch(
        "SELECT created_at FROM chat_messages 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 1",
        [$user_id]
    );
    
    if ($last_message) {
        $last_time = strtotime($last_message['created_at']);
        $now = time();
        
        if ($now - $last_time < 3) { // 3 second cooldown
            echo json_encode(['error' => 'Please wait before sending another message']);
            return;
        }
    }
    
    // Check for forbidden words
    $forbidden_words = ['hack', 'cheat', 'exploit', 'admin', 'mod', 'fuck', 'shit'];
    $lower_message = strtolower($message);
    
    foreach ($forbidden_words as $word) {
        if (strpos($lower_message, $word) !== false) {
            echo json_encode(['error' => 'Message contains forbidden content']);
            return;
        }
    }
    
    // Insert message
    $message_id = $db->insert('chat_messages', [
        'user_id' => $user_id,
        'channel' => $channel,
        'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Get user info for response
    $user = $db->fetch("
        SELECT u.username, c.level, c.class, c.avatar 
        FROM users u 
        JOIN characters c ON u.id = c.user_id 
        WHERE u.id = ?
    ", [$user_id]);
    
    // Format message HTML
    $time = date('H:i');
    $message_html = '
        <div class="chat-message">
            <div class="message-header">
                <span class="message-user">
                    <img src="images/avatars/' . $user['avatar'] . '" class="message-avatar">
                    ' . htmlspecialchars($user['username']) . '
                    <span class="message-level">Lv. ' . $user['level'] . '</span>
                </span>
                <span class="message-time">' . $time . '</span>
            </div>
            <div class="message-content">' . nl2br(htmlspecialchars($message)) . '</div>
        </div>
    ';
    
    echo json_encode([
        'success' => true,
        'message_id' => $message_id,
        'message_html' => $message_html,
        'user' => [
            'username' => $user['username'],
            'level' => $user['level'],
            'class' => $user['class'],
            'avatar' => $user['avatar']
        ]
    ]);
}

function getMessages() {
    global $db;
    
    $channel = $_GET['channel'] ?? 'global';
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    // Get last 50 messages for the channel
    $messages = $db->fetchAll("
        SELECT 
            cm.*,
            u.username,
            c.level,
            c.class,
            c.avatar
        FROM chat_messages cm
        JOIN users u ON cm.user_id = u.id
        JOIN characters c ON u.id = c.user_id
        WHERE cm.channel = ?
        " . ($last_id > 0 ? "AND cm.id > ?" : "") . "
        ORDER BY cm.created_at DESC
        LIMIT 50
    ", $last_id > 0 ? [$channel, $last_id] : [$channel]);
    
    // Reverse to show oldest first
    $messages = array_reverse($messages);
    
    $html = '';
    foreach ($messages as $message) {
        $time = date('H:i', strtotime($message['created_at']));
        
        // Check if message is from system
        if ($message['user_id'] == 0) {
            $html .= '
                <div class="chat-message system">
                    <div class="message-content">' . $message['message'] . '</div>
                    <div class="message-time">' . $time . '</div>
                </div>
            ';
        } else {
            $html .= '
                <div class="chat-message">
                    <div class="message-header">
                        <span class="message-user">
                            <img src="images/avatars/' . $message['avatar'] . '" class="message-avatar">
                            ' . htmlspecialchars($message['username']) . '
                            <span class="message-level">Lv. ' . $message['level'] . '</span>
                        </span>
                        <span class="message-time">' . $time . '</span>
                    </div>
                    <div class="message-content">' . nl2br(htmlspecialchars($message['message'])) . '</div>
                </div>
            ';
        }
    }
    
    // Get last message ID for polling
    $last_message_id = !empty($messages) ? end($messages)['id'] : $last_id;
    
    echo json_encode([
        'messages' => $html,
        'last_id' => $last_message_id,
        'count' => count($messages)
    ]);
}

function getChannels() {
    $channels = [
        ['id' => 'global', 'name' => 'Global Chat', 'icon' => 'fa-globe', 'users' => rand(100, 500)],
        ['id' => 'trade', 'name' => 'Trade', 'icon' => 'fa-exchange-alt', 'users' => rand(50, 200)],
        ['id' => 'guild', 'name' => 'Guild', 'icon' => 'fa-users', 'users' => rand(20, 100)],
        ['id' => 'help', 'name' => 'Help', 'icon' => 'fa-question-circle', 'users' => rand(10, 50)],
        ['id' => 'offtopic', 'name' => 'Off-Topic', 'icon' => 'fa-comments', 'users' => rand(30, 150)]
    ];
    
    echo json_encode($channels);
}

function getOnlineUsers() {
    global $db;
    
    // Get users active in last 5 minutes
    $five_minutes_ago = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    $online_users = $db->fetchAll("
        SELECT 
            u.username,
            c.level,
            c.class,
            c.avatar,
            u.last_login
        FROM users u
        JOIN characters c ON u.id = c.user_id
        WHERE u.last_login > ?
        ORDER BY c.level DESC
        LIMIT 50
    ", [$five_minutes_ago]);
    
    $html = '<div class="online-count">' . count($online_users) . ' players online</div>';
    
    foreach ($online_users as $user) {
        $time_ago = timeAgo($user['last_login']);
        
        $html .= '
            <div class="online-user">
                <img src="images/avatars/' . $user['avatar'] . '" class="user-avatar">
                <div class="user-info">
                    <div class="user-name">' . htmlspecialchars($user['username']) . '</div>
                    <div class="user-details">
                        Lv. ' . $user['level'] . ' ' . $user['class'] . '
                        <span class="user-status">• ' . $time_ago . '</span>
                    </div>
                </div>
            </div>
        ';
    }
    
    echo $html;
}
?>
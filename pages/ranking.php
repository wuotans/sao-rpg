<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

// Tipos de ranking disponíveis
$ranking_types = [
    'level' => 'Nível',
    'pvp_wins' => 'Vitórias PvP',
    'boss_kills' => 'Chefes Derrotados',
    'col' => 'Col (Dinheiro)',
    'total_damage' => 'Dano Total'
];

$current_type = isset($_GET['type']) && array_key_exists($_GET['type'], $ranking_types) 
    ? $_GET['type'] 
    : 'level';

// Buscar ranking
switch($current_type) {
    case 'pvp_wins':
        $order_by = 'pvp_wins DESC, level DESC';
        break;
    case 'boss_kills':
        $order_by = 'boss_kills DESC, level DESC';
        break;
    case 'col':
        $order_by = 'col DESC, level DESC';
        break;
    case 'total_damage':
        $order_by = 'total_damage DESC, level DESC';
        break;
    default:
        $order_by = 'level DESC, experience DESC';
        break;
}

$stmt = $pdo->prepare("SELECT *, 
    (SELECT COUNT(*) FROM battles WHERE user_id = users.id AND result = 'win' AND type = 'pvp') as pvp_wins,
    (SELECT COUNT(*) FROM boss_battles WHERE user_id = users.id AND result = 'win') as boss_kills,
    (SELECT SUM(damage_dealt) FROM battles WHERE user_id = users.id) as total_damage
    FROM users 
    WHERE banned = 0 
    ORDER BY $order_by 
    LIMIT 100");
$stmt->execute();
$ranking = $stmt->fetchAll();

// Verificar posição do usuário atual
$user_id = $_SESSION['user_id'];
$user_position = null;

foreach($ranking as $index => $row) {
    if($row['id'] == $user_id) {
        $user_position = $index + 1;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ranking - SAO RPG</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/ranking.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="ranking-header">
            <h1>🏆 Ranking de Jogadores</h1>
            <div class="ranking-filters">
                <?php foreach($ranking_types as $type => $name): ?>
                    <a href="?type=<?php echo $type; ?>" 
                       class="filter-btn <?php echo $current_type == $type ? 'active' : ''; ?>">
                        <?php echo $name; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if($user_position): ?>
                <div class="user-rank">
                    <span>Sua posição: <strong>#<?php echo $user_position; ?></strong></span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="ranking-table-container">
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th width="60">Posição</th>
                        <th>Jogador</th>
                        <th>Nível</th>
                        <th>Guilda</th>
                        <th>Vitórias PvP</th>
                        <th>Chefes</th>
                        <th>Col</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ranking as $index => $player): ?>
                        <?php
                        $position = $index + 1;
                        $rank_class = '';
                        if($position == 1) $rank_class = 'first';
                        elseif($position == 2) $rank_class = 'second';
                        elseif($position == 3) $rank_class = 'third';
                        ?>
                        
                        <tr class="<?php echo $rank_class; ?> <?php echo $player['id'] == $user_id ? 'current-user' : ''; ?>">
                            <td>
                                <div class="rank-position">
                                    <?php if($position <= 3): ?>
                                        <span class="medal">🥇</span>
                                    <?php else: ?>
                                        <span class="position-number">#<?php echo $position; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="player-info">
                                    <div class="player-avatar">
                                        <img src="../assets/avatars/<?php echo htmlspecialchars($player['avatar']); ?>.png" 
                                             alt="<?php echo htmlspecialchars($player['username']); ?>">
                                        <?php if($player['vip_status']): ?>
                                            <span class="vip-badge">VIP</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="player-details">
                                        <a href="profile.php?id=<?php echo $player['id']; ?>" class="player-name">
                                            <?php echo htmlspecialchars($player['username']); ?>
                                        </a>
                                        <div class="player-class">
                                            <?php 
                                            $classes = [
                                                1 => 'Espadachim',
                                                2 => 'Lanceiro',
                                                3 => 'Dual Wielder',
                                                4 => 'Assassin',
                                                5 => 'Healer'
                                            ];
                                            echo $classes[$player['class_id']] ?? 'Aventureiro';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="level-display">
                                    <span class="level-number"><?php echo $player['level']; ?></span>
                                    <div class="level-progress">
                                        <div class="progress-bar">
                                            <?php
                                            $exp_needed = $player['level'] * 100 + ($player['level'] * 50);
                                            $exp_percent = min(100, ($player['experience'] / $exp_needed) * 100);
                                            ?>
                                            <div class="progress-fill" style="width: <?php echo $exp_percent; ?>%"></div>
                                        </div>
                                        <small><?php echo floor($exp_percent); ?>%</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($player['guild_id']): ?>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT name FROM guilds WHERE id = ?");
                                    $stmt->execute([$player['guild_id']]);
                                    $guild = $stmt->fetch();
                                    ?>
                                    <span class="guild-name"><?php echo htmlspecialchars($guild['name'] ?? ''); ?></span>
                                <?php else: ?>
                                    <span class="no-guild">Sem Guilda</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="stat-value">
                                    <span class="stat-number"><?php echo $player['pvp_wins'] ?? 0; ?></span>
                                    <small>vitórias</small>
                                </div>
                            </td>
                            <td>
                                <div class="stat-value">
                                    <span class="stat-number"><?php echo $player['boss_kills'] ?? 0; ?></span>
                                    <small>derrotados</small>
                                </div>
                            </td>
                            <td>
                                <div class="col-amount">
                                    <img src="../assets/icons/col.png" alt="Col">
                                    <span><?php echo number_format($player['col'], 0, ',', '.'); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $last_active = strtotime($player['last_active']);
                                $now = time();
                                $diff = $now - $last_active;
                                
                                if($diff < 300): // 5 minutos
                                    $status = 'online';
                                    $status_text = 'Online';
                                    $status_class = 'status-online';
                                elseif($diff < 3600): // 1 hora
                                    $status = 'away';
                                    $status_text = 'Ausente';
                                    $status_class = 'status-away';
                                else:
                                    $status = 'offline';
                                    $status_text = 'Offline';
                                    $status_class = 'status-offline';
                                endif;
                                ?>
                                
                                <div class="player-status <?php echo $status_class; ?>">
                                    <span class="status-dot"></span>
                                    <span class="status-text"><?php echo $status_text; ?></span>
                                    <?php if($diff >= 3600): ?>
                                        <small><?php echo floor($diff / 3600); ?>h</small>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="ranking-stats">
            <div class="stat-card">
                <h3>🏅 Top 3</h3>
                <div class="top-players">
                    <?php for($i = 0; $i < 3 && $i < count($ranking); $i++): ?>
                        <div class="top-player">
                            <div class="top-avatar">
                                <img src="../assets/avatars/<?php echo htmlspecialchars($ranking[$i]['avatar']); ?>.png" 
                                     alt="<?php echo htmlspecialchars($ranking[$i]['username']); ?>">
                                <span class="top-medal">
                                    <?php if($i == 0) echo '🥇';
                                    elseif($i == 1) echo '🥈';
                                    else echo '🥉'; ?>
                                </span>
                            </div>
                            <div class="top-info">
                                <h4><?php echo htmlspecialchars($ranking[$i]['username']); ?></h4>
                                <p>Nível <?php echo $ranking[$i]['level']; ?></p>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h3>📊 Estatísticas do Ranking</h3>
                <ul class="stats-list">
                    <li>
                        <span>Total de Jogadores:</span>
                        <strong>
                            <?php 
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE banned = 0");
                            echo $stmt->fetch()['total'];
                            ?>
                        </strong>
                    </li>
                    <li>
                        <span>Jogadores VIP:</span>
                        <strong>
                            <?php 
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE vip_status = 1 AND banned = 0");
                            echo $stmt->fetch()['total'];
                            ?>
                        </strong>
                    </li>
                    <li>
                        <span>Nível Médio:</span>
                        <strong>
                            <?php 
                            $stmt = $pdo->query("SELECT AVG(level) as avg_level FROM users WHERE banned = 0");
                            echo floor($stmt->fetch()['avg_level'] ?? 1);
                            ?>
                        </strong>
                    </li>
                    <li>
                        <span>Maior Nível:</span>
                        <strong><?php echo $ranking[0]['level'] ?? 1; ?></strong>
                    </li>
                </ul>
            </div>
            
            <div class="stat-card">
                <h3>🎯 Recompensas Diárias</h3>
                <p>Os top 10 jogadores recebem recompensas diárias:</p>
                <div class="rewards-list">
                    <div class="reward">
                        <span>#1-3:</span>
                        <strong>1000 Col + Item Raro</strong>
                    </div>
                    <div class="reward">
                        <span>#4-10:</span>
                        <strong>500 Col + Item Épico</strong>
                    </div>
                    <div class="reward">
                        <span>#11-50:</span>
                        <strong>250 Col</strong>
                    </div>
                </div>
                <p class="reward-note">Recompensas resetam diariamente às 00:00</p>
            </div>
        </div>
        
        <div class="ranking-info">
            <h3>Como Subir no Ranking?</h3>
            <div class="tips-grid">
                <div class="tip">
                    <div class="tip-icon">⚔️</div>
                    <h4>Batalhe NPCs</h4>
                    <p>Derrote NPCs para ganhar EXP e subir de nível</p>
                </div>
                <div class="tip">
                    <div class="tip-icon">🎯</div>
                    <h4>Participe de PvP</h4>
                    <p>Vitórias em PvP dão pontos de ranking extras</p>
                </div>
                <div class="tip">
                    <div class="tip-icon">👑</div>
                    <h4>Derrote Chefes</h5>
                    <p>Chefes de andar dão grandes recompensas</p>
                </div>
                <div class="tip">
                    <div class="tip-icon">💰</div>
                    <h4>Junte Col</h4>
                    <p>Acumule dinheiro para comprar equipamentos melhores</p>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .ranking-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        color: white;
    }
    
    .ranking-filters {
        display: flex;
        gap: 10px;
        margin: 20px 0;
    }
    
    .filter-btn {
        padding: 8px 16px;
        background: rgba(255,255,255,0.2);
        border-radius: 5px;
        color: white;
        text-decoration: none;
        transition: background 0.3s;
    }
    
    .filter-btn.active {
        background: #4CAF50;
    }
    
    .ranking-table {
        width: 100%;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .ranking-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
    }
    
    .ranking-table td {
        padding: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .ranking-table tr.first {
        background: linear-gradient(90deg, rgba(255,215,0,0.1) 0%, rgba(255,215,0,0.05) 100%);
    }
    
    .ranking-table tr.second {
        background: linear-gradient(90deg, rgba(192,192,192,0.1) 0%, rgba(192,192,192,0.05) 100%);
    }
    
    .ranking-table tr.third {
        background: linear-gradient(90deg, rgba(205,127,50,0.1) 0%, rgba(205,127,50,0.05) 100%);
    }
    
    .ranking-table tr.current-user {
        background: linear-gradient(90deg, rgba(33,150,243,0.1) 0%, rgba(33,150,243,0.05) 100%);
        border-left: 3px solid #2196F3;
    }
    </style>
</body>
</html>
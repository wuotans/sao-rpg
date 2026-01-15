// SAO RPG - Combat System

class CombatSystem {
    constructor() {
        this.player = null;
        this.monster = null;
        this.battleLog = [];
        this.skills = [];
        this.isPlayerTurn = true;
        this.autoBattle = false;
        this.battleInterval = null;
    }
    
    // Initialize combat
    init(player, monster) {
        this.player = player;
        this.monster = monster;
        this.battleLog = [];
        this.isPlayerTurn = true;
        
        this.loadSkills();
        this.renderCombatUI();
        this.addToLog(`Battle started against ${monster.name}!`, 'system');
    }
    
    // Load player skills
    loadSkills() {
        // Basic skills available to all players
        this.skills = [
            {
                id: 1,
                name: 'Slash',
                type: 'attack',
                damage: 15,
                mp_cost: 5,
                cooldown: 0,
                description: 'A basic sword slash'
            },
            {
                id: 2,
                name: 'Heavy Strike',
                type: 'attack',
                damage: 25,
                mp_cost: 10,
                cooldown: 2,
                description: 'A powerful strike that deals extra damage'
            },
            {
                id: 3,
                name: 'Heal',
                type: 'heal',
                amount: 30,
                mp_cost: 15,
                cooldown: 3,
                description: 'Restores HP'
            }
        ];
        
        // Load additional skills from server
        this.loadAdditionalSkills();
    }
    
    loadAdditionalSkills() {
        $.ajax({
            url: 'api/player.php?action=get_skills',
            method: 'GET',
            success: (data) => {
                try {
                    const serverSkills = JSON.parse(data);
                    this.skills = [...this.skills, ...serverSkills];
                    this.renderSkills();
                } catch (e) {
                    console.error('Error loading skills:', e);
                }
            }
        });
    }
    
    // Render combat UI
    renderCombatUI() {
        this.renderCombatants();
        this.renderSkills();
        this.updateHealthBars();
    }
    
    renderCombatants() {
        const $arena = $('#battleArena');
        if ($arena.length === 0) return;
        
        $arena.html(`
            <div class="combatant player-combatant">
                <div class="combatant-avatar player-avatar" 
                     style="background-image: url('images/avatars/${this.player.avatar}')"></div>
                <div class="combatant-name player-name">${this.player.username}</div>
                <div class="combatant-level">Level ${this.player.level}</div>
                <div class="health-display">
                    <div class="stat-label">
                        <i class="fas fa-heart"></i> HP
                        <span id="player-current-hp">${this.player.current_hp}</span>/<span id="player-max-hp">${this.player.max_hp}</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar-fill hp-bar" id="player-hp-bar"></div>
                    </div>
                </div>
                <div class="mana-display">
                    <div class="stat-label">
                        <i class="fas fa-bolt"></i> MP
                        <span id="player-current-mp">${this.player.current_mp}</span>/<span id="player-max-mp">${this.player.max_mp}</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar-fill mp-bar" id="player-mp-bar"></div>
                    </div>
                </div>
            </div>
            
            <div class="vs-text">VS</div>
            
            <div class="combatant monster-combatant">
                <div class="combatant-avatar monster-avatar"
                     style="background-image: url('images/monsters/${this.monster.id || 'default'}.png')"></div>
                <div class="combatant-name monster-name">${this.monster.name}</div>
                <div class="combatant-level">Floor ${this.monster.floor}</div>
                <div class="health-display">
                    <div class="stat-label">
                        <i class="fas fa-heart"></i> HP
                        <span id="monster-current-hp">${this.monster.current_hp}</span>/<span id="monster-max-hp">${this.monster.max_hp}</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar-fill hp-bar" id="monster-hp-bar"></div>
                    </div>
                </div>
            </div>
        `);
        
        this.updateHealthBars();
    }
    
    renderSkills() {
        const $skillsContainer = $('#skillsContainer');
        if ($skillsContainer.length === 0) return;
        
        let skillsHTML = '';
        
        this.skills.forEach(skill => {
            const canUse = this.player.current_mp >= skill.mp_cost;
            const cooldownText = skill.cooldown > 0 ? `<div class="skill-cooldown"><i class="fas fa-clock"></i> ${skill.cooldown} turns</div>` : '';
            
            skillsHTML += `
                <div class="skill-card ${canUse ? '' : 'disabled'}" 
                     onclick="combatSystem.useSkill(${skill.id})"
                     ${!canUse ? 'disabled' : ''}>
                    <div class="skill-icon">
                        <i class="fas ${this.getSkillIcon(skill.type)}"></i>
                    </div>
                    <div class="skill-name">${skill.name}</div>
                    <div class="skill-description">${skill.description}</div>
                    <div class="skill-cost">
                        <i class="fas fa-bolt"></i> ${skill.mp_cost} MP
                    </div>
                    ${cooldownText}
                </div>
            `;
        });
        
        $skillsContainer.html(skillsHTML);
    }
    
    getSkillIcon(skillType) {
        switch(skillType) {
            case 'attack': return 'fa-swords';
            case 'heal': return 'fa-heart';
            case 'buff': return 'fa-arrow-up';
            case 'debuff': return 'fa-arrow-down';
            default: return 'fa-star';
        }
    }
    
    // Update health bars
    updateHealthBars() {
        if (!this.player || !this.monster) return;
        
        // Player HP
        const playerHpPercent = (this.player.current_hp / this.player.max_hp) * 100;
        $('#player-hp-bar').css('width', playerHpPercent + '%');
        $('#player-current-hp').text(this.player.current_hp);
        
        // Player MP
        const playerMpPercent = (this.player.current_mp / this.player.max_mp) * 100;
        $('#player-mp-bar').css('width', playerMpPercent + '%');
        $('#player-current-mp').text(this.player.current_mp);
        
        // Monster HP
        const monsterHpPercent = (this.monster.current_hp / this.monster.max_hp) * 100;
        $('#monster-hp-bar').css('width', monsterHpPercent + '%');
        $('#monster-current-hp').text(this.monster.current_hp);
    }
    
    // Use a skill
    useSkill(skillId) {
        if (!this.isPlayerTurn) {
            this.addToLog('Not your turn!', 'system');
            return;
        }
        
        const skill = this.skills.find(s => s.id === skillId);
        if (!skill) return;
        
        // Check MP
        if (this.player.current_mp < skill.mp_cost) {
            this.addToLog('Not enough MP!', 'system');
            return;
        }
        
        // Deduct MP
        this.player.current_mp -= skill.mp_cost;
        this.updateHealthBars();
        
        // Execute skill
        switch(skill.type) {
            case 'attack':
                this.attack(skill);
                break;
            case 'heal':
                this.heal(skill);
                break;
            case 'buff':
                this.buff(skill);
                break;
            case 'debuff':
                this.debuff(skill);
                break;
        }
        
        // Monster's turn
        this.isPlayerTurn = false;
        setTimeout(() => {
            this.monsterTurn();
        }, 1000);
    }
    
    // Basic attack
    basicAttack() {
        if (!this.isPlayerTurn) return;
        
        const damage = this.calculateDamage(this.player.atk, this.monster.def, this.player.crit);
        
        this.monster.current_hp -= damage.amount;
        if (this.monster.current_hp < 0) this.monster.current_hp = 0;
        
        this.addToLog(`You hit ${this.monster.name} for ${damage.amount} damage${damage.critical ? ' (CRITICAL!)' : ''}`, 'player');
        this.updateHealthBars();
        
        // Check if monster is dead
        if (this.monster.current_hp <= 0) {
            this.victory();
            return;
        }
        
        // Monster's turn
        this.isPlayerTurn = false;
        setTimeout(() => {
            this.monsterTurn();
        }, 1000);
    }
    
    // Skill-based attack
    attack(skill) {
        let damage = skill.damage || this.player.atk;
        
        // Add player ATK to skill damage
        damage += this.player.atk;
        
        // Calculate final damage with defense reduction
        const finalDamage = Math.max(1, damage - this.monster.def);
        
        // Critical chance
        let isCritical = false;
        if (Math.random() * 100 <= this.player.crit) {
            finalDamage *= 1.5;
            isCritical = true;
            finalDamage = Math.round(finalDamage);
        }
        
        this.monster.current_hp -= finalDamage;
        if (this.monster.current_hp < 0) this.monster.current_hp = 0;
        
        this.addToLog(`You used ${skill.name} on ${this.monster.name} for ${finalDamage} damage${isCritical ? ' (CRITICAL!)' : ''}`, 'player');
        this.updateHealthBars();
        
        // Check if monster is dead
        if (this.monster.current_hp <= 0) {
            this.victory();
        }
    }
    
    // Heal skill
    heal(skill) {
        const healAmount = skill.amount || 30;
        const newHp = Math.min(this.player.current_hp + healAmount, this.player.max_hp);
        const actualHeal = newHp - this.player.current_hp;
        
        this.player.current_hp = newHp;
        this.addToLog(`You healed yourself for ${actualHeal} HP`, 'heal');
        this.updateHealthBars();
    }
    
    // Buff skill
    buff(skill) {
        this.addToLog(`You used ${skill.name} - ${skill.description}`, 'buff');
        // Implement buff logic here
    }
    
    // Debuff skill
    debuff(skill) {
        this.addToLog(`You used ${skill.name} on ${this.monster.name} - ${skill.description}`, 'debuff');
        // Implement debuff logic here
    }
    
    // Monster's turn
    monsterTurn() {
        if (this.monster.current_hp <= 0) return;
        
        const damage = this.calculateDamage(this.monster.atk, this.player.def, 5); // 5% crit chance for monsters
        
        this.player.current_hp -= damage.amount;
        if (this.player.current_hp < 0) this.player.current_hp = 0;
        
        this.addToLog(`${this.monster.name} hit you for ${damage.amount} damage${damage.critical ? ' (CRITICAL!)' : ''}`, 'monster');
        this.updateHealthBars();
        
        // Check if player is dead
        if (this.player.current_hp <= 0) {
            this.defeat();
            return;
        }
        
        // Player's turn again
        this.isPlayerTurn = true;
        this.renderSkills(); // Update skill availability
    }
    
    // Calculate damage
    calculateDamage(attackerAtk, defenderDef, critChance) {
        let damage = Math.max(1, attackerAtk - defenderDef);
        
        // Add some randomness (80-120%)
        const variance = 0.8 + Math.random() * 0.4;
        damage = Math.round(damage * variance);
        
        // Critical hit
        let isCritical = false;
        if (Math.random() * 100 <= critChance) {
            damage = Math.round(damage * 1.5);
            isCritical = true;
        }
        
        return {
            amount: damage,
            critical: isCritical
        };
    }
    
    // Victory
    victory() {
        this.addToLog(`You defeated ${this.monster.name}!`, 'victory');
        
        // Calculate rewards
        const exp = this.monster.exp || 10;
        const gold = this.monster.gold || 5;
        
        this.addToLog(`Gained ${exp} EXP and ${gold} Gold!`, 'reward');
        
        // Send victory to server
        this.reportVictory(exp, gold);
        
        // Disable combat controls
        this.isPlayerTurn = false;
        
        // Show continue button
        this.showContinueButton();
    }
    
    // Defeat
    defeat() {
        this.addToLog(`You were defeated by ${this.monster.name}!`, 'defeat');
        
        // Send defeat to server
        this.reportDefeat();
        
        // Disable combat controls
        this.isPlayerTurn = false;
        
        // Show retry button
        this.showRetryButton();
    }
    
    // Report victory to server
    reportVictory(exp, gold) {
        $.ajax({
            url: 'api/battle.php?action=victory',
            method: 'POST',
            data: {
                monster_id: this.monster.id,
                exp: exp,
                gold: gold
            },
            success: (data) => {
                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        // Show drops
                        if (result.drops && result.drops.length > 0) {
                            this.showDrops(result.drops);
                        }
                        
                        // Update player stats
                        if (result.player) {
                            this.player = result.player;
                            this.updateHealthBars();
                        }
                    }
                } catch (e) {
                    console.error('Error reporting victory:', e);
                }
            }
        });
    }
    
    // Report defeat to server
    reportDefeat() {
        $.ajax({
            url: 'api/battle.php?action=defeat',
            method: 'POST',
            data: {
                monster_id: this.monster.id
            }
        });
    }
    
    // Show drops
    showDrops(drops) {
        let dropMessage = 'Items dropped: ';
        drops.forEach((drop, index) => {
            dropMessage += `${drop.name}${index < drops.length - 1 ? ', ' : ''}`;
        });
        
        this.addToLog(dropMessage, 'loot');
    }
    
    // Add message to battle log
    addToLog(message, type = 'system') {
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const logEntry = {
            time: time,
            text: message,
            type: type
        };
        
        this.battleLog.push(logEntry);
        this.renderLogEntry(logEntry);
    }
    
    // Render log entry
    renderLogEntry(entry) {
        const $log = $('#battleLog');
        if ($log.length === 0) return;
        
        const $entry = $(`
            <div class="log-entry ${entry.type}">
                <span class="log-time">[${entry.time}]</span>
                <span class="log-text">${entry.text}</span>
            </div>
        `);
        
        $log.append($entry);
        $log.scrollTop($log[0].scrollHeight);
    }
    
    // Show continue button
    showContinueButton() {
        const $controls = $('#battleControls');
        if ($controls.length === 0) return;
        
        $controls.html(`
            <button class="battle-btn continue-btn" onclick="combatSystem.continueBattle()">
                <i class="fas fa-forward"></i> Continue
            </button>
        `);
    }
    
    // Show retry button
    showRetryButton() {
        const $controls = $('#battleControls');
        if ($controls.length === 0) return;
        
        $controls.html(`
            <button class="battle-btn retry-btn" onclick="combatSystem.retryBattle()">
                <i class="fas fa-redo"></i> Retry
            </button>
            <button class="battle-btn flee-btn" onclick="combatSystem.flee()">
                <i class="fas fa-running"></i> Flee
            </button>
        `);
    }
    
    // Continue to next battle
    continueBattle() {
        this.loadNewMonster();
    }
    
    // Retry same monster
    retryBattle() {
        this.monster.current_hp = this.monster.max_hp;
        this.player.current_hp = this.player.max_hp;
        this.player.current_mp = this.player.max_mp;
        
        this.isPlayerTurn = true;
        this.battleLog = [];
        
        this.renderCombatUI();
        this.addToLog('Battle restarted!', 'system');
        
        // Restore controls
        this.restoreControls();
    }
    
    // Flee from battle
    flee() {
        this.addToLog('You fled from battle!', 'system');
        
        // Send flee to server
        $.ajax({
            url: 'api/battle.php?action=flee',
            method: 'POST',
            data: {
                monster_id: this.monster.id
            }
        });
        
        // Return to map or previous screen
        window.location.href = 'index.php';
    }
    
    // Load new monster
    loadNewMonster() {
        $.ajax({
            url: 'api/battle.php?action=get_monster',
            method: 'GET',
            success: (data) => {
                try {
                    const newMonster = JSON.parse(data);
                    this.monster = newMonster;
                    
                    this.isPlayerTurn = true;
                    this.battleLog = [];
                    
                    this.renderCombatUI();
                    this.addToLog(`New enemy appeared: ${newMonster.name}!`, 'system');
                    
                    // Restore controls
                    this.restoreControls();
                } catch (e) {
                    console.error('Error loading new monster:', e);
                }
            }
        });
    }
    
    // Restore battle controls
    restoreControls() {
        const $controls = $('#battleControls');
        if ($controls.length === 0) return;
        
        $controls.html(`
            <button class="battle-btn attack-btn" onclick="combatSystem.basicAttack()">
                <i class="fas fa-swords"></i> Basic Attack
            </button>
            <button class="battle-btn defend-btn" onclick="combatSystem.defend()">
                <i class="fas fa-shield-alt"></i> Defend
            </button>
            <button class="battle-btn flee-btn" onclick="combatSystem.flee()">
                <i class="fas fa-running"></i> Flee
            </button>
            <button class="battle-btn auto-btn" onclick="combatSystem.toggleAutoBattle()">
                <i class="fas fa-robot"></i> Auto Battle
            </button>
        `);
    }
    
    // Defend action
    defend() {
        if (!this.isPlayerTurn) return;
        
        // Increase defense for this turn
        const defenseBonus = Math.round(this.player.def * 0.5);
        this.player.def += defenseBonus;
        
        this.addToLog(`You defend, increasing DEF by ${defenseBonus} for this turn`, 'player');
        
        // Monster's turn
        this.isPlayerTurn = false;
        setTimeout(() => {
            // Restore defense after monster's turn
            this.player.def -= defenseBonus;
            this.monsterTurn();
        }, 1000);
    }
    
    // Toggle auto battle
    toggleAutoBattle() {
        if (this.autoBattle) {
            this.stopAutoBattle();
        } else {
            this.startAutoBattle();
        }
    }
    
    // Start auto battle
    startAutoBattle() {
        this.autoBattle = true;
        $('.auto-btn').html('<i class="fas fa-stop"></i> Stop Auto');
        
        this.addToLog('Auto battle started!', 'system');
        
        this.battleInterval = setInterval(() => {
            if (!this.autoBattle || !this.isPlayerTurn || this.monster.current_hp <= 0 || this.player.current_hp <= 0) {
                if (this.monster.current_hp <= 0 || this.player.current_hp <= 0) {
                    this.stopAutoBattle();
                }
                return;
            }
            
            // Use basic attack in auto mode
            this.basicAttack();
        }, 2000); // Attack every 2 seconds
    }
    
    // Stop auto battle
    stopAutoBattle() {
        this.autoBattle = false;
        clearInterval(this.battleInterval);
        $('.auto-btn').html('<i class="fas fa-robot"></i> Auto Battle');
        this.addToLog('Auto battle stopped.', 'system');
    }
    
    // Flee from battle (public method)
    flee() {
        if (confirm('Are you sure you want to flee from battle?')) {
            this.addToLog('You fled from battle!', 'system');
            
            // Send flee to server
            $.ajax({
                url: 'api/battle.php?action=flee',
                method: 'POST',
                data: {
                    monster_id: this.monster.id
                },
                success: () => {
                    // Return to map
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                }
            });
        }
    }
}

// Create global combat system instance
const combatSystem = new CombatSystem();

// Global functions for HTML onclick
window.combatSystem = combatSystem;
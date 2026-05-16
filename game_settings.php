<?php
// game_settings.php
require 'config.php';

$gameSettingsFile = SETTINGS_DIR . '/ServerGameSettings.json';
$defaultGameSettings = SERVER_DIR . '/VRisingServer_Data/StreamingAssets/Settings/ServerGameSettings.json';

// Copy defaults if not exists
if (!file_exists($gameSettingsFile) && file_exists($defaultGameSettings)) {
    copy($defaultGameSettings, $gameSettingsFile);
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameDataContent = file_exists($gameSettingsFile) ? @file_get_contents($gameSettingsFile) : false;
    $gameData = $gameDataContent !== false ? json_decode($gameDataContent, true) : [];
    if (!$gameData) $gameData = [];
    
    // Quick Settings
    if (isset($_POST['quick_settings'])) {
        $gameData['GameModeType'] = $_POST['GameModeType'] ?? $gameData['GameModeType'];
        $gameData['GameDifficulty'] = $_POST['GameDifficulty'] ?? $gameData['GameDifficulty'];
        $gameData['CastleDamageMode'] = $_POST['CastleDamageMode'] ?? $gameData['CastleDamageMode'];
        $gameData['ClanSize'] = (int)($_POST['ClanSize'] ?? $gameData['ClanSize']);
        $gameData['InventoryStacksModifier'] = (float)($_POST['InventoryStacksModifier'] ?? $gameData['InventoryStacksModifier']);
        $gameData['DropTableModifier_General'] = (float)($_POST['DropTableModifier_General'] ?? $gameData['DropTableModifier_General']);
        
        $gameData['BloodBoundEquipment'] = isset($_POST['BloodBoundEquipment']);
        $gameData['TeleportBoundItems'] = isset($_POST['TeleportBoundItems']);
        $gameData['AllowGlobalChat'] = isset($_POST['AllowGlobalChat']);
        
        file_put_contents($gameSettingsFile, json_encode($gameData, JSON_PRETTY_PRINT));
        $message = "Game rules saved successfully!";
    }
    
    // Advanced Settings
    if (isset($_POST['advanced_settings']) && !empty($_POST['raw_json'])) {
        $parsed = json_decode($_POST['raw_json'], true);
        if ($parsed) {
            file_put_contents($gameSettingsFile, json_encode($parsed, JSON_PRETTY_PRINT));
            $message = "Advanced JSON saved successfully!";
        } else {
            $message = "Error: Invalid JSON format!";
        }
    }
}

$gameDataContent = file_exists($gameSettingsFile) ? @file_get_contents($gameSettingsFile) : false;
$gameData = $gameDataContent !== false ? json_decode($gameDataContent, true) : [];
if (!$gameData) $gameData = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Rules - V Rising Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.9rem; }
        .form-control { width: 100%; padding: 0.8rem 1rem; border-radius: 8px; border: 1px solid var(--border); background: rgba(0,0,0,0.2); color: white; font-family: 'Inter', sans-serif;}
        .form-control:focus { outline: none; border-color: var(--primary); }
        .row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .col { flex: 1; min-width: 250px; }
        select.form-control { appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23b7bcf8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1rem top 50%; background-size: 0.65rem auto; }
        .tabs { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        .tab-btn { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.1rem; font-family: inherit; transition: 0.3s; padding: 0.5rem 1rem; border-radius: 8px; }
        .tab-btn.active { color: var(--primary); background: rgba(183, 188, 248, 0.1); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

    <nav class="navbar">
        <h1><i class="fa-solid fa-moon"></i> V Rising <span>Panel</span></h1>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="settings.php">Host Settings</a>
            <a href="game_settings.php" class="active">Game Rules</a>
        </div>
    </nav>

    <div class="container">
        <div class="glass-panel" style="max-width: 900px; margin: 0 auto;">
            
            <?php if ($message): ?>
            <div style="background: <?php echo strpos($message, 'Error') !== false ? 'rgba(231, 76, 60, 0.2)' : 'rgba(42, 157, 143, 0.2)'; ?>; border: 1px solid <?php echo strpos($message, 'Error') !== false ? '#e74c3c' : 'var(--secondary)'; ?>; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; color: white;">
                <i class="fa-solid <?php echo strpos($message, 'Error') !== false ? 'fa-triangle-exclamation' : 'fa-check'; ?>"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('quick')"><i class="fa-solid fa-sliders"></i> Basic Rules</button>
                <button class="tab-btn" onclick="switchTab('advanced')"><i class="fa-solid fa-code"></i> Advanced Editor</button>
            </div>

            <!-- Quick Settings Tab -->
            <div id="quick" class="tab-content active">
                <form method="POST">
                    <input type="hidden" name="quick_settings" value="1">
                    
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label>Game Mode Type</label>
                                <select name="GameModeType" class="form-control">
                                    <option value="PvE" <?php echo ($gameData['GameModeType'] ?? '') == 'PvE' ? 'selected' : ''; ?>>PvE (Player vs Environment)</option>
                                    <option value="PvP" <?php echo ($gameData['GameModeType'] ?? '') == 'PvP' ? 'selected' : ''; ?>>PvP (Player vs Player)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label>Game Difficulty</label>
                                <select name="GameDifficulty" class="form-control">
                                    <option value="Relaxed" <?php echo ($gameData['GameDifficulty'] ?? '') == 'Relaxed' ? 'selected' : ''; ?>>Relaxed / Easy</option>
                                    <option value="Normal" <?php echo ($gameData['GameDifficulty'] ?? '') == 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="Hard" <?php echo ($gameData['GameDifficulty'] ?? '') == 'Hard' ? 'selected' : ''; ?>>Hard / Brutal</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label>Inventory Stacks Multiplier (Default 1.0)</label>
                                <input type="number" step="0.1" name="InventoryStacksModifier" class="form-control" value="<?php echo htmlspecialchars($gameData['InventoryStacksModifier'] ?? 1.0); ?>">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label>Loot Drop Multiplier (Default 1.0)</label>
                                <input type="number" step="0.1" name="DropTableModifier_General" class="form-control" value="<?php echo htmlspecialchars($gameData['DropTableModifier_General'] ?? 1.0); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label>Max Clan Size</label>
                                <input type="number" name="ClanSize" class="form-control" value="<?php echo htmlspecialchars($gameData['ClanSize'] ?? 4); ?>">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label>Castle Damage Mode</label>
                                <select name="CastleDamageMode" class="form-control">
                                    <option value="Never" <?php echo ($gameData['CastleDamageMode'] ?? '') == 'Never' ? 'selected' : ''; ?>>Never (Indestructible)</option>
                                    <option value="Always" <?php echo ($gameData['CastleDamageMode'] ?? '') == 'Always' ? 'selected' : ''; ?>>Always</option>
                                    <option value="TimeRestricted" <?php echo ($gameData['CastleDamageMode'] ?? '') == 'TimeRestricted' ? 'selected' : ''; ?>>Time Restricted (Raid Windows)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1.5rem;">
                        <h4 style="margin-bottom: 1rem; color: var(--primary);">Toggles / Aturan Main</h4>
                        
                        <div class="form-group" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <input type="checkbox" id="BloodBoundEquipment" name="BloodBoundEquipment" value="1" <?php echo (!isset($gameData['BloodBoundEquipment']) || $gameData['BloodBoundEquipment']) ? 'checked' : ''; ?>>
                            <label for="BloodBoundEquipment" style="margin: 0; color: white;">Equipment Drop on Death (Uncheck = Drop Everything, Check = Keep Armor/Weapons)</label>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <input type="checkbox" id="TeleportBoundItems" name="TeleportBoundItems" value="1" <?php echo (!isset($gameData['TeleportBoundItems']) || $gameData['TeleportBoundItems']) ? 'checked' : ''; ?>>
                            <label for="TeleportBoundItems" style="margin: 0; color: white;">Teleport Blocked For Resources (Uncheck = Can teleport with ANY item)</label>
                        </div>

                        <div class="form-group" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <input type="checkbox" id="AllowGlobalChat" name="AllowGlobalChat" value="1" <?php echo (!isset($gameData['AllowGlobalChat']) || $gameData['AllowGlobalChat']) ? 'checked' : ''; ?>>
                            <label for="AllowGlobalChat" style="margin: 0; color: white;">Allow Global Chat</label>
                        </div>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" class="control-btn btn-primary"><i class="fa-solid fa-save"></i> Save Rules</button>
                    </div>
                </form>
            </div>

            <!-- Advanced Tab -->
            <div id="advanced" class="tab-content">
                <form method="POST">
                    <input type="hidden" name="advanced_settings" value="1">
                    
                    <div class="form-group">
                        <label>Raw Game Settings JSON <br><small style="color:#e74c3c;">Danger: Invalid JSON format will break the server startup!</small></label>
                        <textarea name="raw_json" class="form-control" rows="20" style="font-family: monospace; font-size: 0.85rem;"><?php echo htmlspecialchars(json_encode($gameData, JSON_PRETTY_PRINT)); ?></textarea>
                    </div>

                    <div style="margin-top: 1rem;">
                        <button type="submit" class="control-btn btn-primary"><i class="fa-solid fa-save"></i> Save Raw JSON</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

<?php
// settings.php
require 'config.php';

$defaultHostSettings = SERVER_DIR . '/VRisingServer_Data/StreamingAssets/Settings/ServerHostSettings.json';
$defaultGameSettings = SERVER_DIR . '/VRisingServer_Data/StreamingAssets/Settings/ServerGameSettings.json';

$hostSettingsFile = SETTINGS_DIR . '/ServerHostSettings.json';
$gameSettingsFile = SETTINGS_DIR . '/ServerGameSettings.json';

// Copy defaults if not exists
if (!file_exists($hostSettingsFile) && file_exists($defaultHostSettings)) {
    copy($defaultHostSettings, $hostSettingsFile);
}
if (!file_exists($gameSettingsFile) && file_exists($defaultGameSettings)) {
    copy($defaultGameSettings, $gameSettingsFile);
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['host_settings'])) {
        $hostDataContent = @file_get_contents($hostSettingsFile);
        $hostData = $hostDataContent !== false ? json_decode($hostDataContent, true) : [];
        if (!$hostData) $hostData = [];
        $hostData['Name'] = $_POST['Name'] ?? $hostData['Name'];
        $hostData['Description'] = $_POST['Description'] ?? $hostData['Description'];
        
        // Password: jika kosong, hapus key sepenuhnya agar server tidak minta password
        if (isset($_POST['Password']) && trim($_POST['Password']) !== '') {
            $hostData['Password'] = trim($_POST['Password']);
        } else {
            unset($hostData['Password']);
        }
        
        $hostData['SaveName'] = !empty($_POST['SaveName']) ? $_POST['SaveName'] : ($hostData['SaveName'] ?? 'world1');
        $hostData['BindAddress'] = $_POST['BindAddress'] ?? $hostData['BindAddress'] ?? '';
        $hostData['MaxConnectedUsers'] = (int)($_POST['MaxConnectedUsers'] ?? 40);
        $hostData['Port'] = (int)($_POST['Port'] ?? $hostData['Port'] ?? 9876);
        $hostData['QueryPort'] = (int)($_POST['QueryPort'] ?? $hostData['QueryPort'] ?? 9877);
        $hostData['ListOnSteam'] = isset($_POST['ListOnSteam']);
        $hostData['ListOnEOS'] = isset($_POST['ListOnSteam']); // typically if you list on steam you list on EOS too
        
        file_put_contents($hostSettingsFile, json_encode($hostData, JSON_PRETTY_PRINT));
        
        // V Rising juga menyimpan copy settings di dalam folder save game
        // Kita harus update SEMUA file ServerHostSettings.json di dalam Saves/
        $savesDir = SAVE_DIR . '/Saves';
        if (is_dir($savesDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($savesDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->getFilename() === 'ServerHostSettings.json') {
                    $saveSettingsPath = $file->getPathname();
                    $saveDataContent = @file_get_contents($saveSettingsPath);
                    $saveData = $saveDataContent !== false ? json_decode($saveDataContent, true) : [];
                    if (!$saveData) $saveData = [];
                    
                    // Sync password setting
                    if (isset($hostData['Password'])) {
                        $saveData['Password'] = $hostData['Password'];
                    } else {
                        unset($saveData['Password']);
                    }
                    
                    // Sync other key settings
                    $saveData['Name'] = $hostData['Name'];
                    $saveData['Description'] = $hostData['Description'];
                    $saveData['MaxConnectedUsers'] = $hostData['MaxConnectedUsers'];
                    
                    file_put_contents($saveSettingsPath, json_encode($saveData, JSON_PRETTY_PRINT));
                }
            }
        }
        
        $message = "Host settings saved! (All save locations updated)";
    }
}

$hostDataContent = file_exists($hostSettingsFile) ? @file_get_contents($hostSettingsFile) : false;
$hostData = $hostDataContent !== false ? json_decode($hostDataContent, true) : [];
if (!$hostData) $hostData = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Settings - V Rising Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.9rem; }
        .form-control { width: 100%; padding: 0.8rem 1rem; border-radius: 8px; border: 1px solid var(--border); background: rgba(0,0,0,0.2); color: white; font-family: 'Inter', sans-serif;}
        .form-control:focus { outline: none; border-color: var(--primary); }
    </style>
</head>
<body>

    <nav class="navbar">
        <h1><i class="fa-solid fa-moon"></i> V Rising <span>Panel</span></h1>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="settings.php" class="active">Host Settings</a>
            <a href="game_settings.php">Game Rules</a>
        </div>
    </nav>

    <div class="container">
        <div class="glass-panel" style="max-width: 800px; margin: 0 auto;">
            <h2 style="margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem;">Host Settings</h2>
            
            <?php if($message): ?>
            <div style="background: rgba(42, 157, 143, 0.2); border: 1px solid var(--secondary); padding: 1rem; border-radius: 8px; margin-bottom: 2rem; color: var(--secondary);">
                <i class="fa-solid fa-check"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="host_settings" value="1">
                
                <div class="form-group">
                    <label>Server Name</label>
                    <input type="text" name="Name" class="form-control" value="<?php echo htmlspecialchars($hostData['Name'] ?? 'V Rising Server'); ?>">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="Description" class="form-control" rows="3"><?php echo htmlspecialchars($hostData['Description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Password (Kosongkan jika publik)</label>
                    <input type="text" name="Password" class="form-control" value="<?php echo htmlspecialchars($hostData['Password'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Nama World / Save Name (Folder penyimpanan game)</label>
                    <input type="text" name="SaveName" class="form-control" value="<?php echo htmlspecialchars($hostData['SaveName'] ?? 'world1'); ?>">
                </div>

                <div class="form-group">
                    <label>IP Bind (Opsional, kosongkan untuk semua IP 0.0.0.0)</label>
                    <input type="text" name="BindAddress" class="form-control" value="<?php echo htmlspecialchars($hostData['BindAddress'] ?? ''); ?>" placeholder="Misal: 192.168.1.10">
                </div>

                <div class="form-group">
                    <label>Max Players</label>
                    <input type="number" name="MaxConnectedUsers" class="form-control" value="<?php echo htmlspecialchars($hostData['MaxConnectedUsers'] ?? 40); ?>">
                </div>

                <div class="form-group">
                    <label>Game Port</label>
                    <input type="number" name="Port" class="form-control" value="<?php echo htmlspecialchars($hostData['Port'] ?? 9876); ?>">
                </div>

                <div class="form-group">
                    <label>Query Port</label>
                    <input type="number" name="QueryPort" class="form-control" value="<?php echo htmlspecialchars($hostData['QueryPort'] ?? 9877); ?>">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 1rem;">
                    <input type="checkbox" id="ListOnSteam" name="ListOnSteam" value="1" <?php echo !empty($hostData['ListOnSteam']) ? 'checked' : ''; ?>>
                    <label for="ListOnSteam" style="margin: 0; color: white;">List on Master Server (Publicly Visible)</label>
                </div>

                <div style="margin-top: 3rem;">
                    <button type="submit" class="control-btn btn-primary"><i class="fa-solid fa-save"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>

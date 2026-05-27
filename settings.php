<?php
// settings.php
require 'config.php';

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['host_settings'])) {
        $hostData = readRomesteadConfig();
        $hostData['AutoStartWorldName'] = !empty($_POST['AutoStartWorldName']) ? $_POST['AutoStartWorldName'] : 'romestead_server';
        $hostData['AutoCreateAndLoadWorld'] = isset($_POST['AutoCreateAndLoadWorld']);
        $hostData['AutoCreateWorldSize'] = (int)($_POST['AutoCreateWorldSize'] ?? 1);
        $hostData['AutoCreateWorldSeed'] = isset($_POST['AutoCreateWorldSeed']) && trim($_POST['AutoCreateWorldSeed']) !== ''
            ? trim($_POST['AutoCreateWorldSeed'])
            : null;
        
        // Password kosong berarti server publik tanpa password.
        if (isset($_POST['Password']) && trim($_POST['Password']) !== '') {
            $hostData['Password'] = trim($_POST['Password']);
        } else {
            $hostData['Password'] = '';
        }
        
        $hostData['Port'] = (int)($_POST['Port'] ?? $hostData['Port'] ?? 5580);
        $hostData['MaxPlayers'] = (int)($_POST['MaxPlayers'] ?? 8);
        $hostData['EnableCheats'] = isset($_POST['EnableCheats']);
        writeRomesteadConfig($hostData);
        
        $message = "Romestead settings saved!";
    }
}

$hostData = readRomesteadConfig();
$worldSizeOptions = [
    0 => 'Small',
    1 => 'Medium',
    2 => 'Large'
];
$selectedWorldSize = (int)($hostData['AutoCreateWorldSize'] ?? 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Settings - Romestead Panel</title>
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
        <h1><i class="fa-solid fa-server"></i> Romestead <span>Panel</span></h1>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="settings.php" class="active">Host Settings</a>
            <a href="game_settings.php">Server Config</a>
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
                    <label>World Name</label>
                    <input type="text" name="AutoStartWorldName" class="form-control" value="<?php echo htmlspecialchars($hostData['AutoStartWorldName'] ?? 'romestead_server'); ?>">
                </div>

                <div class="form-group">
                    <label>World Size</label>
                    <select name="AutoCreateWorldSize" class="form-control">
                        <?php foreach ($worldSizeOptions as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $selectedWorldSize === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label . ' (' . $value . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>World Seed (Kosongkan untuk random)</label>
                    <input type="text" name="AutoCreateWorldSeed" class="form-control" value="<?php echo htmlspecialchars($hostData['AutoCreateWorldSeed'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Password (Kosongkan jika publik)</label>
                    <input type="text" name="Password" class="form-control" value="<?php echo htmlspecialchars($hostData['Password'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Max Players</label>
                    <input type="number" name="MaxPlayers" class="form-control" value="<?php echo htmlspecialchars($hostData['MaxPlayers'] ?? 8); ?>">
                </div>

                <div class="form-group">
                    <label>Game Port</label>
                    <input type="number" name="Port" class="form-control" value="<?php echo htmlspecialchars($hostData['Port'] ?? 5580); ?>">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 1rem;">
                    <input type="checkbox" id="AutoCreateAndLoadWorld" name="AutoCreateAndLoadWorld" value="1" <?php echo !empty($hostData['AutoCreateAndLoadWorld']) ? 'checked' : ''; ?>>
                    <label for="AutoCreateAndLoadWorld" style="margin: 0; color: white;">Auto-create and load world</label>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 1rem;">
                    <input type="checkbox" id="EnableCheats" name="EnableCheats" value="1" <?php echo !empty($hostData['EnableCheats']) ? 'checked' : ''; ?>>
                    <label for="EnableCheats" style="margin: 0; color: white;">Enable Cheats</label>
                </div>

                <div style="margin-top: 3rem;">
                    <button type="submit" class="control-btn btn-primary"><i class="fa-solid fa-save"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>

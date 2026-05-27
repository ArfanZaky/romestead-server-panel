<?php
// game_settings.php
require 'config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['raw_json'])) {
    $parsed = json_decode($_POST['raw_json'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
        writeRomesteadConfig($parsed);
        $message = 'Server config saved successfully!';
    } else {
        $message = 'Error: Invalid JSON format!';
    }
}

$gameData = readRomesteadConfig();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Config - Romestead Panel</title>
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
            <a href="settings.php">Host Settings</a>
            <a href="game_settings.php" class="active">Server Config</a>
        </div>
    </nav>

    <div class="container">
        <div class="glass-panel" style="max-width: 900px; margin: 0 auto;">
            <?php if ($message): ?>
            <div style="background: <?php echo strpos($message, 'Error') !== false ? 'rgba(231, 76, 60, 0.2)' : 'rgba(42, 157, 143, 0.2)'; ?>; border: 1px solid <?php echo strpos($message, 'Error') !== false ? '#e74c3c' : 'var(--secondary)'; ?>; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; color: white;">
                <i class="fa-solid <?php echo strpos($message, 'Error') !== false ? 'fa-triangle-exclamation' : 'fa-check'; ?>"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Raw config.json</label>
                    <textarea name="raw_json" class="form-control" rows="24" style="font-family: monospace; font-size: 0.85rem;"><?php echo htmlspecialchars(json_encode($gameData, JSON_PRETTY_PRINT)); ?></textarea>
                </div>

                <div style="margin-top: 1rem;">
                    <button type="submit" class="control-btn btn-primary"><i class="fa-solid fa-save"></i> Save Config</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

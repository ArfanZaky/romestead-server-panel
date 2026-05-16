<?php
// config.php
session_start();

// Tentukan direktori tempat folder "engine" (server game) berada.
// Jika file web berada di folder yang sama dengan engine, gunakan __DIR__
// Tapi jika engine ada di /mnt/gamedisk/gameserver, ganti menjadi '/mnt/gamedisk/gameserver'
define('BASE_DIR', __DIR__); // <-- EDIT BARIS INI JIKA ENGINE ADA DI TEMPAT LAIN
define('SERVER_DIR', BASE_DIR . '/engine/server');
define('SERVER_EXE', SERVER_DIR . '/VRisingServer.exe');

// VRising Configuration directories
define('SAVE_DIR', BASE_DIR . '/engine/savedata');
define('SETTINGS_DIR', SAVE_DIR . '/Settings');
define('BACKUP_DIR', BASE_DIR . '/engine/backups');
define('LOG_FILE', '/tmp/vrising_server.log'); // Dipindahkan ke /tmp agar dipastikan selalu writable

if (!file_exists(SAVE_DIR)) {
    mkdir(SAVE_DIR, 0777, true);
}
if (!file_exists(SETTINGS_DIR)) {
    mkdir(SETTINGS_DIR, 0777, true);
}
if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0777, true);
}

// Function to check if server is running
// PENTING: Gunakan [V] bracket trick agar pgrep tidak mendeteksi dirinya sendiri
function isServerRunning() {
    $processes = [];
    exec("pgrep -f '[V]RisingServer.exe'", $processes);
    
    // pgrep prints process IDs if found, so array count > 0 means it's running
    return count($processes) > 0;
}

// Function to kill the VRising server and all related Wine processes
function killVRisingServer() {
    // Attempt graceful exit
    exec("wineserver -k 2>/dev/null");
    sleep(1);
    
    // 1. Find all PIDs matching the game server and kill them forcefully
    $pids = [];
    exec("pgrep -f '[V]RisingServer.exe'", $pids);
    foreach ($pids as $pid) {
        $pid = trim($pid);
        if (is_numeric($pid)) {
            exec("kill -9 " . escapeshellarg($pid) . " 2>/dev/null");
        }
    }
    
    // 2. Find and kill wine-preloader PIDs
    $winePids = [];
    exec("pgrep -f '[w]ine'", $winePids);
    foreach ($winePids as $pid) {
        $pid = trim($pid);
        if (is_numeric($pid)) {
            exec("kill -9 " . escapeshellarg($pid) . " 2>/dev/null");
        }
    }
    
    // 3. Kill xvfb-run and Xvfb PIDs
    $xvfbPids = [];
    exec("pgrep -f '[x]vfb'", $xvfbPids);
    foreach ($xvfbPids as $pid) {
        $pid = trim($pid);
        if (is_numeric($pid)) {
            exec("kill -9 " . escapeshellarg($pid) . " 2>/dev/null");
        }
    }
    
    // 4. Fallback killall for good measure
    exec("killall -9 wineserver 2>/dev/null");
    exec("killall -9 wine 2>/dev/null");
    exec("killall -9 xvfb-run 2>/dev/null");
    
    sleep(1);
}
?>

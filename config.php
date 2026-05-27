<?php
// config.php
session_start();

// Tentukan direktori tempat folder "engine" (server game) berada.
// Jika file web berada di folder yang sama dengan engine, gunakan __DIR__
// Tapi jika engine ada di /mnt/gamedisk/gameserver, ganti menjadi '/mnt/gamedisk/gameserver'
define('BASE_DIR', __DIR__); // <-- EDIT BARIS INI JIKA ENGINE ADA DI TEMPAT LAIN
define('SERVER_DIR', BASE_DIR . '/engine/server');
define('APP_NAME', 'Romestead');
define('APP_STEAM_ID', '4763510');
define('SERVER_EXE_NAME', 'Server.exe');
define('SERVER_EXE', SERVER_DIR . '/' . SERVER_EXE_NAME);
define('SERVER_DLL', SERVER_DIR . '/Server.dll');
define('ROMESTEAD_CONFIG_FILE', SERVER_DIR . '/config.json');

// Romestead configuration directories
define('SAVE_DIR', BASE_DIR . '/engine/savedata');
define('WORLD_SAVE_DIR', SERVER_DIR . '/saved_worlds');
define('SETTINGS_DIR', SAVE_DIR . '/Settings');
define('BACKUP_DIR', BASE_DIR . '/engine/backups');
define('LOG_FILE', '/tmp/romestead_server.log'); // Dipindahkan ke /tmp agar dipastikan selalu writable
define('DOTNET_BIN', '/opt/dotnet/dotnet');
define('AUTO_BACKUP_PREFIX', 'daily_backup_');
define('AUTO_BACKUP_RETENTION_DAYS', 3);

if (!file_exists(SAVE_DIR)) {
    mkdir(SAVE_DIR, 0777, true);
}
if (!file_exists(WORLD_SAVE_DIR)) {
    mkdir(WORLD_SAVE_DIR, 0777, true);
}
if (!file_exists(SETTINGS_DIR)) {
    mkdir(SETTINGS_DIR, 0777, true);
}
if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0777, true);
}

function getDefaultRomesteadConfig() {
    return [
        'AutoStartWorldName' => 'romestead_server',
        'AutoCreateAndLoadWorld' => true,
        'AutoCreateWorldSize' => 1,
        'AutoCreateWorldSeed' => null,
        'Password' => '',
        'Port' => 5580,
        'MaxPlayers' => 8,
        'EnableCheats' => false
    ];
}

function ensureRomesteadConfigFile() {
    if (file_exists(ROMESTEAD_CONFIG_FILE)) {
        return;
    }

    if (!is_dir(SERVER_DIR)) {
        @mkdir(SERVER_DIR, 0777, true);
    }

    @file_put_contents(ROMESTEAD_CONFIG_FILE, json_encode(getDefaultRomesteadConfig(), JSON_PRETTY_PRINT));
}

function readRomesteadConfig() {
    ensureRomesteadConfigFile();
    $content = file_exists(ROMESTEAD_CONFIG_FILE) ? @file_get_contents(ROMESTEAD_CONFIG_FILE) : false;
    $config = $content !== false ? json_decode($content, true) : [];
    if (!is_array($config)) {
        $config = [];
    }

    return array_replace(getDefaultRomesteadConfig(), $config);
}

function writeRomesteadConfig(array $config) {
    ensureRomesteadConfigFile();
    file_put_contents(ROMESTEAD_CONFIG_FILE, json_encode(array_replace(getDefaultRomesteadConfig(), $config), JSON_PRETTY_PRINT));
}

function getSaveBackupSourceDir() {
    if (is_dir(WORLD_SAVE_DIR) && count(scandir(WORLD_SAVE_DIR)) > 2) {
        return WORLD_SAVE_DIR;
    }

    return SAVE_DIR;
}

function createSaveBackup(string $prefix = 'backup_') {
    $saveDir = getSaveBackupSourceDir();
    $backupDir = BACKUP_DIR;

    if (!is_dir($saveDir) || count(scandir($saveDir)) <= 2) {
        return ['success' => false, 'message' => 'No save data found to backup.'];
    }

    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0777, true);
    }

    $timestamp = date('Y-m-d_His');
    $backupFile = $backupDir . '/' . $prefix . $timestamp . '.tar.gz';
    $command = 'tar -czf ' . escapeshellarg($backupFile) . ' -C ' . escapeshellarg(dirname($saveDir)) . ' ' . escapeshellarg(basename($saveDir)) . ' 2>&1';
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    if ($returnCode <= 1 && file_exists($backupFile)) {
        $sizeMB = round(filesize($backupFile) / 1024 / 1024, 2);
        $warning = ($returnCode === 1) ? ' (some files changed during backup - this is normal while server is running)' : '';
        return [
            'success' => true,
            'filename' => basename($backupFile),
            'size_mb' => $sizeMB,
            'message' => 'Backup created: ' . basename($backupFile) . " ({$sizeMB} MB)" . $warning
        ];
    }

    return ['success' => false, 'message' => 'Backup failed: ' . implode(' ', $output)];
}

function pruneOldAutomaticBackups(int $retentionDays = AUTO_BACKUP_RETENTION_DAYS) {
    $deleted = [];
    $cutoff = time() - ($retentionDays * 86400);
    $files = glob(BACKUP_DIR . '/' . AUTO_BACKUP_PREFIX . '*.tar.gz') ?: [];

    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            if (@unlink($file)) {
                $deleted[] = basename($file);
            }
        }
    }

    return $deleted;
}

// Function to check if server is running
// PENTING: Gunakan bracket trick agar pgrep tidak mendeteksi dirinya sendiri
function isServerRunning() {
    $processes = [];
    exec("pgrep -f '[d]otnet .*Server\\.dll'", $processes);
    
    // pgrep prints process IDs if found, so array count > 0 means it's running
    return count($processes) > 0;
}

// Function to kill the Romestead server process
function killRomesteadServer() {
    // 1. Find all PIDs matching the game server and kill them forcefully
    $pids = [];
    exec("pgrep -f '[d]otnet .*Server\\.dll'", $pids);
    foreach ($pids as $pid) {
        $pid = trim($pid);
        if (is_numeric($pid)) {
            exec("kill -9 " . escapeshellarg($pid) . " 2>/dev/null");
        }
    }

    $stdinPids = [];
    exec("pgrep -f '[t]ail -f /dev/null'", $stdinPids);
    foreach ($stdinPids as $pid) {
        $pid = trim($pid);
        if (is_numeric($pid)) {
            exec("kill -9 " . escapeshellarg($pid) . " 2>/dev/null");
        }
    }
    sleep(1);
}
?>

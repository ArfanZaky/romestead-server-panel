<?php
// api.php
require 'config.php';

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

// Prevent accidental server control from GET (prefetch/crawler/bookmark/open-url).
$serverControlActions = ['start', 'stop', 'restart'];
if (in_array($action, $serverControlActions, true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST for server control actions.']);
    exit;
}

if ($action === 'status') {
    $isRunning = isServerRunning();
    $ramMB = 0;
    $cpuLoad = 0;

    if ($isRunning) {
        // Get RAM of all Wine/VRising processes (RSS memory in KB via ps)
        // Di Linux, VRisingServer.exe berjalan lewat Wine, jadi kita hitung total RAM semua proses terkait
        $psOut = [];
        exec("ps -eo rss,command | grep -E '[V]RisingServer|[w]ine-preloader|[w]ineserver' | awk '{sum+=\$1} END {print sum}'", $psOut);
        if (!empty($psOut) && is_numeric(trim($psOut[0]))) {
            $ramMB = round((int) trim($psOut[0]) / 1024);
        }

        // Get System CPU Load (Overall) via top or /proc/stat 
        // Simplest one-liner in linux using top
        $cpuOut = [];
        exec("top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - $1}'", $cpuOut);
        if (!empty($cpuOut)) {
            $cpuLoad = round((float) $cpuOut[0]);
        }
    }

    echo json_encode([
        'status' => $isRunning ? 'online' : 'offline',
        'ram_mb' => $ramMB,
        'cpu_percent' => $cpuLoad
    ]);
    exit;
}

if ($action === 'start') {
    if (isServerRunning()) {
        echo json_encode(['success' => false, 'message' => 'Server is already running.']);
        exit;
    }

    if (!file_exists(SERVER_EXE)) {
        echo json_encode(['success' => false, 'message' => 'VRisingServer.exe not found! Is the installation complete?']);
        exit;
    }

    $exe = SERVER_EXE;
    $saveDir = SAVE_DIR;
    $logFile = LOG_FILE;
    $engineDir = dirname($logFile);

    // WINE ENVIRONMENT CHECK
    $hasWine = file_exists('/usr/bin/wine') || file_exists('/usr/bin/wine64');
    $hasXvfb = file_exists('/usr/bin/xvfb-run');
    if (!$hasWine || !$hasXvfb) {
        echo json_encode(['success' => false, 'message' => "Missing dependencies! Make sure 'wine' and 'xvfb' are installed on Linux."]);
        exit;
    }

    // WINE ENVIRONMENT CHECK
    // Wine menolak /tmp karena bukan milik www-data. Jadi kita buat folder khusus di /tmp yang dimiliki oleh web server.
    $tmpWineDir = '/tmp/wine_' . exec('whoami');
    if (!file_exists($tmpWineDir)) {
        @mkdir($tmpWineDir, 0777, true);
    }
    $winePrefix = "export HOME={$tmpWineDir} && export WINEPREFIX={$tmpWineDir}/.wine && ";


    // Read bind address if any
    $bindAddress = ' -address 0.0.0.0';
    $hostSettingsFile = SETTINGS_DIR . '/ServerHostSettings.json';
    if (file_exists($hostSettingsFile)) {
        $settingsData = json_decode(file_get_contents($hostSettingsFile), true);
        if (!empty($settingsData['BindAddress'])) {
            $bindAddress = ' -address ' . trim($settingsData['BindAddress']);
        }
    }

    // Linux Wine Launcher - output captured to log file
    $logFile = LOG_FILE;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] === SERVER STARTING ===\n");

    // CD to server dir + Set WINEPREFIX + Force Unity to log to stdout and skip graphics
    $serverDir = escapeshellarg(dirname($exe));
    $cmdExe = escapeshellarg(basename($exe));
    $command = $winePrefix . 'cd ' . $serverDir . ' && nohup xvfb-run --auto-servernum --server-args="-screen 0 1024x768x24" env WINEDEBUG=-all wine ' . $cmdExe . ' -batchmode -nographics -persistentDataPath ' . escapeshellarg($saveDir) . ' -logfile - >> ' . escapeshellarg($logFile) . ' 2>&1 &';

    exec($command);

    // wait a moment
    sleep(2);

    echo json_encode(['success' => true, 'message' => 'Start command sent. Check status.']);
    exit;
}

if ($action === 'stop') {
    if (!isServerRunning()) {
        echo json_encode(['success' => false, 'message' => 'Server is not running.']);
        exit;
    }

    killVRisingServer();

    echo json_encode(['success' => true, 'message' => 'Server stopped.']);
    exit;
}

if ($action === 'restart') {
    if (!isServerRunning()) {
        echo json_encode(['success' => false, 'message' => 'Server is not running. Use start instead.']);
        exit;
    }

    // Step 1: Kill the server completely
    killVRisingServer();

    // Step 2: Verify it's dead
    $maxWait = 10;
    $waited = 0;
    while (isServerRunning() && $waited < $maxWait) {
        sleep(1);
        $waited++;
    }

    if (isServerRunning()) {
        echo json_encode(['success' => false, 'message' => 'Failed to stop server after ' . $maxWait . ' seconds. Try stopping manually.']);
        exit;
    }

    // Step 3: Start the server again
    if (!file_exists(SERVER_EXE)) {
        echo json_encode(['success' => false, 'message' => 'Server stopped, but VRisingServer.exe not found for restart!']);
        exit;
    }

    $exe = SERVER_EXE;
    $saveDir = SAVE_DIR;

    // Read bind address if any
    $bindAddress = ' -address 0.0.0.0';
    $hostSettingsFile = SETTINGS_DIR . '/ServerHostSettings.json';
    if (file_exists($hostSettingsFile)) {
        $settingsData = json_decode(file_get_contents($hostSettingsFile), true);
        if (!empty($settingsData['BindAddress'])) {
            $bindAddress = ' -address ' . trim($settingsData['BindAddress']);
        }
    }

    // Linux Wine Launcher - output captured to log file
    $logFile = LOG_FILE;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] === SERVER RESTARTING ===\n");

    // WINE ENVIRONMENT CHECK
    $tmpWineDir = '/tmp/wine_' . exec('whoami');
    if (!file_exists($tmpWineDir)) {
        @mkdir($tmpWineDir, 0777, true);
    }
    $winePrefix = "export HOME={$tmpWineDir} && export WINEPREFIX={$tmpWineDir}/.wine && ";

    $serverDir = escapeshellarg(dirname($exe));
    $cmdExe = escapeshellarg(basename($exe));
    $command = $winePrefix . 'cd ' . $serverDir . ' && nohup xvfb-run --auto-servernum --server-args="-screen 0 1024x768x24" env WINEDEBUG=-all wine ' . $cmdExe . ' -batchmode -nographics -persistentDataPath ' . escapeshellarg($saveDir) . ' -logfile - >> ' . escapeshellarg($logFile) . ' 2>&1 &';

    exec($command);
    sleep(3);

    $restarted = isServerRunning();
    echo json_encode([
        'success' => true,
        'message' => $restarted ? 'Server restarted successfully!' : 'Restart command sent. Server may need a moment to start.'
    ]);
    exit;
}

// ==================== BACKUP & RESTORE ====================

if ($action === 'backup') {
    $saveDir = SAVE_DIR;
    $backupDir = BACKUP_DIR;

    // Check if savedata directory has content
    if (!is_dir($saveDir) || count(scandir($saveDir)) <= 2) {
        echo json_encode(['success' => false, 'message' => 'No save data found to backup.']);
        exit;
    }

    // Generate filename with timestamp
    $timestamp = date('Y-m-d_His');
    $backupFile = $backupDir . '/backup_' . $timestamp . '.tar.gz';

    // Create tar.gz backup of the entire savedata directory
    $command = 'tar -czf ' . escapeshellarg($backupFile) . ' -C ' . escapeshellarg(dirname($saveDir)) . ' ' . escapeshellarg(basename($saveDir)) . ' 2>&1';
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    // tar exit code 0 = success, 1 = warning (file changed during read, normal saat server running)
    // Hanya exit code >= 2 yang dianggap fatal error
    if ($returnCode <= 1 && file_exists($backupFile)) {
        $sizeMB = round(filesize($backupFile) / 1024 / 1024, 2);
        $warning = ($returnCode === 1) ? ' (some files changed during backup - this is normal while server is running)' : '';
        echo json_encode([
            'success' => true,
            'message' => "Backup created: backup_{$timestamp}.tar.gz ({$sizeMB} MB)" . $warning
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Backup failed: ' . implode(' ', $output)]);
    }
    exit;
}

if ($action === 'list_backups') {
    $backupDir = BACKUP_DIR;
    $backups = [];

    if (is_dir($backupDir)) {
        // Cari semua file .tar.gz (backup, pre_restore_safety, upload, dll)
        $files = glob($backupDir . '/*.tar.gz');
        // Sort newest first
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size_mb' => round(filesize($file) / 1024 / 1024, 2),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
    }

    echo json_encode(['success' => true, 'backups' => $backups]);
    exit;
}

if ($action === 'restore') {
    $filename = $_GET['file'] ?? '';

    if (empty($filename)) {
        echo json_encode(['success' => false, 'message' => 'No backup file specified.']);
        exit;
    }

    // Security: prevent path traversal
    $filename = basename($filename);
    $backupFile = BACKUP_DIR . '/' . $filename;

    if (!file_exists($backupFile)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found.']);
        exit;
    }

    // Server must be stopped before restore
    if (isServerRunning()) {
        echo json_encode(['success' => false, 'message' => 'Stop the server first before restoring a backup!']);
        exit;
    }

    $saveDir = SAVE_DIR;
    $parentDir = dirname($saveDir);
    $saveDirName = basename($saveDir);

    // Step 1: Remove current savedata
    exec('rm -rf ' . escapeshellarg($saveDir) . ' 2>&1', $rmOutput, $rmCode);

    // Step 2: Extract backup
    $command = 'tar -xzf ' . escapeshellarg($backupFile) . ' -C ' . escapeshellarg($parentDir) . ' 2>&1';
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    if ($returnCode === 0) {
        echo json_encode(['success' => true, 'message' => "Restore complete from: {$filename}. You can start the server now."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Restore failed: ' . implode(' ', $output)]);
    }
    exit;
}

// ==================== UPLOAD BACKUP / RESTORE ====================

if ($action === 'upload_backup') {
    if (!isset($_FILES['backupfile']) || $_FILES['backupfile']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['backupfile']['error'] ?? -1;
        $errMsgs = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit (php.ini).',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        $msg = $errMsgs[$errCode] ?? 'Upload error (code: ' . $errCode . ')';
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    $uploadedFile = $_FILES['backupfile']['tmp_name'];
    $originalName = basename($_FILES['backupfile']['name']);
    $fileSize = $_FILES['backupfile']['size'];
    $sizeMB = round($fileSize / 1024 / 1024, 2);

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $backupDir = BACKUP_DIR;
    $timestamp = date('Y-m-d_His');

    if ($ext === 'gz' || $ext === 'tgz' || (substr($originalName, -7) === '.tar.gz')) {
        $destFile = $backupDir . '/backup_upload_' . $timestamp . '.tar.gz';
        if (move_uploaded_file($uploadedFile, $destFile)) {
            echo json_encode(['success' => true, 'message' => "Backup uploaded: " . basename($destFile) . " ({$sizeMB} MB)"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
        }
    } elseif ($ext === 'zip') {
        $tmpExtract = '/tmp/upload_extract_' . $timestamp;
        @mkdir($tmpExtract, 0777, true);
        $output = [];
        $retCode = 0;
        exec('unzip -o ' . escapeshellarg($uploadedFile) . ' -d ' . escapeshellarg($tmpExtract) . ' 2>&1', $output, $retCode);
        if ($retCode !== 0) {
            exec('rm -rf ' . escapeshellarg($tmpExtract));
            echo json_encode(['success' => false, 'message' => 'Failed to extract ZIP: ' . implode(' ', $output)]);
            exit;
        }
        $destFile = $backupDir . '/backup_upload_' . $timestamp . '.tar.gz';
        exec('tar -czf ' . escapeshellarg($destFile) . ' -C ' . escapeshellarg($tmpExtract) . ' . 2>&1', $tarOut, $tarCode);
        exec('rm -rf ' . escapeshellarg($tmpExtract));
        if ($tarCode === 0 && file_exists($destFile)) {
            $newSizeMB = round(filesize($destFile) / 1024 / 1024, 2);
            echo json_encode(['success' => true, 'message' => "ZIP uploaded & converted: " . basename($destFile) . " ({$newSizeMB} MB)"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to repack ZIP as tar.gz.']);
        }
    } else {
        $destFile = $backupDir . '/backup_upload_' . $timestamp . '_' . $originalName;
        if (move_uploaded_file($uploadedFile, $destFile)) {
            echo json_encode(['success' => true, 'message' => "File uploaded: " . basename($destFile) . " ({$sizeMB} MB). Note: only .tar.gz files can be restored via dashboard."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
        }
    }
    exit;
}

// Upload & directly restore savegame
if ($action === 'upload_restore') {
    if (isServerRunning()) {
        echo json_encode(['success' => false, 'message' => 'Stop the server first before restoring!']);
        exit;
    }

    if (!isset($_FILES['backupfile']) || $_FILES['backupfile']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['backupfile']['error'] ?? -1;
        echo json_encode(['success' => false, 'message' => 'Upload error (code: ' . $errCode . ')']);
        exit;
    }

    $uploadedFile = $_FILES['backupfile']['tmp_name'];
    $originalName = basename($_FILES['backupfile']['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $saveDir = SAVE_DIR;
    $parentDir = dirname($saveDir);

    // Safety backup current savedata
    $backupDir = BACKUP_DIR;
    if (is_dir($saveDir) && count(scandir($saveDir)) > 2) {
        $safetyBackup = $backupDir . '/pre_restore_safety_' . date('Y-m-d_His') . '.tar.gz';
        exec('tar -czf ' . escapeshellarg($safetyBackup) . ' -C ' . escapeshellarg($parentDir) . ' ' . escapeshellarg(basename($saveDir)) . ' 2>&1');
    }

    // Clear current savedata
    exec('rm -rf ' . escapeshellarg($saveDir) . '/* 2>&1');
    @mkdir($saveDir, 0777, true);
    @mkdir($saveDir . '/Settings', 0777, true);

    // Extract uploaded file
    $output = [];
    $retCode = 0;

    if ($ext === 'gz' || $ext === 'tgz' || (substr($originalName, -7) === '.tar.gz')) {
        $peekOutput = [];
        exec('tar -tzf ' . escapeshellarg($uploadedFile) . ' | head -5', $peekOutput);
        $firstEntry = $peekOutput[0] ?? '';
        if (strpos($firstEntry, 'savedata/') === 0) {
            exec('rm -rf ' . escapeshellarg($saveDir) . ' 2>&1');
            exec('tar -xzf ' . escapeshellarg($uploadedFile) . ' -C ' . escapeshellarg($parentDir) . ' 2>&1', $output, $retCode);
        } else {
            exec('tar -xzf ' . escapeshellarg($uploadedFile) . ' -C ' . escapeshellarg($saveDir) . ' 2>&1', $output, $retCode);
        }
    } elseif ($ext === 'zip') {
        exec('unzip -o ' . escapeshellarg($uploadedFile) . ' -d ' . escapeshellarg($saveDir) . ' 2>&1', $output, $retCode);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported file format. Use .tar.gz or .zip']);
        exit;
    }

    // Fix permissions
    exec('chmod -R 777 ' . escapeshellarg($saveDir) . ' 2>&1');

    if ($retCode === 0) {
        $hasSaves = false;
        if (is_dir($saveDir . '/Saves')) $hasSaves = true;
        $dirs = @scandir($saveDir);
        foreach ($dirs as $d) {
            if ($d === '.' || $d === '..') continue;
            if (is_dir($saveDir . '/' . $d . '/Saves')) {
                exec('cp -a ' . escapeshellarg($saveDir . '/' . $d) . '/* ' . escapeshellarg($saveDir) . '/ 2>&1');
                exec('rm -rf ' . escapeshellarg($saveDir . '/' . $d) . ' 2>&1');
                $hasSaves = true;
                break;
            }
        }
        $warning = $hasSaves ? '' : ' WARNING: No "Saves" folder found in extracted data - the server may not find the world save.';
        echo json_encode(['success' => true, 'message' => 'Save data restored from upload! You can start the server now.' . $warning]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Extraction failed: ' . implode(' ', $output)]);
    }
    exit;
}

if ($action === 'delete_backup') {
    $filename = $_GET['file'] ?? '';

    if (empty($filename)) {
        echo json_encode(['success' => false, 'message' => 'No backup file specified.']);
        exit;
    }

    // Security: prevent path traversal
    $filename = basename($filename);
    $backupFile = BACKUP_DIR . '/' . $filename;

    if (!file_exists($backupFile)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found.']);
        exit;
    }

    if (unlink($backupFile)) {
        echo json_encode(['success' => true, 'message' => "Backup deleted: {$filename}"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete backup file.']);
    }
    exit;
}

// Debug: lihat semua file settings dan isi password-nya
if ($action === 'debug_settings') {
    $results = [];

    // Scan semua ServerHostSettings.json di seluruh engine/savedata
    $baseDir = SAVE_DIR;
    if (is_dir($baseDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'ServerHostSettings.json') {
                $path = $file->getPathname();
                $data = json_decode(file_get_contents($path), true);
                $results[] = [
                    'path' => $path,
                    'has_password_key' => array_key_exists('Password', $data ?? []),
                    'password_value' => $data['Password'] ?? '(KEY NOT EXISTS)',
                    'name' => $data['Name'] ?? '(no name)',
                ];
            }
        }
    }

    // Juga cek default settings di server installation
    $defaultFile = SERVER_DIR . '/VRisingServer_Data/StreamingAssets/Settings/ServerHostSettings.json';
    if (file_exists($defaultFile)) {
        $data = json_decode(file_get_contents($defaultFile), true);
        $results[] = [
            'path' => $defaultFile . ' [DEFAULT/TEMPLATE]',
            'has_password_key' => array_key_exists('Password', $data ?? []),
            'password_value' => $data['Password'] ?? '(KEY NOT EXISTS)',
            'name' => $data['Name'] ?? '(no name)',
        ];
    }

    echo json_encode(['success' => true, 'files_found' => count($results), 'settings' => $results], JSON_PRETTY_PRINT);
    exit;
}

// Force hapus password dari SEMUA file ServerHostSettings.json
if ($action === 'force_remove_password') {
    $count = 0;
    $baseDir = SAVE_DIR;

    if (is_dir($baseDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'ServerHostSettings.json') {
                $path = $file->getPathname();
                $data = json_decode(file_get_contents($path), true);
                if ($data && array_key_exists('Password', $data)) {
                    unset($data['Password']);
                    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
                    $count++;
                }
            }
        }
    }

    // Juga bersihkan dari default/template
    $defaultFile = SERVER_DIR . '/VRisingServer_Data/StreamingAssets/Settings/ServerHostSettings.json';
    if (file_exists($defaultFile)) {
        $data = json_decode(file_get_contents($defaultFile), true);
        if ($data && array_key_exists('Password', $data)) {
            unset($data['Password']);
            file_put_contents($defaultFile, json_encode($data, JSON_PRETTY_PRINT));
            $count++;
        }
    }

    echo json_encode(['success' => true, 'message' => "Password removed from {$count} settings file(s). Restart the server now."]);
    exit;
}

// ==================== LOG VIEWER ====================

if ($action === 'log') {
    $logFile = LOG_FILE;
    $lines = isset($_GET['lines']) ? (int) $_GET['lines'] : 100;
    $lines = max(10, min($lines, 500)); // clamp between 10 and 500

    if (!file_exists($logFile)) {
        echo json_encode(['success' => true, 'log' => '(No log file yet. Start the server to generate logs.)', 'lines' => 0]);
        exit;
    }

    // Read last N lines efficiently using tail
    $output = [];
    exec('tail -n ' . $lines . ' ' . escapeshellarg($logFile), $output);

    $logContent = implode("\n", $output);

    // Also get file size info
    $fileSize = filesize($logFile);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);

    echo json_encode([
        'success' => true,
        'log' => $logContent,
        'lines' => count($output),
        'file_size_mb' => $fileSizeMB
    ]);
    exit;
}

if ($action === 'clear_log') {
    $logFile = LOG_FILE;
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] === LOG CLEARED ===\n");
    echo json_encode(['success' => true, 'message' => 'Log cleared.']);
    exit;
}

if ($action === 'install_server') {
    $serverDir = SERVER_DIR;
    $engineDir = BASE_DIR . '/engine';
    $logFile = '/tmp/steamcmd_install.log';
    $scriptFile = '/tmp/steamcmd_install_script.txt';
    
    $hasSteamCmd = file_exists('/usr/bin/steamcmd') || file_exists('/usr/games/steamcmd.sh');
    if (!$hasSteamCmd) {
        echo json_encode(['success' => false, 'message' => 'steamcmd not found on this system!']);
        exit;
    }
    
    @unlink($logFile);
    
    if (!file_exists($serverDir)) {
        @mkdir($serverDir, 0777, true);
    }
    
    // Bersihkan SEMUA cache SteamCMD (penyebab utama "Missing configuration")
    $steamDir = $engineDir . '/Steam';
    if (is_dir($steamDir . '/appcache')) {
        exec('rm -rf ' . escapeshellarg($steamDir . '/appcache'));
    }
    
    // Buat script file untuk SteamCMD (lebih reliable daripada command line args)
    $scriptContent = "@ShutdownOnFailedCommand 0\n";
    $scriptContent .= "@NoPromptForPassword 1\n";
    $scriptContent .= "@sSteamCmdForcePlatformType windows\n";
    $scriptContent .= "@sSteamCmdForcePlatformBitness 64\n";
    $scriptContent .= "login anonymous\n";
    $scriptContent .= "force_install_dir " . $serverDir . "\n";
    $scriptContent .= "app_info_update 1\n";
    $scriptContent .= "app_update 1829350 validate\n";
    $scriptContent .= "app_update 1829350 validate\n";
    $scriptContent .= "quit\n";
    file_put_contents($scriptFile, $scriptContent);
    
    // Jalankan SteamCMD dengan script file di background
    $cmd = "nohup env HOME=" . escapeshellarg($engineDir) . " /usr/games/steamcmd.sh +runscript " . escapeshellarg($scriptFile) . " > " . escapeshellarg($logFile) . " 2>&1 &";
    exec($cmd);
    
    echo json_encode(['success' => true, 'message' => 'Installation started.']);
    exit;
}

if ($action === 'install_status') {
    $logFile = '/tmp/steamcmd_install.log';
    if (!file_exists($logFile)) {
        echo json_encode(['log' => 'Waiting for installation to start...', 'installing' => false]);
        exit;
    }
    
    // Limit log size to prevent huge payload
    $output = [];
    exec("tail -n 100 " . escapeshellarg($logFile), $output);
    $log = implode("\n", $output);
    
    $installing = true;
    
    // Check if finished via log or process
    $fullLog = file_get_contents($logFile);
    if (strpos($fullLog, "Success! App '1829350' fully installed.") !== false || strpos($fullLog, "Error!") !== false) {
        $installing = false;
    } else {
        $pid = exec("pgrep -f 'app_update 1829350'");
        if (empty($pid) && filemtime($logFile) < (time() - 10)) {
            $installing = false; // Process died and 10 seconds passed
        }
    }
    
    echo json_encode(['log' => $log, 'installing' => $installing]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
?>

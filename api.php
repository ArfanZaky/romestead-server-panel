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
        // Get RAM of the Romestead .NET process (RSS memory in KB via ps)
        $psOut = [];
        exec("ps -eo rss,command | grep -E '[d]otnet .*Server\\.dll' | awk '{sum+=\$1} END {print sum}'", $psOut);
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

    if (!file_exists(SERVER_DLL)) {
        echo json_encode(['success' => false, 'message' => 'Server.dll not found! Is the installation complete?']);
        exit;
    }

    $logFile = LOG_FILE;
    $config = readRomesteadConfig();
    $config['AutoCreateAndLoadWorld'] = true;
    if (empty($config['AutoStartWorldName'])) {
        $config['AutoStartWorldName'] = 'romestead_server';
    }
    if ($config['Password'] === null) {
        $config['Password'] = '';
    }
    writeRomesteadConfig($config);

    if (!file_exists(DOTNET_BIN)) {
        echo json_encode(['success' => false, 'message' => ".NET runtime not found at " . DOTNET_BIN]);
        exit;
    }

    // Native .NET launcher - output captured to log file
    $logFile = LOG_FILE;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] === SERVER STARTING ===\n");

    $serverDir = escapeshellarg(SERVER_DIR);
    $command = 'cd ' . $serverDir . ' && nohup bash -lc ' . escapeshellarg('(printf "\n"; tail -f /dev/null) | env HOME=' . SERVER_DIR . ' DOTNET_CLI_HOME=' . SERVER_DIR . ' ' . DOTNET_BIN . ' Server.dll') . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

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

    killRomesteadServer();

    echo json_encode(['success' => true, 'message' => 'Server stopped.']);
    exit;
}

if ($action === 'restart') {
    if (!isServerRunning()) {
        echo json_encode(['success' => false, 'message' => 'Server is not running. Use start instead.']);
        exit;
    }

    // Step 1: Kill the server completely
    killRomesteadServer();

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
    if (!file_exists(SERVER_DLL)) {
        echo json_encode(['success' => false, 'message' => 'Server stopped, but Server.dll not found for restart!']);
        exit;
    }

    // Native .NET launcher - output captured to log file
    $logFile = LOG_FILE;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] === SERVER RESTARTING ===\n");

    $serverDir = escapeshellarg(SERVER_DIR);
    $command = 'cd ' . $serverDir . ' && nohup bash -lc ' . escapeshellarg('(printf "\n"; tail -f /dev/null) | env HOME=' . SERVER_DIR . ' DOTNET_CLI_HOME=' . SERVER_DIR . ' ' . DOTNET_BIN . ' Server.dll') . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';

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
    echo json_encode(createSaveBackup('backup_'));
    exit;
}

if ($action === 'daily_backup') {
    $result = createSaveBackup(AUTO_BACKUP_PREFIX);
    $result['deleted_old_backups'] = pruneOldAutomaticBackups(AUTO_BACKUP_RETENTION_DAYS);
    echo json_encode($result);
    exit;
}

if ($action === 'list_backups') {
    pruneOldAutomaticBackups(AUTO_BACKUP_RETENTION_DAYS);

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

    $peekOutput = [];
    exec('tar -tzf ' . escapeshellarg($backupFile) . ' | head -1', $peekOutput);
    $firstEntry = $peekOutput[0] ?? '';

    if (strpos($firstEntry, 'saved_worlds/') === 0) {
        $saveDir = WORLD_SAVE_DIR;
        $parentDir = SERVER_DIR;
    } else {
        $saveDir = SAVE_DIR;
        $parentDir = dirname($saveDir);
    }

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

// ==================== LOG VIEWER ====================

if ($action === 'log') {
    $logFile = LOG_FILE;
    $lines = isset($_GET['lines']) ? (int) $_GET['lines'] : 100;
    $lines = max(10, min($lines, 500)); // clamp between 10 and 500
    $maxBytes = 200 * 1024;

    if (!file_exists($logFile)) {
        echo json_encode([
            'success' => true,
            'log' => '(No log file yet. Start the server to generate logs.)',
            'lines' => 0,
            'file_size_mb' => 0,
            'truncated' => false
        ]);
        exit;
    }

    $fileSize = filesize($logFile);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
    $truncated = $fileSize > $maxBytes;

    // Romestead world generation can write progress updates as one huge line.
    // Limit by bytes first so the browser never receives multi-MB log payloads.
    $output = [];
    exec('tail -c ' . $maxBytes . ' ' . escapeshellarg($logFile), $output);
    $logContent = implode("\n", $output);
    $logContent = str_replace("\r", "\n", $logContent);
    $logContent = preg_replace("/[ \t]{20,}/", "\n", $logContent);

    $logLines = array_values(array_filter(
        preg_split("/\n/", $logContent),
        static function ($line) {
            return trim($line) !== '';
        }
    ));
    if (count($logLines) > $lines) {
        $logLines = array_slice($logLines, -$lines);
    }
    $logContent = implode("\n", $logLines);

    echo json_encode([
        'success' => true,
        'log' => $logContent,
        'lines' => count($logLines),
        'file_size_mb' => $fileSizeMB,
        'truncated' => $truncated,
        'max_payload_kb' => round($maxBytes / 1024)
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
    $scriptContent .= "force_install_dir " . $serverDir . "\n";
    $scriptContent .= "login anonymous\n";
    $scriptContent .= "app_info_update 1\n";
    $scriptContent .= "app_update 4763510 validate\n";
    $scriptContent .= "app_update 4763510 validate\n";
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
    if (strpos($fullLog, "Success! App '4763510' fully installed.") !== false || strpos($fullLog, "Error!") !== false) {
        $installing = false;
    } else {
        $pid = exec("pgrep -f 'app_update 4763510'");
        if (empty($pid) && filemtime($logFile) < (time() - 10)) {
            $installing = false; // Process died and 10 seconds passed
        }
    }
    
    echo json_encode(['log' => $log, 'installing' => $installing]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
?>

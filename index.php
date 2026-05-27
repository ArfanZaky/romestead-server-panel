<?php
// index.php
require 'config.php';

$data = readRomesteadConfig();
$currentPort = $data['Port'] ?? 5580;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Romestead Server Manager</title>
    <link rel="stylesheet" href="assets/style.css">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <h1><i class="fa-solid fa-server"></i> Romestead <span>Panel</span></h1>
        <div class="nav-links">
            <a href="index.php" class="active">Dashboard</a>
            <a href="settings.php">Host Settings</a>
            <a href="game_settings.php">Server Config</a>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-grid">
            <!-- Action Card -->
            <div class="glass-panel status-card">
                <div id="statusBadge" class="status-badge status-offline">Checking Status...</div>
                
                <h2 style="margin-bottom: 2rem;">Server Controls</h2>
                
                <div style="display: flex; gap: 1rem;">
                    <button id="btnStart" class="control-btn btn-primary" onclick="toggleServer('start')">
                        <i class="fa-solid fa-play"></i> Start Server
                        <div class="loader" id="loaderStart"></div>
                    </button>
                    <button id="btnStop" class="control-btn btn-stop" onclick="toggleServer('stop')" style="display:none;">
                        <i class="fa-solid fa-stop"></i> Stop Server
                        <div class="loader" id="loaderStop"></div>
                    </button>
                    <button id="btnRestart" class="control-btn btn-restart" onclick="toggleServer('restart')" style="display:none;">
                        <i class="fa-solid fa-rotate-right"></i> Restart
                        <div class="loader" id="loaderRestart"></div>
                    </button>
                </div>
                
                <p id="actionMessage" style="margin-top: 1.5rem; color: var(--text-muted); font-size: 0.9rem;"></p>
            </div>

            <!-- Info Card -->
            <div class="glass-panel">
                <h3 style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Server Information</h3>
                <ul class="info-list">
                    <li>
                        <span class="label">Game Port</span>
                        <span class="value"><?php echo $currentPort; ?></span>
                    </li>
                    <li>
                        <span class="label">Save Name</span>
                        <span class="value" style="font-weight:bold; color: #f0c36d;"><?php echo htmlspecialchars($data['AutoStartWorldName'] ?? 'romestead_server'); ?></span>
                    </li>
                    <li>
                        <span class="label">Installation Path</span>
                        <div style="text-align: right;">
                            <span class="value" style="font-size: 0.8rem; display:block; margin-bottom: 5px;"><?php echo SERVER_DIR; ?></span>
                            <button class="btn-backup" onclick="installServer()" style="font-size: 0.75rem; padding: 0.3rem 0.8rem;">
                                <i class="fa-solid fa-download"></i> Install / Update Server
                            </button>
                        </div>
                    </li>
                    <li>
                        <span class="label">Save Data Path</span>
                        <span class="value" style="font-size: 0.8rem;"><?php echo WORLD_SAVE_DIR; ?></span>
                    </li>
                </ul>
            </div>

            <!-- Resource Card -->
            <div class="glass-panel">
                <h3 style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Resource Monitor</h3>
                
                <div style="margin-bottom: 1rem;">
                    <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                        <span class="label">System CPU</span>
                        <span class="value" id="valCpu" style="font-weight: 600;">0%</span>
                    </div>
                    <div style="width: 100%; background: rgba(0,0,0,0.3); border-radius: 4px; height: 10px;">
                        <div id="barCpu" style="width: 0%; height: 10px; background: var(--primary); border-radius: 4px; transition: 0.5s;"></div>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                        <span class="label">App Memory (RAM)</span>
                        <span class="value" id="valRam" style="font-weight: 600;">0 MB</span>
                    </div>
                    <!-- Assuming 8GB max for progress bar visualization basis -->
                    <div style="width: 100%; background: rgba(0,0,0,0.3); border-radius: 4px; height: 10px;">
                        <div id="barRam" style="width: 0%; height: 10px; background: #2a9d8f; border-radius: 4px; transition: 0.5s;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup & Restore Section (Full Width) -->
        <div class="glass-panel" style="margin-top: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                <h3><i class="fa-solid fa-hard-drive"></i> Backup & Restore</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="control-btn btn-backup" onclick="openUploadModal()" style="background: rgba(42, 157, 143, 0.15); border-color: #2a9d8f;">
                        <i class="fa-solid fa-upload"></i> Upload Save
                    </button>
                    <button id="btnBackup" class="control-btn btn-backup" onclick="createBackup()">
                        <i class="fa-solid fa-download"></i> Create Backup
                        <div class="loader" id="loaderBackup"></div>
                    </button>
                </div>
            </div>
            
            <p id="backupMessage" style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-muted); min-height: 1.2rem;"></p>
            <p style="margin-bottom: 1rem; font-size: 0.82rem; color: var(--text-muted);">
                Auto backup runs daily at 03:00 UTC and keeps the last 3 days of daily backups.
            </p>

            <div id="backupListContainer">
                <div class="backup-empty" id="backupEmpty">
                    <i class="fa-solid fa-box-open" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                    <p>No backups found. Create one to protect your save data!</p>
                </div>
                <table class="backup-table" id="backupTable" style="display: none;">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Date</th>
                            <th>Size</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="backupTableBody">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Server Log Console (Full Width) -->
        <div class="glass-panel" style="margin-top: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                <h3><i class="fa-solid fa-terminal"></i> Server Console</h3>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span id="logInfo" style="font-size: 0.75rem; color: var(--text-muted); margin-right: 0.5rem;"></span>
                    <select id="logLines" class="log-select" onchange="loadLog()">
                        <option value="50">50 lines</option>
                        <option value="100" selected>100 lines</option>
                        <option value="200">200 lines</option>
                        <option value="500">500 lines</option>
                    </select>
                    <button id="btnAutoScroll" class="action-btn log-toggle-btn active" onclick="toggleAutoScroll()" title="Auto-scroll">
                        <i class="fa-solid fa-angles-down"></i>
                    </button>
                    <button id="btnPauseLog" class="action-btn log-toggle-btn" onclick="toggleLogPause()" title="Pause log refresh">
                        <i class="fa-solid fa-pause" id="pauseIcon"></i>
                    </button>
                    <button class="action-btn" onclick="clearLog()" title="Clear log">
                        <i class="fa-solid fa-eraser"></i>
                    </button>
                </div>
            </div>
            <div id="logConsole" class="log-console">
                <pre id="logContent">Loading logs...</pre>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div id="restoreModal" class="modal-overlay" style="display: none;">
        <div class="modal-box glass-panel">
            <h3 style="margin-bottom: 1rem; color: #f0c36d;"><i class="fa-solid fa-triangle-exclamation"></i> Confirm Restore</h3>
            <p style="margin-bottom: 0.5rem; color: var(--text-muted);">This will <strong style="color: var(--primary);">replace all current save data</strong> with the backup:</p>
            <p id="restoreFileName" style="margin-bottom: 1.5rem; font-weight: 600; color: var(--text-main); word-break: break-all;"></p>
            <p style="margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.85rem;">
                <i class="fa-solid fa-info-circle"></i> The server must be <strong>stopped</strong> to restore. This action cannot be undone.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="control-btn btn-stop" onclick="closeRestoreModal()">Cancel</button>
                <button class="control-btn btn-primary" id="btnConfirmRestore" onclick="confirmRestore()">
                    <i class="fa-solid fa-rotate-left"></i> Restore Now
                    <div class="loader" id="loaderRestore"></div>
                </button>
            </div>
        </div>
    </div>

    <!-- Upload Save Modal -->
    <div id="uploadModal" class="modal-overlay" style="display: none;">
        <div class="modal-box glass-panel" style="max-width: 550px;">
            <h3 style="margin-bottom: 1rem; color: #2a9d8f;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Save Data</h3>
            <p style="margin-bottom: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                Upload your recovery save file. Supported formats: <strong>.tar.gz</strong>, <strong>.zip</strong>
            </p>

            <!-- Drop Zone -->
            <div id="uploadDropZone" class="upload-dropzone" onclick="document.getElementById('uploadFileInput').click();">
                <input type="file" id="uploadFileInput" accept=".tar.gz,.tgz,.gz,.zip" style="display:none;" onchange="handleFileSelect(this)">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2.5rem; color: #2a9d8f; margin-bottom: 0.8rem;"></i>
                <p id="uploadFileName" style="font-weight: 600; margin-bottom: 0.3rem;">Click or drag file here</p>
                <p id="uploadFileSize" style="font-size: 0.8rem; color: var(--text-muted);">Max 500 MB • .tar.gz or .zip</p>
            </div>

            <!-- Progress Bar -->
            <div id="uploadProgressContainer" style="display: none; margin-top: 1rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Uploading...</span>
                    <span id="uploadProgressText" style="font-size: 0.8rem; color: var(--text-main); font-weight: 600;">0%</span>
                </div>
                <div style="width: 100%; background: rgba(0,0,0,0.3); border-radius: 4px; height: 8px; overflow: hidden;">
                    <div id="uploadProgressBar" style="width: 0%; height: 8px; background: linear-gradient(90deg, #2a9d8f, #52d4c7); border-radius: 4px; transition: width 0.3s;"></div>
                </div>
            </div>

            <p id="uploadMessage" style="margin-top: 1rem; font-size: 0.85rem; min-height: 1.2rem;"></p>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.8rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button class="control-btn btn-stop" onclick="closeUploadModal()">Cancel</button>
                <button class="control-btn btn-backup" id="btnUploadBackup" onclick="uploadFile('upload_backup')" disabled style="background: rgba(42, 157, 143, 0.15); border-color: #2a9d8f;">
                    <i class="fa-solid fa-box-archive"></i> Save as Backup
                </button>
                <button class="control-btn btn-primary" id="btnUploadRestore" onclick="uploadFile('upload_restore')" disabled>
                    <i class="fa-solid fa-rotate-left"></i> Upload & Restore
                </button>
            </div>
        </div>
    </div>

    <!-- Script that handles install status checks -->
    <div id="installModal" class="modal-overlay" style="display:none;">
        <div class="glass-panel modal-box" style="max-width: 800px;">
                <h3 style="margin-bottom: 1rem; color: #f0c36d;"><i class="fa-solid fa-cloud-arrow-down"></i> Installation Progress</h3>
            <p style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-muted);">
                Downloading Romestead Dedicated Server via SteamCMD (App ID <?php echo APP_STEAM_ID; ?>).<br>
                This process can take 10-30 minutes depending on your server's internet speed.
            </p>
            <div id="installLogConsole" class="log-console" style="margin-bottom: 1.5rem; height: 300px; font-size: 0.7rem; background: rgba(0,0,0,0.5);">
                Loading...
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn-stop" onclick="closeInstallModal()" style="padding: 0.6rem 1.5rem;">Close Status</button>
            </div>
        </div>
    </div>

    <script>
        let pendingRestoreFile = '';

        function checkStatus() {
            fetch('api.php?action=status')
                .then(res => res.json())
                .then(data => {
                    const badge = document.getElementById('statusBadge');
                    const btnStart = document.getElementById('btnStart');
                    const btnStop = document.getElementById('btnStop');
                    const btnRestart = document.getElementById('btnRestart');

                    if(data.status === 'online') {
                        badge.className = 'status-badge status-online';
                        badge.innerHTML = '<i class="fa-solid fa-check-circle"></i> SERVER ONLINE';
                        btnStart.style.display = 'none';
                        btnStop.style.display = 'inline-flex';
                        btnRestart.style.display = 'inline-flex';
                        
                        // Update resources
                        document.getElementById('valCpu').innerText = data.cpu_percent + '%';
                        document.getElementById('barCpu').style.width = data.cpu_percent + '%';
                        
                        document.getElementById('valRam').innerText = data.ram_mb + ' MB';
                        // Max visualized bound for RAM bar = 8000 MB
                        let ramPct = Math.min((data.ram_mb / 8000) * 100, 100);
                        document.getElementById('barRam').style.width = ramPct + '%';
                    } else {
                        badge.className = 'status-badge status-offline';
                        badge.innerHTML = '<i class="fa-solid fa-times-circle"></i> SERVER OFFLINE';
                        btnStart.style.display = 'inline-flex';
                        btnStop.style.display = 'none';
                        btnRestart.style.display = 'none';
                        
                        // Reset resources
                        document.getElementById('valCpu').innerText = '0%';
                        document.getElementById('barCpu').style.width = '0%';
                        document.getElementById('valRam').innerText = '0 MB';
                        document.getElementById('barRam').style.width = '0%';
                    }
                })
                .catch(err => console.error(err));
        }

        let installStatusTimer = null;

        function closeInstallModal() {
            document.getElementById('installModal').style.display = 'none';
            if (installStatusTimer) clearInterval(installStatusTimer);
        }

        function trackInstallStatus() {
            const installConsole = document.getElementById('installLogConsole');
            document.getElementById('installModal').style.display = 'flex';
            installConsole.innerHTML = '<pre>Fetching status...</pre>';
            
            if (installStatusTimer) clearInterval(installStatusTimer);
            
            installStatusTimer = setInterval(() => {
                fetch('api.php?action=install_status')
                    .then(res => res.json())
                    .then(data => {
                        if (data.log) {
                            installConsole.innerHTML = '<pre>' + data.log + '</pre>';
                            installConsole.scrollTop = installConsole.scrollHeight;
                        }
                        
                        // If no longer installing but has status
                        if (!data.installing) {
                            clearInterval(installStatusTimer);
                            installConsole.innerHTML += '\n<pre style="color:#2a9d8f;">=== INSTALLATION FINISHED ===</pre>\n';
                        }
                    })
                    .catch(e => console.error(e));
            }, 3000);
        }

        function installServer() {
            if (!confirm('This will download ~3GB of game server files via SteamCMD. Make sure the server is STOPPED before proceeding. Continue?')) return;
            
            fetch('api.php?action=install_server')
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success || data.message.includes("already in progress")) {
                        trackInstallStatus();
                    }
                })
                .catch(err => alert('Request failed: ' + err));
        }

        function toggleServer(action) {
            if (action === 'restart' && !confirm('Restart Romestead server now? Connected players will be disconnected.')) {
                return;
            }
            if (action === 'stop' && !confirm('Stop Romestead server now? Connected players will be disconnected.')) {
                return;
            }

            const btnMap = { start: 'btnStart', stop: 'btnStop', restart: 'btnRestart' };
            const loaderMap = { start: 'loaderStart', stop: 'loaderStop', restart: 'loaderRestart' };
            const msgMap = { start: 'Starting server...', stop: 'Stopping server...', restart: 'Restarting server... This may take a moment.' };

            const btn = document.getElementById(btnMap[action]);
            const loader = document.getElementById(loaderMap[action]);
            const msg = document.getElementById('actionMessage');

            btn.disabled = true;
            loader.style.display = 'inline-block';
            msg.innerText = msgMap[action] || 'Processing...';

            fetch('api.php?action=' + action, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    msg.innerText = data.message;
                    msg.style.color = data.success ? 'var(--secondary)' : 'var(--primary)';
                    // Re-check status after a delay
                    setTimeout(checkStatus, 3000);
                })
                .finally(() => {
                    btn.disabled = false;
                    loader.style.display = 'none';
                });
        }

        // ==================== BACKUP FUNCTIONS ====================

        function createBackup() {
            const btn = document.getElementById('btnBackup');
            const loader = document.getElementById('loaderBackup');
            const msg = document.getElementById('backupMessage');

            btn.disabled = true;
            loader.style.display = 'inline-block';
            msg.innerText = 'Creating backup... Please wait.';
            msg.style.color = 'var(--text-muted)';

            fetch('api.php?action=backup')
                .then(res => res.json())
                .then(data => {
                    msg.innerText = data.message;
                    msg.style.color = data.success ? 'var(--secondary)' : 'var(--primary)';
                    if (data.success) {
                        loadBackupList();
                    }
                })
                .catch(err => {
                    msg.innerText = 'Error creating backup.';
                    msg.style.color = 'var(--primary)';
                })
                .finally(() => {
                    btn.disabled = false;
                    loader.style.display = 'none';
                });
        }

        function loadBackupList() {
            fetch('api.php?action=list_backups')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('backupTableBody');
                    const table = document.getElementById('backupTable');
                    const empty = document.getElementById('backupEmpty');
                    
                    tbody.innerHTML = '';

                    if (data.backups && data.backups.length > 0) {
                        table.style.display = 'table';
                        empty.style.display = 'none';

                        data.backups.forEach((b, idx) => {
                            const tr = document.createElement('tr');
                            tr.style.animation = `slideUp 0.3s ease-out ${idx * 0.05}s both`;
                            tr.innerHTML = `
                                <td>
                                    <i class="fa-solid fa-file-zipper" style="color: #f0c36d; margin-right: 0.5rem;"></i>
                                    ${b.filename}
                                </td>
                                <td>${b.date}</td>
                                <td>${b.size_mb} MB</td>
                                <td style="text-align: right;">
                                    <button class="action-btn action-restore" onclick="openRestoreModal('${b.filename}')" title="Restore this backup">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <button class="action-btn action-delete" onclick="deleteBackup('${b.filename}')" title="Delete this backup">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        table.style.display = 'none';
                        empty.style.display = 'flex';
                    }
                });
        }

        function openRestoreModal(filename) {
            pendingRestoreFile = filename;
            document.getElementById('restoreFileName').innerText = filename;
            document.getElementById('restoreModal').style.display = 'flex';
        }

        function closeRestoreModal() {
            pendingRestoreFile = '';
            document.getElementById('restoreModal').style.display = 'none';
        }

        function confirmRestore() {
            if (!pendingRestoreFile) return;

            const btn = document.getElementById('btnConfirmRestore');
            const loader = document.getElementById('loaderRestore');
            const msg = document.getElementById('backupMessage');

            btn.disabled = true;
            loader.style.display = 'inline-block';

            fetch('api.php?action=restore&file=' + encodeURIComponent(pendingRestoreFile))
                .then(res => res.json())
                .then(data => {
                    msg.innerText = data.message;
                    msg.style.color = data.success ? 'var(--secondary)' : 'var(--primary)';
                    closeRestoreModal();
                })
                .catch(err => {
                    msg.innerText = 'Error during restore.';
                    msg.style.color = 'var(--primary)';
                })
                .finally(() => {
                    btn.disabled = false;
                    loader.style.display = 'none';
                });
        }

        function deleteBackup(filename) {
            if (!confirm('Delete backup: ' + filename + '?\nThis cannot be undone.')) return;

            const msg = document.getElementById('backupMessage');
            msg.innerText = 'Deleting backup...';
            msg.style.color = 'var(--text-muted)';

            fetch('api.php?action=delete_backup&file=' + encodeURIComponent(filename))
                .then(res => res.json())
                .then(data => {
                    msg.innerText = data.message;
                    msg.style.color = data.success ? 'var(--secondary)' : 'var(--primary)';
                    loadBackupList();
                });
        }

        // ==================== UPLOAD FUNCTIONS ====================

        let selectedUploadFile = null;

        function openUploadModal() {
            selectedUploadFile = null;
            document.getElementById('uploadFileInput').value = '';
            document.getElementById('uploadFileName').innerText = 'Click or drag file here';
            document.getElementById('uploadFileSize').innerText = 'Max 500 MB • .tar.gz or .zip';
            document.getElementById('uploadMessage').innerText = '';
            document.getElementById('uploadProgressContainer').style.display = 'none';
            document.getElementById('uploadProgressBar').style.width = '0%';
            document.getElementById('btnUploadBackup').disabled = true;
            document.getElementById('btnUploadRestore').disabled = true;
            document.getElementById('uploadDropZone').classList.remove('has-file');
            document.getElementById('uploadModal').style.display = 'flex';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
            selectedUploadFile = null;
        }

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                
                if (file.size > 500 * 1024 * 1024) {
                    document.getElementById('uploadMessage').innerText = 'File too large! Maximum 500 MB.';
                    document.getElementById('uploadMessage').style.color = 'var(--primary)';
                    return;
                }

                selectedUploadFile = file;
                document.getElementById('uploadFileName').innerText = file.name;
                document.getElementById('uploadFileSize').innerText = sizeMB + ' MB';
                document.getElementById('uploadDropZone').classList.add('has-file');
                document.getElementById('btnUploadBackup').disabled = false;
                document.getElementById('btnUploadRestore').disabled = false;
                document.getElementById('uploadMessage').innerText = '';
            }
        }

        // Drag and drop support
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('uploadDropZone');
            if (!dropZone) return;

            ['dragenter', 'dragover'].forEach(evt => {
                dropZone.addEventListener(evt, e => {
                    e.preventDefault();
                    dropZone.classList.add('drag-over');
                });
            });
            ['dragleave', 'drop'].forEach(evt => {
                dropZone.addEventListener(evt, e => {
                    e.preventDefault();
                    dropZone.classList.remove('drag-over');
                });
            });
            dropZone.addEventListener('drop', e => {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const input = document.getElementById('uploadFileInput');
                    input.files = files;
                    handleFileSelect(input);
                }
            });
        });

        function uploadFile(action) {
            if (!selectedUploadFile) return;

            if (action === 'upload_restore') {
                if (!confirm('This will REPLACE all current save data with the uploaded file.\nA safety backup will be created first.\n\nThe server must be STOPPED. Continue?')) return;
            }

            const formData = new FormData();
            formData.append('backupfile', selectedUploadFile);

            const xhr = new XMLHttpRequest();
            const progressContainer = document.getElementById('uploadProgressContainer');
            const progressBar = document.getElementById('uploadProgressBar');
            const progressText = document.getElementById('uploadProgressText');
            const msg = document.getElementById('uploadMessage');
            const btnBackup = document.getElementById('btnUploadBackup');
            const btnRestore = document.getElementById('btnUploadRestore');

            progressContainer.style.display = 'block';
            btnBackup.disabled = true;
            btnRestore.disabled = true;
            msg.innerText = '';

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = pct + '%';
                    progressText.innerText = pct + '%';
                }
            });

            xhr.addEventListener('load', function() {
                try {
                    const data = JSON.parse(xhr.responseText);
                    msg.innerText = data.message;
                    msg.style.color = data.success ? 'var(--secondary)' : 'var(--primary)';
                    if (data.success) {
                        loadBackupList();
                        // Close modal after 2s on success
                        setTimeout(() => {
                            closeUploadModal();
                            document.getElementById('backupMessage').innerText = data.message;
                            document.getElementById('backupMessage').style.color = 'var(--secondary)';
                        }, 2000);
                    }
                } catch(e) {
                    msg.innerText = 'Server error: ' + xhr.responseText.substring(0, 200);
                    msg.style.color = 'var(--primary)';
                }
                btnBackup.disabled = false;
                btnRestore.disabled = false;
            });

            xhr.addEventListener('error', function() {
                msg.innerText = 'Upload failed. Network error.';
                msg.style.color = 'var(--primary)';
                btnBackup.disabled = false;
                btnRestore.disabled = false;
            });

            xhr.open('POST', 'api.php?action=' + action, true);
            xhr.send(formData);
        }

        // ==================== LOG VIEWER FUNCTIONS ====================

        let logAutoScroll = true;
        let logPaused = false;
        let logInterval = null;

        function loadLog() {
            if (logPaused) return;
            
            const lines = document.getElementById('logLines').value;
            fetch('api.php?action=log&lines=' + lines)
                .then(res => res.json())
                .then(data => {
                    const logEl = document.getElementById('logContent');
                    const consoleEl = document.getElementById('logConsole');
                    const infoEl = document.getElementById('logInfo');
                    
                    if (data.success) {
                        // Colorize log output
                        let html = escapeHtml(data.log);
                        // Highlight errors in red
                        html = html.replace(/(.*(?:error|exception|fail|crash|fatal).*)$/gmi, '<span class="log-error">$1</span>');
                        // Highlight warnings in yellow
                        html = html.replace(/(.*(?:warn|warning).*)$/gmi, '<span class="log-warn">$1</span>');
                        // Highlight server markers
                        html = html.replace(/(=== .+ ===)/g, '<span class="log-marker">$1</span>');
                        // Highlight timestamps in brackets
                        html = html.replace(/(\[\d{4}-\d{2}-\d{2}[^\]]*\])/g, '<span class="log-timestamp">$1</span>');
                        
                        logEl.innerHTML = html;
                        infoEl.innerText = data.lines + ' lines | ' + data.file_size_mb + ' MB' + (data.truncated ? ' | capped' : '');
                        
                        // Auto-scroll to bottom
                        if (logAutoScroll) {
                            consoleEl.scrollTop = consoleEl.scrollHeight;
                        }
                    }
                })
                .catch(err => {
                    document.getElementById('logContent').innerText = '(Error loading log: ' + err.message + ')';
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        function toggleAutoScroll() {
            logAutoScroll = !logAutoScroll;
            const btn = document.getElementById('btnAutoScroll');
            btn.classList.toggle('active', logAutoScroll);
        }

        function toggleLogPause() {
            logPaused = !logPaused;
            const btn = document.getElementById('btnPauseLog');
            const icon = document.getElementById('pauseIcon');
            
            if (logPaused) {
                btn.classList.add('active');
                icon.className = 'fa-solid fa-play';
                btn.title = 'Resume log refresh';
            } else {
                btn.classList.remove('active');
                icon.className = 'fa-solid fa-pause';
                btn.title = 'Pause log refresh';
                loadLog(); // immediately refresh
            }
        }

        function clearLog() {
            fetch('api.php?action=clear_log')
                .then(res => res.json())
                .then(data => {
                    if (data.success) loadLog();
                });
        }

        // Check initially and then every 5 seconds
        checkStatus();
        setInterval(checkStatus, 5000);
        
        // Load backups on page load
        loadBackupList();

        // Load log initially and refresh every 3 seconds
        loadLog();
        logInterval = setInterval(loadLog, 5000);
    </script>
</body>
</html>

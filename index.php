<?php
/**
 * IPTV Automation Management Panel
 * Main Dashboard
 */

// Configuration
define('CONFIG_FILE', '/opt/iptv-automation/config.json');
define('QUEUE_FILE', '/opt/iptv-automation/queue.json');
define('DB_FILE', '/opt/iptv-automation/media.db');

// Load configuration
function loadConfig() {
    if (file_exists(CONFIG_FILE)) {
        return json_decode(file_get_contents(CONFIG_FILE), true);
    }
    return [
        'radarr_url' => 'http://localhost:7878',
        'radarr_api_key' => '',
        'sonarr_url' => 'http://localhost:8989',
        'sonarr_api_key' => '',
        'tmdb_api_key' => '',
        'base_url' => 'http://' . $_SERVER['SERVER_ADDR']
    ];
}

// Save configuration
function saveConfig($config) {
    return file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
}

// Load queue
function loadQueue() {
    if (file_exists(QUEUE_FILE)) {
        return json_decode(file_get_contents(QUEUE_FILE), true);
    }
    return [];
}

// Get service status
function getServiceStatus($service) {
    $output = shell_exec("systemctl is-active $service 2>&1");
    return trim($output) === 'active';
}

// Get queue statistics
function getQueueStats($queue) {
    $stats = [
        'total' => count($queue),
        'pending' => 0,
        'transcoded' => 0,
        'failed' => 0
    ];
    
    foreach ($queue as $item) {
        $status = $item['status'] ?? 'unknown';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }
    
    return $stats;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_config':
            $config = [
                'radarr_url' => $_POST['radarr_url'] ?? '',
                'radarr_api_key' => $_POST['radarr_api_key'] ?? '',
                'sonarr_url' => $_POST['sonarr_url'] ?? '',
                'sonarr_api_key' => $_POST['sonarr_api_key'] ?? '',
                'tmdb_api_key' => $_POST['tmdb_api_key'] ?? '',
                'base_url' => $_POST['base_url'] ?? ''
            ];
            
            if (saveConfig($config)) {
                echo json_encode(['success' => true, 'message' => 'Configuration saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save configuration']);
            }
            exit;
            
        case 'start_watcher':
            exec('sudo systemctl start iptv-watcher 2>&1', $output, $return);
            echo json_encode(['success' => $return === 0, 'message' => $return === 0 ? 'Watcher started' : 'Failed to start watcher']);
            exit;
            
        case 'stop_watcher':
            exec('sudo systemctl stop iptv-watcher 2>&1', $output, $return);
            echo json_encode(['success' => $return === 0, 'message' => $return === 0 ? 'Watcher stopped' : 'Failed to stop watcher']);
            exit;
            
        case 'run_transcode':
            exec('python3 /opt/iptv-automation/scripts/transcode.py > /opt/iptv-automation/logs/transcode.log 2>&1 &');
            echo json_encode(['success' => true, 'message' => 'Transcoding started']);
            exit;
            
        case 'run_metadata':
            exec('python3 /opt/iptv-automation/scripts/fetch_metadata.py > /opt/iptv-automation/logs/metadata.log 2>&1 &');
            echo json_encode(['success' => true, 'message' => 'Metadata fetch started']);
            exit;
            
        case 'run_generate':
            exec('python3 /opt/iptv-automation/scripts/generate_m3u.py > /opt/iptv-automation/logs/generate.log 2>&1 &');
            echo json_encode(['success' => true, 'message' => 'Playlist generation started']);
            exit;
            
        case 'run_all':
            exec('/opt/iptv-automation/scripts/process_all.sh > /opt/iptv-automation/logs/process.log 2>&1 &');
            echo json_encode(['success' => true, 'message' => 'Full pipeline started']);
            exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

$config = loadConfig();
$queue = loadQueue();
$stats = getQueueStats($queue);

// Service status
$services = [
    'radarr' => getServiceStatus('radarr'),
    'sonarr' => getServiceStatus('sonarr'),
    'jackett' => getServiceStatus('jackett'),
    'qbittorrent' => getServiceStatus('qbittorrent'),
    'iptv-watcher' => getServiceStatus('iptv-watcher')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPTV Automation Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .main-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--primary-color);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 600;
            padding: 15px 25px;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
        }
        
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stat-card.danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        
        .stat-card h2 {
            font-size: 3rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card p {
            margin: 10px 0 0 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .service-status {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        
        .service-status .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 15px;
        }
        
        .service-status .status-indicator.active {
            background: #28a745;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
        }
        
        .service-status .status-indicator.inactive {
            background: #dc3545;
        }
        
        .queue-item {
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .queue-item.pending {
            border-left-color: #ffc107;
        }
        
        .queue-item.transcoded {
            border-left-color: #28a745;
        }
        
        .queue-item.failed {
            border-left-color: #dc3545;
        }
        
        .btn-action {
            border-radius: 25px;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .log-viewer {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container">
            <div class="header">
                <h1><i class="bi bi-film"></i> IPTV Automation Panel</h1>
                <p>Complete Media Processing Pipeline Management</p>
            </div>
            
            <ul class="nav nav-tabs px-4 pt-4" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="queue-tab" data-bs-toggle="tab" data-bs-target="#queue" type="button">
                        <i class="bi bi-list-task"></i> Queue
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="actions-tab" data-bs-toggle="tab" data-bs-target="#actions" type="button">
                        <i class="bi bi-play-circle"></i> Actions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button">
                        <i class="bi bi-gear"></i> Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="playlists-tab" data-bs-toggle="tab" data-bs-target="#playlists" type="button">
                        <i class="bi bi-music-note-list"></i> Playlists
                    </button>
                </li>
            </ul>
            
            <div class="tab-content p-4" id="mainTabContent">
                <!-- Dashboard Tab -->
                <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                    <h3 class="mb-4">Processing Statistics</h3>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="stat-card primary">
                                <h2><?= $stats['total'] ?></h2>
                                <p>Total Items</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card warning">
                                <h2><?= $stats['pending'] ?></h2>
                                <p>Pending</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card success">
                                <h2><?= $stats['transcoded'] ?></h2>
                                <p>Completed</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card danger">
                                <h2><?= $stats['failed'] ?></h2>
                                <p>Failed</p>
                            </div>
                        </div>
                    </div>
                    
                    <h3 class="mb-4">Service Status</h3>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <?php foreach ($services as $service => $status): ?>
                            <div class="service-status">
                                <div class="status-indicator <?= $status ? 'active' : 'inactive' ?>"></div>
                                <div class="flex-grow-1">
                                    <strong><?= ucfirst(str_replace('-', ' ', $service)) ?></strong>
                                </div>
                                <span class="badge <?= $status ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $status ? 'Running' : 'Stopped' ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <strong>Quick Links</strong>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:7878" target="_blank" class="btn btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Open Radarr
                                        </a>
                                        <a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:8989" target="_blank" class="btn btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Open Sonarr
                                        </a>
                                        <a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:9117" target="_blank" class="btn btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Open Jackett
                                        </a>
                                        <a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:8080" target="_blank" class="btn btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Open qBittorrent
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Queue Tab -->
                <div class="tab-pane fade" id="queue" role="tabpanel">
                    <h3 class="mb-4">Processing Queue</h3>
                    
                    <?php if (empty($queue)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Queue is empty. Add media to Radarr or Sonarr to get started.
                    </div>
                    <?php else: ?>
                    <?php foreach ($queue as $item): ?>
                    <div class="queue-item <?= $item['status'] ?? 'pending' ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-1"><?= htmlspecialchars($item['filename']) ?></h5>
                                <small class="text-muted">
                                    <?= ucfirst($item['media_type']) ?> • 
                                    <?php if (isset($item['metadata']['title'])): ?>
                                        <?= htmlspecialchars($item['metadata']['title']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Added: <?= date('Y-m-d H:i', strtotime($item['added_at'])) ?></small>
                            </div>
                            <div class="col-md-3 text-end">
                                <?php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'transcoded' => 'success',
                                    'failed' => 'danger'
                                ];
                                $status = $item['status'] ?? 'pending';
                                $color = $statusColors[$status] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $color ?> px-3 py-2">
                                    <?= ucfirst($status) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Actions Tab -->
                <div class="tab-pane fade" id="actions" role="tabpanel">
                    <h3 class="mb-4">Manual Actions</h3>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <strong><i class="bi bi-play-circle"></i> Processing Pipeline</strong>
                                </div>
                                <div class="card-body">
                                    <p>Run individual steps or the complete pipeline</p>
                                    
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-action btn-warning" onclick="runAction('run_transcode')">
                                            <i class="bi bi-film"></i> Run Transcoding
                                        </button>
                                        <button class="btn btn-action btn-info text-white" onclick="runAction('run_metadata')">
                                            <i class="bi bi-database"></i> Fetch Metadata
                                        </button>
                                        <button class="btn btn-action btn-success" onclick="runAction('run_generate')">
                                            <i class="bi bi-music-note-list"></i> Generate Playlists
                                        </button>
                                        <hr>
                                        <button class="btn btn-action btn-primary btn-lg" onclick="runAction('run_all')">
                                            <i class="bi bi-lightning-charge"></i> Run Complete Pipeline
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-secondary text-white">
                                    <strong><i class="bi bi-activity"></i> Watcher Control</strong>
                                </div>
                                <div class="card-body">
                                    <p>Control the file watcher service</p>
                                    
                                    <div class="d-grid gap-2">
                                        <?php if ($services['iptv-watcher']): ?>
                                        <button class="btn btn-action btn-danger" onclick="runAction('stop_watcher')">
                                            <i class="bi bi-stop-circle"></i> Stop Watcher
                                        </button>
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle"></i> Watcher is currently running
                                        </div>
                                        <?php else: ?>
                                        <button class="btn btn-action btn-success" onclick="runAction('start_watcher')">
                                            <i class="bi bi-play-circle"></i> Start Watcher
                                        </button>
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle"></i> Watcher is not running
                                        </div>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                                            <i class="bi bi-arrow-clockwise"></i> Refresh Status
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="card">
                            <div class="card-header">
                                <strong><i class="bi bi-terminal"></i> Action Log</strong>
                            </div>
                            <div class="card-body">
                                <div class="log-viewer" id="actionLog">
                                    <div class="text-muted">No actions performed yet...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Tab -->
                <div class="tab-pane fade" id="settings" role="tabpanel">
                    <h3 class="mb-4">Configuration</h3>
                    
                    <form id="configForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <strong>Radarr Settings</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Radarr URL</label>
                                            <input type="text" class="form-control" name="radarr_url" value="<?= htmlspecialchars($config['radarr_url']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Radarr API Key</label>
                                            <input type="text" class="form-control" name="radarr_api_key" value="<?= htmlspecialchars($config['radarr_api_key']) ?>" placeholder="Get from Radarr Settings → General → Security">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-4">
                                    <div class="card-header bg-success text-white">
                                        <strong>Sonarr Settings</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Sonarr URL</label>
                                            <input type="text" class="form-control" name="sonarr_url" value="<?= htmlspecialchars($config['sonarr_url']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Sonarr API Key</label>
                                            <input type="text" class="form-control" name="sonarr_api_key" value="<?= htmlspecialchars($config['sonarr_api_key']) ?>" placeholder="Get from Sonarr Settings → General → Security">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <strong>TMDB Settings</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">TMDB API Key</label>
                                            <input type="text" class="form-control" name="tmdb_api_key" value="<?= htmlspecialchars($config['tmdb_api_key']) ?>" placeholder="Get from themoviedb.org">
                                        </div>
                                        <div class="alert alert-info">
                                            <small>
                                                <strong>Get TMDB API Key:</strong><br>
                                                1. Go to <a href="https://www.themoviedb.org/settings/api" target="_blank">themoviedb.org</a><br>
                                                2. Create account → Settings → API<br>
                                                3. Request API key (free)
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-4">
                                    <div class="card-header bg-warning text-dark">
                                        <strong>Stream Settings</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Base URL</label>
                                            <input type="text" class="form-control" name="base_url" value="<?= htmlspecialchars($config['base_url']) ?>" placeholder="http://your-server-ip">
                                            <small class="text-muted">URL for HLS streams in M3U playlists</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-action btn-primary btn-lg">
                                <i class="bi bi-save"></i> Save Configuration
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Playlists Tab -->
                <div class="tab-pane fade" id="playlists" role="tabpanel">
                    <h3 class="mb-4">Generated Playlists</h3>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <strong><i class="bi bi-film"></i> Movies Playlist</strong>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $moviesPlaylist = '/opt/iptv-automation/playlists/movies.m3u';
                                    if (file_exists($moviesPlaylist)):
                                        $content = file_get_contents($moviesPlaylist);
                                        $lines = explode("\n", $content);
                                        $count = (count($lines) - 1) / 2; // Each entry is 2 lines
                                    ?>
                                    <p class="mb-3">
                                        <strong>Entries:</strong> <?= $count ?><br>
                                        <strong>File size:</strong> <?= number_format(filesize($moviesPlaylist) / 1024, 2) ?> KB
                                    </p>
                                    <a href="/playlists/movies.m3u" download class="btn btn-primary" target="_blank">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick="viewPlaylist('movies')">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Playlist not generated yet
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <strong><i class="bi bi-tv"></i> Series Playlist</strong>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $seriesPlaylist = '/opt/iptv-automation/playlists/series.m3u';
                                    if (file_exists($seriesPlaylist)):
                                        $content = file_get_contents($seriesPlaylist);
                                        $lines = explode("\n", $content);
                                        $count = (count($lines) - 1) / 2;
                                    ?>
                                    <p class="mb-3">
                                        <strong>Entries:</strong> <?= $count ?><br>
                                        <strong>File size:</strong> <?= number_format(filesize($seriesPlaylist) / 1024, 2) ?> KB
                                    </p>
                                    <a href="/playlists/series.m3u" download class="btn btn-success" target="_blank">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick="viewPlaylist('series')">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Playlist not generated yet
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Save configuration
        document.getElementById('configForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'save_config');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Error saving configuration');
            }
        });
        
        // Run action
        async function runAction(action) {
            const formData = new FormData();
            formData.append('action', action);
            
            const log = document.getElementById('actionLog');
            const timestamp = new Date().toLocaleTimeString();
            
            log.innerHTML += `<div>[${timestamp}] Running ${action}...</div>`;
            log.scrollTop = log.scrollHeight;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    log.innerHTML += `<div class="text-success">[${timestamp}] ✅ ${result.message}</div>`;
                    
                    if (action === 'start_watcher' || action === 'stop_watcher') {
                        setTimeout(() => location.reload(), 2000);
                    }
                } else {
                    log.innerHTML += `<div class="text-danger">[${timestamp}] ❌ ${result.message}</div>`;
                }
            } catch (error) {
                log.innerHTML += `<div class="text-danger">[${timestamp}] ❌ Error</div>`;
            }
            
            log.scrollTop = log.scrollHeight;
        }
        
        // View playlist
        function viewPlaylist(type) {
            window.open(`/playlists/${type}.m3u`, '_blank');
        }
    </script>
</body>
</html>

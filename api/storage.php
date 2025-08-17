<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Storage file path
$dataDir = '/var/www/homepage/data';
$settingsFile = $dataDir . '/settings.json';
$appsFile = $dataDir . '/apps.json';

// Create data directory if it doesn't exist
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

function getSettings() {
    global $settingsFile;
    if (file_exists($settingsFile)) {
        $data = file_get_contents($settingsFile);
        return json_decode($data, true) ?: [];
    }
    return ['title' => 'Home']; // Default settings
}

function saveSettings($settings) {
    global $settingsFile;
    return file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
}

function getApps() {
    global $appsFile;
    if (file_exists($appsFile)) {
        $data = file_get_contents($appsFile);
        return json_decode($data, true) ?: [];
    }
    return [
        ['name' => 'Plex', 'url' => 'http://localhost:32400', 'icon' => '🎬'],
        ['name' => 'Pi-hole', 'url' => 'http://192.168.1.100/admin', 'icon' => '🛡️'],
        ['name' => 'Portainer', 'url' => 'http://localhost:9000', 'icon' => '🐳']
    ]; // Default apps
}

function saveApps($apps) {
    global $appsFile;
    return file_put_contents($appsFile, json_encode($apps, JSON_PRETTY_PRINT));
}

// Handle different request types
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $type = $_GET['type'] ?? '';
        
        if ($type === 'settings') {
            echo json_encode(['success' => true, 'data' => getSettings()]);
        } elseif ($type === 'apps') {
            echo json_encode(['success' => true, 'data' => getApps()]);
        } elseif ($type === 'overview_order') {
            $orderFile = $dataDir . '/overview_order.json';
            if (file_exists($orderFile)) {
                $data = file_get_contents($orderFile);
                $order = json_decode($data, true) ?: ['system', 'apps', 'containers', 'network'];
            } else {
                $order = ['system', 'apps', 'containers', 'network'];
            }
            echo json_encode(['success' => true, 'data' => $order]);
        } else {
            echo json_encode([
                'success' => true, 
                'data' => [
                    'settings' => getSettings(),
                    'apps' => getApps()
                ]
            ]);
        }
        break;
        
    case 'POST':
        $type = $input['type'] ?? '';
        $data = $input['data'] ?? null;
        
        if ($type === 'settings' && $data) {
            $success = saveSettings($data);
            echo json_encode(['success' => $success !== false]);
        } elseif ($type === 'apps' && $data) {
            $success = saveApps($data);
            echo json_encode(['success' => $success !== false]);
        } elseif ($type === 'overview_order' && $data) {
            $orderFile = $dataDir . '/overview_order.json';
            $success = file_put_contents($orderFile, json_encode($data, JSON_PRETTY_PRINT));
            echo json_encode(['success' => $success !== false]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        break;
}
?>
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function getContainers() {
    $cmd = "docker ps -a --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}'";
    $output = shell_exec($cmd);
    
    if (!$output) {
        return [];
    }
    
    $lines = explode("\n", trim($output));
    array_shift($lines); // Remove header
    
    $containers = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        
        $parts = explode("\t", $line);
        if (count($parts) >= 4) {
            $name = trim($parts[0]);
            $image = trim($parts[1]);
            $status = trim($parts[2]);
            $ports = trim($parts[3]);
            
            // Parse status
            $isRunning = strpos($status, 'Up') === 0;
            $statusText = $isRunning ? 'running' : 'stopped';
            
            // Get uptime
            $uptime = $isRunning ? substr($status, 3) : '0s';
            
            // Get resource usage for running containers
            $cpu = '0%';
            $memory = '0MB';
            
            if ($isRunning) {
                $statsCmd = "docker stats --no-stream --format 'table {{.CPUPerc}}\t{{.MemUsage}}' $name 2>/dev/null";
                $statsOutput = shell_exec($statsCmd);
                if ($statsOutput) {
                    $statsLines = explode("\n", trim($statsOutput));
                    if (count($statsLines) > 1) {
                        $statsParts = explode("\t", $statsLines[1]);
                        if (count($statsParts) >= 2) {
                            $cpu = trim($statsParts[0]);
                            $memoryParts = explode(" / ", trim($statsParts[1]));
                            $memory = trim($memoryParts[0]);
                        }
                    }
                }
            }
            
            $containers[] = [
                'name' => $name,
                'image' => $image,
                'status' => $statusText,
                'ports' => $ports,
                'uptime' => $uptime,
                'cpu' => $cpu,
                'memory' => $memory
            ];
        }
    }
    
    return $containers;
}

function executeDockerAction($container, $action) {
    $allowedActions = ['start', 'stop', 'restart'];
    
    if (!in_array($action, $allowedActions)) {
        return ['success' => false, 'error' => 'Invalid action'];
    }
    
    // Sanitize container name
    $container = preg_replace('/[^a-zA-Z0-9_-]/', '', $container);
    
    $cmd = "docker $action $container 2>&1";
    $output = shell_exec($cmd);
    
    // Check if command was successful
    $exitCode = 0;
    exec("docker $action $container", $dummy, $exitCode);
    
    return [
        'success' => $exitCode === 0,
        'output' => $output,
        'error' => $exitCode !== 0 ? $output : null
    ];
}

function getContainerLogs($container) {
    $container = preg_replace('/[^a-zA-Z0-9_-]/', '', $container);
    $cmd = "docker logs --tail 100 $container 2>&1";
    return shell_exec($cmd);
}

// Handle different request types
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['logs'])) {
        $logs = getContainerLogs($_GET['logs']);
        header('Content-Type: text/plain');
        echo $logs;
        exit;
    } else {
        echo json_encode(getContainers());
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['container']) && isset($input['action'])) {
        $result = executeDockerAction($input['container'], $input['action']);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    }
}
?>
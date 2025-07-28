<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function isAuthenticated() {
    return isset($_SESSION['authenticated']) && 
           $_SESSION['authenticated'] === true && 
           isset($_SESSION['username']) &&
           time() < ($_SESSION['expires'] ?? 0);
}

function requireAuth() {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Authentication required for this operation',
            'requireAuth' => true
        ]);
        exit;
    }
}

function getContainers() {
    $cmd = "sudo /snap/bin/docker ps -a --format 'json' 2>&1";
    $output = shell_exec($cmd);
    
    if (!$output) {
        return [];
    }
    
    $lines = explode("\n", trim($output));
    $containers = [];
    
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        
        $data = json_decode($line, true);
        if ($data) {
            $name = $data['Names'];
            $image = $data['Image'];
            $status = $data['Status'];
            $ports = $data['Ports'] ?? 'none';
            
            // Parse status for running state
            $isRunning = strpos($status, 'Up') === 0;
            $statusText = $isRunning ? 'running' : 'stopped';
            
            // Extract uptime (remove "Up " prefix and clean up)
            $uptime = $status;
            if ($isRunning) {
                $uptime = preg_replace('/^Up\s+/', '', $status);
                $uptime = preg_replace('/\s+\(.*?\)$/', '', $uptime); // Remove health status
            }
            
            // Get CPU and Memory stats for running containers
            $cpu = '0%';
            $memory = '0MB';
            
            if ($isRunning) {
                $statsCmd = "sudo /snap/bin/docker stats --no-stream --format 'json' $name 2>/dev/null";
                $statsOutput = shell_exec($statsCmd);
                if ($statsOutput) {
                    $statsData = json_decode(trim($statsOutput), true);
                    if ($statsData) {
                        $cpu = $statsData['CPUPerc'] ?? '0%';
                        $memory = $statsData['MemUsage'] ?? '0MB';
                        // Extract just the used memory part
                        if (strpos($memory, '/') !== false) {
                            $memory = explode('/', $memory)[0];
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
                'memory' => trim($memory)
            ];
        }
    }
    
    return $containers;
}

function executeDockerAction($container, $action) {
    // Check if authentication is required for this action
    $sensitiveActions = ['remove', 'purge'];
    if (in_array($action, $sensitiveActions)) {
        requireAuth();
    }
    
    $allowedActions = ['start', 'stop', 'restart', 'remove', 'purge'];
    
    if (!in_array($action, $allowedActions)) {
        return ['success' => false, 'error' => 'Invalid action'];
    }
    
    $container = preg_replace('/[^a-zA-Z0-9_-]/', '', $container);
    
    // Log the action for security
    if (isAuthenticated()) {
        error_log("Docker action: {$action} on container {$container} by user {$_SESSION['username']}");
    }
    
    if ($action === 'remove') {
        $cmd = "sudo /snap/bin/docker rm -f $container 2>&1";
    } elseif ($action === 'purge') {
        // Stop and remove container, then remove associated volumes and images
        $image = shell_exec("sudo /snap/bin/docker inspect --format='{{.Config.Image}}' $container 2>/dev/null");
        $image = trim($image);
        
        $commands = [
            "sudo /snap/bin/docker stop $container 2>/dev/null",
            "sudo /snap/bin/docker rm -f $container 2>/dev/null",
            "sudo /snap/bin/docker volume prune -f 2>/dev/null"
        ];
        
        if ($image && $image !== '') {
            $commands[] = "sudo /snap/bin/docker rmi $image 2>/dev/null";
        }
        
        $output = '';
        foreach ($commands as $cmd) {
            $output .= shell_exec($cmd) . "\n";
        }
        
        return [
            'success' => true,
            'output' => $output
        ];
    } else {
        $cmd = "sudo /snap/bin/docker $action $container 2>&1";
    }
    
    $output = shell_exec($cmd);
    
    return [
        'success' => strpos($output, 'Error') === false && strpos($output, 'error') === false,
        'output' => $output
    ];
}

function deployContainer($data) {
    // Require authentication for deploying containers
    requireAuth();
    
    // Log the deployment for security
    error_log("Docker deployment by user {$_SESSION['username']}: " . json_encode($data));
    
    if ($data['method'] === 'simple') {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['name']);
        $image = $data['image'];
        
        $cmd = "sudo /snap/bin/docker run -d --name $name";
        
        // Add ports
        if (!empty($data['ports'])) {
            $cmd .= " -p " . escapeshellarg($data['ports']);
        }
        
        // Add environment variables
        if (!empty($data['env'])) {
            $envLines = explode("\n", $data['env']);
            foreach ($envLines as $env) {
                $env = trim($env);
                if (!empty($env) && strpos($env, '=') !== false) {
                    $cmd .= " -e " . escapeshellarg($env);
                }
            }
        }
        
        // Add volumes
        if (!empty($data['volumes'])) {
            $cmd .= " -v " . escapeshellarg($data['volumes']);
        }
        
        $cmd .= " " . escapeshellarg($image) . " 2>&1";
        
    } elseif ($data['method'] === 'compose') {
        // Create temporary compose file
        $composeFile = '/tmp/docker-compose-' . uniqid() . '.yml';
        file_put_contents($composeFile, $data['compose']);
        
        $cmd = "cd /tmp && sudo /snap/bin/docker-compose -f $composeFile up -d 2>&1";
        
        $output = shell_exec($cmd);
        unlink($composeFile);
        
        return [
            'success' => strpos($output, 'Error') === false && strpos($output, 'error') === false,
            'output' => $output
        ];
    }
    
    $output = shell_exec($cmd);
    
    return [
        'success' => strpos($output, 'Error') === false && strpos($output, 'error') === false,
        'output' => $output
    ];
}

function getContainerLogs($container) {
    $container = preg_replace('/[^a-zA-Z0-9_-]/', '', $container);
    $cmd = "sudo /snap/bin/docker logs --tail 100 $container 2>&1";
    return shell_exec($cmd);
}

// Handle requests
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
    
    if (isset($input['action'])) {
        if ($input['action'] === 'deploy') {
            $result = deployContainer($input);
            echo json_encode($result);
        } elseif (isset($input['container'])) {
            $result = executeDockerAction($input['container'], $input['action']);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No action specified']);
    }
}
?>
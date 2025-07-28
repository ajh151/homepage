<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function authenticateUser($username, $password) {
    // Validate username format (alphanumeric, underscore, hyphen only)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        return false;
    }
    
    // Create a temporary script to avoid shell escaping issues
    $script = "#!/bin/bash\necho " . escapeshellarg($password) . " | pamtester login " . escapeshellarg($username) . " authenticate >/dev/null 2>&1\necho \$?\n";
    
    $tempFile = tempnam(sys_get_temp_dir(), 'auth_');
    file_put_contents($tempFile, $script);
    chmod($tempFile, 0755);
    
    // Execute the script
    $exitCode = trim(shell_exec($tempFile));
    unlink($tempFile);
    
    return $exitCode === '0';
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
            'error' => 'Authentication required',
            'requireAuth' => true
        ]);
        exit;
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'login') {
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo json_encode([
                'success' => false, 
                'error' => 'Username and password required'
            ]);
            exit;
        }
        
        if (authenticateUser($username, $password)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['expires'] = time() + (30 * 60); // 30 minutes
            
            echo json_encode([
                'success' => true,
                'message' => 'Authentication successful',
                'username' => $username,
                'expires' => $_SESSION['expires']
            ]);
        } else {
            // Add delay to prevent brute force attacks
            sleep(2);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid username or password'
            ]);
        }
        
    } elseif ($action === 'logout') {
        session_destroy();
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
        
    } elseif ($action === 'extend') {
        if (isAuthenticated()) {
            $_SESSION['expires'] = time() + (30 * 60); // Extend by 30 minutes
            echo json_encode([
                'success' => true,
                'expires' => $_SESSION['expires']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Not authenticated'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
    }
    
} elseif ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'status') {
        if (isAuthenticated()) {
            echo json_encode([
                'authenticated' => true,
                'username' => $_SESSION['username'],
                'expires' => $_SESSION['expires'],
                'timeLeft' => $_SESSION['expires'] - time()
            ]);
        } else {
            echo json_encode([
                'authenticated' => false
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
    }
}
?>
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
    
    // Use passwd command to verify credentials
    $pythonScript = '
import subprocess
import sys
import os

try:
    username = sys.argv[1]
    password = sys.argv[2]
    
    # Create a simple test using su command
    # This method works without spwd module
    cmd = ["sudo", "-S", "-u", username, "whoami"]
    
    process = subprocess.Popen(
        cmd,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )
    
    stdout, stderr = process.communicate(input=password + "\n", timeout=5)
    
    if process.returncode == 0 and stdout.strip() == username:
        print("SUCCESS")
    else:
        print("INVALID_PASSWORD")
        
except subprocess.TimeoutExpired:
    print("TIMEOUT")
except Exception as e:
    print(f"ERROR: {str(e)}")
    sys.exit(1)
';
    
    // Write Python script to temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'auth_');
    file_put_contents($tempFile, $pythonScript);
    
    // Execute the script
    $cmd = sprintf(
        'python3 %s %s %s 2>&1',
        escapeshellarg($tempFile),
        escapeshellarg($username),
        escapeshellarg($password)
    );
    
    $output = trim(shell_exec($cmd));
    unlink($tempFile);
    
    return $output === 'SUCCESS';
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
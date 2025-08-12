<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getInternetSpeed() {
    // Simple speed test using speedtest-cli or wget
    $speedTestUrl = 'http://speedtest.ftp.otenet.gr/files/test100k.db';
    $startTime = microtime(true);
    
    // Download a small file and measure time
    $data = @file_get_contents($speedTestUrl, false, stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0'
        ]
    ]));
    
    $endTime = microtime(true);
    
    if ($data === false) {
        return ['download' => 'N/A', 'ping' => 'N/A'];
    }
    
    $fileSize = strlen($data); // bytes
    $timeTaken = $endTime - $startTime; // seconds
    $speedBps = $fileSize / $timeTaken; // bytes per second
    $speedMbps = round(($speedBps * 8) / 1024 / 1024, 1); // Mbps
    
    // Get ping to Google DNS
    $pingCmd = "ping -c 1 8.8.8.8 | grep 'time=' | awk -F'time=' '{print $2}' | awk '{print $1}'";
    $ping = trim(shell_exec($pingCmd));
    
    return [
        'download' => $speedMbps . ' Mbps',
        'ping' => $ping ? $ping . ' ms' : 'N/A'
    ];
}

function getServiceStatus($services) {
    $results = [];
    
    foreach ($services as $name => $config) {
        $host = $config['host'];
        $port = $config['port'];
        $timeout = $config['timeout'] ?? 3;
        
        $startTime = microtime(true);
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $endTime = microtime(true);
        
        if ($connection) {
            fclose($connection);
            $responseTime = round(($endTime - $startTime) * 1000, 1); // ms
            $results[$name] = [
                'status' => 'online',
                'response_time' => $responseTime . ' ms',
                'url' => $config['url'] ?? null
            ];
        } else {
            $results[$name] = [
                'status' => 'offline',
                'response_time' => 'N/A',
                'url' => $config['url'] ?? null,
                'error' => $errstr
            ];
        }
    }
    
    return $results;
}

function getNetworkDevices() {
    // Get network range (assuming 192.168.1.x)
    $networkRange = '192.168.1';
    $devices = [];
    
    // Get ARP table for known devices
    $arpOutput = shell_exec("arp -a 2>/dev/null");
    if ($arpOutput) {
        $lines = explode("\n", trim($arpOutput));
        foreach ($lines as $line) {
            if (preg_match('/\((192\.168\.1\.\d+)\) at ([a-f0-9:]+)/i', $line, $matches)) {
                $ip = $matches[1];
                $mac = $matches[2];
                
                // Try to get hostname
                $hostname = gethostbyaddr($ip);
                if ($hostname === $ip) {
                    $hostname = 'Unknown';
                }
                
                $devices[] = [
                    'ip' => $ip,
                    'hostname' => $hostname,
                    'mac' => strtoupper($mac),
                    'status' => 'online'
                ];
            }
        }
    }
    
    // If no ARP entries, do a quick ping sweep of common IPs
    if (empty($devices)) {
        $commonIPs = [1, 100, 101, 102, 200, 254]; // Router and common device IPs
        foreach ($commonIPs as $lastOctet) {
            $ip = "$networkRange.$lastOctet";
            $pingResult = shell_exec("ping -c 1 -W 1 $ip 2>/dev/null | grep '1 packets transmitted'");
            if (strpos($pingResult, '1 received') !== false) {
                $hostname = gethostbyaddr($ip);
                if ($hostname === $ip) {
                    $hostname = 'Unknown';
                }
                
                $devices[] = [
                    'ip' => $ip,
                    'hostname' => $hostname,
                    'mac' => 'Unknown',
                    'status' => 'online'
                ];
            }
        }
    }
    
    return $devices;
}

// Define your services to monitor
$services = [
    'Plex' => [
        'host' => 'localhost',
        'port' => 32400,
        'url' => 'http://localhost:32400'
    ],
    'Pi-hole' => [
        'host' => '192.168.1.100',
        'port' => 80,
        'url' => 'http://192.168.1.100/admin'
    ],
    'Portainer' => [
        'host' => 'localhost',
        'port' => 9000,
        'url' => 'http://localhost:9000'
    ],
    'SSH' => [
        'host' => 'localhost',
        'port' => 22
    ],
    'Router' => [
        'host' => '192.168.1.1',
        'port' => 80,
        'url' => 'http://192.168.1.1'
    ]
];

// Handle different request types
$action = $_GET['action'] ?? 'all';

switch ($action) {
    case 'speed':
        echo json_encode(['speed' => getInternetSpeed()]);
        break;
        
    case 'services':
        echo json_encode(['services' => getServiceStatus($services)]);
        break;
        
    case 'devices':
        echo json_encode(['devices' => getNetworkDevices()]);
        break;
        
    default:
        echo json_encode([
            'speed' => getInternetSpeed(),
            'services' => getServiceStatus($services),
            'devices' => getNetworkDevices()
        ]);
        break;
}
?>
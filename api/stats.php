<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getCpuTemp() {
    $temp_files = [
        '/sys/class/thermal/thermal_zone0/temp',
        '/sys/class/thermal/thermal_zone1/temp'
    ];
    
    foreach ($temp_files as $file) {
        if (file_exists($file)) {
            $temp = file_get_contents($file);
            return round($temp / 1000);
        }
    }
    return 45; // fallback
}

function getFail2banCount() {
    $cmd = "sudo fail2ban-client status 2>/dev/null | grep -oP 'Currently banned:\s*\K\d+' || echo '0'";
    $output = shell_exec($cmd);
    return (int)trim($output);
}

function getCpuUsage() {
    $load = sys_getloadavg();
    $cores = (int)shell_exec("nproc");
    return round(($load[0] / $cores) * 100);
}

function getMemoryUsage() {
    $cmd = "free | grep Mem | awk '{printf \"%.0f\", $3/$2 * 100.0}'";
    $output = shell_exec($cmd);
    return (int)trim($output);
}

function getDiskUsage() {
    $cmd = "df -h / | awk 'NR==2{print $5}' | sed 's/%//'";
    $output = shell_exec($cmd);
    return (int)trim($output);
}

function getDetailedDiskUsage() {
    // Get main filesystem usage
    $cmd = "df -h / | awk 'NR==2{printf \"%s,%s,%s,%s\", $2, $3, $4, $5}'";
    $output = trim(shell_exec($cmd));
    $parts = explode(',', $output);
    
    $mainDisk = [
        'mount' => '/',
        'total' => $parts[0] ?? '0',
        'used' => $parts[1] ?? '0',
        'available' => $parts[2] ?? '0',
        'percentage' => (int)str_replace('%', '', $parts[3] ?? '0'),
        'type' => 'system'
    ];
    
    // Get all mounted filesystems (including USB devices)
    $cmd = "df -h --output=source,target,size,used,avail,pcent,fstype | grep -E '^/dev/' | grep -v '^/dev/loop'";
    $output = shell_exec($cmd);
    
    $disks = [$mainDisk];
    
    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 6) {
                $device = $parts[0];
                $mount = $parts[1];
                $total = $parts[2];
                $used = $parts[3];
                $avail = $parts[4];
                $percent = (int)str_replace('%', '', $parts[5]);
                $fstype = $parts[6] ?? 'unknown';
                
                // Skip if it's the root filesystem (already added)
                if ($mount === '/') continue;
                
                // Determine device type
                $type = 'disk';
                if (strpos($device, '/dev/sd') === 0 && $mount !== '/') {
                    $type = 'usb';
                } elseif (strpos($device, '/dev/mmcblk') === 0) {
                    $type = 'sd_card';
                } elseif (strpos($mount, '/media/') === 0 || strpos($mount, '/mnt/') === 0) {
                    $type = 'removable';
                }
                
                $disks[] = [
                    'device' => $device,
                    'mount' => $mount,
                    'total' => $total,
                    'used' => $used,
                    'available' => $avail,
                    'percentage' => $percent,
                    'type' => $type,
                    'filesystem' => $fstype
                ];
            }
        }
    }
    
    return $disks;
}
function getUptime() {
    $cmd = "uptime -s";
    $bootTime = shell_exec($cmd);
    $bootTimestamp = strtotime(trim($bootTime));
    $uptime = time() - $bootTimestamp;
    return round($uptime / 86400); // days
}

$stats = [
    'cpuTemp' => getCpuTemp(),
    'fail2banCount' => getFail2banCount(),
    'cpuUsage' => getCpuUsage(),
    'memUsage' => getMemoryUsage(),
    'diskUsage' => getDiskUsage(),
    'uptime' => getUptime(),
    'detailedDisks' => getDetailedDiskUsage()
];

echo json_encode($stats);
?>

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
    'uptime' => getUptime()
];

echo json_encode($stats);
?>

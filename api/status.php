<?php

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../config/server.php';

function getServiceStatus($serviceName)
{
    $safeService = escapeshellarg($serviceName);
    $output = shell_exec("systemctl is-active $safeService 2>/dev/null");
    $status = trim((string) $output);
    return $status !== '' ? $status : 'unknown';
}

function getTailLines($filePath, $lineCount)
{
    if (!is_readable($filePath)) {
        return ['Permission denied or file not found'];
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return ['Unable to read log file'];
    }

    return array_slice($lines, -$lineCount);
}

$serviceName = $config['service_name'];
$serviceStatus = getServiceStatus($serviceName);
$lineCount = (int) $config['log_lines'];

$response = [
    'app_name' => $config['app_name'],
    'service_name' => $serviceName,
    'service_status' => $serviceStatus,
    'is_running' => ($serviceStatus === 'active'),
    'server_hostname' => gethostname(),
    'php_version' => PHP_VERSION,
    'timestamp' => date('Y-m-d H:i:s'),
    'refresh_interval_ms' => (int) $config['refresh_interval_ms'],
    'access_log' => getTailLines($config['access_log'], $lineCount),
    'error_log' => getTailLines($config['error_log'], $lineCount),
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
<?php
/**
 * apache.php - Apache service status and HTTP health check.
 */
require_once __DIR__ . '/system.php';

/**
 * Asks systemd whether the service is active.
 * "systemctl is-active" prints: active, inactive, failed, activating, ...
 * The exit code is NOT zero for a stopped service, so we read the printed text.
 */
function get_apache_status(string $service): array
{
    // Safety check: the name comes from server.php, but we validate it anyway.
    if (!preg_match('/^[A-Za-z0-9_.@-]+$/', $service)) {
        throw new RuntimeException('Invalid service name in server.php.', 500);
    }

    $result = run_command(['systemctl', 'is-active', $service]);

    if ($result === null || $result['output'] === '') {
        return [
            'service'        => $service,
            'apache_status'  => 'unknown',
            'raw_state'      => null,
            'uptime_seconds' => null,
            'uptime_human'   => null,
            'message'       => 'Could not read the service state (is systemctl available?).',
        ];
    }

    $raw = strtolower($result['output']);
    if ($raw === 'active') {
        $status = 'running';
    } elseif ($raw === 'inactive' || $raw === 'failed') {
        $status = 'stopped';
    } else {
        $status = 'unknown'; // e.g. activating, deactivating, reloading
    }

    $uptime = ($status === 'running') ? get_service_uptime($service) : null;

    return [
        'service'        => $service,
        'apache_status'  => $status,
        'raw_state'      => $raw,
        'uptime_seconds' => $uptime,
        'uptime_human'   => $uptime === null ? null : format_uptime($uptime),
    ];
}

/**
 * Seconds since the service became active.
 * systemd gives the start time as microseconds since boot (monotonic clock);
 * /proc/uptime gives seconds since boot. uptime = boot_uptime - start_time.
 * Returns null if the value cannot be read.
 */
function get_service_uptime(string $service): ?int
{
    $result = run_command(['systemctl', 'show', $service, '--property=ActiveEnterTimestampMonotonic', '--value']);
    if ($result === null || !ctype_digit($result['output']) || (int) $result['output'] <= 0) {
        return null;
    }
    $proc = @file_get_contents('/proc/uptime');
    if ($proc === false) {
        return null;
    }
    $bootSeconds = (float) strtok($proc, ' ');
    return max(0, (int) floor($bootSeconds - ((int) $result['output']) / 1000000));
}

/** 93784 -> "1d 2h 3m 4s" */
function format_uptime(int $seconds): string
{
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return ($d > 0 ? "{$d}d " : '') . "{$h}h {$m}m {$s}s";
}

/**
 * Sends an HTTP GET request to the monitored website and measures the time.
 */
function check_http_health(string $url, int $timeout = 5): array
{
    $parts  = parse_url($url);
    $scheme = strtolower($parts['scheme'] ?? '');
    if ($parts === false || !in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
        throw new RuntimeException('Invalid monitored_url in server.php.', 500);
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is missing. Install php-curl.', 500);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'Sentinel-Monitor/1.0',
    ]);

    $start   = microtime(true);
    $body    = curl_exec($ch);
    $elapsed = round((microtime(true) - $start) * 1000, 2);
    $errno   = curl_errno($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    $result = [
        'url'              => $url,
        'http_status'      => 'offline',
        'http_code'        => null,
        'response_time_ms' => null,
        'error'            => null,
    ];

    if ($body === false || $errno !== 0) {
        // cURL error 28 = timeout, 7 = connection refused, 6 = cannot resolve host
        $result['error'] = ($errno === 28) ? 'Request timed out.' : 'Connection failed.';
        return $result;
    }

    $result['http_code']        = $code;
    $result['response_time_ms'] = $elapsed;

    if ($code >= 200 && $code < 400) {
        $result['http_status'] = 'online';
    } else {
        $result['error'] = "Unexpected HTTP status code: $code.";
    }
    return $result;
}

<?php
/**
 * system.php - Safe command execution, CPU usage and memory usage.
 */

/**
 * Runs a command WITHOUT a shell. The command is an array such as
 * ['systemctl', 'is-active', 'apache2'], so no text can be injected.
 * Returns ['output', 'error', 'exit_code'] or null if it could not start.
 */
function run_command(array $command): ?array
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return null;
    }
    $output = stream_get_contents($pipes[1]);
    $error  = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'output'    => trim((string) $output),
        'error'     => trim((string) $error),
        'exit_code' => $exitCode,
    ];
}

/**
 * Reads the aggregate "cpu" line of /proc/stat.
 * Returns [total_time, idle_time, number_of_cores].
 */
function read_cpu_times(): array
{
    $lines = @file('/proc/stat', FILE_IGNORE_NEW_LINES);
    if ($lines === false || count($lines) === 0) {
        throw new RuntimeException('Cannot read /proc/stat.', 500);
    }

    $times = null;
    $cores = 0;
    foreach ($lines as $line) {
        if (strpos($line, 'cpu ') === 0) {
            $fields = preg_split('/\s+/', trim($line));
            array_shift($fields);                       // remove the word "cpu"
            $times = array_map('intval', $fields);
        } elseif (preg_match('/^cpu\d+\s/', $line)) {
            $cores++;
        }
    }
    if ($times === null || count($times) < 5) {
        throw new RuntimeException('Invalid CPU data in /proc/stat.', 500);
    }

    // Fields: user nice system idle iowait irq softirq steal ...
    $idle  = $times[3] + $times[4];                     // idle + iowait
    $total = array_sum(array_slice($times, 0, 8));      // guest time is already inside user/nice
    return [$total, $idle, $cores];
}

/**
 * CPU usage in percent, measured between two readings of /proc/stat.
 */
function get_cpu_usage(int $sampleMs = 500): array
{
    $sampleMs = max(100, min(5000, $sampleMs));

    [$total1, $idle1, $cores] = read_cpu_times();
    usleep($sampleMs * 1000);
    [$total2, $idle2]         = read_cpu_times();

    $totalDelta = $total2 - $total1;
    $idleDelta  = $idle2 - $idle1;
    if ($totalDelta <= 0 || $idleDelta < 0) {
        throw new RuntimeException('Invalid CPU reading. Please try again.', 500);
    }

    $usage = (($totalDelta - $idleDelta) / $totalDelta) * 100;
    $usage = max(0, min(100, $usage));

    return [
        'cpu_usage_percent' => round($usage, 2),
        'cpu_cores'         => $cores,
        'sample_ms'         => $sampleMs,
    ];
}

/**
 * Memory usage from /proc/meminfo (values are in kB there).
 */
function get_memory_usage(): array
{
    $lines = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Cannot read /proc/meminfo.', 500);
    }

    $values = [];
    foreach ($lines as $line) {
        if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+)\s+kB/', $line, $m)) {
            $values[$m[1]] = (int) $m[2];
        }
    }
    if (empty($values['MemTotal']) || !isset($values['MemAvailable'])) {
        throw new RuntimeException('MemTotal or MemAvailable not found in /proc/meminfo.', 500);
    }

    $totalKb     = $values['MemTotal'];
    $availableKb = $values['MemAvailable'];
    $usedKb      = $totalKb - $availableKb;

    return [
        'total_mb'      => round($totalKb / 1024, 2),
        'available_mb'  => round($availableKb / 1024, 2),
        'used_mb'       => round($usedKb / 1024, 2),
        'usage_percent' => round(($usedKb / $totalKb) * 100, 2),
    ];
}

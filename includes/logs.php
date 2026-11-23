<?php
/**
 * logs.php - Read and parse the latest Apache log lines.
 * Only the fixed paths from server.php are used. Nothing comes from the URL.
 */

const LOG_LINE_MAX_LENGTH = 500;

/** Returns the last $count lines of a file (efficient: reads from the end). */
function tail_file(string $path, int $count): array
{
    $name = basename($path);
    if (!file_exists($path)) {
        throw new RuntimeException("Log file not found: $name", 404);
    }
    if (!is_readable($path) || ($fh = @fopen($path, 'rb')) === false) {
        throw new RuntimeException(
            "Permission denied reading $name. Add the PHP user to the 'adm' group (see README).",
            403
        );
    }

    fseek($fh, 0, SEEK_END);
    $position = ftell($fh);
    $buffer   = '';
    while ($position > 0 && substr_count($buffer, "\n") <= $count) {
        $read      = min(4096, $position);
        $position -= $read;
        fseek($fh, $position);
        $buffer = fread($fh, $read) . $buffer;
    }
    fclose($fh);

    $lines = array_values(array_filter(explode("\n", $buffer), 'strlen'));
    return array_slice($lines, -$count);
}

/** Cuts very long lines so the response stays small. */
function shorten_line(string $line): string
{
    return strlen($line) > LOG_LINE_MAX_LENGTH
        ? substr($line, 0, LOG_LINE_MAX_LENGTH) . '...'
        : $line;
}

/** Latest Apache error log entries, newest first. */
function get_error_logs(string $path, int $count): array
{
    $count   = max(1, min(100, $count));
    $entries = [];

    foreach (array_reverse(tail_file($path, $count)) as $line) {
        $raw = shorten_line($line);
        // Apache 2.4: [Sun Nov 22 15:30:00.123456 2026] [core:notice] [pid 1:tid 2] message
        if (preg_match('/^\[([^\]]+)\]\s+\[([^\]:]+)(?::([^\]]+))?\]\s+(?:\[pid [^\]]+\]\s+)?(.*)$/', $raw, $m)) {
            $date = DateTime::createFromFormat('D M d H:i:s.u Y', $m[1]);
            $entries[] = [
                'timestamp' => $date ? $date->format('Y-m-d H:i:s') : $m[1],
                'module'    => $m[2],
                'level'     => ($m[3] ?? '') !== '' ? $m[3] : $m[2],
                'message'   => $m[4],
                'raw'       => $raw,
            ];
        } else {
            $entries[] = [
                'timestamp' => null,
                'module'    => null,
                'level'     => null,
                'message'   => $raw,
                'raw'       => $raw,
            ];
        }
    }

    return ['file' => basename($path), 'count' => count($entries), 'entries' => $entries];
}

/**
 * Latest Apache access log entries, newest first.
 * Privacy: user-agent, referer and the query string (?token=...) are NOT returned.
 */
function get_access_logs(string $path, int $count): array
{
    $count   = max(1, min(100, $count));
    $entries = [];

    foreach (array_reverse(tail_file($path, $count)) as $line) {
        // Combined log format: IP ident user [time] "METHOD /path HTTP/1.1" status bytes ...
        if (preg_match('/^(\S+) \S+ \S+ \[([^\]]+)\] "([^"]*)" (\d{3}) (\S+)/', $line, $m)) {
            $date = DateTime::createFromFormat('d/M/Y:H:i:s O', $m[2]);

            $request  = explode(' ', $m[3], 3);
            $pathOnly = isset($request[1]) ? strtok($request[1], '?') : false;

            $entries[] = [
                'parsed'      => true,
                'ip'          => $m[1],
                'timestamp'   => $date ? $date->format('Y-m-d H:i:s') : $m[2],
                'method'      => $request[0] !== '' ? $request[0] : null,
                'path'        => $pathOnly === false ? null : shorten_line($pathOnly),
                'protocol'    => $request[2] ?? null,
                'status_code' => (int) $m[4],
                'bytes'       => ctype_digit($m[5]) ? (int) $m[5] : 0,
            ];
        } else {
            $entries[] = ['parsed' => false, 'raw' => shorten_line($line)];
        }
    }

    return ['file' => basename($path), 'count' => count($entries), 'entries' => $entries];
}

<?php
/**
 * response.php - Shared helpers for JSON responses and error handling.
 *
 * Every response has the same structure:
 *   success: { "success": true,  "data": {...},               "timestamp": "..." }
 *   error:   { "success": false, "error": {"message": "..."}, "timestamp": "..." }
 */

/**
 * Prepares an API request and returns the configuration array.
 * - hides PHP warnings/notices so they never mix with the JSON
 * - allows only GET (and OPTIONS for browsers)
 * - converts uncaught exceptions and fatal errors into JSON errors
 */
function api_bootstrap(): array
{
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ob_start(); // catch any accidental output

    // Warnings and notices go to the server log, not to the response.
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false; // the @ operator was used
        }
        error_log("Sentinel PHP notice: $message in $file:$line");
        return true;
    });

    // Any exception that is not caught becomes a JSON error.
    set_exception_handler(function (Throwable $e): void {
        $code = (int) $e->getCode();
        if ($code < 400 || $code > 599) {
            $code = 500;
        }
        // Only our own RuntimeExceptions carry messages that are safe to show.
        $message = ($e instanceof RuntimeException) ? $e->getMessage() : 'Internal server error.';
        error_log('Sentinel exception: ' . $e->getMessage());
        send_error($message, $code);
    });

    // Fatal errors cannot be caught, but we can still answer with JSON.
    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log('Sentinel fatal error: ' . $err['message']);
            send_error('Internal server error.', 500);
        }
    });

    $config = require __DIR__ . '/../config/server.php';
    date_default_timezone_set($config['timezone']);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        header('Allow: GET, OPTIONS');
        send_json([], 204);
    }
    if ($method !== 'GET') {
        header('Allow: GET, OPTIONS');
        send_error('Method not allowed. Use GET.', 405);
    }

    return $config;
}

/** Sends a JSON document and stops the script. */
function send_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean(); // remove anything printed by mistake
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *'); // lets the dashboard call us from any origin
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    if ($status !== 204) {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        echo $json !== false
            ? $json
            : '{"success":false,"error":{"message":"JSON encoding failed."}}';
    }
    exit;
}

/** Success response: { success: true, data: ..., timestamp: ... } */
function send_success(array $data, int $status = 200): void
{
    send_json([
        'success'   => true,
        'data'      => $data,
        'timestamp' => date('Y-m-d H:i:s'),
    ], $status);
}

/** Error response: { success: false, error: { message }, timestamp } */
function send_error(string $message, int $status = 500): void
{
    send_json([
        'success'   => false,
        'error'     => ['message' => $message],
        'timestamp' => date('Y-m-d H:i:s'),
    ], $status);
}

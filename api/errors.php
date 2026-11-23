<?php
/** errors.php - Latest Apache error log entries (GET) */
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/logs.php';

$config = api_bootstrap();
send_success(get_error_logs($config['error_log'], (int) $config['log_lines']));

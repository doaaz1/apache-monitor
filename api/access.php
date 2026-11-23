<?php
/** access.php - Latest Apache access log entries (GET) */
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/logs.php';

$config = api_bootstrap();
send_success(get_access_logs($config['access_log'], (int) $config['log_lines']));

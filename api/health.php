<?php
/** health.php - HTTP health check (GET) */
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/apache.php';

$config = api_bootstrap();
send_success(check_http_health($config['monitored_url'], (int) $config['http_timeout']));

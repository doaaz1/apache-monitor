<?php
/** cpu.php - CPU usage (GET) */
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/system.php';

$config = api_bootstrap();
send_success(get_cpu_usage((int) $config['cpu_sample_ms']));

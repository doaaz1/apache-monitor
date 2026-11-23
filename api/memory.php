<?php
/** memory.php - Memory usage (GET) */
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/system.php';

$config = api_bootstrap();
send_success(get_memory_usage());

<?php
/** status.php - Apache service status (GET) */
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/apache.php';

$config = api_bootstrap();
send_success(get_apache_status($config['service_name']));

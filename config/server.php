<?php

return [
    'app_name' => 'Apache Web Server Monitoring System',
    'service_name' => 'apache2',
    'access_log' => '/var/log/apache2/access.log',
    'error_log' => '/var/log/apache2/error.log',
    'log_lines' => 15,
    'refresh_interval_ms' => 5000,
];
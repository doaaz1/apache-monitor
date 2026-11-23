<?php
/**
 * server.php - Central settings for the Sentinel backend.
 * Change values here only. No passwords or secrets are stored in this file.
 */
return [
    // Name of the monitored systemd service (letters, digits, . _ - @ only)
    'service_name'   => 'apache2',

    // Website that the HTTP health check will request (Apache on port 80)
    'monitored_url'  => 'http://localhost/',

    // Maximum seconds to wait for the website to answer
    'http_timeout'   => 5,

    // Apache log files (fixed paths; never taken from API parameters)
    'error_log'      => '/var/log/apache2/error.log',
    'access_log'     => '/var/log/apache2/access.log',

    // Number of log entries returned by errors.php and access.php
    'log_lines'      => 20,

    // Time between the two /proc/stat readings used for CPU usage (milliseconds)
    'cpu_sample_ms'  => 500,

    // Timezone used for the "timestamp" field in every response
    'timezone'       => 'Asia/Riyadh',
];

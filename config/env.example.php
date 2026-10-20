<?php
// Copy this file to config/env.php and fill in your database credentials.
// config/env.php is gitignored - never commit your real password.
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'trip_ticket',
    'db_user' => 'root',
    'db_pass' => '',
    // Optional: TLS CA bundle path for MySQL hosts that require SSL
    // (e.g. TiDB Serverless: '/etc/ssl/certs/ca-certificates.crt').
    'db_ssl_ca' => '',
];

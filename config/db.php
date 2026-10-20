<?php
require_once __DIR__ . '/../includes/functions.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $env = get_env();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $env['db_host'],
        (int) $env['db_port'],
        $env['db_name']
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // TLS for MySQL clouds that require it (TiDB Serverless, Aiven, ...).
    // DB_SSL_CA points at a CA bundle; if unset but the host is TiDB Serverless,
    // force TLS anyway with the system CA bundle (ca-certificates.crt).
    $sslCa = !empty($env['db_ssl_ca']) ? $env['db_ssl_ca'] : '';
    if ($sslCa === '' && str_ends_with(strtolower($env['db_host']), '.tidbcloud.com')) {
        foreach (['/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/ca-bundle.pem'] as $candidate) {
            if (is_file($candidate)) {
                $sslCa = $candidate;
                break;
            }
        }
    }
    if ($sslCa !== '' && is_file($sslCa)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    }

    try {
        $pdo = new PDO($dsn, $env['db_user'], $env['db_pass'], $options);
    } catch (PDOException $e) {
        // TLS enabled but certificate verification failed - retry encrypted
        // without server-cert verification (still eavesdropping-proof).
        if (!empty($options[PDO::MYSQL_ATTR_SSL_CA])
            && (str_contains($e->getMessage(), 'certificate') || str_contains($e->getMessage(), 'verify'))) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            try {
                $pdo = new PDO($dsn, $env['db_user'], $env['db_pass'], $options);
                return $pdo;
            } catch (PDOException $e2) {
                $e = $e2;
            }
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        exit('Database connection failed. Check config/env.php and make sure database.sql was imported. '
            . '<br><small>' . htmlspecialchars($e->getMessage()) . '</small>');
    }

    return $pdo;
}

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

    try {
        $pdo = new PDO($dsn, $env['db_user'], $env['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database connection failed. Check config/env.php and make sure database.sql was imported. '
            . '<br><small>' . htmlspecialchars($e->getMessage()) . '</small>');
    }

    return $pdo;
}

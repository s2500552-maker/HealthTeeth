<?php
// Single shared PDO connection. Prepared statements are used everywhere.

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $d = app_config()['db'];
    $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";

    try {
        $pdo = new PDO($dsn, $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Log the real reason (may include host info); show nothing sensitive to the user.
        error_log('HealthTeeth DB connection failed: ' . $e->getMessage());
        throw new RuntimeException('Service temporarily unavailable.');
    }

    return $pdo;
}

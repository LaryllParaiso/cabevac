<?php
/**
 * CabEvac — Database Connection (PDO Singleton)
 * 
 * Provides a single PDO connection instance to MySQL via XAMPP.
 * Uses environment variables from .env file.
 * 
 * @package CabEvac
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

loadEnv(__DIR__ . '/../.env');

/**
 * Get the PDO database connection singleton.
 * 
 * @return PDO Active database connection
 * @throws RuntimeException If connection fails
 */
function getDb(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $dbName = $_ENV['DB_NAME'] ?? 'cabevac_db';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
        ]);
    } catch (PDOException $e) {
        logError('Database connection failed', [
            'message' => $e->getMessage(),
            'host' => $host,
            'database' => $dbName
        ]);
        throw new RuntimeException('Database connection failed. Check logs for details.');
    }

    return $pdo;
}

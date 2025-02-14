<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$db_config = [
    'host' => getenv('PGHOST'),
    'port' => getenv('PGPORT'),
    'dbname' => getenv('PGDATABASE'),
    'user' => getenv('PGUSER'),
    'password' => getenv('PGPASSWORD')
];

try {
    $pdo = new PDO(
        "pgsql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']}",
        $db_config['user'],
        $db_config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
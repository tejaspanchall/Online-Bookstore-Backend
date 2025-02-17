<?php
require __DIR__ . '/../vendor/autoload.php'; // Load Composer packages

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // Load .env file
$dotenv->load();

// Fetch DB credentials from .env
$host = $_ENV["PGHOST"];
$dbname = $_ENV["PGDATABASE"];
$user = $_ENV["PGUSER"];
$pass = $_ENV["PGPASSWORD"];
$port = $_ENV["PGPORT"];

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]));
}
?>
<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

$query = $_GET['q'] ?? '';

if (empty($query)) {
    $stmt = $pdo->query("SELECT * FROM books");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

$sql = "SELECT * FROM books 
        WHERE title ILIKE ? 
        OR isbn ILIKE ? 
        OR author ILIKE ?";
$stmt = $pdo->prepare($sql);
$param = "%$query%";

try {
    $stmt->execute([$param, $param, $param]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(PDOException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
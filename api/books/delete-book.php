<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

JWTMiddleware::initialize($_ENV['JWT_SECRET_KEY']);
$tokenData = JWTMiddleware::validateToken();

if (!$tokenData) {
    sendResponse(['error' => 'Invalid or missing token'], 401);
}

if ($tokenData->role !== 'teacher') {
    sendResponse(['error' => 'Only teachers can delete books'], 403);
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['id']) || !is_numeric($data['id'])) {
    sendResponse(['error' => 'Invalid book ID'], 400);
}

try {
    $pdo->beginTransaction();
    
    $checkStmt = $pdo->prepare("SELECT id FROM books WHERE id = :id");
    $checkStmt->execute([':id' => $data['id']]);
    if (!$checkStmt->fetch()) {
        $pdo->rollBack();
        sendResponse(['error' => 'Book not found'], 404);
    }

    $stmt = $pdo->prepare("DELETE FROM user_books WHERE book_id = :id");
    $stmt->execute([':id' => $data['id']]);

    $stmt = $pdo->prepare("DELETE FROM books WHERE id = :id");
    $stmt->execute([':id' => $data['id']]);

    $pdo->commit();
    sendResponse(['success' => true, 'message' => 'Book deleted successfully']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Database error: ' . $e->getMessage());
    sendResponse(['error' => 'Database error', 'message' => 'An error occurred while deleting the book'], 500);
}
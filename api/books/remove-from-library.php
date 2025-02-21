<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    JWTMiddleware::initialize($_ENV['JWT_SECRET_KEY']);
    $tokenData = JWTMiddleware::validateToken();
    
    if (!$tokenData || !isset($tokenData->userId)) {
        throw new Exception('Invalid or missing authentication');
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request data'
        ]);
        exit;
    }

    $deleteStmt = $pdo->prepare("
        DELETE FROM user_books
        WHERE user_id = :userId AND book_id = :bookId
    ");
    
    $deleteStmt->execute([
        ':userId' => $tokenData->userId,
        ':bookId' => $data['id']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Book removed from library'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
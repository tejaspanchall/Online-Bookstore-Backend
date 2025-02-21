<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

    $userId = $tokenData->userId;
    
    $stmt = $pdo->prepare("
        SELECT b.id, b.title, b.author, b.isbn, b.image, b.description
        FROM books b
        INNER JOIN user_books ub ON b.id = ub.book_id
        WHERE ub.user_id = :userId
        ORDER BY b.title ASC
    ");
    
    $stmt->execute([':userId' => $userId]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $books
    ]);

} catch (Exception $e) {
    $statusCode = $e instanceof PDOException ? 500 : 401;
    http_response_code($statusCode);
    echo json_encode([
        'status' => 'error',
        'message' => $statusCode === 500 ? 'Database error occurred' : $e->getMessage()
    ]);
}
<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

    if (!$data || !isset($data['isbn'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request data'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $checkBook = $pdo->prepare("SELECT id FROM books WHERE isbn = :isbn");
    $checkBook->execute([':isbn' => $data['isbn']]);
    $existingBook = $checkBook->fetch(PDO::FETCH_ASSOC);
    
    if ($existingBook) {
        $bookId = $existingBook['id'];
        
        $updateBook = $pdo->prepare("
            UPDATE books 
            SET title = :title, author = :author, image = :image, description = :description
            WHERE id = :id
        ");
        
        $updateBook->execute([
            ':id' => $bookId,
            ':title' => $data['title'] ?? '',
            ':author' => $data['author'] ?? '',
            ':image' => $data['image'] ?? '',
            ':description' => $data['description'] ?? ''
        ]);
    } else {
        $insertBook = $pdo->prepare("
            INSERT INTO books (isbn, title, author, image, description) 
            VALUES (:isbn, :title, :author, :image, :description)
            RETURNING id
        ");
        
        $insertBook->execute([
            ':isbn' => $data['isbn'],
            ':title' => $data['title'] ?? '',
            ':author' => $data['author'] ?? '',
            ':image' => $data['image'] ?? '',
            ':description' => $data['description'] ?? ''
        ]);
        
        $bookResult = $insertBook->fetch(PDO::FETCH_ASSOC);
        $bookId = $bookResult['id'];
    }

    $addToLibrary = $pdo->prepare("
        INSERT INTO user_books (user_id, book_id) 
        VALUES (:userId, :bookId)
    ");
    
    $addToLibrary->execute([
        ':userId' => $tokenData->userId,
        ':bookId' => $bookId
    ]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Book successfully added to library'
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

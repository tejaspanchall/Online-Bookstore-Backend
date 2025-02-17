<?php
require_once '../../config/database.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

session_start();

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestLog = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'session' => isset($_SESSION['user_id']) ? 'exists' : 'missing'
];

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'debug' => $requestLog
    ]);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$requestLog['rawInput'] = $json;
$requestLog['parsedData'] = $data;

if (!$data || !isset($data['isbn'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid request data',
        'debug' => $requestLog
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $insertBook = $pdo->prepare("
        INSERT INTO books (isbn, title, author, image, description) 
        VALUES (:isbn, :title, :author, :image, :description)
        ON CONFLICT (isbn) DO UPDATE SET
            title = EXCLUDED.title,
            author = EXCLUDED.author,
            image = EXCLUDED.image,
            description = EXCLUDED.description
        RETURNING id
    ");
    
    $bookData = [
        ':isbn' => $data['isbn'],
        ':title' => $data['title'] ?? '',
        ':author' => $data['author'] ?? '',
        ':image' => $data['image'] ?? '',
        ':description' => $data['description'] ?? ''
    ];
    
    $insertBook->execute($bookData);
    $bookResult = $insertBook->fetch(PDO::FETCH_ASSOC);
    $bookId = $bookResult['id'];

    $addToLibrary = $pdo->prepare("
        INSERT INTO user_books (user_id, book_id) 
        VALUES (:userId, :bookId)
        ON CONFLICT (user_id, book_id) DO NOTHING
    ");
    
    $addToLibrary->execute([
        ':userId' => $_SESSION['user_id'],
        ':bookId' => $bookId
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Book successfully added to library',
        'debug' => [
            'request' => $requestLog,
            'bookId' => $bookId,
            'userId' => $_SESSION['user_id']
        ]
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'debug' => [
            'request' => $requestLog,
            'sqlState' => $e->errorInfo[0],
            'errorCode' => $e->errorInfo[1],
            'errorMsg' => $e->errorInfo[2]
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'debug' => $requestLog
    ]);
}
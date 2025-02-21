<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authorization header missing or invalid']);
    exit;
}

$token = $matches[1];

JWTMiddleware::initialize($_ENV['JWT_SECRET_KEY']);
$tokenData = JWTMiddleware::validateToken($token);

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$tokenData) {
    sendResponse(['error' => 'Invalid or expired token'], 401);
}

if ($tokenData->role !== 'teacher') {
    sendResponse(['error' => 'Only teachers can update books'], 403);
}

$json = file_get_contents('php://input');
if (!$json) {
    sendResponse(['error' => 'No data received'], 400);
}

$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendResponse(['error' => 'Invalid JSON data: ' . json_last_error_msg()], 400);
}

$required = ['id', 'title', 'author', 'isbn', 'description', 'image'];
foreach ($required as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        sendResponse(['error' => "Missing required field: $field"], 400);
    }
}

$data = array_map('strip_tags', $data);
$data = array_map('trim', $data);

if (strlen($data['title']) > 255) {
    sendResponse(['error' => 'Title must not exceed 255 characters'], 400);
}
if (strlen($data['author']) > 255) {
    sendResponse(['error' => 'Author must not exceed 255 characters'], 400);
}
if (strlen($data['description']) > 2000) {
    sendResponse(['error' => 'Description must not exceed 2000 characters'], 400);
}

if (!filter_var($data['image'], FILTER_VALIDATE_URL) || 
    !preg_match('/^https?:\/\//', $data['image'])) {
    sendResponse(['error' => 'Invalid image URL. Must be an HTTP/HTTPS URL'], 400);
}

try {
    $pdo->beginTransaction();
    
    error_log('Updating book ID: ' . $data['id']);
    
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$data['id']]);
    $currentBook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentBook) {
        $pdo->rollBack();
        error_log('Book not found with ID: ' . $data['id']);
        sendResponse(['error' => 'Book not found'], 404);
    }

    error_log('Checking for duplicate ISBN: ' . $data['isbn']);
    
    $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ? AND id != ?");
    $stmt->execute([$data['isbn'], $data['id']]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        error_log('Duplicate ISBN found: ' . $data['isbn']);
        sendResponse(['error' => 'Another book with this ISBN already exists'], 400);
    }

    error_log('Executing UPDATE query for book ID: ' . $data['id']);
    
    $stmt = $pdo->prepare("
        UPDATE books SET 
            title = :title, 
            author = :author, 
            isbn = :isbn, 
            description = :description,
            image = :image
        WHERE id = :id
    ");

    $params = [
        ':id' => $data['id'],
        ':title' => $data['title'],
        ':author' => $data['author'],
        ':isbn' => $data['isbn'],
        ':description' => $data['description'],
        ':image' => $data['image']
    ];
    
    error_log('Update parameters: ' . json_encode($params));
    
    $result = $stmt->execute($params);
    
    if (!$result) {
        $pdo->rollBack();
        $errorInfo = $stmt->errorInfo();
        error_log('Database error in UPDATE: ' . json_encode($errorInfo));
        sendResponse(['error' => 'Database error: ' . $errorInfo[2]], 500);
    }
    
    $pdo->commit();
    error_log('Book update successful for ID: ' . $data['id']);

    sendResponse([
        'success' => true,
        'message' => 'Book updated successfully',
        'data' => [
            'id' => $data['id'],
            'title' => $data['title'],
            'author' => $data['author'],
            'isbn' => $data['isbn'],
            'description' => $data['description'],
            'image' => $data['image']
        ]
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('PDO Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    error_log('SQL State: ' . $e->getCode());
    sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('General Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    sendResponse(['error' => 'Error updating book: ' . $e->getMessage()], 500);
}
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
    exit(0);
}

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'No authorization token provided']);
    exit;
}

try {
    JWTMiddleware::initialize($_ENV['JWT_SECRET_KEY']);
    $tokenData = JWTMiddleware::validateToken($token);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

if ($tokenData->role !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Only teachers can add books']);
    exit;
}

$json = file_get_contents('php://input');
if (!$json) {
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data: ' . json_last_error_msg()]);
    exit;
}

$required = ['title', 'description', 'isbn', 'author', 'image'];
foreach ($required as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
    $data[$field] = trim($data[$field]);
}

if (!filter_var($data['image'], FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image URL']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
    $stmt->execute([$data['isbn']]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'A book with this ISBN already exists']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO books (title, image, description, isbn, author) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['title'],
        $data['image'],
        $data['description'],
        $data['isbn'],
        $data['author']
    ]);
    
    $bookId = $pdo->lastInsertId();
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Book added successfully',
        'data' => [
            'id' => $bookId,
            'title' => $data['title'],
            'author' => $data['author'],
            'isbn' => $data['isbn'],
            'description' => $data['description'],
            'image' => $data['image']
        ]
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error adding book: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add book']);
    exit;
}
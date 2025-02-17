<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

session_start();

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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

if (!isset($_SESSION['user_id'])) {
    sendResponse(['error' => 'Please login to continue'], 401);
}

try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'teacher') {
        sendResponse(['error' => 'Only teachers can add books'], 403);
    }
} catch(PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    sendResponse(['error' => 'Server error occurred'], 500);
}

$json = file_get_contents('php://input');
if (!$json) {
    sendResponse(['error' => 'No data received'], 400);
}

$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendResponse(['error' => 'Invalid JSON data: ' . json_last_error_msg()], 400);
}

$required = ['title', 'description', 'isbn', 'author', 'image'];
foreach ($required as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        sendResponse(['error' => "Missing required field: $field"], 400);
    }
    $data[$field] = trim($data[$field]);
}

if (!filter_var($data['image'], FILTER_VALIDATE_URL)) {
    sendResponse(['error' => 'Invalid image URL'], 400);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
    $stmt->execute([$data['isbn']]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        sendResponse(['error' => 'A book with this ISBN already exists'], 400);
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
    
    sendResponse([
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
    sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error adding book: ' . $e->getMessage());
    sendResponse(['error' => 'Failed to add book: ' . $e->getMessage()], 500);
}
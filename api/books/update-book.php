<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

session_start();

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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
        sendResponse(['error' => 'Only teachers can update books'], 403);
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

$required = ['id', 'title', 'author', 'isbn', 'description', 'image'];
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

    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$data['id']]);
    $currentBook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentBook) {
        $pdo->rollBack();
        sendResponse(['error' => 'Book not found'], 404);
    }

    $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ? AND id != ?");
    $stmt->execute([$data['isbn'], $data['id']]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        sendResponse(['error' => 'Another book with this ISBN already exists'], 400);
    }

    $stmt = $pdo->prepare("
        UPDATE books SET 
            title = :title, 
            author = :author, 
            isbn = :isbn, 
            description = :description,
            image = :image
        WHERE id = :id
    ");
    
    $result = $stmt->execute([
        ':id' => $data['id'],
        ':title' => $data['title'],
        ':author' => $data['author'],
        ':isbn' => $data['isbn'],
        ':description' => $data['description'],
        ':image' => $data['image']
    ]);
    
    if (!$result) {
        $pdo->rollBack();
        sendResponse(['error' => 'Failed to update book: ' . implode(', ', $stmt->errorInfo())], 500);
    }
    
    $pdo->commit();

    sendResponse([
        'status' => 'success',
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
    error_log('Database error: ' . $e->getMessage());
    sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error updating book: ' . $e->getMessage());
    sendResponse(['error' => 'Failed to update book: ' . $e->getMessage()], 500);
}
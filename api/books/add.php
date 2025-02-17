<?php
require_once '../../config/database.php';
require_once '../../config/cloudinary.php';
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

if (!$cloudinary) {
    sendResponse(['error' => 'Image upload service not configured'], 500);
}

if (!isset($_SESSION['user_id'])) {
    sendResponse(['error' => 'Please login to continue'], 401);
}

try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user['role'] !== 'teacher') {
        sendResponse(['error' => 'Only teachers can add books'], 403);
    }
} catch(PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    sendResponse(['error' => 'Server error occurred'], 500);
}
$required = ['title', 'description', 'isbn', 'author'];
$data = [];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        sendResponse(['error' => "Missing required field: $field"], 400);
    }
    $data[$field] = trim($_POST[$field]);
}

// Handle image upload
$imageUrl = null;
if (!empty($_FILES['image'])) {
    // Validate image
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($_FILES['image']['type'], $allowedTypes)) {
        sendResponse(['error' => 'Invalid image type. Allowed types: JPG, PNG, GIF, WEBP'], 400);
    }

    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        sendResponse(['error' => 'Image too large (max 5MB)'], 400);
    }

    try {
        $result = $cloudinary->uploadApi()->upload(
            $_FILES['image']['tmp_name'],
            [
                'folder' => 'book_covers',
                'resource_type' => 'image',
                'transformation' => [
                    'quality' => 'auto',
                    'fetch_format' => 'auto',
                    'width' => 800,
                    'height' => 1200,
                    'crop' => 'limit'
                ]
            ]
        );
        $imageUrl = $result['secure_url'];
    } catch (Exception $e) {
        error_log('Cloudinary upload failed: ' . $e->getMessage());
        sendResponse(['error' => 'Failed to upload image'], 500);
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
    $stmt->execute([$data['isbn']]);
    if ($stmt->fetch()) {
        if ($imageUrl) {
            try {
                preg_match('/book_covers\/[^.]+/', $imageUrl, $matches);
                if (!empty($matches)) {
                    $cloudinary->uploadApi()->destroy($matches[0]);
                }
            } catch (Exception $e) {
                error_log('Failed to delete uploaded image: ' . $e->getMessage());
            }
        }
        
        $pdo->rollBack();
        sendResponse(['error' => 'A book with this ISBN already exists'], 400);
    }

    $stmt = $pdo->prepare("
        INSERT INTO books (title, image, description, isbn, author, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $data['title'],
        $imageUrl,
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
            'image_url' => $imageUrl
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    
    if ($imageUrl) {
        try {
            preg_match('/book_covers\/[^.]+/', $imageUrl, $matches);
            if (!empty($matches)) {
                $cloudinary->uploadApi()->destroy($matches[0]);
            }
        } catch (Exception $deleteError) {
            error_log('Failed to delete uploaded image: ' . $deleteError->getMessage());
        }
    }
    
    error_log('Error adding book: ' . $e->getMessage());
    sendResponse(['error' => 'Failed to add book'], 500);
}
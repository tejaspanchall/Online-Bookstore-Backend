<?php
require_once '../../config/database.php';
require_once '../../config/cloudinary.php';
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

    if (!$user || $user['role'] !== 'teacher') {
        sendResponse(['error' => 'Only teachers can update books'], 403);
    }
} catch(PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    sendResponse(['error' => 'Server error occurred'], 500);
}

$required = ['id', 'title', 'author', 'isbn', 'description'];
$data = [];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        sendResponse(['error' => "Missing required field: $field"], 400);
    }
    $data[$field] = trim($_POST[$field]);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$data['id']]);
    $currentBook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentBook) {
        sendResponse(['error' => 'Book not found'], 404);
    }
} catch(PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    sendResponse(['error' => 'Server error occurred'], 500);
}

$imageUrl = null;
if (!empty($_FILES['image'])) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($_FILES['image']['type'], $allowedTypes)) {
        sendResponse(['error' => 'Invalid image type. Allowed types: JPG, PNG, GIF, WEBP'], 400);
    }

    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        sendResponse(['error' => 'Image too large (max 5MB)'], 400);
    }

    try {
        if ($currentBook['image']) {
            preg_match('/book_covers\/[^.]+/', $currentBook['image'], $matches);
            if (!empty($matches)) {
                try {
                    $cloudinary->uploadApi()->destroy($matches[0]);
                } catch (Exception $e) {
                    error_log('Failed to delete old image: ' . $e->getMessage());
                }
            }
        }

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

    $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ? AND id != ?");
    $stmt->execute([$data['isbn'], $data['id']]);
    if ($stmt->fetch()) {
        if ($imageUrl) {
            try {
                preg_match('/book_covers\/[^.]+/', $imageUrl, $matches);
                if (!empty($matches)) {
                    $cloudinary->uploadApi()->destroy($matches[0]);
                }
            } catch (Exception $e) {
                error_log('Failed to delete new image: ' . $e->getMessage());
            }
        }
        
        $pdo->rollBack();
        sendResponse(['error' => 'Another book with this ISBN already exists'], 400);
    }

    $sql = "UPDATE books SET 
            title = :title, 
            author = :author, 
            isbn = :isbn, 
            description = :description,
            updated_at = NOW()";
    
    $params = [
        ':id' => $data['id'],
        ':title' => $data['title'],
        ':author' => $data['author'],
        ':isbn' => $data['isbn'],
        ':description' => $data['description']
    ];

    if ($imageUrl) {
        $sql .= ", image = :image";
        $params[':image'] = $imageUrl;
    }

    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        sendResponse(['error' => 'No changes were made to the book'], 400);
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
            'image_url' => $imageUrl ?? $currentBook['image']
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
            error_log('Failed to delete new image: ' . $deleteError->getMessage());
        }
    }
    
    error_log('Error updating book: ' . $e->getMessage());
    sendResponse(['error' => 'Failed to update book'], 500);
}
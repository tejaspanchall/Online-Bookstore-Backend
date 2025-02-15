<?php
require_once '../../config/database.php';
require 'vendor/autoload.php';

use Cloudinary\Cloudinary;

ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'true');
session_start();

header('Access-Control-Allow-Origin: https://online-bookstore-frontend.vercel.app');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

error_log('Session data: ' . print_r($_SESSION, true));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Please login to continue',
        'session_status' => session_status(),
        'session_id' => session_id()
    ]);
    exit;
}

$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => getenv('CLOUDINARY_API_KEY'),
        'api_secret' => getenv('CLOUDINARY_API_SECRET'),
    ],
]);

try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can add books']);
        exit;
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$data = $_POST;
if (empty($data)) {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
}

error_log('Received data: ' . print_r($data, true));

$required = ['title', 'description', 'isbn', 'author'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$imagePath = null;
if (!empty($_FILES['image'])) {
    $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($imageFileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image type']);
        exit;
    }

    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'Image too large (max 5MB)']);
        exit;
    }

    try {
        $result = $cloudinary->uploadApi()->upload($_FILES['image']['tmp_name'], [
            'folder' => 'book_covers',
            'transformation' => [
                'width' => 800,
                'height' => 1200,
                'crop' => 'limit',
                'quality' => 'auto',
                'fetch_format' => 'auto'
            ]
        ]);
        
        $imagePath = $result['secure_url'];
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload image: ' . $e->getMessage()]);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
    $stmt->execute([$data['isbn']]);
    $existingBook = $stmt->fetch();

    if ($existingBook) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'A book with this ISBN already exists']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO books (title, image, description, isbn, author) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['title'],
        $imagePath,
        $data['description'],
        $data['isbn'],
        $data['author']
    ]);
    $bookId = $pdo->lastInsertId();

    $pdo->commit();
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Book added successfully',
        'id' => $bookId,
        'image_path' => $imagePath
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
<?php
require_once '../../config/database.php';
require 'vendor/autoload.php';

use Cloudinary\Cloudinary;

session_start();

header('Access-Control-Allow-Origin: https://online-bookstore-frontend.vercel.app');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check login status
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Initialize Cloudinary
$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => getenv('CLOUDINARY_API_KEY'),
        'api_secret' => getenv('CLOUDINARY_API_SECRET'),
    ],
]);

// Handle form data
$data = $_POST;
if (empty($data)) {
    $data = json_decode(file_get_contents('php://input'), true);
}

$required = ['id', 'title', 'author', 'isbn', 'description'];
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
        // Get the old image URL
        $stmt = $pdo->prepare("SELECT image FROM books WHERE id = ?");
        $stmt->execute([$data['id']]);
        $oldImageUrl = $stmt->fetchColumn();

        // Delete old image from Cloudinary if it exists
        if ($oldImageUrl) {
            try {
                $parsedUrl = parse_url($oldImageUrl);
                $pathParts = explode('/', $parsedUrl['path']);
                $publicId = 'book_covers/' . pathinfo(end($pathParts), PATHINFO_FILENAME);
                $cloudinary->uploadApi()->destroy($publicId);
            } catch (Exception $e) {
                // Log error but continue with new upload
                error_log('Failed to delete old image: ' . $e->getMessage());
            }
        }

        // Upload new image
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

    $sql = "UPDATE books SET title = :title, author = :author, isbn = :isbn, description = :description";
    $params = [
        ':id' => $data['id'],
        ':title' => $data['title'],
        ':author' => $data['author'],
        ':isbn' => $data['isbn'],
        ':description' => $data['description']
    ];

    if ($imagePath) {
        $sql .= ", image = :image";
        $params[':image'] = $imagePath;
    }

    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $pdo->commit();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Book updated successfully',
        'image_path' => $imagePath
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
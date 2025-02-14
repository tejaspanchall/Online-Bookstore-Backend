<?php
require_once '../../config/database.php';
session_start();

header('Access-Control-Allow-Origin: https://online-bookstore-frontend.vercel.app');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$required = ['id', 'title', 'author', 'isbn', 'description'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$imagePath = null;
if (!empty($_FILES['image'])) {
    $uploadDir = '../../uploads/book_covers/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $imageFileName = uniqid() . '_' . basename($_FILES['image']['name']);
    $targetFilePath = $uploadDir . $imageFileName;

    $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
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

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
        $stmt = $pdo->prepare("SELECT image FROM books WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $oldImage = $stmt->fetchColumn();

        $imagePath = '/online-bookstore/backend/uploads/book_covers/' . $imageFileName;

        if ($oldImage) {
            $oldImagePath = str_replace('/online-bookstore/backend', '..', $oldImage);
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }
    }
}

try {
    $sql = "UPDATE books SET title = :title, author = :author, isbn = :isbn, description = :description";
    $params = [
        ':id' => $_POST['id'],
        ':title' => $_POST['title'],
        ':author' => $_POST['author'],
        ':isbn' => $_POST['isbn'],
        ':description' => $_POST['description']
    ];

    if ($imagePath) {
        $sql .= ", image = :image";
        $params[':image'] = $imagePath;
    }

    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Book updated successfully',
        'image_path' => $imagePath
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
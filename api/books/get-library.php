<?php
require_once '../../config/database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: https://online-bookstore-frontend.vercel.app');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.title, b.author, b.isbn, b.image, b.description
        FROM books b
        INNER JOIN user_books ub ON b.id = ub.book_id
        WHERE ub.user_id = :userId
        ORDER BY b.title ASC
    ");
    
    $stmt->execute([':userId' => $_SESSION['user_id']]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($books);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
<?php
require_once '../../config/database.php';

// Set session cookie parameters before starting session
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.railway.app',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();

header('Access-Control-Allow-Origin: https://online-bookstore-frontend.vercel.app');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if session exists and contains user_id
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No active session']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, email, firstname, lastname, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Update session with current user data
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode([
            'status' => 'valid',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'firstname' => $user['firstname'],
                'lastname' => $user['lastname'],
                'role' => $user['role']
            ]
        ]);
    } else {
        // User no longer exists in database
        session_destroy();
        http_response_code(401);
        echo json_encode(['error' => 'Invalid session']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
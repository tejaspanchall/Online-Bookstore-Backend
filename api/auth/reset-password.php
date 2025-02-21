<?php
require_once '../../config/database.php';
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

JWTMiddleware::initialize($_ENV['JWT_SECRET_KEY']);

header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND']);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'];
$password = $data['password'];

if (empty($token) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token IS NOT NULL");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired reset token']);
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?");
if ($stmt->execute([$hashed_password, $token])) {
    echo json_encode(['status' => 'success', 'message' => 'Password reset successful']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to reset password']);
}
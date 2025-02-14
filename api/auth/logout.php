<?php
require_once '../../config/database.php';

session_start();

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header('Access-Control-Allow-Origin: https://online-bookstore-seven.vercel.app');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

echo json_encode(['status' => 'success']);
?>
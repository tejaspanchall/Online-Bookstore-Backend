<?php
header("Content-Type: application/json");
require_once "config/database.php";
$query = $pdo->query("SELECT * FROM test");
$users = $query->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(["status" => "success", "data" => $users]);
?>
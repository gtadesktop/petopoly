<?php
// php/db.php
$host = 'sql301.infinityfree.com';
$db   = 'if0_39544258_petopoly';
$user = 'if0_39544258';
$pass = 'nu2U1h5eNyN0GD';
$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database connection failed']));
}
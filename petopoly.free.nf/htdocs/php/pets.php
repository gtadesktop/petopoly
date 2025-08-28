<?php
// php/pets.php
header('Content-Type: application/json');
require 'db.php';
session_start();
$uid = $_SESSION['uid'] ?? 0;

if ($_GET['action']==='list') {
    $pets = $pdo->query("SELECT * FROM pet_templates")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($pets);
    exit;
}

if ($_GET['action']==='inventory') {
    if (!$uid) { echo json_encode([]); exit; }
    $stmt = $pdo->prepare("SELECT up.id, pt.name, pt.img, up.level, up.wins, up.losses 
        FROM user_pets up JOIN pet_templates pt ON up.template_id=pt.id
        WHERE up.user_id=?");
    $stmt->execute([$uid]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($_GET['action']==='buy') {
    $data = json_decode(file_get_contents('php://input'), true);
    $petId = (int)$data['petId'];
    $price = $pdo->query("SELECT price FROM pet_templates WHERE id=$petId")->fetchColumn();
    // Konto prüfen
    $bal = $pdo->query("SELECT balance FROM users WHERE id=$uid")->fetchColumn();
    if ($bal < $price) {
        echo json_encode(['status'=>'error','msg'=>'Nicht genug Guthaben']);
        exit;
    }
    // Abzug & Eintrag
    $pdo->prepare("UPDATE users SET balance=balance-? WHERE id=?")->execute([$price,$uid]);
    $pdo->prepare("INSERT INTO user_pets (user_id, template_id, level, wins, losses, start_value, attr_value)
        VALUES (?, ?, 1, 0, 0, ?, ?)")->execute([$uid,$petId, $price, 10]);
    echo json_encode(['status'=>'ok']);
    exit;
}

http_response_code(400);
echo json_encode(['error'=>'Unknown action']);
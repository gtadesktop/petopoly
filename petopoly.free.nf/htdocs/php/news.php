<?php
// php/news.php
header('Content-Type: application/json');
require 'db.php';
session_start();
$uid = $_SESSION['uid'] ?? 0;

$fr = $pdo->prepare("SELECT id, from_user_id, expires_at FROM friend_requests WHERE to_user_id=? AND status='pending' AND expires_at>NOW()");
$fr->execute([$uid]);
$requests = $fr->fetchAll(PDO::FETCH_ASSOC);

$ch = $pdo->prepare("SELECT id, from_user_id, petA_id, petB_id, expires_at FROM challenges WHERE to_user_id=? AND status='pending' AND expires_at>NOW()");
$ch->execute([$uid]);
$challenges = $ch->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['friend_requests'=>$requests,'challenges'=>$challenges]);
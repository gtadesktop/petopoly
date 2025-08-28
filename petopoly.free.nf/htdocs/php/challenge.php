<?php
// php/challenge.php
header('Content-Type: application/json');
require 'db.php';
session_start();
$uid = $_SESSION['uid'] ?? 0;
$in = json_decode(file_get_contents('php://input'), true);

switch ($_GET['action']) {
  case 'send':
    $to   = (int)$in['to_user'];
    $petA = (int)$in['petA'];
    $petB = (int)$in['petB'];
    $expires = date('Y-m-d H:i:s', time() + 10*60*48);
    $pdo->prepare("INSERT INTO challenges (from_user_id,to_user_id,petA_id,petB_id,status,expires_at) VALUES (?,?,?,?, 'pending',?)")
        ->execute([$uid,$to,$petA,$petB,$expires]);
    echo json_encode(['status'=>'sent']);
    break;

  case 'accept':
  case 'decline':
    $cid = (int)$in['challenge_id'];
    $stat = $_GET['action']==='accept'?'accepted':'rejected';
    $pdo->prepare("UPDATE challenges SET status=?, expires_at=NULL WHERE id=? AND to_user_id=?")
        ->execute([$stat,$cid,$uid]);
    echo json_encode(['status'=>$stat]);
    break;

  default:
    http_response_code(400);
    echo json_encode(['error'=>'Unknown action']);
}
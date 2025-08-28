<?php
// php/chat.php
header('Content-Type: application/json');
require 'db.php';
session_start();
$uid = $_SESSION['uid'] ?? 0;
$in = json_decode(file_get_contents('php://input'), true);

switch ($_GET['action']) {
  case 'send':
    $to = (int)$in['to_user'];
    $msg = trim($in['content']);
    // Prüfe Block
    $b = $pdo->prepare("SELECT blocked FROM friends WHERE user_id=? AND friend_id=?");
    $b->execute([$to,$uid]);
    if ($b->fetchColumn()) { http_response_code(204); exit; }
    $pdo->prepare("INSERT INTO messages (from_user_id,to_user_id,content,sent_at,delivered) VALUES (?,?,?,NOW(),1)")
        ->execute([$uid,$to,$msg]);
    echo json_encode(['status'=>'sent']);
    break;

  case 'fetch':
    $with = (int)$_GET['with'];
    $stmt = $pdo->prepare("SELECT from_user_id,to_user_id,content,sent_at FROM messages 
        WHERE (from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?) ORDER BY sent_at ASC");
    $stmt->execute([$uid,$with,$with,$uid]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;

  default:
    http_response_code(400);
    echo json_encode(['error'=>'Unknown action']);
}
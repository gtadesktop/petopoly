<?php
// php/daily_quests.php
header('Content-Type: application/json');
require 'db.php';
session_start();
$uid = $_SESSION['uid'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

if ($_GET['action']==='list') {
    $quests = $pdo->query("
      SELECT q.id, q.name, q.description, q.reward,
             IF(uq.claimed,1,0) AS claimed,
             IF(uq.completed_at IS NULL,0,1) AS completed
      FROM daily_quests q
      LEFT JOIN user_daily_quests uq 
        ON q.id=uq.quest_id AND uq.user_id=$uid
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($quests);
    exit;
}

if ($_GET['action']==='complete') {
    $qid = (int)$input['quest_id'];
    $pdo->prepare("
      INSERT INTO user_daily_quests (user_id, quest_id, completed_at, claimed)
      VALUES (?, ?, NOW(), 0)
      ON DUPLICATE KEY UPDATE completed_at=NOW()
    ")->execute([$uid, $qid]);
    echo json_encode(['status'=>'done']);
    exit;
}

if ($_GET['action']==='claim') {
    $qid = (int)$input['quest_id'];
    $row = $pdo->prepare("SELECT completed_at, claimed FROM user_daily_quests WHERE user_id=? AND quest_id=?");
    $row->execute([$uid,$qid]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    if ($r && $r['completed_at'] && !$r['claimed']) {
        $reward = $pdo->query("SELECT reward FROM daily_quests WHERE id=$qid")->fetchColumn();
        $pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$reward,$uid]);
        $pdo->prepare("UPDATE user_daily_quests SET claimed=1 WHERE user_id=? AND quest_id=?")->execute([$uid,$qid]);
        $pdo->prepare("UPDATE users SET extra_rate=extra_rate+0.001 WHERE id=?")->execute([$uid]);
        echo json_encode(['status'=>'claimed','reward'=>$reward]);
    } else {
        echo json_encode(['status'=>'error']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error'=>'Unknown action']);
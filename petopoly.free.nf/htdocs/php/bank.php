<?php
header('Content-Type: application/json');
require __DIR__ . '/db.php';
session_start();

try {
    // Auth prüfen
    if (!isset($_SESSION['uid'])) {
        http_response_code(401);
        echo json_encode(['error'=>'not_authenticated']);
        exit;
    }
    $uid = (int)$_SESSION['uid'];
    $action = $_GET['action'] ?? '';

    // Hilfsfunktion: monatliche Zinsen verbuchen
    function accrueInterest($pdo, $uid) {
        $stmt = $pdo->prepare("SELECT balance, base_rate, extra_rate FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $rate = ($row['base_rate'] + $row['extra_rate']) / 100;
        $interest = $row['balance'] * $rate;
        $pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")
            ->execute([$interest, $uid]);
    }

    switch ($action) {
        case 'status':
            accrueInterest($pdo, $uid);

            // Kontostand
            $balance = $pdo->prepare("SELECT balance FROM users WHERE id=?");
            $balance->execute([$uid]);
            $bal = (float)$balance->fetchColumn();

            // Sicherheiten (Pets)
            $col = $pdo->prepare("
              SELECT SUM(pt.start_value + up.level*pt.attr_value + (up.wins/(up.losses+1))*10) 
              FROM user_pets up
              JOIN pet_templates pt ON up.template_id=pt.id
              WHERE up.user_id=?
            ");
            $col->execute([$uid]);
            $security = (float)$col->fetchColumn();

            echo json_encode(['balance'=>$bal,'collateral'=>$security]);
            break;

        case 'apply':
            $input = json_decode(file_get_contents('php://input'), true);
            $amt   = isset($input['amount']) ? (float)$input['amount'] : 0;

            // erneut Sicherheiten prüfen
            $col = $pdo->prepare("
              SELECT SUM(pt.start_value + up.level*pt.attr_value + (up.wins/(up.losses+1))*10) 
              FROM user_pets up
              JOIN pet_templates pt ON up.template_id=pt.id
              WHERE up.user_id=?
            ");
            $col->execute([$uid]);
            $security = (float)$col->fetchColumn();

            if ($security * 0.5 >= $amt && $amt > 0) {
                $pdo->prepare("INSERT INTO loans (user_id, amount, interest_rate) VALUES (?, ?, 0)")
                    ->execute([$uid, $amt]);
                $pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")
                    ->execute([$amt, $uid]);
                echo json_encode(['status'=>'approved','amount'=>$amt]);
            } else {
                echo json_encode(['status'=>'denied']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error'=>'unknown_action']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
      'error'=>'server_error',
      'detail'=>$e->getMessage()  // im Prod weglassen oder in Log schreiben
    ]);
}
<?php
header('Content-Type: application/json');
require __DIR__ . '/db.php';
session_start();

// rohes JSON auslesen
$input = json_decode(file_get_contents('php://input'), true);

// Validierung
if (!$input) {
    http_response_code(400);
    echo json_encode(['status'=>'error','msg'=>'Ungültiges JSON']);
    exit;
}

try {
    switch ($_GET['action']) {

        //  Registrierung
        case 'register':
            if (empty($input['username']) || empty($input['password'])) {
                http_response_code(400);
                echo json_encode(['status'=>'error','msg'=>'Username und Passwort erforderlich']);
                exit;
            }
            // Username eintragen
            $stmt = $pdo->prepare("
              INSERT INTO users (username, pass, balance, base_rate, extra_rate) 
              VALUES (?, ?, 500.00, 3.0, 0.0)
            ");
            $hash = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt->execute([$input['username'], $hash]);
            $uid = $pdo->lastInsertId();
            $_SESSION['uid'] = $uid;
            echo json_encode(['status'=>'ok','userId'=>$uid]);
            exit;

        //  Login
        case 'login':
            if (empty($input['username']) || empty($input['password'])) {
                http_response_code(400);
                echo json_encode(['status'=>'error','msg'=>'Username und Passwort erforderlich']);
                exit;
            }
            $stmt = $pdo->prepare("
              SELECT id, pass 
              FROM users 
              WHERE username = ?
            ");
            $stmt->execute([$input['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($input['password'], $user['pass'])) {
                $_SESSION['uid'] = $user['id'];
                echo json_encode(['status'=>'ok','userId'=>$user['id']]);
            } else {
                http_response_code(401);
                echo json_encode(['status'=>'error','msg'=>'Ungültige Zugangsdaten']);
            }
            exit;

        //  Session-Status (optional)
        case 'status':
            if (!empty($_SESSION['uid'])) {
                echo json_encode(['status'=>'ok','userId'=>$_SESSION['uid']]);
            } else {
                echo json_encode(['status'=>'error']);
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode(['status'=>'error','msg'=>'Unbekannte Aktion']);
            exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    // Im Produktivmodus entfernst du besser $e->getMessage()
    echo json_encode(['status'=>'error','msg'=>'Serverfehler: '.$e->getMessage()]);
    exit;
}
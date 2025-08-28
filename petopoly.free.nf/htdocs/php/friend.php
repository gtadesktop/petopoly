<?php
header('Content-Type: application/json');
require __DIR__ . '/db.php';
session_start();

try {
    // Prüfe, ob der Nutzer eingeloggt ist
    if (empty($_SESSION['uid'])) {
        http_response_code(401);
        echo json_encode(['error' => 'not_authenticated']);
        exit;
    }
    $uid    = (int) $_SESSION['uid'];
    $action = $_GET['action'] ?? '';
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];

    switch ($action) {
        //
        // 1) Freundesliste abrufen
        //
        case 'list':
            $stmt = $pdo->prepare("
                SELECT friend_id, blocked
                FROM friends
                WHERE user_id = ?
            ");
            $stmt->execute([$uid]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        //
        // 2) Nutzer suchen
        //
        case 'search':
            $term = trim($input['username'] ?? '');
            if ($term === '') {
                echo json_encode([]);
                exit;
            }
            $like = "%$term%";
            $stmt = $pdo->prepare("
                SELECT id   AS friend_id,
                       username
                FROM users
                WHERE username LIKE ?
                  AND id <> ?
                LIMIT 20
            ");
            $stmt->execute([$like, $uid]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        //
        // 3) Freundesanfrage senden
        //
        case 'send':
            $to = (int) ($input['to_user'] ?? 0);
            if ($to <= 0 || $to === $uid) {
                http_response_code(400);
                echo json_encode(['error' => 'invalid_user']);
                exit;
            }
            // Prüfe auf bestehende Pending-Anfrage
            $chk = $pdo->prepare("
                SELECT COUNT(*) 
                FROM friend_requests
                WHERE from_user_id = ? 
                  AND to_user_id   = ? 
                  AND status       = 'pending'
            ");
            $chk->execute([$uid, $to]);
            if ($chk->fetchColumn() > 0) {
                echo json_encode(['status' => 'already_sent']);
                exit;
            }
            // Anfrage speichern
            $expires = date('Y-m-d H:i:s', time() + 10 * 60 * 24); 
            $pdo->prepare("
                INSERT INTO friend_requests
                  (from_user_id, to_user_id, status, expires_at)
                VALUES (?, ?, 'pending', ?)
            ")->execute([$uid, $to, $expires]);
            echo json_encode(['status' => 'sent']);
            break;

        //
        // 4) Freundesanfrage beantworten
        //
        case 'respond':
            $rid      = (int) ($input['request_id'] ?? 0);
            $decision = $input['decision'] ?? '';
            if (!in_array($decision, ['accepted','rejected'], true)) {
                http_response_code(400);
                echo json_encode(['error' => 'invalid_decision']);
                exit;
            }
            // Anfrage updaten
            $pdo->prepare("
                UPDATE friend_requests
                SET status      = ?,
                    expires_at  = NULL
                WHERE id        = ?
                  AND to_user_id = ?
            ")->execute([$decision, $rid, $uid]);

            if ($decision === 'accepted') {
                // Gegenseitig in friends eintragen
                // Ignoriere Doppel-Einträge bei UNIQUE-Constraint
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO friends
                      (user_id, friend_id, blocked, created_at)
                    VALUES (?, ?, 0, NOW()), (?, ?, 0, NOW())
                ");
                // from_user_id finden
                $from = $pdo->prepare("
                    SELECT from_user_id
                    FROM friend_requests
                    WHERE id = ?
                ");
                $from->execute([$rid]);
                $fromUser = (int) $from->fetchColumn();
                $stmt->execute([$uid, $fromUser, $fromUser, $uid]);
            }

            echo json_encode(['status' => $decision]);
            break;

        //
        // 5) Freund entfernen
        //
        case 'remove':
            $fid = (int) ($input['friend_id'] ?? 0);
            if ($fid <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'invalid_friend_id']);
                exit;
            }
            $pdo->prepare("
                DELETE FROM friends
                WHERE (user_id = ? AND friend_id = ?)
                   OR (user_id = ? AND friend_id = ?)
            ")->execute([$uid, $fid, $fid, $uid]);
            echo json_encode(['status' => 'removed']);
            break;

        //
        // 6) Freund blockieren
        //
        case 'block':
            $fid = (int) ($input['friend_id'] ?? 0);
            if ($fid <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'invalid_friend_id']);
                exit;
            }
            $pdo->prepare("
                UPDATE friends
                SET blocked = 1
                WHERE user_id   = ?
                  AND friend_id = ?
            ")->execute([$uid, $fid]);
            echo json_encode(['status' => 'blocked']);
            break;

        //
        // 7) Freund entblocken
        //
        case 'unblock':
            $fid = (int) ($input['friend_id'] ?? 0);
            if ($fid <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'invalid_friend_id']);
                exit;
            }
            $pdo->prepare("
                UPDATE friends
                SET blocked = 0
                WHERE user_id   = ?
                  AND friend_id = ?
            ")->execute([$uid, $fid]);
            echo json_encode(['status' => 'unblocked']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'unknown_action']);
            break;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'server_error',
        'message' => $e->getMessage()
    ]);
}
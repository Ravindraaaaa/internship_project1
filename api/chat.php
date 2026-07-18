<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$user_id = get_user_id();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    // 1. LIST CONVERSATIONS
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT c.id as conversation_id, c.created_at,
                   u.id as peer_id, u.name as peer_name, u.role as peer_role,
                   COALESCE(ap.profile_pic, sp.profile_pic, '') as peer_avatar,
                   (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                   (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                   (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0) as unread_count
            FROM conversations c
            JOIN users u ON (c.sender_id = ? AND c.receiver_id = u.id) OR (c.receiver_id = ? AND c.sender_id = u.id)
            LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND u.role = 'alumni'
            LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
            WHERE c.sender_id = ? OR c.receiver_id = ?
            ORDER BY last_message_time DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize avatar images paths
        foreach ($conversations as &$c) {
            if (!empty($c['peer_avatar']) && file_exists(__DIR__ . '/../' . $c['peer_avatar'])) {
                $c['peer_avatar'] = '../' . $c['peer_avatar'];
            } else {
                $c['peer_avatar'] = ($c['peer_role'] === 'admin') 
                    ? 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png' 
                    : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
            }
        }

        echo json_encode(['status' => 'success', 'conversations' => $conversations]);
        exit;
    }

    // 2. GET MESSAGES THREAD
    elseif ($action === 'thread') {
        $conversation_id = intval($_GET['conversation_id'] ?? 0);
        $peer_id = intval($_GET['peer_id'] ?? 0);

        if ($conversation_id <= 0 && $peer_id > 0) {
            // Find existing conversation with peer
            $stmt = $pdo->prepare("SELECT id FROM conversations WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $stmt->execute([$user_id, $peer_id, $peer_id, $user_id]);
            $conversation_id = (int)$stmt->fetchColumn();
        }

        if ($conversation_id <= 0) {
            echo json_encode(['status' => 'success', 'messages' => [], 'conversation_id' => 0]);
            exit;
        }

        // Verify user is part of conversation
        $stmt = $pdo->prepare("SELECT sender_id, receiver_id FROM conversations WHERE id = ?");
        $stmt->execute([$conversation_id]);
        $convo = $stmt->fetch();
        if (!$convo || ($convo['sender_id'] != $user_id && $convo['receiver_id'] != $user_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
            exit;
        }

        // Mark messages as read
        $stmtRead = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?");
        $stmtRead->execute([$conversation_id, $user_id]);

        // Retrieve messages
        $stmtMsg = $pdo->prepare("SELECT id, sender_id, message, is_read, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
        $stmtMsg->execute([$conversation_id]);
        $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'conversation_id' => $conversation_id,
            'messages' => $messages
        ]);
        exit;
    }

    // 3. SEND MESSAGE
    elseif ($action === 'send') {
        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $peer_id = intval($_POST['peer_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            echo json_encode(['status' => 'error', 'message' => 'Empty message.']);
            exit;
        }

        if ($conversation_id <= 0 && $peer_id > 0) {
            // Find or create conversation
            $stmt = $pdo->prepare("SELECT id FROM conversations WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $stmt->execute([$user_id, $peer_id, $peer_id, $user_id]);
            $conversation_id = (int)$stmt->fetchColumn();

            if ($conversation_id <= 0) {
                // Check if peer is valid user
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $stmtCheck->execute([$peer_id]);
                if (!$stmtCheck->fetchColumn()) {
                    echo json_encode(['status' => 'error', 'message' => 'Peer user not found.']);
                    exit;
                }

                $stmtInsertConvo = $pdo->prepare("INSERT INTO conversations (sender_id, receiver_id) VALUES (?, ?)");
                $stmtInsertConvo->execute([$user_id, $peer_id]);
                $conversation_id = $pdo->lastInsertId();
            }
        }

        if ($conversation_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid conversation parameters.']);
            exit;
        }

        // Verify membership
        $stmt = $pdo->prepare("SELECT sender_id, receiver_id FROM conversations WHERE id = ?");
        $stmt->execute([$conversation_id]);
        $convo = $stmt->fetch();
        if (!$convo || ($convo['sender_id'] != $user_id && $convo['receiver_id'] != $user_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
            exit;
        }

        // Insert message
        $stmtInsertMsg = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, is_read) VALUES (?, ?, ?, 0)");
        $stmtInsertMsg->execute([$conversation_id, $user_id, $message]);

        // Send a notification to the peer
        $target_peer = ($convo['sender_id'] == $user_id) ? $convo['receiver_id'] : $convo['sender_id'];
        $stmtNotify = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority) VALUES (?, ?, ?, 'info', 'medium')");
        $stmtNotify->execute([
            $target_peer,
            'New message received',
            substr(get_user_name() . ': ' . $message, 0, 100),
        ]);

        echo json_encode(['status' => 'success', 'conversation_id' => $conversation_id]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$user_id = get_user_id();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'count') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $count = $stmt->fetchColumn();
        echo json_encode(['status' => 'success', 'count' => (int)$count]);
        exit;
    } 
    
    elseif ($action === 'list') {
        $filter_read = $_GET['status'] ?? 'all'; // 'all', 'read', 'unread'
        $filter_type = $_GET['type'] ?? 'all'; // 'all', 'success', 'error', 'warning', 'info'
        $search = trim($_GET['search'] ?? '');
        
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user_id];
        
        if ($filter_read === 'unread') {
            $query .= " AND is_read = 0";
        } elseif ($filter_read === 'read') {
            $query .= " AND is_read = 1";
        }
        
        if ($filter_type !== 'all') {
            $query .= " AND type = ?";
            $params[] = $filter_type;
        }
        
        if ($search !== '') {
            $query .= " AND (title LIKE ? OR message LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        
        $query .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'notifications' => $notifications]);
        exit;
    } 
    
    elseif ($action === 'mark_read') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
        } else {
            // Mark all read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        echo json_encode(['status' => 'success']);
        exit;
    } 
    
    elseif ($action === 'broadcast') {
        if (!is_admin()) {
            echo json_encode(['error' => 'Forbidden: Admins only.']);
            exit;
        }
        
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $audience = $_POST['audience'] ?? 'all'; // 'all', 'student', 'alumni'
        $type = $_POST['type'] ?? 'info'; // 'success', 'error', 'warning', 'info'
        $priority = $_POST['priority'] ?? 'medium'; // 'low', 'medium', 'high'
        
        if (empty($title) || empty($message)) {
            echo json_encode(['error' => 'Title and message are required.']);
            exit;
        }
        
        // Fetch matching users
        if ($audience === 'all') {
            $stmt = $pdo->query("SELECT id FROM users");
            $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ?");
            $stmt->execute([$audience]);
            $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Insert notifications for all targeted users
        $pdo->beginTransaction();
        $stmtInsert = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        foreach ($target_users as $target_user_id) {
            $stmtInsert->execute([$target_user_id, $title, $message, $type, $priority]);
        }
        $pdo->commit();
        
        log_activity(get_user_id(), 'broadcast_notification', "Broadcasted: '$title' to audience '$audience'");
        echo json_encode(['status' => 'success', 'message' => 'Broadcasted to ' . count($target_users) . ' users.']);
        exit;
    }
    
    echo json_encode(['error' => 'Invalid notification action.']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

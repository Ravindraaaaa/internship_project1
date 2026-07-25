<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$user_id = get_user_id();
$role = get_user_role();
$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';

try {
    switch ($action) {
        case 'fetch':
            $category = $_GET['category'] ?? 'all';
            $unread_only = isset($_GET['unread_only']) ? (bool)$_GET['unread_only'] : false;
            $search = $_GET['search'] ?? '';
            $sort = $_GET['sort'] ?? 'newest';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            $data = NotificationEngine::getUserNotifications($user_id, $role, [
                'category' => $category,
                'unread_only' => $unread_only,
                'search' => $search,
                'sort' => $sort,
                'limit' => $limit,
                'offset' => $offset
            ]);

            echo json_encode([
                'status' => 'success',
                'unread_count' => $data['unread'],
                'total' => $data['total'],
                'items' => $data['items']
            ]);
            break;

        case 'mark_read':
            $notif_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($notif_id > 0) {
                NotificationEngine::markAsRead($notif_id, $user_id);
                echo json_encode(['status' => 'success', 'message' => 'Marked as read']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid notification ID']);
            }
            break;

        case 'mark_all_read':
            NotificationEngine::markAllAsRead($user_id, $role);
            echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read']);
            break;

        case 'clear_all':
            $stmtClear = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmtClear->execute([$user_id]);
            ActivityLogger::log($user_id, "Cleared all notifications", "notification");
            echo json_encode(['status' => 'success', 'message' => 'All notifications cleared']);
            break;

        case 'delete':
            $notif_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($notif_id > 0) {
                NotificationEngine::deleteNotification($notif_id, $user_id);
                echo json_encode(['status' => 'success', 'message' => 'Notification deleted']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid notification ID']);
            }
            break;

        case 'get_preferences':
            $stmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prefs) {
                $prefs = [
                    'user_id' => $user_id,
                    'chat_notif' => 1,
                    'announcement_notif' => 1,
                    'job_notif' => 1,
                    'mentorship_notif' => 1,
                    'application_notif' => 1,
                    'security_notif' => 1,
                    'email_notif' => 1
                ];
            }

            echo json_encode(['status' => 'success', 'preferences' => $prefs]);
            break;

        case 'save_preferences':
            $chat = !empty($_POST['chat_notif']) ? 1 : 0;
            $annc = !empty($_POST['announcement_notif']) ? 1 : 0;
            $job  = !empty($_POST['job_notif']) ? 1 : 0;
            $ment = !empty($_POST['mentorship_notif']) ? 1 : 0;
            $app  = !empty($_POST['application_notif']) ? 1 : 0;
            $sec  = !empty($_POST['security_notif']) ? 1 : 0;
            $email = !empty($_POST['email_notif']) ? 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO notification_preferences 
                (user_id, chat_notif, announcement_notif, job_notif, mentorship_notif, application_notif, security_notif, email_notif)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                chat_notif=?, announcement_notif=?, job_notif=?, mentorship_notif=?, application_notif=?, security_notif=?, email_notif=?");

            $stmt->execute([
                $user_id, $chat, $annc, $job, $ment, $app, $sec, $email,
                $chat, $annc, $job, $ment, $app, $sec, $email
            ]);

            ActivityLogger::log($user_id, "Updated notification preferences", "settings");
            echo json_encode(['status' => 'success', 'message' => 'Preferences updated successfully']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();

$action = $_GET['action'] ?? '';
$target_id = intval($_GET['id'] ?? 0);

if (empty($action) || $target_id <= 0) {
    set_flash('error', 'Invalid action parameters.');
    header('Location: dashboard.php');
    exit;
}

try {
    if ($action === 'delete_user') {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM alumni_profiles WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM student_profiles WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM mentorship_requests WHERE student_id = ? OR alumni_id = ?")->execute([$target_id, $target_id]);
        $pdo->prepare("DELETE FROM event_rsvps WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
        $pdo->commit();
        set_flash('success', 'User profile deleted successfully!');
    } elseif ($action === 'delete_job') {
        $pdo->prepare("DELETE FROM jobs WHERE id = ?")->execute([$target_id]);
        set_flash('success', 'Job posting deleted successfully!');
    } elseif ($action === 'delete_event') {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM event_rsvps WHERE event_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$target_id]);
        $pdo->commit();
        set_flash('success', 'Event deleted successfully!');
    } else {
        $stmtCheck = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
        $stmtCheck->execute([$target_id]);
        $user = $stmtCheck->fetch();

        if (!$user) {
            set_flash('error', 'Requested user not found.');
        } elseif ($user['role'] !== 'alumni') {
            set_flash('error', 'This action can only be performed on alumni accounts.');
        } else {
            if ($action === 'approve') {
                $stmtUpdate = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
                $stmtUpdate->execute([$target_id]);
                set_flash('success', 'Alumnus "' . htmlspecialchars($user['name']) . '" approved successfully!');
            } elseif ($action === 'reject') {
                $stmtUpdate = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                $stmtUpdate->execute([$target_id]);
                set_flash('warning', 'Alumnus "' . htmlspecialchars($user['name']) . '" registration rejected.');
            } else {
                set_flash('error', 'Unknown approval action.');
            }
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Action failed: ' . $e->getMessage());
}

$tab = $_GET['tab'] ?? 'overview';
header("Location: dashboard.php?tab=" . urlencode($tab));
exit;
?>

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
    } elseif ($action === 'delete_feedback') {
        $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$target_id]);
        set_flash('success', 'Feedback entry deleted successfully!');
    } elseif ($action === 'toggle_feedback_status') {
        $status = trim($_GET['status'] ?? 'Resolved');
        $pdo->prepare("UPDATE feedback SET status = ? WHERE id = ?")->execute([$status, $target_id]);
        set_flash('success', "Feedback status updated to " . htmlspecialchars($status) . "!");
    } elseif ($action === 'delete_ticket') {
        $pdo->prepare("DELETE FROM support_tickets WHERE id = ?")->execute([$target_id]);
        set_flash('success', 'Support ticket deleted successfully!');
    } elseif ($action === 'toggle_ticket_status') {
        $status = trim($_GET['status'] ?? 'Resolved');
        $pdo->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $target_id]);
        set_flash('success', "Support ticket status updated to " . htmlspecialchars($status) . "!");
    } else {
        $stmtCheck = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
        $stmtCheck->execute([$target_id]);
        $user = $stmtCheck->fetch();

        if (!$user) {
            set_flash('error', 'Requested user not found.');
        } elseif ($user['role'] !== 'alumni') {
            set_flash('error', 'This action can only be performed on alumni accounts.');
        } else {
            require_once __DIR__ . '/../includes/mailer_helper.php';
            require_once __DIR__ . '/../includes/notification_helper.php';

            $stmtCheckFull = $pdo->prepare("SELECT name, email, role FROM users WHERE id = ?");
            $stmtCheckFull->execute([$target_id]);
            $userInfo = $stmtCheckFull->fetch();

            if ($action === 'approve') {
                $stmtUpdate = $pdo->prepare("UPDATE users SET status = 'approved', failed_attempts = 0, lockout_until = NULL WHERE id = ?");
                $stmtUpdate->execute([$target_id]);

                // 1. Send Approval Email to Alumnus
                if (!empty($userInfo['email'])) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $login_url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname($_SERVER['PHP_SELF'] ?? '')), '/\\') . '/login.php';

                    $email_html = build_enterprise_email_template(
                        "Account Approved - Welcome to AlumniNet!",
                        "<p>Hello <strong>" . htmlspecialchars($userInfo['name']) . "</strong>,</p>
                        <p>Great news! Your Alumni registration request has been <strong>APPROVED</strong> by the platform administrators.</p>
                        <p>You can now sign in using your registered credentials to access alumni networking, job referrals, events, and mentorship features.</p>",
                        $login_url,
                        "Sign In Now"
                    );
                    send_logged_email($userInfo['email'], "Account Approved: Welcome to AlumniNet", $email_html, $userInfo['name'], 'account_approved');
                }

                // 2. In-App Notification
                NotificationEngine::send([
                    'user_id' => $target_id,
                    'type' => 'success',
                    'category' => 'system',
                    'title' => "Account Approved! 🎉",
                    'message' => "Your Alumni profile has been verified and approved by administrators.",
                    'icon' => 'circle-check',
                    'color' => 'emerald'
                ]);

                set_flash('success', 'Alumnus "' . htmlspecialchars($user['name']) . '" approved successfully and notification email sent!');
            } elseif ($action === 'reject') {
                $stmtUpdate = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                $stmtUpdate->execute([$target_id]);

                if (!empty($userInfo['email'])) {
                    $email_html = build_enterprise_email_template(
                        "AlumniNet Registration Update",
                        "<p>Hello <strong>" . htmlspecialchars($userInfo['name']) . "</strong>,</p>
                        <p>Your Alumni registration request was reviewed by platform administrators and could not be approved at this time.</p>
                        <p>If you believe this is an error, please contact platform support at support@alumninet.edu.</p>",
                        null,
                        null
                    );
                    send_logged_email($userInfo['email'], "AlumniNet Registration Status Update", $email_html, $userInfo['name'], 'account_rejected');
                }

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

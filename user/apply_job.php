<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$uid = get_user_id();

if ($job_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT title, company, application_link FROM jobs WHERE id = ?");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($job && !empty($job['application_link'])) {
            
            log_activity($uid, 'applied_job', "Job: " . $job['title'] . " at " . $job['company']);

            // Send Email
            $subject = "Job Application Started: " . $job['title'];
            $message = "You have initiated an application for the position of <strong>{$job['title']}</strong> at <strong>{$job['company']}</strong>.<br><br>If you haven't completed your application on their external portal yet, you can return to it here: <a href='{$job['application_link']}'>{$job['application_link']}</a>.<br><br>Good luck!";
            
            send_system_email($uid, $subject, $message);

            // Redirect to external application
            header("Location: " . $job['application_link']);
            exit;
        }
    } catch (Exception $e) {
        error_log("Apply job error: " . $e->getMessage());
    }
}

// Fallback redirect if something fails
header("Location: jobs.php");
exit;
?>

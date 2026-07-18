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
$role = get_user_role();

$response = [
    'status' => 'success',
    'timestamp' => time()
];

try {
    // 1. Unread notifications count & list
    $stmtNotifCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmtNotifCount->execute([$user_id]);
    $response['unread_notif_count'] = (int)$stmtNotifCount->fetchColumn();

    $stmtNotifList = $pdo->prepare("SELECT id, title, message, type, priority, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmtNotifList->execute([$user_id]);
    $response['notif_list'] = $stmtNotifList->fetchAll(PDO::FETCH_ASSOC);

    // 2. Online users tracking (active in the last 5 minutes)
    $stmtOnlineCount = $pdo->query("SELECT COUNT(*) FROM users WHERE last_active >= NOW() - INTERVAL 5 MINUTE");
    $response['online_users_count'] = (int)$stmtOnlineCount->fetchColumn();

    $stmtOnlineList = $pdo->prepare("SELECT id, name, role, last_active FROM users WHERE last_active >= NOW() - INTERVAL 5 MINUTE AND id != ? ORDER BY name ASC LIMIT 5");
    $stmtOnlineList->execute([$user_id]);
    $response['online_users_list'] = $stmtOnlineList->fetchAll(PDO::FETCH_ASSOC);

    // 3. Dashboard Analytics & Statistics based on Role
    if (is_admin()) {
        $response['admin_stats'] = [
            'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'pending' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'alumni' AND status = 'pending'")->fetchColumn(),
            'jobs' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn(),
            'events' => (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= NOW()")->fetchColumn()
        ];

        // Real system activity logs from db
        $stmtLogs = $pdo->query("SELECT a.action, a.details, a.created_at, u.name as user_name FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5");
        $response['activity_timeline'] = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        // Chart Data: Monthly Registrations (Last 6 Months)
        $regData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthLabel = date('M', strtotime("-$i months"));
            $monthPattern = date('Y-m', strtotime("-$i months"));

            $stmtAlum = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'alumni' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmtAlum->execute([$monthPattern]);
            $alumCount = (int)$stmtAlum->fetchColumn();

            $stmtStud = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmtStud->execute([$monthPattern]);
            $studCount = (int)$stmtStud->fetchColumn();

            $regData[] = [
                'month' => $monthLabel,
                'alumni' => $alumCount,
                'students' => $studCount
            ];
        }
        $response['chart_registrations'] = $regData;

        // Chart Data: Jobs Share by Company
        $stmtJobs = $pdo->query("SELECT company, COUNT(*) as qty FROM jobs GROUP BY company ORDER BY qty DESC LIMIT 5");
        $response['chart_jobs_share'] = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

    } else {
        if ($role === 'alumni') {
            $stmtJobsPosted = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE posted_by = ?");
            $stmtJobsPosted->execute([$user_id]);
            $jobsCount = (int)$stmtJobsPosted->fetchColumn();

            $stmtMentRequests = $pdo->prepare("SELECT COUNT(*) FROM mentorship_requests WHERE alumni_id = ?");
            $stmtMentRequests->execute([$user_id]);
            $mentCount = (int)$stmtMentRequests->fetchColumn();

            $response['user_stats'] = [
                'jobs_posted' => $jobsCount,
                'mentorship_requests' => $mentCount
            ];
        } else { // student
            $stmtMentors = $pdo->prepare("SELECT COUNT(*) FROM mentorship_requests WHERE student_id = ?");
            $stmtMentors->execute([$user_id]);
            $mentorsCount = (int)$stmtMentors->fetchColumn();

            $stmtRsvps = $pdo->prepare("SELECT COUNT(*) FROM event_rsvps r JOIN events e ON r.event_id = e.id WHERE r.user_id = ? AND e.event_date >= NOW()");
            $stmtRsvps->execute([$user_id]);
            $rsvpsCount = (int)$stmtRsvps->fetchColumn();

            $response['user_stats'] = [
                'active_mentors' => $mentorsCount,
                'rsvps_reserved' => $rsvpsCount
            ];

            // Chart Data: Student Applications & Activities (Last 6 Months)
            $studChart = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthLabel = date('M', strtotime("-$i months"));
                $monthPattern = date('Y-m', strtotime("-$i months"));

                $stmtApp = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE user_id = ? AND DATE_FORMAT(applied_at, '%Y-%m') = ?");
                $stmtApp->execute([$user_id, $monthPattern]);
                $appCount = (int)$stmtApp->fetchColumn();

                $stmtAct = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
                $stmtAct->execute([$user_id, $monthPattern]);
                $actCount = (int)$stmtAct->fetchColumn();

                $studChart[] = [
                    'month' => $monthLabel,
                    'applications' => $appCount,
                    'activities' => $actCount
                ];
            }
            $response['chart_student_activity'] = $studChart;
        }
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

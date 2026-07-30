<?php
$is_subfolder = true;

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/admin.php';
require_once __DIR__ . '/../includes/auth_helper.php';
check_admin();

$uid = $_SESSION['admin_id'];
$role = 'admin';
$user_name = $_SESSION['admin_name'];

$page_title = "Admin Dashboard";

$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png'; // Admin default icon

$tab = $_GET['tab'] ?? 'overview';

// Handle AJAX Read Receipts Log for Notification Audit Modal
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_notif_audit') {
    header('Content-Type: application/json');
    $title = trim($_GET['title'] ?? '');
    
    try {
        $stmt = $pdo->prepare("SELECT n.id, n.is_read, n.read_at, n.created_at, u.name as user_name, u.email as user_email, u.role as user_role 
                               FROM notifications n 
                               JOIN users u ON n.user_id = u.id 
                               WHERE n.title = ? 
                               ORDER BY n.is_read DESC, u.name ASC");
        $stmt->execute([$title]);
        $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $receipts]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle Announcement Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_announcement') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $audience = $_POST['audience'] ?? 'all';
    
    if ($title && $content) {
        try {
            $pdo->beginTransaction();
            // Insert into announcements
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, audience, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $audience, $uid]);
            
            // Get target users
            $query = "SELECT id FROM users";
            if ($audience === 'students') $query .= " WHERE role = 'student'";
            if ($audience === 'alumni') $query .= " WHERE role = 'alumni'";
            
            $users = $pdo->query($query)->fetchAll(PDO::FETCH_COLUMN);
            
            // Bulk insert notifications
            if (count($users) > 0) {
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, link, sender_id) VALUES (?, ?, ?, 'info', 'medium', 'user/dashboard.php', ?)");
                foreach ($users as $user_id) {
                    $notif_stmt->execute([$user_id, 'New Announcement: ' . $title, substr($content, 0, 100) . '...', $uid]);
                }
            }
            $pdo->commit();
            set_flash('success', 'Announcement published successfully!');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Error creating announcement: ' . $e->getMessage());
        }
    }
    header("Location: dashboard.php?tab=announcements");
    exit;
}

// Handle Broadcast Alumni Placement Spotlight Notification Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_placement_spotlight') {
    $alumni_user_id = intval($_POST['alumni_user_id'] ?? 0);
    $company_name = trim($_POST['company_name'] ?? '');
    $package_offer = trim($_POST['package_offer'] ?? '');
    $custom_msg = trim($_POST['custom_msg'] ?? '');

    if ($alumni_user_id > 0) {
        try {
            $pdo->beginTransaction();

            // Fetch Alumni details
            $stmtA = $pdo->prepare("SELECT u.id, u.name, u.email, ap.company, ap.position, ap.salary, ap.reg_no, ap.passing_year, ap.graduation_year 
                                    FROM users u 
                                    JOIN alumni_profiles ap ON u.id = ap.user_id 
                                    WHERE u.id = ?");
            $stmtA->execute([$alumni_user_id]);
            $alm_info = $stmtA->fetch();

            if ($alm_info) {
                $alumni_name = $alm_info['name'];
                $pass_yr = !empty($alm_info['passing_year']) ? $alm_info['passing_year'] : ($alm_info['graduation_year'] ?? '2024');
                $alumni_id_str = !empty($alm_info['reg_no']) ? $alm_info['reg_no'] : ('ALU-' . $pass_yr . '-' . str_pad($alumni_user_id, 4, '0', STR_PAD_LEFT));
                
                $final_comp = !empty($company_name) ? $company_name : (!empty($alm_info['company']) ? $alm_info['company'] : 'Top Industry Recruiter');
                $final_pkg = !empty($package_offer) ? $package_offer : (!empty($alm_info['salary']) ? $alm_info['salary'] : 'High Package');

                $notif_title = "🎉 Congratulations Alumni! Placement Spotlight";
                $notif_body = !empty($custom_msg) ? $custom_msg : ("Hearty Congratulations to " . $alumni_name . " (ID: " . $alumni_id_str . ") for securing a placement at " . $final_comp . " with " . $final_pkg . " package! Click to view profile.");
                $target_link = "user/view_profile.php?id=" . $alumni_user_id;

                // Broadcast Notification to ALL Students and Regular Users
                $users = $pdo->query("SELECT id FROM users WHERE role = 'student' OR role = 'alumni'")->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($users) > 0) {
                    $stmtIns = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, link, sender_id, created_at) VALUES (?, ?, ?, 'placement_spotlight', 'high', ?, ?, NOW())");
                    foreach ($users as $u_target_id) {
                        $stmtIns->execute([$u_target_id, $notif_title, $notif_body, $target_link, $uid]);
                    }
                }

                // Also publish as priority Announcement
                $stmtAnn = $pdo->prepare("INSERT INTO announcements (title, content, priority, target_audience, status, created_by, created_at) VALUES (?, ?, 'High', 'all', 'Publish', ?, NOW())");
                $stmtAnn->execute(["🎉 Placement Spotlight: " . $alumni_name . " (" . $alumni_id_str . ")", $notif_body, $uid]);

                $pdo->commit();
                set_flash('success', '🎉 Placement Spotlight notification successfully broadcasted to all students & users!');
            } else {
                $pdo->rollBack();
                set_flash('error', 'Alumni record not found.');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Error broadcasting placement spotlight: ' . $e->getMessage());
        }
    }
    header("Location: dashboard.php?tab=announcements");
    exit;
}

// Handle Add Alumni Form Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_alumni') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $year = intval($_POST['graduation_year'] ?? date('Y'));
    $course = trim($_POST['course'] ?? 'General');
    $company = trim($_POST['company'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $raw_pass = !empty($_POST['password']) ? trim($_POST['password']) : 'Alumni#' . mt_rand(1000, 9999);
    
    if (!empty($name) && !empty($email)) {
        try {
            require_once __DIR__ . '/../includes/mailer_helper.php';

            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtUser->execute([$email]);
            $uId = $stmtUser->fetchColumn();
            
            $passHash = password_hash($raw_pass, PASSWORD_BCRYPT);

            if (!$uId) {
                $stmtIns = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'alumni', 'approved', NOW())");
                $stmtIns->execute([$name, $email, $passHash]);
                $uId = $pdo->lastInsertId();
            } else {
                $stmtUpd = $pdo->prepare("UPDATE users SET password = ?, status = 'approved', role = 'alumni' WHERE id = ?");
                $stmtUpd->execute([$passHash, $uId]);
            }
            
            $stmtProf = $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, passing_year, course, company, position) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE graduation_year=?, passing_year=?, course=?, company=?, position=?");
            $stmtProf->execute([$uId, $year, $year, $course, $company, $position, $year, $year, $course, $company, $position]);
            
            // Dispatch Credentials Welcome Email to Alumni
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $login_url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '') . '/../login.php';

            $welcome_email_html = build_enterprise_email_template(
                "Welcome to AlumniNet - Your Credentials",
                "<p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>An administrator has created your official Alumni Account on the AlumniNet Portal.</p>
                <div style='background: rgba(255,255,255,0.05); padding: 18px; border-radius: 10px; margin: 18px 0; border-left: 4px solid #818cf8;'>
                    <p style='margin: 0 0 8px 0;'><strong>Username / Email:</strong> " . htmlspecialchars($email) . "</p>
                    <p style='margin: 0 0 8px 0;'><strong>Password:</strong> <code style='background: rgba(0,0,0,0.3); padding: 3px 8px; border-radius: 4px; color: #38bdf8; font-weight: bold; font-size: 1.05em;'>" . htmlspecialchars($raw_pass) . "</code></p>
                    <p style='margin: 0;'><strong>Role:</strong> Alumni Member</p>
                </div>
                <p>You can now log in to complete your portfolio, mentor students, and share career referral opportunities.</p>",
                $login_url,
                "Log In to AlumniNet"
            );

            $mail_res = send_logged_email($email, "AlumniNet Credentials - Welcome {$name}", $welcome_email_html, $name, 'alumni_credentials');
            
            set_flash('success', "Alumni profile for {$name} created! Credentials sent to {$email} (Login Password: {$raw_pass}).");
        } catch (Exception $e) {
            set_flash('error', 'Error creating alumni account: ' . $e->getMessage());
        }
    } else {
        set_flash('error', 'Please provide both alumni name and valid email address.');
    }
    header("Location: dashboard.php?tab=alumni");
    exit;
}

require_once __DIR__ . '/../includes/mailer_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

// Handle Admin Ticket Reply & Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_ticket') {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $reply_msg = trim($_POST['reply_message'] ?? '');
    $new_status = trim($_POST['status'] ?? 'In Review');

    if ($ticket_id > 0 && !empty($reply_msg)) {
        try {
            $stmt = $pdo->prepare("SELECT st.*, u.email as user_email, u.name as user_name FROM support_tickets st LEFT JOIN users u ON st.user_id = u.id WHERE st.id = ?");
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                // Insert ticket reply
                $reply_stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, sender_id, sender_role, message, created_at) VALUES (?, ?, 'admin', ?, NOW())");
                $reply_stmt->execute([$ticket_id, $uid, $reply_msg]);

                // Update ticket status
                $upd_stmt = $pdo->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                $upd_stmt->execute([$new_status, $ticket_id]);

                // Send email to user
                if (!empty($ticket['user_email'])) {
                    $email_html = build_enterprise_email_template(
                        "Update on Support Ticket #{$ticket['ticket_number']}",
                        "<p>Hello <strong>" . htmlspecialchars($ticket['user_name'] ?? 'User') . "</strong>,</p>
                        <p>An administrator has posted an update regarding your support ticket <strong>#{$ticket['ticket_number']}</strong>.</p>
                        <div style='background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #10b981;'>
                            <p style='margin: 0 0 5px 0;'><strong>Status Changed To:</strong> " . htmlspecialchars($new_status) . "</p>
                            <p style='margin: 0;'><strong>Admin Message:</strong> " . nl2br(htmlspecialchars($reply_msg)) . "</p>
                        </div>
                        <p>Log in to AlumniNet to view the full conversation history or post a response.</p>",
                        null,
                        null
                    );
                    send_logged_email($ticket['user_email'], "Support Update: Ticket #{$ticket['ticket_number']}", $email_html, $ticket['user_name'] ?? '', 'ticket_reply');
                }

                // Send in-app notification to user
                if (!empty($ticket['user_id'])) {
                    NotificationEngine::send([
                        'user_id' => $ticket['user_id'],
                        'type' => 'info',
                        'category' => 'support',
                        'title' => "Ticket Reply: {$ticket['ticket_number']}",
                        'message' => "Admin replied to ticket #{$ticket['ticket_number']}: status is now {$new_status}.",
                        'icon' => 'headset',
                        'color' => 'emerald'
                    ]);
                }

                set_flash('success', "Ticket #{$ticket['ticket_number']} updated and reply email dispatched!");
            }
        } catch (Exception $e) {
            set_flash('error', 'Error updating ticket: ' . $e->getMessage());
        }
    }
    header("Location: dashboard.php?tab=support_tickets");
    exit;
}

// Handle Admin Feedback Reply & Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_feedback') {
    $fdb_id = intval($_POST['feedback_id'] ?? 0);
    $reply_msg = trim($_POST['admin_reply'] ?? '');
    $new_status = trim($_POST['status'] ?? 'Resolved');

    if ($fdb_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM feedback WHERE id = ?");
            $stmt->execute([$fdb_id]);
            $fdb = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fdb) {
                $upd = $pdo->prepare("UPDATE feedback SET admin_reply = ?, status = ? WHERE id = ?");
                $upd->execute([$reply_msg, $new_status, $fdb_id]);

                // Send email to user
                if (!empty($fdb['email'])) {
                    $email_html = build_enterprise_email_template(
                        "Update on Feedback #{$fdb['feedback_id']}",
                        "<p>Hello <strong>" . htmlspecialchars($fdb['name']) . "</strong>,</p>
                        <p>Our platform administration team has reviewed your feedback (Reference: <strong>{$fdb['feedback_id']}</strong>).</p>
                        <div style='background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #f59e0b;'>
                            <p style='margin: 0 0 5px 0;'><strong>Status:</strong> " . htmlspecialchars($new_status) . "</p>
                            <p style='margin: 0;'><strong>Moderator Response:</strong> " . nl2br(htmlspecialchars($reply_msg)) . "</p>
                        </div>",
                        null,
                        null
                    );
                    send_logged_email($fdb['email'], "Feedback Response: {$fdb['feedback_id']}", $email_html, $fdb['name'], 'feedback_reply');
                }

                // In-App Notification
                if (!empty($fdb['user_id'])) {
                    NotificationEngine::send([
                        'user_id' => $fdb['user_id'],
                        'type' => 'success',
                        'category' => 'system',
                        'title' => "Feedback Reviewed: {$fdb['feedback_id']}",
                        'message' => "Moderators reviewed your feedback '{$fdb['subject']}'. Status: {$new_status}.",
                        'icon' => 'comments',
                        'color' => 'amber'
                    ]);
                }

                set_flash('success', "Feedback #{$fdb['feedback_id']} status updated to {$new_status} and notification sent.");
            }
        } catch (Exception $e) {
            set_flash('error', 'Error updating feedback: ' . $e->getMessage());
        }
    }
    header("Location: dashboard.php?tab=feedback");
    exit;
}

$admin_stats = [];
$pending_approvals = [];
$all_alumni = [];
$all_students = [];
$all_jobs = [];
$all_events = [];
$all_messages = [];
$alumni_by_stream = [];
$student_by_stream = [];

try {
    $admin_stats['users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
    $admin_stats['total_alumni'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'alumni'")->fetchColumn();
    $admin_stats['total_students'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $admin_stats['online_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE last_active >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
    $admin_stats['pending'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $admin_stats['jobs'] = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn();
    $admin_stats['events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= NOW()")->fetchColumn();
    
    $stmtPend = $pdo->query("SELECT u.id, u.name, u.email, COALESCE(ap.graduation_year, 'N/A') as graduation_year, COALESCE(ap.course, sp.course, 'General') as course, ap.company, ap.position 
                             FROM users u 
                             LEFT JOIN alumni_profiles ap ON u.id = ap.user_id 
                             LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                             WHERE u.status = 'pending' 
                             ORDER BY u.created_at DESC");
    $pending_approvals = $stmtPend->fetchAll();

    if ($tab === 'alumni') {
        $stmt = $pdo->query("SELECT u.id, u.name, u.email, u.status, u.last_active, ap.graduation_year, ap.course, ap.company, ap.position, ap.reg_no, COALESCE(ap.is_blue_tick, 0) as is_blue_tick 
                             FROM users u 
                             LEFT JOIN alumni_profiles ap ON u.id = ap.user_id 
                             WHERE u.role = 'alumni' 
                             ORDER BY u.created_at DESC");
        $all_alumni = $stmt->fetchAll();
    } elseif ($tab === 'students') {
        $stmt = $pdo->query("SELECT u.id, u.name, u.email, u.status, u.last_active, sp.current_year, sp.course 
                             FROM users u 
                             LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                             WHERE u.role = 'student' 
                             ORDER BY u.created_at DESC");
        $all_students = $stmt->fetchAll();
    } elseif ($tab === 'jobs') {
        $stmt = $pdo->query("SELECT j.*, u.name as poster_name FROM jobs j LEFT JOIN users u ON j.posted_by = u.id ORDER BY j.created_at DESC");
        $all_jobs = $stmt->fetchAll();
    } elseif ($tab === 'events') {
        $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC");
        $all_events = $stmt->fetchAll();
    } elseif ($tab === 'messages') {
        $stmt = $pdo->query("SELECT mr.message, mr.status, mr.created_at, u_std.name as student_name, u_alm.name as alumni_name 
                             FROM mentorship_requests mr 
                             JOIN users u_std ON mr.student_id = u_std.id 
                             JOIN users u_alm ON mr.alumni_id = u_alm.id 
                             ORDER BY mr.created_at DESC");
        $all_messages = $stmt->fetchAll();
    } elseif ($tab === 'reports') {
        $alumni_by_stream = $pdo->query("SELECT course, COUNT(*) as qty FROM alumni_profiles GROUP BY course")->fetchAll();
        $student_by_stream = $pdo->query("SELECT course, COUNT(*) as qty FROM student_profiles GROUP BY course")->fetchAll();
    } elseif ($tab === 'feedback') {
        $stmt = $pdo->query("SELECT f.*, COALESCE(u.name, f.name, 'User') as user_name, COALESCE(u.email, f.email, '') as user_email, COALESCE(u.role, f.role, 'alumni') as user_role 
                             FROM feedback f 
                             LEFT JOIN users u ON f.user_id = u.id 
                             ORDER BY f.created_at DESC");
        $all_feedback = $stmt->fetchAll();
    } elseif ($tab === 'announcements') {
        $stmt = $pdo->query("SELECT a.*, u.name as admin_name 
                             FROM announcements a 
                             LEFT JOIN users u ON a.created_by = u.id 
                             ORDER BY a.created_at DESC");
        $all_announcements = $stmt->fetchAll();

        $stmtAlmList = $pdo->query("SELECT u.id, u.name, ap.company, ap.position, ap.salary, ap.reg_no, ap.passing_year, ap.graduation_year 
                                    FROM users u 
                                    JOIN alumni_profiles ap ON u.id = ap.user_id 
                                    WHERE u.role = 'alumni' 
                                    ORDER BY u.name ASC");
        $all_alumni_list = $stmtAlmList->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($tab === 'notifications') {
        $stmt = $pdo->query("SELECT n.*, u.name as user_name, u.email as user_email, u.role as user_role, s.name as sender_name 
                             FROM notifications n 
                             JOIN users u ON n.user_id = u.id 
                             LEFT JOIN users s ON n.sender_id = s.id 
                             ORDER BY n.created_at DESC LIMIT 100");
        $notif_logs = $stmt->fetchAll();
    }
} catch (Exception $e) {
    set_flash('error', 'Error loading admin data: ' . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    
    <!-- ==================== SIDEBAR ==================== -->
    <?php render_sidebar($tab === 'overview' ? 'dashboard' : $tab); ?>

    <!-- ==================== WORKSPACE CONTENT ==================== -->
    <div class="dashboard-content-area">
        
        <!-- Top Navbar -->
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>

        <!-- Main Workspace -->
        <main class="dashboard-workspace">
            
            <div class="dashboard-title-row">
                <div>
                    <h2><i class="fa-solid fa-user-shield" style="color: var(--theme-accent-purple); margin-right: 0.5rem;"></i> Welcome back, Administrator!</h2>
                    <p style="color: var(--theme-text-secondary); font-size: 0.9rem;">Portal analytics and approvals center.</p>
                </div>
            </div>

            <!-- TAB A: DEFAULT OVERVIEW -->
            <?php if ($tab === 'overview'): ?>
                
                <!-- Metrics row (Native HTML Clickable Real-Time Stats) -->
                <div class="stats-cards-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
                    <a href="dashboard.php?tab=alumni" class="stat-card-view card-glass" style="display: flex; justify-content: space-between; align-items: center; text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to View & Manage Alumni Directory">
                        <div>
                            <span class="stat-card-lbl">Total Alumni</span>
                            <div class="stat-card-val" style="color: #818cf8; font-weight: 800; font-size: 1.8rem; margin-top: 0.25rem;"><?php echo number_format($admin_stats['total_alumni']); ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: #818cf8; font-size: 1.8rem; opacity: 0.85;"><i class="fa-solid fa-user-graduate"></i></div>
                    </a>

                    <a href="dashboard.php?tab=students" class="stat-card-view card-glass" style="display: flex; justify-content: space-between; align-items: center; text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to View & Manage Student Directory">
                        <div>
                            <span class="stat-card-lbl">Total Students</span>
                            <div class="stat-card-val" style="color: #38bdf8; font-weight: 800; font-size: 1.8rem; margin-top: 0.25rem;"><?php echo number_format($admin_stats['total_students']); ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: #38bdf8; font-size: 1.8rem; opacity: 0.85;"><i class="fa-solid fa-graduation-cap"></i></div>
                    </a>

                    <a href="dashboard.php?tab=overview#pending-registrations-review" class="stat-card-view card-glass" style="display: flex; justify-content: space-between; align-items: center; text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to Review Pending Verifications">
                        <div>
                            <span class="stat-card-lbl">Pending Approvals</span>
                            <div class="stat-card-val" style="<?php echo $admin_stats['pending'] > 0 ? 'color: #f59e0b;' : 'color: #10b981;'; ?> font-weight: 800; font-size: 1.8rem; margin-top: 0.25rem;"><?php echo number_format($admin_stats['pending']); ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: #f59e0b; font-size: 1.8rem; opacity: 0.85;"><i class="fa-solid fa-user-clock"></i></div>
                    </a>

                    <a href="dashboard.php?tab=overview#active-activity-timeline" class="stat-card-view card-glass" style="display: flex; justify-content: space-between; align-items: center; text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to View Active Users & Live Logs">
                        <div>
                            <span class="stat-card-lbl">Online Active Now</span>
                            <div class="stat-card-val" style="color: #10b981; font-weight: 800; font-size: 1.8rem; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="fa-solid fa-circle" style="font-size: 0.75rem; color: #10b981; animation: pulse 1.5s infinite;"></i>
                                <?php echo number_format($admin_stats['online_users']); ?>
                            </div>
                        </div>
                        <div class="stat-card-icon" style="color: #10b981; font-size: 1.8rem; opacity: 0.85;"><i class="fa-solid fa-signal"></i></div>
                    </a>

                    <a href="dashboard.php?tab=jobs" class="stat-card-view card-glass" style="display: flex; justify-content: space-between; align-items: center; text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" title="Click to View Job Board & Referrals">
                        <div>
                            <span class="stat-card-lbl">Active Referrals</span>
                            <div class="stat-card-val" style="color: #a855f7; font-weight: 800; font-size: 1.8rem; margin-top: 0.25rem;"><?php echo number_format($admin_stats['jobs']); ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: #a855f7; font-size: 1.8rem; opacity: 0.85;"><i class="fa-solid fa-briefcase"></i></div>
                    </a>
                </div>

                <!-- Admin panels grids -->
                <div class="dashboard-widget-grid">
                    <div class="card-glass" style="display: flex; flex-direction: column; height: 355px;">
                        <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-line" style="color: var(--theme-accent-purple);"></i> Monthly Registration Analytics</h3>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="adminRegistrationsChart"></canvas>
                        </div>
                    </div>
                    <div class="card-glass" style="display: flex; flex-direction: column; height: 355px;">
                        <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-pie" style="color: var(--theme-accent-blue);"></i> Jobs Sector Share</h3>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="adminJobsSectorChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Pending Approvals & Activity logs -->
                <div class="dashboard-widget-grid" style="grid-template-columns: 2fr 1fr;">
                    <div class="card-glass">
                        <h3 style="font-size: 1.15rem; margin-bottom: 1rem;"><i class="fa-solid fa-user-check" style="color: var(--accent-warning);"></i> Pending Registrations Review</h3>
                        <div class="table-responsive">
                            <?php if (!empty($pending_approvals)): ?>
                                <table class="custom-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Course</th>
                                            <th>Company</th>
                                            <th style="text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_approvals as $user): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($user['name']); ?></strong> (Class of <?php echo htmlspecialchars($user['graduation_year']); ?>)</td>
                                                <td><?php echo htmlspecialchars($user['course']); ?></td>
                                                <td><?php echo htmlspecialchars($user['company'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td>
                                                <td style="text-align: right; display:flex; gap: 0.5rem; justify-content: flex-end;">
                                                    <a href="admin_approvals.php?action=approve&id=<?php echo $user['id']; ?>&tab=overview" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;"><i class="fa-solid fa-check"></i> Approve</a>
                                                    <a href="admin_approvals.php?action=reject&id=<?php echo $user['id']; ?>&tab=overview" class="btn btn-danger" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;" onclick="return confirm('Reject registration?')"><i class="fa-solid fa-xmark"></i> Reject</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div style="text-align: center; padding: 2rem; color: var(--theme-text-secondary);">
                                    <i class="fa-solid fa-circle-check" style="font-size: 2.2rem; color: #10b981; margin-bottom: 0.75rem; display:block;"></i>
                                    <span>All verifications requests cleared!</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-glass" style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <div>
                            <h3 style="font-size: 1.15rem; margin-bottom: 1rem;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--theme-accent-purple);"></i> System Activity</h3>
                            <ul class="timeline" id="system-activity-timeline">
                                <div style="text-align: center; color: var(--theme-text-secondary); font-size: 0.85rem; padding: 1rem;">Loading activity...</div>
                            </ul>
                        </div>
                        <div style="border-top: 1px solid var(--theme-border); padding-top: 1rem;">
                            <h3 style="font-size: 1.15rem; margin-bottom: 1rem;"><i class="fa-solid fa-circle" style="color: #10b981; font-size: 0.7rem; vertical-align: middle; margin-right: 0.5rem;"></i> Online Users (<span id="online-users-count">0</span>)</h3>
                            <div id="online-users-container" style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem;">
                                <div style="text-align: center; color: var(--theme-text-secondary); font-size: 0.85rem; padding: 1rem;">Loading online users...</div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- TAB B: MANAGE ALUMNI -->
            <?php elseif ($tab === 'alumni'): ?>
                <div class="card-glass">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.3rem; margin:0;"><i data-lucide="user-check" style="vertical-align: middle; margin-right: 0.5rem; color: var(--theme-accent-purple);"></i> Manage Alumni Members</h3>
                        <div style="display:flex; gap: 0.5rem;">
                            <button class="btn btn-primary" onclick="openModal('addAlumniModal')"><i class="fa-solid fa-user-plus"></i> Add Single Alumni</button>
                            <button class="btn btn-secondary" onclick="openModal('importAlumniModal')"><i class="fa-solid fa-file-import"></i> Import Alumni (CSV/PDF)</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Graduation</th>
                                    <th>Course Stream</th>
                                    <th>Current Work</th>
                                    <th>Live Activity</th>
                                    <th>Account Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_alumni as $alm): 
                                    $isOnline = (!empty($alm['last_active']) && (strtotime($alm['last_active']) >= (time() - 300)));
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($alm['name']); ?></strong>
                                            <?php if (!empty($alm['is_blue_tick'])): ?>
                                                <i class="fa-solid fa-circle-check" style="color: #38bdf8; font-size: 0.95rem; margin-left: 4px;" title="Top Recruiter / High Package Verified Alumni"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($alm['email']); ?></td>
                                        <td><?php echo htmlspecialchars($alm['graduation_year'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($alm['course'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($alm['position'] ?? 'N/A'); ?> at <?php echo htmlspecialchars($alm['company'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($isOnline): ?>
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                    <i class="fa-solid fa-circle" style="color: #10b981; font-size: 0.55rem;"></i> Online
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                    <i class="fa-solid fa-circle" style="color: #94a3b8; font-size: 0.55rem;"></i> Offline
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-<?php echo $alm['status'] === 'approved' ? 'approved' : ($alm['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($alm['status']); ?></span></td>
                                        <td style="text-align: right; display:flex; gap:0.4rem; justify-content:flex-end; align-items:center;">
                                            <a href="../user/view_profile.php?id=<?php echo $alm['id']; ?>" target="_blank" class="btn btn-primary" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px; background: linear-gradient(135deg, #6366f1, #a855f7); color:#ffffff; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem;" title="View Complete Alumni Member Profile">
                                                <i class="fa-solid fa-address-card"></i> View Profile
                                            </a>
                                            <?php if ($alm['status'] !== 'approved'): ?>
                                                <a href="admin_approvals.php?action=approve&id=<?php echo $alm['id']; ?>&tab=alumni" class="btn btn-primary" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px;">Approve</a>
                                            <?php endif; ?>
                                            <?php if ($alm['status'] !== 'rejected'): ?>
                                                <a href="admin_approvals.php?action=reject&id=<?php echo $alm['id']; ?>&tab=alumni" class="btn btn-secondary" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px;">Reject</a>
                                            <?php endif; ?>
                                            <a href="admin_approvals.php?action=delete_user&id=<?php echo $alm['id']; ?>&tab=alumni" class="btn btn-danger" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px;" onclick="return confirm('Delete user profile completely?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB C: MANAGE STUDENTS -->
            <?php elseif ($tab === 'students'): ?>
                <div class="card-glass">
                    <h3 style="font-size: 1.3rem; margin-bottom: 1.25rem;"><i data-lucide="users" style="vertical-align: middle; margin-right: 0.5rem; color: var(--theme-accent-blue);"></i> Manage Students Directory</h3>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Academic Year</th>
                                    <th>Department / Course</th>
                                    <th>Live Activity</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_students as $std): 
                                    $isOnline = (!empty($std['last_active']) && (strtotime($std['last_active']) >= (time() - 300)));
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($std['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($std['email']); ?></td>
                                        <td>Year <?php echo htmlspecialchars($std['current_year'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($std['course'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($isOnline): ?>
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                    <i class="fa-solid fa-circle" style="color: #10b981; font-size: 0.55rem;"></i> Online
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                    <i class="fa-solid fa-circle" style="color: #94a3b8; font-size: 0.55rem;"></i> Offline
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right; display: flex; justify-content: flex-end; gap: 0.4rem;">
                                            <button onclick="viewStudentDetails(<?php echo $std['id']; ?>)" class="btn btn-primary" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px;"><i class="fa-solid fa-eye"></i> View Profile</button>
                                            <a href="admin_approvals.php?action=delete_user&id=<?php echo $std['id']; ?>&tab=students" class="btn btn-danger" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px;" onclick="return confirm('Delete student profile completely?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB D: MANAGE JOBS -->
            <?php elseif ($tab === 'jobs'): ?>
                <div class="card-glass">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <h3 style="font-size: 1.3rem; margin:0;"><i data-lucide="briefcase" style="vertical-align: middle; margin-right: 0.5rem; color: #10b981;"></i> Manage Shared Career Referrals</h3>
                        <button class="btn btn-primary" onclick="openModal('postJobModal')"><i class="fa-solid fa-plus"></i> Share Job Referral</button>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Company</th>
                                    <th>Location</th>
                                    <th>Salary Range</th>
                                    <th>Shared By</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_jobs as $job): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($job['company']); ?></td>
                                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                                        <td><?php echo htmlspecialchars($job['salary_range'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($job['poster_name'] ?? 'System/Admin'); ?> (<small><?php echo htmlspecialchars($job['poster_role']); ?></small>)</td>
                                        <td><span class="badge badge-student"><?php echo htmlspecialchars($job['status']); ?></span></td>
                                        <td style="text-align: right;">
                                            <a href="admin_approvals.php?action=delete_job&id=<?php echo $job['id']; ?>&tab=jobs" class="btn btn-danger" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px;" onclick="return confirm('Delete job referral listing?')">Delete Post</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB E: MANAGE EVENTS -->
            <?php elseif ($tab === 'events'): ?>
                <div class="card-glass">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
                        <h3><i data-lucide="calendar" style="vertical-align: middle; margin-right: 0.5rem; color: #f59e0b;"></i> Scheduled Networking Events</h3>
                        <button class="btn btn-primary" onclick="openModal('createEventModal')"><i class="fa-solid fa-plus"></i> Schedule New Event</button>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Event Title</th>
                                    <th>Date & Time</th>
                                    <th>Location</th>
                                    <th>Event Type</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_events as $ev): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ev['title']); ?></strong></td>
                                        <td><?php echo date('M d, Y - h:i A', strtotime($ev['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($ev['location']); ?></td>
                                        <td><span class="badge badge-alumni" style="text-transform: uppercase;"><?php echo htmlspecialchars($ev['event_type']); ?></span></td>
                                        <td style="text-align: right;">
                                            <a href="admin_approvals.php?action=delete_event&id=<?php echo $ev['id']; ?>&tab=events" class="btn btn-danger" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px;" onclick="return confirm('Delete this event calendar item?')">Delete Event</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB F: MESSAGES -->
            <?php elseif ($tab === 'messages'): ?>
                <div class="card-glass">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                        <h3 style="font-size: 1.3rem; display:flex; align-items:center; gap:0.5rem; margin:0;">
                            <i data-lucide="messages-square" style="color: var(--theme-accent-purple);"></i> 
                            System Message Logs
                        </h3>
                        <div class="sub-tab-buttons" style="display:flex; gap:0.5rem; background:rgba(255,255,255,0.03); border:1px solid var(--theme-border); padding:0.25rem; border-radius:8px;">
                            <button class="btn btn-secondary btn-small" id="btn-show-mentorship" onclick="switchMessageLogTab('mentorship')" style="border:none; padding:0.4rem 0.85rem; background: var(--theme-accent-purple); color: #ffffff;">Mentorship Connections</button>
                            <button class="btn btn-secondary btn-small" id="btn-show-chats" onclick="switchMessageLogTab('chats')" style="border:none; padding:0.4rem 0.85rem; background: transparent; color: var(--theme-text-secondary);">Direct Chats</button>
                        </div>
                    </div>

                    <!-- MENTORSHIP MESSAGES CONTAINER -->
                    <div id="mentorship-logs-container" class="table-responsive">
                        <table class="custom-table" id="mentorship-logs-table">
                            <thead>
                                <tr>
                                    <th>From (Student)</th>
                                    <th>To (Alumni Mentor)</th>
                                    <th>Intro Message</th>
                                    <th>Status</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody id="mentorship-logs-tbody">
                                <?php if (!empty($all_messages)): ?>
                                    <?php foreach ($all_messages as $msg): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($msg['student_name']); ?></strong></td>
                                            <td><strong><?php echo htmlspecialchars($msg['alumni_name']); ?></strong></td>
                                            <td><span style="font-style: italic; font-size:0.85rem;">"<?php echo htmlspecialchars($msg['message']); ?>"</span></td>
                                            <td><span class="badge badge-<?php echo $msg['status'] === 'accepted' ? 'approved' : ($msg['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($msg['status']); ?></span></td>
                                            <td><?php echo date('M d, Y - h:i A', strtotime($msg['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding: 3rem 1rem; color:var(--theme-text-secondary);">
                                            <i class="fa-solid fa-comments" style="font-size: 2rem; color: var(--theme-text-muted); margin-bottom: 0.5rem; display: block;"></i>
                                            <strong>No Mentorship Connection Logs Found</strong><br>
                                            <span style="font-size: 0.85rem; opacity: 0.7;">When students send mentorship requests to alumni members, the connection history will appear here in real-time.</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- DIRECT CHAT MESSAGES CONTAINER -->
                    <div id="chats-logs-container" class="table-responsive" style="display:none;">
                        <table class="custom-table" id="chat-logs-table">
                            <thead>
                                <tr>
                                    <th>Sender</th>
                                    <th>Receiver</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody id="chat-logs-tbody">
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 3rem 1rem; color:var(--theme-text-secondary);">
                                        <i class="fa-solid fa-paper-plane" style="font-size: 2rem; color: var(--theme-text-muted); margin-bottom: 0.5rem; display: block;"></i>
                                        <strong>No Direct Chat Logs Recorded</strong><br>
                                        <span style="font-size: 0.85rem; opacity: 0.7;">When users send direct messages in the portal, real-time activity logs will be displayed here.</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- TAB G: ANALYTICAL REPORTS -->
            <?php elseif ($tab === 'reports'): ?>
                <div class="card-glass" style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.3rem; margin-bottom: 1.5rem;"><i data-lucide="line-chart" style="vertical-align: middle; margin-right: 0.5rem; color: var(--theme-accent-blue);"></i> Analytical Reports</h3>
                    
                    <div class="dashboard-widget-grid" style="grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <h4 style="font-size:0.95rem; margin-bottom:1rem; text-transform:uppercase; color: var(--theme-text-secondary);">Alumni Breakdown by Stream</h4>
                            <div class="table-responsive">
                                <table class="custom-table">
                                    <thead>
                                        <tr>
                                            <th>Course Stream</th>
                                            <th style="text-align: right;">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alumni_by_stream as $r): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($r['course']); ?></td>
                                                <td style="text-align: right;"><strong><?php echo $r['qty']; ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4 style="font-size:0.95rem; margin-bottom:1rem; text-transform:uppercase; color: var(--theme-text-secondary);">Students Breakdown by Stream</h4>
                            <div class="table-responsive">
                                <table class="custom-table">
                                    <thead>
                                        <tr>
                                            <th>Course Stream</th>
                                            <th style="text-align: right;">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_by_stream as $r): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($r['course']); ?></td>
                                                <td style="text-align: right;"><strong><?php echo $r['qty']; ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Panels conforming to FR-16 Report Generation -->
                <div class="card-glass" style="padding: 2rem;">
                    <h3 style="font-size: 1.25rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-file-export" style="color: var(--theme-accent-purple); margin-right:0.5rem;"></i> Generate & Export Platform Reports</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem;">
                        <!-- Users report -->
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); padding: 1.25rem; border-radius: var(--border-radius-sm);">
                            <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem;"><i class="fa-solid fa-users" style="color:var(--theme-accent-purple);"></i> Users Report</h4>
                            <p style="font-size:0.75rem; color:var(--theme-text-secondary); margin-bottom:1rem;">Account records, roles, statuses, and registration timestamps.</p>
                            <div style="display:flex; gap:0.4rem;">
                                <a href="reports_generator.php?type=users&format=csv" class="btn btn-secondary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">CSV</a>
                                <a href="reports_generator.php?type=users&format=excel" class="btn btn-secondary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">Excel</a>
                                <a href="reports_generator.php?type=users&format=print" target="_blank" class="btn btn-primary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">Print</a>
                            </div>
                        </div>
                        <!-- Placements/Jobs report -->
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); padding: 1.25rem; border-radius: var(--border-radius-sm);">
                            <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem;"><i class="fa-solid fa-briefcase" style="color:var(--theme-accent-blue);"></i> Jobs Report</h4>
                            <p style="font-size:0.75rem; color:var(--theme-text-secondary); margin-bottom:1rem;">Job listings, companies, locations, poster names, and dates.</p>
                            <div style="display:flex; gap:0.4rem;">
                                <a href="reports_generator.php?type=placements&format=csv" class="btn btn-secondary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">CSV</a>
                                <a href="reports_generator.php?type=placements&format=excel" class="btn btn-secondary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">Excel</a>
                                <a href="reports_generator.php?type=placements&format=print" target="_blank" class="btn btn-primary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">Print</a>
                            </div>
                        </div>
                        <!-- Events report -->
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); padding: 1.25rem; border-radius: var(--border-radius-sm);">
                            <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem;"><i class="fa-solid fa-calendar-days" style="color:#10b981;"></i> Events Report</h4>
                            <p style="font-size:0.75rem; color:var(--theme-text-secondary); margin-bottom:1rem;">Event details, dates, type configurations, and RSVP stats.</p>
                            <div style="display:flex; gap:0.4rem;">
                                <a href="reports_generator.php?type=events&format=csv" class="btn btn-secondary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">CSV</a>
                                <a href="reports_generator.php?type=events&format=excel" class="btn btn-secondary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">Excel</a>
                                <a href="reports_generator.php?type=events&format=print" target="_blank" class="btn btn-primary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">Print</a>
                            </div>
                        </div>
                        <!-- Applications report -->
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); padding: 1.25rem; border-radius: var(--border-radius-sm);">
                            <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem;"><i class="fa-solid fa-file-lines" style="color:#f59e0b;"></i> Applications Report</h4>
                            <p style="font-size:0.75rem; color:var(--theme-text-secondary); margin-bottom:1rem;">Job applications status log, applied details, and candidates.</p>
                            <div style="display:flex; gap:0.4rem;">
                                <a href="reports_generator.php?type=applications&format=csv" class="btn btn-secondary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">CSV</a>
                                <a href="reports_generator.php?type=applications&format=excel" class="btn btn-secondary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">Excel</a>
                                <a href="reports_generator.php?type=applications&format=print" target="_blank" class="btn btn-primary btn-small" style="padding:0.4rem 0.6rem; font-size:0.75rem;">Print</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'feedback'): ?>
                <div class="card-glass">
                    <h3 style="font-size: 1.3rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-comments" style="vertical-align: middle; margin-right: 0.5rem; color: var(--theme-accent-purple);"></i> Manage User Reviews & Feedback</h3>
                    <div class="table-responsive">
                        <?php if (!empty($all_feedback)): ?>
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Rating</th>
                                        <th>User Details</th>
                                        <th>Subject</th>
                                        <th>Message Review</th>
                                        <th>Submitted On</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_feedback as $fb): ?>
                                        <tr>
                                            <td>
                                                <div style="color: #f59e0b; display: flex; gap: 0.15rem; font-size: 0.9rem;">
                                                    <?php for($i=1; $i<=5; $i++): ?>
                                                        <i class="fa-<?php echo ($i <= $fb['rating']) ? 'solid' : 'regular'; ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($fb['user_name']); ?></strong>
                                                <div style="font-size: 0.72rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($fb['user_email']); ?> | <span style="text-transform:uppercase; font-weight:700;"><?php echo $fb['user_role']; ?></span></div>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($fb['subject']); ?></strong></td>
                                            <td style="max-width: 300px; font-size: 0.85rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($fb['message']); ?></td>
                                            <td style="font-size:0.75rem; color: var(--theme-text-secondary);"><?php echo date('M d, Y', strtotime($fb['created_at'])); ?></td>
                                            <td style="text-align: right;">
                                                <button type="button" onclick="document.getElementById('reply_feedback_id').value = <?php echo $fb['id']; ?>; openModal('replyFeedbackModal')" class="btn btn-primary" style="padding:0.35rem 0.6rem; font-size:0.75rem; border-radius:6px; margin-right: 0.35rem; border: none; cursor: pointer;"><i class="fa-solid fa-reply"></i> Reply</button>
                                                <a href="admin_approvals.php?action=delete_feedback&id=<?php echo $fb['id']; ?>&tab=feedback" class="btn btn-danger" style="padding:0.35rem 0.6rem; font-size:0.75rem; border-radius:6px;" onclick="return confirm('Delete this feedback entry completely?')"><i class="fa-solid fa-trash-can"></i> Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; padding: 3rem; color: var(--theme-text-secondary);">
                                <i class="fa-solid fa-inbox" style="font-size: 2.5rem; margin-bottom: 1rem; display:block;"></i>
                                <span>No feedback or reviews submitted yet.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <!-- TAB: ANNOUNCEMENTS -->
            <?php elseif ($tab === 'announcements'): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="font-size: 1.25rem; font-weight: 700;">Announcements</h2>
                        <p style="color: var(--theme-text-secondary); font-size: 0.9rem;">Broadcast messages to all users or specific groups.</p>
                    </div>
                </div>
                
                <div class="card-glass" style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-bullhorn" style="color: var(--theme-accent-purple);"></i> Create New Announcement</h3>
                    <form method="POST" action="dashboard.php?tab=announcements">
                        <input type="hidden" name="action" value="create_announcement">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="display:block; margin-bottom: 0.5rem;">Announcement Title</label>
                            <input type="text" name="title" class="input-glass" required placeholder="e.g. Platform Maintenance Schedule">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="display:block; margin-bottom: 0.5rem;">Message Content</label>
                            <textarea name="content" class="input-glass" rows="4" required placeholder="Write the full announcement here..."></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="display:block; margin-bottom: 0.5rem;">Target Audience</label>
                            <select name="audience" class="input-glass">
                                <option value="all">All Users (Students & Alumni)</option>
                                <option value="students">Students Only</option>
                                <option value="alumni">Alumni Only</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish Announcement</button>
                    </form>
                </div>

                <!-- RECENT PLACED ALUMNI SPOTLIGHT CARD -->
                <div class="card-glass" style="margin-bottom: 2rem; border-left: 4px solid #a855f7; background: linear-gradient(135deg, rgba(168,85,247,0.05), rgba(99,102,241,0.05));">
                    <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem; display:flex; align-items:center; gap:0.5rem; color:var(--theme-text);">
                        <i class="fa-solid fa-crown" style="color: #eab308;"></i>
                        <span>🎉 Broadcast Recent Alumni Placement Spotlight</span>
                    </h3>
                    <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.25rem; line-height: 1.5;">
                        Broadcast an interactive congratulations notification celebrate recently placed alumni members. All students and users will receive a direct notification with a clickable profile link.
                    </p>

                    <form method="POST" action="dashboard.php?tab=announcements">
                        <input type="hidden" name="action" value="publish_placement_spotlight">
                        
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="display:block; margin-bottom: 0.4rem; font-weight:700;">Select Placed Alumni Member</label>
                            <select name="alumni_user_id" class="input-glass" required style="width:100%; padding:0.65rem;" onchange="autoFillSpotlight(this)">
                                <option value="">-- Choose Alumni Member --</option>
                                <?php if (!empty($all_alumni_list)): ?>
                                    <?php foreach ($all_alumni_list as $alm_opt): 
                                        $pass_yr = !empty($alm_opt['passing_year']) ? $alm_opt['passing_year'] : ($alm_opt['graduation_year'] ?? '2024');
                                        $alm_id_str = !empty($alm_opt['reg_no']) ? $alm_opt['reg_no'] : ('ALU-' . $pass_yr . '-' . str_pad($alm_opt['id'], 4, '0', STR_PAD_LEFT));
                                    ?>
                                        <option value="<?php echo $alm_opt['id']; ?>" data-company="<?php echo htmlspecialchars($alm_opt['company'] ?? ''); ?>" data-salary="<?php echo htmlspecialchars($alm_opt['salary'] ?? ''); ?>" data-name="<?php echo htmlspecialchars($alm_opt['name']); ?>" data-idstr="<?php echo htmlspecialchars($alm_id_str); ?>">
                                            <?php echo htmlspecialchars($alm_opt['name']); ?> (ID: <?php echo htmlspecialchars($alm_id_str); ?>) - <?php echo htmlspecialchars($alm_opt['company'] ?: 'Independent'); ?> (<?php echo htmlspecialchars($alm_opt['salary'] ?: 'Package N/A'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:1rem;">
                            <div>
                                <label class="form-label" style="display:block; margin-bottom: 0.4rem; font-size:0.8rem; font-weight:600;">Placed Company Name</label>
                                <input type="text" id="spotlight_company" name="company_name" class="input-glass" placeholder="e.g. Google / Microsoft / TCS">
                            </div>
                            <div>
                                <label class="form-label" style="display:block; margin-bottom: 0.4rem; font-size:0.8rem; font-weight:600;">Placement Package Offer</label>
                                <input type="text" id="spotlight_package" name="package_offer" class="input-glass" placeholder="e.g. 12 LPA / 15 Lakhs">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label" style="display:block; margin-bottom: 0.4rem; font-size:0.8rem; font-weight:600;">Congratulations Message (Optional Customization)</label>
                            <textarea id="spotlight_msg" name="custom_msg" class="input-glass" rows="2" placeholder="Auto-generated: Congratulations to [Alumni Name] (ID: ALU-2024-XXXX) for getting placed at [Company] with [Package] LPA! Click to view profile."></textarea>
                        </div>

                        <button type="submit" class="btn" style="background: linear-gradient(135deg, #a855f7, #6366f1); color: #ffffff; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 10px 25px rgba(168, 85, 247, 0.3);">
                            <i class="fa-solid fa-bullhorn"></i> Broadcast Placement Spotlight to All Students
                        </button>
                    </form>
                </div>

                <script>
                function autoFillSpotlight(selectEl) {
                    const opt = selectEl.options[selectEl.selectedIndex];
                    if (opt && opt.value) {
                        const comp = opt.getAttribute('data-company') || '';
                        const sal = opt.getAttribute('data-salary') || '';
                        const name = opt.getAttribute('data-name') || '';
                        const idstr = opt.getAttribute('data-idstr') || '';
                        
                        document.getElementById('spotlight_company').value = comp;
                        document.getElementById('spotlight_package').value = sal;
                        document.getElementById('spotlight_msg').value = `🎉 Hearty Congratulations to ${name} (ID: ${idstr}) for securing a placement at ${comp || 'Top Recruiter'} with ${sal || 'a high package'}! Click to view profile.`;
                    }
                }
                </script>
                
                <div class="card-glass">
                    <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--theme-accent-blue);"></i> Previous Announcements</h3>
                    <?php if (!empty($all_announcements)): ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($all_announcements as $ann): ?>
                                <div style="border: 1px solid var(--theme-border); border-radius: 8px; padding: 1rem; background: rgba(255,255,255,0.02);">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                        <h4 style="margin: 0; font-size: 1.05rem;"><?php echo htmlspecialchars($ann['title']); ?></h4>
                                        <span class="status-badge status-approved" style="font-size: 0.75rem;">To: <?php echo ucfirst(htmlspecialchars($ann['audience'])); ?></span>
                                    </div>
                                    <p style="margin: 0 0 0.75rem 0; font-size: 0.9rem; color: var(--theme-text-secondary); white-space: pre-line;"><?php echo htmlspecialchars($ann['content']); ?></p>
                                    <div style="font-size: 0.75rem; color: var(--theme-text-secondary);">
                                        <i class="fa-solid fa-user"></i> By <?php echo htmlspecialchars($ann['admin_name'] ?? 'Admin'); ?> &nbsp;|&nbsp; 
                                        <i class="fa-solid fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($ann['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--theme-text-secondary);">
                            <p>No announcements have been made yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($tab === 'notifications'): ?>

                <!-- TAB: NOTIFICATION AUDIT & READ TRACKER -->
                <div class="card-glass fade-in" style="margin-bottom: 2rem; padding: 1.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h3 style="margin:0; font-size: 1.25rem;"><i class="fa-solid fa-bell" style="color: var(--theme-accent-purple); margin-right: 0.4rem;"></i> Notification Delivery & Read Tracker</h3>
                            <p style="margin: 0.25rem 0 0 0; color: var(--theme-text-secondary); font-size: 0.85rem;">Audit log showing target users, alert messages, read statuses, and timestamps. Click any row to inspect full audit details.</p>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <label for="notif-search-input" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;">Search notifications</label>
                            <input type="text" id="notif-search-input" name="notif_search" class="input-glass" style="padding: 0.45rem 0.85rem; font-size: 0.82rem; width: 220px;" placeholder="Search user or notification..." autocomplete="off" aria-label="Search notifications" onkeyup="filterNotifTable()">
                        </div>
                    </div>

                    <?php if (!empty($notif_logs)): ?>
                        <div class="table-responsive" style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">
                            <table class="recent-activities-table" id="notif-tracker-table" style="width: 100%; min-width: 980px; border-collapse: separate; border-spacing: 0 0.4rem;">
                                <thead>
                                    <tr style="background: rgba(255,255,255,0.03);">
                                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; white-space: nowrap;">Target User</th>
                                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; white-space: nowrap;">Notification Title</th>
                                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; white-space: nowrap;">Sender / Source</th>
                                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; white-space: nowrap;">Message</th>
                                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; white-space: nowrap;">Priority</th>
                                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; white-space: nowrap;">Read Status</th>
                                        <th style="padding: 0.85rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; white-space: nowrap;">Sent Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notif_logs as $nlog): ?>
                                        <tr class="notif-row" style="cursor: pointer; background: rgba(255,255,255,0.015); border: 1px solid var(--theme-border); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='rgba(255,255,255,0.015)'" onclick="showAuditDetailModal('<?php echo htmlspecialchars(addslashes($nlog['title'])); ?>', '<?php echo htmlspecialchars(addslashes($nlog['message'])); ?>', '<?php echo htmlspecialchars(addslashes($nlog['user_name'])); ?>', '<?php echo htmlspecialchars(addslashes($nlog['sender_name'] ?? 'System Admin')); ?>', '<?php echo $nlog['is_read']; ?>', '<?php echo $nlog['read_at']; ?>', '<?php echo date('M d, Y h:i A', strtotime($nlog['created_at'])); ?>')">
                                            <td style="padding: 1rem; white-space: nowrap;">
                                                <strong style="color: var(--theme-text); display:inline-flex; align-items:center; gap:0.35rem;"><i class="fa-solid fa-user-circle" style="color:var(--theme-accent-purple);"></i> <?php echo htmlspecialchars($nlog['user_name']); ?></strong><br>
                                                <span style="font-size:0.75rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($nlog['user_email']); ?> (<?php echo ucfirst($nlog['user_role']); ?>)</span>
                                            </td>
                                            <td style="padding: 1rem; white-space: nowrap;">
                                                <strong style="color: var(--theme-accent-blue); display:inline-flex; align-items:center; gap:0.3rem;">
                                                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i> <?php echo htmlspecialchars($nlog['title']); ?>
                                                </strong>
                                            </td>
                                            <td style="padding: 1rem; white-space: nowrap;">
                                                <span style="font-size:0.8rem; color: var(--theme-text-secondary); display:inline-flex; align-items:center; gap:0.3rem;">
                                                    <i class="fa-solid fa-paper-plane" style="font-size:0.75rem; color:var(--theme-accent-purple);"></i> <?php echo htmlspecialchars($nlog['sender_name'] ?? 'System Broadcast'); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; max-width: 220px; font-size: 0.85rem; color: var(--theme-text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($nlog['message']); ?></td>
                                            <td style="padding: 1rem; white-space: nowrap;">
                                                <span class="badge badge-<?php echo $nlog['priority'] === 'high' ? 'rejected' : 'approved'; ?>" style="padding: 0.3rem 0.65rem;">
                                                    <?php echo ucfirst(htmlspecialchars($nlog['priority'] ?? 'medium')); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; white-space: nowrap;">
                                                <?php if ($nlog['is_read'] == 1): ?>
                                                    <span class="badge badge-approved" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.3rem;" title="Read at: <?php echo $nlog['read_at']; ?>">
                                                        <i class="fa-solid fa-circle-check"></i> READ <?php echo !empty($nlog['read_at']) ? ' (' . date('h:i A', strtotime($nlog['read_at'])) . ')' : ''; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-pending" style="background: rgba(234, 179, 8, 0.15); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3); padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                                        <i class="fa-solid fa-clock"></i> UNREAD
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 1rem; font-size: 0.8rem; color: var(--theme-text-secondary); white-space: nowrap;"><?php echo date('M d, Y h:i A', strtotime($nlog['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <script>
                        function filterNotifTable() {
                            const val = document.getElementById('notif-search-input').value.toLowerCase();
                            const rows = document.querySelectorAll('.notif-row');
                            rows.forEach(r => {
                                r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
                            });
                        }
                        </script>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2.5rem; color: var(--theme-text-secondary);">
                            <i class="fa-solid fa-bell-slash" style="font-size: 2.5rem; margin-bottom: 0.75rem;"></i>
                            <p>No notification dispatch logs recorded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- AUDIT DETAIL MODAL -->
                <div id="auditDetailModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.85); backdrop-filter:blur(8px); z-index:99999; justify-content:center; align-items:center; padding:1.5rem; overflow-y:auto;">
                    <div style="background:#1e293b; border:1px solid #334155; border-radius:14px; max-width:640px; width:95%; max-height:88vh; overflow-y:auto; padding:1.75rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); color:#f8fafc;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                            <h3 style="font-size:1.15rem; font-weight:700; color:#38bdf8; display:flex; align-items:center; gap:0.5rem; margin:0;">
                                <i class="fa-solid fa-users-viewfinder"></i> Notification Audit & Recipient Read Log
                            </h3>
                            <button type="button" onclick="closeAuditModal()" style="background:none; border:none; color:#94a3b8; font-size:1.5rem; cursor:pointer;">&times;</button>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.85rem; font-size:0.9rem;">
                            <div style="background:#0f172a; padding:0.85rem 1rem; border-radius:8px; border:1px solid #1e293b;">
                                <span style="font-size:0.72rem; color:#94a3b8; font-weight:600; text-transform:uppercase; display:block; margin-bottom:0.2rem;">Notification Title</span>
                                <strong id="modal-notif-title" style="font-size:1.05rem; color:#f8fafc;"></strong>
                            </div>

                            <div style="background:#0f172a; padding:0.85rem 1rem; border-radius:8px; border:1px solid #1e293b;">
                                <span style="font-size:0.72rem; color:#94a3b8; font-weight:600; text-transform:uppercase; display:block; margin-bottom:0.2rem;">Message Content</span>
                                <p id="modal-notif-msg" style="margin:0; color:#cbd5e1; line-height:1.4; white-space:pre-line; font-size:0.85rem;"></p>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div style="background:#0f172a; padding:0.75rem 0.85rem; border-radius:8px; border:1px solid #1e293b;">
                                    <span style="font-size:0.72rem; color:#94a3b8; font-weight:600; text-transform:uppercase; display:block;">Sender / Source</span>
                                    <strong id="modal-notif-sender" style="color:#c084fc; font-size:0.85rem;"></strong>
                                </div>
                                <div style="background:#0f172a; padding:0.75rem 0.85rem; border-radius:8px; border:1px solid #1e293b;">
                                    <span style="font-size:0.72rem; color:#94a3b8; font-weight:600; text-transform:uppercase; display:block;">Sent Timestamp</span>
                                    <span id="modal-notif-sent" style="color:#cbd5e1; font-weight:600; font-size:0.85rem;"></span>
                                </div>
                            </div>

                            <!-- LIVE ALL USERS READ STATUS AUDIT LIST -->
                            <div style="background:#0f172a; padding:1rem; border-radius:8px; border:1px solid #1e293b; margin-top:0.2rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; border-bottom:1px solid #1e293b; padding-bottom:0.5rem;">
                                    <span style="font-size:0.8rem; color:#38bdf8; font-weight:700; text-transform:uppercase; display:flex; align-items:center; gap:0.4rem;">
                                        <i class="fa-solid fa-eye"></i> All Recipients Read Statuses
                                    </span>
                                    <span id="modal-read-stats" style="font-size:0.75rem; background:rgba(16, 185, 129, 0.15); color:#4ade80; padding:0.2rem 0.6rem; border-radius:4px; border:1px solid rgba(74, 222, 128, 0.3); font-weight:700;"></span>
                                </div>
                                <div id="modal-recipients-list" style="max-height: 260px; overflow-y: auto; display:flex; flex-direction:column; gap:0.5rem; padding-right: 4px;">
                                    <div style="text-align:center; padding:1rem; color:#94a3b8;"><i class="fa-solid fa-spinner fa-spin"></i> Fetching recipient list...</div>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; margin-top:1.25rem;">
                            <button type="button" onclick="closeAuditModal()" class="btn btn-secondary">Close Audit View</button>
                        </div>
                    </div>
                </div>

                <script>
                function showAuditDetailModal(title, msg, user, sender, isRead, readAt, sentTime) {
                    document.getElementById('modal-notif-title').textContent = title;
                    document.getElementById('modal-notif-msg').textContent = msg;
                    document.getElementById('modal-notif-sender').textContent = sender;
                    document.getElementById('modal-notif-sent').textContent = sentTime;
                    
                    const listContainer = document.getElementById('modal-recipients-list');
                    const statsElem = document.getElementById('modal-read-stats');
                    
                    listContainer.innerHTML = '<div style="text-align:center; padding:1rem; color:#94a3b8;"><i class="fa-solid fa-spinner fa-spin"></i> Fetching recipient list...</div>';
                    statsElem.textContent = 'Loading...';

                    document.getElementById('auditDetailModal').style.display = 'flex';
                    if (typeof window.lockBackgroundScroll === 'function') window.lockBackgroundScroll();
                    else document.body.style.overflow = 'hidden';

                    // Fetch all users who received this notification title
                    fetch('dashboard.php?ajax=get_notif_audit&title=' + encodeURIComponent(title))
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success' && data.data) {
                            const total = data.data.length;
                            const readCount = data.data.filter(r => parseInt(r.is_read) === 1).length;
                            const pct = total > 0 ? Math.round((readCount / total) * 100) : 0;

                            statsElem.textContent = `${readCount} / ${total} Users Read (${pct}%)`;

                            let html = '';
                            data.data.forEach(r => {
                                const hasRead = parseInt(r.is_read) === 1;
                                const statusBadge = hasRead 
                                    ? `<span style="color:#10b981; font-weight:700; font-size:0.78rem;"><i class="fa-solid fa-circle-check"></i> READ ${r.read_at ? '(' + r.read_at + ')' : ''}</span>`
                                    : `<span style="color:#f59e0b; font-weight:600; font-size:0.78rem;"><i class="fa-solid fa-clock"></i> UNREAD</span>`;

                                html += `<div style="display:flex; justify-content:space-between; align-items:center; background:#1e293b; padding:0.6rem 0.85rem; border-radius:6px; border:1px solid #334155;">
                                    <div>
                                        <strong style="color:#f8fafc; font-size:0.85rem;"><i class="fa-solid fa-user-circle" style="color:#c084fc; margin-right:0.3rem;"></i> ${r.user_name}</strong> 
                                        <span style="font-size:0.72rem; color:#94a3b8; text-transform:uppercase;">(${r.user_role})</span>
                                        <div style="font-size:0.75rem; color:#94a3b8;">${r.user_email}</div>
                                    </div>
                                    <div>${statusBadge}</div>
                                </div>`;
                            });
                            listContainer.innerHTML = html;
                        } else {
                            listContainer.innerHTML = '<div style="color:#ef4444; padding:0.5rem;">Failed to fetch recipients.</div>';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        listContainer.innerHTML = '<div style="color:#ef4444; padding:0.5rem;">Error loading recipient data.</div>';
                    });
                }

                function closeAuditModal() {
                    document.getElementById('auditDetailModal').style.display = 'none';
                    if (typeof window.unlockBackgroundScroll === 'function') window.unlockBackgroundScroll();
                    else document.body.style.overflow = '';
                }

                document.addEventListener('DOMContentLoaded', function() {
                    const modalBackdrop = document.getElementById('auditDetailModal');
                    if (modalBackdrop) {
                        modalBackdrop.addEventListener('click', function(e) {
                            if (e.target === this) closeAuditModal();
                        });
                    }
                });
                </script>

            <?php endif; ?>



        </main>
    </div>
</div>

<!-- ==================== CREATE EVENT MODAL (ADMIN ONLY) ==================== -->
<div class="modal" id="createEventModal">
    <div class="modal-content" style="max-width: 550px;">
        <button class="modal-close" onclick="closeModal('createEventModal')">&times;</button>
        <h2 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-calendar-plus" style="color: var(--theme-accent-purple);"></i> Schedule Network Event</h2>
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Configure meeting timelines for verified users.</p>
        
        <form action="../user/events.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_event">
            <input type="hidden" name="redirect" value="../admin/dashboard.php?tab=events">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Event Title</label>
                <input type="text" name="title" class="input-glass" placeholder="Grand Homecoming 2026" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Description</label>
                <textarea name="description" class="input-glass" rows="3" placeholder="Reunion agenda..." required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Date & Time</label>
                    <input type="datetime-local" name="event_date" class="input-glass" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Event Type</label>
                    <select name="event_type" class="input-glass">
                        <option value="in-person">In-Person</option>
                        <option value="online">Online webinar</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Location / URL Link</label>
                <input type="text" name="location" class="input-glass" placeholder="Campus Auditorium" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Upload Banner Picture (Optional)</label>
                <input type="file" name="banner" accept="image/*" class="input-glass">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createEventModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Schedule Event</button>
            </div>
        </form>
    </div>
</div>



<!-- ==================== POST REFERRAL JOB MODAL (ADMIN ONLY) ==================== -->
<div class="modal" id="postJobModal">
    <div class="modal-content" style="max-width: 650px;">
        <button class="modal-close" onclick="closeModal('postJobModal')">&times;</button>
        <h2 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-briefcase" style="color: var(--theme-accent-purple);"></i> Share Job Referral</h2>
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Post internal referral opportunities directly to campus members.</p>
        
        <form action="../user/jobs.php" method="POST">
            <input type="hidden" name="action" value="post_job">
            <input type="hidden" name="redirect" value="../admin/dashboard.php?tab=jobs">
            
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Job Title</label>
                    <input type="text" name="title" class="input-glass" placeholder="e.g. Frontend Engineer" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Company</label>
                    <input type="text" name="company" class="input-glass" placeholder="e.g. Stripe" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Location</label>
                    <input type="text" name="location" class="input-glass" placeholder="e.g. Remote (India)" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Job Category</label>
                    <select name="type" class="input-glass" required>
                        <option value="full-time">Full-Time</option>
                        <option value="part-time">Part-Time</option>
                        <option value="internship">Internship</option>
                        <option value="contract">Contract</option>
                        <option value="remote">Remote</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Salary Range (Optional)</label>
                    <input type="text" name="salary_range" class="input-glass" placeholder="e.g. ₹12L - ₹15L / year">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Application Link / Email</label>
                    <input type="text" name="application_link" class="input-glass" placeholder="https://careers.stripe.com/apply" required>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Job Summary</label>
                    <textarea name="description" class="input-glass" rows="3" placeholder="Briefly detail roles and project scope..." required></textarea>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Skills & Requirements</label>
                    <textarea name="requirements" class="input-glass" rows="2" placeholder="Specify tech stack and years of experience..." required></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('postJobModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Publish Referral</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== REPLY FEEDBACK MODAL ==================== -->
<div class="modal" id="replyFeedbackModal">
    <div class="modal-content" style="max-width: 550px;">
        <button class="modal-close" onclick="closeModal('replyFeedbackModal')">&times;</button>
        <h3 style="margin-top:0; color:var(--theme-text);"><i class="fa-solid fa-reply"></i> Reply to Feedback</h3>
        <p style="color:var(--theme-text-secondary); font-size:0.85rem; margin-bottom:1.5rem;">The user will receive an email and an in-app notification with your response.</p>
        
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="action" value="reply_feedback">
            <input type="hidden" name="feedback_id" id="reply_feedback_id" value="">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Update Status</label>
                <select name="status" class="form-input" style="width: 100%;" required>
                    <option value="Resolved">Resolved</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Admin Reply Message</label>
                <textarea name="admin_reply" class="form-input" rows="5" style="width: 100%; resize: vertical;" placeholder="Type your response here..." required></textarea>
            </div>
            
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('replyFeedbackModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Reply</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== ADD SINGLE ALUMNI MODAL ==================== -->
<div class="modal" id="addAlumniModal">
    <div class="modal-content" style="max-width: 580px;">
        <button class="modal-close" onclick="closeModal('addAlumniModal')">&times;</button>
        <h2 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-user-plus" style="color: var(--theme-accent-purple);"></i> Add Single Alumni</h2>
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Enter alumni profile details to create and register member data.</p>
        
        <form action="dashboard.php" method="POST">
            <input type="hidden" name="action" value="add_alumni">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; display:block;">Full Name</label>
                    <input type="text" name="name" class="input-glass" placeholder="e.g. Rahul Sharma" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; display:block;">Email Address</label>
                    <input type="email" name="email" class="input-glass" placeholder="rahul@example.com" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; display:block;">Graduation Year</label>
                    <input type="number" name="graduation_year" class="input-glass" placeholder="<?php echo date('Y'); ?>" value="<?php echo date('Y'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; display:block;">Course Stream</label>
                    <input type="text" name="course" class="input-glass" placeholder="e.g. B.Tech Computer Engineering" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; display:block;">Current Company</label>
                    <input type="text" name="company" class="input-glass" placeholder="e.g. TCS / Google">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; display:block;">Designation / Position</label>
                    <input type="text" name="position" class="input-glass" placeholder="e.g. Senior Software Engineer">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-size:0.82rem; font-weight:600; margin-bottom:0.4rem; display:block;">Initial Login Password (Optional)</label>
                <input type="text" name="password" class="input-glass" placeholder="Auto-generated if left blank (e.g. Alumni#8492)">
                <small style="color: var(--theme-text-secondary); font-size: 0.76rem; margin-top: 0.3rem; display: block;">
                    <i class="fa-solid fa-envelope-circle-check" style="color: #38bdf8;"></i> Credentials (Username & Password) will be emailed directly to the alumni's address upon submission.
                </small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAlumniModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Submit Alumni Data</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== IMPORT ALUMNI MODAL (CSV/PDF) ==================== -->
<div class="modal" id="importAlumniModal">
    <div class="modal-content" style="max-width: 580px;">
        <button class="modal-close" onclick="closeModal('importAlumniModal')">&times;</button>
        <h2 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-file-import" style="color: var(--theme-accent-purple);"></i> Bulk Import Alumni Data</h2>
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Upload CSV, Excel, or PDF document containing alumni records.</p>
        
        <form action="enterprise_control.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_import">
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Select File (.csv, .xlsx, .pdf)</label>
                <input type="file" name="import_file" accept=".csv,.xlsx,.pdf" class="input-glass" required>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center;">
                <a href="download_alumni_template.php" class="btn btn-secondary btn-small" style="font-size: 0.8rem;"><i class="fa-solid fa-download"></i> Download CSV Template</a>
                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('importAlumniModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Start Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Student Details View Modal -->
<div class="modal" id="studentDetailsModal">
    <div class="modal-content" style="max-width: 750px; padding: 2.5rem; max-height: 85vh; overflow-y: auto;">
        <button class="modal-close" onclick="closeModal('studentDetailsModal')">&times;</button>
        
        <div style="display: flex; gap: 1.5rem; align-items: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
            <img id="m-student-avatar" src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Avatar" style="width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 2.5px solid var(--theme-border);">
            <div>
                <h2 id="m-student-name" style="margin: 0; font-size: 1.45rem;"></h2>
                <p id="m-student-course" style="color: var(--theme-accent-purple); font-weight: 600; font-size: 0.92rem; margin: 0.2rem 0;"></p>
                <div style="font-size: 0.8rem; color: var(--theme-text-secondary); display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 0.25rem;">
                    <span><i class="fa-solid fa-envelope"></i> <span id="m-student-email"></span></span>
                    <span><i class="fa-solid fa-phone"></i> <span id="m-student-phone"></span></span>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Column 1: Academic & Bio -->
            <div>
                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--theme-text); border-left: 3px solid var(--theme-accent-blue); padding-left: 0.5rem;">Academic Info</h3>
                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); padding: 1rem; border-radius: var(--border-radius-sm); font-size: 0.88rem; display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem;">
                    <div><strong>Academic Year:</strong> Year <span id="m-student-year"></span></div>
                    <div><strong>Cumulative CGPA:</strong> <span id="m-student-cgpa"></span> / 10.00</div>
                    <div id="m-student-resume-container"></div>
                </div>

                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--theme-text); border-left: 3px solid var(--theme-accent-blue); padding-left: 0.5rem;">Biography</h3>
                <p id="m-student-bio" style="font-size: 0.88rem; color: var(--theme-text-secondary); line-height: 1.6; margin-bottom: 1.5rem; white-space: pre-line;"></p>
                
                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--theme-text); border-left: 3px solid var(--theme-accent-blue); padding-left: 0.5rem;">Social Connections</h3>
                <div style="display: flex; gap: 0.75rem; font-size: 1.25rem;">
                    <a id="m-student-linkedin" href="#" target="_blank" class="btn btn-secondary btn-small" style="font-size: 0.85rem; display:none;"><i class="fa-brands fa-linkedin"></i> LinkedIn</a>
                    <a id="m-student-github" href="#" target="_blank" class="btn btn-secondary btn-small" style="font-size: 0.85rem; display:none;"><i class="fa-brands fa-github"></i> GitHub</a>
                </div>
            </div>

            <!-- Column 2: Skills, Certs, Achievements -->
            <div>
                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--theme-text); border-left: 3px solid var(--theme-accent-purple); padding-left: 0.5rem;">Skills Stack</h3>
                <div id="m-student-skills" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
                    <!-- Skills progress bars go here -->
                </div>

                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--theme-text); border-left: 3px solid var(--theme-accent-purple); padding-left: 0.5rem;">Uploaded Credentials</h3>
                <div id="m-student-certs" style="display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1.5rem; font-size: 0.85rem;">
                    <!-- Certificates list goes here -->
                </div>

                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--theme-text); border-left: 3px solid var(--theme-accent-purple); padding-left: 0.5rem;">Key Achievements</h3>
                <div id="m-student-achievements" style="display: flex; flex-direction: column; gap: 0.65rem; font-size: 0.85rem;">
                    <!-- Achievements go here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.add('collapsed');
        }
    });

    function viewStudentDetails(studentId) {
        const modal = document.getElementById('studentDetailsModal');
        if (!modal) return;
        
        // Clear out previous details
        document.getElementById('m-student-name').textContent = 'Loading...';
        document.getElementById('m-student-course').textContent = '';
        document.getElementById('m-student-email').textContent = '';
        document.getElementById('m-student-phone').textContent = '';
        document.getElementById('m-student-year').textContent = '';
        document.getElementById('m-student-cgpa').textContent = '';
        document.getElementById('m-student-bio').textContent = '';
        document.getElementById('m-student-skills').innerHTML = '';
        document.getElementById('m-student-certs').innerHTML = '';
        document.getElementById('m-student-achievements').innerHTML = '';
        document.getElementById('m-student-resume-container').innerHTML = '';
        document.getElementById('m-student-linkedin').style.display = 'none';
        document.getElementById('m-student-github').style.display = 'none';
        
        openModal('studentDetailsModal');
        
        fetch('../api/get_student_details.php?id=' + studentId)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const user = data.user;
                    const profile = data.profile;
                    const resume = data.resume;
                    const certs = data.certificates;
                    const skills = data.skills;
                    const achievements = data.achievements;
                    
                    document.getElementById('m-student-name').textContent = user.name;
                    document.getElementById('m-student-email').textContent = user.email;
                    document.getElementById('m-student-phone').textContent = user.phone || 'No phone number';
                    
                    const avatarImg = document.getElementById('m-student-avatar');
                    if (profile.profile_pic) {
                        avatarImg.src = '../' + profile.profile_pic;
                    } else {
                        avatarImg.src = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                    }
                    
                    document.getElementById('m-student-course').textContent = profile.course || 'No stream configured';
                    document.getElementById('m-student-year').textContent = profile.current_year || '1';
                    document.getElementById('m-student-cgpa').textContent = profile.cgpa || '0.00';
                    document.getElementById('m-student-bio').textContent = profile.bio || 'No bio written yet.';
                    
                    if (profile.linkedin) {
                        const ln = document.getElementById('m-student-linkedin');
                        ln.href = profile.linkedin;
                        ln.style.display = 'inline-flex';
                    }
                    if (profile.github) {
                        const gh = document.getElementById('m-student-github');
                        gh.href = profile.github;
                        gh.style.display = 'inline-flex';
                    }
                    
                    const resumeContainer = document.getElementById('m-student-resume-container');
                    if (resume) {
                        resumeContainer.innerHTML = `<strong>Resume File:</strong> <a href="../${resume.file_path}" target="_blank" style="color: var(--theme-accent-blue); text-decoration: underline;"><i class="fa-solid fa-file-pdf"></i> Download Resume</a>`;
                    } else {
                        resumeContainer.innerHTML = '<strong>Resume File:</strong> <span style="color: var(--theme-text-secondary);">No resume uploaded</span>';
                    }
                    
                    const skillsContainer = document.getElementById('m-student-skills');
                    if (skills.length > 0) {
                        skills.forEach(sk => {
                            skillsContainer.innerHTML += `
                                <div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; margin-bottom:0.25rem;">
                                        <span>${sk.name}</span>
                                        <span>${sk.progress}%</span>
                                    </div>
                                    <div style="width:100%; background:rgba(255,255,255,0.05); height:6px; border-radius:10px; overflow:hidden;">
                                        <div style="background:var(--theme-accent-gradient); width:${sk.progress}%; height:100%;"></div>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        skillsContainer.innerHTML = '<div style="color:var(--theme-text-secondary); font-size:0.85rem;">No skills mapped to profile.</div>';
                    }
                    
                    const certsContainer = document.getElementById('m-student-certs');
                    if (certs.length > 0) {
                        certs.forEach(c => {
                            certsContainer.innerHTML += `
                                <div style="background:rgba(255,255,255,0.01); border:1px solid var(--theme-border); padding:0.75rem; border-radius:6px; display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                                    <div>
                                        <strong style="display:block; font-size:0.85rem;">${c.name}</strong>
                                        <span style="font-size:0.75rem; color:var(--theme-text-secondary);">${c.issuer} | Issued: ${c.issue_date}</span>
                                    </div>
                                    <a href="../${c.file_path}" target="_blank" class="btn btn-secondary btn-small" style="font-size:0.75rem; padding:0.2rem 0.5rem;"><i class="fa-solid fa-file-arrow-download"></i> View</a>
                                </div>
                            `;
                        });
                    } else {
                        certsContainer.innerHTML = '<div style="color:var(--theme-text-secondary); font-size:0.85rem;">No credentials/certificates uploaded.</div>';
                    }
                    
                    const achContainer = document.getElementById('m-student-achievements');
                    if (achievements.length > 0) {
                        achievements.forEach(a => {
                            achContainer.innerHTML += `
                                <div style="background:rgba(255,255,255,0.01); border:1px solid var(--theme-border); padding:0.75rem; border-radius:6px; margin-bottom: 0.5rem;">
                                    <strong style="display:block; font-size:0.85rem;">${a.title}</strong>
                                    <p style="font-size:0.78rem; color:var(--theme-text-secondary); margin:0.2rem 0 0.4rem 0;">${a.description}</p>
                                    <span style="font-size:0.72rem; color:var(--theme-accent-purple); font-weight:600;"><i class="fa-solid fa-trophy"></i> Date: ${a.date_achieved}</span>
                                </div>
                            `;
                        });
                    } else {
                        achContainer.innerHTML = '<div style="color:var(--theme-text-secondary); font-size:0.85rem;">No key achievements recorded.</div>';
                    }
                } else {
                    document.getElementById('m-student-name').textContent = 'Error loading details.';
                    alert(data.error || 'Failed to load details.');
                }
            })
            .catch(err => {
                document.getElementById('m-student-name').textContent = 'Request failed.';
                console.error(err);
            });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

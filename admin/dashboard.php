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
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
                foreach ($users as $user_id) {
                    $notif_stmt->execute([$user_id, 'New Announcement: ' . $title, substr($content, 0, 100) . '...']);
                }
            }
            $pdo->commit();
            set_flash('success', 'Announcement published successfully!');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Error creating announcement: ' . $e->getMessage());
        }
    } else {
        set_flash('error', 'Title and content are required.');
    }
    header("Location: dashboard.php?tab=announcements");
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
    $admin_stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $admin_stats['pending'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'alumni' AND status = 'pending'")->fetchColumn();
    $admin_stats['jobs'] = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn();
    $admin_stats['events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= NOW()")->fetchColumn();
    
    $stmtPend = $pdo->query("SELECT u.id, u.name, u.email, ap.graduation_year, ap.course, ap.company, ap.position 
                             FROM users u 
                             JOIN alumni_profiles ap ON u.id = ap.user_id 
                             WHERE u.role = 'alumni' AND u.status = 'pending' 
                             ORDER BY u.created_at DESC");
    $pending_approvals = $stmtPend->fetchAll();

    if ($tab === 'alumni') {
        $stmt = $pdo->query("SELECT u.id, u.name, u.email, u.status, ap.graduation_year, ap.course, ap.company, ap.position 
                             FROM users u 
                             LEFT JOIN alumni_profiles ap ON u.id = ap.user_id 
                             WHERE u.role = 'alumni' 
                             ORDER BY u.created_at DESC");
        $all_alumni = $stmt->fetchAll();
    } elseif ($tab === 'students') {
        $stmt = $pdo->query("SELECT u.id, u.name, u.email, sp.current_year, sp.course 
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
        $stmt = $pdo->query("SELECT f.*, u.name as user_name, u.email as user_email, u.role as user_role 
                             FROM feedback f 
                             JOIN users u ON f.user_id = u.id 
                             ORDER BY f.created_at DESC");
        $all_feedback = $stmt->fetchAll();
    } elseif ($tab === 'announcements') {
        $stmt = $pdo->query("SELECT a.*, u.name as admin_name 
                             FROM announcements a 
                             LEFT JOIN users u ON a.created_by = u.id 
                             ORDER BY a.created_at DESC");
        $all_announcements = $stmt->fetchAll();
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
                    <h2>Welcome back, Administrator!</h2>
                    <p style="color: var(--theme-text-secondary); font-size: 0.9rem;">Portal analytics and approvals center.</p>
                </div>
            </div>

            <!-- TAB A: DEFAULT OVERVIEW -->
            <?php if ($tab === 'overview'): ?>
                
                <!-- Metrics row -->
                <div class="stats-cards-grid">
                    <div class="stat-card-view card-glass" style="cursor: pointer;" onclick="location.href='dashboard.php?tab=students'">
                        <div>
                            <span class="stat-card-lbl">Total Users</span>
                            <div class="stat-card-val"><?php echo $admin_stats['users']; ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: var(--theme-accent-purple);"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="stat-card-view card-glass" style="cursor: pointer;" onclick="location.href='dashboard.php?tab=alumni'">
                        <div>
                            <span class="stat-card-lbl">Pending Approvals</span>
                            <div class="stat-card-val" style="<?php echo $admin_stats['pending'] > 0 ? 'color: var(--accent-warning);' : ''; ?>"><?php echo $admin_stats['pending']; ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: var(--accent-warning);"><i class="fa-solid fa-user-clock"></i></div>
                    </div>
                    <div class="stat-card-view card-glass" style="cursor: pointer;" onclick="location.href='dashboard.php?tab=jobs'">
                        <div>
                            <span class="stat-card-lbl">Active Referrals</span>
                            <div class="stat-card-val"><?php echo $admin_stats['jobs']; ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: var(--theme-accent-blue);"><i class="fa-solid fa-briefcase"></i></div>
                    </div>
                    <div class="stat-card-view card-glass" style="cursor: pointer;" onclick="location.href='dashboard.php?tab=events'">
                        <div>
                            <span class="stat-card-lbl">Scheduled Events</span>
                            <div class="stat-card-val"><?php echo $admin_stats['events']; ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: #10b981;"><i class="fa-solid fa-calendar-check"></i></div>
                    </div>
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
                    <h3 style="font-size: 1.3rem; margin-bottom: 1.25rem;"><i data-lucide="user-check" style="vertical-align: middle; margin-right: 0.5rem; color: var(--theme-accent-purple);"></i> Manage Alumni Members</h3>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Graduation</th>
                                    <th>Course Stream</th>
                                    <th>Current Work</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_alumni as $alm): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($alm['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($alm['email']); ?></td>
                                        <td><?php echo htmlspecialchars($alm['graduation_year'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($alm['course'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($alm['position'] ?? 'N/A'); ?> at <?php echo htmlspecialchars($alm['company'] ?? 'N/A'); ?></td>
                                        <td><span class="badge badge-<?php echo $alm['status'] === 'approved' ? 'approved' : ($alm['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($alm['status']); ?></span></td>
                                        <td style="text-align: right; display:flex; gap:0.4rem; justify-content:flex-end;">
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
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_students as $std): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($std['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($std['email']); ?></td>
                                        <td>Year <?php echo htmlspecialchars($std['current_year'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($std['course'] ?? 'N/A'); ?></td>
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
                                <?php foreach ($all_messages as $msg): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($msg['student_name']); ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($msg['alumni_name']); ?></strong></td>
                                        <td><span style="font-style: italic; font-size:0.85rem;">"<?php echo htmlspecialchars($msg['message']); ?>"</span></td>
                                        <td><span class="badge badge-<?php echo $msg['status'] === 'accepted' ? 'approved' : ($msg['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($msg['status']); ?></span></td>
                                        <td><?php echo date('M d, Y - h:i A', strtotime($msg['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
                                <tr><td colspan="5" style="text-align:center;color:var(--theme-text-secondary);">Loading chat messages...</td></tr>
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

<?php
$is_subfolder = true;

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/admin.php';
check_admin();

$uid = $_SESSION['admin_id'];
$role = 'admin';
$user_name = $_SESSION['admin_name'];

$page_title = "Admin Dashboard";

$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png'; // Admin default icon

$tab = $_GET['tab'] ?? 'overview';

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
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <div class="top-nav-search">
                    <i data-lucide="search" style="width: 18px; height: 18px; color: var(--theme-text-secondary);"></i>
                    <input type="text" class="input-glass" style="padding-left: 2.5rem;" placeholder="Search entries...">
                </div>
            </div>

            <div class="top-nav-actions">
                <button class="btn btn-primary btn-small" onclick="openModal('postJobModal')" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; font-size: 0.8rem; border-radius: 6px; font-weight: 600;">
                    <i class="fa-solid fa-briefcase"></i> Share Job
                </button>
                <button class="btn btn-primary btn-small" onclick="openModal('createEventModal')" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.85rem; font-size: 0.8rem; border-radius: 6px; font-weight: 600;">
                    <i class="fa-solid fa-calendar-plus"></i> Schedule Event
                </button>

                <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open visual settings">
                    <i data-lucide="palette" style="width: 20px; height: 20px;"></i>
                </button>
                
                <!-- Notification Bell -->
                <div class="top-nav-icon-wrapper" id="notif-bell-toggle">
                    <i data-lucide="bell" style="width: 20px; height: 20px;"></i>
                    <span class="top-nav-badge">1</span>
                    <div class="nav-dropdown-menu" id="notif-dropdown-menu">
                        <div class="dropdown-header-info">
                            <h4>Recent Alerts</h4>
                            <p>You have 1 new notice</p>
                        </div>
                        <div class="notif-item">
                            <div class="notif-item-title"><i class="fa-solid fa-info-circle" style="color: var(--theme-accent-blue);"></i> Platform database synchronized.</div>
                            <div class="notif-item-time">System notification</div>
                        </div>
                    </div>
                </div>

                <!-- User profile dropdown -->
                <div style="position: relative;">
                    <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="User Avatar" class="nav-user-avatar" id="profile-avatar-toggle">
                    <div class="nav-dropdown-menu" id="profile-dropdown-menu">
                        <div class="dropdown-header-info">
                            <h4><?php echo htmlspecialchars($user_name); ?></h4>
                            <p>admin@alumni.com</p>
                        </div>
                        <div style="border-top: 1px solid var(--theme-border); margin: 0.25rem 0;"></div>
                        <a href="../logout.php" class="dropdown-item" style="color: var(--accent-danger);"><i data-lucide="log-out" style="width:16px;height:16px;"></i> Sign Out</a>
                    </div>
                </div>
            </div>
        </nav>

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
                                        <td style="text-align: right;">
                                            <a href="admin_approvals.php?action=delete_user&id=<?php echo $std['id']; ?>&tab=students" class="btn btn-danger" style="padding:0.3rem 0.6rem; font-size:0.72rem; border-radius:6px;" onclick="return confirm('Delete student profile completely?')">Delete Student</a>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.add('collapsed');
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

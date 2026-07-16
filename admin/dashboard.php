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
    }
} catch (Exception $e) {
    set_flash('error', 'Error loading admin data: ' . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    
    <!-- ==================== SIDEBAR ==================== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="logo logo-text">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <button class="sidebar-toggle-btn" id="sidebar-toggle">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; text-align: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;" class="sidebar-profile-box">
            <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--theme-accent-purple);" class="user-sidebar-avatar">
            <div style="margin-top: 0.75rem;" class="link-text">
                <h4 style="font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;"><?php echo htmlspecialchars($user_name); ?></h4>
                <p style="font-size: 0.72rem; color: var(--theme-text-secondary); text-transform: uppercase;">Admin Portal</p>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item <?php echo $tab === 'overview' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=overview"><i data-lucide="gauge"></i> <span class="link-text">Dashboard</span></a>
            </li>
            <li class="sidebar-item <?php echo $tab === 'alumni' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=alumni"><i data-lucide="user-check"></i> <span class="link-text">Manage Alumni</span></a>
            </li>
            <li class="sidebar-item <?php echo $tab === 'students' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=students"><i data-lucide="users"></i> <span class="link-text">Manage Students</span></a>
            </li>
            <li class="sidebar-item <?php echo $tab === 'jobs' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=jobs"><i data-lucide="briefcase"></i> <span class="link-text">Manage Jobs</span></a>
            </li>
            <li class="sidebar-item <?php echo $tab === 'events' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=events"><i data-lucide="calendar"></i> <span class="link-text">Manage Events</span></a>
            </li>
            <li class="sidebar-item <?php echo $tab === 'messages' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=messages"><i data-lucide="messages-square"></i> <span class="link-text">System Messages</span></a>
            </li>
            <li class="sidebar-item <?php echo $tab === 'reports' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=reports"><i data-lucide="line-chart"></i> <span class="link-text">Reports</span></a>
            </li>
            <li class="sidebar-item" style="margin-top: auto; border-top: 1px solid var(--theme-border); padding-top: 1rem;">
                <a href="../logout.php" style="color: var(--accent-danger);"><i data-lucide="log-out"></i> <span class="link-text">Sign Out</span></a>
            </li>
        </ul>
    </aside>

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
                    <div class="stat-card-view card-glass">
                        <div>
                            <span class="stat-card-lbl">Total Users</span>
                            <div class="stat-card-val"><?php echo $admin_stats['users']; ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: var(--theme-accent-purple);"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="stat-card-view card-glass">
                        <div>
                            <span class="stat-card-lbl">Pending Approvals</span>
                            <div class="stat-card-val" style="<?php echo $admin_stats['pending'] > 0 ? 'color: var(--accent-warning);' : ''; ?>"><?php echo $admin_stats['pending']; ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: var(--accent-warning);"><i class="fa-solid fa-user-clock"></i></div>
                    </div>
                    <div class="stat-card-view card-glass">
                        <div>
                            <span class="stat-card-lbl">Active Referrals</span>
                            <div class="stat-card-val"><?php echo $admin_stats['jobs']; ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: var(--theme-accent-blue);"><i class="fa-solid fa-briefcase"></i></div>
                    </div>
                    <div class="stat-card-view card-glass">
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

                    <div class="card-glass">
                        <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--theme-accent-purple);"></i> System Activity</h3>
                        <ul class="timeline">
                            <li class="timeline-item">
                                <span class="timeline-marker success"></span>
                                <div class="timeline-time">Just now</div>
                                <div class="timeline-title">Admin logged in</div>
                                <div class="timeline-desc">Session authorized</div>
                            </li>
                            <li class="timeline-item">
                                <span class="timeline-marker blue"></span>
                                <div class="timeline-time">1 hour ago</div>
                                <div class="timeline-title">Database seeded done</div>
                                <div class="timeline-desc">Seeding done successfully</div>
                            </li>
                        </ul>
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
                    <h3 style="font-size: 1.3rem; margin-bottom: 1.25rem;"><i data-lucide="briefcase" style="vertical-align: middle; margin-right: 0.5rem; color: #10b981;"></i> Manage Shared Career Referrals</h3>
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
                    <h3 style="font-size: 1.3rem; margin-bottom: 1.25rem;"><i data-lucide="messages-square" style="vertical-align: middle; margin-right: 0.5rem; color: var(--theme-accent-purple);"></i> System Connection Requests Log</h3>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>From (Student)</th>
                                    <th>To (Alumni Mentor)</th>
                                    <th>Intro Message</th>
                                    <th>Status</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/dashboard.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.add('collapsed');
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

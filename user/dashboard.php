<?php
$is_subfolder = true;

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/user.php';
check_user();

$uid = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'student';
$user_name = $_SESSION['user_name'];

$page_title = "User Dashboard";

$user_status = 'approved';
$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; // default avatar

try {
    $stmtStatus = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmtStatus->execute([$uid]);
    $user_status = $stmtStatus->fetchColumn();
    
    if ($role === 'alumni') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/../' . $prof['profile_pic'])) {
            $sidebar_avatar = '../' . $prof['profile_pic'];
        }
    } else if ($role === 'student') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/../' . $prof['profile_pic'])) {
            $sidebar_avatar = '../' . $prof['profile_pic'];
        }
    }
} catch (Exception $e) {
    // Fail silently or set default
}

$alumni_mentorships = [];
$alumni_jobs = [];
$student_mentorships = [];
$student_rsvps = [];

try {
    if ($role === 'alumni') {
        $stmtMent = $pdo->prepare("SELECT mr.id, mr.message, mr.status, u.name as student_name, sp.course, sp.profile_pic 
                                   FROM mentorship_requests mr 
                                   JOIN users u ON mr.student_id = u.id 
                                   LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                                   WHERE mr.alumni_id = ? 
                                   ORDER BY mr.created_at DESC");
        $stmtMent->execute([$uid]);
        $alumni_mentorships = $stmtMent->fetchAll();
        
        $stmtJobs = $pdo->prepare("SELECT * FROM jobs WHERE posted_by = ? AND poster_role = 'user' ORDER BY created_at DESC");
        $stmtJobs->execute([$uid]);
        $alumni_jobs = $stmtJobs->fetchAll();
    } elseif ($role === 'student') {
        $stmtMent = $pdo->prepare("SELECT mr.id, mr.message, mr.status, u.name as alumni_name, ap.company, ap.position, ap.profile_pic, ap.linkedin 
                                   FROM mentorship_requests mr 
                                   JOIN users u ON mr.alumni_id = u.id 
                                   LEFT JOIN alumni_profiles ap ON u.id = ap.user_id 
                                   WHERE mr.student_id = ? 
                                   ORDER BY mr.created_at DESC");
        $stmtMent->execute([$uid]);
        $student_mentorships = $stmtMent->fetchAll();
        
        $stmtRSVP = $pdo->prepare("SELECT e.id as event_id, e.title, e.event_date, e.location, r.status as rsvp_status 
                                   FROM event_rsvps r 
                                   JOIN events e ON r.event_id = e.id 
                                   WHERE r.user_id = ? AND e.event_date >= NOW() 
                                   ORDER BY e.event_date ASC");
        $stmtRSVP->execute([$uid]);
        $student_rsvps = $stmtRSVP->fetchAll();
    }
} catch (Exception $e) {
    set_flash('error', 'Error loading user data: ' . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    
    <!-- ==================== SIDEBAR ==================== -->
    <?php render_sidebar('dashboard'); ?>

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
                            <div class="notif-item-title"><i class="fa-solid fa-info-circle" style="color: var(--theme-accent-blue);"></i> Welcome to AlumniNet! Check your profile completion.</div>
                            <div class="notif-item-time">System notification</div>
                        </div>
                    </div>
                </div>

                <!-- User profile dropdown -->
                <div style="position: relative;">
                    <img src="<?php echo htmlspecialchars(str_replace('../', '', $sidebar_avatar)); ?>" alt="User Avatar" class="nav-user-avatar" id="profile-avatar-toggle">
                    <div class="nav-dropdown-menu" id="profile-dropdown-menu">
                        <div class="dropdown-header-info">
                            <h4><?php echo htmlspecialchars($user_name); ?></h4>
                            <p><?php echo htmlspecialchars($role); ?> portal</p>
                        </div>
                        <a href="profile.php" class="dropdown-item"><i data-lucide="user" style="width:16px;height:16px;"></i> My Profile</a>
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
                    <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p style="color: var(--theme-text-secondary); font-size: 0.9rem;">Here is your live platforms metrics overview for today.</p>
                </div>
            </div>

            <!-- Alumni pending verification warnings banner -->
            <?php if ($role === 'alumni' && $user_status === 'pending'): ?>
                <div class="card-glass" style="background: rgba(245, 158, 17, 0.08); border-color: rgba(245, 158, 17, 0.25); display: flex; gap: 1.25rem; align-items: center; color: #fde047; margin-bottom: 2rem;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 2.2rem;"></i>
                    <div>
                        <h4 style="color: #ffffff; font-size: 1.05rem; margin-bottom: 0.2rem;">Account Verification Pending</h4>
                        <p style="font-size: 0.88rem; opacity: 0.95;">Your alumni registration is under review by administrator staff. Post referrals and mentorship matching details will unlock once verified.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- USER DASHBOARD DEFAULT PANELS -->
            <div class="stats-cards-grid">
                <div class="stat-card-view card-glass">
                    <div>
                        <span class="stat-card-lbl">Welcome Back</span>
                        <div class="stat-card-val" style="font-size: 1.45rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;"><?php echo htmlspecialchars($user_name); ?></div>
                    </div>
                    <div class="stat-card-icon" style="color: var(--theme-accent-purple);"><i class="fa-solid fa-graduation-cap"></i></div>
                </div>
                
                <?php if ($role === 'alumni'): ?>
                    <div class="stat-card-view card-glass">
                        <div>
                            <span class="stat-card-lbl">Referrals Posted</span>
                            <div class="stat-card-val"><?php echo count($alumni_jobs); ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: var(--theme-accent-blue);"><i class="fa-solid fa-briefcase"></i></div>
                    </div>
                    <div class="stat-card-view card-glass">
                        <div>
                            <span class="stat-card-lbl">Mentoring Requests</span>
                            <div class="stat-card-val"><?php echo count($alumni_mentorships); ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: #10b981;"><i class="fa-solid fa-handshake-angle"></i></div>
                    </div>
                <?php else: ?>
                    <div class="stat-card-view card-glass">
                        <div>
                            <span class="stat-card-lbl">Active Mentors</span>
                            <div class="stat-card-val"><?php echo count($student_mentorships); ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: var(--theme-accent-blue);"><i class="fa-solid fa-handshake-angle"></i></div>
                    </div>
                    <div class="stat-card-view card-glass">
                        <div>
                            <span class="stat-card-lbl">RSVPs Reserved</span>
                            <div class="stat-card-val"><?php echo count($student_rsvps); ?></div>
                        </div>
                        <div class="stat-card-icon" style="color: #10b981;"><i class="fa-solid fa-calendar-check"></i></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DUAL WIDGET SPLIT GRIDS -->
            <?php if ($role === 'student'): ?>
                <div class="dashboard-widget-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                    
                    <!-- Line Activity Graph -->
                    <div class="card-glass" style="display: flex; flex-direction: column; height: 380px;">
                        <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-line" style="color: var(--theme-accent-purple);"></i> Job Applications & Profile Analytics</h3>
                        <div style="flex-grow: 1; position: relative;">
                            <canvas id="userActivityChart"></canvas>
                        </div>
                    </div>

                    <!-- Active Connections -->
                    <div class="card-glass" style="display:flex; flex-direction:column; height: 380px;">
                        <h3 style="font-size: 1.15rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-handshake-angle" style="color: var(--theme-accent-blue);"></i> Active Mentorships</h3>
                        <div style="display:flex; flex-direction:column; gap: 1rem; overflow-y:auto; flex-grow:1;">
                            <?php if (!empty($student_mentorships)): ?>
                                <?php foreach ($student_mentorships as $conn): 
                                    $mentor_avatar = $conn['profile_pic'] ? htmlspecialchars($conn['profile_pic']) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                                ?>
                                    <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--theme-border); padding-bottom: 0.75rem;">
                                        <div style="display:flex; align-items:center; gap: 0.75rem;">
                                            <img src="<?php echo $mentor_avatar; ?>" alt="Mentor" style="width: 36px; height: 36px; border-radius:50%; object-fit:cover;">
                                            <div>
                                                <h4 style="font-size: 0.88rem;"><?php echo htmlspecialchars($conn['alumni_name']); ?></h4>
                                                <p style="font-size: 0.7rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($conn['position']); ?> at <?php echo htmlspecialchars($conn['company']); ?></p>
                                            </div>
                                        </div>
                                        <span class="badge badge-<?php echo $conn['status'] === 'accepted' ? 'approved' : ($conn['status'] === 'pending' ? 'pending' : 'rejected'); ?>" style="font-size:0.7rem;"><?php echo htmlspecialchars($conn['status']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="font-size: 0.82rem; color: var(--theme-text-secondary);">No mentorship requests submitted. Browse the directory!</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Online Members -->
                    <div class="card-glass" style="display:flex; flex-direction:column; height: 380px;">
                        <h3 style="font-size: 1.15rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-circle" style="color: #10b981; font-size: 0.7rem; vertical-align: middle; margin-right: 0.5rem;"></i> Online Network (<span id="online-users-count">0</span>)</h3>
                        <div id="online-users-container" style="display:flex; flex-direction:column; gap: 1rem; overflow-y:auto; flex-grow:1;">
                            <div style="text-align: center; color: var(--theme-text-secondary); font-size: 0.85rem; padding: 1rem;">Loading online members...</div>
                        </div>
                    </div>

                </div>

                <!-- RSVPs Timelines -->
                <div class="card-glass">
                    <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-calendar-check" style="color: #10b981;"></i> Your Upcoming RSVPs</h3>
                    <?php if (!empty($student_rsvps)): ?>
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Event Title</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th style="text-align: right;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student_rsvps as $rsvp): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($rsvp['title']); ?></strong></td>
                                            <td><?php echo date('M d, Y - h:i A', strtotime($rsvp['event_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($rsvp['location']); ?></td>
                                            <td style="text-align: right;"><span class="badge badge-student" style="text-transform: uppercase;"><?php echo str_replace('_', ' ', $rsvp['rsvp_status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="font-size: 0.85rem; color: var(--theme-text-secondary);">You have not RSVP'd to any upcoming campus events yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($role === 'alumni'): ?>
                <div class="dashboard-widget-grid" style="grid-template-columns: 2fr 1fr;">
                    
                    <!-- Received Mentorship requests -->
                    <div class="card-glass">
                        <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-handshake-angle" style="color: var(--theme-accent-purple);"></i> Received Mentorship Requests</h3>
                        <?php if (!empty($alumni_mentorships)): ?>
                            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                                <?php foreach ($alumni_mentorships as $req): 
                                    $student_pic = $req['profile_pic'] ? htmlspecialchars($req['profile_pic']) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                                ?>
                                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); padding: 1.25rem; border-radius: var(--border-radius-md);">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <img src="<?php echo $student_pic; ?>" alt="Student" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                <div>
                                                    <h4 style="font-size: 0.92rem;"><?php echo htmlspecialchars($req['student_name']); ?></h4>
                                                    <p style="font-size: 0.75rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($req['course']); ?></p>
                                                </div>
                                            </div>
                                            <span class="badge badge-<?php echo $req['status'] === 'accepted' ? 'approved' : ($req['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                        </div>
                                        <p style="font-size: 0.85rem; color: var(--theme-text-secondary); font-style: italic; background: rgba(0,0,0,0.1); padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 0.75rem;">
                                            "<?php echo htmlspecialchars($req['message']); ?>"
                                        </p>
                                        
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <div style="display:flex; gap: 0.5rem; justify-content: flex-end;">
                                                <a href="mentorship.php?action=accept&id=<?php echo $req['id']; ?>" class="btn btn-primary btn-small" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; <?php echo $user_status !== 'approved' ? 'pointer-events: none; opacity: 0.5;' : ''; ?>"><i class="fa-solid fa-check"></i> Accept</a>
                                                <a href="mentorship.php?action=decline&id=<?php echo $req['id']; ?>" class="btn btn-danger btn-small" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;" onclick="return confirm('Decline request?')"><i class="fa-solid fa-xmark"></i> Decline</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 3rem 1.5rem; color: var(--theme-text-secondary);">
                                <i class="fa-solid fa-inbox" style="font-size: 2.2rem; margin-bottom: 0.75rem; display:block;"></i>
                                <span>No connection requests received yet.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Referrals shared & Online Users -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <div class="card-glass">
                            <h3 style="font-size: 1.15rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-briefcase" style="color: var(--theme-accent-blue);"></i> Referrals Shared</h3>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php if (!empty($alumni_jobs)): ?>
                                    <?php foreach ($alumni_jobs as $job): ?>
                                        <div style="border-bottom: 1px solid var(--theme-border); padding-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <h4 style="font-size: 0.9rem; font-weight: 600;"><?php echo htmlspecialchars($job['title']); ?></h4>
                                                <p style="font-size: 0.75rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($job['company']); ?> | <?php echo htmlspecialchars($job['location']); ?></p>
                                            </div>
                                            <span class="badge badge-student"><?php echo htmlspecialchars($job['status']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="font-size: 0.85rem; color: var(--theme-text-secondary);">You have not shared any referrals yet.</p>
                                <?php endif; ?>
                                <a href="jobs.php" class="btn btn-secondary btn-small" style="margin-top: 1rem; width: 100%; text-align:center;">Post Job Referral</a>
                            </div>
                        </div>

                        <div class="card-glass">
                            <h3 style="font-size: 1.15rem; margin-bottom: 1rem;"><i class="fa-solid fa-circle" style="color: #10b981; font-size: 0.7rem; vertical-align: middle; margin-right: 0.5rem;"></i> Online Network (<span id="online-users-count">0</span>)</h3>
                            <div id="online-users-container" style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem;">
                                <div style="text-align: center; color: var(--theme-text-secondary); font-size: 0.85rem; padding: 1rem;">Loading online network...</div>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endif; ?>

        </main>
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

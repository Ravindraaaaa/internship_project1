<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$uid = get_user_id();
$role = get_user_role();
$user_name = get_user_name();

// 1. Process Actions (GET / POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'request') {
        require_role(['student']);
        
        $alumni_id = intval($_POST['alumni_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($alumni_id <= 0 || empty($message)) {
            set_flash('error', 'Invalid mentorship request details.');
        } else {
            try {
                $stmtCheckAlumni = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'alumni'");
                $stmtCheckAlumni->execute([$alumni_id]);
                if (!$stmtCheckAlumni->fetch()) {
                    set_flash('error', 'Target user is not an alumnus.');
                } else {
                    $stmtCheckDup = $pdo->prepare("SELECT id FROM mentorship_requests WHERE student_id = ? AND alumni_id = ?");
                    $stmtCheckDup->execute([$uid, $alumni_id]);
                    if ($stmtCheckDup->fetch()) {
                        set_flash('info', 'Mentorship request already exists.');
                    } else {
                        $stmtInsert = $pdo->prepare("INSERT INTO mentorship_requests (student_id, alumni_id, message, status) VALUES (?, ?, ?, 'pending')");
                        $stmtInsert->execute([$uid, $alumni_id, $message]);
                        set_flash('success', 'Mentorship request sent successfully!');
                    }
                }
            } catch (Exception $e) {
                set_flash('error', 'Error sending request: ' . $e->getMessage());
            }
        }
        header('Location: alumni.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    require_role(['alumni']);
    
    // Check if approved
    $stmtApproveCheck = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmtApproveCheck->execute([$uid]);
    $is_approved = $stmtApproveCheck->fetchColumn() === 'approved';

    if (!$is_approved) {
        set_flash('error', 'Your profile must be approved by admin to accept mentorships.');
        header('Location: dashboard.php');
        exit;
    }

    $action = $_GET['action'];
    $req_id = intval($_GET['id'] ?? 0);

    if ($req_id > 0 && in_array($action, ['accept', 'decline'])) {
        try {
            $stmtVerify = $pdo->prepare("SELECT id FROM mentorship_requests WHERE id = ? AND alumni_id = ?");
            $stmtVerify->execute([$req_id, $uid]);
            if ($stmtVerify->fetch()) {
                $status = ($action === 'accept') ? 'accepted' : 'declined';
                $stmtUpdate = $pdo->prepare("UPDATE mentorship_requests SET status = ? WHERE id = ?");
                $stmtUpdate->execute([$status, $req_id]);
                
                if ($status === 'accepted') {
                    set_flash('success', 'Mentorship request accepted!');
                } else {
                    set_flash('info', 'Mentorship request declined.');
                }
            } else {
                set_flash('error', 'Request not found.');
            }
        } catch (Exception $e) {
            set_flash('error', 'Action failed: ' . $e->getMessage());
        }
    }
    header('Location: dashboard.php');
    exit;
}

// 2. Load View Portal Content
$page_title = "Mentorship Hub";

$received_requests = [];
$sent_requests = [];

try {
    if ($role === 'alumni') {
        $stmt = $pdo->prepare("SELECT mr.id, mr.message, mr.status, mr.created_at, u.name as student_name, u.email as student_email, sp.course, sp.profile_pic, sp.linkedin, sp.github
                               FROM mentorship_requests mr 
                               JOIN users u ON mr.student_id = u.id 
                               LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                               WHERE mr.alumni_id = ? 
                               ORDER BY mr.created_at DESC");
        $stmt->execute([$uid]);
        $received_requests = $stmt->fetchAll();
    } elseif ($role === 'student') {
        $stmt = $pdo->prepare("SELECT mr.id, mr.message, mr.status, mr.created_at, u.name as alumni_name, u.email as alumni_email, ap.company, ap.position, ap.course, ap.profile_pic, ap.linkedin, ap.website
                               FROM mentorship_requests mr 
                               JOIN users u ON mr.alumni_id = u.id 
                               LEFT JOIN alumni_profiles ap ON u.id = ap.user_id 
                               WHERE mr.student_id = ? 
                               ORDER BY mr.created_at DESC");
        $stmt->execute([$uid]);
        $sent_requests = $stmt->fetchAll();
    }
} catch (Exception $e) {
    set_flash('error', 'Failed loading connections: ' . $e->getMessage());
}

$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
if (!is_admin()) {
    if ($role === 'alumni') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/' . $prof['profile_pic'])) {
            $sidebar_avatar = $prof['profile_pic'];
        }
    } else if ($role === 'student') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/' . $prof['profile_pic'])) {
            $sidebar_avatar = $prof['profile_pic'];
        }
    }
}

$is_subfolder = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo logo-text">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <button class="sidebar-toggle-btn" id="sidebar-toggle">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; text-align: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;" class="sidebar-profile-box">
            <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--theme-accent-purple);" class="user-sidebar-avatar">
            <div style="margin-top: 0.75rem;" class="link-text">
                <h4 style="font-size: 0.9rem;"><?php echo htmlspecialchars($user_name); ?></h4>
                <p style="font-size: 0.72rem; color: var(--theme-text-secondary); text-transform: uppercase;"><?php echo htmlspecialchars($role); ?> member</p>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> <span class="link-text">Dashboard</span></a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php"><i class="fa-solid fa-circle-user"></i> <span class="link-text">My Profile</span></a>
            </li>
            <li class="sidebar-item active">
                <a href="mentorship.php"><i class="fa-solid fa-handshake-angle"></i> <span class="link-text">Mentorship</span></a>
            </li>
            <li class="sidebar-item">
                <a href="alumni.php"><i class="fa-solid fa-users"></i> <span class="link-text">Alumni Directory</span></a>
            </li>
            <li class="sidebar-item">
                <a href="jobs.php"><i class="fa-solid fa-briefcase"></i> <span class="link-text">Job Board</span></a>
            </li>
            <li class="sidebar-item">
                <a href="events.php"><i class="fa-solid fa-calendar-days"></i> <span class="link-text">Events Board</span></a>
            </li>
            <li class="sidebar-item" style="margin-top: auto; border-top: 1px solid var(--theme-border); padding-top: 1rem;">
                <a href="logout.php" style="color: var(--accent-danger);"><i class="fa-solid fa-right-from-bracket"></i> <span class="link-text">Sign Out</span></a>
            </li>
        </ul>
    </aside>

    <div class="dashboard-content-area">
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h2>Mentorship connections</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open visual settings">
                    <i class="fa-solid fa-palette"></i>
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-small"><i class="fa-solid fa-gauge"></i> Dashboard</a>
            </div>
        </nav>

        <main class="dashboard-workspace">
            
            <!-- ALUMNI MENTORSHIP RECEIVED VIEW -->
            <?php if ($role === 'alumni'): ?>
                <div class="card-glass">
                    <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-handshake-angle" style="color: var(--theme-accent-purple);"></i> Received Connections</h3>
                    
                    <?php if (!empty($received_requests)): ?>
                        <div style="display:flex; flex-direction:column; gap: 1.5rem;">
                            <?php foreach ($received_requests as $req): 
                                $student_pic = $req['profile_pic'] ? htmlspecialchars($req['profile_pic']) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                            ?>
                                <div style="border-bottom: 1px solid var(--theme-border); padding-bottom: 1.25rem;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                        <div style="display:flex; align-items:center; gap:0.75rem;">
                                            <img src="<?php echo $student_pic; ?>" alt="Avatar" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                                            <div>
                                                <h4 style="font-size:0.95rem;"><?php echo htmlspecialchars($req['student_name']); ?></h4>
                                                <p style="font-size:0.75rem; color:var(--theme-text-secondary);"><?php echo htmlspecialchars($req['course']); ?></p>
                                            </div>
                                        </div>
                                        <span class="badge badge-<?php echo $req['status'] === 'accepted' ? 'approved' : ($req['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                    </div>
                                    <p style="font-size:0.85rem; color:var(--theme-text-secondary); font-style:italic; background:rgba(0,0,0,0.1); padding:0.5rem 0.75rem; border-radius:6px; margin-bottom:0.75rem;">
                                        "<?php echo htmlspecialchars($req['message']); ?>"
                                    </p>
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <span style="font-size:0.75rem; color:var(--theme-text-secondary);"><i class="fa-solid fa-clock"></i> Received: <?php echo date('M d, Y', strtotime($req['created_at'])); ?></span>
                                        <div style="display:flex; gap:0.5rem;">
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <a href="mentorship.php?action=accept&id=<?php echo $req['id']; ?>" class="btn btn-primary btn-small"><i class="fa-solid fa-check"></i> Accept</a>
                                                <a href="mentorship.php?action=decline&id=<?php echo $req['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Decline request?')"><i class="fa-solid fa-xmark"></i> Decline</a>
                                            <?php elseif ($req['status'] === 'accepted'): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($req['student_email']); ?>" class="btn btn-secondary btn-small"><i class="fa-solid fa-envelope"></i> Email Student</a>
                                                <?php if ($req['linkedin']): ?>
                                                    <a href="<?php echo htmlspecialchars($req['linkedin']); ?>" target="_blank" class="btn btn-secondary btn-small"><i class="fa-brands fa-linkedin"></i> LinkedIn</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--theme-text-secondary); text-align:center; padding: 2rem 0;">No student connections requests received.</p>
                    <?php endif; ?>
                </div>

            <!-- STUDENT MENTORSHIP SENT VIEW -->
            <?php elseif ($role === 'student'): ?>
                <div class="card-glass">
                    <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-handshake" style="color: var(--theme-accent-blue);"></i> Outgoing Connections</h3>
                    
                    <?php if (!empty($sent_requests)): ?>
                        <div style="display:flex; flex-direction:column; gap:1.5rem;">
                            <?php foreach ($sent_requests as $req): 
                                $alumni_pic = $req['profile_pic'] ? htmlspecialchars($req['profile_pic']) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                            ?>
                                <div style="border-bottom: 1px solid var(--theme-border); padding-bottom: 1.25rem;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                        <div style="display:flex; align-items:center; gap:0.75rem;">
                                            <img src="<?php echo $alumni_pic; ?>" alt="Avatar" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                                            <div>
                                                <h4 style="font-size:0.95rem;"><?php echo htmlspecialchars($req['alumni_name']); ?></h4>
                                                <p style="font-size:0.75rem; color:var(--theme-text-secondary);"><?php echo htmlspecialchars($req['position'] ?? 'Graduate'); ?> at <?php echo htmlspecialchars($req['company'] ?? 'AlumniNet'); ?></p>
                                            </div>
                                        </div>
                                        <span class="badge badge-<?php echo $req['status'] === 'accepted' ? 'approved' : ($req['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                    </div>
                                    <p style="font-size:0.85rem; color:var(--theme-text-secondary); font-style:italic; background:rgba(0,0,0,0.1); padding:0.5rem 0.75rem; border-radius:6px; margin-bottom:0.75rem;">
                                        "<?php echo htmlspecialchars($req['message']); ?>"
                                    </p>
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <span style="font-size:0.75rem; color:var(--theme-text-secondary);"><i class="fa-solid fa-clock"></i> Sent: <?php echo date('M d, Y', strtotime($req['created_at'])); ?></span>
                                        <div style="display:flex; gap:0.5rem;">
                                            <?php if ($req['status'] === 'accepted'): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($req['alumni_email']); ?>" class="btn btn-primary btn-small"><i class="fa-solid fa-envelope"></i> Email Mentor</a>
                                                <?php if ($req['linkedin']): ?>
                                                    <a href="<?php echo htmlspecialchars($req['linkedin']); ?>" target="_blank" class="btn btn-secondary btn-small"><i class="fa-brands fa-linkedin"></i> LinkedIn</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--theme-text-secondary); text-align:center; padding: 2rem 0;">No active mentorship requests. Search directory to request a mentor!</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Scripts loading sidebars collapse -->
<script src="../assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

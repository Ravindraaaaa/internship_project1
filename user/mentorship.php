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
        $alumni_id = intval($_POST['alumni_id'] ?? 0); // target user_id
        $message = trim($_POST['message'] ?? '');

        if ($alumni_id <= 0 || empty($message)) {
            set_flash('error', 'Invalid connection request details.');
        } else {
            try {
                $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $stmtCheckUser->execute([$alumni_id]);
                if (!$stmtCheckUser->fetch()) {
                    set_flash('error', 'Target user not found.');
                } else {
                    $stmtCheckDup = $pdo->prepare("SELECT id FROM mentorship_requests WHERE (student_id = ? AND alumni_id = ?) OR (student_id = ? AND alumni_id = ?)");
                    $stmtCheckDup->execute([$uid, $alumni_id, $alumni_id, $uid]);
                    if ($stmtCheckDup->fetch()) {
                        set_flash('info', 'Connection request already exists.');
                    } else {
                        $stmtInsert = $pdo->prepare("INSERT INTO mentorship_requests (student_id, alumni_id, message, status) VALUES (?, ?, ?, 'pending')");
                        $stmtInsert->execute([$uid, $alumni_id, $message]);
                        set_flash('success', 'Connection request sent successfully!');
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
    require_role(['alumni', 'student']);
    
    // Check if approved
    $stmtApproveCheck = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmtApproveCheck->execute([$uid]);
    $is_approved = $stmtApproveCheck->fetchColumn() === 'approved';

    if (!$is_approved) {
        set_flash('error', 'Your profile must be approved by admin to accept connection requests.');
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
                    set_flash('success', 'Connection request accepted!');
                } else {
                    set_flash('info', 'Connection request declined.');
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
    // Load all incoming requests (where logged in user is the receiver/alumni_id)
    $stmtReceived = $pdo->prepare("
        SELECT mr.id, mr.message, mr.status, mr.created_at, u.id as sender_id, u.name as sender_name, u.email as sender_email, u.role as sender_role,
               COALESCE(ap.course, sp.course) as course,
               COALESCE(ap.profile_pic, sp.profile_pic) as profile_pic
        FROM mentorship_requests mr 
        JOIN users u ON mr.student_id = u.id 
        LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND u.role = 'alumni'
        LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
        WHERE mr.alumni_id = ? 
        ORDER BY mr.created_at DESC
    ");
    $stmtReceived->execute([$uid]);
    $received_requests = $stmtReceived->fetchAll();

    // Load all outgoing requests (where logged in user is the sender/student_id)
    $stmtSent = $pdo->prepare("
        SELECT mr.id, mr.message, mr.status, mr.created_at, u.id as receiver_id, u.name as receiver_name, u.email as receiver_email, u.role as receiver_role,
               COALESCE(ap.course, sp.course) as course,
               COALESCE(ap.profile_pic, sp.profile_pic) as profile_pic
        FROM mentorship_requests mr 
        JOIN users u ON mr.alumni_id = u.id 
        LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND u.role = 'alumni'
        LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
        WHERE mr.student_id = ? 
        ORDER BY mr.created_at DESC
    ");
    $stmtSent->execute([$uid]);
    $sent_requests = $stmtSent->fetchAll();
} catch (Exception $e) {
    set_flash('error', 'Failed loading connections: ' . $e->getMessage());
}

$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
if (!is_admin()) {
    if ($role === 'alumni') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        $sidebar_avatar = get_avatar_url($prof['profile_pic'] ?? '');
    } else if ($role === 'student') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        $sidebar_avatar = get_avatar_url($prof['profile_pic'] ?? '');
    }
}

$is_subfolder = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    
    <!-- Sidebar -->
    <?php render_sidebar('mentorship'); ?>

    <div class="dashboard-content-area">
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h2>Mentorship connections</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark/Bright Mode">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-small"><i class="fa-solid fa-gauge"></i> Dashboard</a>
            </div>
        </nav>

        <main class="dashboard-workspace">
            
            <!-- Incoming Requests -->
            <div class="card-glass" style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-handshake-angle" style="color: var(--theme-accent-purple);"></i> Incoming Connection Requests</h3>
                
                <?php if (!empty($received_requests)): ?>
                    <div style="display:flex; flex-direction:column; gap: 1.5rem;">
                         <?php foreach ($received_requests as $req): 
                             $sender_pic = get_avatar_url($req['profile_pic'] ?? '');
                             $sender_id_str = get_student_id_string($req['sender_id'], $req['course'] ?? '');
                         ?>
                            <div style="border-bottom: 1px solid var(--theme-border); padding-bottom: 1.25rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <img src="<?php echo $sender_pic; ?>" alt="Avatar" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                                        <div>
                                            <h4 style="font-size:0.95rem;"><?php echo htmlspecialchars($req['sender_name']); ?> <small style="opacity: 0.6; font-size: 0.75rem;">(<?php echo $sender_id_str; ?>)</small></h4>
                                            <p style="font-size:0.75rem; color:var(--theme-text-secondary);"><?php echo htmlspecialchars($req['course']); ?> | <strong style="text-transform: uppercase;"><?php echo htmlspecialchars($req['sender_role']); ?></strong></p>
                                        </div>
                                    </div>
                                    <span class="badge badge-<?php echo $req['status'] === 'accepted' ? 'approved' : ($req['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                </div>
                                <p style="font-size:0.85rem; color:var(--theme-text-secondary); font-style:italic; background:rgba(0,0,0,0.02); border: 1px solid var(--theme-border); padding:0.5rem 0.75rem; border-radius:6px; margin-bottom:0.75rem;">
                                    "<?php echo htmlspecialchars($req['message']); ?>"
                                </p>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:0.75rem; color:var(--theme-text-secondary);"><i class="fa-solid fa-clock"></i> Received: <?php echo date('M d, Y', strtotime($req['created_at'])); ?></span>
                                    <div style="display:flex; gap:0.5rem;">
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <a href="mentorship.php?action=accept&id=<?php echo $req['id']; ?>" class="btn btn-primary btn-small"><i class="fa-solid fa-check"></i> Accept</a>
                                            <a href="mentorship.php?action=decline&id=<?php echo $req['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Decline request?')"><i class="fa-solid fa-xmark"></i> Decline</a>
                                        <?php elseif ($req['status'] === 'accepted'): ?>
                                            <a href="chat.php?peer_id=<?php echo $req['sender_id']; ?>" class="btn btn-primary btn-small"><i class="fa-solid fa-comment-dots"></i> Chat</a>
                                            <a href="view_profile.php?id=<?php echo $req['sender_id']; ?>" class="btn btn-secondary btn-small"><i class="fa-solid fa-user"></i> Profile</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                         <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--theme-text-secondary); text-align:center; padding: 2rem 0;">No incoming connection requests received.</p>
                <?php endif; ?>
            </div>

            <!-- Outgoing Requests -->
            <div class="card-glass">
                <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-handshake" style="color: var(--theme-accent-blue);"></i> Outgoing Connection Requests</h3>
                
                <?php if (!empty($sent_requests)): ?>
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                         <?php foreach ($sent_requests as $req): 
                             $receiver_pic = get_avatar_url($req['profile_pic'] ?? '');
                             $receiver_id_str = get_student_id_string($req['receiver_id'], $req['course'] ?? '');
                         ?>
                            <div style="border-bottom: 1px solid var(--theme-border); padding-bottom: 1.25rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                    <div style="display:flex; align-items:center; gap:0.75rem;">
                                        <img src="<?php echo $receiver_pic; ?>" alt="Avatar" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                                        <div>
                                            <h4 style="font-size:0.95rem;"><?php echo htmlspecialchars($req['receiver_name']); ?> <small style="opacity: 0.6; font-size: 0.75rem;">(<?php echo $receiver_id_str; ?>)</small></h4>
                                            <p style="font-size:0.75rem; color:var(--theme-text-secondary);"><?php echo htmlspecialchars($req['course']); ?> | <strong style="text-transform: uppercase;"><?php echo htmlspecialchars($req['receiver_role']); ?></strong></p>
                                        </div>
                                    </div>
                                    <span class="badge badge-<?php echo $req['status'] === 'accepted' ? 'approved' : ($req['status'] === 'pending' ? 'pending' : 'rejected'); ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                </div>
                                <p style="font-size:0.85rem; color:var(--theme-text-secondary); font-style:italic; background:rgba(0,0,0,0.02); border: 1px solid var(--theme-border); padding:0.5rem 0.75rem; border-radius:6px; margin-bottom:0.75rem;">
                                    "<?php echo htmlspecialchars($req['message']); ?>"
                                </p>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:0.75rem; color:var(--theme-text-secondary);"><i class="fa-solid fa-clock"></i> Sent: <?php echo date('M d, Y', strtotime($req['created_at'])); ?></span>
                                    <div style="display:flex; gap:0.5rem;">
                                        <?php if ($req['status'] === 'accepted'): ?>
                                            <a href="chat.php?peer_id=<?php echo $req['receiver_id']; ?>" class="btn btn-primary btn-small"><i class="fa-solid fa-comment-dots"></i> Chat</a>
                                            <a href="view_profile.php?id=<?php echo $req['receiver_id']; ?>" class="btn btn-secondary btn-small"><i class="fa-solid fa-user"></i> Profile</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                         <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--theme-text-secondary); text-align:center; padding: 2rem 0;">No outgoing connection requests sent. Search directory to establish connections!</p>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<!-- Scripts loading sidebars collapse -->
<script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

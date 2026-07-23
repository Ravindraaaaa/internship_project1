<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
handle_session_timeout();

$uid = get_user_id();
$role = get_user_role();
$user_name = get_user_name();

$target_id = intval($_GET['id'] ?? 0);
if ($target_id <= 0) {
    set_flash('error', 'Invalid member profile ID.');
    header('Location: alumni.php');
    exit;
}

// Redirect to self-profile if target is current user
if ($target_id === $uid) {
    header('Location: profile.php');
    exit;
}

try {
    // 1. Fetch core target user details
    $stmtC = $pdo->prepare("SELECT id, name, role, email, status FROM users WHERE id = ? AND status = 'approved'");
    $stmtC->execute([$target_id]);
    $target_user = $stmtC->fetch();

    if (!$target_user) {
        set_flash('error', 'Member profile not found or pending approval.');
        header('Location: alumni.php');
        exit;
    }

    $target_role = $target_user['role'];

    // 2. Fetch profile details
    $profile = [];
    if ($target_role === 'alumni') {
        $stmtP = $pdo->prepare("SELECT * FROM alumni_profiles WHERE user_id = ?");
        $stmtP->execute([$target_id]);
        $profile = $stmtP->fetch() ?: [];
    } else {
        $stmtP = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$target_id]);
        $profile = $stmtP->fetch() ?: [];
    }

    // 3. Fetch skills
    $stmtSkills = $pdo->prepare("
        SELECT s.name, us.progress 
        FROM user_skills us 
        JOIN skills s ON us.skill_id = s.id 
        WHERE us.user_id = ? 
        ORDER BY us.progress DESC
    ");
    $stmtSkills->execute([$target_id]);
    $skills = $stmtSkills->fetchAll();

    // 4. Fetch achievements
    $stmtAch = $pdo->prepare("SELECT title, description, date_achieved FROM achievements WHERE user_id = ? ORDER BY date_achieved DESC");
    $stmtAch->execute([$target_id]);
    $achievements = $stmtAch->fetchAll();

    // 5. Fetch certificates
    $stmtCerts = $pdo->prepare("SELECT name, issuer, issue_date, file_path FROM user_certificates WHERE user_id = ? ORDER BY issue_date DESC");
    $stmtCerts->execute([$target_id]);
    $certificates = $stmtCerts->fetchAll();

    // 6. Fetch connection status
    $stmtConn = $pdo->prepare("
        SELECT status FROM mentorship_requests 
        WHERE (student_id = ? AND alumni_id = ?) OR (student_id = ? AND alumni_id = ?)
    ");
    $stmtConn->execute([$uid, $target_id, $target_id, $uid]);
    $conn_status = $stmtConn->fetchColumn();

} catch (Exception $e) {
    set_flash('error', 'Failed loading profile details.');
    header('Location: alumni.php');
    exit;
}

// 6.5 Load Target User's Connections List
$connected_users = [];
try {
    $stmtConnUsers = $pdo->prepare("
        SELECT u.id as user_id, u.name, u.role, 
               COALESCE(ap.course, sp.course) as course,
               COALESCE(ap.profile_pic, sp.profile_pic) as profile_pic
        FROM mentorship_requests mr
        JOIN users u ON (u.id = mr.student_id AND mr.alumni_id = ?) OR (u.id = mr.alumni_id AND mr.student_id = ?)
        LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND u.role = 'alumni'
        LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
        WHERE mr.status = 'accepted' AND u.id != ?
    ");
    $stmtConnUsers->execute([$target_id, $target_id, $target_id]);
    $connected_users = $stmtConnUsers->fetchAll();
} catch (Exception $e) {
    $connected_users = [];
}

$page_title = htmlspecialchars($target_user['name']) . "'s Profile";
$sidebar_avatar = get_avatar_url($profile['profile_pic'] ?? '');
$target_display_id = get_student_id_string($target_user['id'], $profile['course'] ?? '');

$is_subfolder = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    
    <!-- Sidebar -->
    <?php render_sidebar('alumni'); ?>

    <div class="dashboard-content-area">
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h2>Member Profile</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark/Bright Mode">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <a href="alumni.php" class="btn btn-secondary btn-small"><i class="fa-solid fa-arrow-left"></i> Directory</a>
            </div>
        </nav>

        <main class="dashboard-workspace">
            
            <!-- Cover Header -->
            <div class="profile-cover-wrapper">
                <div class="profile-cover-photo"></div>
                <div class="profile-avatar-row">
                    <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="Avatar" class="profile-avatar-main">
                    <div class="profile-header-info">
                        <h2><?php echo htmlspecialchars($target_user['name']); ?></h2>
                        <p><?php echo htmlspecialchars($profile['course'] ?? 'No stream configured'); ?></p>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; flex-wrap: wrap;">
                            <a href="javascript:void(0)" onclick="openConnectionsModal()" style="text-decoration: none; font-size: 0.82rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; color: var(--theme-accent-purple);">
                                <i class="fa-solid fa-circle-nodes"></i> <?php echo count($connected_users); ?> Connections
                            </a>
                            <?php if ($target_role === 'student'): ?>
                                <span class="badge" style="background: var(--theme-accent-purple); color: #fff; font-size: 0.68rem; padding: 0.2rem 0.6rem;"><?php echo htmlspecialchars($target_display_id); ?></span>
                            <?php else: ?>
                                <span class="badge badge-alumni" style="font-size: 0.68rem; padding: 0.2rem 0.6rem;"><?php echo htmlspecialchars($target_display_id); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Connect / Message Actions in cover header -->
                    <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 25px;">
                        <?php if ($conn_status): ?>
                            <?php if ($conn_status === 'accepted'): ?>
                                <button class="btn btn-secondary btn-small" style="pointer-events:none; opacity:0.85; padding: 0.6rem 1rem;" disabled><i class="fa-solid fa-circle-check"></i> Connected</button>
                                <a href="chat.php?peer_id=<?php echo $target_id; ?>" class="btn btn-primary btn-small" style="padding: 0.6rem 1rem;"><i class="fa-solid fa-comment-dots"></i> Message</a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-small" style="pointer-events:none; opacity:0.8; padding: 0.6rem 1rem;" disabled><i class="fa-solid fa-hourglass-start"></i> Connection <?php echo ucfirst($conn_status); ?></button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-primary btn-small" style="padding: 0.6rem 1.2rem;" onclick="openMentorshipSetup(<?php echo $target_id; ?>, '<?php echo htmlspecialchars(addslashes($target_user['name'])); ?>', '<?php echo $target_role; ?>')">
                                <i class="fa-solid <?php echo $target_role === 'alumni' ? 'fa-handshake-angle' : 'fa-user-plus'; ?>"></i> 
                                Connect
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Details Split Columns -->
            <div style="display: grid; grid-template-columns: 1.1fr 2fr; gap: 2rem; align-items: start;">
                
                <!-- Left Column: Core Info Details Card -->
                <div style="display:flex; flex-direction:column; gap:2rem;">
                    <div class="card-glass" style="padding: 2rem 1.5rem;">
                        <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1.25rem; border-bottom: 1px solid var(--theme-border); padding-bottom: 0.75rem; color: var(--theme-text);">
                            <i class="fa-solid fa-address-card" style="color:var(--theme-accent-blue);"></i> Core Profile Info
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                            <div>
                                <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">Course / Branch</h4>
                                <p style="font-size: 0.92rem; font-weight: 600; color: var(--theme-text); margin: 0;"><?php echo htmlspecialchars($profile['course'] ?? 'Not configured'); ?></p>
                            </div>

                            <?php if ($target_role === 'alumni'): ?>
                                <div>
                                    <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">Graduation Year</h4>
                                    <p style="font-size: 0.92rem; color: var(--theme-text); margin: 0;">Class of <?php echo htmlspecialchars($profile['graduation_year'] ?? 'Not set'); ?></p>
                                </div>
                                <?php if (!empty($profile['company'])): ?>
                                    <div>
                                        <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">Current Employment</h4>
                                        <p style="font-size: 0.92rem; color: var(--theme-text); margin: 0;"><strong><?php echo htmlspecialchars($profile['position']); ?></strong> at <?php echo htmlspecialchars($profile['company']); ?> (<?php echo htmlspecialchars($profile['industry']); ?>)</p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($profile['website'])): ?>
                                    <div>
                                        <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">Website / Portfolio</h4>
                                        <p style="font-size: 0.92rem; margin: 0;"><a href="<?php echo htmlspecialchars($profile['website']); ?>" target="_blank" style="color: var(--theme-accent-blue); text-decoration: underline;"><i class="fa-solid fa-globe"></i> Visit Website</a></p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div>
                                    <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">Academic Year</h4>
                                    <p style="font-size: 0.92rem; color: var(--theme-text); margin: 0;">Year <?php echo htmlspecialchars($profile['current_year'] ?? '1'); ?></p>
                                </div>
                                <div>
                                    <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">Cumulative CGPA</h4>
                                    <p style="font-size: 0.92rem; color: var(--theme-text); margin: 0;"><?php echo htmlspecialchars($profile['cgpa'] ?? '0.00'); ?> / 10.00</p>
                                </div>
                                <?php if (!empty($profile['github'])): ?>
                                    <div>
                                        <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">GitHub Portfolio</h4>
                                        <p style="font-size: 0.92rem; margin: 0;"><a href="<?php echo htmlspecialchars($profile['github']); ?>" target="_blank" style="color: var(--theme-accent-blue); text-decoration: underline;"><i class="fa-brands fa-github"></i> Visit GitHub</a></p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Email Address Hidden for Privacy Constraint -->
                            <div>
                                <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">Email Address</h4>
                                <p style="font-size: 0.92rem; color: var(--theme-text-secondary); font-style: italic; margin: 0;"><i class="fa-solid fa-lock"></i> Protected for privacy</p>
                            </div>

                            <?php if (!empty($profile['linkedin'])): ?>
                                <div>
                                    <h4 style="font-size: 0.78rem; font-weight: 600; color: var(--theme-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase;">LinkedIn Link</h4>
                                    <p style="font-size: 0.92rem; margin: 0;"><a href="<?php echo htmlspecialchars($profile['linkedin']); ?>" target="_blank" style="color: var(--theme-accent-blue); text-decoration: underline;"><i class="fa-brands fa-linkedin"></i> Visit LinkedIn</a></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <!-- Right Column: Biography, Skills, Achievements, Certificates -->
            <div style="display:flex; flex-direction:column; gap:2rem;">
                
                <!-- Biography Card -->
                <div class="card-glass" style="padding: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;"><i class="fa-solid fa-align-left" style="color:var(--theme-accent-blue);"></i> Biography / Introduction</h3>
                    <p style="font-size: 0.95rem; line-height: 1.6; color: var(--theme-text); margin: 0; white-space: pre-line;">
                        <?php echo htmlspecialchars($profile['bio'] ?? 'This member has not written a biography yet.'); ?>
                    </p>
                </div>

                <!-- Skills Card -->
                <div class="card-glass" style="padding: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;"><i class="fa-solid fa-chart-line" style="color:var(--theme-accent-purple);"></i> Professional Skills</h3>
                    <?php if (!empty($skills)): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                            <?php foreach ($skills as $sk): ?>
                                <div>
                                    <div style="display:flex; justify-content:space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                                        <strong><?php echo htmlspecialchars($sk['name']); ?></strong>
                                        <span style="color:var(--theme-accent-purple); font-weight:700;"><?php echo $sk['progress']; ?>%</span>
                                    </div>
                                    <div style="width:100%; height:6px; background:rgba(255,255,255,0.06); border-radius:50px; overflow:hidden;">
                                        <div style="width:<?php echo $sk['progress']; ?>%; height:100%; background:var(--theme-accent-gradient); border-radius:50px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.88rem; color: var(--theme-text-secondary); font-style: italic; margin:0;">No skills listed yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Achievements Card -->
                <div class="card-glass" style="padding: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;"><i class="fa-solid fa-trophy" style="color:#fbbf24;"></i> Honors & Achievements</h3>
                    <?php if (!empty($achievements)): ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($achievements as $ach): ?>
                                <div style="background:rgba(15,23,42,0.02); border:1px solid var(--theme-border); padding: 1.25rem; border-radius: var(--border-radius-sm);">
                                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                                        <i class="fa-solid fa-award" style="color:#fbbf24;"></i>
                                        <h4 style="font-size:0.95rem; font-weight:700; margin:0;"><?php echo htmlspecialchars($ach['title']); ?></h4>
                                    </div>
                                    <p style="font-size:0.82rem; color:var(--theme-text-secondary); margin-bottom:0.4rem;"><?php echo htmlspecialchars($ach['description']); ?></p>
                                    <p style="font-size:0.75rem; color:var(--theme-text-secondary);"><i class="fa-solid fa-calendar-days"></i> <?php echo date('M d, Y', strtotime($ach['date_achieved'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.88rem; color: var(--theme-text-secondary); font-style: italic; margin:0;">No awards or honors cataloged yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Certificates Card -->
                <div class="card-glass" style="padding: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;"><i class="fa-solid fa-certificate" style="color:var(--theme-accent-blue);"></i> Credentials & Certificates</h3>
                    <?php if (!empty($certificates)): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                            <?php foreach ($certificates as $cert): ?>
                                <div style="background:rgba(15,23,42,0.02); border:1px solid var(--theme-border); padding: 1rem; border-radius: var(--border-radius-sm); display:flex; flex-direction:column; justify-content:space-between; min-height:110px;">
                                    <div>
                                        <h4 style="font-size:0.88rem; font-weight:700; margin-bottom:0.2rem;"><?php echo htmlspecialchars($cert['name']); ?></h4>
                                        <p style="font-size:0.75rem; color:var(--theme-text-secondary); margin-bottom:0.5rem;"><i class="fa-solid fa-building-columns"></i> <?php echo htmlspecialchars($cert['issuer']); ?></p>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--theme-border); padding-top:0.5rem;">
                                        <span style="font-size:0.7rem; color:var(--theme-text-secondary);"><?php echo date('M d, Y', strtotime($cert['issue_date'])); ?></span>
                                        <?php if (!empty($cert['file_path']) && file_exists(__DIR__ . '/../' . $cert['file_path'])): ?>
                                            <a href="../<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank" style="font-size:0.72rem; color:var(--theme-accent-blue); text-decoration:underline;"><i class="fa-solid fa-file-pdf"></i> View doc</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.88rem; color: var(--theme-text-secondary); font-style: italic; margin:0;">No credentials cataloged yet.</p>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div>
</div>

<!-- Connection Modal -->
<div class="modal" id="mentorshipRequestModal">
    <div class="modal-content" style="max-width: 500px;">
        <button class="modal-close" onclick="closeModal('mentorshipRequestModal')">&times;</button>
        <h2 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-graduation-cap" style="color: var(--theme-accent-purple);" id="modal-icon"></i> <span id="modal-title-text">Request Connection</span></h2>
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Requesting connection with: <strong id="mentor-name-title"></strong></p>
        
        <form action="mentorship.php" method="POST">
            <input type="hidden" name="action" value="request">
            <input type="hidden" name="alumni_id" id="modal-alumni-id" value="">
            
            <div class="form-group">
                <label for="message" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Introduction Message</label>
                <textarea name="message" id="message" class="input-glass" rows="4" placeholder="..." required></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('mentorshipRequestModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openMentorshipSetup(id, name, role) {
        document.getElementById('modal-alumni-id').value = id;
        document.getElementById('mentor-name-title').textContent = name;
        if (role === 'student') {
            document.getElementById('modal-title-text').textContent = 'Connect with Student';
            document.getElementById('modal-icon').className = 'fa-solid fa-user-plus';
            document.getElementById('message').placeholder = 'Introduce yourself and state why you would like to connect...';
        } else {
            document.getElementById('modal-title-text').textContent = 'Request Mentoring';
            document.getElementById('modal-icon').className = 'fa-solid fa-graduation-cap';
            document.getElementById('message').placeholder = 'Briefly state your academic goals and how they can help...';
        }
        openModal('mentorshipRequestModal');
    }
</script>
<!-- Connections Modal -->
<div class="modal" id="connectionsListModal">
    <div class="modal-content" style="max-width: 550px; padding: 2rem;">
        <button class="modal-close" onclick="closeModal('connectionsListModal')">&times;</button>
        <h2 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; color: var(--theme-text); font-size: 1.25rem;">
            <i class="fa-solid fa-circle-nodes" style="color: var(--theme-accent-purple);"></i> Connections List
        </h2>
        
        <?php if (!empty($connected_users)): ?>
            <div style="display: flex; flex-direction: column; gap: 1rem; max-height: 380px; overflow-y: auto; padding-right: 0.5rem;" class="custom-scrollbar">
                <?php foreach ($connected_users as $conn): 
                    $conn_avatar = get_avatar_url($conn['profile_pic'] ?? '');
                    $conn_id_str = get_student_id_string($conn['user_id'], $conn['course'] ?? '');
                ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.02); border: 1px solid var(--theme-border); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm); transition: transform 0.2s;" onmouseover="this.style.transform='translateX(3px)'" onmouseout="this.style.transform='none'">
                        <div style="display: flex; align-items: center; gap: 0.75rem; width: 70%;">
                            <img src="<?php echo $conn_avatar; ?>" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <h4 style="font-size: 0.88rem; margin: 0; font-weight: 700;">
                                    <a href="view_profile.php?id=<?php echo $conn['user_id']; ?>" style="color: var(--theme-text); text-decoration: none;"><?php echo htmlspecialchars($conn['name']); ?></a>
                                </h4>
                                <span style="font-size: 0.72rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($conn['course']); ?></span>
                                <span class="badge" style="font-size: 0.6rem; padding: 0.15rem 0.4rem; background: var(--theme-accent-purple); color: #fff; margin-left: 0.35rem;"><?php echo $conn_id_str; ?></span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="view_profile.php?id=<?php echo $conn['user_id']; ?>" class="btn btn-secondary btn-small" style="padding: 0.35rem 0.65rem; font-size: 0.72rem;" title="View Profile"><i class="fa-solid fa-user"></i></a>
                            <?php if ($conn['user_id'] != $uid): ?>
                                <a href="chat.php?peer_id=<?php echo $conn['user_id']; ?>" class="btn btn-primary btn-small" style="padding: 0.35rem 0.65rem; font-size: 0.72rem;" title="Send Message"><i class="fa-solid fa-comment-dots"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2.5rem 1rem;">
                <i class="fa-solid fa-user-group" style="font-size: 2.5rem; color: var(--theme-text-secondary); margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="color: var(--theme-text-secondary); font-size: 0.9rem; margin: 0;">No active accepted connections recorded.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function openConnectionsModal() {
    openModal('connectionsListModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

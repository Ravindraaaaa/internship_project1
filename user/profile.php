<?php
ob_start();
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

if (is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$uid = get_user_id();
$role = get_user_role();
$user_name = get_user_name();

$page_title = "My Profile Details";

// 1. Process Profile Update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    
    $bio = trim($_POST['bio'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    
    try {
        $pdo->beginTransaction();

        // Common details or profile pic handling
        $profile_pic_path = '';
        
        // Fetch current profile pic first
        if ($role === 'alumni') {
            $stmtC = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
            $stmtC->execute([$uid]);
            $profile_pic_path = $stmtC->fetchColumn();
        } else {
            $stmtC = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
            $stmtC->execute([$uid]);
            $profile_pic_path = $stmtC->fetchColumn();
        }

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
            $fileName = $_FILES['profile_pic']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadFileDir = '../uploads/profiles/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $newFileName = md5(time() . $uid) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $profile_pic_path = 'uploads/profiles/' . $newFileName;
                } else {
                    throw new Exception("Failed to save uploaded profile picture.");
                }
            } else {
                throw new Exception("Invalid profile picture extension. Only JPG, JPEG, and PNG are allowed.");
            }
        } elseif (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("Profile picture upload failed with error code: " . $_FILES['profile_pic']['error']);
        }

        if ($profile_pic_path === false) {
            $profile_pic_path = null;
        }

        if ($role === 'alumni') {
            $grad_year = intval($_POST['graduation_year'] ?? date('Y'));
            $course = trim($_POST['course'] ?? '');
            $company = trim($_POST['company'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $industry = trim($_POST['industry'] ?? '');
            $website = trim($_POST['website'] ?? '');

            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM alumni_profiles WHERE user_id = ?");
            $stmtCheck->execute([$uid]);
            if ($stmtCheck->fetchColumn() > 0) {
                $stmtUp = $pdo->prepare("UPDATE alumni_profiles 
                                         SET graduation_year = ?, course = ?, company = ?, position = ?, industry = ?, linkedin = ?, website = ?, bio = ?, profile_pic = ? 
                                         WHERE user_id = ?");
                $stmtUp->execute([$grad_year, $course, $company, $position, $industry, $linkedin, $website, $bio, $profile_pic_path, $uid]);
            } else {
                $stmtUp = $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, course, company, position, industry, linkedin, website, bio, profile_pic) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtUp->execute([$uid, $grad_year, $course, $company, $position, $industry, $linkedin, $website, $bio, $profile_pic_path]);
            }
        } else {
            $curr_yr = intval($_POST['current_year'] ?? 1);
            $course = trim($_POST['course'] ?? '');
            $github = trim($_POST['github'] ?? '');
            $cgpa = floatval($_POST['cgpa'] ?? 0.00);

            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM student_profiles WHERE user_id = ?");
            $stmtCheck->execute([$uid]);
            if ($stmtCheck->fetchColumn() > 0) {
                $stmtUp = $pdo->prepare("UPDATE student_profiles 
                                         SET current_year = ?, course = ?, linkedin = ?, github = ?, bio = ?, profile_pic = ?, cgpa = ? 
                                         WHERE user_id = ?");
                $stmtUp->execute([$curr_yr, $course, $linkedin, $github, $bio, $profile_pic_path, $cgpa, $uid]);
            } else {
                $stmtUp = $pdo->prepare("INSERT INTO student_profiles (user_id, current_year, course, linkedin, github, bio, profile_pic, cgpa) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtUp->execute([$uid, $curr_yr, $course, $linkedin, $github, $bio, $profile_pic_path, $cgpa]);
            }
        }

        // Update name in users table
        $name_input = trim($_POST['name'] ?? '');
        if (!empty($name_input)) {
            $stmtName = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmtName->execute([$name_input, $uid]);
            $_SESSION['user_name'] = $name_input;
        }

        $pdo->commit();
        set_flash('success', 'Profile updated successfully!');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', 'Update failed: ' . $e->getMessage());
    }
    header('Location: profile.php');
    exit;
}

// 2. Load Core Data
$profile = [];
$user_core = [];

try {
    $stmtC = $pdo->prepare("SELECT name, email, role FROM users WHERE id = ?");
    $stmtC->execute([$uid]);
    $user_core = $stmtC->fetch();

    if ($role === 'alumni') {
        $stmtP = $pdo->prepare("SELECT * FROM alumni_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $profile = $stmtP->fetch() ?: [];
    } else {
        $stmtP = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $profile = $stmtP->fetch() ?: [];
    }
} catch (Exception $e) {
    set_flash('error', 'Failed loading profile details.');
}

// 2.5 Load Connected Users List
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
    $stmtConnUsers->execute([$uid, $uid, $uid]);
    $connected_users = $stmtConnUsers->fetchAll();
} catch (Exception $e) {
    $connected_users = [];
}

// 3. Calculate Profile Progress
$total_fields = 8;
$filled_fields = 0;

if (!empty($user_core['name'])) $filled_fields++;
if (!empty($profile['profile_pic'])) $filled_fields++;
if (!empty($profile['bio'])) $filled_fields++;
if (!empty($profile['course'])) $filled_fields++;
if (!empty($profile['linkedin'])) $filled_fields++;

if ($role === 'alumni') {
    if (!empty($profile['graduation_year'])) $filled_fields++;
    if (!empty($profile['company'])) $filled_fields++;
    if (!empty($profile['position'])) $filled_fields++;
} else {
    if (!empty($profile['current_year'])) $filled_fields++;
    if (!empty($profile['github'])) $filled_fields++;
    $total_fields = 7;
}

$completion_percent = round(($filled_fields / $total_fields) * 100);

$sidebar_avatar = get_avatar_url($profile['profile_pic'] ?? '');

$is_subfolder = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .progress-bar-container {
        width: 100%;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50px;
        height: 10px;
        margin-top: 0.5rem;
        overflow: hidden;
        border: 1px solid var(--theme-border);
    }
    .progress-bar-fill {
        height: 100%;
        background: var(--theme-accent-gradient);
        width: 0%;
        border-radius: 50px;
        transition: width 1.5s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .profile-tabs .tab-btn {
        background: transparent;
        border: none;
        padding: 0.6rem 1.4rem;
        border-radius: 50px;
        color: var(--theme-text-secondary);
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.25s ease;
    }
    .profile-tabs .tab-btn.active {
        background: var(--theme-accent-gradient);
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
    }
    .profile-tabs .tab-btn:hover:not(.active) {
        color: var(--theme-text);
        background: rgba(0, 0, 0, 0.04);
    }
    .profile-bento-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    .bento-card {
        background: var(--theme-card);
        border: 1px solid var(--theme-border);
        border-radius: var(--border-radius-lg);
        padding: 1.5rem;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .bento-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-soft);
    }
    .bento-card-header {
        font-size: 0.95rem;
        font-weight: 700;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-bottom: 1px solid var(--theme-border);
        padding-bottom: 0.75rem;
    }
    .bento-item {
        margin-bottom: 1rem;
    }
    .bento-item:last-child {
        margin-bottom: 0;
    }
    .bento-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--theme-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }
    .bento-value {
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--theme-text);
    }
    .bio-quote-box {
        border-left: 4px solid var(--theme-accent-purple);
        background: rgba(139, 92, 246, 0.02);
        padding: 1rem 1.25rem;
        border-radius: 0 12px 12px 0;
    }
</style>

<div class="dashboard-wrapper">
    
    <!-- Sidebar -->
    <?php render_sidebar('profile'); ?>

    <div class="dashboard-content-area">
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h2>Edit Member Profile</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark/Bright Mode">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-small"><i class="fa-solid fa-gauge"></i> Dashboard</a>
            </div>
        </nav>

        <main class="dashboard-workspace">
            
            <!-- Cover Header -->
            <div class="profile-cover-wrapper">
                <div class="profile-cover-photo"></div>
                <div class="profile-avatar-row">
                    <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="Avatar" class="profile-avatar-main">
                    <div class="profile-header-info">
                        <h2><?php echo htmlspecialchars($user_name); ?></h2>
                        <p><?php echo htmlspecialchars($profile['course'] ?? 'No stream configured'); ?></p>
                        <a href="javascript:void(0)" onclick="openConnectionsModal()" style="text-decoration: none; font-size: 0.82rem; font-weight: 700; margin-top: 0.5rem; display: inline-flex; align-items: center; gap: 0.4rem; color: var(--theme-accent-purple);">
                            <i class="fa-solid fa-circle-nodes"></i> <?php echo count($connected_users); ?> Connections
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Layout columns -->
            <div class="profile-two-columns">
                
                <!-- Left stats progress card -->
                <div class="profile-sidebar-card">
                    <div class="card-glass">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1.25rem;">Completeness Progress</h3>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size: 0.85rem; color: var(--theme-text-secondary);">Current Level</span>
                            <strong style="color: var(--theme-accent-purple); font-size:1.1rem;"><?php echo $completion_percent; ?>%</strong>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" id="completion-bar-fill" style="width: <?php echo $completion_percent; ?>%;"></div>
                        </div>
                    </div>

                    <div class="card-glass">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">Verification Status</h3>
                        <?php if ($role === 'alumni'): ?>
                            <?php 
                            $status = $user_core['role'] === 'alumni' ? $profile['user_id'] : 0;
                            // Fetch verification status from users
                            $st = $pdo->prepare("SELECT status FROM users WHERE id = ?");
                            $st->execute([$uid]);
                            $alumni_status = $st->fetchColumn();
                            ?>
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <i class="fa-solid fa-circle-check" style="font-size:1.55rem; color: <?php echo $alumni_status === 'approved' ? '#10b981' : '#f59e0b'; ?>;"></i>
                                <div>
                                    <h4 style="font-size:0.92rem;"><?php echo ucfirst($alumni_status); ?></h4>
                                    <p style="font-size:0.75rem; color:var(--theme-text-secondary);">Administrator verification status</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <i class="fa-solid fa-circle-check" style="font-size:1.55rem; color: #10b981;"></i>
                                <div>
                                    <h4 style="font-size:0.92rem;">Verified Student</h4>
                                    <p style="font-size:0.75rem; color:var(--theme-text-secondary);">Standard student credentials accepted</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Platform Activity Metrics -->
                    <div class="card-glass">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-chart-simple" style="color: var(--theme-accent-blue);"></i> Platform Activity</h3>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 0.5rem;">
                                <span style="font-size: 0.82rem; color: var(--theme-text-secondary);"><i class="fa-solid fa-circle-nodes"></i> Connections</span>
                                <strong style="font-size: 0.9rem; color: var(--theme-text);"><?php echo count($connected_users); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 0.5rem;">
                                <span style="font-size: 0.82rem; color: var(--theme-text-secondary);"><i class="fa-solid fa-briefcase"></i> Saved Jobs</span>
                                <strong style="font-size: 0.9rem; color: var(--theme-text);"><?php echo isset($bookmarked_jobs) ? count($bookmarked_jobs) : 0; ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.25rem;">
                                <span style="font-size: 0.82rem; color: var(--theme-text-secondary);"><i class="fa-solid fa-calendar-check"></i> Reserved RSVPs</span>
                                <strong style="font-size: 0.9rem; color: var(--theme-text);"><?php echo isset($saved_events) ? count($saved_events) : 0; ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Shortcuts -->
                    <div class="card-glass">
                        <h3 style="font-size: 1.1rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-rocket" style="color: var(--theme-accent-purple);"></i> Quick Links</h3>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <a href="portfolio.php" class="btn btn-secondary btn-small" style="width: 100%; text-align: left; justify-content: flex-start; display: flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-file-invoice"></i> My Resume & Locker</a>
                            <a href="chat.php" class="btn btn-secondary btn-small" style="width: 100%; text-align: left; justify-content: flex-start; display: flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-comment-dots"></i> Message Center</a>
                        </div>
                    </div>

                </div>

                <!-- Right side: View profile details or edit form -->
                <div class="profile-main-content" style="flex-grow: 1;">
                    
                    <!-- Premium Tabs Capsule -->
                    <div style="display: flex; gap: 0.5rem; background: rgba(15,23,42,0.03); border: 1px solid var(--theme-border); padding: 0.35rem; border-radius: 50px; width: fit-content; margin-bottom: 1.75rem;" class="profile-tabs">
                        <button type="button" class="tab-btn active" id="tab-overview-btn" onclick="switchProfileTab('overview')">
                            <i class="fa-solid fa-address-card"></i> Overview
                        </button>
                        <button type="button" class="tab-btn" id="tab-edit-btn" onclick="switchProfileTab('edit')">
                            <i class="fa-solid fa-user-gear"></i> Edit Details
                        </button>
                    </div>

                    <!-- 1. Overview Tab -->
                    <div id="profile-overview-tab" class="profile-tab-content">
                        <div class="profile-bento-grid">
                            
                            <!-- Bento Card 1: Core Credentials -->
                            <div class="bento-card">
                                <div class="bento-card-header" style="color: var(--theme-text);">
                                    <i class="fa-solid fa-shield-halved" style="color: var(--theme-accent-purple);"></i> Credentials & ID
                                </div>
                                <div class="bento-item">
                                    <div class="bento-label">Member System ID</div>
                                    <div class="bento-value" style="color: var(--theme-accent-purple); font-size: 1.05rem; font-weight: 700;">
                                        <i class="fa-solid fa-id-badge"></i> <?php echo htmlspecialchars(get_student_id_string($uid, $profile['course'] ?? '')); ?>
                                    </div>
                                </div>
                                <div class="bento-item">
                                    <div class="bento-label">Full Name</div>
                                    <div class="bento-value"><?php echo htmlspecialchars($user_name); ?></div>
                                </div>
                                <div class="bento-item">
                                    <div class="bento-label">Account Role</div>
                                    <div class="bento-value" style="text-transform: uppercase; font-size: 0.8rem;"><?php echo htmlspecialchars($role); ?></div>
                                </div>
                            </div>

                            <!-- Bento Card 2: Academic & Employment -->
                            <div class="bento-card">
                                <div class="bento-card-header" style="color: var(--theme-text);">
                                    <i class="fa-solid fa-graduation-cap" style="color: var(--theme-accent-blue);"></i> Course & Details
                                </div>
                                <div class="bento-item">
                                    <div class="bento-label">Department / Stream</div>
                                    <div class="bento-value"><?php echo htmlspecialchars($profile['course'] ?? 'Not configured'); ?></div>
                                </div>
                                <?php if ($role === 'alumni'): ?>
                                    <div class="bento-item">
                                        <div class="bento-label">Graduation Year</div>
                                        <div class="bento-value">Class of <?php echo htmlspecialchars($profile['graduation_year'] ?? 'Not set'); ?></div>
                                    </div>
                                    <?php if (!empty($profile['company'])): ?>
                                        <div class="bento-item">
                                            <div class="bento-label">Current Role</div>
                                            <div class="bento-value"><?php echo htmlspecialchars($profile['position']); ?> at <?php echo htmlspecialchars($profile['company']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="bento-item">
                                        <div class="bento-label">Academic Year</div>
                                        <div class="bento-value">Year <?php echo htmlspecialchars($profile['current_year'] ?? '1'); ?></div>
                                    </div>
                                    <div class="bento-item">
                                        <div class="bento-label">Cumulative CGPA</div>
                                        <div class="bento-value"><?php echo htmlspecialchars($profile['cgpa'] ?? '0.00'); ?> / 10.00</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Bento Card 3: Social Connectivity -->
                            <div class="bento-card" style="grid-column: span 2;">
                                <div class="bento-card-header" style="color: var(--theme-text);">
                                    <i class="fa-solid fa-share-nodes" style="color: var(--theme-accent-purple);"></i> Verified Network Connections
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                                    <div class="bento-item">
                                        <div class="bento-label">Email Handle</div>
                                        <div class="bento-value" style="font-size: 0.82rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($user_core['email']); ?></div>
                                    </div>
                                    <div class="bento-item">
                                        <div class="bento-label">LinkedIn profile</div>
                                        <div class="bento-value" style="font-size: 0.82rem;">
                                            <?php if (!empty($profile['linkedin'])): ?>
                                                <a href="<?php echo htmlspecialchars($profile['linkedin']); ?>" target="_blank" style="color: var(--theme-accent-blue); text-decoration: underline;"><i class="fa-brands fa-linkedin"></i> Verified Profile</a>
                                            <?php else: ?>
                                                <span style="opacity: 0.5;">Not connected</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="bento-item">
                                        <div class="bento-label">GitHub / Website</div>
                                        <div class="bento-value" style="font-size: 0.82rem;">
                                            <?php if ($role === 'student' && !empty($profile['github'])): ?>
                                                <a href="<?php echo htmlspecialchars($profile['github']); ?>" target="_blank" style="color: var(--theme-accent-blue); text-decoration: underline;"><i class="fa-brands fa-github"></i> Repository</a>
                                            <?php elseif ($role === 'alumni' && !empty($profile['website'])): ?>
                                                <a href="<?php echo htmlspecialchars($profile['website']); ?>" target="_blank" style="color: var(--theme-accent-blue); text-decoration: underline;"><i class="fa-solid fa-globe"></i> Website</a>
                                            <?php else: ?>
                                                <span style="opacity: 0.5;">Not connected</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bento Card 4: Biography / Intro -->
                            <div class="bento-card" style="grid-column: span 2;">
                                <div class="bento-card-header" style="color: var(--theme-text);">
                                    <i class="fa-solid fa-quote-left" style="color: var(--theme-accent-blue);"></i> About Me / Biography
                                </div>
                                <div class="bio-quote-box">
                                    <p style="font-size: 0.92rem; color: var(--theme-text); line-height: 1.6; margin: 0; white-space: pre-line;">
                                        <?php echo htmlspecialchars($profile['bio'] ?? 'Write a short professional description about your background, career, and research targets.'); ?>
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- 2. Edit Profile Tab -->
                    <div id="profile-edit-tab" class="profile-tab-content" style="display: none;">
                        <div class="card-glass" style="border: 1px solid var(--theme-border); padding: 2rem;">
                            <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--theme-border); padding-bottom: 1rem; color: var(--theme-text);">
                                <i class="fa-solid fa-user-pen" style="color: var(--theme-accent-blue);"></i> Edit Profile Details
                            </h3>
                            
                            <form action="profile.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                                    
                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Full Name</label>
                                        <input type="text" name="name" class="input-glass" value="<?php echo htmlspecialchars($user_name); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Email Address (Read-only)</label>
                                        <input type="email" class="input-glass" value="<?php echo htmlspecialchars($user_core['email']); ?>" readonly style="opacity: 0.6; cursor: not-allowed;">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Department / Stream</label>
                                        <select name="course" class="input-glass" required>
                                            <option value="Computer Science Engineering" <?php echo ($profile['course'] ?? '') == 'Computer Science Engineering' ? 'selected' : ''; ?>>Computer Science Engineering</option>
                                            <option value="Information Technology" <?php echo ($profile['course'] ?? '') == 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                                            <option value="Electronics & Communication" <?php echo ($profile['course'] ?? '') == 'Electronics & Communication' ? 'selected' : ''; ?>>Electronics & Communication</option>
                                            <option value="Mechanical Engineering" <?php echo ($profile['course'] ?? '') == 'Mechanical Engineering' ? 'selected' : ''; ?>>Mechanical Engineering</option>
                                            <option value="Civil Engineering" <?php echo ($profile['course'] ?? '') == 'Civil Engineering' ? 'selected' : ''; ?>>Civil Engineering</option>
                                        </select>
                                    </div>

                                    <!-- Role specific fields -->
                                    <?php if ($role === 'alumni'): ?>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Graduation Year</label>
                                            <input type="number" name="graduation_year" class="input-glass" min="1950" max="2035" value="<?php echo htmlspecialchars($profile['graduation_year'] ?? date('Y')); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Current Company</label>
                                            <input type="text" name="company" class="input-glass" placeholder="e.g. Google" value="<?php echo htmlspecialchars($profile['company'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Current Position</label>
                                            <input type="text" name="position" class="input-glass" placeholder="e.g. Senior Software Engineer" value="<?php echo htmlspecialchars($profile['position'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Industry Sector</label>
                                            <input type="text" name="industry" class="input-glass" placeholder="e.g. Technology" value="<?php echo htmlspecialchars($profile['industry'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Website Portfolio URL</label>
                                            <input type="url" name="website" class="input-glass" placeholder="https://myportfolio.io" value="<?php echo htmlspecialchars($profile['website'] ?? ''); ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Current Academic Year (1-4)</label>
                                            <input type="number" name="current_year" class="input-glass" min="1" max="4" value="<?php echo htmlspecialchars($profile['current_year'] ?? 1); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Cumulative CGPA (0.00 - 10.00)</label>
                                            <input type="number" name="cgpa" step="0.01" min="0" max="10" class="input-glass" placeholder="8.50" value="<?php echo htmlspecialchars($profile['cgpa'] ?? '0.00'); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">GitHub URL</label>
                                            <input type="url" name="github" class="input-glass" placeholder="https://github.com/myname" value="<?php echo htmlspecialchars($profile['github'] ?? ''); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">LinkedIn URL</label>
                                        <input type="url" name="linkedin" class="input-glass" placeholder="https://linkedin.com/in/myname" value="<?php echo htmlspecialchars($profile['linkedin'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group" style="grid-column: span 2;">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Upload Avatar Picture</label>
                                        <input type="file" name="profile_pic" accept="image/*" class="input-glass">
                                    </div>

                                    <div class="form-group" style="grid-column: span 2;">
                                        <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block; color: var(--theme-text);">Short Biography</label>
                                        <textarea name="bio" class="input-glass" rows="4" required><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                    </div>

                                </div>

                                <div style="display:flex; justify-content:flex-end; gap:1rem;">
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

        </main>
    </div>
</div>

<script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>
<script>
function switchProfileTab(tabName) {
    const overviewTab = document.getElementById('profile-overview-tab');
    const editTab = document.getElementById('profile-edit-tab');
    const overviewBtn = document.getElementById('tab-overview-btn');
    const editBtn = document.getElementById('tab-edit-btn');
    
    if (tabName === 'overview') {
        overviewTab.style.display = 'block';
        editTab.style.display = 'none';
        overviewBtn.classList.add('active');
        editBtn.classList.remove('active');
        
        if (window.gsap) {
            gsap.fromTo('#profile-overview-tab', {opacity: 0, y: 15}, {opacity: 1, y: 0, duration: 0.4, ease: 'power2.out'});
        }
    } else {
        overviewTab.style.display = 'none';
        editTab.style.display = 'block';
        overviewBtn.classList.remove('active');
        editBtn.classList.add('active');
        
        if (window.gsap) {
            gsap.fromTo('#profile-edit-tab', {opacity: 0, y: 15}, {opacity: 1, y: 0, duration: 0.4, ease: 'power2.out'});
        }
    }
}
</script>

<!-- Connections Modal -->
<div class="modal" id="connectionsListModal">
    <div class="modal-content" style="max-width: 550px; padding: 2rem;">
        <button class="modal-close" onclick="closeModal('connectionsListModal')">&times;</button>
        <h2 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; color: var(--theme-text); font-size: 1.25rem;">
            <i class="fa-solid fa-circle-nodes" style="color: var(--theme-accent-purple);"></i> My Connections
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
                            <a href="chat.php?peer_id=<?php echo $conn['user_id']; ?>" class="btn btn-primary btn-small" style="padding: 0.35rem 0.65rem; font-size: 0.72rem;" title="Send Message"><i class="fa-solid fa-comment-dots"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2.5rem 1rem;">
                <i class="fa-solid fa-user-group" style="font-size: 2.5rem; color: var(--theme-text-secondary); margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="color: var(--theme-text-secondary); font-size: 0.9rem; margin: 0;">You don't have any accepted network connections yet.</p>
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

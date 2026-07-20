<?php
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
                }
            }
        }

        if ($role === 'alumni') {
            $grad_year = intval($_POST['graduation_year'] ?? date('Y'));
            $course = trim($_POST['course'] ?? '');
            $company = trim($_POST['company'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $industry = trim($_POST['industry'] ?? '');
            $website = trim($_POST['website'] ?? '');

            $stmtUp = $pdo->prepare("UPDATE alumni_profiles 
                                     SET graduation_year = ?, course = ?, company = ?, position = ?, industry = ?, linkedin = ?, website = ?, bio = ?, profile_pic = ? 
                                     WHERE user_id = ?");
            $stmtUp->execute([$grad_year, $course, $company, $position, $industry, $linkedin, $website, $bio, $profile_pic_path, $uid]);
        } else {
            $curr_yr = intval($_POST['current_year'] ?? 1);
            $course = trim($_POST['course'] ?? '');
            $github = trim($_POST['github'] ?? '');
            $cgpa = floatval($_POST['cgpa'] ?? 0.00);

            $stmtUp = $pdo->prepare("UPDATE student_profiles 
                                     SET current_year = ?, course = ?, linkedin = ?, github = ?, bio = ?, profile_pic = ?, cgpa = ? 
                                     WHERE user_id = ?");
            $stmtUp->execute([$curr_yr, $course, $linkedin, $github, $bio, $profile_pic_path, $cgpa, $uid]);
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
        $profile = $stmtP->fetch();
    } else {
        $stmtP = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $profile = $stmtP->fetch();
    }
} catch (Exception $e) {
    set_flash('error', 'Failed loading profile details.');
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
                <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open visual settings">
                    <i class="fa-solid fa-palette"></i>
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
                </div>

                <!-- Right edit form card -->
                <div class="card-glass">
                    <h3 style="font-size: 1.15rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-user-pen" style="color: var(--theme-accent-blue);"></i> Edit Profile Details</h3>
                    
                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            
                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Full Name</label>
                                <input type="text" name="name" class="input-glass" value="<?php echo htmlspecialchars($user_name); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Email Address (Read-only)</label>
                                <input type="email" class="input-glass" value="<?php echo htmlspecialchars($user_core['email']); ?>" readonly style="opacity: 0.6; cursor: not-allowed;">
                            </div>

                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Department / Stream</label>
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
                                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Graduation Year</label>
                                    <input type="number" name="graduation_year" class="input-glass" min="1950" max="2035" value="<?php echo htmlspecialchars($profile['graduation_year'] ?? date('Y')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Current Company</label>
                                    <input type="text" name="company" class="input-glass" placeholder="e.g. Google" value="<?php echo htmlspecialchars($profile['company'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Current Position</label>
                                    <input type="text" name="position" class="input-glass" placeholder="e.g. Senior Software Engineer" value="<?php echo htmlspecialchars($profile['position'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Industry Sector</label>
                                    <input type="text" name="industry" class="input-glass" placeholder="e.g. Technology" value="<?php echo htmlspecialchars($profile['industry'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Website Portfolio URL</label>
                                    <input type="url" name="website" class="input-glass" placeholder="https://myportfolio.io" value="<?php echo htmlspecialchars($profile['website'] ?? ''); ?>">
                                </div>
                            <?php else: ?>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Current Academic Year (1-4)</label>
                                    <input type="number" name="current_year" class="input-glass" min="1" max="4" value="<?php echo htmlspecialchars($profile['current_year'] ?? 1); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Cumulative CGPA (0.00 - 10.00)</label>
                                    <input type="number" name="cgpa" step="0.01" min="0" max="10" class="input-glass" placeholder="8.50" value="<?php echo htmlspecialchars($profile['cgpa'] ?? '0.00'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">GitHub URL</label>
                                    <input type="url" name="github" class="input-glass" placeholder="https://github.com/myname" value="<?php echo htmlspecialchars($profile['github'] ?? ''); ?>">
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">LinkedIn URL</label>
                                <input type="url" name="linkedin" class="input-glass" placeholder="https://linkedin.com/in/myname" value="<?php echo htmlspecialchars($profile['linkedin'] ?? ''); ?>">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Upload Avatar Picture</label>
                                <input type="file" name="profile_pic" accept="image/*" class="input-glass">
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Short Biography</label>
                                <textarea name="bio" class="input-glass" rows="4" required><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                            </div>

                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:1rem;">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
                        </div>
                    </form>
                </div>

            </div>

        </main>
    </div>
</div>

<script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

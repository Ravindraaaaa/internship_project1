<?php
ob_start();
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$uid = get_user_id();
$role = get_user_role();
$user_name = get_user_name();
$is_admin_user = is_admin();

$page_title = "Change Password";

// Handle Password Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        set_flash('error', 'All fields are required.');
    } elseif ($new_password !== $confirm_password) {
        set_flash('error', 'New password and confirmation password do not match.');
    } elseif (strlen($new_password) < 6) {
        set_flash('error', 'New password must be at least 6 characters long.');
    } else {
        try {
            // Fetch stored password hash
            if ($is_admin_user && isset($_SESSION['admin_id'])) {
                $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $stored_hash = $stmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                $stored_hash = $stmt->fetchColumn();
            }

            if (!$stored_hash || !password_verify($current_password, $stored_hash)) {
                set_flash('error', 'Your current password is incorrect.');
            } else {
                // Hash new password & update
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);

                if ($is_admin_user && isset($_SESSION['admin_id'])) {
                    $stmtUpdate = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $stmtUpdate->execute([$new_hash, $_SESSION['admin_id']]);
                }
                
                // Always update users table as well if account exists in users table
                $stmtUpdateUser = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpdateUser->execute([$new_hash, $uid]);

                set_flash('success', 'Password updated successfully! Next time you log in, please use your new password.');
                header('Location: change_password.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('error', 'An error occurred while updating your password: ' . $e->getMessage());
        }
    }
}

// Fetch avatar for sidebar/nav
$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
try {
    if ($role === 'alumni') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $pic = $stmtP->fetchColumn();
        if ($pic) $sidebar_avatar = get_avatar_url($pic);
    } elseif ($role === 'student') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $pic = $stmtP->fetchColumn();
        if ($pic) $sidebar_avatar = get_avatar_url($pic);
    }
} catch (Exception $e) {
    // Ignore error
}

$is_subfolder = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .pass-input-wrapper {
        position: relative;
    }
    .pass-input-wrapper input {
        padding-right: 2.75rem !important;
    }
    .pass-toggle-icon {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--theme-text-secondary);
        transition: color 0.2s ease;
        z-index: 5;
    }
    .pass-toggle-icon:hover {
        color: var(--theme-accent-purple);
    }
    .strength-meter {
        height: 6px;
        border-radius: 4px;
        background: rgba(255,255,255,0.08);
        overflow: hidden;
        margin-top: 0.5rem;
        transition: all 0.3s ease;
    }
    .strength-bar {
        height: 100%;
        width: 0%;
        border-radius: 4px;
        transition: width 0.3s ease, background-color 0.3s ease;
    }
    .security-tips-card {
        background: var(--theme-card);
        border: 1px solid var(--theme-border);
        border-radius: var(--border-radius-lg);
        padding: 1.5rem;
    }
    .tip-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
        font-size: 0.85rem;
        color: var(--theme-text-secondary);
    }
    .tip-item:last-child {
        margin-bottom: 0;
    }
    .tip-item i {
        color: #10b981;
        margin-top: 0.2rem;
    }
</style>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <?php render_sidebar('profile'); ?>

    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>

        <main class="dashboard-workspace">

            <!-- Page Header -->
            <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 1.65rem; font-weight: 800; color: var(--theme-text); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fa-solid fa-key" style="color: var(--theme-accent-purple);"></i> Account Security & Password
                    </h1>
                    <p style="color: var(--theme-text-secondary); font-size: 0.88rem;">Update your account authentication credentials safely.</p>
                </div>
                <div>
                    <a href="profile.php" class="btn btn-secondary btn-small" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-user-gear"></i> Back to My Profile
                    </a>
                </div>
            </div>

            <!-- Content Grid -->
            <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.75rem; align-items: start;">
                
                <!-- Main Form Card -->
                <div class="card-glass" style="border: 1px solid var(--theme-border); padding: 2.25rem; border-radius: var(--border-radius-lg); background: var(--theme-card);">
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.5rem; border-bottom: 1px solid var(--theme-border); padding-bottom: 0.85rem; color: var(--theme-text); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-lock" style="color: var(--theme-accent-blue);"></i> Change Password Form
                    </h3>

                    <form action="change_password.php" method="POST" id="changePasswordForm">
                        <input type="hidden" name="action" value="change_password">

                        <!-- Current Password -->
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="current_password" class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block; color: var(--theme-text);">
                                Current Password <span style="color: var(--accent-danger);">*</span>
                            </label>
                            <div class="pass-input-wrapper">
                                <input type="password" name="current_password" id="current_password" class="input-glass" style="width: 100%; padding: 0.75rem 1rem;" placeholder="Enter current password" required>
                                <i class="fa-regular fa-eye pass-toggle-icon" onclick="togglePassVisibility('current_password', this)"></i>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="new_password" class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block; color: var(--theme-text);">
                                New Password <span style="color: var(--accent-danger);">*</span>
                            </label>
                            <div class="pass-input-wrapper">
                                <input type="password" name="new_password" id="new_password" class="input-glass" style="width: 100%; padding: 0.75rem 1rem;" placeholder="At least 6 characters" required oninput="checkPasswordStrength(this.value)">
                                <i class="fa-regular fa-eye pass-toggle-icon" onclick="togglePassVisibility('new_password', this)"></i>
                            </div>
                            <div class="strength-meter">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.35rem;">
                                <span style="font-size: 0.75rem; color: var(--theme-text-secondary);" id="strengthText">Password strength: Empty</span>
                                <span style="font-size: 0.75rem; color: var(--theme-text-secondary);">Min 6 chars</span>
                            </div>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="form-group" style="margin-bottom: 2rem;">
                            <label for="confirm_password" class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block; color: var(--theme-text);">
                                Confirm New Password <span style="color: var(--accent-danger);">*</span>
                            </label>
                            <div class="pass-input-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" class="input-glass" style="width: 100%; padding: 0.75rem 1rem;" placeholder="Re-type new password" required oninput="checkPassMatch()">
                                <i class="fa-regular fa-eye pass-toggle-icon" onclick="togglePassVisibility('confirm_password', this)"></i>
                            </div>
                            <div id="matchNotice" style="font-size: 0.78rem; margin-top: 0.35rem; display: none;"></div>
                        </div>

                        <!-- Action buttons -->
                        <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--theme-border); padding-top: 1.25rem;">
                            <a href="profile.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Cancel</a>
                            <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fa-solid fa-shield-halved"></i> Update Password</button>
                        </div>
                    </form>
                </div>

                <!-- Right Security Tips Card -->
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    
                    <div class="security-tips-card">
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--theme-text); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-shield-virus" style="color: var(--theme-accent-purple);"></i> Password Guidelines
                        </h4>
                        
                        <div class="tip-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>Use at least 8 characters with a mix of upper & lowercase letters.</div>
                        </div>
                        <div class="tip-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>Include numbers (0-9) and special symbols (!@#$%^&*).</div>
                        </div>
                        <div class="tip-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>Do not use common words, birthdays, or public social usernames.</div>
                        </div>
                        <div class="tip-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>Ensure your password is unique and not reused on other websites.</div>
                        </div>
                    </div>

                    <!-- Quick Session Info -->
                    <div class="security-tips-card" style="background: rgba(139, 92, 246, 0.03); border-color: rgba(139, 92, 246, 0.2);">
                        <h4 style="font-size: 0.92rem; font-weight: 700; color: var(--theme-text); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-user-shield" style="color: #10b981;"></i> Account Info
                        </h4>
                        <p style="font-size: 0.8rem; color: var(--theme-text-secondary); line-height: 1.5; margin: 0;">
                            Logged in as: <strong style="color: var(--theme-text);"><?php echo htmlspecialchars($user_name); ?></strong><br>
                            Role: <strong style="color: var(--theme-accent-purple); text-transform: uppercase; font-size: 0.75rem;"><?php echo htmlspecialchars($role); ?></strong>
                        </p>
                    </div>

                </div>

            </div>

        </main>
    </div>
</div>

<script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>
<script>
function togglePassVisibility(inputId, iconEl) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        iconEl.classList.remove('fa-eye');
        iconEl.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        iconEl.classList.remove('fa-eye-slash');
        iconEl.classList.add('fa-eye');
    }
}

function checkPasswordStrength(password) {
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    
    if (!password) {
        bar.style.width = '0%';
        text.innerText = 'Password strength: Empty';
        text.style.color = 'var(--theme-text-secondary)';
        return;
    }
    
    let score = 0;
    if (password.length >= 6) score += 25;
    if (password.length >= 10) score += 25;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score += 25;
    if (/[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)) score += 25;
    
    bar.style.width = score + '%';
    
    if (score <= 25) {
        bar.style.backgroundColor = '#ef4444';
        text.innerText = 'Password strength: Weak';
        text.style.color = '#ef4444';
    } else if (score <= 50) {
        bar.style.backgroundColor = '#f59e0b';
        text.innerText = 'Password strength: Fair';
        text.style.color = '#f59e0b';
    } else if (score <= 75) {
        bar.style.backgroundColor = '#3b82f6';
        text.innerText = 'Password strength: Good';
        text.style.color = '#3b82f6';
    } else {
        bar.style.backgroundColor = '#10b981';
        text.innerText = 'Password strength: Strong 💪';
        text.style.color = '#10b981';
    }
    checkPassMatch();
}

function checkPassMatch() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    const notice = document.getElementById('matchNotice');
    
    if (!confirmPass) {
        notice.style.display = 'none';
        return;
    }
    
    notice.style.display = 'block';
    if (newPass === confirmPass) {
        notice.style.color = '#10b981';
        notice.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
    } else {
        notice.style.color = '#ef4444';
        notice.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

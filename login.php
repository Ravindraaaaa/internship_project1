<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security_helper.php';

$page_title = "Sign In";

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $login_error = 'Please fill in all credentials fields.';
    } else {
        // Check account lockout
        $locked_secs = is_account_locked($login_input);
        if ($locked_secs !== false) {
            $mins = ceil($locked_secs / 60);
            $login_error = "This account is temporarily locked due to failed login attempts. Try again in $mins minutes.";
        } else {
            // 1. Try checking admins table
            $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE email = ? OR username = ?");
            $stmtAdmin->execute([$login_input, $login_input]);
            $admin = $stmtAdmin->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Check if user account table is locked or has 2fa
                $stmtAdminUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmtAdminUser->execute([$admin['user_id']]);
                $adminUser = $stmtAdminUser->fetch();

                reset_failed_attempts($login_input);
                log_login($adminUser ? $adminUser['id'] : null, $login_input, 'success');

                // If 2FA enabled
                if ($adminUser && $adminUser['two_factor_secret'] === 'enabled') {
                    $_SESSION['2fa_pending'] = true;
                    $_SESSION['2fa_role'] = 'admin';
                    $_SESSION['2fa_admin_id'] = $admin['id'];
                    $_SESSION['2fa_admin_name'] = $admin['name'];
                    $_SESSION['2fa_admin_role'] = $admin['role'];
                    $_SESSION['2fa_user_id'] = $adminUser['id'];
                    header('Location: two_factor.php');
                    exit;
                }

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['user_id'] = $admin['user_id'];
                
                if (!empty($_POST['remember'])) {
                    set_remember_me_cookie($adminUser['id']);
                }
                
                set_flash('success', 'Logged in successfully as Super Admin!');
                header('Location: dashboard.php');
                exit;
            }

            // 2. Try checking regular users table
            $stmtUser = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmtUser->execute([$login_input, $login_input]);
            $user = $stmtUser->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'rejected') {
                    register_failed_attempt($login_input);
                    log_login($user['id'], $login_input, 'failed');
                    $login_error = 'Your registration request has been rejected by the administrator.';
                } elseif ($user['status'] === 'blocked') {
                    register_failed_attempt($login_input);
                    log_login($user['id'], $login_input, 'failed');
                    $login_error = 'Your account has been blocked by the administrator.';
                } else {
                    reset_failed_attempts($login_input);
                    log_login($user['id'], $login_input, 'success');

                    // If 2FA enabled
                    if ($user['two_factor_secret'] === 'enabled') {
                        $_SESSION['2fa_pending'] = true;
                        $_SESSION['2fa_role'] = 'user';
                        $_SESSION['2fa_user_id'] = $user['id'];
                        $_SESSION['2fa_user_name'] = $user['name'];
                        $_SESSION['2fa_user_role'] = $user['role'];
                        $_SESSION['2fa_user_status'] = $user['status'];
                        header('Location: two_factor.php');
                        exit;
                    }

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_status'] = $user['status'];

                    if ($user['role'] === 'admin') {
                        // Ensure they have a record in the admins table
                        $stmtCheckAdmin = $pdo->prepare("SELECT id, role FROM admins WHERE user_id = ?");
                        $stmtCheckAdmin->execute([$user['id']]);
                        $adminRow = $stmtCheckAdmin->fetch();
                        if (!$adminRow) {
                            $stmtInsAdmin = $pdo->prepare("INSERT INTO admins (user_id, username, name, email, password, role) VALUES (?, ?, ?, ?, ?, 'superadmin')");
                            $stmtInsAdmin->execute([$user['id'], $user['username'], $user['name'], $user['email'], $user['password']]);
                            $adminId = $pdo->lastInsertId();
                            $adminRole = 'superadmin';
                        } else {
                            $adminId = $adminRow['id'];
                            $adminRole = $adminRow['role'];
                        }
                        $_SESSION['admin_id'] = $adminId;
                        $_SESSION['admin_name'] = $user['name'];
                        $_SESSION['admin_role'] = $adminRole;
                    }

                    if (!empty($_POST['remember'])) {
                        set_remember_me_cookie($user['id']);
                    }

                    if ($user['status'] === 'pending') {
                        set_flash('info', 'Logged in! Note: Your profile is pending admin approval.');
                    } else {
                        set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
                    }
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                register_failed_attempt($login_input);
                log_login(null, $login_input, 'failed');
                $login_error = 'Invalid email/username or password details.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .login-wrapper {
        display: flex;
        min-height: 95vh;
        align-items: center;
        justify-content: center;
        padding: 4rem 2rem;
    }
    .login-card {
        width: 100%;
        max-width: 440px;
    }
    .login-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    .login-header h1 {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    .login-header p {
        color: var(--theme-text-secondary);
        font-size: 0.9rem;
    }
    .checkbox-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: var(--theme-text-secondary);
        cursor: pointer;
    }
    .checkbox-container input {
        cursor: pointer;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-6px); }
        40%, 80% { transform: translateX(6px); }
    }
</style>

<div class="login-wrapper">
    <div class="card-glass login-card fade-in">
        <div class="login-header">
            <a href="index.php" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <h1>Welcome Back</h1>
            <p>Access your portal account dashboard</p>
        </div>

        <?php if (!empty($login_error)): ?>
            <div class="card-glass" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25); color: #f87171; padding: 0.85rem 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem; display: flex; align-items: center; gap: 0.65rem; animation: shake 0.4s ease; border: 1px solid rgba(239, 68, 68, 0.3);">
                <i class="fa-solid fa-circle-xmark" style="color: #ef4444; font-size: 1rem;"></i> 
                <span><?php echo htmlspecialchars($login_error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="login.php" method="POST">
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="login_input" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Email or Username</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-envelope" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="text" name="login_input" id="login_input" class="input-glass" style="padding-left: 2.85rem;" placeholder="name@example.com" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="password" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Password</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="password" name="password" id="password" class="input-glass" style="padding-left: 2.85rem;" placeholder="••••••••" required>
                    <!-- Toggle password visibility button icon -->
                    <i class="fa-solid fa-eye-slash" id="toggle-pass-visibility" style="position: absolute; right: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary); cursor: pointer;" title="Toggle Password Visibility"></i>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <label class="checkbox-container">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <span>Remember me</span>
                </label>
                <a href="forgot_password.php" style="font-size: 0.85rem; color: var(--theme-accent-blue); font-weight: 500;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--theme-text-secondary); display: flex; flex-direction: column; gap: 0.5rem;">
            <div>New to the portal? <a href="register.php" style="color: var(--theme-accent-blue); font-weight: 600;">Sign Up</a></div>
            <div style="border-top: 1px solid var(--theme-border); margin-top: 0.75rem; padding-top: 0.75rem;">
                Are you an Administrator? <a href="admin/admin_login.php" style="color: var(--theme-accent-purple); font-weight: 600;">Admin Login</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Invert password visibility toggle
        const toggleBtn = document.getElementById('toggle-pass-visibility');
        const passInput = document.getElementById('password');
        
        if (toggleBtn && passInput) {
            toggleBtn.addEventListener('click', function() {
                if (passInput.type === 'password') {
                    passInput.type = 'text';
                    this.className = 'fa-solid fa-eye';
                } else {
                    passInput.type = 'password';
                    this.className = 'fa-solid fa-eye-slash';
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

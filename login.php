<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';

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
        set_flash('error', 'Please fill in all credentials fields.');
    } else {
        // 1. Try checking admins table
        $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE email = ? OR username = ?");
        $stmtAdmin->execute([$login_input, $login_input]);
        $admin = $stmtAdmin->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];
            
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
                set_flash('error', 'Your registration request has been rejected by the administrator.');
            } elseif ($user['status'] === 'blocked') {
                set_flash('error', 'Your account has been blocked by the administrator.');
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_status'] = $user['status'];

                if ($user['status'] === 'pending') {
                    set_flash('info', 'Logged in! Note: Your profile is pending admin approval.');
                } else {
                    set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
                }
                header('Location: dashboard.php');
                exit;
            }
        } else {
            set_flash('error', 'Invalid email/username or password details.');
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
                    <input type="checkbox" id="remember">
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

<?php
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = "Administrator Login";

// Redirect to dashboard if admin already logged in
if (is_logged_in() && is_admin()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        set_flash('error', 'Please fill in both admin credentials fields.');
    } else {
        try {
            // Check only the admins table
            $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE email = ? OR username = ?");
            $stmtAdmin->execute([$login_input, $login_input]);
            $admin = $stmtAdmin->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['user_id'] = $admin['user_id'];
                
                set_flash('success', 'Administrator Session Authorized!');
                header('Location: dashboard.php');
                exit;
            } else {
                set_flash('error', 'Access Denied: Invalid Administrator Credentials.');
            }
        } catch (Exception $e) {
            set_flash('error', 'Auth query failed: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .admin-login-wrapper {
        display: flex;
        min-height: 95vh;
        align-items: center;
        justify-content: center;
        padding: 5rem 2rem;
        position: relative;
    }
    .admin-card {
        width: 100%;
        max-width: 460px;
        background: rgba(15, 23, 42, 0.45);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    }
    body.theme-light .admin-card {
        background: rgba(255, 255, 255, 0.75);
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.06);
    }
    .admin-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }
    .admin-shield-icon {
        font-size: 3.5rem;
        background: linear-gradient(135deg, #eab308 0%, #ef4444 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 1rem;
        filter: drop-shadow(0 0 10px rgba(234, 179, 8, 0.25));
    }
    .admin-header h1 {
        font-size: 2.2rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        background: linear-gradient(135deg, #ffffff 0%, #9ca3af 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    body.theme-light .admin-header h1 {
        background: linear-gradient(135deg, #0f172a 0%, #475569 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .admin-header p {
        color: var(--theme-text-secondary);
        font-size: 0.9rem;
    }
    .admin-form-group {
        margin-bottom: 1.5rem;
    }
    .admin-label {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--theme-text-secondary);
        margin-bottom: 0.5rem;
        display: block;
    }
</style>

<div class="admin-login-wrapper">
    
    <!-- Top-right Settings Button -->
    <div style="position: absolute; top: 2rem; right: 2rem; z-index: 10;">
        <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open style parameters">
            <i data-lucide="palette" style="width:20px;height:20px;"></i>
        </button>
    </div>

    <!-- Central glassmorphism admin panel login card -->
    <div class="card-glass admin-card fade-in" style="transform: scale(0.95); opacity: 0;">
        
        <div class="admin-header">
            <div class="admin-shield-icon">
                <i data-lucide="shield-check" style="width: 55px; height: 55px; stroke-width: 1.5;"></i>
            </div>
            <h1>Admin Panel</h1>
            <p>Secure Administrator Access</p>
        </div>

        <form action="admin_login.php" id="admin-form" method="POST">
            
            <div class="admin-form-group">
                <label for="username" class="admin-label">Admin Username</label>
                <div style="position: relative;">
                    <i data-lucide="user" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--theme-text-secondary);"></i>
                    <input type="text" name="username" id="username" class="input-glass" style="padding-left: 2.85rem;" placeholder="e.g. admin" required>
                </div>
            </div>

            <div class="admin-form-group">
                <label for="password" class="admin-label">Admin Password</label>
                <div style="position: relative;">
                    <i data-lucide="lock" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--theme-text-secondary);"></i>
                    <input type="password" name="password" id="password" class="input-glass" style="padding-left: 2.85rem;" placeholder="••••••••" required>
                    <i data-lucide="eye-off" id="toggle-admin-pass" style="position: absolute; right: 1.1rem; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--theme-text-secondary); cursor: pointer;" title="Toggle Password"></i>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <label class="switch-container" style="display: flex; align-items: center; gap: 0.5rem; width: auto; height: auto;">
                    <input type="checkbox" id="remember">
                    <span class="switch-slider" style="position: static; display: inline-block; width: 40px; height: 20px;"></span>
                    <span style="font-size: 0.85rem; color: var(--theme-text-secondary); font-weight: 500;">Remember me</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" id="admin-submit-btn" style="width: 100%; padding: 0.9rem; font-size: 1rem; background: linear-gradient(135deg, #ea580c 0%, #ea580c 100%);">
                <i data-lucide="shield-alert" style="width: 18px; height: 18px; vertical-align: middle;"></i> <span>Authenticate Admin</span>
            </button>
            
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem;">
            <a href="../index.php" style="color: var(--theme-text-secondary); font-weight: 500;"><i class="fa-solid fa-arrow-left"></i> Return to Public Site</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. GSAP entrance animations for login card
        gsap.to('.admin-card', {
            scale: 1,
            opacity: 1,
            duration: 0.8,
            ease: 'back.out(1.2)',
            delay: 0.1
        });

        // 2. Password visibility switch (using event delegation to handle Lucide icon replacement)
        document.addEventListener('click', function(e) {
            const toggleBtn = e.target.closest('#toggle-admin-pass');
            if (toggleBtn) {
                const passInput = document.getElementById('password');
                if (passInput) {
                    if (passInput.type === 'password') {
                        passInput.type = 'text';
                        toggleBtn.setAttribute('data-lucide', 'eye');
                    } else {
                        passInput.type = 'password';
                        toggleBtn.setAttribute('data-lucide', 'eye-off');
                    }
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            }
        });

        // 3. Button loading state mock visual
        const form = document.getElementById('admin-form');
        const submitBtn = document.getElementById('admin-submit-btn');
        if (form && submitBtn) {
            form.addEventListener('submit', () => {
                submitBtn.disabled = true;
                submitBtn.querySelector('span').textContent = 'Authenticating Session...';
                submitBtn.style.opacity = '0.85';
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

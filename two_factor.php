<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security_helper.php';

$page_title = "Two-Factor Verification";

if (!isset($_SESSION['2fa_pending']) || $_SESSION['2fa_pending'] !== true) {
    header('Location: login.php');
    exit;
}

$simulated_code = "123456"; // Standard mock code for ease of demonstration
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error = "Please enter the verification code.";
    } elseif ($code === $simulated_code) {
        // Complete the authentication
        $role = $_SESSION['2fa_role'];
        
        if ($role === 'admin') {
            $_SESSION['admin_id'] = $_SESSION['2fa_admin_id'];
            $_SESSION['admin_name'] = $_SESSION['2fa_admin_name'];
            $_SESSION['admin_role'] = $_SESSION['2fa_admin_role'];
            log_activity($_SESSION['2fa_user_id'], 'login_2fa_success', 'Super Admin logged in with 2FA.');
            set_flash('success', 'Logged in successfully as Super Admin!');
        } else {
            $_SESSION['user_id'] = $_SESSION['2fa_user_id'];
            $_SESSION['user_name'] = $_SESSION['2fa_user_name'];
            $_SESSION['user_role'] = $_SESSION['2fa_user_role'];
            $_SESSION['user_status'] = $_SESSION['2fa_user_status'];
            log_activity($_SESSION['2fa_user_id'], 'login_2fa_success', 'User logged in with 2FA.');
            
            if ($_SESSION['2fa_user_status'] === 'pending') {
                set_flash('info', 'Logged in! Note: Your profile is pending admin approval.');
            } else {
                set_flash('success', 'Welcome back, ' . htmlspecialchars($_SESSION['2fa_user_name']) . '!');
            }
        }
        
        // Clean 2FA temp variables
        unset($_SESSION['2fa_pending']);
        unset($_SESSION['2fa_role']);
        unset($_SESSION['2fa_admin_id']);
        unset($_SESSION['2fa_admin_name']);
        unset($_SESSION['2fa_admin_role']);
        unset($_SESSION['2fa_user_id']);
        unset($_SESSION['2fa_user_name']);
        unset($_SESSION['2fa_user_role']);
        unset($_SESSION['2fa_user_status']);
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid verification code. Please check and try again.";
        if (isset($_SESSION['2fa_user_id'])) {
            log_activity($_SESSION['2fa_user_id'], 'login_2fa_failed', 'Failed 2FA code attempt.');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="login-wrapper" style="display: flex; min-height: 90vh; align-items: center; justify-content: center; padding: 4rem 2rem;">
    <div class="login-card card-glass" style="width: 100%; max-width: 440px; padding: 2.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border); box-shadow: var(--shadow-soft);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <i class="fa-solid fa-shield-halved" style="font-size: 3rem; background: var(--theme-accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 1rem;"></i>
            <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--theme-text);">Two-Factor Auth</h2>
            <p style="color: var(--theme-text-secondary); font-size: 0.88rem;">To secure your account, please enter the verification code.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="card-glass" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25); color: #f87171; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Simulated code hint -->
        <div class="card-glass" style="background: rgba(59, 130, 246, 0.08); border-color: rgba(59, 130, 246, 0.25); color: var(--theme-accent-blue); padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem; text-align: center;">
            <i class="fa-solid fa-circle-info"></i> Simulated 2FA active. Use code: <strong style="font-size: 1.05rem; letter-spacing: 2px; color: #ffffff;"><?php echo $simulated_code; ?></strong>
        </div>

        <form action="two_factor.php" method="POST" autocomplete="off">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="code" style="display: block; font-size: 0.88rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--theme-text-secondary);">Verification Code</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-key" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="text" name="code" id="code" class="input-glass" style="width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); border-radius: var(--border-radius-sm); color: var(--theme-text); font-size: 1.1rem; text-align: center; letter-spacing: 5px; font-weight: bold;" placeholder="••••••" maxlength="6" required autofocus>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; border-radius: var(--border-radius-sm); font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border: none; cursor: pointer; background: var(--theme-accent-gradient); color: #ffffff;">
                <i class="fa-solid fa-circle-check"></i> Verify and Log In
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="logout.php" style="color: var(--theme-text-secondary); font-size: 0.85rem; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-text)'" onmouseout="this.style.color='var(--theme-text-secondary)'"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

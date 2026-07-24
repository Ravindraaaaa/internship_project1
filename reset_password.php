<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';

$page_title = "Reset Password";

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$valid_token = false;
$email = '';

if (empty($token)) {
    set_flash('error', 'Token parameter is missing or invalid.');
    header('Location: login.php');
    exit;
}

try {
    // Check if token exists and is not expired
    $stmtCheck = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmtCheck->execute([$token]);
    $reset_req = $stmtCheck->fetch();

    if ($reset_req) {
        $valid_token = true;
        $email = $reset_req['email'];
    } else {
        set_flash('error', 'Password reset token has expired or is invalid.');
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    set_flash('error', 'Token validation query failed.');
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        set_flash('error', 'Please enter your new password.');
    } elseif ($password !== $confirm_password) {
        set_flash('error', 'Passwords do not match.');
    } else {
        try {
            $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
            
            $pdo->beginTransaction();
            
            // 1. Update users table if exists
            $stmtUser = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmtUser->execute([$hashed_pass, $email]);

            // 2. Update admins table if exists
            $stmtAdmin = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
            $stmtAdmin->execute([$hashed_pass, $email]);

            // 3. Clear reset tokens
            $stmtClear = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmtClear->execute([$email]);

            $pdo->commit();
            set_flash('success', 'Your password has been reset successfully! Please log in.');
            header('Location: login.php');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Update password query failed: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="login-wrapper" style="display: flex; min-height: 85vh; align-items: center; justify-content: center; padding: 4rem 2rem;">
    <div class="card-glass login-card fade-in" style="width: 100%; max-width: 480px; padding: 2.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border); box-shadow: var(--shadow-soft);">
        <div class="login-header" style="text-align: center; margin-bottom: 2rem;">
            <a href="index.php" class="logo" style="justify-content: center; margin-bottom: 1rem; text-decoration: none; font-size: 1.5rem; font-weight: 700; color: var(--theme-text); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-graduation-cap" style="color: var(--theme-accent-purple);"></i> AlumniNet
            </a>
            <h1 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--theme-text);">Set New Password</h1>
            <p style="color: var(--theme-text-secondary); font-size: 0.88rem;">Update password for account linked to <strong style="color: var(--theme-text);"><?php echo htmlspecialchars($email); ?></strong></p>
        </div>

        <form action="reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="password" class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block; color: var(--theme-text);">New Password</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="password" name="password" id="password" class="input-glass" style="width: 100%; padding: 0.8rem 1rem 0.8rem 2.85rem; background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); border-radius: var(--border-radius-sm); color: var(--theme-text); font-size: 0.95rem;" placeholder="••••••••" required autofocus>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="confirm_password" class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block; color: var(--theme-text);">Confirm Password</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="password" name="confirm_password" id="confirm_password" class="input-glass" style="width: 100%; padding: 0.8rem 1rem 0.8rem 2.85rem; background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); border-radius: var(--border-radius-sm); color: var(--theme-text); font-size: 0.95rem;" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border: none; cursor: pointer; background: var(--theme-accent-gradient); color: #ffffff; border-radius: var(--border-radius-sm);">
                <i class="fa-solid fa-key"></i> Update Password
            </button>
        </form>

        <div style="margin-top: 1.75rem; text-align: center; font-size: 0.88rem; color: var(--theme-text-secondary);">
            Remembered your password? <a href="login.php" style="color: var(--theme-accent-blue); font-weight: 600; text-decoration: none;">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

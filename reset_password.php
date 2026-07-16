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

<div class="login-wrapper">
    <div class="card-glass login-card fade-in" style="max-width: 480px;">
        <div class="login-header">
            <a href="index.php" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <h1>Set New Password</h1>
            <p>Update password for account linked to <?php echo htmlspecialchars($email); ?></p>
        </div>

        <form action="reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="password" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">New Password</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="password" name="password" id="password" class="input-glass" style="padding-left: 2.85rem;" placeholder="••••••••" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="confirm_password" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Confirm Password</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="password" name="confirm_password" id="confirm_password" class="input-glass" style="padding-left: 2.85rem;" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem;">
                <i class="fa-solid fa-key"></i> Update Password
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

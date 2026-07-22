<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security_helper.php';
require_once __DIR__ . '/includes/mailer_helper.php';

$page_title = "Forgot Password";

// Ensure password_resets table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    // ignore
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $token_csrf = $_POST['csrf_token'] ?? '';
    if (!check_csrf($token_csrf)) {
        set_flash('error', 'CSRF verification failed. Please try again.');
    } else {
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please enter a valid email address.');
        } else {
            try {
                // Case-insensitive verification if email exists in users or admins
                $stmtCheck = $pdo->prepare("SELECT email FROM users WHERE LOWER(email) = LOWER(?) UNION SELECT email FROM admins WHERE LOWER(email) = LOWER(?)");
                $stmtCheck->execute([$email, $email]);
                $exists = $stmtCheck->fetch();

                if ($exists) {
                    $matched_email = $exists['email'];

                    // Generate secure token
                    $token = bin2hex(random_bytes(32));
                    
                    // Clear previous tokens for this email
                    $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE LOWER(email) = LOWER(?)");
                    $stmtDel->execute([$matched_email]);

                    // Save reset token in DB
                    $stmtInsert = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                    $stmtInsert->execute([$matched_email, $token]);

                    // Dispatch real-time SMTP Email via DB connected helper
                    $mail_res = send_password_reset_email($matched_email, $token);

                    if ($mail_res['success']) {
                        $flash_msg = 'A password reset link has been sent to ' . htmlspecialchars($matched_email) . '. Please check your inbox or spam folder.';
                        set_flash('success', $flash_msg);
                    } else {
                        throw new Exception($mail_res['error'] ?? 'Unknown SMTP error.');
                    }
                } else {
                    set_flash('error', 'This email address is not registered.');
                }
            } catch (Exception $e) {
                set_flash('error', 'Reset request failed: ' . $e->getMessage());
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="login-wrapper" style="display: flex; min-height: 90vh; align-items: center; justify-content: center; padding: 4rem 2rem;">
    <div class="card-glass login-card fade-in" style="width: 100%; max-width: 480px; padding: 2.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border); box-shadow: var(--shadow-soft);">
        <div class="login-header" style="text-align: center; margin-bottom: 2rem;">
            <a href="index.php" class="logo" style="justify-content: center; margin-bottom: 1rem; text-decoration: none; font-size: 1.5rem; font-weight: 700; color: var(--theme-text); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-graduation-cap" style="color: var(--theme-accent-purple);"></i> AlumniNet
            </a>
            <h1 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--theme-text);">Reset Password</h1>
            <p style="color: var(--theme-text-secondary); font-size: 0.88rem;">Enter your email address to receive a password reset link</p>
        </div>

        <form action="forgot_password.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="form-group" style="margin-bottom: 1.75rem;">
                <label for="email" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block; color: var(--theme-text);">Email Address</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-envelope" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="email" name="email" id="email" class="input-glass" style="width: 100%; padding: 0.8rem 1rem 0.8rem 2.85rem; background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); border-radius: var(--border-radius-sm); color: var(--theme-text); font-size: 0.95rem;" placeholder="name@example.com" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border: none; cursor: pointer; background: var(--theme-accent-gradient); color: #ffffff; border-radius: var(--border-radius-sm);">
                <i class="fa-solid fa-paper-plane"></i> Send Password Reset Link
            </button>
        </form>

        <div style="margin-top: 1.75rem; text-align: center; font-size: 0.88rem; color: var(--theme-text-secondary);">
            Remembered your password? <a href="login.php" style="color: var(--theme-accent-blue); font-weight: 600; text-decoration: none;">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

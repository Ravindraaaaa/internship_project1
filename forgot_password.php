<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';

$page_title = "Forgot Password";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        set_flash('error', 'Please enter your email address.');
    } else {
        try {
            // Verify if email exists in users or admins
            $stmtCheck = $pdo->prepare("SELECT email FROM users WHERE email = ? UNION SELECT email FROM admins WHERE email = ?");
            $stmtCheck->execute([$email, $email]);
            $exists = $stmtCheck->fetch();

            if ($exists) {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                
                // Save reset token in DB
                $stmtInsert = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                $stmtInsert->execute([$email, $token]);

                // Simulate email delivery for local XAMPP environment
                $simulated_link = "reset_password.php?token=" . $token;
                set_flash('success', 'Reset link generated successfully! (SMTP Mock): <a href="' . $simulated_link . '" style="color:var(--theme-accent-blue); font-weight:600; text-decoration:underline;">Click here to reset your password</a>');
            } else {
                set_flash('error', 'No account found with this email address.');
            }
        } catch (Exception $e) {
            set_flash('error', 'Reset failed: ' . $e->getMessage());
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
            <h1>Reset Password</h1>
            <p>Enter your email to receive a password reset link</p>
        </div>

        <form action="forgot_password.php" method="POST">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="email" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Email Address</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-envelope" style="position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                    <input type="email" name="email" id="email" class="input-glass" style="padding-left: 2.85rem;" placeholder="name@example.com" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--theme-text-secondary);">
            Remembered your password? <a href="login.php" style="color: var(--theme-accent-blue); font-weight: 600;">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

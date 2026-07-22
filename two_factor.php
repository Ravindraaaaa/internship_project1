<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security_helper.php';

$page_title = "Two-Factor Verification";

if (!isset($_SESSION['2fa_pending']) || $_SESSION['2fa_pending'] !== true) {
    header('Location: login.php');
    exit;
}

// Generate dynamic OTP code if not initialized
if (empty($_SESSION['2fa_otp_code']) || empty($_SESSION['2fa_otp_expires'])) {
    $_SESSION['2fa_otp_code'] = sprintf('%06d', mt_rand(100000, 999999));
    $_SESSION['2fa_otp_expires'] = time() + 300; // 5 mins
    $_SESSION['2fa_attempts'] = 0;
    
    // Dispatch real-time 2FA email
    $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['2fa_user_id']]);
    $uEmail = $stmtUser->fetchColumn();
    if ($uEmail) {
        send_2fa_otp_email($uEmail, $_SESSION['2fa_otp_code']);
    }
}

$simulated_code = $_SESSION['2fa_otp_code'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d1 = $_POST['d1'] ?? '';
    $d2 = $_POST['d2'] ?? '';
    $d3 = $_POST['d3'] ?? '';
    $d4 = $_POST['d4'] ?? '';
    $d5 = $_POST['d5'] ?? '';
    $d6 = $_POST['d6'] ?? '';
    
    $code = trim($d1 . $d2 . $d3 . $d4 . $d5 . $d6);
    if (empty($code)) {
        $code = trim($_POST['code'] ?? '');
    }

    if (empty($code)) {
        $error = "Please enter the 6-digit verification code.";
    } elseif (time() > $_SESSION['2fa_otp_expires']) {
        $error = "OTP expired. Click 'Resend 2FA Code' below.";
    } elseif ($code === $_SESSION['2fa_otp_code']) {
        // Complete the authentication
        $role = $_SESSION['2fa_role'];
        
        if ($role === 'admin') {
            $_SESSION['admin_id'] = $_SESSION['2fa_admin_id'];
            $_SESSION['admin_name'] = $_SESSION['2fa_admin_name'];
            $_SESSION['admin_role'] = $_SESSION['2fa_admin_role'];
            $_SESSION['user_id'] = $_SESSION['2fa_user_id'];
            log_activity($_SESSION['2fa_user_id'], 'login_2fa_success', 'Super Admin logged in with 2FA.');
            set_flash('success', 'Logged in successfully as Super Admin!');
        } else {
            $_SESSION['user_id'] = $_SESSION['2fa_user_id'];
            $_SESSION['user_name'] = $_SESSION['2fa_user_name'];
            $_SESSION['user_role'] = $_SESSION['2fa_user_role'];
            $_SESSION['user_status'] = $_SESSION['2fa_user_status'];

            if ($_SESSION['2fa_user_role'] === 'admin') {
                $stmtCheckAdmin = $pdo->prepare("SELECT id, role FROM admins WHERE user_id = ?");
                $stmtCheckAdmin->execute([$_SESSION['2fa_user_id']]);
                $adminRow = $stmtCheckAdmin->fetch();
                if (!$adminRow) {
                    $stmtUser = $pdo->prepare("SELECT username, email, password FROM users WHERE id = ?");
                    $stmtUser->execute([$_SESSION['2fa_user_id']]);
                    $udata = $stmtUser->fetch();

                    $stmtInsAdmin = $pdo->prepare("INSERT INTO admins (user_id, username, name, email, password, role) VALUES (?, ?, ?, ?, ?, 'superadmin')");
                    $stmtInsAdmin->execute([$_SESSION['2fa_user_id'], $udata['username'], $_SESSION['2fa_user_name'], $udata['email'], $udata['password']]);
                    $adminId = $pdo->lastInsertId();
                    $adminRole = 'superadmin';
                } else {
                    $adminId = $adminRow['id'];
                    $adminRole = $adminRow['role'];
                }
                $_SESSION['admin_id'] = $adminId;
                $_SESSION['admin_name'] = $_SESSION['2fa_user_name'];
                $_SESSION['admin_role'] = $adminRole;
            }

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
        unset($_SESSION['2fa_otp_code']);
        unset($_SESSION['2fa_otp_expires']);
        
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

<style>
    .otp-wrapper {
        display: flex;
        min-height: 90vh;
        align-items: center;
        justify-content: center;
        padding: 4rem 2rem;
    }
    .otp-card {
        width: 100%;
        max-width: 480px;
        padding: 2.5rem;
        border-radius: var(--border-radius-lg);
        background: var(--theme-card);
        border: 1px solid var(--theme-border);
        box-shadow: var(--shadow-soft);
        text-align: center;
    }
    .otp-inputs {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        margin: 2rem 0;
    }
    .otp-digit {
        width: 50px;
        height: 60px;
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.03);
        border: 1.5px solid var(--theme-border);
        color: var(--theme-text);
        transition: all 0.2s ease;
    }
    .otp-digit:focus {
        border-color: var(--theme-accent-purple);
        box-shadow: 0 0 15px rgba(168, 85, 247, 0.3);
        outline: none;
        background: rgba(168, 85, 247, 0.05);
    }
</style>

<div class="otp-wrapper">
    <div class="otp-card card-glass fade-in">
        <div style="margin-bottom: 1.5rem;">
            <i class="fa-solid fa-shield-halved" style="font-size: 3rem; background: var(--theme-accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 1rem;"></i>
            <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--theme-text);">Two-Factor Verification</h2>
            <p style="color: var(--theme-text-secondary); font-size: 0.88rem;">To secure your account, please enter the 6-digit 2FA code.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="card-glass" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25); color: #f87171; padding: 0.85rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem; justify-content: center;">
                <i class="fa-solid fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Simulated code hint -->
        <div class="card-glass" style="display: none; background: rgba(59, 130, 246, 0.08); border-color: rgba(59, 130, 246, 0.25); color: var(--theme-accent-blue); padding: 0.85rem 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem;">
            <i class="fa-solid fa-paper-plane" style="margin-right: 0.4rem;"></i> [DEMO SIMULATION] 2FA OTP Code: 
            <strong id="demo-2fa-display" style="font-size: 1.15rem; letter-spacing: 3px; color: #ffffff; margin-left: 0.3rem;"><?php echo htmlspecialchars($simulated_code); ?></strong>
        </div>

        <form action="two_factor.php" method="POST" autocomplete="off">
            <div class="otp-inputs">
                <input type="text" name="d1" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required autofocus>
                <input type="text" name="d2" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <input type="text" name="d3" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <input type="text" name="d4" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <input type="text" name="d5" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <input type="text" name="d6" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; border: none; cursor: pointer; background: var(--theme-accent-gradient); color: #ffffff;">
                <i class="fa-solid fa-circle-check"></i> Verify and Log In
            </button>
        </form>

        <div style="margin-top: 1rem;">
            <button type="button" id="btn-resend-2fa" onclick="resend2FAOtp()" class="btn btn-secondary btn-small" style="font-size: 0.82rem; cursor: pointer;">
                <i class="fa-solid fa-rotate-right"></i> Resend 2FA Code
            </button>
        </div>

        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="logout.php" style="color: var(--theme-text-secondary); font-size: 0.85rem; text-decoration: none; transition: color 0.2s;"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</div>

<script>
    const inputs = document.querySelectorAll('.otp-digit');
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const val = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = val;
            if (val && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
            if (pasteData.length >= 6) {
                for (let i = 0; i < 6; i++) {
                    inputs[i].value = pasteData[i] || '';
                }
                inputs[5].focus();
            }
        });
    });

    function resend2FAOtp() {
        const btn = document.getElementById('btn-resend-2fa');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

        const formData = new FormData();
        formData.append('action', 'resend_2fa_otp');

        fetch('api/send_otp.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('demo-2fa-display').textContent = data.demo_otp;
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Resend 2FA Code';
                alert(data.message);
                inputs[0].focus();
            } else {
                alert(data.message || 'Failed to resend 2FA OTP.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Resend 2FA Code';
            }
        })
        .catch(err => {
            alert('Network error while resending code.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Resend 2FA Code';
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

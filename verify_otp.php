<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security_helper.php';

$page_title = "Verify OTP";

// Check if there is an active OTP verification session
if (!isset($_SESSION['otp_verify']) || $_SESSION['otp_verify']['type'] !== 'signup') {
    header('Location: register.php');
    exit;
}

$otp_data = $_SESSION['otp_verify'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $token = $_POST['csrf_token'] ?? '';
    if (!check_csrf($token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Collect code from segmented inputs or single field
        $d1 = $_POST['d1'] ?? '';
        $d2 = $_POST['d2'] ?? '';
        $d3 = $_POST['d3'] ?? '';
        $d4 = $_POST['d4'] ?? '';
        $d5 = $_POST['d5'] ?? '';
        $d6 = $_POST['d6'] ?? '';
        
        $submitted_code = trim($d1 . $d2 . $d3 . $d4 . $d5 . $d6);
        if (empty($submitted_code)) {
            $submitted_code = trim($_POST['otp_code'] ?? '');
        }

        // Check expiration
        if (time() > $_SESSION['otp_verify']['expires_at']) {
            $error = 'OTP has expired! Click "Resend OTP" to receive a new code.';
        } elseif ($_SESSION['otp_verify']['attempts'] >= 5) {
            $error = 'Maximum verification attempts exceeded. Please restart registration.';
            unset($_SESSION['otp_verify']);
        } elseif ($submitted_code !== $_SESSION['otp_verify']['code']) {
            $_SESSION['otp_verify']['attempts']++;
            $remaining = 5 - $_SESSION['otp_verify']['attempts'];
            $error = "Incorrect OTP code. $remaining attempt(s) remaining.";
        } else {
            // OTP VERIFIED SUCCESSFULLY -> CREATE USER IN DATABASE
            $uData = $_SESSION['otp_verify']['user_data'];
            
            try {
                // Re-verify email uniqueness before inserting
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmtCheck->execute([$uData['email']]);
                if ($stmtCheck->fetch()) {
                    $error = 'This email address was already registered while verifying.';
                    unset($_SESSION['otp_verify']);
                } else {
                    $pdo->beginTransaction();

                    // Insert User record
                    $stmtInsert = $pdo->prepare("INSERT INTO users (name, email, username, password, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtInsert->execute([
                        $uData['name'],
                        $uData['email'],
                        $uData['username'],
                        $uData['password'],
                        $uData['phone'],
                        $uData['role'],
                        $uData['status']
                    ]);
                    $new_user_id = $pdo->lastInsertId();

                    // Insert Profile record
                    if ($uData['role'] === 'student') {
                        $stmtProfile = $pdo->prepare("INSERT INTO student_profiles (user_id, current_year, course, bio, profile_pic) VALUES (?, 1, ?, ?, ?)");
                        $stmtProfile->execute([$new_user_id, $uData['course'], 'Registered Student.', $uData['profile_pic_path']]);
                    } else {
                        $stmtProfile = $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, course, bio, profile_pic) VALUES (?, ?, ?, ?, ?)");
                        $stmtProfile->execute([$new_user_id, $uData['grad_year'], $uData['course'], 'Registered Alumnus.', $uData['profile_pic_path']]);
                    }

                    $pdo->commit();
                    log_activity($new_user_id, 'registration_otp_verified', 'User verified phone/email OTP and registered successfully as ' . $uData['role']);

                    // Dispatch automatic in-app notifications
                    create_notification(
                        $new_user_id,
                        "Welcome to AlumniNet! 🎉",
                        "Your account registration has been submitted successfully. Explore platform features, jobs, and networking.",
                        "success",
                        "high"
                    );
                    notify_admins(
                        "New User Registration",
                        "User " . $uData['name'] . " (" . ucfirst($uData['role']) . ") registered with email " . $uData['email'] . ".",
                        "info",
                        "medium"
                    );

                    unset($_SESSION['otp_verify']);

                    if ($uData['role'] === 'alumni') {
                        set_flash('success', 'Mobile OTP Verified! Registration submitted for admin approval.');
                    } else {
                        set_flash('success', 'Mobile OTP Verified! Your account has been activated. Please log in.');
                    }
                    header('Location: login.php');
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to create user account: ' . $e->getMessage();
            }
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
    .timer-badge {
        font-size: 0.85rem;
        color: var(--theme-text-secondary);
        margin-top: 1rem;
    }
    .timer-badge strong {
        color: var(--theme-accent-blue);
    }
</style>

<div class="otp-wrapper">
    <div class="otp-card card-glass fade-in">
        <div style="margin-bottom: 1.5rem;">
            <div style="width: 70px; height: 70px; margin: 0 auto 1.25rem; background: rgba(168, 85, 247, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(168, 85, 247, 0.3);">
                <i class="fa-solid fa-mobile-screen-button" style="font-size: 2rem; color: var(--theme-accent-purple);"></i>
            </div>
            <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.4rem;">OTP Verification</h2>
            <p style="color: var(--theme-text-secondary); font-size: 0.88rem;">
                Enter the 6-digit verification code sent to your mobile phone & email address.
            </p>
        </div>

        <!-- SMS & EMAIL DISPATCH STATUS BADGE -->
        <div style="background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 10px; padding: 0.85rem 1.15rem; margin-bottom: 1.5rem; text-align: left; font-size: 0.83rem;">
            <div style="display: flex; align-items: center; gap: 0.4rem; color: #38bdf8; font-weight: 700; margin-bottom: 0.35rem;">
                <i class="fa-solid fa-mobile-screen-button"></i> Mobile SMS & Email OTP Dispatch Active
            </div>
            <div style="color: var(--theme-text-secondary); line-height: 1.5;">
                📱 <strong>Mobile SMS:</strong> Dispatched to +91 <strong><?php echo htmlspecialchars($_SESSION['otp_verify']['phone']); ?></strong><br>
                📧 <strong>Email Address:</strong> Sent to <strong><?php echo htmlspecialchars($_SESSION['otp_verify']['email']); ?></strong>
            </div>
        </div>

        <!-- DEMO OTP DISPLAY BANNER -->
        <div class="card-glass" style="display: none; background: rgba(59, 130, 246, 0.08); border-color: rgba(59, 130, 246, 0.25); color: var(--theme-accent-blue); padding: 0.85rem 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem;">
            <i class="fa-solid fa-paper-plane" style="margin-right: 0.4rem;"></i> [DEMO SIMULATION] Your OTP Code: 
            <strong id="demo-otp-display" style="font-size: 1.15rem; letter-spacing: 3px; color: #ffffff; margin-left: 0.3rem;">
                <?php echo htmlspecialchars($_SESSION['otp_verify']['code']); ?>
            </strong>
        </div>

        <?php if (!empty($error)): ?>
            <div class="card-glass" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25); color: #f87171; padding: 0.85rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem; justify-content: center;">
                <i class="fa-solid fa-circle-xmark"></i> <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form action="verify_otp.php" method="POST" id="otp-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="otp-inputs">
                <input type="text" name="d1" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required autofocus>
                <input type="text" name="d2" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <input type="text" name="d3" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <input type="text" name="d4" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <input type="text" name="d5" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <input type="text" name="d6" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <i class="fa-solid fa-shield-check"></i> Verify & Complete Sign Up
            </button>
        </form>

        <div class="timer-badge">
            Resend code in <strong id="countdown">05:00</strong>
        </div>

        <div style="margin-top: 1rem;">
            <button type="button" id="btn-resend" onclick="resendOTP()" disabled class="btn btn-secondary btn-small" style="font-size: 0.82rem; opacity: 0.5; cursor: not-allowed;">
                <i class="fa-solid fa-rotate-right"></i> Resend OTP Code
            </button>
        </div>

        <div style="margin-top: 1.75rem; font-size: 0.85rem;">
            <a href="register.php" style="color: var(--theme-text-secondary); text-decoration: none;">
                <i class="fa-solid fa-arrow-left"></i> Change details / Start over
            </a>
        </div>
    </div>
</div>

<script>
    // Segmented digit inputs auto-focus & paste handling
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

    // Countdown Timer logic
    let expiresAt = <?php echo $_SESSION['otp_verify']['expires_at']; ?>;
    
    function updateTimer() {
        const now = Math.floor(Date.now() / 1000);
        let secondsLeft = expiresAt - now;

        if (secondsLeft <= 0) {
            document.getElementById('countdown').textContent = '00:00 (Expired)';
            enableResendBtn();
            return;
        }

        const mins = Math.floor(secondsLeft / 60);
        const secs = secondsLeft % 60;
        document.getElementById('countdown').textContent = 
            (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
        
        // Enable resend button after 30 seconds
        if ((300 - secondsLeft) >= 30) {
            enableResendBtn();
        }
    }

    function enableResendBtn() {
        const btn = document.getElementById('btn-resend');
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
    }

    setInterval(updateTimer, 1000);
    updateTimer();

    // Resend OTP via AJAX
    function resendOTP() {
        const btn = document.getElementById('btn-resend');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

        const formData = new FormData();
        formData.append('action', 'resend_signup_otp');

        fetch('api/send_otp.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('demo-otp-display').textContent = data.demo_otp;
                expiresAt = Math.floor(Date.now() / 1000) + data.expires_in;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Resend OTP Code';
                alert(data.message);
                inputs[0].focus();
            } else {
                alert(data.message || 'Failed to resend OTP.');
                enableResendBtn();
                btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Resend OTP Code';
            }
        })
        .catch(err => {
            alert('Network error while resending OTP.');
            enableResendBtn();
            btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Resend OTP Code';
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

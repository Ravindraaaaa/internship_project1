<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security_helper.php';

$page_title = "Register";

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!check_csrf($csrf_token)) {
        $register_error = 'Invalid security token (CSRF). Please reload the page and try again.';
    } else {
        $first_name = trim(filter_var($_POST['first_name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $last_name = trim(filter_var($_POST['last_name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $name = trim($first_name . ' ' . $last_name);
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = trim($_POST['role'] ?? 'student'); // 'student' or 'alumni'
        if (!in_array($role, ['student', 'alumni'])) {
            $role = 'student';
        }

        // Clean phone number: remove any non-digit characters
        $phone_digits = preg_replace('/[^0-9]/', '', $phone);

        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
            $register_error = 'All required credential fields must be filled out.';
        } elseif (strlen($phone_digits) !== 10 || !preg_match('/^[0-9]{10}$/', $phone_digits)) {
            $register_error = 'Phone number must contain exactly 10 digits (numbers only, e.g., 9876543210).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_error = 'Invalid email address format.';
        } elseif (strlen($password) < 6) {
            $register_error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $register_error = 'Passwords do not match.';
        } else {
            try {
                // Check email uniqueness
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? UNION SELECT id FROM admins WHERE email = ?");
                $stmtCheck->execute([$email, $email]);
                if ($stmtCheck->fetch()) {
                    $register_error = 'This email address is already registered.';
                } else {
                    $status = ($role === 'alumni') ? 'pending' : 'approved';
                    $hashed_pass = password_hash($password, PASSWORD_BCRYPT);

                    $username_base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]));
                    if (empty($username_base)) {
                        $username_base = 'user';
                    }
                    $username = $username_base;
                    $stmtUsernameCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmtUsernameCheck->execute([$username]);
                    $count = 1;
                    while ($stmtUsernameCheck->fetch()) {
                        $username = $username_base . $count;
                        $stmtUsernameCheck->execute([$username]);
                        $count++;
                    }

                    // Handle profile picture upload first
                    $profile_pic_path = '';
                    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                        $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
                        $fileName = $_FILES['profile_pic']['name'];
                        $fileSize = $_FILES['profile_pic']['size'];
                        
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                        
                        // Strict MIME inspection
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $fileTmpPath);
                        finfo_close($finfo);
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                        if (in_array($fileExtension, $allowedExtensions) && in_array($mimeType, $allowedMimes) && $fileSize <= 2 * 1024 * 1024) {
                            $uploadFileDir = './uploads/profiles/';
                            if (!is_dir($uploadFileDir)) {
                                mkdir($uploadFileDir, 0755, true);
                            }
                            $newFileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
                            $dest_path = $uploadFileDir . $newFileName;
                            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                                $profile_pic_path = 'uploads/profiles/' . $newFileName;
                            }
                        }
                    }

                    $course = trim(filter_var($_POST['course'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
                    $grad_year = intval($_POST['grad_year'] ?? date('Y'));

                    // Generate random 6-digit OTP code
                    $otp_code = sprintf('%06d', mt_rand(100000, 999999));

                    $_SESSION['otp_verify'] = [
                        'type' => 'signup',
                        'code' => $otp_code,
                        'expires_at' => time() + 300, // 5 mins
                        'attempts' => 0,
                        'email' => $email,
                        'phone' => $phone_digits,
                        'user_data' => [
                            'name' => $name,
                            'email' => $email,
                            'username' => $username,
                            'password' => $hashed_pass,
                            'phone' => $phone_digits,
                            'role' => $role,
                            'status' => $status,
                            'course' => $course,
                            'grad_year' => $grad_year,
                            'profile_pic_path' => $profile_pic_path
                        ]
                    ];

                    // Dispatch real-time SMTP Verification Email
                    send_signup_otp_email($email, $otp_code);

                    // Redirect to OTP Verification Screen
                    header('Location: verify_otp.php');
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $register_error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .signup-wrapper {
        display: flex;
        min-height: 95vh;
        align-items: center;
        justify-content: center;
        padding: 6rem 2rem;
    }
    .signup-card {
        width: 100%;
        max-width: 680px;
    }
    .signup-header {
        text-align: center;
        margin-bottom: 2.25rem;
    }
    .signup-header h1 {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    .signup-header p {
        color: var(--theme-text-secondary);
        font-size: 0.9rem;
    }
    
    .upload-avatar-container {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
        grid-column: span 2;
        background: rgba(255,255,255,0.02);
        padding: 1rem;
        border-radius: var(--border-radius-md);
        border: 1px dashed var(--theme-border);
    }
    .avatar-preview {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--theme-accent-purple);
    }
    
    .role-select-box {
        display: flex;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--theme-border);
        border-radius: var(--border-radius-md);
        padding: 0.25rem;
        margin-bottom: 1.5rem;
        grid-column: span 2;
    }
    .role-option-btn {
        flex: 1;
        text-align: center;
        padding: 0.6rem;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.88rem;
        border-radius: var(--border-radius-sm);
        transition: var(--transition-speed);
        color: var(--theme-text-secondary);
    }
    .role-option-btn.active {
        background: var(--theme-accent-gradient);
        color: #ffffff;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        .upload-avatar-container, .role-select-box {
            grid-column: span 1;
        }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-6px); }
        40%, 80% { transform: translateX(6px); }
    }
</style>

<div class="signup-wrapper">
    <div class="card-glass signup-card fade-in">
        <div class="signup-header">
            <a href="index.php" class="logo" style="justify-content: center; margin-bottom: 1rem;">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <h1>Create Account</h1>
            <p>Register to search alumni, request mentoring and RSVP to events</p>
        </div>

        <?php if (!empty($register_error)): ?>
            <div class="card-glass" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25); color: #f87171; padding: 0.85rem 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem; display: flex; align-items: center; gap: 0.65rem; animation: shake 0.4s ease; border: 1px solid rgba(239, 68, 68, 0.3);">
                <i class="fa-solid fa-circle-xmark" style="color: #ef4444; font-size: 1rem;"></i> 
                <span><?php echo htmlspecialchars($register_error); ?></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" enctype="multipart/form-data" id="signup-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            
            <div class="form-grid">
                
                <!-- Role toggle -->
                <div class="role-select-box">
                    <div class="role-option-btn active" id="btn-student" onclick="toggleRoleOption('student')">Student</div>
                    <div class="role-option-btn" id="btn-alumni" onclick="toggleRoleOption('alumni')">Alumni Member</div>
                </div>
                <input type="hidden" name="role" id="role-input" value="student">

                <!-- Profile Pic upload -->
                <div class="upload-avatar-container">
                    <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Upload Preview" class="avatar-preview" id="profile-pic-preview">
                    <div>
                        <h4 style="font-size: 0.9rem; margin-bottom: 0.25rem;">Choose Avatar</h4>
                        <p style="font-size: 0.75rem; color: var(--theme-text-secondary); margin-bottom: 0.75rem;">Supported formats: JPG, PNG, WEBP (Max 2MB)</p>
                        <label class="btn btn-secondary btn-small" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; cursor:pointer;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo
                            <input type="file" name="profile_pic" accept="image/jpeg,image/png,image/webp" onchange="previewAvatarPic(this)" style="display: none;">
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="first_name" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">First Name</label>
                    <input type="text" name="first_name" id="first_name" class="input-glass" placeholder="John" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="last_name" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Last Name</label>
                    <input type="text" name="last_name" id="last_name" class="input-glass" placeholder="Doe" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Email Address</label>
                    <input type="email" name="email" id="email" class="input-glass" placeholder="john@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Phone Number (10 Digits)</label>
                    <input type="text" 
                           name="phone" 
                           id="phone" 
                           class="input-glass" 
                           placeholder="9876543210" 
                           maxlength="10" 
                           minlength="10" 
                           pattern="[0-9]{10}" 
                           inputmode="numeric" 
                           required 
                           oninput="validatePhoneNumber(this)"
                           onkeypress="return isNumberKey(event)"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    <small id="phone-hint" style="font-size: 0.73rem; color: var(--theme-text-secondary); margin-top: 0.35rem; display: block; transition: color 0.2s ease;">
                        Must be exactly 10 numeric digits
                    </small>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Password</label>
                    <input type="password" name="password" id="password" class="input-glass" placeholder="••••••••" required onkeyup="checkPasswordStrength(this.value)">
                    <div style="margin-top: 0.5rem; display: flex; gap: 0.25rem; align-items: center;" id="password-strength-container">
                        <div style="height: 4px; flex-grow: 1; background: rgba(255,255,255,0.06); border-radius: 2px;" class="strength-bar"></div>
                        <div style="height: 4px; flex-grow: 1; background: rgba(255,255,255,0.06); border-radius: 2px;" class="strength-bar"></div>
                        <div style="height: 4px; flex-grow: 1; background: rgba(255,255,255,0.06); border-radius: 2px;" class="strength-bar"></div>
                        <span id="strength-text" style="font-size: 0.7rem; color: var(--theme-text-secondary); margin-left: 0.5rem;">Weak</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="input-glass" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label for="course" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Department / Stream</label>
                    <select name="course" id="course" class="input-glass" required>
                        <option value="Computer Science Engineering">Computer Science Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Electronics & Communication">Electronics & Communication</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                    </select>
                </div>

                <div class="form-group" id="passout-field" style="display: none;">
                    <label for="grad_year" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Passout Year</label>
                    <input type="number" name="grad_year" id="grad_year" class="input-glass" min="1950" max="2035" value="<?php echo date('Y'); ?>">
                </div>

            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.9rem; font-size: 1rem; margin-top: 2rem;">
                <i class="fa-solid fa-user-plus"></i> Register Profile
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--theme-text-secondary);">
            Already have an account? <a href="login.php" style="color: var(--theme-accent-blue); font-weight: 600;">Sign In</a>
        </div>
    </div>
</div>

<script>
    function isNumberKey(evt) {
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            return false;
        }
        return true;
    }

    function validatePhoneNumber(input) {
        // Strict integer filter: replace any non-numeric characters instantly
        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 10);
        const hint = document.getElementById('phone-hint');
        if (input.value.length === 10) {
            hint.style.color = '#22c55e';
            hint.textContent = '✓ Valid 10-digit numeric phone number';
        } else if (input.value.length > 0) {
            hint.style.color = '#ef4444';
            hint.textContent = 'Must be exactly 10 digits (' + (10 - input.value.length) + ' more needed)';
        } else {
            hint.style.color = 'var(--theme-text-secondary)';
            hint.textContent = 'Must be exactly 10 numeric digits';
        }
    }

    document.getElementById('signup-form').addEventListener('submit', function(e) {
        const phoneInput = document.getElementById('phone');
        const phoneVal = phoneInput.value.replace(/[^0-9]/g, '');
        if (phoneVal.length !== 10) {
            e.preventDefault();
            alert('Phone number must contain exactly 10 numeric digits.');
            phoneInput.focus();
            return false;
        }
    });

    function previewAvatarPic(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profile-pic-preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function toggleRoleOption(role) {
        document.getElementById('role-input').value = role;
        document.getElementById('btn-student').classList.remove('active');
        document.getElementById('btn-alumni').classList.remove('active');
        
        if (role === 'student') {
            document.getElementById('btn-student').classList.add('active');
            document.getElementById('passout-field').style.display = 'none';
            document.getElementById('grad_year').removeAttribute('required');
        } else {
            document.getElementById('btn-alumni').classList.add('active');
            document.getElementById('passout-field').style.display = 'block';
            document.getElementById('grad_year').setAttribute('required', 'required');
        }
    }

    function checkPasswordStrength(password) {
        const bars = document.querySelectorAll('.strength-bar');
        const text = document.getElementById('strength-text');
        let strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/) || password.match(/[^a-zA-Z0-9]/)) strength++;
        
        bars.forEach(bar => bar.style.background = 'rgba(255,255,255,0.06)');
        
        if (password.length === 0) {
            text.textContent = 'Too short';
            text.style.color = 'var(--theme-text-secondary)';
        } else if (strength === 1) {
            bars[0].style.background = '#ef4444';
            text.textContent = 'Weak';
            text.style.color = '#ef4444';
        } else if (strength === 2) {
            bars[0].style.background = '#eab308';
            bars[1].style.background = '#eab308';
            text.textContent = 'Medium';
            text.style.color = '#eab308';
        } else if (strength === 3) {
            bars[0].style.background = '#22c55e';
            bars[1].style.background = '#22c55e';
            bars[2].style.background = '#22c55e';
            text.textContent = 'Strong';
            text.style.color = '#22c55e';
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

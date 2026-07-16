<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';

$page_title = "Register";

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $name = trim($first_name . ' ' . $last_name);
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? 'student'); // 'student' or 'alumni'

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        set_flash('error', 'All core credentials fields are required.');
    } elseif ($password !== $confirm_password) {
        set_flash('error', 'Passwords do not match.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Invalid email address format.');
    } else {
        try {
            // Check email uniqueness
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? UNION SELECT id FROM admins WHERE email = ?");
            $stmtCheck->execute([$email, $email]);
            if ($stmtCheck->fetch()) {
                set_flash('error', 'This email is already registered.');
            } else {
                $status = ($role === 'alumni') ? 'pending' : 'approved';
                $hashed_pass = password_hash($password, PASSWORD_BCRYPT);

                $username_base = explode('@', $email)[0];
                $username = $username_base;
                $stmtUsernameCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtUsernameCheck->execute([$username]);
                $count = 1;
                while ($stmtUsernameCheck->fetch()) {
                    $username = $username_base . $count;
                    $stmtUsernameCheck->execute([$username]);
                    $count++;
                }

                $pdo->beginTransaction();

                // 1. Insert into users table
                $stmtInsertUser = $pdo->prepare("INSERT INTO users (name, email, username, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtInsertUser->execute([$name, $email, $username, $hashed_pass, $role, $status]);
                $new_user_id = $pdo->lastInsertId();

                // Handle file upload
                $profile_pic_path = '';
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
                    $fileName = $_FILES['profile_pic']['name'];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));
                    
                    $allowedExtensions = ['jpg', 'jpeg', 'png'];
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $uploadFileDir = './uploads/profiles/';
                        if (!is_dir($uploadFileDir)) {
                            mkdir($uploadFileDir, 0755, true);
                        }
                        $newFileName = md5(time() . $new_user_id) . '.' . $fileExtension;
                        $dest_path = $uploadFileDir . $newFileName;
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $profile_pic_path = 'uploads/profiles/' . $newFileName;
                        }
                    }
                }

                if ($role === 'student') {
                    $current_year = 1;
                    $course = trim($_POST['course'] ?? '');
                    $bio = 'Registered Student.';

                    $stmtProfile = $pdo->prepare("INSERT INTO student_profiles (user_id, current_year, course, bio, profile_pic) VALUES (?, ?, ?, ?, ?)");
                    $stmtProfile->execute([$new_user_id, $current_year, $course, $bio, $profile_pic_path]);
                } else {
                    $grad_year = intval($_POST['grad_year'] ?? date('Y'));
                    $course = trim($_POST['course'] ?? '');
                    $company = '';
                    $position = '';
                    $industry = '';
                    $bio = 'Registered Alumnus.';

                    $stmtProfile = $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, course, company, position, industry, bio, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtProfile->execute([$new_user_id, $grad_year, $course, $company, $position, $industry, $bio, $profile_pic_path]);
                }

                $pdo->commit();

                if ($role === 'alumni') {
                    set_flash('success', 'Registration submitted! Awaiting administrator approval.');
                } else {
                    set_flash('success', 'Registration successful! Please log in.');
                }
                header('Location: login.php');
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Registration failed: ' . $e->getMessage());
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

        <form action="register.php" method="POST" enctype="multipart/form-data">
            
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
                        <p style="font-size: 0.75rem; color: var(--theme-text-secondary); margin-bottom: 0.75rem;">Supported formats: JPG, PNG (Max 2MB)</p>
                        <label class="btn btn-secondary btn-small" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; cursor:pointer;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo
                            <input type="file" name="profile_pic" accept="image/*" onchange="previewAvatarPic(this)" style="display: none;">
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="first_name" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">First Name</label>
                    <input type="text" name="first_name" id="first_name" class="input-glass" placeholder="John" required>
                </div>

                <div class="form-group">
                    <label for="last_name" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Last Name</label>
                    <input type="text" name="last_name" id="last_name" class="input-glass" placeholder="Doe" required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Email Address</label>
                    <input type="email" name="email" id="email" class="input-glass" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Phone Number</label>
                    <input type="tel" name="phone" id="phone" class="input-glass" placeholder="+91 9876543210" required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Password</label>
                    <input type="password" name="password" id="password" class="input-glass" placeholder="••••••••" required>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

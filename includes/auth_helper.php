<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/security_helper.php';
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    handle_session_timeout();
}

/**
 * Check if any user (regular or admin) is logged in.
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
            check_remember_me_cookie();
        }
        return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
    }
}

/**
 * Check if the logged-in user is an admin.
 */
if (!function_exists('is_admin')) {
    function is_admin() {
        return isset($_SESSION['admin_id']);
    }
}

/**
 * Get current user role ('superadmin', 'moderator', 'alumni', 'student', or null).
 */
if (!function_exists('get_user_role')) {
    function get_user_role() {
        if (is_admin()) {
            return $_SESSION['admin_role']; // 'superadmin' or 'moderator'
        }
        return $_SESSION['user_role'] ?? null; // 'alumni' or 'student'
    }
}

/**
 * Get current user ID.
 */
if (!function_exists('get_user_id')) {
    function get_user_id() {
        global $pdo;
        if (is_admin()) {
            if (!isset($_SESSION['user_id']) && isset($_SESSION['admin_id'])) {
                if (!isset($pdo)) {
                    require_once __DIR__ . '/db.php';
                }
                try {
                    $stmt = $pdo->prepare("SELECT user_id FROM admins WHERE id = ?");
                    $stmt->execute([$_SESSION['admin_id']]);
                    $uid = $stmt->fetchColumn();
                    if ($uid) {
                        $_SESSION['user_id'] = $uid;
                    }
                } catch (Exception $e) {
                    // ignore
                }
            }
            return $_SESSION['user_id'] ?? $_SESSION['admin_id'];
        }
        return $_SESSION['user_id'] ?? null;
    }
}

/**
 * Get current user's name.
 */
if (!function_exists('get_user_name')) {
    function get_user_name() {
        if (is_admin()) {
            return $_SESSION['admin_name'];
        }
        return $_SESSION['user_name'] ?? 'User';
    }
}

/**
 * Enforce that a user is logged in. Redirect to login if not.
 */
if (!function_exists('require_login')) {
    function require_login() {
        if (!is_logged_in()) {
            set_flash('error', 'Please log in to access this page.');
            $current_dir = basename(dirname($_SERVER['PHP_SELF']));
            $redirect_prefix = ($current_dir === 'user' || $current_dir === 'admin') ? '../' : '';
            header('Location: ' . $redirect_prefix . 'login.php');
            exit;
        }
    }
}

/**
 * Enforce that the user is an admin.
 */
if (!function_exists('require_admin')) {
    function require_admin() {
        require_login();
        if (!is_admin()) {
            set_flash('error', 'Access denied. Administrator privileges required.');
            $current_dir = basename(dirname($_SERVER['PHP_SELF']));
            $redirect_prefix = ($current_dir === 'user' || $current_dir === 'admin') ? '../' : '';
            header('Location: ' . $redirect_prefix . 'dashboard.php');
            exit;
        }
    }
}

/**
 * Enforce specific roles (e.g. 'alumni', 'student').
 */
if (!function_exists('require_role')) {
    function require_role($allowed_roles = []) {
        require_login();
        $role = get_user_role();
        if (!in_array($role, $allowed_roles) && !is_admin()) {
            set_flash('error', 'You do not have permission to access this page.');
            $current_dir = basename(dirname($_SERVER['PHP_SELF']));
            $redirect_prefix = ($current_dir === 'user' || $current_dir === 'admin') ? '../' : '';
            header('Location: ' . $redirect_prefix . 'dashboard.php');
            exit;
        }
    }
}

/**
 * Set flash message in session.
 */
if (!function_exists('set_flash')) {
    function set_flash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type, // 'success', 'error', 'info', 'warning'
            'message' => $message
        ];
    }
}

/**
 * Retrieve and clear flash message.
 */
if (!function_exists('get_flash')) {
    function get_flash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}

/**
 * Helper to display flash alerts in premium style.
 */
if (!function_exists('display_flash')) {
    function display_flash() {
        $flash = get_flash();
        if ($flash) {
            $typeClass = htmlspecialchars($flash['type']);
            $message = $flash['message'];
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        if (window.showToast) {
                            window.showToast(" . json_encode($message) . ", " . json_encode($typeClass) . ");
                        } else {
                            alert(" . json_encode(strip_tags($message)) . ");
                        }
                    }, 100);
                });
            </script>";
        }
    }
}

/**
 * Checks if a user is eligible for a job or event based on mapped requirements.
 */
if (!function_exists('check_user_eligibility')) {
    function check_user_eligibility($user_id, $target_id, $type = 'job') {
        global $pdo;
        
        if (is_admin()) {
            return ['eligible' => true, 'reason' => ''];
        }
        
        $stmt = $pdo->prepare("SELECT role, department_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['eligible' => false, 'reason' => 'User record not found.'];
        }
        
        if ($user['role'] === 'alumni') {
            return ['eligible' => true, 'reason' => ''];
        }
        
        $cgpa = 0.00;
        $stmt = $pdo->prepare("SELECT cgpa FROM student_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch();
        if ($student) {
            $cgpa = floatval($student['cgpa']);
        }
        
        $stmt = $pdo->prepare("SELECT s.name FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ?");
        $stmt->execute([$user_id]);
        $user_skills = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($type === 'job') {
            $stmt = $pdo->prepare("SELECT r.* FROM job_requirements jr JOIN requirements r ON jr.requirement_id = r.id WHERE jr.job_id = ?");
        } else {
            $stmt = $pdo->prepare("SELECT r.* FROM event_requirements er JOIN requirements r ON er.requirement_id = r.id WHERE er.event_id = ?");
        }
        $stmt->execute([$target_id]);
        $requirements = $stmt->fetchAll();
        
        foreach ($requirements as $r) {
            if ($r['min_cgpa'] > 0.0 && $cgpa < floatval($r['min_cgpa'])) {
                return ['eligible' => false, 'reason' => "Requires min CGPA: " . $r['min_cgpa'] . " (Your CGPA: " . number_format($cgpa, 2) . ")"];
            }
            
            if (!empty($r['allowed_departments'])) {
                $allowed = explode(',', $r['allowed_departments']);
                if (!in_array($user['department_id'], $allowed)) {
                    return ['eligible' => false, 'reason' => "Not open to your department."];
                }
            }
            
            if (!empty($r['skills_required'])) {
                $required = array_map('trim', explode(',', $r['skills_required']));
                foreach ($required as $req_skill) {
                    if (!in_array(strtolower($req_skill), array_map('strtolower', $user_skills))) {
                        return ['eligible' => false, 'reason' => "Missing required skill: " . htmlspecialchars($req_skill)];
                    }
                }
            }
            
            if ($r['deadline']) {
                if (strtotime($r['deadline']) < time()) {
                    return ['eligible' => false, 'reason' => "The application deadline has passed."];
                }
            }
        }
        
        return ['eligible' => true, 'reason' => ''];
    }
}
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($profile_pic) {
        if (empty($profile_pic)) {
            return 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
        }
        if (strpos($profile_pic, 'http://') === 0 || strpos($profile_pic, 'https://') === 0) {
            return $profile_pic;
        }
        $path_prefix = (basename(dirname($_SERVER['PHP_SELF'])) === 'user' || basename(dirname($_SERVER['PHP_SELF'])) === 'admin') ? '../' : '';
        $local_path = __DIR__ . '/../' . $profile_pic;
        if (file_exists($local_path)) {
            return $path_prefix . $profile_pic;
        }
        return 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
    }
}

if (!function_exists('render_sidebar')) {
    function render_sidebar($active_page = '') {
        $GLOBALS['sidebar_rendered'] = true;
        global $pdo;
        $uid = get_user_id();
        $role = get_user_role();
        $user_name = get_user_name();
        
        $path_prefix = (basename(dirname($_SERVER['PHP_SELF'])) === 'user' || basename(dirname($_SERVER['PHP_SELF'])) === 'admin') ? '../' : '';
        $sub_prefix = (basename(dirname($_SERVER['PHP_SELF'])) === 'user' || basename(dirname($_SERVER['PHP_SELF'])) === 'admin') ? '' : 'user/';
        $admin_prefix = (basename(dirname($_SERVER['PHP_SELF'])) === 'admin') ? '' : 'admin/';
        if (basename(dirname($_SERVER['PHP_SELF'])) === 'user') {
            $admin_prefix = '../admin/';
        }
        
        // Default Avatar
        $sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
        if (is_logged_in()) {
            if (is_admin()) {
                $sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png';
            } else {
                try {
                    if ($role === 'alumni') {
                        $stmt = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
                        $stmt->execute([$uid]);
                        $prof = $stmt->fetch();
                        $sidebar_avatar = get_avatar_url($prof['profile_pic'] ?? '');
                    } else if ($role === 'student') {
                        $stmt = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
                        $stmt->execute([$uid]);
                        $prof = $stmt->fetch();
                        $sidebar_avatar = get_avatar_url($prof['profile_pic'] ?? '');
                    }
                } catch (Exception $e) {
                    // silent fail
                }
            }
        }
        
        ?>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="<?php echo $path_prefix; ?>index.php" class="logo logo-text">
                    <i class="fa-solid fa-graduation-cap"></i> AlumniNet
                </a>
                <button class="sidebar-toggle-btn" id="sidebar-toggle">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
            </div>

            <ul class="sidebar-menu">
                <?php if (is_logged_in()): ?>
                    <?php if (is_admin()): ?>
                        <li class="sidebar-item <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
                            <a href="<?php echo $admin_prefix; ?>dashboard.php?tab=overview"><i class="fa-solid fa-gauge"></i> <span class="link-text">Dashboard</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'alumni' ? 'active' : ''; ?>">
                            <a href="<?php echo $admin_prefix; ?>dashboard.php?tab=alumni"><i class="fa-solid fa-user-check"></i> <span class="link-text">Manage Alumni</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'students' ? 'active' : ''; ?>">
                            <a href="<?php echo $admin_prefix; ?>dashboard.php?tab=students"><i class="fa-solid fa-users"></i> <span class="link-text">Manage Students</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'jobs' ? 'active' : ''; ?>">
                            <a href="<?php echo $admin_prefix; ?>dashboard.php?tab=jobs"><i class="fa-solid fa-briefcase"></i> <span class="link-text">Manage Jobs</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'events' ? 'active' : ''; ?>">
                            <a href="<?php echo $admin_prefix; ?>dashboard.php?tab=events"><i class="fa-solid fa-calendar-days"></i> <span class="link-text">Manage Events</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'messages' ? 'active' : ''; ?>">
                            <a href="<?php echo $admin_prefix; ?>dashboard.php?tab=messages"><i class="fa-solid fa-envelope"></i> <span class="link-text">System Messages</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'reports' ? 'active' : ''; ?>">
                            <a href="<?php echo $admin_prefix; ?>dashboard.php?tab=reports"><i class="fa-solid fa-chart-line"></i> <span class="link-text">Reports</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'feedback' ? 'active' : ''; ?>">
                            <a href="<?php echo $admin_prefix; ?>dashboard.php?tab=feedback"><i class="fa-solid fa-comments"></i> <span class="link-text">User Feedback</span></a>
                        </li>
                    <?php else: ?>
                        <li class="sidebar-item <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>dashboard.php"><i class="fa-solid fa-gauge"></i> <span class="link-text">Dashboard</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'profile' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>profile.php"><i class="fa-solid fa-circle-user"></i> <span class="link-text">My Profile</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'mentorship' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>mentorship.php"><i class="fa-solid fa-handshake-angle"></i> <span class="link-text">Mentorship</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'alumni' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>alumni.php"><i class="fa-solid fa-users"></i> <span class="link-text">Alumni Directory</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'jobs' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>jobs.php"><i class="fa-solid fa-briefcase"></i> <span class="link-text">Job Board</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'events' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>events.php"><i class="fa-solid fa-calendar-days"></i> <span class="link-text">Events Board</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'portfolio' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>portfolio.php"><i class="fa-solid fa-folder-kanban"></i> <span class="link-text">My Portfolio</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'chat' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>chat.php"><i class="fa-solid fa-comment-dots"></i> <span class="link-text">Messenger</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'feedback' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>feedback.php"><i class="fa-solid fa-comments"></i> <span class="link-text">Feedback</span></a>
                        </li>
                        <li class="sidebar-item <?php echo $active_page === 'help' ? 'active' : ''; ?>">
                            <a href="<?php echo $sub_prefix; ?>help.php"><i class="fa-solid fa-circle-question"></i> <span class="link-text">Help & Support</span></a>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="sidebar-item <?php echo $active_page === 'alumni' ? 'active' : ''; ?>">
                        <a href="<?php echo $sub_prefix; ?>alumni.php"><i class="fa-solid fa-users"></i> <span class="link-text">Alumni Directory</span></a>
                    </li>
                    <li class="sidebar-item <?php echo $active_page === 'jobs' ? 'active' : ''; ?>">
                        <a href="<?php echo $sub_prefix; ?>jobs.php"><i class="fa-solid fa-briefcase"></i> <span class="link-text">Job Board</span></a>
                    </li>
                    <li class="sidebar-item <?php echo $active_page === 'events' ? 'active' : ''; ?>">
                        <a href="<?php echo $sub_prefix; ?>events.php"><i class="fa-solid fa-calendar-days"></i> <span class="link-text">Events Board</span></a>
                    </li>
                    <li class="sidebar-item <?php echo $active_page === 'help' ? 'active' : ''; ?>">
                        <a href="<?php echo $sub_prefix; ?>help.php"><i class="fa-solid fa-circle-question"></i> <span class="link-text">Help & Support</span></a>
                    </li>
                <?php endif; ?>
            </ul>
        </aside>
        <?php
    }
}

if (!function_exists('set_remember_me_cookie')) {
    function set_remember_me_cookie($user_id) {
        global $pdo;
        $token = bin2hex(random_bytes(32));
        $hashed_token = hash('sha256', $token);
        
        if (!isset($pdo)) {
            require_once __DIR__ . '/../config/db.php';
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$hashed_token, $user_id]);
            
            // Cookie format user_id:token, expires in 30 days
            $cookie_value = $user_id . ':' . $token;
            setcookie('remember_user', $cookie_value, time() + (86400 * 30), "/", "", false, true);
        } catch (Exception $e) {
            // silent fail
        }
    }
}

if (!function_exists('clear_remember_me_cookie')) {
    function clear_remember_me_cookie() {
        global $pdo;
        if (isset($_COOKIE['remember_user'])) {
            $parts = explode(':', $_COOKIE['remember_user'], 2);
            if (count($parts) === 2) {
                $user_id = intval($parts[0]);
                if (!isset($pdo)) {
                    require_once __DIR__ . '/../config/db.php';
                }
                try {
                    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                    $stmt->execute([$user_id]);
                } catch (Exception $e) {
                    // silent fail
                }
            }
            setcookie('remember_user', '', time() - 3600, "/", "", false, true);
        }
    }
}

if (!function_exists('check_remember_me_cookie')) {
    function check_remember_me_cookie() {
        global $pdo;
        if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
            return;
        }
        
        if (isset($_COOKIE['remember_user'])) {
            $parts = explode(':', $_COOKIE['remember_user'], 2);
            if (count($parts) === 2) {
                $user_id = intval($parts[0]);
                $token = $parts[1];
                $hashed_token = hash('sha256', $token);
                
                if (!isset($pdo)) {
                    require_once __DIR__ . '/../config/db.php';
                }
                
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if ($user && $user['remember_token'] === $hashed_token && $user['status'] !== 'blocked' && $user['status'] !== 'rejected') {
                        // Log user in
                        if ($user['role'] === 'admin') {
                            $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE user_id = ?");
                            $stmtAdmin->execute([$user_id]);
                            $admin = $stmtAdmin->fetch();
                            if (!$admin) {
                                $stmtInsAdmin = $pdo->prepare("INSERT INTO admins (user_id, username, name, email, password, role) VALUES (?, ?, ?, ?, ?, 'superadmin')");
                                $stmtInsAdmin->execute([$user_id, $user['username'], $user['name'], $user['email'], $user['password']]);
                                $adminId = $pdo->lastInsertId();
                                $adminRole = 'superadmin';
                            } else {
                                $adminId = $admin['id'];
                                $adminRole = $admin['role'];
                            }
                            $_SESSION['admin_id'] = $adminId;
                            $_SESSION['admin_name'] = $user['name'];
                            $_SESSION['admin_role'] = $adminRole;
                            $_SESSION['user_id'] = $user_id;
                        } else {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['user_role'] = $user['role'];
                            $_SESSION['user_status'] = $user['status'];
                        }
                        
                        // Rotate token
                        set_remember_me_cookie($user_id);
                    } else {
                        clear_remember_me_cookie();
                    }
                } catch (Exception $e) {
                    // silent fail
                }
            }
        }
    }
}
?>

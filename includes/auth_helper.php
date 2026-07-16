<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if any user (regular or admin) is logged in.
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
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
        if (is_admin()) {
            return $_SESSION['admin_id'];
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
            $message = htmlspecialchars($flash['message']);
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        if (window.showToast) {
                            window.showToast(" . json_encode($message) . ", " . json_encode($typeClass) . ");
                        } else {
                            alert(" . json_encode($message) . ");
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
?>

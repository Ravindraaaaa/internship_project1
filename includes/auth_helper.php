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
?>

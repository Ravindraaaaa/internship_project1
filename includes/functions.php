<?php
/**
 * Global Helper Functions & Security Utilities
 */

if (!function_exists('sanitize_input')) {
    /**
     * Sanitizes inputs to prevent XSS.
     */
    function sanitize_input($data) {
        return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('escape_output')) {
    /**
     * Escapes variables for safe output rendering.
     */
    function escape_output($data) {
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generate_csrf_token')) {
    /**
     * Generates a CSRF token if one does not exist.
     */
    function generate_csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verifies that the submitted CSRF token matches the session one.
     */
    function verify_csrf_token($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/* ==================== GLOBAL NOTIFICATION DISPATCH ENGINE ==================== */

if (!function_exists('create_notification')) {
    /**
     * Creates an in-app notification for a specific target user.
     */
    function create_notification($user_id, $title, $message, $type = 'info', $priority = 'medium') {
        global $pdo;
        if (!$pdo || empty($user_id)) return false;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            return $stmt->execute([$user_id, $title, $message, $type, $priority]);
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notify_admins')) {
    /**
     * Dispatches a high-priority notification to all system administrators.
     */
    function notify_admins($title, $message, $type = 'info', $priority = 'high') {
        global $pdo;
        if (!$pdo) return false;
        
        try {
            $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' UNION SELECT user_id as id FROM admins WHERE user_id > 0");
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmtInsert = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            foreach ($admins as $admin_id) {
                if ($admin_id) {
                    $stmtInsert->execute([$admin_id, $title, $message, $type, $priority]);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to notify admins: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notify_all_users')) {
    /**
     * Dispatches a broadcast notification to all users (or filtered by role).
     */
    function notify_all_users($title, $message, $type = 'info', $priority = 'medium', $role_filter = null) {
        global $pdo;
        if (!$pdo) return false;
        
        try {
            if ($role_filter) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'approved'");
                $stmt->execute([$role_filter]);
            } else {
                $stmt = $pdo->query("SELECT id FROM users WHERE status = 'approved'");
            }
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmtInsert = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            foreach ($users as $u_id) {
                if ($u_id) {
                    $stmtInsert->execute([$u_id, $title, $message, $type, $priority]);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to notify all users: " . $e->getMessage());
            return false;
        }
    }
}
?>

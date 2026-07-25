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

/* ==================== GLOBAL NOTIFICATION & ACTIVITY DISPATCH ENGINE ==================== */

require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/activity_logger.php';

if (!function_exists('create_notification')) {
    /**
     * Creates an in-app notification for a specific target user using NotificationEngine.
     */
    function create_notification($user_id, $title, $message, $type = 'info', $priority = 'medium', $link = null, $category = 'system') {
        global $pdo;
        if (!$pdo || empty($user_id)) return false;

        // Auto-infer category if not passed explicitly
        if ($category === 'system') {
            $lowerTitle = strtolower($title);
            if (strpos($lowerTitle, 'mentorship') !== false || strpos($lowerTitle, 'connection') !== false) $category = 'mentorship';
            elseif (strpos($lowerTitle, 'job') !== false || strpos($lowerTitle, 'internship') !== false) $category = 'jobs';
            elseif (strpos($lowerTitle, 'event') !== false || strpos($lowerTitle, 'rsvp') !== false) $category = 'events';
            elseif (strpos($lowerTitle, 'message') !== false || strpos($lowerTitle, 'chat') !== false) $category = 'messages';
            elseif (strpos($lowerTitle, 'announcement') !== false) $category = 'announcements';
            elseif (strpos($lowerTitle, 'security') !== false || strpos($lowerTitle, 'otp') !== false || strpos($lowerTitle, '2fa') !== false || strpos($lowerTitle, 'password') !== false) $category = 'security';
        }

        return NotificationEngine::send([
            'user_id' => $user_id,
            'receiver_id' => $user_id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'category' => $category,
            'priority' => $priority,
            'url' => $link
        ]);
    }
}

if (!function_exists('notify_admins')) {
    /**
     * Dispatches a high-priority notification to all system administrators.
     */
    function notify_admins($title, $message, $type = 'info', $priority = 'high', $link = null) {
        return NotificationEngine::sendToRole('admin', [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'category' => 'system',
            'priority' => $priority,
            'url' => $link
        ]);
    }
}

if (!function_exists('notify_all_users')) {
    /**
     * Dispatches a broadcast notification to all users (or filtered by role).
     */
    function notify_all_users($title, $message, $type = 'info', $priority = 'medium', $role_filter = null, $link = null) {
        $targetRole = $role_filter ?: 'all';
        return NotificationEngine::sendToRole($targetRole, [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'category' => 'announcements',
            'priority' => $priority,
            'url' => $link
        ]);
    }
}

if (!function_exists('log_user_activity')) {
    function log_user_activity($user_id, $action, $category = 'general', $details = null) {
        return ActivityLogger::log($user_id, $action, $category, $details);
    }
}

if (!function_exists('log_admin_audit')) {
    function log_admin_audit($admin_id, $action, $affected_user_id = null, $old_val = null, $new_val = null) {
        return ActivityLogger::logAudit($admin_id, $action, $affected_user_id, $old_val, $new_val);
    }
}
?>

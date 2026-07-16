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
?>

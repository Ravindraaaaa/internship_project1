<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Log user activities
 */
if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $details = null) {
        global $pdo;
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $action, $details, $ip]);
        } catch (Exception $e) {
            // Silently fail to not interrupt request
        }
    }
}

/**
 * Log logins
 */
if (!function_exists('log_login')) {
    function log_login($user_id, $email, $status) {
        global $pdo;
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt = $pdo->prepare("INSERT INTO login_history (user_id, email, ip_address, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $email, $ip, $status]);
        } catch (Exception $e) {
            // Silently fail
        }
    }
}

/**
 * Verify account lockout status
 */
if (!function_exists('is_account_locked')) {
    function is_account_locked($email) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT lockout_until, failed_attempts FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch();
            if ($user && $user['lockout_until']) {
                $lockout_time = strtotime($user['lockout_until']);
                if ($lockout_time > time()) {
                    return $lockout_time - time(); // seconds remaining
                } else {
                    // Lockout period expired; reset it
                    $stmtReset = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE email = ? OR username = ?");
                    $stmtReset->execute([$email, $email]);
                }
            }
        } catch (Exception $e) {
            // ignore
        }
        return false;
    }
}

/**
 * Record failed login attempts
 */
if (!function_exists('register_failed_attempt')) {
    function register_failed_attempt($email) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id, failed_attempts FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch();
            if ($user) {
                $attempts = $user['failed_attempts'] + 1;
                $lockout_until = null;
                if ($attempts >= 5) {
                    $lockout_until = date('Y-m-d H:i:s', time() + 900); // 15 minutes lockout
                    log_activity($user['id'], 'account_lockout', 'Account locked due to 5 consecutive failed logins.');
                }
                $stmtUp = $pdo->prepare("UPDATE users SET failed_attempts = ?, lockout_until = ? WHERE id = ?");
                $stmtUp->execute([$attempts, $lockout_until, $user['id']]);
            }
        } catch (Exception $e) {
            // ignore
        }
    }
}

/**
 * Reset failed attempts on login success
 */
if (!function_exists('reset_failed_attempts')) {
    function reset_failed_attempts($email) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE email = ? OR username = ?");
            $stmt->execute([$email, $email]);
        } catch (Exception $e) {
            // ignore
        }
    }
}

/**
 * Enforce automatic session timeout (15 mins)
 */
if (!function_exists('handle_session_timeout')) {
    function handle_session_timeout() {
        if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
            return;
        }

        $timeout_duration = 900; // 15 minutes
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
            // Session expired!
            $uid = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
            if ($uid) {
                log_activity($uid, 'session_timeout', 'User session expired due to inactivity.');
            }
            
            // Clear all session variables
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            
            session_start();
            set_flash('warning', 'Your session has expired due to inactivity. Please log in again.');
            
            $current_script = basename($_SERVER['PHP_SELF']);
            $redirect_prefix = ($current_script === 'dashboard.php' || file_exists('login.php')) ? '' : '../';
            header("Location: " . $redirect_prefix . "login.php");
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
}

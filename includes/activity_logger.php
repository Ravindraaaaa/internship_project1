<?php
/**
 * Activity & Audit Logging Engine
 * Tracks user activity history and administrative audit trails.
 */

if (!class_exists('ActivityLogger')) {
    class ActivityLogger {
        private static $pdo = null;

        public static function init($pdo_conn = null) {
            if ($pdo_conn) {
                self::$pdo = $pdo_conn;
            } else {
                global $pdo;
                self::$pdo = $pdo;
            }
        }

        /**
         * Log user action into activity_logs
         */
        public static function log($user_id, $action, $category = 'general', $details = null) {
            self::init();
            if (!self::$pdo || !$user_id) return false;

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $browser = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 100);

            try {
                $stmt = self::$pdo->prepare("INSERT INTO activity_logs (user_id, action, category, details, ip_address, browser, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                return $stmt->execute([$user_id, $action, $category, $details, $ip, $browser]);
            } catch (Exception $e) {
                error_log("ActivityLogger::log error: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Log administrative audit action into admin_audit_logs
         */
        public static function logAudit($admin_id, $action, $affected_user_id = null, $old_val = null, $new_val = null) {
            self::init();
            if (!self::$pdo || !$admin_id) return false;

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $browser = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 100);

            if (is_array($old_val) || is_object($old_val)) $old_val = json_encode($old_val);
            if (is_array($new_val) || is_object($new_val)) $new_val = json_encode($new_val);

            try {
                $stmt = self::$pdo->prepare("INSERT INTO admin_audit_logs (admin_id, action, affected_user_id, ip_address, browser, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                return $stmt->execute([$admin_id, $action, $affected_user_id, $ip, $browser, $old_val, $new_val]);
            } catch (Exception $e) {
                error_log("ActivityLogger::logAudit error: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Fetch user activity history
         */
        public static function getUserHistory($user_id, $limit = 50, $offset = 0) {
            self::init();
            if (!self::$pdo || !$user_id) return [];

            try {
                $stmt = self::$pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $stmt->bindValue(1, (int)$user_id, PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
                $stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                return [];
            }
        }

        /**
         * Fetch admin audit log entries
         */
        public static function getAuditLogs($limit = 100, $offset = 0, $admin_id = null) {
            self::init();
            if (!self::$pdo) return [];

            try {
                $where = "";
                $params = [];
                if ($admin_id) {
                    $where = "WHERE a.admin_id = ?";
                    $params[] = $admin_id;
                }

                $sql = "SELECT a.*, u_admin.name as admin_name, u_aff.name as affected_name
                        FROM admin_audit_logs a
                        LEFT JOIN users u_admin ON a.admin_id = u_admin.id
                        LEFT JOIN users u_aff ON a.affected_user_id = u_aff.id
                        {$where}
                        ORDER BY a.created_at DESC
                        LIMIT {$limit} OFFSET {$offset}";

                $stmt = self::$pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                return [];
            }
        }
    }
}
?>

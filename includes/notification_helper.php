<?php
/**
 * Modern Notification Engine Helper
 * Handles unified dispatching, category filtering, preferences check, and role broadcasts.
 */

if (!class_exists('NotificationEngine')) {
    class NotificationEngine {
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
         * Dispatch notification to a single target user
         */
        public static function send($data) {
            self::init();
            if (!self::$pdo) return false;

            $user_id       = $data['user_id'] ?? null;
            $sender_id     = $data['sender_id'] ?? null;
            $receiver_id   = $data['receiver_id'] ?? $user_id;
            $receiver_role = $data['receiver_role'] ?? null;
            $type          = $data['type'] ?? 'info';
            $category      = $data['category'] ?? 'system';
            $title         = trim($data['title'] ?? 'Notification');
            $message       = trim($data['message'] ?? '');
            $icon          = $data['icon'] ?? 'bell';
            $color         = $data['color'] ?? 'indigo';
            $url           = $data['url'] ?? $data['link'] ?? null;
            $priority      = $data['priority'] ?? 'medium';

            if (!$receiver_id && !$receiver_role) return false;

            // Check receiver preference if single target
            if ($receiver_id) {
                if (!self::checkPreference($receiver_id, $category)) {
                    return false; // User muted this category
                }
            }

            try {
                $stmt = self::$pdo->prepare("INSERT INTO notifications 
                    (user_id, sender_id, receiver_id, receiver_role, type, category, title, message, icon, color, url, link, priority, is_read, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
                
                $success = $stmt->execute([
                    $receiver_id, $sender_id, $receiver_id, $receiver_role,
                    $type, $category, $title, $message, $icon, $color, $url, $url, $priority
                ]);

                if ($success) {
                    $notif_id = self::$pdo->lastInsertId();
                    self::logDelivery($notif_id, $receiver_id ?? 0, 'in_app', 'delivered');
                }

                return $success;
            } catch (Exception $e) {
                error_log("NotificationEngine::send error: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Broadcast notification to a specific role (e.g. 'student', 'alumni', 'admin')
         */
        public static function sendToRole($role, $data) {
            self::init();
            if (!self::$pdo) return false;

            $data['receiver_role'] = $role;
            $data['user_id'] = null;
            $data['receiver_id'] = null;

            return self::send($data);
        }

        /**
         * Broadcast to all active users
         */
        public static function broadcastAll($data) {
            return self::sendToRole('all', $data);
        }

        /**
         * Check user preference for a notification category
         */
        public static function checkPreference($user_id, $category) {
            self::init();
            if (!self::$pdo || !$user_id) return true;

            $column = 'system';
            switch (strtolower($category)) {
                case 'messages':
                case 'chat':
                    $column = 'chat_notif';
                    break;
                case 'announcements':
                    $column = 'announcement_notif';
                    break;
                case 'jobs':
                case 'internships':
                    $column = 'job_notif';
                    break;
                case 'mentorship':
                    $column = 'mentorship_notif';
                    break;
                case 'applications':
                    $column = 'application_notif';
                    break;
                case 'security':
                    $column = 'security_notif';
                    break;
                default:
                    return true;
            }

            try {
                $stmt = self::$pdo->prepare("SELECT {$column} FROM notification_preferences WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $val = $stmt->fetchColumn();
                return ($val === false) ? true : (bool)$val;
            } catch (Exception $e) {
                return true;
            }
        }

        /**
         * Get user notifications with filtering, category, search, sorting, and pagination
         */
        public static function getUserNotifications($user_id, $role = 'student', $options = []) {
            self::init();
            if (!self::$pdo || !$user_id) return ['items' => [], 'unread' => 0, 'total' => 0];

            $category = $options['category'] ?? 'all';
            $unread_only = !empty($options['unread_only']);
            $search = trim($options['search'] ?? '');
            $sort = $options['sort'] ?? 'newest';
            $limit = (int)($options['limit'] ?? 20);
            $offset = (int)($options['offset'] ?? 0);

            try {
                // 1. Unread count
                $unreadSql = "SELECT COUNT(*) FROM notifications 
                              WHERE (user_id = ? OR receiver_id = ? OR receiver_role = ? OR receiver_role = 'all')
                              AND ( (user_id = ? AND is_read = 0) OR (user_id IS NULL AND id NOT IN (SELECT notification_id FROM notification_reads WHERE user_id = ?)) )";
                $stmtUnread = self::$pdo->prepare($unreadSql);
                $stmtUnread->execute([$user_id, $user_id, $role, $user_id, $user_id]);
                $unreadCount = (int)$stmtUnread->fetchColumn();

                // 2. Fetch items
                $where = ["(n.user_id = ? OR n.receiver_id = ? OR n.receiver_role = ? OR n.receiver_role = 'all')"];
                $params = [$user_id, $user_id, $role];

                if ($category !== 'all') {
                    $where[] = "n.category = ?";
                    $params[] = $category;
                }

                if ($unread_only) {
                    $where[] = "( (n.user_id = ? AND n.is_read = 0) OR (n.user_id IS NULL AND n.id NOT IN (SELECT notification_id FROM notification_reads WHERE user_id = ?)) )";
                    $params[] = $user_id;
                    $params[] = $user_id;
                }

                if ($search !== '') {
                    $where[] = "(n.title LIKE ? OR n.message LIKE ?)";
                    $params[] = '%' . $search . '%';
                    $params[] = '%' . $search . '%';
                }

                $orderBy = "n.created_at DESC";
                if ($sort === 'oldest') {
                    $orderBy = "n.created_at ASC";
                } elseif ($sort === 'priority') {
                    $orderBy = "FIELD(n.priority, 'high', 'medium', 'low') ASC, n.created_at DESC";
                } elseif ($sort === 'unread') {
                    $orderBy = "n.is_read ASC, n.created_at DESC";
                }

                $whereSql = implode(' AND ', $where);

                $sql = "SELECT n.*, 
                               CASE 
                                 WHEN n.user_id IS NULL THEN (SELECT COUNT(*) FROM notification_reads nr WHERE nr.notification_id = n.id AND nr.user_id = ?) > 0
                                 ELSE n.is_read = 1 
                               END as is_read_status,
                               u.name as sender_name
                        FROM notifications n
                        LEFT JOIN users u ON n.sender_id = u.id
                        WHERE {$whereSql}
                        ORDER BY {$orderBy}
                        LIMIT {$limit} OFFSET {$offset}";

                // Insert user_id for the CASE subquery at beginning of select
                array_unshift($params, $user_id);

                $stmt = self::$pdo->prepare($sql);
                $stmt->execute($params);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($items as &$item) {
                    $item['is_read'] = (int)$item['is_read_status'];
                    $item['time_ago'] = self::timeAgo($item['created_at']);
                    $item['url'] = $item['url'] ?? $item['link'] ?? '#';
                }

                return [
                    'items' => $items,
                    'unread' => $unreadCount,
                    'total' => count($items)
                ];
            } catch (Exception $e) {
                error_log("getUserNotifications error: " . $e->getMessage());
                return ['items' => [], 'unread' => 0, 'total' => 0];
            }
        }

        /**
         * Mark notification as read
         */
        public static function markAsRead($notif_id, $user_id) {
            self::init();
            if (!self::$pdo || !$notif_id || !$user_id) return false;

            try {
                // Check if direct notification
                $stmt = self::$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notif_id, $user_id]);

                if ($stmt->rowCount() === 0) {
                    // It might be a broadcast notification -> record in notification_reads
                    $stmtRead = self::$pdo->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at) VALUES (?, ?, NOW())");
                    $stmtRead->execute([$notif_id, $user_id]);
                }
                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        /**
         * Mark all notifications as read for user
         */
        public static function markAllAsRead($user_id, $role = 'student') {
            self::init();
            if (!self::$pdo || !$user_id) return false;

            try {
                // Mark direct notifications
                $stmt = self::$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$user_id]);

                // Insert all broadcast IDs into notification_reads for user
                $stmtBc = self::$pdo->prepare("SELECT id FROM notifications WHERE (receiver_role = ? OR receiver_role = 'all') AND user_id IS NULL");
                $stmtBc->execute([$role]);
                $bcIds = $stmtBc->fetchAll(PDO::FETCH_COLUMN);

                $stmtInsertRead = self::$pdo->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at) VALUES (?, ?, NOW())");
                foreach ($bcIds as $bId) {
                    $stmtInsertRead->execute([$bId, $user_id]);
                }
                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        /**
         * Delete notification
         */
        public static function deleteNotification($notif_id, $user_id) {
            self::init();
            if (!self::$pdo || !$notif_id || !$user_id) return false;

            try {
                $stmt = self::$pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$notif_id, $user_id]);
                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        /**
         * Private helper: Log delivery channel
         */
        private static function logDelivery($notif_id, $user_id, $channel = 'in_app', $status = 'delivered') {
            try {
                $stmt = self::$pdo->prepare("INSERT INTO notification_delivery_log (notification_id, user_id, channel, status, delivered_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$notif_id, $user_id, $channel, $status]);
            } catch (Exception $e) {}
        }

        /**
         * Helper: Relative time string
         */
        public static function timeAgo($datetime) {
            $time = strtotime($datetime);
            if (!$time) return 'Just now';
            $diff = time() - $time;
            if ($diff < 60) return 'Just now';
            if ($diff < 3600) return floor($diff / 60) . 'm ago';
            if ($diff < 86400) return floor($diff / 3600) . 'h ago';
            if ($diff < 604800) return floor($diff / 86400) . 'd ago';
            return date('M j, Y', $time);
        }
    }
}
?>

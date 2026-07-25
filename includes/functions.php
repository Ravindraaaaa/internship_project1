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

if (!function_exists('get_event_schedule_status')) {
    /**
     * Determines event or schedule status based on current time:
     * - 'running' (Live Now): Current time between start_date and end_date (or within 4h window of start_date)
     * - 'upcoming': Current time before start_date
     * - 'ended': Current time after end_date (or past start_date if no end_date)
     */
    function get_event_schedule_status($start_date_str, $end_date_str = null) {
        $now = time();
        $start = strtotime($start_date_str);
        
        if (empty($end_date_str)) {
            // Default 4 hours duration window if no end_date specified
            $end = $start + (4 * 3600);
        } else {
            $end = strtotime($end_date_str);
        }

        if ($now >= $start && $now <= $end) {
            return 'running';
        } elseif ($now < $start) {
            return 'upcoming';
        } else {
            return 'ended';
        }
    }
}

if (!function_exists('render_event_status_badge')) {
    /**
     * Renders professional status badge & date badges for events / schedules:
     * Running: Green Live Badge + Red End Date Highlight
     * Upcoming: Electric Blue/Purple Badge + Muted End Date
     * Ended: Gray Ended Badge + Muted Red End Date
     */
    function render_event_status_badge($start_date_str, $end_date_str = null) {
        $status = get_event_schedule_status($start_date_str, $end_date_str);
        $start_fmt = date('M d, Y - h:i A', strtotime($start_date_str));
        $end_fmt = !empty($end_date_str) ? date('M d, Y - h:i A', strtotime($end_date_str)) : null;

        $html = '<div class="event-schedule-status-container" style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; margin-bottom:0.75rem;">';

        if ($status === 'running') {
            $html .= '<span class="status-badge status-running" style="background: rgba(16, 185, 129, 0.18); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.45); padding: 0.35rem 0.8rem; border-radius: 50px; font-weight: 700; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 0 14px rgba(16, 185, 129, 0.25);">';
            $html .= '<span class="pulse-live-dot" style="width: 9px; height: 9px; background: #10b981; border-radius: 50%; display: inline-block; animation: pulseGreen 1.5s infinite; box-shadow: 0 0 8px #10b981;"></span>';
            $html .= 'RUNNING / LIVE NOW</span>';

            $html .= '<span class="date-badge date-start-green" style="background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.25); padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">';
            $html .= '<i class="fa-solid fa-play" style="font-size:0.7rem; margin-right:0.35rem; color:#10b981;"></i> Start: ' . $start_fmt . '</span>';

            if ($end_fmt) {
                $html .= '<span class="date-badge date-end-red" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.5); padding: 0.25rem 0.7rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 0 10px rgba(239, 68, 68, 0.25);">';
                $html .= '<i class="fa-solid fa-clock" style="font-size:0.7rem; margin-right:0.35rem; color: #ef4444;"></i> End Date: ' . $end_fmt . '</span>';
            } else {
                $html .= '<span class="date-badge date-end-red" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.5); padding: 0.25rem 0.7rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">';
                $html .= '<i class="fa-solid fa-clock" style="font-size:0.7rem; margin-right:0.35rem; color: #ef4444;"></i> End Date: Active Today</span>';
            }
        } elseif ($status === 'upcoming') {
            $html .= '<span class="status-badge status-upcoming" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.35); padding: 0.35rem 0.8rem; border-radius: 50px; font-weight: 700; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 0.45rem;">';
            $html .= '<i class="fa-solid fa-calendar-day" style="color:#60a5fa;"></i> UPCOMING</span>';

            $html .= '<span class="date-badge date-blue" style="background: rgba(59, 130, 246, 0.1); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.25); padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">';
            $html .= '<i class="fa-solid fa-calendar-check" style="font-size:0.7rem; margin-right:0.35rem; color:#3b82f6;"></i> Start: ' . $start_fmt . '</span>';

            if ($end_fmt) {
                $html .= '<span class="date-badge date-muted-blue" style="background: rgba(148, 163, 184, 0.1); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.2); padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.75rem;">';
                $html .= '<i class="fa-solid fa-flag-checkered" style="font-size:0.7rem; margin-right:0.35rem; color:#818cf8;"></i> End: ' . $end_fmt . '</span>';
            }
        } else {
            $html .= '<span class="status-badge status-ended" style="background: rgba(148, 163, 184, 0.12); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.25); padding: 0.35rem 0.8rem; border-radius: 50px; font-weight: 700; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 0.45rem;">';
            $html .= '<i class="fa-solid fa-circle-stop" style="color:#94a3b8;"></i> ENDED</span>';

            $html .= '<span class="date-badge date-ended-gray" style="background: rgba(148, 163, 184, 0.08); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.2); padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.75rem;">';
            $html .= '<i class="fa-solid fa-calendar-xmark" style="font-size:0.7rem; margin-right:0.35rem;"></i> Held: ' . $start_fmt . '</span>';

            if ($end_fmt) {
                $html .= '<span class="date-badge date-end-red-muted" style="background: rgba(239, 68, 68, 0.12); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">';
                $html .= '<i class="fa-solid fa-clock-rotate-left" style="font-size:0.7rem; margin-right:0.35rem; color: #ef4444;"></i> Ended: ' . $end_fmt . '</span>';
            }
        }

        $html .= '</div>';
        return $html;
    }
}
?>

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
    function create_notification($user_id, $title, $message, $type = 'info', $priority = 'medium', $link = null, $category = 'system', $sender_id = null) {
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
            'sender_id' => $sender_id,
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
    function notify_admins($title, $message, $type = 'info', $priority = 'high', $link = null, $sender_id = null) {
        return NotificationEngine::sendToRole('admin', [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'category' => 'system',
            'priority' => $priority,
            'url' => $link,
            'sender_id' => $sender_id
        ]);
    }
}

if (!function_exists('notify_all_users')) {
    /**
     * Dispatches a broadcast notification to all users (or filtered by role).
     */
    function notify_all_users($title, $message, $type = 'info', $priority = 'medium', $role_filter = null, $link = null, $sender_id = null) {
        $targetRole = $role_filter ?: 'all';
        return NotificationEngine::sendToRole($targetRole, [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'category' => 'announcements',
            'priority' => $priority,
            'url' => $link,
            'sender_id' => $sender_id
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

if (!function_exists('generate_ai_support_reply')) {
    /**
     * Generates a professional AI support email response based on topic/query.
     */
    function generate_ai_support_reply($user_name, $user_email, $subject, $message, $category = 'Support Ticket') {
        global $pdo;
        
        $gemini_api_key = '';
        if ($pdo) {
            try {
                $stmtKey = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'gemini_api_key'");
                $stmtKey->execute();
                $gemini_api_key = $stmtKey->fetchColumn() ?: '';
            } catch (Exception $e) {}
        }
        
        $ai_response = '';
        
        // 1. Try Gemini API if key is present
        if (!empty($gemini_api_key)) {
            $prompt = "You are the AlumniNet Senior Technical Support AI Agent. A member named {$user_name} ({$user_email}) submitted a {$category} with Subject: '{$subject}' and Message: '{$message}'. Provide an extremely polite, highly professional, step-by-step resolution and official response email. Keep it empathetic, well-formatted, and concise (under 200 words).";
            
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $gemini_api_key;
            $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            $result = curl_exec($ch);
            curl_close($ch);
            
            if ($result) {
                $json = json_decode($result, true);
                if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                    $ai_response = trim($json['candidates'][0]['content']['parts'][0]['text']);
                }
            }
        }
        
        // 2. Intelligent Rule Engine Fallback if API Key not present or API call fails
        if (empty($ai_response)) {
            $msg_lower = strtolower($subject . ' ' . $message);
            
            if (strpos($msg_lower, 'job') !== false || strpos($msg_lower, 'career') !== false || strpos($msg_lower, 'referral') !== false) {
                $solution = "Regarding your inquiry about career opportunities and referrals, our placement algorithm updates active job postings every hour. You can filter opportunities by full-time or internship criteria directly on the Jobs portal and connect with relevant alumni for internal recommendations.";
            } elseif (strpos($msg_lower, 'event') !== false || strpos($msg_lower, 'webinar') !== false || strpos($msg_lower, 'session') !== false) {
                $solution = "Thank you for your question regarding campus events and workshops. Active event dates are color-coded in green in your Events Hub. Make sure to click 'RSVP Now' to secure your seat and receive automated reminders.";
            } elseif (strpos($msg_lower, 'connect') !== false || strpos($msg_lower, 'mentor') !== false || strpos($msg_lower, 'chat') !== false) {
                $solution = "For connection and mentorship inquiries, ensure your profile CGPA, technical skills, and resume are updated. Once you send a connection request, the alumnus receives an instant high-priority alert and can initiate a 1-on-1 chat session upon acceptance.";
            } elseif (strpos($msg_lower, 'resume') !== false || strpos($msg_lower, 'score') !== false || strpos($msg_lower, 'ats') !== false) {
                $solution = "Our AI Resume Builder rates your document out of 100 based on modern ATS screening standards. Use the '✨ AI Generate Bio' button to craft a high-impact summary and complete your experience section to maximize your score.";
            } else {
                $solution = "Our technical operations team has logged your submission regarding '{$subject}'. We have initiated a review of your request to ensure optimal platform experience.";
            }
            
            $ai_response = "Dear {$user_name},\n\nThank you for contacting the AlumniNet Intelligent Support Center regarding '{$subject}'.\n\n{$solution}\n\nOur system will monitor your ticket status. If you require further immediate assistance, feel free to submit additional details.\n\nBest regards,\nAlumniNet Intelligent Support Team";
        }
        
        return $ai_response;
    }
}

if (!function_exists('get_company_logo_url')) {
    /**
     * Resolves high-res company brand logo URL for display on alumni cards and profiles.
     */
    function get_company_logo_url($company) {
        $comp = strtolower(trim($company ?? ''));
        if (empty($comp) || $comp === 'independent' || $comp === 'n/a' || $comp === 'none' || $comp === 'campus' || $comp === 'student') {
            return null;
        }

        // Domain mapping dictionary for popular companies
        $domain_map = [
            'google' => 'google.com',
            'tcs' => 'tcs.com',
            'tata consultancy services' => 'tcs.com',
            'infosys' => 'infosys.com',
            'wipro' => 'wipro.com',
            'microsoft' => 'microsoft.com',
            'amazon' => 'amazon.com',
            'accenture' => 'accenture.com',
            'cognizant' => 'cognizant.com',
            'capgemini' => 'capgemini.com',
            'hcl' => 'hcltech.com',
            'hcltech' => 'hcltech.com',
            'ibm' => 'ibm.com',
            'oracle' => 'oracle.com',
            'tech mahindra' => 'techmahindra.com',
            'l&t' => 'larsentoubro.com',
            'meta' => 'meta.com',
            'facebook' => 'meta.com',
            'apple' => 'apple.com',
            'netflix' => 'netflix.com',
            'adobe' => 'adobe.com',
            'salesforce' => 'salesforce.com',
            'uber' => 'uber.com',
            'swiggy' => 'swiggy.com',
            'zomato' => 'zomato.com',
            'paytm' => 'paytm.com',
            'stripe' => 'stripe.com',
            'cisco' => 'cisco.com',
            'intel' => 'intel.com',
            'nvidia' => 'nvidia.com',
            'samsung' => 'samsung.com',
            'persistent' => 'persistent.com',
            'persistent systems' => 'persistent.com'
        ];

        $domain = $domain_map[$comp] ?? null;
        if (!$domain) {
            $clean = preg_replace('/[^a-z0-9]/', '', $comp);
            if (!empty($clean)) {
                $domain = $clean . '.com';
            }
        }

        if ($domain) {
            return "https://logo.clearbit.com/" . $domain;
        }

        return null;
    }
}
?>

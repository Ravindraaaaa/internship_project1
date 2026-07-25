<?php
$host = 'localhost';
$db   = 'internship_project1';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('check_csrf')) {
    function check_csrf($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Self-healing schema check for missing columns across database tables
try {
    // 1. Check & add cgpa in student_profiles
    $checkStudentTable = $pdo->query("SHOW TABLES LIKE 'student_profiles'")->fetch();
    if ($checkStudentTable) {
        $checkCgpa = $pdo->query("SHOW COLUMNS FROM student_profiles LIKE 'cgpa'")->fetch();
        if (!$checkCgpa) {
            $pdo->exec("ALTER TABLE student_profiles ADD COLUMN cgpa DECIMAL(4,2) DEFAULT 0.00");
        } else {
            // Ensure it's DECIMAL(4,2) so 10.00 doesn't crash
            $pdo->exec("ALTER TABLE student_profiles MODIFY COLUMN cgpa DECIMAL(4,2) DEFAULT 0.00");
        }
    }

    // 2. Check & add last_active and two_factor_secret in users
    $checkUsersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($checkUsersTable) {
        $checkLastActive = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_active'")->fetch();
        if (!$checkLastActive) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL");
        }
        $check2FA = $pdo->query("SHOW COLUMNS FROM users LIKE 'two_factor_secret'")->fetch();
        if (!$check2FA) {
            $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(255) NULL DEFAULT NULL");
        }
        $checkPhone = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
        if (!$checkPhone) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
        }
        $checkIsBot = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_bot'")->fetch();
        if (!$checkIsBot) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_bot TINYINT(1) DEFAULT 0");
        }
    }

    // 3. Check & create ai_chats table if missing
    $checkAiChatsTable = $pdo->query("SHOW TABLES LIKE 'ai_chats'")->fetch();
    if (!$checkAiChatsTable) {
        $pdo->exec("CREATE TABLE ai_chats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            query TEXT NOT NULL,
            response TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 4. Check & add columns to notifications table if missing
    $checkNotificationsTable = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetch();
    if ($checkNotificationsTable) {
        $cols = [
            'sender_id' => "INT DEFAULT NULL",
            'receiver_id' => "INT DEFAULT NULL",
            'receiver_role' => "VARCHAR(50) DEFAULT NULL",
            'type' => "VARCHAR(50) DEFAULT 'info'",
            'category' => "VARCHAR(50) DEFAULT 'system'",
            'icon' => "VARCHAR(50) DEFAULT 'bell'",
            'color' => "VARCHAR(30) DEFAULT 'indigo'",
            'url' => "VARCHAR(255) DEFAULT NULL",
            'link' => "VARCHAR(255) DEFAULT NULL",
            'priority' => "VARCHAR(50) DEFAULT 'medium'",
            'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        foreach ($cols as $col => $definition) {
            $checkCol = $pdo->query("SHOW COLUMNS FROM notifications LIKE '$col'")->fetch();
            if (!$checkCol) {
                $pdo->exec("ALTER TABLE notifications ADD COLUMN $col $definition");
            }
        }
    } else {
        $pdo->exec("CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            sender_id INT DEFAULT NULL,
            receiver_id INT DEFAULT NULL,
            receiver_role VARCHAR(50) DEFAULT NULL,
            type VARCHAR(50) DEFAULT 'info',
            category VARCHAR(50) DEFAULT 'system',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            icon VARCHAR(50) DEFAULT 'bell',
            color VARCHAR(30) DEFAULT 'indigo',
            url VARCHAR(255) DEFAULT NULL,
            link VARCHAR(255) DEFAULT NULL,
            priority VARCHAR(50) DEFAULT 'medium',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_receiver (receiver_id, receiver_role),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 5. Check & create notification_reads table
    $checkNotifReads = $pdo->query("SHOW TABLES LIKE 'notification_reads'")->fetch();
    if (!$checkNotifReads) {
        $pdo->exec("CREATE TABLE notification_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NOT NULL,
            user_id INT NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_notif_user (notification_id, user_id),
            INDEX idx_user_read (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 6. Check & create notification_preferences table
    $checkNotifPref = $pdo->query("SHOW TABLES LIKE 'notification_preferences'")->fetch();
    if (!$checkNotifPref) {
        $pdo->exec("CREATE TABLE notification_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            chat_notif TINYINT(1) DEFAULT 1,
            announcement_notif TINYINT(1) DEFAULT 1,
            job_notif TINYINT(1) DEFAULT 1,
            mentorship_notif TINYINT(1) DEFAULT 1,
            application_notif TINYINT(1) DEFAULT 1,
            security_notif TINYINT(1) DEFAULT 1,
            email_notif TINYINT(1) DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 7. Check & create notification_delivery_log table
    $checkDeliveryLog = $pdo->query("SHOW TABLES LIKE 'notification_delivery_log'")->fetch();
    if (!$checkDeliveryLog) {
        $pdo->exec("CREATE TABLE notification_delivery_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NOT NULL,
            user_id INT NOT NULL,
            channel VARCHAR(50) DEFAULT 'in_app',
            status VARCHAR(50) DEFAULT 'delivered',
            delivered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 8. Check & create announcement_views table
    $checkAnncViews = $pdo->query("SHOW TABLES LIKE 'announcement_views'")->fetch();
    if (!$checkAnncViews) {
        $pdo->exec("CREATE TABLE announcement_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            announcement_id INT NOT NULL,
            user_id INT NOT NULL,
            device VARCHAR(100) DEFAULT NULL,
            browser VARCHAR(100) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'seen',
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_duration INT DEFAULT 0,
            INDEX idx_annc_user (announcement_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 9. Check & create announcement_history table
    $checkAnncHist = $pdo->query("SHOW TABLES LIKE 'announcement_history'")->fetch();
    if (!$checkAnncHist) {
        $pdo->exec("CREATE TABLE announcement_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            announcement_id INT NOT NULL,
            admin_id INT DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 10. Check & create activity_logs table
    $checkActLogs = $pdo->query("SHOW TABLES LIKE 'activity_logs'")->fetch();
    if ($checkActLogs) {
        $checkCategory = $pdo->query("SHOW COLUMNS FROM activity_logs LIKE 'category'")->fetch();
        if (!$checkCategory) {
            $pdo->exec("ALTER TABLE activity_logs ADD COLUMN category VARCHAR(50) DEFAULT 'general'");
        }
        $checkIp = $pdo->query("SHOW COLUMNS FROM activity_logs LIKE 'ip_address'")->fetch();
        if (!$checkIp) {
            $pdo->exec("ALTER TABLE activity_logs ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL");
        }
        $checkBrowser = $pdo->query("SHOW COLUMNS FROM activity_logs LIKE 'browser'")->fetch();
        if (!$checkBrowser) {
            $pdo->exec("ALTER TABLE activity_logs ADD COLUMN browser VARCHAR(100) DEFAULT NULL");
        }
    } else {
        $pdo->exec("CREATE TABLE activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            action VARCHAR(255) NOT NULL,
            category VARCHAR(50) DEFAULT 'general',
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            browser VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_act (user_id),
            INDEX idx_act_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 11. Check & create admin_audit_logs table
    $checkAuditLogs = $pdo->query("SHOW TABLES LIKE 'admin_audit_logs'")->fetch();
    if (!$checkAuditLogs) {
        $pdo->exec("CREATE TABLE admin_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            affected_user_id INT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            browser VARCHAR(100) DEFAULT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_audit (admin_id),
            INDEX idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 12. Check & create notification_templates table
    $checkTemplates = $pdo->query("SHOW TABLES LIKE 'notification_templates'")->fetch();
    if (!$checkTemplates) {
        $pdo->exec("CREATE TABLE notification_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL UNIQUE,
            title_template VARCHAR(255) NOT NULL,
            message_template TEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'system',
            icon VARCHAR(50) DEFAULT 'bell',
            color VARCHAR(30) DEFAULT 'indigo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 13. Check & add progress in user_skills if missing
    $checkUserSkillsTable = $pdo->query("SHOW TABLES LIKE 'user_skills'")->fetch();
    if ($checkUserSkillsTable) {
        $checkProgress = $pdo->query("SHOW COLUMNS FROM user_skills LIKE 'progress'")->fetch();
        if (!$checkProgress) {
            $pdo->exec("ALTER TABLE user_skills ADD COLUMN progress INT DEFAULT 0");
        }
    }
} catch (Exception $e) {
    // fail-silent during uninitialized database setup
}

// Update last active timestamp for online tracking
$current_session_user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
if ($current_session_user_id) {
    try {
        $stmtActive = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $stmtActive->execute([$current_session_user_id]);
    } catch (Exception $e) {
        // fail-silent
    }
}


// ==================== GLOBAL SMTP CONFIGURATION IN DB.PHP ====================
if (!defined('SMTP_ENABLED')) define('SMTP_ENABLED', true);
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 465);
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'ssl');
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'alumninethelp@gmail.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'shwjemrwlywyoqzl');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'alumninethelp@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'AlumniNet Security Team');

$smtp_config_file = __DIR__ . '/smtp.php';
$GLOBALS['smtp_config'] = file_exists($smtp_config_file) ? require $smtp_config_file : [
    'enabled'     => SMTP_ENABLED,
    'host'        => SMTP_HOST,
    'port'        => SMTP_PORT,
    'encryption'  => SMTP_ENCRYPTION,
    'username'    => SMTP_USERNAME,
    'password'    => SMTP_PASSWORD,
    'from_email'  => SMTP_FROM_EMAIL,
    'from_name'   => SMTP_FROM_NAME
];

$mailer_helper_file = __DIR__ . '/../includes/mailer_helper.php';
if (file_exists($mailer_helper_file)) {
    require_once $mailer_helper_file;
}

if (!function_exists('send_password_reset_email')) {
    function send_password_reset_email($recipient_email, $token) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_dir = dirname($_SERVER['PHP_SELF'] ?? '');
        $base_url = rtrim($protocol . $host . $script_dir, '/\\');
        
        if (strpos($base_url, 'reset_password.php') !== false || strpos($base_url, 'forgot_password.php') !== false) {
            $base_url = dirname($base_url);
        }
        $reset_link = rtrim($base_url, '/\\') . "/reset_password.php?token=" . $token;

        $subject = "Password Reset Request - AlumniNet Portal";
        $html_body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: "Segoe UI", Arial, sans-serif; background-color: #090d16; color: #f8fafc; padding: 20px; margin: 0; }
                .card { background-color: #0f172a; border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 14px; max-width: 520px; margin: auto; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                .logo { font-size: 24px; font-weight: bold; color: #818cf8; margin-bottom: 20px; display: inline-block; text-decoration: none; }
                .btn { display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; margin: 20px 0; }
                .footer { font-size: 12px; color: #94a3b8; margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="logo">🎓 AlumniNet</div>
                <h2 style="color: #ffffff; margin-top: 0;">Password Reset Request</h2>
                <p style="color: #94a3b8; font-size: 14px;">Hello,</p>
                <p style="color: #cbd5e1; font-size: 14px;">We received a request to reset the password for your account (<strong>'.htmlspecialchars($recipient_email).'</strong>).</p>
                <p style="color: #cbd5e1; font-size: 14px;">Click the button below to establish a new password:</p>
                <p><a href="' . $reset_link . '" class="btn" target="_blank">Reset Password Now</a></p>
                <p style="font-size: 13px; color: #94a3b8; margin-top: 20px;">Or copy and paste this link into your browser:<br><a href="' . $reset_link . '" style="color: #38bdf8; word-break: break-all;">' . $reset_link . '</a></p>
                <div class="footer">
                    This link will expire in 1 hour.<br>&copy; ' . date('Y') . ' AlumniNet Platform. Security & Identity Service.
                </div>
            </div>
        </body>
        </html>';

        return send_smtp_email($recipient_email, $subject, $html_body);
    }
}

if (!function_exists('send_signup_otp_email')) {
    function send_signup_otp_email($recipient_email, $otp_code) {
        $subject = "AlumniNet - Verification OTP Code";
        $html_body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: "Segoe UI", Arial, sans-serif; background-color: #090d16; color: #f8fafc; padding: 20px; margin: 0; }
                .card { background-color: #0f172a; border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 14px; max-width: 520px; margin: auto; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                .logo { font-size: 24px; font-weight: bold; color: #818cf8; margin-bottom: 20px; display: inline-block; text-decoration: none; }
                .otp-box { display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #ffffff; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); padding: 15px 30px; border-radius: 10px; margin: 20px 0; box-shadow: 0 5px 15px rgba(168, 85, 247, 0.4); }
                .footer { font-size: 12px; color: #94a3b8; margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="logo">🎓 AlumniNet</div>
                <h2 style="color: #ffffff; margin-top: 0;">Confirm Your Sign Up</h2>
                <p style="color: #cbd5e1; font-size: 14px;">Thank you for registering on AlumniNet! To complete your sign-up process, please enter the following 6-digit verification code on the verification page:</p>
                <div class="otp-box">' . htmlspecialchars($otp_code) . '</div>
                <p style="color: #94a3b8; font-size: 13px;">This verification code is valid for 5 minutes.</p>
                <div class="footer">
                    &copy; ' . date('Y') . ' AlumniNet Platform. Security & Identity Service.
                </div>
            </div>
        </body>
        </html>';

        return send_smtp_email($recipient_email, $subject, $html_body);
    }
}

if (!function_exists('send_2fa_otp_email')) {
    function send_2fa_otp_email($recipient_email, $otp_code) {
        $subject = "AlumniNet - 2FA Security Verification Code";
        $html_body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: "Segoe UI", Arial, sans-serif; background-color: #090d16; color: #f8fafc; padding: 20px; margin: 0; }
                .card { background-color: #0f172a; border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 14px; max-width: 520px; margin: auto; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                .logo { font-size: 24px; font-weight: bold; color: #818cf8; margin-bottom: 20px; display: inline-block; text-decoration: none; }
                .otp-box { display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #ffffff; background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); padding: 15px 30px; border-radius: 10px; margin: 20px 0; box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4); }
                .footer { font-size: 12px; color: #94a3b8; margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="logo">🎓 AlumniNet</div>
                <h2 style="color: #ffffff; margin-top: 0;">Two-Factor Authentication</h2>
                <p style="color: #cbd5e1; font-size: 14px;">We detected a login attempt. Please verify your identity by entering the following 6-digit 2FA verification code:</p>
                <div class="otp-box">' . htmlspecialchars($otp_code) . '</div>
                <p style="color: #94a3b8; font-size: 13px;">This verification code is valid for 5 minutes.</p>
                <div class="footer">
                    If this wasn\'t you, please change your password immediately.<br>
                    &copy; ' . date('Y') . ' AlumniNet Platform. Security & Identity Service.
                </div>
            </div>
        </body>
        </html>';

        return send_smtp_email($recipient_email, $subject, $html_body);
    }
}

if (!function_exists('send_sms_otp')) {
    function send_sms_otp($phone_number, $otp_code) {
        if (empty($phone_number)) {
            return false;
        }

        $clean_phone = preg_replace('/[^0-9+]/', '', $phone_number);
        $message = "Your AlumniNet verification OTP code is: {$otp_code}. Valid for 5 minutes.";

        // Log to system error log
        error_log("[AlumniNet SMS OTP] Dispatched to {$clean_phone}: {$message}");

        // Save last sent SMS info in session for UI status display
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['last_sms_otp'] = [
                'phone' => $clean_phone,
                'code' => $otp_code,
                'sent_at' => date('Y-m-d H:i:s'),
                'status' => 'Dispatched'
            ];
        }

        // Optional integration for SMS Gateways (e.g. Fast2SMS / Twilio)
        if (defined('SMS_API_KEY') && !empty(SMS_API_KEY)) {
            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'route' => 'otp',
                        'variables_values' => $otp_code,
                        'numbers' => $clean_phone
                    ]),
                    CURLOPT_HTTPHEADER => [
                        "authorization: " . SMS_API_KEY,
                        "Content-Type: application/json"
                    ],
                    CURLOPT_TIMEOUT => 4
                ]);
                @curl_exec($ch);
                @curl_close($ch);
            } catch (Exception $e) {
                // Failover silently
            }
        }

        return true;
    }
}

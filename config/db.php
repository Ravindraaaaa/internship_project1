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
            $pdo->exec("ALTER TABLE student_profiles ADD COLUMN cgpa DECIMAL(3,2) DEFAULT 0.00");
        }
    }

    // 2. Check & add last_active in users
    $checkUsersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($checkUsersTable) {
        $checkLastActive = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_active'")->fetch();
        if (!$checkLastActive) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL");
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

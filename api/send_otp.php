<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/security_helper.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'resend_signup_otp') {
    if (!isset($_SESSION['otp_verify']) || $_SESSION['otp_verify']['type'] !== 'signup') {
        echo json_encode(['success' => false, 'message' => 'No active registration session found.']);
        exit;
    }
    
    // Generate new OTP
    $new_otp = sprintf('%06d', mt_rand(100000, 999999));
    $_SESSION['otp_verify']['code'] = $new_otp;
    $_SESSION['otp_verify']['expires_at'] = time() + 300; // 5 mins
    $_SESSION['otp_verify']['attempts'] = 0;
    
    // Dispatch via SMTP
    send_signup_otp_email($_SESSION['otp_verify']['email'], $new_otp);
    
    echo json_encode([
        'success' => true,
        'message' => 'A new OTP has been generated & sent!',
        'demo_otp' => $new_otp,
        'expires_in' => 300
    ]);
    exit;
}

if ($action === 'resend_2fa_otp') {
    if (!isset($_SESSION['2fa_pending']) || $_SESSION['2fa_pending'] !== true) {
        echo json_encode(['success' => false, 'message' => 'No active 2FA session found.']);
        exit;
    }
    
    $new_otp = sprintf('%06d', mt_rand(100000, 999999));
    $_SESSION['2fa_otp_code'] = $new_otp;
    $_SESSION['2fa_otp_expires'] = time() + 300;
    $_SESSION['2fa_attempts'] = 0;
    
    // Fetch email to dispatch
    $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['2fa_user_id']]);
    $uEmail = $stmtUser->fetchColumn();
    if ($uEmail) {
        send_2fa_otp_email($uEmail, $new_otp);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'A new 2FA code has been generated & sent!',
        'demo_otp' => $new_otp,
        'expires_in' => 300
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);

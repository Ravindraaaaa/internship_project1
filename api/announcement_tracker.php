<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = get_user_id();
$annc_id = (int)($_POST['announcement_id'] ?? $_GET['announcement_id'] ?? 0);
$duration = (int)($_POST['read_duration'] ?? 0);

if ($annc_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid announcement ID']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Basic device/browser parser
$device = (strpos($agent, 'Mobile') !== false) ? 'Mobile' : ((strpos($agent, 'Tablet') !== false) ? 'Tablet' : 'Desktop');
$browser = 'Browser';
if (strpos($agent, 'Chrome') !== false) $browser = 'Chrome';
elseif (strpos($agent, 'Firefox') !== false) $browser = 'Firefox';
elseif (strpos($agent, 'Safari') !== false) $browser = 'Safari';
elseif (strpos($agent, 'Edge') !== false) $browser = 'Edge';

try {
    $stmt = $pdo->prepare("INSERT INTO announcement_views (announcement_id, user_id, device, browser, ip_address, status, viewed_at, read_duration)
        VALUES (?, ?, ?, ?, ?, 'read', NOW(), ?)
        ON DUPLICATE KEY UPDATE read_duration = read_duration + VALUES(read_duration), status = 'read', viewed_at = NOW()");
    
    $stmt->execute([$annc_id, $user_id, $device, $browser, $ip, $duration]);

    echo json_encode(['status' => 'success', 'message' => 'Announcement view recorded']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

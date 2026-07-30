<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mailer_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';

try {
    $uid = 1;
    $user_name = "Test User";
    $category = "Account & Login";
    $priority = "Medium";
    $subject = "pp";
    $message = "pp";
    $attachment_path = null;

    $tkt_num = 'TKT-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO support_tickets (ticket_number, user_id, subject, category, priority, description, attachment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'New', NOW())");
    $stmt->execute([$tkt_num, $uid, $subject, $category, $priority, $message, $attachment_path]);

    echo "Success inserting ticket.\n";

    NotificationEngine::send([
        'user_id' => $uid,
        'type' => 'info',
        'category' => 'support',
        'title' => "Ticket Created ({$tkt_num})",
        'message' => "Your ticket '{$subject}' was logged successfully.",
        'icon' => 'headset',
        'color' => 'indigo'
    ]);
    
    echo "Success sending notification.\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security_helper.php';

try {
    $uid = 1;
    log_activity($uid, 'submitted_help_ticket', "Ticket: TKT-123");
    echo "Success log_activity.\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

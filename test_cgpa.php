<?php
require_once __DIR__ . '/includes/db.php';
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'test@test.com'");
    $stmt->execute();
    echo "DB connection test success";
} catch(Exception $e) {
    echo $e->getMessage();
}
?>

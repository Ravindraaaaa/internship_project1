<?php
require_once __DIR__ . '/includes/db.php';
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_profiles");
    $stmt->execute();
    echo "Success: student_profiles has " . $stmt->fetchColumn() . " records.";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

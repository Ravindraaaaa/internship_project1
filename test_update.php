<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 2; // Demo Student User
$_SESSION['role'] = 'student';

$_POST['action'] = 'update_profile';
$_POST['bio'] = 'Test bio update';
$_POST['linkedin'] = 'https://linkedin.com/in/test';
$_POST['current_year'] = '2';
$_POST['course'] = 'Information Technology';
$_POST['github'] = 'https://github.com/test';
$_POST['cgpa'] = '8.50';
$_POST['name'] = 'Demo Student User Updated';

try {
    $userId = 2;
    $currentYear = intval($_POST['current_year']);
    $course = trim($_POST['course']);
    $linkedin = trim($_POST['linkedin']);
    $github = trim($_POST['github']);
    $bio = trim($_POST['bio']);
    $cgpa = floatval($_POST['cgpa']);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() > 0) {
        $stmtUpdate = $pdo->prepare("UPDATE student_profiles SET current_year = ?, course = ?, linkedin = ?, github = ?, bio = ?, cgpa = ? WHERE user_id = ?");
        $stmtUpdate->execute([$currentYear, $course, $linkedin, $github, $bio, $cgpa, $userId]);
        echo "Update successful\n";
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO student_profiles (user_id, current_year, course, linkedin, github, bio, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtInsert->execute([$userId, $currentYear, $course, $linkedin, $github, $bio, $cgpa]);
        echo "Insert successful\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

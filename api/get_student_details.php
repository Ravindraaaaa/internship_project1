<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$student_id = intval($_GET['id'] ?? 0);
if ($student_id <= 0) {
    echo json_encode(['error' => 'Invalid student ID.']);
    exit;
}

try {
    // 1. Fetch core user info
    $stmtUser = $pdo->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id = ? AND role = 'student'");
    $stmtUser->execute([$student_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['error' => 'Student record not found.']);
        exit;
    }

    // 2. Fetch student profile
    $stmtProfile = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $stmtProfile->execute([$student_id]);
    $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC) ?: [];

    // 3. Fetch resume
    $stmtResume = $pdo->prepare("SELECT file_path, uploaded_at FROM resumes WHERE user_id = ?");
    $stmtResume->execute([$student_id]);
    $resume = $stmtResume->fetch(PDO::FETCH_ASSOC) ?: null;

    // 4. Fetch certificates
    $stmtCerts = $pdo->prepare("SELECT name, issuer, issue_date, file_path FROM user_certificates WHERE user_id = ? ORDER BY issue_date DESC");
    $stmtCerts->execute([$student_id]);
    $certificates = $stmtCerts->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch skills
    $stmtSkills = $pdo->prepare("SELECT s.name, us.progress FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ? ORDER BY us.progress DESC");
    $stmtSkills->execute([$student_id]);
    $skills = $stmtSkills->fetchAll(PDO::FETCH_ASSOC);

    // 6. Fetch achievements
    $stmtAch = $pdo->prepare("SELECT title, description, date_achieved FROM achievements WHERE user_id = ? ORDER BY date_achieved DESC");
    $stmtAch->execute([$student_id]);
    $achievements = $stmtAch->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'user' => $user,
        'profile' => $profile,
        'resume' => $resume,
        'certificates' => $certificates,
        'skills' => $skills,
        'achievements' => $achievements
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$user_id = get_user_id();
$type = $_POST['type'] ?? ''; // 'resume', 'certificate', 'avatar'
$name = trim($_POST['name'] ?? '');
$issuer = trim($_POST['issuer'] ?? '');
$issue_date = $_POST['issue_date'] ?? '';

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or file upload error occurred.']);
    exit;
}

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$fileSize = $_FILES['file']['size'];
$fileParts = explode(".", $fileName);
$fileExtension = strtolower(end($fileParts));

// Set up validations
$max_size = 5 * 1024 * 1024; // 5MB default
$allowed_exts = [];

if ($type === 'resume') {
    $allowed_exts = ['pdf', 'doc', 'docx'];
    $dest_folder = 'uploads/resumes/';
} elseif ($type === 'certificate') {
    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
    $dest_folder = 'uploads/certificates/';
    if (empty($name) || empty($issuer) || empty($issue_date)) {
        echo json_encode(['error' => 'Certificate name, issuer, and date are required.']);
        exit;
    }
} elseif ($type === 'avatar') {
    $allowed_exts = ['jpg', 'jpeg', 'png'];
    $dest_folder = 'uploads/profiles/';
    $max_size = 2 * 1024 * 1024; // 2MB for avatar
} else {
    echo json_encode(['error' => 'Invalid file upload type.']);
    exit;
}

if ($fileSize > $max_size) {
    echo json_encode(['error' => 'File size exceeds the allowed limit.']);
    exit;
}

if (!in_array($fileExtension, $allowed_exts)) {
    echo json_encode(['error' => 'Unsupported file format. Supported: ' . implode(', ', $allowed_exts)]);
    exit;
}

try {
    $absolute_dest_folder = __DIR__ . '/../' . $dest_folder;
    if (!is_dir($absolute_dest_folder)) {
        mkdir($absolute_dest_folder, 0755, true);
    }
    
    $newFileName = md5(time() . $user_id . $fileName) . '.' . $fileExtension;
    $relativePath = $dest_folder . $newFileName;
    $absolutePath = $absolute_dest_folder . $newFileName;
    
    if (move_uploaded_file($fileTmpPath, $absolutePath)) {
        $pdo->beginTransaction();
        
        if ($type === 'resume') {
            // Delete old resume file if exists
            $stmtOld = $pdo->prepare("SELECT file_path FROM resumes WHERE user_id = ?");
            $stmtOld->execute([$user_id]);
            $oldPath = $stmtOld->fetchColumn();
            if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
                @unlink(__DIR__ . '/../' . $oldPath);
            }
            
            // Save to resumes table
            $stmt = $pdo->prepare("INSERT INTO resumes (user_id, file_path, uploaded_at) VALUES (?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE file_path = ?, uploaded_at = NOW()");
            $stmt->execute([$user_id, $relativePath, $relativePath]);
            
            log_activity($user_id, 'upload_resume', 'Uploaded new resume.');
        } 
        
        elseif ($type === 'certificate') {
            // Save to user_certificates
            $stmt = $pdo->prepare("INSERT INTO user_certificates (user_id, name, issuer, issue_date, file_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $issuer, $issue_date, $relativePath]);
            
            log_activity($user_id, 'upload_certificate', "Uploaded certificate: '$name'.");
        } 
        
        elseif ($type === 'avatar') {
            // Update respective profile
            $role = $_SESSION['user_role'] ?? 'student';
            if ($role === 'alumni') {
                $stmtOld = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
                $stmtOld->execute([$user_id]);
                $oldPath = $stmtOld->fetchColumn();
                if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath) && strpos($oldPath, 'http') === false) {
                    @unlink(__DIR__ . '/../' . $oldPath);
                }
                
                $stmt = $pdo->prepare("UPDATE alumni_profiles SET profile_pic = ? WHERE user_id = ?");
                $stmt->execute([$relativePath, $user_id]);
            } else {
                $stmtOld = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
                $stmtOld->execute([$user_id]);
                $oldPath = $stmtOld->fetchColumn();
                if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath) && strpos($oldPath, 'http') === false) {
                    @unlink(__DIR__ . '/../' . $oldPath);
                }
                
                $stmt = $pdo->prepare("UPDATE student_profiles SET profile_pic = ? WHERE user_id = ?");
                $stmt->execute([$relativePath, $user_id]);
            }
            
            log_activity($user_id, 'upload_avatar', 'Updated profile picture.');
        }
        
        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'file_path' => $relativePath,
            'file_name' => $fileName
        ]);
    } else {
        echo json_encode(['error' => 'Failed to save uploaded file.']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}

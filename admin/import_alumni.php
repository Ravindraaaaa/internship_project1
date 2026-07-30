<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/admin.php';
require_once __DIR__ . '/../includes/auth_helper.php';
check_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Error uploading file.');
        header("Location: dashboard.php?tab=alumni");
        exit;
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        set_flash('error', 'Only CSV files are allowed.');
        header("Location: dashboard.php?tab=alumni");
        exit;
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        set_flash('error', 'Could not open the uploaded file.');
        header("Location: dashboard.php?tab=alumni");
        exit;
    }
    
    $success_count = 0;
    $skip_count = 0;
    $row_count = 0;
    
    $default_password = password_hash('Alumni@123', PASSWORD_DEFAULT);
    
    try {
        $pdo->beginTransaction();
        
        $stmtCheckEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheckUsername = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmtInsertUser = $pdo->prepare("INSERT INTO users (name, email, username, password, role, status) VALUES (?, ?, ?, ?, 'alumni', 'approved')");
        $stmtInsertProfile = $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, course, company, position) VALUES (?, ?, ?, ?, ?)");
        
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $row_count++;
            
            // Skip empty rows or header row
            if (count($data) < 4 || (strtolower(trim($data[0])) === 'name' && strtolower(trim($data[1])) === 'email')) {
                continue;
            }
            
            $name = trim($data[0] ?? '');
            $email = trim($data[1] ?? '');
            $grad_year = (int)trim($data[2] ?? 0);
            $course = trim($data[3] ?? '');
            $company = trim($data[4] ?? '');
            $position = trim($data[5] ?? '');
            
            if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$grad_year || !$course) {
                $skip_count++;
                continue; // Skip invalid rows
            }
            
            // Generate a username based on email
            $username_base = explode('@', $email)[0];
            $username = $username_base;
            $suffix = 1;
            
            $stmtCheckUsername->execute([$username]);
            while ($stmtCheckUsername->fetchColumn()) {
                $username = $username_base . $suffix;
                $suffix++;
                $stmtCheckUsername->execute([$username]);
            }
            
            // Check if email already exists
            $stmtCheckEmail->execute([$email]);
            if ($stmtCheckEmail->fetchColumn()) {
                $skip_count++;
                continue; // Skip existing users
            }
            
            // Insert user
            $stmtInsertUser->execute([$name, $email, $username, $default_password]);
            $user_id = $pdo->lastInsertId();
            
            // Insert profile
            $stmtInsertProfile->execute([$user_id, $grad_year, $course, $company, $position]);
            
            $success_count++;
        }
        
        $pdo->commit();
        
        if ($success_count > 0) {
            set_flash('success', "Successfully imported $success_count alumni. ($skip_count skipped)");
        } else {
            set_flash('error', "No valid new alumni found to import. ($skip_count skipped)");
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Database error during import: ' . $e->getMessage());
    }
    
    fclose($handle);
} else {
    set_flash('error', 'Invalid request.');
}

header("Location: dashboard.php?tab=alumni");
exit;

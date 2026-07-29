<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/SimpleXLSXReader.php';
require_once __DIR__ . '/../includes/SimplePDFReader.php';

require_admin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if ($action === 'preview' || $action === 'preview_ai') {
    if ($action === 'preview_ai') {
        $rows = json_decode($_POST['ai_data'] ?? '[]', true);
        $fileName = $_POST['filename'] ?? 'ai_extraction.txt';
    } else {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'File upload failed.']);
            exit;
        }

        $fileTmpPath = $_FILES['import_file']['tmp_name'];
        $fileName = $_FILES['import_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Parse File
        if ($ext === 'pdf') {
            $result = SimplePDFReader::parse($fileTmpPath);
        } else if (in_array($ext, ['csv', 'xlsx'])) {
            $result = SimpleXLSXReader::parse($fileTmpPath);
        } else {
            echo json_encode(['status' => 'error', 'message' => "You uploaded a .$ext file, but no Gemini API Key is configured. To extract data from images or unstructured files, please enter your Gemini API Key in the Universal AI Extractor section."]);
            exit;
        }
        if (isset($result['error'])) {
            echo json_encode(['status' => 'error', 'message' => $result['error']]);
            exit;
        }

        $rows = $result['data'];
    }

    if (empty($rows) || count($rows) < 2) { // Need header + at least 1 row
        echo json_encode(['status' => 'error', 'message' => 'File is empty or could not be parsed correctly.']);
        exit;
    }

    $headers = array_map('strtolower', array_map('trim', $rows[0]));
    
    // Map headers to indexes
    $hmap = [];
    foreach ($headers as $index => $h) {
        if (strpos($h, 'first name') !== false) $hmap['first_name'] = $index;
        if (strpos($h, 'last name') !== false) $hmap['last_name'] = $index;
        if (strpos($h, 'email') !== false) $hmap['email'] = $index;
        if (strpos($h, 'phone') !== false || strpos($h, 'mobile') !== false) $hmap['phone'] = $index;
        if (strpos($h, 'grad') !== false && strpos($h, 'year') !== false) $hmap['grad_year'] = $index;
        if (strpos($h, 'course') !== false) $hmap['course'] = $index;
        if (strpos($h, 'enrollment') !== false || strpos($h, 'alumni id') !== false || strpos($h, 'student id') !== false) $hmap['enrollment_id'] = $index;
        if (strpos($h, 'company') !== false) $hmap['company'] = $index;
        if (strpos($h, 'position') !== false) $hmap['position'] = $index;
        if (strpos($h, 'industry') !== false) $hmap['industry'] = $index;
        if (strpos($h, 'linkedin') !== false) $hmap['linkedin'] = $index;
    }

    $req_fields = ['first_name', 'last_name', 'email', 'phone', 'grad_year', 'course', 'enrollment_id'];
    $missing = [];
    foreach ($req_fields as $rf) {
        if (!isset($hmap[$rf])) $missing[] = $rf;
    }
    
    if (count($missing) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required columns: ' . implode(', ', $missing)]);
        exit;
    }

    // Fetch existing emails/phones for dupe check
    $existing = [];
    $stmt = $pdo->query("SELECT email, phone FROM users");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($r['email'])) $existing['email'][$r['email']] = true;
        if (!empty($r['phone'])) $existing['phone'][$r['phone']] = true;
    }

    $previewData = [];
    $stats = [
        'total' => 0,
        'valid' => 0,
        'invalid' => 0,
        'duplicate' => 0
    ];

    $seen_emails = [];
    $seen_phones = [];
    $seen_enrollments = [];

    for ($i = 1; $i < count($rows); $i++) {
        $r = $rows[$i];
        if (empty(implode('', $r))) continue; // skip empty rows
        
        $stats['total']++;

        $fname = trim($r[$hmap['first_name']] ?? '');
        $lname = trim($r[$hmap['last_name']] ?? '');
        $email = strtolower(trim($r[$hmap['email']] ?? ''));
        $phone = preg_replace('/[^0-9]/', '', trim($r[$hmap['phone']] ?? ''));
        $grad_year = intval(trim($r[$hmap['grad_year']] ?? ''));
        $course = trim($r[$hmap['course']] ?? '');
        $enrollment_id = trim($r[$hmap['enrollment_id']] ?? '');

        $errors = [];
        $status = 'valid';

        // Validation
        if (empty($fname)) $errors[] = "First name required.";
        if (empty($email)) {
            $errors[] = "Email required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email.";
        }
        if (empty($phone) || strlen($phone) !== 10) $errors[] = "Phone must be 10 digits.";
        if (empty($grad_year) || $grad_year < 1950 || $grad_year > intval(date('Y')) + 1) $errors[] = "Invalid graduation year.";
        if (empty($course)) $errors[] = "Course required.";

        // Dupe checks
        $is_db_dupe = isset($existing['email'][$email]) || isset($existing['phone'][$phone]);
        $is_file_dupe = isset($seen_emails[$email]) || isset($seen_phones[$phone]) || (isset($seen_enrollments[$enrollment_id]) && !empty($enrollment_id));
        
        if (!empty($email)) $seen_emails[$email] = true;
        if (!empty($phone)) $seen_phones[$phone] = true;
        if (!empty($enrollment_id)) $seen_enrollments[$enrollment_id] = true;

        if (count($errors) > 0) {
            $status = 'invalid';
            $stats['invalid']++;
        } elseif ($is_db_dupe || $is_file_dupe) {
            $status = 'duplicate';
            $stats['duplicate']++;
            if ($is_db_dupe) $errors[] = "Already exists in database.";
            if ($is_file_dupe) $errors[] = "Duplicate in this file.";
        } else {
            $stats['valid']++;
        }

        $previewData[] = [
            'row' => $i + 1,
            'name' => $fname . ' ' . $lname,
            'email' => $email,
            'phone' => $phone,
            'enrollment_id' => $enrollment_id,
            'grad_year' => $grad_year,
            'course' => $course,
            'company' => trim($r[$hmap['company'] ?? ''] ?? ''),
            'position' => trim($r[$hmap['position'] ?? ''] ?? ''),
            'industry' => trim($r[$hmap['industry'] ?? ''] ?? ''),
            'linkedin' => trim($r[$hmap['linkedin'] ?? ''] ?? ''),
            'status' => $status,
            'errors' => implode(" ", $errors),
            'raw_data' => json_encode($r)
        ];
    }

    echo json_encode([
        'status' => 'success', 
        'stats' => $stats, 
        'preview' => $previewData,
        'hmap' => $hmap,
        'filename' => $fileName
    ]);
    exit;
}

if ($action === 'import') {
    $payload = json_decode($_POST['payload'] ?? '{}', true);
    $duplicate_action = $_POST['duplicate_action'] ?? 'skip'; // skip, update
    
    if (empty($payload) || !isset($payload['preview']) || !isset($payload['hmap'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid payload data.']);
        exit;
    }

    $preview = $payload['preview'];
    $hmap = $payload['hmap'];
    $filename = $payload['filename'];
    
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $failed = 0;

    $admin_id = $_SESSION['admin_id'] ?? 1;

    try {
        $pdo->beginTransaction();

        $stmtUserIns = $pdo->prepare("INSERT INTO users (name, email, username, password, phone, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'alumni', 'approved', NOW(), NOW())");
        $stmtProfIns = $pdo->prepare("INSERT INTO alumni_profiles (user_id, enrollment_id, graduation_year, course, company, position, industry, linkedin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmtUserUpd = $pdo->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE email = ? AND role = 'alumni'");
        $stmtProfUpd = $pdo->prepare("UPDATE alumni_profiles SET enrollment_id = ?, graduation_year = ?, course = ?, company = ?, position = ?, industry = ?, linkedin = ? WHERE user_id = (SELECT id FROM users WHERE email = ?)");

        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");

        foreach ($preview as $item) {
            if ($item['status'] === 'invalid') {
                $failed++;
                continue;
            }

            $stmtCheck->execute([$item['email'], $item['phone']]);
            $exists = $stmtCheck->fetchColumn();

            if ($exists) {
                if ($duplicate_action === 'skip') {
                    $skipped++;
                    continue;
                } else if ($duplicate_action === 'update') {
                    // Update user
                    $stmtUserUpd->execute([$item['name'], $item['phone'], $item['email']]);
                    // Update profile
                    $stmtProfUpd->execute([$item['enrollment_id'], $item['grad_year'], $item['course'], $item['company'], $item['position'], $item['industry'], $item['linkedin'], $item['email']]);
                    $updated++;
                }
            } else {
                // Insert new
                $username = strtolower(explode('@', $item['email'])[0]) . mt_rand(100, 999);
                // Secure default password logic based on implementation plan
                $default_pass = 'Alumni@' . $item['grad_year'];
                $hashed_pass = password_hash($default_pass, PASSWORD_BCRYPT);
                
                $stmtUserIns->execute([$item['name'], $item['email'], $username, $hashed_pass, $item['phone']]);
                $user_id = $pdo->lastInsertId();
                
                $stmtProfIns->execute([$user_id, $item['enrollment_id'], $item['grad_year'], $item['course'], $item['company'], $item['position'], $item['industry'], $item['linkedin']]);
                $imported++;
            }
        }

        // Record history
        $stmtHist = $pdo->prepare("INSERT INTO import_history (admin_id, filename, total_rows, imported_count, updated_count, skipped_count, failed_count, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')");
        $stmtHist->execute([$admin_id, $filename, count($preview), $imported, $updated, $skipped, $failed]);

        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => count($preview)
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);

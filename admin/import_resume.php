<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/admin.php';
require_once __DIR__ . '/../includes/auth_helper.php';
check_admin();

$page_title = "Import Alumni Resumes";
$results = [];

function extract_text_from_docx($filename) {
    $text = '';
    $zip = new ZipArchive;
    if ($zip->open($filename) === true) {
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $data = $zip->getFromIndex($index);
            $zip->close();
            
            $doc = new DOMDocument();
            $doc->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
            $text = strip_tags($doc->saveXML());
        }
        $zip->close();
    }
    return $text;
}

function extract_text_from_pdf($filename) {
    // Very basic PDF text extraction (works for uncompressed simple PDFs)
    // For robust extraction, a dedicated library like smalot/pdfparser is recommended.
    $content = file_get_contents($filename);
    $text = preg_replace('/[^a-zA-Z0-9\s@\.\:\,\$\₹\-]/', ' ', $content);
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resumes'])) {
    
    $default_password = password_hash('Alumni@123', PASSWORD_DEFAULT);
    
    foreach ($_FILES['resumes']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['resumes']['error'][$key] !== UPLOAD_ERR_OK) continue;
        
        $file_name = $_FILES['resumes']['name'][$key];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $text = '';
        if ($file_ext === 'docx') {
            $text = extract_text_from_docx($tmp_name);
        } elseif ($file_ext === 'pdf') {
            $text = extract_text_from_pdf($tmp_name);
        } else {
            $results[] = ['file' => $file_name, 'status' => 'error', 'message' => 'Unsupported format.'];
            continue;
        }
        
        // --- Extraction Logic ---
        
        // 1. Email
        preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $email_matches);
        $email = $email_matches[0] ?? '';
        
        // 2. Name (Fallback to email username if not found)
        preg_match('/Name[:\-]?\s*([a-zA-Z\s]+)(?:\\n|\r|$)/i', $text, $name_matches);
        $name = trim($name_matches[1] ?? '');
        if (!$name && $email) {
            $name = ucfirst(explode('@', $email)[0]);
        }
        
        // 3. Company
        preg_match('/(?:Company|Employer|Worked at)[:\-]?\s*([a-zA-Z0-9\s&]+)(?:\\n|\r|$)/i', $text, $company_matches);
        $company = trim($company_matches[1] ?? '');
        
        // 4. Salary
        preg_match('/(?:Salary|CTC)[:\-]?\s*([\$₹€£]?\s*[\d,]+(?:\.\d+)?\s*(?:LPA|K|M)?)/i', $text, $salary_matches);
        $salary = trim($salary_matches[1] ?? '');

        // 5. Course / Grad Year
        preg_match('/(?:Course|Degree)[:\-]?\s*([a-zA-Z\s]+)(?:\\n|\r|$)/i', $text, $course_matches);
        $course = trim($course_matches[1] ?? 'Not Set');
        
        preg_match('/(?:Graduation|Passed out|Batch)[:\-]?\s*(20\d{2})/i', $text, $year_matches);
        $grad_year = (int)($year_matches[1] ?? date('Y'));
        
        if (!$email) {
            $results[] = ['file' => $file_name, 'status' => 'error', 'message' => 'No email found in resume.'];
            continue;
        }
        
        // --- Database Logic ---
        try {
            $pdo->beginTransaction();
            
            // Check user
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            $user_id = $stmtCheck->fetchColumn();
            
            if (!$user_id) {
                // Generate username
                $username_base = explode('@', $email)[0];
                $username = $username_base;
                $suffix = 1;
                $stmtCheckUsername = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtCheckUsername->execute([$username]);
                while ($stmtCheckUsername->fetchColumn()) {
                    $username = $username_base . $suffix;
                    $suffix++;
                    $stmtCheckUsername->execute([$username]);
                }
                
                // Insert User
                $stmtInsertUser = $pdo->prepare("INSERT INTO users (name, email, username, password, role, status) VALUES (?, ?, ?, ?, 'alumni', 'approved')");
                $stmtInsertUser->execute([$name, $email, $username, $default_password]);
                $user_id = $pdo->lastInsertId();
                
                // Insert Profile
                $stmtInsertProfile = $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, course, company, salary) VALUES (?, ?, ?, ?, ?)");
                $stmtInsertProfile->execute([$user_id, $grad_year, $course, $company, $salary]);
                
                $results[] = ['file' => $file_name, 'status' => 'success', 'message' => "Created new alumni: $name. Company: $company. Salary: $salary"];
            } else {
                // Update existing profile
                $stmtCheckProfile = $pdo->prepare("SELECT COUNT(*) FROM alumni_profiles WHERE user_id = ?");
                $stmtCheckProfile->execute([$user_id]);
                if ($stmtCheckProfile->fetchColumn() > 0) {
                    $stmtUp = $pdo->prepare("UPDATE alumni_profiles SET company = COALESCE(NULLIF(?, ''), company), salary = COALESCE(NULLIF(?, ''), salary) WHERE user_id = ?");
                    $stmtUp->execute([$company, $salary, $user_id]);
                } else {
                    $stmtInsertProfile = $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, course, company, salary) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsertProfile->execute([$user_id, $grad_year, $course, $company, $salary]);
                }
                $results[] = ['file' => $file_name, 'status' => 'success', 'message' => "Updated existing alumni: $email. Company: $company. Salary: $salary"];
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $results[] = ['file' => $file_name, 'status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Alumni Resumes - AlumniNet Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #f8fafc; padding: 2rem; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header h2 { margin: 0; font-size: 1.5rem; color: #ffffff; }
        .btn-back { color: #94a3b8; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-back:hover { color: #ffffff; }
        
        .upload-area {
            border: 2px dashed rgba(99, 102, 241, 0.5);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            background: rgba(99, 102, 241, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 1.5rem;
        }
        .upload-area:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 1);
        }
        .upload-area i { font-size: 3rem; color: #818cf8; margin-bottom: 1rem; }
        .upload-area p { color: #cbd5e1; margin-bottom: 0.5rem; }
        .upload-area span { font-size: 0.85rem; color: #64748b; }
        
        .btn-primary { background: #4f46e5; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.3s; }
        .btn-primary:hover { background: #4338ca; }
        
        .results-section { margin-top: 2rem; }
        .result-item { padding: 1rem; border-radius: 8px; margin-bottom: 0.75rem; border-left: 4px solid; display: flex; flex-direction: column; gap: 0.25rem; }
        .result-success { background: rgba(16, 185, 129, 0.1); border-color: #10b981; }
        .result-error { background: rgba(239, 68, 68, 0.1); border-color: #ef4444; }
        .result-filename { font-weight: 600; font-size: 0.95rem; }
        .result-msg { font-size: 0.85rem; color: #cbd5e1; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2><i class="fas fa-file-import mr-2 text-indigo-400"></i> Import Resumes (PDF / DOCX)</h2>
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
    
    <div class="card">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <label for="resumes" class="upload-area" style="display: block;">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Click to select PDF or DOCX files</p>
                <span>Upload alumni resumes to automatically extract details like Company and Salary</span>
                <input type="file" name="resumes[]" id="resumes" multiple accept=".pdf,.docx" style="display: none;" onchange="updateFileCount(this)">
            </label>
            <div id="fileCount" style="text-align: center; margin-bottom: 1rem; color: #a78bfa; font-weight: 500; display: none;"></div>
            
            <button type="submit" class="btn-primary"><i class="fas fa-magic"></i> Extract & Import Details</button>
        </form>

        <?php if (!empty($results)): ?>
            <div class="results-section">
                <h3 style="margin-bottom: 1rem; font-size: 1.1rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.5rem;">Import Results</h3>
                <?php foreach ($results as $res): ?>
                    <div class="result-item <?php echo $res['status'] === 'success' ? 'result-success' : 'result-error'; ?>">
                        <span class="result-filename"><?php echo htmlspecialchars($res['file']); ?></span>
                        <span class="result-msg"><?php echo htmlspecialchars($res['message']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateFileCount(input) {
        const countDisplay = document.getElementById('fileCount');
        if (input.files && input.files.length > 0) {
            countDisplay.style.display = 'block';
            countDisplay.innerText = input.files.length + ' file(s) selected for import';
        } else {
            countDisplay.style.display = 'none';
        }
    }
</script>

</body>
</html>

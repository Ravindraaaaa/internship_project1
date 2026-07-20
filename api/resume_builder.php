<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$user_id = get_user_id();
$role = $_SESSION['user_role'] ?? 'student';

// Handle AJAX Save Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_profile') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $github = trim($_POST['github'] ?? '');
    $website = trim($_POST['website'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        // Update user name in users table
        if ($name) {
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->execute([$name, $user_id]);
        }
        
        // Update bio & social links in corresponding profile table
        if ($role === 'alumni') {
            $stmt = $pdo->prepare("UPDATE alumni_profiles SET bio = ?, linkedin = ?, website = ? WHERE user_id = ?");
            $stmt->execute([$bio, $linkedin, $website, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE student_profiles SET bio = ?, linkedin = ?, github = ? WHERE user_id = ?");
            $stmt->execute([$bio, $linkedin, $github, $user_id]);
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Profile changes synced and saved successfully!']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch Core User Data
$stmtUser = $pdo->prepare("SELECT u.name, u.email, u.role, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

if (!$user) {
    die("User profile not found.");
}

// Fetch Profile Details
$profile = [];
if ($role === 'alumni') {
    $stmtProf = $pdo->prepare("SELECT * FROM alumni_profiles WHERE user_id = ?");
    $stmtProf->execute([$user_id]);
    $profile = $stmtProf->fetch() ?: [];
} else {
    $stmtProf = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $stmtProf->execute([$user_id]);
    $profile = $stmtProf->fetch() ?: [];
}

// Fetch Education
$stmtEdu = $pdo->prepare("SELECT * FROM education WHERE user_id = ? ORDER BY start_year DESC");
$stmtEdu->execute([$user_id]);
$education = $stmtEdu->fetchAll();

// Fetch Experience
$stmtExp = $pdo->prepare("SELECT * FROM experience WHERE user_id = ? ORDER BY start_date DESC");
$stmtExp->execute([$user_id]);
$experience = $stmtExp->fetchAll();

// Fetch Skills
$stmtSkills = $pdo->prepare("SELECT s.name, us.progress FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ?");
$stmtSkills->execute([$user_id]);
$skills = $stmtSkills->fetchAll();

// Resolve avatar path
$avatar_pic = '';
if (!empty($profile['profile_pic'])) {
    if (strpos($profile['profile_pic'], 'http') === 0) {
        $avatar_pic = $profile['profile_pic'];
    } elseif (file_exists(__DIR__ . '/../' . $profile['profile_pic'])) {
        $avatar_pic = '../' . $profile['profile_pic'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume - <?php echo htmlspecialchars($user['name']); ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Roboto:wght@300;400;500;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Customize variables */
            --accent: #2563eb;
            --accent-light: #eff6ff;
            --accent-dark: #1e3a8a;
            --accent-tint: #f0fdf4;
            --primary: #1e293b;
            --primary-light: #475569;
            --text-main: #334155;
            --text-light: #64748b;
            --border: #e2e8f0;
            --font-family: 'Inter', sans-serif;
            --page-margin: 25mm;
            --section-spacing: 1.75rem;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Top Header Control Area */
        .builder-header {
            height: 64px;
            background-color: #1e293b;
            border-bottom: 1px solid #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1.5rem;
            z-index: 10;
        }

        .header-title-area {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-title-area i {
            font-size: 1.5rem;
            color: #3b82f6;
        }

        .header-title-area h1 {
            font-size: 1.15rem;
            font-weight: 700;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1.1rem;
            background-color: var(--accent);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #475569;
        }

        .btn-success {
            background-color: #10b981;
        }

        /* Main workspace grid */
        .builder-workspace {
            display: flex;
            flex: 1;
            overflow: hidden;
            position: relative;
        }

        /* Sidebar Style */
        .customizer-sidebar {
            width: 420px;
            background-color: #111827;
            border-right: 1px solid #1f2937;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-section {
            background: #1f2937;
            border-radius: 8px;
            border: 1px solid #374151;
            padding: 1.25rem;
        }

        .sidebar-section-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid #374151;
            padding-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #d1d5db;
            margin-bottom: 0.4rem;
        }

        .form-input {
            width: 100%;
            background: #111827;
            border: 1px solid #4b5563;
            border-radius: 6px;
            padding: 0.55rem 0.75rem;
            color: #ffffff;
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            border-color: #3b82f6;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: #d1d5db;
            cursor: pointer;
        }

        .checkbox-label input {
            cursor: pointer;
            accent-color: #3b82f6;
            width: 15px;
            height: 15px;
        }

        /* Color customizer dot options */
        .color-selector {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .color-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .color-dot.active {
            border-color: #ffffff;
            transform: scale(1.15);
        }

        .dot-blue { background-color: #2563eb; }
        .dot-green { background-color: #10b981; }
        .dot-red { background-color: #ef4444; }
        .dot-indigo { background-color: #6366f1; }
        .dot-charcoal { background-color: #374151; }

        /* Typography selections styling */
        .select-font {
            width: 100%;
            background: #111827;
            border: 1px solid #4b5563;
            border-radius: 6px;
            padding: 0.55rem;
            color: #ffffff;
            font-size: 0.85rem;
            outline: none;
        }

        /* Layout selections styling */
        .layout-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .layout-btn {
            background-color: #111827;
            border: 1px solid #4b5563;
            color: #d1d5db;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            text-align: center;
            font-weight: 600;
            transition: all 0.2s;
        }

        .layout-btn.active {
            background-color: #3b82f6;
            border-color: #3b82f6;
            color: #ffffff;
        }

        /* Live Preview Panel (Right Side) */
        .preview-pane {
            flex: 1;
            background-color: #0f172a;
            overflow-y: auto;
            padding: 2.5rem 1.5rem;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        /* Resume A4 Layout Sheet */
        .resume-sheet {
            width: 210mm;
            min-height: 297mm;
            background: #ffffff;
            color: var(--text-main);
            font-family: var(--font-family);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.3), 0 8px 10px -6px rgb(0 0 0 / 0.3);
            padding: var(--page-margin);
            position: relative;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        /* Elements inside Resume Sheet */
        .resume-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .header-left h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.025em;
            margin-bottom: 0.25rem;
        }

        .header-left p {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .header-right {
            text-align: right;
            font-size: 0.85rem;
            color: var(--text-light);
            line-height: 1.6;
        }

        .header-right div {
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.4rem;
        }

        .header-right i {
            color: var(--accent);
            width: 14px;
            text-align: center;
        }

        /* Flexible Content Columns inside sheet */
        .resume-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2.5rem;
        }

        .resume-section {
            margin-bottom: var(--section-spacing);
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.15rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--accent);
        }

        .section-title::after {
            content: '';
            flex-grow: 1;
            height: 1px;
            background-color: var(--border);
            margin-left: 0.5rem;
        }

        .summary-text {
            font-size: 0.88rem;
            line-height: 1.6;
            color: var(--text-main);
        }

        .timeline-item {
            margin-bottom: 1.25rem;
            position: relative;
            padding-left: 0.88rem;
            border-left: 2px solid var(--border);
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-item-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.15rem;
        }

        .timeline-item-meta {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 0.4rem;
            display: flex;
            justify-content: space-between;
        }

        .timeline-item-meta span.date {
            color: var(--text-light);
            font-weight: 400;
        }

        .timeline-item-desc {
            font-size: 0.82rem;
            line-height: 1.5;
            color: var(--text-light);
        }

        .skills-list {
            list-style: none;
        }

        .skill-item {
            margin-bottom: 0.88rem;
        }

        .skill-name-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .skill-track {
            height: 5px;
            background-color: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }

        .skill-fill {
            height: 100%;
            background-color: var(--accent);
            border-radius: 3px;
        }

        .verification-badge {
            font-size: 0.78rem;
            line-height: 1.5;
            color: var(--text-light);
            background-color: var(--accent-light);
            padding: 0.75rem;
            border-radius: 6px;
            border-left: 3px solid var(--accent);
        }

        /* ------------------ TEMPLATE LAYOUT VARIATIONS ------------------ */

        /* 1. CLASSIC SINGLE COLUMN TEMPLATE */
        .resume-sheet.template-classic .resume-grid {
            display: block;
        }

        .resume-sheet.template-classic .sidebar-column {
            margin-top: 1.75rem;
        }

        .resume-sheet.template-classic .resume-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
            border-bottom: 2px solid var(--accent);
        }

        .resume-sheet.template-classic .header-right {
            text-align: center;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.75rem;
            width: 100%;
        }

        .resume-sheet.template-classic .header-right div {
            justify-content: center;
            margin-bottom: 0;
        }

        /* 2. MODERN HEADER BANNER TEMPLATE */
        .resume-sheet.template-modern {
            padding: 0; /* Clear container padding to style header edge-to-edge */
        }

        .resume-sheet.template-modern .resume-header {
            background-color: var(--accent-dark);
            color: #ffffff;
            padding: 2.5rem 3rem 2rem 3rem;
            margin-bottom: 2rem;
            border-bottom: none;
        }

        .resume-sheet.template-modern .header-left h2 {
            color: #ffffff;
        }

        .resume-sheet.template-modern .header-left p {
            color: var(--accent-light);
        }

        .resume-sheet.template-modern .header-right {
            color: rgba(255, 255, 255, 0.8);
        }

        .resume-sheet.template-modern .header-right div i {
            color: #ffffff;
        }

        .resume-sheet.template-modern .resume-grid {
            padding: 0 3rem 3rem 3rem;
        }

        /* 3. CREATIVE SPLIT SIDEBAR TEMPLATE */
        .resume-sheet.template-creative {
            padding: 0;
            overflow: hidden;
            display: flex;
            min-height: 297mm;
        }

        .resume-sheet.template-creative .resume-header {
            display: none; /* Hide standard header block */
        }

        .resume-sheet.template-creative .resume-grid {
            grid-template-columns: 1fr 2fr;
            gap: 0;
            width: 100%;
        }

        /* Creative Left Sidebar Column */
        .resume-sheet.template-creative .sidebar-column {
            background-color: var(--accent-light);
            border-right: 1px solid var(--border);
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .resume-sheet.template-creative .sidebar-column .section-title {
            color: var(--accent-dark);
        }

        .resume-sheet.template-creative .sidebar-column .section-title::after {
            background-color: var(--accent);
        }

        /* Creative Right Main Column */
        .resume-sheet.template-creative .main-column {
            padding: 3rem 2.5rem;
        }

        /* Creative custom top section for name */
        .resume-sheet.template-creative .creative-top-header {
            margin-bottom: 2rem;
            padding-bottom: 1.25rem;
            border-bottom: 2px solid var(--accent);
        }

        .resume-sheet.template-creative .creative-top-header h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.025em;
            margin-bottom: 0.25rem;
        }

        .resume-sheet.template-creative .creative-top-header p {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--accent);
            text-transform: uppercase;
        }

        .resume-sheet.template-creative .creative-contact-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-main);
            margin-bottom: 1rem;
        }

        .resume-sheet.template-creative .creative-contact-group div {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .resume-sheet.template-creative .creative-contact-group div i {
            color: var(--accent);
            width: 14px;
        }

        /* 4. MINIMALIST CLEAN TEMPLATE */
        .resume-sheet.template-minimalist {
            padding: 2.5rem;
        }

        .resume-sheet.template-minimalist .resume-header {
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .resume-sheet.template-minimalist .header-left h2 {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .resume-sheet.template-minimalist .header-left p {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .resume-sheet.template-minimalist .section-title {
            font-size: 0.9rem;
            border-bottom: none;
            margin-bottom: 0.75rem;
        }

        .resume-sheet.template-minimalist .section-title i {
            display: none;
        }

        .resume-sheet.template-minimalist .section-title::after {
            background-color: var(--border);
        }

        .resume-sheet.template-minimalist .timeline-item {
            border-left: none;
            padding-left: 0;
            margin-bottom: 1rem;
        }

        .resume-sheet.template-minimalist .timeline-item-title {
            font-size: 0.9rem;
        }

        .resume-sheet.template-minimalist .timeline-item-meta {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .resume-sheet.template-minimalist .timeline-item-desc {
            font-size: 0.8rem;
        }

        .resume-sheet.template-minimalist .skill-track {
            height: 3px;
        }

        /* ------------------ TOAST POPUP ------------------ */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #10b981;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgb(0 0 0 / 0.1);
            font-weight: 600;
            font-size: 0.85rem;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 9999;
        }

        .toast-notification.active {
            transform: translateY(0);
            opacity: 1;
        }

        /* ------------------ PRINT MEDIA OVERRIDES ------------------ */
        @media print {
            body {
                background: none !important;
                color: #000000 !important;
                overflow: visible !important;
                height: auto !important;
            }

            .builder-header,
            .customizer-sidebar {
                display: none !important;
            }

            .preview-pane {
                padding: 0 !important;
                margin: 0 !important;
                background: none !important;
                overflow: visible !important;
                display: block !important;
            }

            .resume-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                min-height: 0 !important;
                border: none !important;
            }

            .resume-sheet.template-modern .resume-header {
                padding-top: 1.5cm !important;
            }
        }
    </style>
</head>
<body>

    <!-- 1. Builder Top Nav Control Header -->
    <header class="builder-header">
        <div class="header-title-area">
            <i class="fa-solid fa-file-invoice"></i>
            <div>
                <h1>AlumniNet Dynamic Resume Builder</h1>
                <p style="font-size: 0.7rem; color: #94a3b8; font-weight: 500;">Interactive layout engine</p>
            </div>
        </div>
        
        <div class="btn-group">
            <a href="../user/portfolio.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Portfolio</a>
            <button onclick="saveProfileToDatabase()" class="btn btn-success"><i class="fa-solid fa-cloud-arrow-up"></i> Save to Profile</button>
            <button onclick="window.print()" class="btn"><i class="fa-solid fa-print"></i> Save / Print PDF</button>
        </div>
    </header>

    <!-- 2. Dual Panel Workspace -->
    <div class="builder-workspace">
        
        <!-- Sidebar Panel Options (Left) -->
        <aside class="customizer-sidebar">
            
            <!-- Section 1: Template Selection -->
            <section class="sidebar-section">
                <h3 class="sidebar-section-title"><i class="fa-solid fa-palette"></i> Design & Template</h3>
                
                <div class="form-group">
                    <label>Choose Resume Style</label>
                    <div class="layout-options">
                        <button class="layout-btn active" id="lay-classic" onclick="setTemplate('classic')">Classic</button>
                        <button class="layout-btn" id="lay-modern" onclick="setTemplate('modern')">Modern Banner</button>
                        <button class="layout-btn" id="lay-creative" onclick="setTemplate('creative')">Creative Split</button>
                        <button class="layout-btn" id="lay-minimalist" onclick="setTemplate('minimalist')">Minimalist</button>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Accent Color</span>
                        <div style="display:flex; align-items:center; gap:0.25rem;">
                            <span style="font-size:0.75rem; color:#9ca3af;">Custom:</span>
                            <input type="color" id="custom-color-picker" value="#2563eb" style="border:none; background:none; width:22px; height:22px; cursor:pointer;" onchange="updateCustomColor(this.value)" oninput="updateCustomColor(this.value)">
                        </div>
                    </label>
                    <div class="color-selector">
                        <span class="color-dot dot-blue active" title="Sapphire Blue" onclick="setAccent('#2563eb', '#eff6ff', '#1e3a8a', '#e0e7ff', this)"></span>
                        <span class="color-dot dot-green" title="Emerald Green" onclick="setAccent('#10b981', '#ecfdf5', '#064e3b', '#e6f4ea', this)"></span>
                        <span class="color-dot dot-red" title="Coral Red" onclick="setAccent('#ef4444', '#fef2f2', '#7f1d1d', '#fde8e8', this)"></span>
                        <span class="color-dot dot-indigo" title="Indigo Violet" onclick="setAccent('#6366f1', '#eef2ff', '#312e81', '#e0e7ff', this)"></span>
                        <span class="color-dot dot-charcoal" title="Slate Gray" onclick="setAccent('#374151', '#f3f4f6', '#111827', '#e5e7eb', this)"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Typography Font</label>
                    <select class="select-font" onchange="setFont(this.value)">
                        <option value="Inter">Inter (Sans-Serif)</option>
                        <option value="Georgia">Playfair / Georgia (Serif)</option>
                        <option value="Roboto">Roboto (Clean)</option>
                        <option value="JetBrains Mono">JetBrains Mono (Developer)</option>
                    </select>
                </div>
            </section>

            <!-- Section 2: Toggle Visibility -->
            <section class="sidebar-section">
                <h3 class="sidebar-section-title"><i class="fa-solid fa-eye"></i> Show/Hide Sections</h3>
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-summary" checked onchange="toggleSection('summary', this.checked)"> Summary
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-experience" checked onchange="toggleSection('experience', this.checked)"> Experience
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-education" checked onchange="toggleSection('education', this.checked)"> Education
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-skills" checked onchange="toggleSection('skills', this.checked)"> Skills
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="chk-verification" checked onchange="toggleSection('verification', this.checked)"> Verification
                    </label>
                    <?php if ($avatar_pic): ?>
                        <label class="checkbox-label" style="grid-column: span 2; margin-top: 0.25rem;">
                            <input type="checkbox" id="chk-avatar" onchange="toggleAvatar(this.checked)"> Show Profile Photo
                        </label>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Section 2B: Layout & Spacing -->
            <section class="sidebar-section">
                <h3 class="sidebar-section-title"><i class="fa-solid fa-arrows-left-right-to-line"></i> Layout & Spacing</h3>
                
                <div class="form-group">
                    <label style="display:flex; justify-content:space-between; margin-bottom: 0.3rem;">
                        <span>Page Margin (mm)</span>
                        <span id="lbl-margin">25</span>
                    </label>
                    <input type="range" class="custom-range" min="15" max="35" value="25" oninput="setPageMargin(this.value)">
                </div>

                <div class="form-group">
                    <label style="display:flex; justify-content:space-between; margin-bottom: 0.3rem;">
                        <span>Section Spacing (rem)</span>
                        <span id="lbl-spacing">1.75</span>
                    </label>
                    <input type="range" class="custom-range" min="0.8" max="2.5" step="0.05" value="1.75" oninput="setSectionSpacing(this.value)">
                </div>
            </section>

            <!-- Section 2C: Customize Titles -->
            <section class="sidebar-section">
                <h3 class="sidebar-section-title"><i class="fa-solid fa-heading"></i> Customize Titles</h3>
                
                <div class="form-group">
                    <label>Summary Section Title</label>
                    <input type="text" class="form-input" value="Professional Summary" oninput="updateSectionTitle('summary', this.value)">
                </div>

                <div class="form-group">
                    <label>Experience Section Title</label>
                    <input type="text" class="form-input" value="Experience" oninput="updateSectionTitle('experience', this.value)">
                </div>

                <div class="form-group">
                    <label>Education Section Title</label>
                    <input type="text" class="form-input" value="Education" oninput="updateSectionTitle('education', this.value)">
                </div>

                <div class="form-group">
                    <label>Skills Section Title</label>
                    <input type="text" class="form-input" value="Skills" oninput="updateSectionTitle('skills', this.value)">
                </div>

                <div class="form-group">
                    <label>Verification Section Title</label>
                    <input type="text" class="form-input" value="Verification" oninput="updateSectionTitle('verification', this.value)">
                </div>
            </section>

            <!-- Section 3: Direct Data Editing -->
            <section class="sidebar-section">
                <h3 class="sidebar-section-title"><i class="fa-solid fa-user-pen"></i> Edit Personal Info</h3>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" class="form-input" id="inp-name" value="<?php echo htmlspecialchars($user['name']); ?>" oninput="updateLiveText('name', this.value)">
                </div>

                <div class="form-group">
                    <label>Department / Profession</label>
                    <input type="text" class="form-input" id="inp-dept" value="<?php echo htmlspecialchars($user['department_name'] ?? $role); ?>" oninput="updateLiveText('dept', this.value)">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-input" id="inp-email" value="<?php echo htmlspecialchars($user['email']); ?>" oninput="updateLiveText('email', this.value)">
                </div>

                <div class="form-group">
                    <label>LinkedIn Username / URL</label>
                    <input type="text" class="form-input" id="inp-linkedin" value="<?php echo htmlspecialchars($profile['linkedin'] ?? ''); ?>" oninput="updateLiveLink('linkedin', this.value, 'fa-brands fa-linkedin')">
                </div>

                <?php if ($role === 'student'): ?>
                    <div class="form-group">
                        <label>GitHub Username / URL</label>
                        <input type="text" class="form-input" id="inp-github" value="<?php echo htmlspecialchars($profile['github'] ?? ''); ?>" oninput="updateLiveLink('github', this.value, 'fa-brands fa-github')">
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Website / Portfolio URL</label>
                        <input type="text" class="form-input" id="inp-website" value="<?php echo htmlspecialchars($profile['website'] ?? ''); ?>" oninput="updateLiveLink('website', this.value, 'fa-solid fa-globe')">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Professional Biography</label>
                    <textarea class="form-input" id="inp-bio" rows="4" style="resize:vertical;" oninput="updateLiveBio(this.value)"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                </div>
            </section>
            
        </aside>

        <!-- Preview Sheet (Right) -->
        <main class="preview-pane">
            
            <div class="resume-sheet template-classic" id="resume-sheet">
                
                <!-- 1. CREATIVE SIDEBAR INTRO LINK GROUP (Only active in template-creative) -->
                <div id="creative-header-meta" style="display:none;">
                    <?php if ($avatar_pic): ?>
                        <div style="text-align: center; margin-bottom: 1.25rem;" id="creative-avatar-container">
                            <img src="<?php echo htmlspecialchars($avatar_pic); ?>" id="creative-resume-avatar" class="resume-avatar-img" style="display: none; width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #ffffff; box-shadow: 0 6px 15px rgba(0,0,0,0.1); margin: 0 auto;">
                        </div>
                    <?php endif; ?>
                    <div class="creative-top-header">
                        <h2 id="creative-name"><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p id="creative-dept"><?php echo htmlspecialchars($user['department_name'] ?? $role); ?></p>
                    </div>
                    <div class="creative-contact-group" id="creative-contacts">
                        <div><i class="fa-solid fa-envelope"></i> <span id="creative-c-email"><?php echo htmlspecialchars($user['email']); ?></span></div>
                        <?php if (!empty($profile['linkedin'])): ?>
                            <div id="creative-c-linkedin-wrap"><i class="fa-brands fa-linkedin"></i> <span id="creative-c-linkedin"><?php echo htmlspecialchars(str_replace(['https://', 'http://'], '', $profile['linkedin'])); ?></span></div>
                        <?php endif; ?>
                        <?php if ($role === 'student' && !empty($profile['github'])): ?>
                            <div id="creative-c-github-wrap"><i class="fa-brands fa-github"></i> <span id="creative-c-github"><?php echo htmlspecialchars(str_replace(['https://', 'http://'], '', $profile['github'])); ?></span></div>
                        <?php endif; ?>
                        <?php if ($role === 'alumni' && !empty($profile['website'])): ?>
                            <div id="creative-c-website-wrap"><i class="fa-solid fa-globe"></i> <span id="creative-c-website"><?php echo htmlspecialchars(str_replace(['https://', 'http://'], '', $profile['website'])); ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. STANDARD RUNTIME HEADER (Classic / Modern / Minimalist) -->
                <header class="resume-header" id="standard-header">
                    <div style="display: flex; align-items: center; gap: 1.25rem;">
                        <?php if ($avatar_pic): ?>
                            <img src="<?php echo htmlspecialchars($avatar_pic); ?>" id="resume-avatar" class="resume-avatar-img" style="display: none; width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 3.5px solid var(--accent-light); box-shadow: 0 4px 10px rgba(0,0,0,0.06);">
                        <?php endif; ?>
                        <div class="header-left">
                            <h2 id="val-name"><?php echo htmlspecialchars($user['name']); ?></h2>
                            <p id="val-dept"><?php echo htmlspecialchars($user['department_name'] ?? $role); ?></p>
                        </div>
                    </div>
                    <div class="header-right" id="standard-contacts">
                        <div><i class="fa-solid fa-envelope"></i> <span id="val-email"><?php echo htmlspecialchars($user['email']); ?></span></div>
                        
                        <?php if (!empty($profile['linkedin'])): ?>
                            <div id="val-linkedin-wrap"><i class="fa-brands fa-linkedin"></i> <span id="val-linkedin"><?php echo htmlspecialchars(str_replace(['https://', 'http://'], '', $profile['linkedin'])); ?></span></div>
                        <?php else: ?>
                            <div id="val-linkedin-wrap" style="display:none;"><i class="fa-brands fa-linkedin"></i> <span id="val-linkedin"></span></div>
                        <?php endif; ?>
                        
                        <?php if ($role === 'student' && !empty($profile['github'])): ?>
                            <div id="val-github-wrap"><i class="fa-brands fa-github"></i> <span id="val-github"><?php echo htmlspecialchars(str_replace(['https://', 'http://'], '', $profile['github'])); ?></span></div>
                        <?php elseif ($role === 'student'): ?>
                            <div id="val-github-wrap" style="display:none;"><i class="fa-brands fa-github"></i> <span id="val-github"></span></div>
                        <?php endif; ?>
                        
                        <?php if ($role === 'alumni' && !empty($profile['website'])): ?>
                            <div id="val-website-wrap"><i class="fa-solid fa-globe"></i> <span id="val-website"><?php echo htmlspecialchars(str_replace(['https://', 'http://'], '', $profile['website'])); ?></span></div>
                        <?php elseif ($role === 'alumni'): ?>
                            <div id="val-website-wrap" style="display:none;"><i class="fa-solid fa-globe"></i> <span id="val-website"></span></div>
                        <?php endif; ?>
                    </div>
                </header>

                <!-- Grid Layout (Reordered dynamically based on chosen Template class) -->
                <div class="resume-grid" id="resume-grid-container">
                    
                    <!-- Left main column -->
                    <div class="main-column" id="resume-main-col">
                        
                        <!-- Summary Section -->
                        <section class="resume-section" id="section-summary" style="<?php echo empty($profile['bio']) ? 'display:none;' : ''; ?>">
                            <h3 class="section-title" id="head-summary"><i class="fa-solid fa-user"></i> <span class="sec-title-text">Professional Summary</span></h3>
                            <p class="summary-text" id="val-bio"><?php echo nl2br(htmlspecialchars($profile['bio'] ?? '')); ?></p>
                        </section>

                        <!-- Experience Section -->
                        <section class="resume-section" id="section-experience">
                            <h3 class="section-title" id="head-experience"><i class="fa-solid fa-briefcase"></i> <span class="sec-title-text">Experience</span></h3>
                            <div id="experience-list-container">
                                <?php if ($experience): ?>
                                    <?php foreach ($experience as $exp): ?>
                                        <div class="timeline-item">
                                            <h4 class="timeline-item-title"><?php echo htmlspecialchars($exp['position']); ?></h4>
                                            <div class="timeline-item-meta">
                                                <span><?php echo htmlspecialchars($exp['company']); ?><?php echo !empty($exp['location']) ? ' | ' . htmlspecialchars($exp['location']) : ''; ?></span>
                                                <span class="date">
                                                    <?php 
                                                        $start = date('M Y', strtotime($exp['start_date']));
                                                        $end = !empty($exp['end_date']) ? date('M Y', strtotime($exp['end_date'])) : 'Present';
                                                        echo $start . ' - ' . $end;
                                                    ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($exp['description'])): ?>
                                                <p class="timeline-item-desc"><?php echo nl2br(htmlspecialchars($exp['description'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="timeline-item" style="border-left:none; padding-left:0;">
                                        <p class="timeline-item-desc" style="font-style: italic;">No experience entries listed. Complete your work history in your profile.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Education Section -->
                        <section class="resume-section" id="section-education">
                            <h3 class="section-title" id="head-education"><i class="fa-solid fa-graduation-cap"></i> <span class="sec-title-text">Education</span></h3>
                            <div id="education-list-container">
                                <?php if ($education): ?>
                                    <?php foreach ($education as $edu): ?>
                                        <div class="timeline-item">
                                            <h4 class="timeline-item-title"><?php echo htmlspecialchars($edu['school']); ?></h4>
                                            <div class="timeline-item-meta">
                                                <span><?php echo htmlspecialchars($edu['degree']); ?><?php echo !empty($edu['field_of_study']) ? ' in ' . htmlspecialchars($edu['field_of_study']) : ''; ?></span>
                                                <span class="date"><?php echo htmlspecialchars($edu['start_year']); ?> - <?php echo htmlspecialchars($edu['end_year'] ?: 'Present'); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="timeline-item" style="border-left:none; padding-left:0;">
                                        <p class="timeline-item-desc" style="font-style: italic;">No education entries listed. Complete your academic history in your profile.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                    </div>

                    <!-- Right sidebar column -->
                    <div class="sidebar-column" id="resume-sidebar-col">
                        
                        <!-- Skills Section -->
                        <section class="resume-section" id="section-skills">
                            <h3 class="section-title" id="head-skills"><i class="fa-solid fa-code"></i> <span class="sec-title-text">Skills</span></h3>
                            <div id="skills-list-container">
                                <?php if ($skills): ?>
                                    <ul class="skills-list">
                                        <?php foreach ($skills as $s): ?>
                                            <li class="skill-item">
                                                <div class="skill-name-row">
                                                    <span><?php echo htmlspecialchars($s['name']); ?></span>
                                                    <span><?php echo htmlspecialchars($s['progress']); ?>%</span>
                                                </div>
                                                <div class="skill-track">
                                                    <div class="skill-fill" style="width: <?php echo (int)$s['progress']; ?>%;"></div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="font-size:0.82rem; font-style: italic; color:var(--text-light);">No skills listed. Select skills in your profile details.</p>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Verification Certificate section -->
                        <section class="resume-section" id="section-verification">
                            <h3 class="section-title" id="head-verification"><i class="fa-solid fa-award"></i> <span class="sec-title-text">Verification</span></h3>
                            <div class="verification-badge">
                                <i class="fa-solid fa-shield-halved" style="color:var(--accent); font-size:1.1rem; float:left; margin-right:0.5rem; margin-top:0.1rem;"></i>
                                Verified by <strong>AlumniNet Academic Portal</strong>. Generated directly from registered campus database records.
                            </div>
                        </section>

                    </div>
                </div>

            </div>
            
        </main>
        
    </div>

    <!-- Toast message popup -->
    <div class="toast-notification" id="toast-notify">
        Profile synced and updated!
    </div>

    <!-- Customizing Controller Scripts -->
    <script>
        // Set accent colors dynamic mapping
        function setAccent(primaryHex, lightHex, darkHex, tintHex, element) {
            document.documentElement.style.setProperty('--accent', primaryHex);
            document.documentElement.style.setProperty('--accent-light', lightHex);
            document.documentElement.style.setProperty('--accent-dark', darkHex);
            document.documentElement.style.setProperty('--accent-tint', tintHex);
            
            // Toggle active highlight dots
            document.querySelectorAll('.color-dot').forEach(el => el.classList.remove('active'));
            if (element) element.classList.add('active');

            // Sync color picker value
            const picker = document.getElementById('custom-color-picker');
            if (picker) picker.value = primaryHex;
        }

        // Custom Color Picker dynamic shades calculations
        function updateCustomColor(hex) {
            document.documentElement.style.setProperty('--accent', hex);
            
            // Compute lighter and darker tints programmatically
            const r = parseInt(hex.slice(1,3), 16);
            const g = parseInt(hex.slice(3,5), 16);
            const b = parseInt(hex.slice(5,7), 16);
            
            document.documentElement.style.setProperty('--accent-light', `rgba(${r}, ${g}, ${b}, 0.08)`);
            document.documentElement.style.setProperty('--accent-dark', `rgb(${Math.max(0, r-40)}, ${Math.max(0, g-40)}, ${Math.max(0, b-40)})`);
            document.documentElement.style.setProperty('--accent-tint', `rgba(${r}, ${g}, ${b}, 0.04)`);
            
            // Deactivate default dots
            document.querySelectorAll('.color-dot').forEach(el => el.classList.remove('active'));
            
            const picker = document.getElementById('custom-color-picker');
            if (picker) picker.value = hex;
        }

        // Spacing Customizers
        function setPageMargin(val) {
            document.documentElement.style.setProperty('--page-margin', val + 'mm');
            document.getElementById('lbl-margin').textContent = val;
        }

        function setSectionSpacing(val) {
            document.documentElement.style.setProperty('--section-spacing', val + 'rem');
            document.getElementById('lbl-spacing').textContent = val;
        }

        // Header Title custom text updates
        function updateSectionTitle(sectionId, newTitle) {
            const header = document.getElementById('head-' + sectionId);
            if (header) {
                const textSpan = header.querySelector('.sec-title-text');
                if (textSpan) textSpan.textContent = newTitle;
            }
        }

        // Profile headshot toggle
        function toggleAvatar(show) {
            document.querySelectorAll('.resume-avatar-img').forEach(img => {
                img.style.display = show ? 'block' : 'none';
            });
        }

        // Set layout templates
        function setTemplate(style) {
            const sheet = document.getElementById('resume-sheet');
            
            // Remove previous classes
            sheet.classList.remove('template-classic', 'template-modern', 'template-creative', 'template-minimalist');
            sheet.classList.add('template-' + style);

            // Toggle layout options active button
            document.querySelectorAll('.layout-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('lay-' + style).classList.add('active');

            // Handle special templates structures (like creative template layout order adjustments)
            const standardHeader = document.getElementById('standard-header');
            const creativeMeta = document.getElementById('creative-header-meta');
            const gridContainer = document.getElementById('resume-grid-container');
            const mainCol = document.getElementById('resume-main-col');
            const sidebarCol = document.getElementById('resume-sidebar-col');

            if (style === 'creative') {
                standardHeader.style.display = 'none';
                creativeMeta.style.display = 'block';
                
                // For creative layout: place name header and contact groups inside the sidebar
                if (sidebarCol.firstElementChild !== creativeMeta) {
                    sidebarCol.insertBefore(creativeMeta, sidebarCol.firstChild);
                }
            } else {
                standardHeader.style.display = 'flex';
                creativeMeta.style.display = 'none';
                
                // Return creativeMeta to root context hidden
                if (sheet.firstElementChild !== creativeMeta) {
                    sheet.insertBefore(creativeMeta, sheet.firstChild);
                }
            }
        }

        // Toggle Font families
        function setFont(fontName) {
            document.documentElement.style.setProperty('--font-family', fontName + ', sans-serif');
        }

        // Toggle Section items
        function toggleSection(sectionId, isChecked) {
            const section = document.getElementById('section-' + sectionId);
            if (section) {
                section.style.display = isChecked ? 'block' : 'none';
            }
        }

        // Real-time Text updates
        function updateLiveText(fieldId, val) {
            const el = document.getElementById('val-' + fieldId);
            if (el) el.textContent = val;
            
            // Update creative-mode values as well
            if (fieldId === 'name') {
                document.getElementById('creative-name').textContent = val;
            } else if (fieldId === 'dept') {
                document.getElementById('creative-dept').textContent = val;
            } else if (fieldId === 'email') {
                document.getElementById('creative-c-email').textContent = val;
            }
        }

        function updateLiveBio(val) {
            const el = document.getElementById('val-bio');
            if (el) {
                // simple break conversion
                el.innerHTML = val.replace(/\n/g, '<br>');
            }
            
            // Auto hide/show section based on contents
            const section = document.getElementById('section-summary');
            const chk = document.getElementById('chk-summary');
            if (val.trim() === '') {
                section.style.display = 'none';
            } else if (chk.checked) {
                section.style.display = 'block';
            }
        }

        function updateLiveLink(linkType, val, iconClass) {
            const stdWrap = document.getElementById('val-' + linkType + '-wrap');
            const stdTxt = document.getElementById('val-' + linkType);
            
            const creativeWrap = document.getElementById('creative-c-' + linkType + '-wrap');
            const creativeTxt = document.getElementById('creative-c-' + linkType);

            // Clean clean val for printing
            const cleanVal = val.replace(/https?:\/\//i, '');

            if (val.trim() === '') {
                if (stdWrap) stdWrap.style.display = 'none';
                if (creativeWrap) creativeWrap.style.display = 'none';
            } else {
                if (stdWrap) {
                    stdWrap.style.display = 'flex';
                    stdTxt.textContent = cleanVal;
                }
                if (creativeWrap) {
                    creativeWrap.style.display = 'flex';
                    creativeTxt.textContent = cleanVal;
                }
            }
        }

        // AJAX profile sync save
        function saveProfileToDatabase() {
            const name = document.getElementById('inp-name').value;
            const bio = document.getElementById('inp-bio').value;
            const linkedin = document.getElementById('inp-linkedin').value;
            
            const githubInput = document.getElementById('inp-github');
            const websiteInput = document.getElementById('inp-website');
            
            const github = githubInput ? githubInput.value : '';
            const website = websiteInput ? websiteInput.value : '';

            const formData = new FormData();
            formData.append('action', 'save_profile');
            formData.append('name', name);
            formData.append('bio', bio);
            formData.append('linkedin', linkedin);
            formData.append('github', github);
            formData.append('website', website);

            fetch('resume_builder.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const toast = document.getElementById('toast-notify');
                if (data.status === 'success') {
                    toast.textContent = data.message;
                    toast.style.backgroundColor = '#10b981';
                } else {
                    toast.textContent = "Error saving profile: " + data.message;
                    toast.style.backgroundColor = '#ef4444';
                }
                
                // Render slide notification
                toast.classList.add('active');
                setTimeout(() => {
                    toast.classList.remove('active');
                }, 3000);
            })
            .catch(err => {
                console.error(err);
                alert("Failed to reach save engine. Sync error.");
            });
        }
    </script>
</body>
</html>

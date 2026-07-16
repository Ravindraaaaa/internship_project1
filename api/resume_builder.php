<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$user_id = get_user_id();
$role = $_SESSION['user_role'] ?? 'student';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume - <?php echo htmlspecialchars($user['name']); ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1e293b;
            --primary-light: #475569;
            --accent: #2563eb;
            --text-main: #334155;
            --text-light: #64748b;
            --border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            background-color: #f1f5f9;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Controls Area */
        .controls-panel {
            width: 100%;
            max-width: 800px;
            background: #ffffff;
            padding: 1rem 2rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .controls-panel h1 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: var(--accent);
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.88rem;
            border: none;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background-color: var(--primary);
        }

        /* Resume Sheet Layout */
        .resume-sheet {
            width: 100%;
            max-width: 800px;
            background: #ffffff;
            min-height: 297mm; /* A4 Ratio */
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.07), 0 4px 6px -4px rgb(0 0 0 / 0.07);
            position: relative;
        }

        /* Header block */
        .resume-header {
            border-bottom: 2px solid var(--border);
            padding-bottom: 2rem;
            margin-bottom: 2rem;
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
            font-weight: 500;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .header-right {
            text-align: right;
            font-size: 0.88rem;
            color: var(--text-light);
            line-height: 1.5;
        }

        .header-right div {
            margin-bottom: 0.25rem;
        }

        .header-right i {
            width: 16px;
            margin-right: 0.35rem;
            color: var(--primary-light);
            text-align: center;
        }

        /* Resume Content Columns */
        .resume-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2.5rem;
        }

        .section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.25rem;
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
        }

        .resume-section {
            margin-bottom: 2rem;
        }

        .summary-text {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text-main);
        }

        /* Entry timeline block */
        .timeline-item {
            margin-bottom: 1.5rem;
            position: relative;
            padding-left: 1rem;
            border-left: 2px solid var(--border);
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-item-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.15rem;
        }

        .timeline-item-meta {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }

        .timeline-item-meta span.date {
            color: var(--text-light);
            font-weight: 400;
        }

        .timeline-item-desc {
            font-size: 0.88rem;
            line-height: 1.5;
            color: var(--text-light);
        }

        /* Skills sidebar lists */
        .skills-list {
            list-style: none;
        }

        .skill-item {
            margin-bottom: 1rem;
        }

        .skill-name-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .skill-track {
            height: 6px;
            background-color: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }

        .skill-fill {
            height: 100%;
            background-color: var(--accent);
            border-radius: 3px;
        }

        /* Print Override styles */
        @media print {
            body {
                background: none;
                padding: 0;
            }

            .controls-panel {
                display: none;
            }

            .resume-sheet {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Controls Panel -->
    <div class="controls-panel">
        <h1>AlumniNet Dynamic Resume Builder</h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="../user/portfolio.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Portfolio</a>
            <button onclick="window.print()" class="btn"><i class="fa-solid fa-print"></i> Save / Print PDF</button>
        </div>
    </div>

    <!-- Resume Document -->
    <div class="resume-sheet">
        <header class="resume-header">
            <div class="header-left">
                <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                <p><?php echo htmlspecialchars($user['department_name'] ?? $role); ?></p>
            </div>
            <div class="header-right">
                <div><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                <?php if (!empty($profile['linkedin'])): ?>
                    <div><i class="fa-brands fa-linkedin"></i> <?php echo htmlspecialchars(str_replace(['https://', 'http://'], '', $profile['linkedin'])); ?></div>
                <?php endif; ?>
                <?php if ($role === 'student' && !empty($profile['github'])): ?>
                    <div><i class="fa-brands fa-github"></i> <?php echo htmlspecialchars(str_replace(['https://', 'http://'], '', $profile['github'])); ?></div>
                <?php endif; ?>
                <?php if ($role === 'alumni' && !empty($profile['company'])): ?>
                    <div><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($profile['position']); ?> at <?php echo htmlspecialchars($profile['company']); ?></div>
                <?php endif; ?>
            </div>
        </header>

        <div class="resume-grid">
            <!-- Left main column -->
            <div class="main-column">
                
                <!-- Summary section -->
                <?php if (!empty($profile['bio'])): ?>
                    <section class="resume-section">
                        <h3 class="section-title"><i class="fa-solid fa-user"></i> Professional Summary</h3>
                        <p class="summary-text"><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
                    </section>
                <?php endif; ?>

                <!-- Experience section -->
                <section class="resume-section">
                    <h3 class="section-title"><i class="fa-solid fa-briefcase"></i> Experience</h3>
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
                </section>

                <!-- Education section -->
                <section class="resume-section">
                    <h3 class="section-title"><i class="fa-solid fa-graduation-cap"></i> Education</h3>
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
                </section>

            </div>

            <!-- Right sidebar column -->
            <div class="sidebar-column">
                
                <!-- Skills list -->
                <section class="resume-section">
                    <h3 class="section-title"><i class="fa-solid fa-code"></i> Skills</h3>
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
                        <p style="font-size:0.88rem; font-style: italic; color:var(--text-light);">No skills listed. Select skills in your profile details.</p>
                    <?php endif; ?>
                </section>

                <!-- Custom credentials details -->
                <section class="resume-section" style="margin-top: 2.5rem;">
                    <h3 class="section-title"><i class="fa-solid fa-award"></i> Verification</h3>
                    <p style="font-size: 0.8rem; line-height: 1.6; color: var(--text-light);">
                        This resume is automatically generated by AlumniNet and verified using registered student & alumni directories.
                    </p>
                </section>

            </div>
        </div>
    </div>

</body>
</html>

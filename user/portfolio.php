<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

require_login();
handle_session_timeout();

$user_id = get_user_id();
$role = $_SESSION['user_role'] ?? 'student';
$user_name = $_SESSION['user_name'];

$page_title = "My Portfolio & File Locker";

// Fetch Core User details
$stmt = $pdo->prepare("SELECT email, two_factor_secret FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch();

// Check user status
$user_status = $_SESSION['user_status'] ?? 'approved';

// --- POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Add Skill
    if ($action === 'add_skill') {
        $skill_id = intval($_POST['skill_id'] ?? 0);
        $progress = intval($_POST['progress'] ?? 50);
        if ($skill_id > 0) {
            $stmt = $pdo->prepare("INSERT INTO user_skills (user_id, skill_id, progress) VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE progress = ?");
            $stmt->execute([$user_id, $skill_id, $progress, $progress]);
            log_activity($user_id, 'portfolio_add_skill', "Added or updated skill ID: $skill_id at $progress%");
            set_flash('success', 'Skill added successfully!');
        } else {
            set_flash('error', 'Please select a valid skill.');
        }
    } 
    
    // 2. Delete Skill
    elseif ($action === 'delete_skill') {
        $skill_id = intval($_POST['skill_id'] ?? 0);
        if ($skill_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM user_skills WHERE user_id = ? AND skill_id = ?");
            $stmt->execute([$user_id, $skill_id]);
            log_activity($user_id, 'portfolio_delete_skill', "Deleted skill ID: $skill_id");
            set_flash('success', 'Skill removed from profile.');
        }
    } 
    
    // 3. Update Skill Progress
    elseif ($action === 'update_skills') {
        $progress_updates = $_POST['progress_updates'] ?? [];
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE user_skills SET progress = ? WHERE user_id = ? AND skill_id = ?");
        foreach ($progress_updates as $sid => $progVal) {
            $stmt->execute([intval($progVal), $user_id, intval($sid)]);
        }
        $pdo->commit();
        log_activity($user_id, 'portfolio_update_skills', 'Updated skills progress levels.');
        set_flash('success', 'Skills progress updated!');
    }
    
    // 4. Delete Certificate
    elseif ($action === 'delete_certificate') {
        $cert_id = intval($_POST['certificate_id'] ?? 0);
        if ($cert_id > 0) {
            $stmt = $pdo->prepare("SELECT file_path, name FROM user_certificates WHERE id = ? AND user_id = ?");
            $stmt->execute([$cert_id, $user_id]);
            $cert = $stmt->fetch();
            if ($cert) {
                if (file_exists(__DIR__ . '/../' . $cert['file_path'])) {
                    @unlink(__DIR__ . '/../' . $cert['file_path']);
                }
                $stmtDel = $pdo->prepare("DELETE FROM user_certificates WHERE id = ?");
                $stmtDel->execute([$cert_id]);
                log_activity($user_id, 'portfolio_delete_certificate', "Deleted certificate: " . $cert['name']);
                set_flash('success', 'Certificate deleted successfully!');
            }
        }
    }
    
    // 5. Remove Bookmark
    elseif ($action === 'remove_bookmark') {
        $job_id = intval($_POST['job_id'] ?? 0);
        if ($job_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM bookmarked_jobs WHERE user_id = ? AND job_id = ?");
            $stmt->execute([$user_id, $job_id]);
            log_activity($user_id, 'portfolio_remove_bookmark', "Removed job bookmark ID: $job_id");
            set_flash('success', 'Job bookmark removed.');
        }
    }
    
    // 6. Remove Saved Event
    elseif ($action === 'remove_saved_event') {
        $event_id = intval($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM saved_events WHERE user_id = ? AND event_id = ?");
            $stmt->execute([$user_id, $event_id]);
            log_activity($user_id, 'portfolio_remove_saved_event', "Removed saved event ID: $event_id");
            set_flash('success', 'Event bookmark removed.');
        }
    }
    
    // 7. Toggle 2FA
    elseif ($action === 'toggle_2fa') {
        $two_fa = $_POST['two_fa'] ?? '';
        $val = ($two_fa === '1') ? 'enabled' : null;
        
        $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
        $stmt->execute([$val, $user_id]);
        
        log_activity($user_id, 'toggle_2fa', ($val === 'enabled') ? 'Enabled Two-Factor Authentication' : 'Disabled Two-Factor Authentication');
        set_flash('success', ($val === 'enabled') ? '2FA enabled successfully!' : '2FA disabled.');
        header('Location: portfolio.php');
        exit;
    }
    
    header('Location: portfolio.php');
    exit;
}

// --- FETCH DATA FOR DISPLAY ---

// 1. Calculate Profile Score
$profile_score = 30; // base score for credentials
$profile_suggestions = [];

if ($role === 'student') {
    $stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $prof = $stmt->fetch();
    if ($prof) {
        if (!empty($prof['bio'])) $profile_score += 20; else $profile_suggestions[] = "Add a bio describing your interests.";
        if (!empty($prof['profile_pic'])) $profile_score += 20; else $profile_suggestions[] = "Upload a profile picture.";
        if (!empty($prof['linkedin'])) $profile_score += 15; else $profile_suggestions[] = "Add your LinkedIn URL.";
        if (!empty($prof['github'])) $profile_score += 15; else $profile_suggestions[] = "Add your GitHub URL.";
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM alumni_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $prof = $stmt->fetch();
    if ($prof) {
        if (!empty($prof['bio'])) $profile_score += 15; else $profile_suggestions[] = "Add a bio describing your expertise.";
        if (!empty($prof['profile_pic'])) $profile_score += 15; else $profile_suggestions[] = "Upload a profile picture.";
        if (!empty($prof['linkedin'])) $profile_score += 15; else $profile_suggestions[] = "Add your LinkedIn URL.";
        if (!empty($prof['company'])) $profile_score += 15; else $profile_suggestions[] = "Add your current company.";
        if (!empty($prof['position'])) $profile_score += 10; else $profile_suggestions[] = "Add your current job position.";
    }
}

// 2. Fetch User Resume
$stmt = $pdo->prepare("SELECT file_path, uploaded_at FROM resumes WHERE user_id = ?");
$stmt->execute([$user_id]);
$resume = $stmt->fetch();
if ($resume) $profile_score = min(100, $profile_score + 10);
else $profile_suggestions[] = "Upload your professional resume.";

// 3. Fetch Skills
$stmt = $pdo->prepare("SELECT s.id, s.name, us.progress FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ?");
$stmt->execute([$user_id]);
$user_skills = $stmt->fetchAll();

// Fetch all available skills for adding
$stmt = $pdo->query("SELECT * FROM skills ORDER BY name ASC");
$all_skills = $stmt->fetchAll();

// 4. Fetch Certificates
$stmt = $pdo->prepare("SELECT * FROM user_certificates WHERE user_id = ? ORDER BY issue_date DESC");
$stmt->execute([$user_id]);
$certificates = $stmt->fetchAll();
if ($certificates) $profile_score = min(100, $profile_score + 10);
else $profile_suggestions[] = "Upload course certificates or training proofs.";

// 5. Fetch Bookmarks
$stmt = $pdo->prepare("SELECT j.id, j.title, j.company, j.location, bj.created_at FROM bookmarked_jobs bj JOIN jobs j ON bj.job_id = j.id WHERE bj.user_id = ?");
$stmt->execute([$user_id]);
$bookmarked_jobs = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT e.id, e.title, e.event_date, e.location, se.created_at FROM saved_events se JOIN events e ON se.event_id = e.id WHERE se.user_id = ?");
$stmt->execute([$user_id]);
$saved_events = $stmt->fetchAll();

// 6. Fetch Security Logs
$stmt = $pdo->prepare("SELECT action, details, ip_address, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$security_logs = $stmt->fetchAll();

$avatar_pic = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/../' . $prof['profile_pic'])) {
    $avatar_pic = '../' . $prof['profile_pic'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- ==================== SIDEBAR ==================== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="logo logo-text">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <button class="sidebar-toggle-btn" id="sidebar-toggle">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; text-align: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;" class="sidebar-profile-box">
            <img src="<?php echo htmlspecialchars($avatar_pic); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--theme-accent-purple);" class="user-sidebar-avatar">
            <div style="margin-top: 0.75rem;" class="link-text">
                <h4 style="font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;"><?php echo htmlspecialchars($user_name); ?></h4>
                <p style="font-size: 0.72rem; color: var(--theme-text-secondary); text-transform: uppercase;"><?php echo htmlspecialchars($role); ?> member</p>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="dashboard.php"><i data-lucide="gauge"></i> <span class="link-text">Dashboard</span></a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php"><i data-lucide="user"></i> <span class="link-text">My Profile</span></a>
            </li>
            <li class="sidebar-item">
                <a href="mentorship.php"><i data-lucide="handshake"></i> <span class="link-text">Mentorship</span></a>
            </li>
            <li class="sidebar-item">
                <a href="alumni.php"><i data-lucide="users"></i> <span class="link-text">Alumni Directory</span></a>
            </li>
            <li class="sidebar-item">
                <a href="jobs.php"><i data-lucide="briefcase"></i> <span class="link-text">Job Board</span></a>
            </li>
            <li class="sidebar-item">
                <a href="events.php"><i data-lucide="calendar"></i> <span class="link-text">Events Board</span></a>
            </li>
            <li class="sidebar-item active">
                <a href="portfolio.php"><i data-lucide="folder-kanban"></i> <span class="link-text">My Portfolio</span></a>
            </li>
            <li class="sidebar-item" style="margin-top: auto; border-top: 1px solid var(--theme-border); padding-top: 1rem;">
                <a href="../logout.php" style="color: var(--accent-danger);"><i data-lucide="log-out"></i> <span class="link-text">Sign Out</span></a>
            </li>
        </ul>
    </aside>

    <!-- ==================== MAIN WORKSPACE ==================== -->
    <div class="dashboard-content-area">
        <!-- Top Navbar -->
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--theme-text);">Portfolio Manager</h3>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open visual settings">
                    <i data-lucide="palette" style="width: 20px; height: 20px;"></i>
                </button>
            </div>
        </nav>

        <main class="dashboard-workspace" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; padding: 2rem;">
            <!-- LEFT MAIN COLUMNS -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- 1. Profile Completion Banner -->
                <div class="card-glass" style="display: flex; align-items: center; gap: 2rem; padding: 2rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <div style="position: relative; width: 100px; height: 100px; flex-shrink:0;">
                        <!-- Progress Circle -->
                        <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="3" />
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--theme-accent-blue)" stroke-width="3" stroke-dasharray="<?php echo $profile_score; ?>; 100" />
                        </svg>
                        <div style="position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); font-size: 1.35rem; font-weight: 700; color: #ffffff;"><?php echo $profile_score; ?>%</div>
                    </div>
                    <div>
                        <h3 style="font-size: 1.35rem; font-weight: 700; color: #ffffff; margin-bottom: 0.5rem;">Profile Completeness</h3>
                        <p style="font-size: 0.88rem; color: var(--theme-text-secondary); line-height: 1.5;">
                            <?php if ($profile_score >= 90): ?>
                                Excellent! Your profile is highly detailed. This helps you get better mentorship matches and recruiter recommendations.
                            <?php else: ?>
                                Complete your profile information to unlock all premium system matching suggestions.
                            <?php endif; ?>
                        </p>
                        <?php if ($profile_suggestions): ?>
                            <ul style="font-size: 0.82rem; color: var(--theme-accent-purple); margin-top: 0.75rem; padding-left: 1.25rem;">
                                <?php foreach (array_slice($profile_suggestions, 0, 2) as $sugg): ?>
                                    <li><?php echo htmlspecialchars($sugg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Resume & CV Section -->
                <div class="card-glass" style="padding: 2rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #ffffff; margin-bottom: 1.25rem; display:flex; align-items:center; gap:0.5rem;"><i class="fa-solid fa-file-invoice" style="color:var(--theme-accent-blue);"></i> Resume & CV Locker</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <!-- Upload custom resume -->
                        <div style="background: rgba(255,255,255,0.01); border: 1px dashed var(--theme-border); padding: 1.5rem; border-radius: var(--border-radius-sm); text-align: center;">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2rem; color: var(--theme-text-secondary); margin-bottom: 0.75rem;"></i>
                            <h4 style="font-size: 0.95rem; font-weight:600; margin-bottom: 0.25rem;">Upload custom PDF</h4>
                            <p style="font-size: 0.75rem; color: var(--theme-text-secondary); margin-bottom: 1rem;">Supports PDF, DOCX up to 5MB</p>
                            
                            <form id="resume-upload-form">
                                <label class="btn btn-secondary btn-small" style="cursor: pointer; display: inline-flex;">
                                    <i class="fa-solid fa-paperclip"></i> Select File
                                    <input type="file" id="resume-file-input" accept=".pdf,.doc,.docx" style="display:none;" onchange="uploadResumeFile()">
                                </label>
                            </form>
                            
                            <div id="resume-upload-status" style="margin-top:0.5rem; font-size:0.8rem;"></div>
                            
                            <?php if ($resume): ?>
                                <div style="margin-top: 1rem; font-size: 0.82rem; color: var(--theme-text-secondary);">
                                    Active Resume: <a href="../<?php echo htmlspecialchars($resume['file_path']); ?>" target="_blank" style="color: var(--theme-accent-blue); font-weight: 600;"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Dynamic Resume builder link -->
                        <div style="background: rgba(255,255,255,0.01); border: 1px solid var(--theme-border); padding: 1.5rem; border-radius: var(--border-radius-sm); display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
                            <i class="fa-solid fa-wand-magic-sparkles" style="font-size: 2rem; color: var(--theme-accent-purple); margin-bottom: 0.75rem;"></i>
                            <h4 style="font-size: 0.95rem; font-weight:600; margin-bottom: 0.25rem;">AlumniNet Resume Builder</h4>
                            <p style="font-size: 0.75rem; color: var(--theme-text-secondary); margin-bottom: 1.25rem;">Generate a professional resume instantly from your profile history</p>
                            <a href="../api/resume_builder.php" class="btn btn-primary btn-small" target="_blank"><i class="fa-solid fa-play"></i> Launch Builder</a>
                        </div>
                    </div>
                </div>

                <!-- 3. Skill & Progress Manager -->
                <div class="card-glass" style="padding: 2rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #ffffff; margin-bottom: 1.25rem; display:flex; align-items:center; gap:0.5rem;"><i class="fa-solid fa-bolt" style="color:var(--theme-accent-purple);"></i> Technical Skills Tracker</h3>
                    
                    <form action="portfolio.php" method="POST" style="margin-bottom: 2rem;">
                        <input type="hidden" name="action" value="add_skill">
                        <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; align-items: flex-end;">
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Select Skill</label>
                                <select name="skill_id" class="input-glass" style="width:100%;" required>
                                    <option value="">-- Choose Skill --</option>
                                    <?php foreach ($all_skills as $s): ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Progress (%)</label>
                                <input type="number" name="progress" min="0" max="100" value="80" class="input-glass" style="width:100%;" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add</button>
                        </div>
                    </form>

                    <!-- Active Skills Progress Bars -->
                    <?php if ($user_skills): ?>
                        <form action="portfolio.php" method="POST">
                            <input type="hidden" name="action" value="update_skills">
                            <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 1.5rem;">
                                <?php foreach ($user_skills as $us): ?>
                                    <div>
                                        <div style="display: flex; justify-content: space-between; font-size: 0.88rem; font-weight:600; margin-bottom: 0.4rem; color:#ffffff;">
                                            <span><?php echo htmlspecialchars($us['name']); ?></span>
                                            <span><?php echo $us['progress']; ?>%</span>
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center;">
                                            <input type="range" name="progress_updates[<?php echo $us['id']; ?>]" min="0" max="100" value="<?php echo $us['progress']; ?>" class="custom-range" style="flex-grow:1;">
                                            <button type="button" class="btn btn-danger btn-small" onclick="deleteSkill(<?php echo $us['id']; ?>)" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-secondary btn-small"><i class="fa-solid fa-floppy-disk"></i> Save Skill Updates</button>
                        </form>
                    <?php else: ?>
                        <p style="font-size:0.88rem; color: var(--theme-text-secondary); font-style: italic;">No skills added to your tracker yet.</p>
                    <?php endif; ?>
                </div>

                <!-- 4. Certificate Locker -->
                <div class="card-glass" style="padding: 2rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #ffffff; margin-bottom: 1.25rem; display:flex; align-items:center; gap:0.5rem;"><i class="fa-solid fa-award" style="color:var(--theme-accent-blue);"></i> Credentials & Certificates</h3>
                    
                    <!-- Upload Certificate Form -->
                    <form id="cert-upload-form" style="margin-bottom: 2rem; padding: 1.25rem; background: rgba(255,255,255,0.01); border: 1px solid var(--theme-border); border-radius: var(--border-radius-sm);">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Certificate Name</label>
                                <input type="text" id="cert-name" class="input-glass" style="width:100%;" placeholder="e.g. AWS Certified Developer" required>
                            </div>
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Issuing Organization</label>
                                <input type="text" id="cert-issuer" class="input-glass" style="width:100%;" placeholder="e.g. Amazon Web Services" required>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; align-items: flex-end;">
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Issue Date</label>
                                <input type="date" id="cert-date" class="input-glass" style="width:100%;" required>
                            </div>
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Select PDF / Image</label>
                                <input type="file" id="cert-file" accept=".pdf,.png,.jpg,.jpeg" class="input-glass" style="width:100%; padding:0.4rem;" required>
                            </div>
                        </div>
                        <button type="button" onclick="uploadCertificate()" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Credential</button>
                        <div id="cert-upload-status" style="margin-top:0.75rem; font-size:0.82rem;"></div>
                    </form>

                    <!-- Listed Certificates -->
                    <?php if ($certificates): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <?php foreach ($certificates as $cert): ?>
                                <div class="card-glass" style="background:rgba(255,255,255,0.02); border-color:var(--theme-border); padding: 1.25rem; border-radius: var(--border-radius-sm); display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <h4 style="font-size:0.95rem; font-weight:700; color:#ffffff; margin-bottom:0.15rem;"><?php echo htmlspecialchars($cert['name']); ?></h4>
                                        <p style="font-size:0.8rem; color:var(--theme-text-secondary); margin-bottom:0.4rem;">Issued by: <?php echo htmlspecialchars($cert['issuer']); ?></p>
                                        <p style="font-size:0.75rem; color:var(--theme-text-secondary);"><i class="fa-solid fa-calendar-days"></i> <?php echo date('M Y', strtotime($cert['issue_date'])); ?></p>
                                    </div>
                                    <div style="display:flex; gap:0.4rem;">
                                        <a href="../<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank" class="btn btn-secondary btn-small" style="padding:0.4rem;" title="View Credential"><i class="fa-solid fa-eye"></i></a>
                                        <button class="btn btn-danger btn-small" style="padding:0.4rem;" onclick="deleteCertificate(<?php echo $cert['id']; ?>)" title="Delete Credential"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.88rem; color: var(--theme-text-secondary); font-style: italic;">No credential records uploaded yet.</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- RIGHT COLUMN BAR -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- 1. Enterprise Bookmarks -->
                <div class="card-glass" style="padding: 1.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #ffffff; margin-bottom: 1rem; display:flex; align-items:center; gap:0.5rem;"><i class="fa-solid fa-bookmark" style="color:var(--theme-accent-blue);"></i> Saved Jobs</h3>
                    <?php if ($bookmarked_jobs): ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($bookmarked_jobs as $bj): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.02); padding: 0.75rem; border-radius: var(--border-radius-sm); border: 1px solid var(--theme-border);">
                                    <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:70%;">
                                        <a href="jobs.php?id=<?php echo $bj['id']; ?>" style="font-size:0.82rem; font-weight:600; color:#ffffff; text-decoration:none;"><?php echo htmlspecialchars($bj['title']); ?></a>
                                        <p style="font-size:0.72rem; color:var(--theme-text-secondary);"><?php echo htmlspecialchars($bj['company']); ?></p>
                                    </div>
                                    <form action="portfolio.php" method="POST">
                                        <input type="hidden" name="action" value="remove_bookmark">
                                        <input type="hidden" name="job_id" value="<?php echo $bj['id']; ?>">
                                        <button type="submit" style="background:none; border:none; color:var(--accent-danger); cursor:pointer;" title="Remove Saved Job"><i class="fa-solid fa-circle-xmark"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.82rem; color:var(--theme-text-secondary); font-style:italic;">No saved jobs bookmarks.</p>
                    <?php endif; ?>
                </div>

                <div class="card-glass" style="padding: 1.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #ffffff; margin-bottom: 1rem; display:flex; align-items:center; gap:0.5rem;"><i class="fa-solid fa-calendar-heart" style="color:var(--theme-accent-purple);"></i> Saved Events</h3>
                    <?php if ($saved_events): ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($saved_events as $se): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.02); padding: 0.75rem; border-radius: var(--border-radius-sm); border: 1px solid var(--theme-border);">
                                    <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:70%;">
                                        <a href="events.php?id=<?php echo $se['id']; ?>" style="font-size:0.82rem; font-weight:600; color:#ffffff; text-decoration:none;"><?php echo htmlspecialchars($se['title']); ?></a>
                                        <p style="font-size:0.72rem; color:var(--theme-text-secondary);"><?php echo date('M d, Y', strtotime($se['event_date'])); ?></p>
                                    </div>
                                    <form action="portfolio.php" method="POST">
                                        <input type="hidden" name="action" value="remove_saved_event">
                                        <input type="hidden" name="event_id" value="<?php echo $se['id']; ?>">
                                        <button type="submit" style="background:none; border:none; color:var(--accent-danger); cursor:pointer;" title="Remove Saved Event"><i class="fa-solid fa-circle-xmark"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.82rem; color:var(--theme-text-secondary); font-style:italic;">No saved events scheduled.</p>
                    <?php endif; ?>
                </div>

                <!-- 2. Security Configuration Settings -->
                <div class="card-glass" style="padding: 1.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #ffffff; margin-bottom: 1.25rem; display:flex; align-items:center; gap:0.5rem;"><i class="fa-solid fa-shield-halved" style="color:var(--theme-accent-purple);"></i> Security Center</h3>
                    
                    <!-- Toggle 2FA -->
                    <form action="portfolio.php" method="POST" id="toggle-2fa-form" style="margin-bottom: 1.5rem;">
                        <input type="hidden" name="action" value="toggle_2fa">
                        <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.02); padding:0.8rem; border-radius:var(--border-radius-sm); border:1px solid var(--theme-border);">
                            <div>
                                <span style="font-size:0.82rem; font-weight:600; color:#ffffff; display:block;">Two-Factor Auth</span>
                                <span style="font-size:0.72rem; color:var(--theme-text-secondary);">Toggle login verification</span>
                            </div>
                            <label class="switch-container">
                                <input type="checkbox" name="two_fa" value="1" <?php echo $userData['two_factor_secret'] === 'enabled' ? 'checked' : ''; ?> onchange="document.getElementById('toggle-2fa-form').submit()">
                                <span class="switch-slider"></span>
                            </label>
                        </div>
                    </form>

                    <!-- Security Log list -->
                    <h4 style="font-size:0.8rem; font-weight:700; text-transform:uppercase; color:var(--theme-text-secondary); margin-bottom:0.75rem;">Recent Security Audits</h4>
                    <?php if ($security_logs): ?>
                        <div style="display:flex; flex-direction:column; gap:0.65rem;">
                            <?php foreach ($security_logs as $log): ?>
                                <div style="font-size:0.72rem; background:rgba(0,0,0,0.1); padding:0.65rem; border-radius:var(--border-radius-sm); border:1px solid rgba(255,255,255,0.02);">
                                    <div style="font-weight:600; color:#ffffff;"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))); ?></div>
                                    <p style="color:var(--theme-text-secondary); margin:0.15rem 0;"><?php echo htmlspecialchars($log['details']); ?></p>
                                    <span style="font-size:0.65rem; opacity:0.6; color:var(--theme-text-secondary);"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?> | IP: <?php echo htmlspecialchars($log['ip_address']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.78rem; color:var(--theme-text-secondary); font-style:italic;">No security logs recorded.</p>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- Forms for hidden actions -->
<form id="delete-skill-form" action="portfolio.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_skill">
    <input type="hidden" name="skill_id" id="delete-skill-id">
</form>

<form id="delete-cert-form" action="portfolio.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_certificate">
    <input type="hidden" name="certificate_id" id="delete-cert-id">
</form>

<script>
function deleteSkill(id) {
    if (confirm('Are you sure you want to remove this skill from your profile?')) {
        document.getElementById('delete-skill-id').value = id;
        document.getElementById('delete-skill-form').submit();
    }
}

function deleteCertificate(id) {
    if (confirm('Are you sure you want to delete this certificate? This action is irreversible.')) {
        document.getElementById('delete-cert-id').value = id;
        document.getElementById('delete-cert-form').submit();
    }
}

function uploadResumeFile() {
    const input = document.getElementById('resume-file-input');
    const status = document.getElementById('resume-upload-status');
    if (!input.files || input.files.length === 0) return;
    
    status.style.color = 'var(--theme-text-secondary)';
    status.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
    
    const formData = new FormData();
    formData.append('file', input.files[0]);
    formData.append('type', 'resume');
    
    fetch('../api/upload_file.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            status.style.color = '#22c55e';
            status.innerHTML = '<i class="fa-solid fa-circle-check"></i> Uploaded! Reloading...';
            setTimeout(() => location.reload(), 1000);
        } else {
            status.style.color = '#ef4444';
            status.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + (data.error || 'Failed.');
        }
    })
    .catch(err => {
        status.style.color = '#ef4444';
        status.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Upload failed.';
    });
}

function uploadCertificate() {
    const name = document.getElementById('cert-name').value;
    const issuer = document.getElementById('cert-issuer').value;
    const date = document.getElementById('cert-date').value;
    const fileInput = document.getElementById('cert-file');
    const status = document.getElementById('cert-upload-status');
    
    if (!name || !issuer || !date || !fileInput.files || fileInput.files.length === 0) {
        status.style.color = '#ef4444';
        status.innerHTML = 'Please fill out all credential fields and select a file.';
        return;
    }
    
    status.style.color = 'var(--theme-text-secondary)';
    status.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading credential...';
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('type', 'certificate');
    formData.append('name', name);
    formData.append('issuer', issuer);
    formData.append('issue_date', date);
    
    fetch('../api/upload_file.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            status.style.color = '#22c55e';
            status.innerHTML = '<i class="fa-solid fa-circle-check"></i> Certificate saved! Reloading...';
            setTimeout(() => location.reload(), 1000);
        } else {
            status.style.color = '#ef4444';
            status.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + (data.error || 'Failed.');
        }
    })
    .catch(err => {
        status.style.color = '#ef4444';
        status.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Upload failed.';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

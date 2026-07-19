<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

require_admin();
handle_session_timeout();

$admin_id = get_user_id();
$user_name = $_SESSION['admin_name'];
$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png';

$page_title = "Manage Eligibility Requirements";

// Fetch Departments
$stmt = $pdo->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $stmt->fetchAll();

// Fetch Active Jobs
$stmt = $pdo->query("SELECT id, title, company FROM jobs ORDER BY title ASC");
$jobs = $stmt->fetchAll();

// Fetch Active Events
$stmt = $pdo->query("SELECT id, title FROM events ORDER BY title ASC");
$events = $stmt->fetchAll();

// --- POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Create Requirement
    if ($action === 'create_requirement') {
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['type'] ?? 'internship';
        $min_cgpa = floatval($_POST['min_cgpa'] ?? 0.0);
        $allowed_deps = isset($_POST['departments']) ? implode(',', $_POST['departments']) : '';
        $skills_req = trim($_POST['skills_required'] ?? '');
        $deadline = $_POST['deadline'] ?? null;
        $req_docs = trim($_POST['required_documents'] ?? '');
        
        if (empty($title)) {
            set_flash('error', 'Requirement title is required.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO requirements (title, type, min_cgpa, allowed_departments, skills_required, deadline, required_documents) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $type, $min_cgpa, $allowed_deps, $skills_req, empty($deadline) ? null : $deadline, $req_docs]);
            log_activity($admin_id, 'create_requirement', "Created requirement: '$title'");
            set_flash('success', 'Eligibility requirement created successfully!');
        }
    } 
    
    // 2. Delete Requirement
    elseif ($action === 'delete_requirement') {
        $req_id = intval($_POST['requirement_id'] ?? 0);
        if ($req_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM requirements WHERE id = ?");
            $stmt->execute([$req_id]);
            log_activity($admin_id, 'delete_requirement', "Deleted requirement ID: $req_id");
            set_flash('success', 'Requirement deleted successfully.');
        }
    }
    
    // 3. Map to Job
    elseif ($action === 'map_job') {
        $job_id = intval($_POST['job_id'] ?? 0);
        $req_id = intval($_POST['requirement_id'] ?? 0);
        if ($job_id > 0 && $req_id > 0) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO job_requirements (job_id, requirement_id) VALUES (?, ?)");
            $stmt->execute([$job_id, $req_id]);
            log_activity($admin_id, 'map_job_requirement', "Mapped Job ID $job_id to Req ID $req_id");
            set_flash('success', 'Requirement mapped to job!');
        }
    }
    
    // 4. Map to Event
    elseif ($action === 'map_event') {
        $event_id = intval($_POST['event_id'] ?? 0);
        $req_id = intval($_POST['requirement_id'] ?? 0);
        if ($event_id > 0 && $req_id > 0) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO event_requirements (event_id, requirement_id) VALUES (?, ?)");
            $stmt->execute([$event_id, $req_id]);
            log_activity($admin_id, 'map_event_requirement', "Mapped Event ID $event_id to Req ID $req_id");
            set_flash('success', 'Requirement mapped to event!');
        }
    }
    
    // 5. Unmap Job
    elseif ($action === 'unmap_job') {
        $job_id = intval($_POST['job_id'] ?? 0);
        $req_id = intval($_POST['requirement_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM job_requirements WHERE job_id = ? AND requirement_id = ?");
        $stmt->execute([$job_id, $req_id]);
        log_activity($admin_id, 'unmap_job_requirement', "Unmapped Job ID $job_id from Req ID $req_id");
        set_flash('success', 'Job mapping removed.');
    }
    
    // 6. Unmap Event
    elseif ($action === 'unmap_event') {
        $event_id = intval($_POST['event_id'] ?? 0);
        $req_id = intval($_POST['requirement_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM event_requirements WHERE event_id = ? AND requirement_id = ?");
        $stmt->execute([$event_id, $req_id]);
        log_activity($admin_id, 'unmap_event_requirement', "Unmapped Event ID $event_id from Req ID $req_id");
        set_flash('success', 'Event mapping removed.');
    }
    
    header('Location: requirements.php');
    exit;
}

// --- FETCH REQUIREMENTS & MAPPINGS ---
$stmt = $pdo->query("SELECT * FROM requirements ORDER BY created_at DESC");
$requirements_list = $stmt->fetchAll();

$stmt = $pdo->query("SELECT jr.job_id, jr.requirement_id, j.title as job_title, j.company, r.title as req_title 
                     FROM job_requirements jr 
                     JOIN jobs j ON jr.job_id = j.id 
                     JOIN requirements r ON jr.requirement_id = r.id");
$job_mappings = $stmt->fetchAll();

$stmt = $pdo->query("SELECT er.event_id, er.requirement_id, e.title as event_title, r.title as req_title 
                     FROM event_requirements er 
                     JOIN events e ON er.event_id = e.id 
                     JOIN requirements r ON er.requirement_id = r.id");
$event_mappings = $stmt->fetchAll();

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
            <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--theme-accent-purple);" class="user-sidebar-avatar">
            <div style="margin-top: 0.75rem;" class="link-text">
                <h4 style="font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;"><?php echo htmlspecialchars($user_name); ?></h4>
                <p style="font-size: 0.72rem; color: var(--theme-text-secondary); text-transform: uppercase;">Admin Portal</p>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="dashboard.php?tab=overview"><i data-lucide="gauge"></i> <span class="link-text">Dashboard</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=alumni"><i data-lucide="user-check"></i> <span class="link-text">Manage Alumni</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=students"><i data-lucide="users"></i> <span class="link-text">Manage Students</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=jobs"><i data-lucide="briefcase"></i> <span class="link-text">Manage Jobs</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=events"><i data-lucide="calendar"></i> <span class="link-text">Manage Events</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=messages"><i data-lucide="messages-square"></i> <span class="link-text">System Messages</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=reports"><i data-lucide="line-chart"></i> <span class="link-text">Reports</span></a>
            </li>
            <li class="sidebar-item active">
                <a href="requirements.php"><i data-lucide="shield-check"></i> <span class="link-text">Requirements</span></a>
            </li>
            <li class="sidebar-item">
                <a href="enterprise_control.php"><i data-lucide="settings-2"></i> <span class="link-text">Control Center</span></a>
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
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--theme-text);">Requirement & Eligibility Manager</h3>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open visual settings">
                    <i data-lucide="palette" style="width: 20px; height: 20px;"></i>
                </button>
            </div>
        </nav>

        <main class="dashboard-workspace" style="display: grid; grid-template-columns: 3fr 2fr; gap: 2rem; padding: 2rem;">
            <!-- LEFT COLUMN: CREATE & LIST -->
            <div style="display:flex; flex-direction:column; gap:2rem;">
                
                <!-- Create Requirement Form -->
                <div class="card-glass" style="padding: 2rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #ffffff; margin-bottom: 1.25rem; display:flex; align-items:center; gap:0.5rem;"><i class="fa-solid fa-plus-circle" style="color:var(--theme-accent-blue);"></i> Create Eligibility Filter</h3>
                    
                    <form action="requirements.php" method="POST">
                        <input type="hidden" name="action" value="create_requirement">
                        
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Requirement Title</label>
                            <input type="text" name="title" class="input-glass" style="width:100%;" placeholder="e.g. Google SWE Eligibility Criteria" required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Type</label>
                                <select name="type" class="input-glass" style="width:100%;">
                                    <option value="internship">Internship Criteria</option>
                                    <option value="placement">Placement Criteria</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Min CGPA</label>
                                <input type="number" step="0.01" name="min_cgpa" min="0" max="10" value="7.5" class="input-glass" style="width:100%;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Allowed Departments (Branches)</label>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background:rgba(0,0,0,0.1); padding:0.75rem; border-radius:var(--border-radius-sm);">
                                <?php foreach ($departments as $d): ?>
                                    <label style="font-size:0.78rem; display:flex; align-items:center; gap:0.4rem; color:var(--theme-text);">
                                        <input type="checkbox" name="departments[]" value="<?php echo $d['id']; ?>" checked>
                                        <?php echo htmlspecialchars($d['code']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Required Skills (Comma-separated)</label>
                                <input type="text" name="skills_required" class="input-glass" style="width:100%;" placeholder="e.g. PHP, MySQL, CSS3">
                            </div>
                            <div class="form-group">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Required Documents (Comma-separated)</label>
                                <input type="text" name="required_documents" class="input-glass" style="width:100%;" placeholder="e.g. Resume, Marksheet">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Deadline Date</label>
                            <input type="date" name="deadline" class="input-glass" style="width:100%;">
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-square-check"></i> Save Eligibility Rule</button>
                    </form>
                </div>

                <!-- Requirement List -->
                <div class="card-glass" style="padding: 2rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #ffffff; margin-bottom: 1.25rem;"><i class="fa-solid fa-list-check" style="color:var(--theme-accent-purple);"></i> Configured Filters</h3>
                    
                    <?php if ($requirements_list): ?>
                        <div style="display:flex; flex-direction:column; gap:1rem;">
                            <?php foreach ($requirements_list as $req): ?>
                                <div style="background:rgba(255,255,255,0.02); padding: 1.25rem; border-radius: var(--border-radius-sm); border: 1px solid var(--theme-border); display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <h4 style="font-size:0.98rem; font-weight:700; color:#ffffff;"><?php echo htmlspecialchars($req['title']); ?></h4>
                                        <p style="font-size:0.75rem; color:var(--theme-text-secondary); margin:0.25rem 0;">Type: <?php echo ucfirst($req['type']); ?> | Min CGPA: <?php echo $req['min_cgpa']; ?></p>
                                        <?php if (!empty($req['skills_required'])): ?>
                                            <span style="font-size:0.7rem; background:rgba(139, 92, 246, 0.15); color:var(--theme-accent-purple); padding:0.15rem 0.4rem; border-radius:3px; display:inline-block; margin-top:0.25rem;">Skills: <?php echo htmlspecialchars($req['skills_required']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <form action="requirements.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this requirement?');">
                                        <input type="hidden" name="action" value="delete_requirement">
                                        <input type="hidden" name="requirement_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small" style="padding:0.4rem;"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.88rem; color: var(--theme-text-secondary); font-style: italic;">No requirements created yet.</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- RIGHT COLUMN: MAPPING -->
            <div style="display:flex; flex-direction:column; gap:2rem;">
                
                <!-- Map to Job Form -->
                <div class="card-glass" style="padding: 1.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #ffffff; margin-bottom: 1rem;"><i class="fa-solid fa-link" style="color:var(--theme-accent-blue);"></i> Map to Job Post</h3>
                    <form action="requirements.php" method="POST">
                        <input type="hidden" name="action" value="map_job">
                        <div class="form-group" style="margin-bottom: 0.85rem;">
                            <label style="display:block; font-size: 0.78rem; margin-bottom: 0.35rem; color: var(--theme-text-secondary);">Select Job Posting</label>
                            <select name="job_id" class="input-glass" style="width:100%;" required>
                                <option value="">-- Choose Job --</option>
                                <?php foreach ($jobs as $j): ?>
                                    <option value="<?php echo $j['id']; ?>"><?php echo htmlspecialchars($j['title']); ?> (<?php echo htmlspecialchars($j['company']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="display:block; font-size: 0.78rem; margin-bottom: 0.35rem; color: var(--theme-text-secondary);">Select Requirement</label>
                            <select name="requirement_id" class="input-glass" style="width:100%;" required>
                                <option value="">-- Choose Requirement --</option>
                                <?php foreach ($requirements_list as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-small" style="width:100%;"><i class="fa-solid fa-link"></i> Map Job Filter</button>
                    </form>
                </div>

                <!-- Map to Event Form -->
                <div class="card-glass" style="padding: 1.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #ffffff; margin-bottom: 1rem;"><i class="fa-solid fa-link" style="color:var(--theme-accent-purple);"></i> Map to Event Attendance</h3>
                    <form action="requirements.php" method="POST">
                        <input type="hidden" name="action" value="map_event">
                        <div class="form-group" style="margin-bottom: 0.85rem;">
                            <label style="display:block; font-size: 0.78rem; margin-bottom: 0.35rem; color: var(--theme-text-secondary);">Select Event</label>
                            <select name="event_id" class="input-glass" style="width:100%;" required>
                                <option value="">-- Choose Event --</option>
                                <?php foreach ($events as $e): ?>
                                    <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="display:block; font-size: 0.78rem; margin-bottom: 0.35rem; color: var(--theme-text-secondary);">Select Requirement</label>
                            <select name="requirement_id" class="input-glass" style="width:100%;" required>
                                <option value="">-- Choose Requirement --</option>
                                <?php foreach ($requirements_list as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-small" style="width:100%;"><i class="fa-solid fa-link"></i> Map Event Filter</button>
                    </form>
                </div>

                <!-- Listed Mappings -->
                <div class="card-glass" style="padding: 1.5rem; border-radius: var(--border-radius-lg); background: var(--theme-card); border: 1px solid var(--theme-border);">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #ffffff; margin-bottom: 1rem;"><i class="fa-solid fa-circle-nodes" style="color:var(--theme-accent-blue);"></i> Active Mappings</h3>
                    
                    <h4 style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--theme-text-secondary); margin-bottom:0.5rem;">Job Eligibility Filters</h4>
                    <?php if ($job_mappings): ?>
                        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.5rem;">
                            <?php foreach ($job_mappings as $jm): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.01); border:1px solid var(--theme-border); padding:0.6rem; border-radius:var(--border-radius-sm); font-size:0.78rem;">
                                    <div>
                                        <span style="font-weight:600; color:#ffffff;"><?php echo htmlspecialchars($jm['job_title']); ?></span>
                                        <p style="font-size:0.7rem; color:var(--theme-accent-blue);">Rule: <?php echo htmlspecialchars($jm['req_title']); ?></p>
                                    </div>
                                    <form action="requirements.php" method="POST">
                                        <input type="hidden" name="action" value="unmap_job">
                                        <input type="hidden" name="job_id" value="<?php echo $jm['job_id']; ?>">
                                        <input type="hidden" name="requirement_id" value="<?php echo $jm['requirement_id']; ?>">
                                        <button type="submit" style="background:none; border:none; color:var(--accent-danger); cursor:pointer;" title="Remove mapping"><i class="fa-solid fa-circle-xmark"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.75rem; color:var(--theme-text-secondary); font-style:italic; margin-bottom:1.5rem;">No active job filters mapped.</p>
                    <?php endif; ?>

                    <h4 style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--theme-text-secondary); margin-bottom:0.5rem;">Event Eligibility Filters</h4>
                    <?php if ($event_mappings): ?>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <?php foreach ($event_mappings as $em): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.01); border:1px solid var(--theme-border); padding:0.6rem; border-radius:var(--border-radius-sm); font-size:0.78rem;">
                                    <div>
                                        <span style="font-weight:600; color:#ffffff;"><?php echo htmlspecialchars($em['event_title']); ?></span>
                                        <p style="font-size:0.7rem; color:var(--theme-accent-purple);">Rule: <?php echo htmlspecialchars($em['req_title']); ?></p>
                                    </div>
                                    <form action="requirements.php" method="POST">
                                        <input type="hidden" name="action" value="unmap_event">
                                        <input type="hidden" name="event_id" value="<?php echo $em['event_id']; ?>">
                                        <input type="hidden" name="requirement_id" value="<?php echo $em['requirement_id']; ?>">
                                        <button type="submit" style="background:none; border:none; color:var(--accent-danger); cursor:pointer;" title="Remove mapping"><i class="fa-solid fa-circle-xmark"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.75rem; color:var(--theme-text-secondary); font-style:italic;">No active event filters mapped.</p>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

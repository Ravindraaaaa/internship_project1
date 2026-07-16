<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

$user_status = 'pending';
if (is_logged_in() && !is_admin()) {
    $stmtStatus = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmtStatus->execute([get_user_id()]);
    $user_status = $stmtStatus->fetchColumn();
} elseif (is_admin()) {
    $user_status = 'approved';
}

// 1. Process Job Post (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_job') {
    if (!is_admin() && (get_user_role() !== 'alumni' || $user_status !== 'approved')) {
        set_flash('error', 'Only approved alumni members or administrators can post jobs.');
        header('Location: jobs.php');
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $salary = trim($_POST['salary_range'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $app_link = trim($_POST['application_link'] ?? '');

    if (empty($title) || empty($company) || empty($location) || empty($type) || empty($description) || empty($requirements) || empty($app_link)) {
        set_flash('error', 'All details except salary range are required.');
    } else {
        try {
            $posted_by = get_user_id();
            $poster_role = is_admin() ? 'admin' : 'user';
            
            $stmtInsert = $pdo->prepare("INSERT INTO jobs (posted_by, poster_role, title, company, location, type, salary_range, description, requirements, application_link, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmtInsert->execute([$posted_by, $poster_role, $title, $company, $location, $type, $salary, $description, $requirements, $app_link]);
            
            set_flash('success', 'Job posting published successfully!');
        } catch (Exception $e) {
            set_flash('error', 'Failed to publish job: ' . $e->getMessage());
        }
    }
    header('Location: jobs.php');
    exit;
}

// 2. Filters
$search = trim($_GET['search'] ?? '');
$type_filter = trim($_GET['type'] ?? '');

$query = "SELECT * FROM jobs WHERE status = 'active'";
$params = [];

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR company LIKE ? OR location LIKE ? OR description LIKE ?)";
    $search_param = "%{$search}%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
}

if (!empty($type_filter)) {
    $query .= " AND type = ?";
    $params[] = $type_filter;
}

$query .= " ORDER BY created_at DESC";

try {
    $stmtJobs = $pdo->prepare($query);
    $stmtJobs->execute($params);
    $active_jobs = $stmtJobs->fetchAll();
} catch (Exception $e) {
    $active_jobs = [];
}

$page_title = "Careers Portal";
$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';

if (is_logged_in() && !is_admin()) {
    $uid = get_user_id();
    $role = get_user_role();
    if ($role === 'alumni') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/' . $prof['profile_pic'])) {
            $sidebar_avatar = $prof['profile_pic'];
        }
    } else if ($role === 'student') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/' . $prof['profile_pic'])) {
            $sidebar_avatar = $prof['profile_pic'];
        }
    }
} elseif (is_admin()) {
    $sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png';
}

$is_subfolder = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo logo-text">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <button class="sidebar-toggle-btn" id="sidebar-toggle">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        </div>

        <?php if (is_logged_in()): ?>
            <div style="display: flex; flex-direction: column; align-items: center; text-align: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;" class="sidebar-profile-box">
                <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--theme-accent-purple);" class="user-sidebar-avatar">
                <div style="margin-top: 0.75rem;" class="link-text">
                    <h4 style="font-size: 0.9rem;"><?php echo htmlspecialchars(get_user_name()); ?></h4>
                    <p style="font-size: 0.72rem; color: var(--theme-text-secondary); text-transform: uppercase;">
                        <?php echo is_admin() ? 'Admin Portal' : htmlspecialchars(get_user_role()) . ' member'; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <ul class="sidebar-menu">
            <?php if (is_logged_in()): ?>
                <li class="sidebar-item">
                    <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> <span class="link-text">Dashboard</span></a>
                </li>
                <?php if (!is_admin()): ?>
                    <li class="sidebar-item">
                        <a href="profile.php"><i class="fa-solid fa-circle-user"></i> <span class="link-text">My Profile</span></a>
                    </li>
                    <li class="sidebar-item">
                        <a href="mentorship.php"><i class="fa-solid fa-handshake-angle"></i> <span class="link-text">Mentorship</span></a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
            <li class="sidebar-item">
                <a href="alumni.php"><i class="fa-solid fa-users"></i> <span class="link-text">Alumni Directory</span></a>
            </li>
            <li class="sidebar-item active">
                <a href="jobs.php"><i class="fa-solid fa-briefcase"></i> <span class="link-text">Job Board</span></a>
            </li>
            <li class="sidebar-item">
                <a href="events.php"><i class="fa-solid fa-calendar-days"></i> <span class="link-text">Events Board</span></a>
            </li>
            <?php if (is_logged_in()): ?>
                <li class="sidebar-item" style="margin-top: auto; border-top: 1px solid var(--theme-border); padding-top: 1rem;">
                    <a href="logout.php" style="color: var(--accent-danger);"><i class="fa-solid fa-right-from-bracket"></i> <span class="link-text">Sign Out</span></a>
                </li>
            <?php endif; ?>
        </ul>
    </aside>

    <div class="dashboard-content-area">
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h2>Job Opportunities Board</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open visual settings">
                    <i class="fa-solid fa-palette"></i>
                </button>
                <?php if (is_admin() || (is_logged_in() && get_user_role() === 'alumni' && $user_status === 'approved')): ?>
                    <button class="btn btn-primary" onclick="openModal('postJobModal')"><i class="fa-solid fa-plus"></i> Share Job Referral</button>
                <?php endif; ?>
            </div>
        </nav>

        <main class="dashboard-workspace">
            
            <!-- Filters -->
            <section class="card-glass" style="margin-bottom: 3rem;">
                <form action="jobs.php" method="GET">
                    <div class="filter-grid" style="display: grid; grid-template-columns: 2.5fr 1.5fr auto; gap: 1rem; align-items: flex-end;">
                        <div class="form-group">
                            <label for="search" class="form-label" style="font-size: 0.82rem; font-weight:600; color: var(--theme-text-secondary); margin-bottom: 0.4rem; display:block;">Search Position</label>
                            <div style="position: relative;">
                                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                                <input type="text" name="search" id="search" class="input-glass" style="padding-left: 2.6rem;" placeholder="Search title, company, keywords..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="type" class="form-label" style="font-size: 0.82rem; font-weight:600; color: var(--theme-text-secondary); margin-bottom: 0.4rem; display:block;">Job Type</label>
                            <select name="type" id="type" class="input-glass">
                                <option value="">All Types</option>
                                <option value="full-time" <?php echo $type_filter == 'full-time' ? 'selected' : ''; ?>>Full-Time</option>
                                <option value="part-time" <?php echo $type_filter == 'part-time' ? 'selected' : ''; ?>>Part-Time</option>
                                <option value="internship" <?php echo $type_filter == 'internship' ? 'selected' : ''; ?>>Internship</option>
                                <option value="contract" <?php echo $type_filter == 'contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="remote" <?php echo $type_filter == 'remote' ? 'selected' : ''; ?>>Remote</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;"><i class="fa-solid fa-filter"></i> Filter</button>
                            <a href="jobs.php" class="btn btn-secondary" style="padding: 0.75rem 1.25rem;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                        </div>
                    </div>
                </form>
            </section>

            <!-- Grid Lists -->
            <section>
                <?php if (!empty($active_jobs)): ?>
                    <div class="cards-catalog">
                        <?php foreach ($active_jobs as $job): ?>
                            <div class="card-glass" style="display: flex; flex-direction: column;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                    <div>
                                        <h3 style="font-size: 1.2rem; font-weight:700; margin-bottom:0.15rem;"><?php echo htmlspecialchars($job['title']); ?></h3>
                                        <div style="color: var(--theme-accent-purple); font-weight: 600; font-size: 0.95rem;"><?php echo htmlspecialchars($job['company']); ?></div>
                                    </div>
                                    <span class="badge badge-student" style="text-transform: uppercase;"><?php echo htmlspecialchars($job['type']); ?></span>
                                </div>
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem;">
                                    <span style="font-size: 0.8rem; color: var(--theme-text-secondary); background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); padding: 0.2rem 0.6rem; border-radius: 4px;"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                    <?php if ($job['salary_range']): ?>
                                        <span style="font-size: 0.8rem; color: var(--theme-text-secondary); background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); padding: 0.2rem 0.6rem; border-radius: 4px;"><i class="fa-solid fa-wallet"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <p style="font-size: 0.88rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem;">
                                    <?php echo htmlspecialchars($job['description']); ?>
                                </p>

                                <div style="margin-bottom: 1.5rem; font-size: 0.82rem; color: var(--theme-text-secondary);">
                                    <strong>Requirements:</strong>
                                    <p style="margin-top: 0.25rem; font-style: italic; white-space: pre-line;"><?php echo htmlspecialchars($job['requirements']); ?></p>
                                    <?php if (isset($eligibility) && !$eligibility['eligible']): ?>
                                        <div style="font-size:0.75rem; color:#f87171; background:rgba(239, 68, 68, 0.08); padding:0.55rem; border-radius:4px; border:1px solid rgba(239,68,68,0.2); margin-top:0.6rem;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> <strong>Eligibility Check Failed:</strong> <?php echo htmlspecialchars($eligibility['reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--theme-border); padding-top: 1rem; margin-top: auto;">
                                    <span style="font-size: 0.78rem; color: var(--theme-text-secondary);"><i class="fa-solid fa-calendar-day"></i> Shared: <?php echo date('M d, Y', strtotime($job['created_at'])); ?></span>
                                    <?php 
                                    $eligibility = ['eligible' => true, 'reason' => ''];
                                    if (is_logged_in() && !is_admin()) {
                                        $eligibility = check_user_eligibility(get_user_id(), $job['id'], 'job');
                                    }
                                    ?>
                                    <?php if (!$eligibility['eligible']): ?>
                                        <div style="font-size:0.75rem; color:#f87171; background:rgba(239, 68, 68, 0.08); padding:0.5rem; border-radius:4px; border:1px solid rgba(239,68,68,0.15);" title="<?php echo htmlspecialchars($eligibility['reason']); ?>">
                                            <i class="fa-solid fa-circle-exclamation"></i> Ineligible
                                        </div>
                                    <?php elseif ($eligibility['eligible']): ?>
                                        <a href="<?php echo htmlspecialchars($job['application_link']); ?>" target="_blank" class="btn btn-primary btn-small"><i class="fa-solid fa-paper-plane"></i> Apply Now</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card-glass" style="text-align: center; padding: 5rem 2rem;">
                        <i class="fa-solid fa-briefcase" style="font-size: 3.5rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem;"></i>
                        <h2>No Active Referrals Found</h2>
                        <p style="color: var(--theme-text-secondary); margin-top: 0.5rem;">Try modifying filter options.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<!-- POST REFERRAL JOB MODAL -->
<div class="modal" id="postJobModal">
    <div class="modal-content" style="max-width: 650px;">
        <button class="modal-close" onclick="closeModal('postJobModal')">&times;</button>
        <h2 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-briefcase" style="color: var(--theme-accent-purple);"></i> Share Job Referral</h2>
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Post internal referral opportunities directly to campus members.</p>
        
        <form action="jobs.php" method="POST">
            <input type="hidden" name="action" value="post_job">
            
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Job Title</label>
                    <input type="text" name="title" class="input-glass" placeholder="e.g. Frontend Engineer" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Company</label>
                    <input type="text" name="company" class="input-glass" placeholder="e.g. Stripe" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Location</label>
                    <input type="text" name="location" class="input-glass" placeholder="e.g. Remote (India)" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Job Category</label>
                    <select name="type" class="input-glass" required>
                        <option value="full-time">Full-Time</option>
                        <option value="part-time">Part-Time</option>
                        <option value="internship">Internship</option>
                        <option value="contract">Contract</option>
                        <option value="remote">Remote</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Salary Range (Optional)</label>
                    <input type="text" name="salary_range" class="input-glass" placeholder="e.g. ₹12L - ₹15L / year">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Application Link / Email</label>
                    <input type="text" name="application_link" class="input-glass" placeholder="https://careers.stripe.com/apply" required>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Job Summary</label>
                    <textarea name="description" class="input-glass" rows="3" placeholder="Briefly detail roles and project scope..." required></textarea>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Skills & Requirements</label>
                    <textarea name="requirements" class="input-glass" rows="2" placeholder="Specify tech stack and years of experience..." required></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('postJobModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Publish Referral</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

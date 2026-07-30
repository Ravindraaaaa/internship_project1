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
    $redir = $_POST['redirect'] ?? 'jobs.php';
    if (!is_admin() && (get_user_role() !== 'alumni' || $user_status !== 'approved')) {
        set_flash('error', 'Only approved alumni members or administrators can post jobs.');
        header('Location: ' . $redir);
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
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    
    $start_date_val = !empty($start_date) ? $start_date : date('Y-m-d H:i:s');
    $end_date_val = !empty($end_date) ? $end_date : null;

    if (empty($title) || empty($company) || empty($location) || empty($type) || empty($description) || empty($requirements) || empty($app_link)) {
        set_flash('error', 'All details except salary range and dates are required.');
    } else {
        try {
            $posted_by = get_user_id();
            $poster_role = is_admin() ? 'admin' : 'user';
            
            $stmtInsert = $pdo->prepare("INSERT INTO jobs (posted_by, poster_role, title, company, location, type, salary_range, description, requirements, application_link, status, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");
            $stmtInsert->execute([$posted_by, $poster_role, $title, $company, $location, $type, $salary, $description, $requirements, $app_link, $start_date_val, $end_date_val]);
            
            // Dispatch automatic notifications
            create_notification($posted_by, "Job Published! 💼", "Your job post '" . $title . "' at " . $company . " is now active.", "success", "medium");
            notify_all_users("New Opportunity: " . $title, "A position (" . $title . " at " . $company . ") has been posted on AlumniNet.", "info", "medium", "student");

            set_flash('success', 'Job posting published successfully!');
        } catch (Exception $e) {
            set_flash('error', 'Failed to publish job: ' . $e->getMessage());
        }
    }
    header('Location: ' . $redir);
    exit;
}

// 2. Filters & Query
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
        $sidebar_avatar = get_avatar_url($prof['profile_pic'] ?? '');
    } else if ($role === 'student') {
        $stmtP = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
        $stmtP->execute([$uid]);
        $prof = $stmtP->fetch();
        $sidebar_avatar = get_avatar_url($prof['profile_pic'] ?? '');
    }
} elseif (is_admin()) {
    $sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png';
}

$is_subfolder = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    
    <!-- Sidebar -->
    <?php render_sidebar('jobs'); ?>

    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>

        <main class="dashboard-workspace">
            
            <!-- Filters & Header Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 700;"><i class="fa-solid fa-briefcase" style="color: var(--theme-accent-purple);"></i> Campus Referral Board</h2>
                    <p style="color: var(--theme-text-secondary); font-size: 0.88rem;">Exclusive career opportunities and internal job referrals posted by alumni.</p>
                </div>
                
                <?php if (is_admin() || (get_user_role() === 'alumni' && $user_status === 'approved')): ?>
                    <button class="btn btn-primary" onclick="openModal('postJobModal')"><i class="fa-solid fa-plus"></i> Share Referral</button>
                <?php endif; ?>
            </div>

            <!-- SEARCH & FILTER BAR -->
            <div class="card-glass" style="margin-bottom: 2rem; padding: 1.25rem;">
                <form action="jobs.php" method="GET" style="display: grid; grid-template-columns: 2.5fr 1.5fr 120px; gap: 1rem; align-items: center;">
                    <input type="text" name="search" class="input-glass" placeholder="Search title, company, skills, or location..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="type" class="input-glass">
                        <option value="">All Job Types</option>
                        <option value="full-time" <?php echo $type_filter === 'full-time' ? 'selected' : ''; ?>>Full-Time</option>
                        <option value="part-time" <?php echo $type_filter === 'part-time' ? 'selected' : ''; ?>>Part-Time</option>
                        <option value="internship" <?php echo $type_filter === 'internship' ? 'selected' : ''; ?>>Internship</option>
                        <option value="contract" <?php echo $type_filter === 'contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="remote" <?php echo $type_filter === 'remote' ? 'selected' : ''; ?>>Remote</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding: 0.8rem;"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                </form>
            </div>

            <!-- JOBS CATALOG GRID -->
            <section>
                <?php if (!empty($active_jobs)): ?>
                    <div class="cards-catalog">
                        <?php foreach ($active_jobs as $job): 
                            $eligibility = is_logged_in() ? check_user_eligibility(get_user_id(), $job['id'], 'job') : ['eligible' => true];
                            
                            // Calculate job status based on start & end dates
                            $now = time();
                            $job_start = !empty($job['start_date']) ? strtotime($job['start_date']) : strtotime($job['created_at']);
                            $job_end = !empty($job['end_date']) ? strtotime($job['end_date']) : null;
                            
                            $job_is_expired = ($job_end && $now > $job_end);
                            $job_is_upcoming = ($now < $job_start);
                        ?>
                            <div class="card-glass" style="display: flex; flex-direction: column; padding: 1.75rem;">
                                <!-- Top header row -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; gap: 0.75rem; flex-wrap: wrap;">
                                    <div>
                                        <h3 style="font-size: 1.25rem; font-weight:700; margin-bottom:0.2rem; color: var(--theme-text);"><?php echo htmlspecialchars($job['title']); ?></h3>
                                        <div style="color: var(--theme-accent-purple); font-weight: 600; font-size: 0.95rem;"><i class="fa-solid fa-building" style="margin-right:0.3rem;"></i> <?php echo htmlspecialchars($job['company']); ?></div>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                        <?php if ($job_is_expired): ?>
                                            <span class="status-badge status-ended" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); padding: 0.25rem 0.65rem; border-radius: 50px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-solid fa-circle-stop"></i> CLOSED
                                            </span>
                                        <?php elseif ($job_is_upcoming): ?>
                                            <span class="status-badge status-upcoming" style="background: rgba(59, 130, 246, 0.18); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); padding: 0.25rem 0.65rem; border-radius: 50px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <i class="fa-solid fa-calendar-day"></i> OPENS SOON
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-running" style="background: rgba(16, 185, 129, 0.18); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.45); padding: 0.25rem 0.65rem; border-radius: 50px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <span class="pulse-live-dot" style="width: 7px; height: 7px; background: #10b981; border-radius: 50%; display: inline-block; animation: pulseGreen 1.5s infinite;"></span>
                                                ACTIVE HIRING
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="badge badge-student" style="text-transform: uppercase; font-size: 0.75rem; padding: 0.25rem 0.65rem;"><?php echo htmlspecialchars($job['type']); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Meta Tags Row -->
                                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem;">
                                    <span style="font-size: 0.82rem; color: var(--theme-text-secondary); background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); padding: 0.25rem 0.65rem; border-radius: 6px;"><i class="fa-solid fa-location-dot" style="color:var(--theme-accent-purple);"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                    <?php if (!empty($job['salary_range'])): ?>
                                        <span style="font-size: 0.82rem; color: var(--theme-text-secondary); background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); padding: 0.25rem 0.65rem; border-radius: 6px;"><i class="fa-solid fa-wallet" style="color:#10b981;"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php endif; ?>
                                </div>
 
                                <p style="font-size: 0.9rem; color: var(--theme-text-secondary); margin-bottom: 1.25rem; line-height: 1.5;">
                                    <?php echo htmlspecialchars($job['description']); ?>
                                </p>
 
                                <div style="margin-bottom: 1.5rem; font-size: 0.82rem; color: var(--theme-text-secondary);">
                                    <strong style="color:var(--theme-text);">Requirements:</strong>
                                    <p style="margin-top: 0.35rem; font-style: italic; white-space: pre-line; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($job['requirements']); ?></p>
                                    <?php if (!$eligibility['eligible']): ?>
                                        <div style="font-size:0.75rem; color:#f87171; background:rgba(239, 68, 68, 0.08); padding:0.55rem; border-radius:6px; border:1px solid rgba(239,68,68,0.2); margin-top:0.6rem;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> <strong>Eligibility Check Failed:</strong> <?php echo htmlspecialchars($eligibility['reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
 
                                <!-- Card Footer Box with Start & End Date Badges + Spacious Apply Button -->
                                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--theme-border); padding-top: 1.25rem; margin-top: auto; gap: 1.25rem; flex-wrap: wrap;">
                                    
                                    <!-- Left Start & End Dates Badges Box -->
                                    <div style="display: flex; flex-direction: column; gap: 0.4rem; min-width: 160px; flex: 1;">
                                        <!-- Start Date / Posted -->
                                        <span class="date-badge date-start-green" style="background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.25); padding: 0.25rem 0.65rem; border-radius: 6px; font-size: 0.76rem; font-weight: 600; width: fit-content; display: inline-flex; align-items: center; gap: 0.35rem;">
                                            <i class="fa-solid fa-play" style="color: #10b981; font-size: 0.7rem;"></i> Start Date: <?php echo date('M d, Y', $job_start); ?>
                                        </span>

                                        <!-- RED End Date / Deadline -->
                                        <?php if (!empty($job['end_date'])): ?>
                                            <span class="date-badge date-end-red" style="background: rgba(239, 68, 68, 0.18); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.45); padding: 0.25rem 0.7rem; border-radius: 6px; font-size: 0.76rem; font-weight: 700; width: fit-content; display: inline-flex; align-items: center; gap: 0.35rem; box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);">
                                                <i class="fa-solid fa-clock" style="color: #ef4444; font-size: 0.72rem;"></i> End Date: <?php echo date('M d, Y - h:i A', strtotime($job['end_date'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="date-badge date-end-red" style="background: rgba(239, 68, 68, 0.18); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.45); padding: 0.25rem 0.7rem; border-radius: 6px; font-size: 0.76rem; font-weight: 700; width: fit-content; display: inline-flex; align-items: center; gap: 0.35rem; box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);">
                                                <i class="fa-solid fa-clock" style="color: #ef4444; font-size: 0.72rem;"></i> End Date: Active Hiring
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Right Apply Action Button -->
                                    <div style="flex-shrink: 0;">
                                        <?php if ($job_is_expired): ?>
                                            <button class="btn btn-secondary btn-small" style="pointer-events:none; opacity:0.6; white-space: nowrap !important; padding: 0.65rem 1.2rem;" disabled><i class="fa-solid fa-circle-xmark"></i> Applications Closed</button>
                                        <?php elseif (!$eligibility['eligible']): ?>
                                            <div style="font-size:0.75rem; color:#f87171; background:rgba(239, 68, 68, 0.08); padding:0.5rem 0.85rem; border-radius:6px; border:1px solid rgba(239,68,68,0.15); white-space: nowrap;" title="<?php echo htmlspecialchars($eligibility['reason']); ?>">
                                                <i class="fa-solid fa-circle-exclamation"></i> Ineligible
                                            </div>
                                        <?php else: ?>
                                            <a href="apply_job.php?id=<?php echo $job['id']; ?>" target="_blank" class="btn btn-primary btn-small" style="white-space: nowrap !important; padding: 0.65rem 1.3rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; font-size: 0.88rem; font-weight: 700; min-width: 130px;"><i class="fa-solid fa-paper-plane"></i> Apply Now</a>
                                        <?php endif; ?>
                                    </div>

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
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Post internal referral opportunities directly to campus members with Start & End dates.</p>
        
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
                
                <!-- Start Date & End Date Fields -->
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;"><i class="fa-solid fa-play" style="color:#10b981;"></i> Start Date & Time</label>
                    <input type="datetime-local" name="start_date" class="input-glass">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.4rem; display:block;"><i class="fa-solid fa-clock" style="color:#ef4444;"></i> Application End Date (Red Highlight)</label>
                    <input type="datetime-local" name="end_date" class="input-glass">
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

<script src="../assets/js/dashboard.js?v=<?php echo time(); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

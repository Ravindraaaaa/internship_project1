<?php
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_page = "alumni";
$uid = get_user_id();

require_login();

// Active Directory Tab ('alumni' vs 'student')
$tab = strtolower(trim($_GET['tab'] ?? ($_GET['role'] ?? 'alumni')));
if ($tab !== 'student') {
    $tab = 'alumni';
}

$page_title = ($tab === 'alumni') ? "Alumni Directory" : "Student Directory";

// Total Counts for Tab Capsules
try {
    $alumni_total_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'alumni'")->fetchColumn();
    $student_total_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
} catch (Exception $e) {
    $alumni_total_count = 0;
    $student_total_count = 0;
}

// Input Filter Parameters
$search = trim($_GET['search'] ?? '');
$year_filter = trim($_GET['year'] ?? '');
$course_filter = trim($_GET['course'] ?? '');
$industry_filter = trim($_GET['industry'] ?? '');

// Dynamic Filter Lists for Dropdowns
try {
    if ($tab === 'alumni') {
        $years_list = $pdo->query("SELECT DISTINCT pass_yr FROM (
            SELECT COALESCE(passing_year, graduation_year) as pass_yr FROM alumni_profiles WHERE passing_year IS NOT NULL OR graduation_year IS NOT NULL
        ) AS years_comb WHERE pass_yr IS NOT NULL AND pass_yr != '' ORDER BY pass_yr DESC")->fetchAll(PDO::FETCH_COLUMN);

        $courses_list = $pdo->query("SELECT DISTINCT COALESCE(branch, course) as c FROM alumni_profiles WHERE branch IS NOT NULL OR course IS NOT NULL ORDER BY c ASC")->fetchAll(PDO::FETCH_COLUMN);
        $industries_list = $pdo->query("SELECT DISTINCT industry FROM alumni_profiles WHERE industry IS NOT NULL AND industry != '' ORDER BY industry ASC")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $years_list = ['1', '2', '3', '4'];
        $courses_list = $pdo->query("SELECT DISTINCT course FROM student_profiles WHERE course IS NOT NULL AND course != '' ORDER BY course ASC")->fetchAll(PDO::FETCH_COLUMN);
        $industries_list = [];
    }
} catch (Exception $e) {
    $years_list = [];
    $courses_list = [];
    $industries_list = [];
}

// Query Construction based strictly on Tab selection
if ($tab === 'alumni') {
    $sql = "SELECT u.id as user_id, u.name, u.role, u.email, u.phone as u_phone,
                   COALESCE(ap.passing_year, ap.graduation_year, 'N/A') as passing_year,
                   COALESCE(ap.course, ap.branch, 'General Stream') as course,
                   COALESCE(ap.bio, 'No biography available.') as bio,
                   ap.profile_pic,
                   ap.company, ap.position as designation, ap.industry, ap.location, ap.linkedin as a_linkedin, ap.mentorship_available,
                   ap.reg_no, COALESCE(ap.is_blue_tick, 0) as is_blue_tick
            FROM users u
            JOIN alumni_profiles ap ON u.id = ap.user_id
            WHERE u.role = 'alumni'";
    
    $params = [];
    if (!empty($search)) {
        $clean_search_id = preg_replace('/[^0-9]/', '', $search);
        if (!empty($clean_search_id)) {
            $sql .= " AND (u.name LIKE ? OR ap.reg_no LIKE ? OR ap.company LIKE ? OR ap.position LIKE ? OR u.id = ?)";
            $sp = "%{$search}%";
            array_push($params, $sp, $sp, $sp, $sp, intval($clean_search_id));
        } else {
            $sql .= " AND (u.name LIKE ? OR ap.reg_no LIKE ? OR ap.company LIKE ? OR ap.position LIKE ?)";
            $sp = "%{$search}%";
            array_push($params, $sp, $sp, $sp, $sp);
        }
    }
    if (!empty($year_filter)) {
        $sql .= " AND (ap.passing_year = ? OR ap.graduation_year = ?)";
        array_push($params, $year_filter, $year_filter);
    }
    if (!empty($course_filter)) {
        $sql .= " AND (ap.course = ? OR ap.branch = ?)";
        array_push($params, $course_filter, $course_filter);
    }
    if (!empty($industry_filter)) {
        $sql .= " AND ap.industry = ?";
        array_push($params, $industry_filter);
    }
} else {
    $sql = "SELECT u.id as user_id, u.name, u.role, u.email, u.phone as u_phone,
                   COALESCE(sp.current_year, '1') as current_year,
                   COALESCE(sp.course, 'General Stream') as course,
                   COALESCE(sp.bio, 'Student profile active.') as bio,
                   sp.profile_pic, sp.cgpa, sp.linkedin as s_linkedin, sp.github
            FROM users u
            JOIN student_profiles sp ON u.id = sp.user_id
            WHERE u.role = 'student'";
    
    $params = [];
    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR sp.course LIKE ?)";
        $sp = "%{$search}%";
        array_push($params, $sp, $sp);
    }
    if (!empty($year_filter)) {
        $sql .= " AND sp.current_year = ?";
        array_push($params, $year_filter);
    }
    if (!empty($course_filter)) {
        $sql .= " AND sp.course = ?";
        array_push($params, $course_filter);
    }
}

$sql .= " ORDER BY u.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouping logic for rendering
$hierarchy = [];
if ($tab === 'alumni') {
    foreach ($members as $m) {
        $yr = (!empty($m['passing_year']) && $m['passing_year'] !== 'N/A') ? $m['passing_year'] : 'Other / Unspecified';
        $br = (!empty($m['course']) && $m['course'] !== 'Unknown') ? $m['course'] : 'General Stream';
        $bt = !empty($m['batch']) ? $m['batch'] : ($yr !== 'Other / Unspecified' ? "Class of " . $yr : "General");
        
        if (!isset($hierarchy[$yr])) $hierarchy[$yr] = [];
        if (!isset($hierarchy[$yr][$br])) $hierarchy[$yr][$br] = [];
        if (!isset($hierarchy[$yr][$br][$bt])) $hierarchy[$yr][$br][$bt] = [];
        
        $hierarchy[$yr][$br][$bt][] = $m;
    }
} else {
    foreach ($members as $m) {
        $yr = "Academic Year " . (!empty($m['current_year']) ? $m['current_year'] : '1');
        $br = (!empty($m['course']) && $m['course'] !== 'Unknown') ? $m['course'] : 'General Stream';
        $bt = "Active Students";
        
        if (!isset($hierarchy[$yr])) $hierarchy[$yr] = [];
        if (!isset($hierarchy[$yr][$br])) $hierarchy[$yr][$br] = [];
        if (!isset($hierarchy[$yr][$br][$bt])) $hierarchy[$yr][$br][$bt] = [];
        
        $hierarchy[$yr][$br][$bt][] = $m;
    }
}

// Active year metrics for Alumni
$active_year = !empty($year_filter) ? $year_filter : null;
$year_alumni_count = 0;
$top_companies_year = [];
if ($tab === 'alumni' && $active_year) {
    try {
        $stmtY = $pdo->prepare("SELECT COUNT(*) FROM alumni_profiles WHERE passing_year = ? OR graduation_year = ?");
        $stmtY->execute([$active_year, $active_year]);
        $year_alumni_count = (int)$stmtY->fetchColumn();

        $stmtC = $pdo->prepare("SELECT company, COUNT(*) as cnt FROM alumni_profiles WHERE (passing_year = ? OR graduation_year = ?) AND company IS NOT NULL AND company != '' GROUP BY company ORDER BY cnt DESC LIMIT 5");
        $stmtC->execute([$active_year, $active_year]);
        $top_companies_year = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .member-dir-header {
        margin-bottom: 2rem;
    }
    .member-dir-header h1 {
        font-size: 2rem;
        font-weight: 800;
        color: #fff;
        margin: 0 0 0.5rem 0;
    }
    .member-dir-header p {
        color: #94a3b8;
        font-size: 0.95rem;
        margin: 0;
    }
    .filter-bar {
        background: var(--theme-card-bg, #0f172a);
        border: 1px solid var(--theme-border, #1e293b);
        border-radius: 12px;
        padding: 1.5rem;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto auto;
        gap: 1rem;
        align-items: end;
        margin-bottom: 2rem;
    }
    .filter-group label {
        display: block;
        font-size: 0.75rem;
        color: var(--theme-text-secondary, #94a3b8);
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .filter-group input, .filter-group select {
        width: 100%;
        background: var(--theme-bg-secondary, #1e293b);
        border: 1px solid var(--theme-border, #334155);
        color: var(--theme-text, #f8fafc);
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        outline: none;
    }
    .filter-group input::placeholder {
        color: var(--theme-text-secondary, #64748b);
    }
    .btn-filter {
        background: linear-gradient(135deg, #6366f1, #a855f7);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-reset {
        background: transparent;
        color: var(--theme-text, #cbd5e1);
        border: 1px solid var(--theme-border, #334155);
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-reset:hover {
        background: var(--theme-bg-secondary, #1e293b);
    }
    .members-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    .member-card {
        background: var(--theme-card-bg, #1e293b);
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--theme-border, rgba(255,255,255,0.05));
    }
    .member-top {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    .member-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        object-fit: cover;
        background: #0f172a;
    }
    .member-info h3 {
        margin: 0 0 0.25rem 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: #f8fafc;
    }
    .role-pill {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .role-alumni {
        background: rgba(99, 102, 241, 0.15);
        color: #818cf8;
        border: 1px solid rgba(99, 102, 241, 0.3);
    }
    .role-student {
        background: rgba(56, 189, 248, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(56, 189, 248, 0.3);
    }
    .member-academic {
        font-size: 0.8rem;
        color: #94a3b8;
    }
    .member-academic div {
        margin-bottom: 0.15rem;
    }
    .member-bio {
        font-size: 0.85rem;
        color: #cbd5e1;
        line-height: 1.5;
        margin-bottom: 1.5rem;
        flex-grow: 1;
    }
    .employment-card {
        background: #0f172a;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .emp-title {
        font-weight: 700;
        font-size: 0.9rem;
        color: #f8fafc;
        margin-bottom: 0.25rem;
    }
    .emp-company {
        font-size: 0.8rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .emp-desc {
        margin-top: 0.75rem;
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.4;
    }
    .btn-connect {
        width: 100%;
        background: linear-gradient(135deg, #6366f1, #a855f7);
        color: white;
        border: none;
        padding: 0.85rem;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: opacity 0.2s;
    }
    .btn-connect:hover {
        opacity: 0.9;
    }
</style>

<div class="dashboard-wrapper">
    <?php render_sidebar($active_page); ?>
    
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        
        <main class="dashboard-workspace" style="padding: 1.5rem;">
            <!-- Header Banner -->
            <div class="alumni-header-banner" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="position: relative; z-index: 1;">
                    <h1 style="font-size: 2.2rem; font-weight: 800; background: linear-gradient(135deg, var(--theme-text), var(--theme-accent-purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0; letter-spacing: -0.5px;">
                        🎓 <?php echo ($tab === 'alumni') ? 'Alumni Directory & Digital Archive' : 'Student Directory & Campus Network'; ?>
                    </h1>
                    <p style="color: var(--theme-text-secondary); font-size: 0.98rem; margin-top: 0.5rem; max-width: 650px; line-height: 1.5;">
                        <?php if ($tab === 'alumni'): ?>
                            Browse verified alumni members by <strong>Passing Year → Branch → Batch</strong>. Discover their professional organization, brand logos, and digital archives.
                        <?php else: ?>
                            Explore active student members by <strong>Academic Year → Course Stream</strong>. Connect with campus peers for mentorship and project collaboration.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- DIRECTORY SELECTION CAPSULES (SEPARATE ALUMNI & STUDENT) -->
            <div style="display: flex; gap: 0.85rem; margin-bottom: 1.75rem; flex-wrap: wrap;">
                <a href="alumni.php?tab=alumni" class="btn" style="padding: 0.75rem 1.6rem; border-radius: 30px; font-weight: 800; font-size: 0.92rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.6rem; <?php echo $tab === 'alumni' ? 'background: linear-gradient(135deg, #6366f1, #a855f7); color: #ffffff; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);' : 'background: var(--theme-card-bg); color: var(--theme-text-secondary); border: 1px solid var(--theme-border);'; ?>">
                    <i class="fa-solid fa-user-graduate"></i> Alumni Directory (<span style="font-size: 0.85rem; opacity: 0.9;"><?php echo number_format($alumni_total_count); ?></span>)
                </a>
                <a href="alumni.php?tab=student" class="btn" style="padding: 0.75rem 1.6rem; border-radius: 30px; font-weight: 800; font-size: 0.92rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.6rem; <?php echo $tab === 'student' ? 'background: linear-gradient(135deg, #3b82f6, #06b6d4); color: #ffffff; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);' : 'background: var(--theme-card-bg); color: var(--theme-text-secondary); border: 1px solid var(--theme-border);'; ?>">
                    <i class="fa-solid fa-graduation-cap"></i> Student Directory (<span style="font-size: 0.85rem; opacity: 0.9;"><?php echo number_format($student_total_count); ?></span>)
                </a>
            </div>

            <!-- YEAR DASHBOARD PILLS & STATS (ALUMNI ONLY) -->
            <?php if ($tab === 'alumni'): ?>
            <div class="card-glass" style="padding: 1.25rem; border-radius: 16px; margin-bottom: 1.5rem;">
                <div style="font-size: 0.85rem; font-weight: 700; color: var(--theme-text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-calendar-days" style="color: #818cf8;"></i> Select Graduation Batch Year
                </div>
                <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; -webkit-overflow-scrolling: touch;">
                    <a href="alumni.php?tab=alumni" class="year-pill <?php echo empty($year_filter) ? 'active' : ''; ?>" style="padding: 0.5rem 1.25rem; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 0.85rem; white-space: nowrap; background: <?php echo empty($year_filter) ? 'linear-gradient(135deg, #6366f1, #a855f7)' : 'var(--theme-bg-secondary)'; ?>; color: <?php echo empty($year_filter) ? '#fff' : 'var(--theme-text)'; ?>; border: 1px solid <?php echo empty($year_filter) ? 'transparent' : 'var(--theme-border)'; ?>;">
                        All Years
                    </a>
                    <?php foreach ($years_list as $yr): ?>
                    <a href="alumni.php?tab=alumni&year=<?php echo urlencode($yr); ?>" class="year-pill <?php echo $year_filter == $yr ? 'active' : ''; ?>" style="padding: 0.5rem 1.25rem; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 0.85rem; white-space: nowrap; background: <?php echo $year_filter == $yr ? 'linear-gradient(135deg, #6366f1, #a855f7)' : 'var(--theme-bg-secondary)'; ?>; color: <?php echo $year_filter == $yr ? '#fff' : 'var(--theme-text)'; ?>; border: 1px solid <?php echo $year_filter == $yr ? 'transparent' : 'var(--theme-border)'; ?>;">
                        Batch <?php echo $yr; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Year Metrics Bar -->
                <?php if ($active_year): ?>
                <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap; background: var(--theme-bg-secondary); padding: 1rem; border-radius: 12px; border: 1px solid var(--theme-border);">
                    <div style="flex: 1; min-width: 140px;">
                        <div style="font-size: 0.75rem; color: var(--theme-text-muted);">Batch <?php echo $active_year; ?> Total Alumni</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #10b981;"><?php echo number_format($year_alumni_count); ?> Members</div>
                    </div>
                    <div style="flex: 2; min-width: 250px;">
                        <div style="font-size: 0.75rem; color: var(--theme-text-muted); margin-bottom: 0.25rem;">Top Recruiters for Batch <?php echo $active_year; ?></div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php if (empty($top_companies_year)): ?>
                            <span style="font-size: 0.8rem; color: var(--theme-text-muted);">No recruiter data recorded</span>
                            <?php else: ?>
                            <?php foreach ($top_companies_year as $tc): ?>
                            <span style="padding: 0.25rem 0.6rem; background: rgba(129, 140, 248, 0.15); color: #818cf8; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: 1px solid rgba(129, 140, 248, 0.2);">
                                <?php echo htmlspecialchars($tc['company']); ?> (<?php echo $tc['cnt']; ?>)
                            </span>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
                
            <!-- Filter Bar Form -->
            <form action="alumni.php" method="GET" class="filter-bar">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                <div class="filter-group">
                    <label>Search <?php echo ($tab === 'alumni') ? 'Alumni Name / Company' : 'Student Name / Stream'; ?></label>
                    <input type="text" name="search" placeholder="<?php echo ($tab === 'alumni') ? 'Search Name, Company, Designation...' : 'Search Student Name, Stream...'; ?>" value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filter-group">
                    <label><?php echo ($tab === 'alumni') ? 'Passout Year' : 'Academic Year'; ?></label>
                    <select name="year">
                        <option value=""><?php echo ($tab === 'alumni') ? 'All Years' : 'All Academic Years'; ?></option>
                        <?php foreach ($years_list as $y): ?>
                            <option value="<?php echo htmlspecialchars($y); ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>><?php echo ($tab === 'alumni') ? 'Batch ' . htmlspecialchars($y) : 'Year ' . htmlspecialchars($y); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Course Stream</label>
                    <select name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses_list as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $course_filter == $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($tab === 'alumni'): ?>
                <div class="filter-group">
                    <label>Industry Sector</label>
                    <select name="industry">
                        <option value="">All Industries</option>
                        <?php foreach ($industries_list as $i): ?>
                            <option value="<?php echo htmlspecialchars($i); ?>" <?php echo $industry_filter == $i ? 'selected' : ''; ?>><?php echo htmlspecialchars($i); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filter</button>
                </div>
                <div>
                    <a href="alumni.php?tab=<?php echo $tab; ?>" class="btn-reset" style="text-decoration:none;"><i class="fa-solid fa-arrow-rotate-right"></i> Reset</a>
                </div>
            </form>

            <!-- MEMBERS CATALOG GRID -->
            <?php if (empty($hierarchy)): ?>
            <div style="text-align:center; padding: 4rem; background: var(--theme-card-bg); border-radius: 16px; border: 1px solid var(--theme-border);">
                <i class="fa-solid fa-users-slash" style="font-size:3rem; color:var(--theme-text-muted); margin-bottom:1rem;"></i>
                <h3 style="color:var(--theme-text); margin-bottom:0.5rem;">No <?php echo ($tab === 'alumni') ? 'Alumni' : 'Student'; ?> members found</h3>
                <p style="color:var(--theme-text-secondary);">Adjust your filters or search keywords to locate members.</p>
            </div>
            <?php else: ?>
            <?php foreach ($hierarchy as $yr => $branches): 
                $yr_badge_text = (is_numeric($yr) && $yr > 1900) ? "Passing Year: " . $yr : (strpos($yr, 'Academic') === 0 ? $yr : "Batch: " . htmlspecialchars($yr));
            ?>
            <div style="margin-bottom: 2.5rem;">
                <!-- Section Header -->
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; padding-bottom: 0.5rem;">
                    <span style="background: <?php echo ($tab === 'alumni') ? 'linear-gradient(135deg, #6366f1, #a855f7)' : 'linear-gradient(135deg, #3b82f6, #06b6d4)'; ?>; color: #fff; padding: 0.6rem 2rem; border-radius: 30px; font-weight: 800; font-size: 1.1rem; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25); letter-spacing: 0.5px;">
                        🎓 <?php echo $yr_badge_text; ?>
                    </span>
                </div>

                <?php foreach ($branches as $br => $batches): ?>
                <div class="hierarchy-header" style="margin-left: 1.5rem; margin-bottom: 2.5rem;">
                    <!-- Branch Sub-Header -->
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--theme-text); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fa-solid fa-graduation-cap" style="color: #818cf8;"></i> <?php echo htmlspecialchars($br); ?>
                    </h3>

                    <?php foreach ($batches as $bt => $members): ?>
                    <div style="margin-bottom: 2rem;">
                        <!-- Batch Sub-Tag -->
                        <div style="font-size: 0.95rem; font-weight: 700; color: var(--theme-text-secondary); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fa-solid fa-layer-group" style="color: #a855f7;"></i> Batch: <?php echo htmlspecialchars($bt); ?>
                            <span style="padding: 0.25rem 0.75rem; background: var(--theme-bg-secondary); color: var(--theme-text); border-radius: 20px; font-size: 0.8rem; box-shadow: inset 0 0 10px rgba(0,0,0,0.1); border: 1px solid var(--theme-border);"><?php echo count($members); ?> Members</span>
                        </div>

                        <!-- Cards Grid -->
                        <div class="cards-catalog" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1.5rem;">
                            <?php foreach ($members as $alum): 
                                $userAvatar = (!empty($alum['profile_pic']) && file_exists(__DIR__ . '/../' . ltrim($alum['profile_pic'], '/'))) ? htmlspecialchars($alum['profile_pic']) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                                $roleTitle = !empty($alum['role']) ? ucfirst($alum['role']) : 'Member';
                                $u_id = !empty($alum['user_id']) ? $alum['user_id'] : (!empty($alum['id']) ? $alum['id'] : 0);
                                
                                if ($tab === 'alumni') {
                                    $designationText = !empty($alum['position']) ? $alum['position'] : (!empty($alum['designation']) ? $alum['designation'] : 'Alumnus');
                                    $companyText = !empty($alum['company']) ? $alum['company'] : 'Independent';
                                    $locationText = !empty($alum['location']) ? $alum['location'] : 'Pune, India';
                                    $hasMentorship = !empty($alum['mentorship_available']) && ($alum['mentorship_available'] == 1 || strtolower((string)$alum['mentorship_available']) === 'yes');
                                    $companyLogo = get_company_logo_url($companyText);
                                } else {
                                    $designationText = "Student (Year " . ($alum['current_year'] ?? '1') . ")";
                                    $companyText = $alum['course'] ?? 'General Stream';
                                    $locationText = !empty($alum['bio']) ? substr($alum['bio'], 0, 30) . '...' : 'Student Member';
                                    $hasMentorship = false;
                                    $companyLogo = null;
                                }
                            ?>
                            <div class="card-glass alumni-member-card" style="padding: 1.5rem; border-radius: 20px; position: relative;">
                                <div style="display: flex; gap: 1.2rem; align-items: flex-start;">
                                    <div class="alumni-avatar-container">
                                        <img src="<?php echo $userAvatar; ?>" alt="<?php echo htmlspecialchars($alum['name']); ?>" class="alumni-avatar" style="width: 68px; height: 68px; border-radius: 50%; object-fit: cover; border: 3px solid <?php echo ($tab === 'alumni') ? '#818cf8' : '#38bdf8'; ?>; flex-shrink: 0; background: rgba(255,255,255,0.05);">
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <?php $alumniIdStr = !empty($alum['reg_no']) ? $alum['reg_no'] : ('ALU-' . (!empty($alum['passing_year']) && $alum['passing_year'] !== 'N/A' ? $alum['passing_year'] : '2024') . '-' . str_pad($u_id, 4, '0', STR_PAD_LEFT)); ?>
                                        <h4 style="font-size: 1.15rem; font-weight: 800; color: var(--theme-text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.2px; display: flex; align-items: center; gap: 0.3rem;">
                                            <span><?php echo htmlspecialchars($alum['name']); ?></span>
                                            <?php if (!empty($alum['is_blue_tick'])): ?>
                                                <i class="fa-solid fa-circle-check" style="color: #38bdf8; font-size: 1.05rem;" title="Top Recruiter / High Package Verified Alumni"></i>
                                            <?php endif; ?>
                                        </h4>
                                        <div style="font-size: 0.72rem; font-weight: 700; color: var(--theme-accent-purple); margin-top: 2px;">
                                            <i class="fa-solid fa-id-badge" style="margin-right: 2px;"></i> ID: <?php echo htmlspecialchars($alumniIdStr); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #818cf8; font-weight: 700; margin-top: 0.3rem; letter-spacing: 0.5px; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($designationText); ?>
                                        </div>

                                        <?php if ($tab === 'alumni'): ?>
                                        <!-- ALUMNI ONLY: COMPANY BRAND LOGO BADGE -->
                                        <div style="font-size: 0.88rem; color: var(--theme-text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.45rem;">
                                            <?php if ($companyLogo): ?>
                                                <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Company Logo" style="width: 22px; height: 22px; border-radius: 5px; object-fit: contain; background: #ffffff; padding: 2px; border: 1px solid var(--theme-border); flex-shrink: 0;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-block';">
                                                <i class="fa-solid fa-building" style="opacity: 0.6; display: none;"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-building" style="opacity: 0.6;"></i>
                                            <?php endif; ?>
                                            <span style="font-weight: 600; color: var(--theme-text);"><?php echo htmlspecialchars($companyText); ?></span>
                                        </div>
                                        <?php else: ?>
                                        <!-- STUDENT COURSE STREAM -->
                                        <div style="font-size: 0.88rem; color: var(--theme-text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.45rem;">
                                            <i class="fa-solid fa-book-open" style="opacity: 0.6; color: #38bdf8;"></i>
                                            <span style="font-weight: 600; color: var(--theme-text);"><?php echo htmlspecialchars($companyText); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--theme-border); display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem;">
                                    <?php if ($tab === 'alumni'): ?>
                                        <span style="color: var(--theme-text-muted);"><i class="fa-solid fa-location-dot" style="color: #38bdf8; margin-right: 4px;"></i> <?php echo htmlspecialchars($locationText); ?></span>
                                        <?php if ($hasMentorship): ?>
                                        <span style="color: #10b981; font-weight: 800; background: rgba(16, 185, 129, 0.15); padding: 0.3rem 0.8rem; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.75rem; letter-spacing: 0.5px; text-transform: uppercase;"><i class="fa-solid fa-handshake" style="margin-right: 4px;"></i> Mentor</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--theme-text-muted);"><i class="fa-solid fa-graduation-cap" style="color: #38bdf8; margin-right: 4px;"></i> Academic Year <?php echo htmlspecialchars($alum['current_year'] ?? '1'); ?></span>
                                        <?php if (!empty($alum['cgpa']) && $alum['cgpa'] > 0): ?>
                                        <span style="color: #38bdf8; font-weight: 800; background: rgba(56, 189, 248, 0.15); padding: 0.3rem 0.8rem; border-radius: 20px; border: 1px solid rgba(56, 189, 248, 0.3); font-size: 0.75rem; letter-spacing: 0.5px;">CGPA: <?php echo htmlspecialchars($alum['cgpa']); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem;">
                                    <?php if ($tab === 'alumni'): ?>
                                    <button type="button" class="btn btn-archive" onclick="openArchiveModal(<?php echo $u_id; ?>)" style="flex: 1; font-size: 0.85rem; font-weight: 700; padding: 0.6rem 0.8rem; border-radius: 12px; justify-content: center; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <i class="fa-solid fa-folder-open"></i> Digital Archive
                                    </button>
                                    <?php if (!empty($alum['a_linkedin'])): ?>
                                    <a href="<?php echo htmlspecialchars($alum['a_linkedin']); ?>" target="_blank" class="btn linkedin-btn" style="font-size: 0.95rem; padding: 0.6rem 0.8rem; border-radius: 12px;">
                                        <i class="fa-brands fa-linkedin"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <a href="portfolio.php" class="btn btn-secondary" style="flex: 1; font-size: 0.85rem; font-weight: 700; padding: 0.6rem 0.8rem; border-radius: 12px; justify-content: center; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                                        <i class="fa-solid fa-user"></i> View Profile
                                    </a>
                                    <?php if (!empty($alum['s_linkedin'])): ?>
                                    <a href="<?php echo htmlspecialchars($alum['s_linkedin']); ?>" target="_blank" class="btn linkedin-btn" style="font-size: 0.95rem; padding: 0.6rem 0.8rem; border-radius: 12px;">
                                        <i class="fa-brands fa-linkedin"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- DIGITAL ARCHIVE MODAL (Read-Only for Students) -->
<div class="modal" id="archiveModal" style="display: none;">
    <div class="modal-content card-glass" style="max-width: 700px; width: 90%; border-radius: 16px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.25rem;"><i class="fa-solid fa-box-archive" style="color: #a855f7;"></i> Member Archive & Verified Records</h3>
            <button type="button" class="btn btn-secondary" onclick="closeArchiveModal()" style="padding: 0.2rem 0.6rem;">&times;</button>
        </div>

        <div id="archiveModalBody">
            <div style="text-align: center; padding: 2rem;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: #818cf8;"></i>
                <p style="margin-top: 1rem; color: var(--theme-text-muted);">Loading digital document archive...</p>
            </div>
        </div>
    </div>
</div>

<script class="script">
function openArchiveModal(userId) {
    // Keep existing modal functionality intact for now
    document.getElementById('archiveModal').style.display = 'flex';
    document.getElementById('archiveModalBody').innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: #818cf8;"></i>
            <p style="margin-top: 1rem; color: var(--theme-text-muted);">Retrieving official document archives...</p>
        </div>
    `;

    fetch('../api/alumni_archive_api.php?action=documents&user_id=' + userId)
    .then(res => res.json())
    .then(data => {
        if (!data.success || !data.profile) {
            document.getElementById('archiveModalBody').innerHTML = '<p style="color: #ef4444; text-align: center;">Failed to retrieve document archive. It may not exist for this member.</p>';
            return;
        }

        const p = data.profile;
        const docs = data.documents || [];

        let docsHtml = '';
        if (docs.length === 0) {
            docsHtml = '<p style="color: var(--theme-text-muted); font-size: 0.85rem;">Original Registration Archive File attached on import.</p>';
        } else {
            docs.forEach(d => {
                docsHtml += `
                    <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.05); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.5rem;">
                        <div>
                            <div style="font-weight: 600; font-size: 0.85rem;">📄 ${d.file_name}</div>
                            <div style="font-size: 0.75rem; color: var(--theme-text-muted);">Uploaded: ${d.uploaded_at}</div>
                        </div>
                        <a href="../${d.file_path}" target="_blank" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.3rem 0.75rem;">View Original</a>
                    </div>
                `;
            });
        }

        document.getElementById('archiveModalBody').innerHTML = `
            <div style="display: flex; gap: 1.5rem; align-items: center; margin-bottom: 1.5rem; background: var(--theme-bg-secondary); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--theme-border);">
                <img src="${p.profile_pic || 'https://ui-avatars.com/api/?name=User'}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #818cf8;">
                <div>
                    <h3 style="margin:0; font-size: 1.2rem; color: var(--theme-text-primary);">${p.name}</h3>
                    <div style="color: #818cf8; font-weight:600; font-size: 0.9rem;">${p.position || 'Professional'} at ${p.company || 'N/A'}</div>
                    <div style="font-size: 0.8rem; color: var(--theme-text-muted); margin-top: 0.25rem;">
                        Reg No: <strong>${p.reg_no || 'REG-' + p.user_id}</strong> | Receipt: <strong>${p.receipt_no || 'REC-VERIFIED'}</strong>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.85rem; margin-bottom: 1.5rem;">
                <div><strong>Branch:</strong> ${p.branch || p.course || 'N/A'}</div>
                <div><strong>Passing Year:</strong> ${p.passing_year || p.graduation_year || 'N/A'}</div>
                <div><strong>Batch:</strong> ${p.batch || '2020-2024'}</div>
                <div><strong>Employment Status:</strong> <span style="color: #10b981; font-weight: bold;">${p.employment_status || 'Working'}</span></div>
                <div><strong>Email:</strong> ${p.email}</div>
                <div><strong>Phone:</strong> ${p.phone || p.u_phone || 'N/A'}</div>
                <div><strong>Current Location:</strong> ${p.location || 'Pune, India'}</div>
                <div><strong>Verification Status:</strong> <span style="padding:0.1rem 0.5rem; background:rgba(16,185,129,0.2); color:#10b981; border-radius:10px; font-weight:bold;">Approved</span></div>
            </div>

            <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem;"><i class="fa-solid fa-file-contract" style="color: #818cf8;"></i> Archived Registration Documents</h4>
            ${docsHtml}

            <div style="margin-top: 1.5rem; border-top: 1px solid var(--theme-border); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.75rem; color: var(--theme-text-muted);">
                    <i class="fa-solid fa-lock" style="color: #10b981;"></i> Data is strictly confidential.
                </div>
                <button type="button" class="btn btn-secondary" onclick="closeArchiveModal()">Close Archive</button>
            </div>
        `;
    });
}

function closeArchiveModal() {
    document.getElementById('archiveModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

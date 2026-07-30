<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

$page_title = "Alumni Directory & Digital Archive";
$active_page = "alumni";
$uid = get_user_id();

require_login();

// Dynamic Filter Lists
try {
    $years_list = $pdo->query("SELECT DISTINCT COALESCE(passing_year, graduation_year) as yr FROM alumni_profiles WHERE passing_year IS NOT NULL OR graduation_year IS NOT NULL ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
    $branches_list = $pdo->query("SELECT DISTINCT COALESCE(branch, course) as br FROM alumni_profiles WHERE branch IS NOT NULL OR course IS NOT NULL ORDER BY br ASC")->fetchAll(PDO::FETCH_COLUMN);
    $companies_list = $pdo->query("SELECT DISTINCT company FROM alumni_profiles WHERE company IS NOT NULL AND company != '' ORDER BY company ASC LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
    $cities_list = $pdo->query("SELECT DISTINCT COALESCE(location, city) as loc FROM alumni_profiles WHERE location IS NOT NULL OR city IS NOT NULL ORDER BY loc ASC LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $years_list = [];
    $branches_list = [];
    $companies_list = [];
    $cities_list = [];
}

// Input Filter Parameters
$search = trim($_GET['search'] ?? '');
$year_filter = trim($_GET['year'] ?? '');
$branch_filter = trim($_GET['branch'] ?? '');
$company_filter = trim($_GET['company'] ?? '');
$city_filter = trim($_GET['city'] ?? '');
$status_filter = trim($_GET['status'] ?? ''); // Working, Higher Studies, Entrepreneur

// Query Construction
$sql = "SELECT u.id as user_id, u.name, u.email, u.phone as u_phone,
               ap.reg_no, ap.branch, ap.course, ap.batch,
               COALESCE(ap.passing_year, ap.graduation_year, '2024') as passing_year,
               ap.ug_year, ap.pg_year, ap.dob, ap.gender,
               ap.company, ap.position as designation, COALESCE(ap.location, ap.city) as location,
               ap.receipt_no, ap.payment_details, ap.employment_status, ap.verification_status,
               ap.skills, ap.achievements, ap.mentorship_available,
               COALESCE(ap.profile_pic, 'https://ui-avatars.com/api/?name=User&background=6366f1&color=fff') as profile_pic,
               COALESCE(ap.signature_pic, 'uploads/signatures/sample_signature.png') as signature_pic,
               ap.linkedin, ap.website, ap.bio
        FROM users u
        LEFT JOIN alumni_profiles ap ON u.id = ap.user_id
        WHERE u.role = 'alumni'";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR ap.reg_no LIKE ? OR ap.company LIKE ? OR ap.position LIKE ? OR ap.skills LIKE ? OR ap.batch LIKE ? OR ap.location LIKE ? OR ap.city LIKE ?)";
    $sp = "%{$search}%";
    array_push($params, $sp, $sp, $sp, $sp, $sp, $sp, $sp, $sp);
}

if (!empty($year_filter)) {
    $sql .= " AND (ap.passing_year = ? OR ap.graduation_year = ?)";
    array_push($params, $year_filter, $year_filter);
}

if (!empty($branch_filter)) {
    $sql .= " AND (ap.branch = ? OR ap.course = ?)";
    array_push($params, $branch_filter, $branch_filter);
}

if (!empty($company_filter)) {
    $sql .= " AND ap.company = ?";
    array_push($params, $company_filter);
}

if (!empty($city_filter)) {
    $sql .= " AND (ap.location = ? OR ap.city = ?)";
    array_push($params, $city_filter, $city_filter);
}

if (!empty($status_filter)) {
    $sql .= " AND ap.employment_status = ?";
    array_push($params, $status_filter);
}

$sql .= " ORDER BY passing_year DESC, u.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alumni_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hierarchical Aggregation (Passing Year -> Branch -> Batch -> Count)
$hierarchy = [];
foreach ($alumni_records as $rec) {
    $yr = !empty($rec['passing_year']) ? $rec['passing_year'] : '2024';
    $br = !empty($rec['branch']) ? $rec['branch'] : (!empty($rec['course']) ? $rec['course'] : 'Computer Engineering');
    $bt = !empty($rec['batch']) ? $rec['batch'] : 'Batch ' . $yr;

    if (!isset($hierarchy[$yr])) $hierarchy[$yr] = [];
    if (!isset($hierarchy[$yr][$br])) $hierarchy[$yr][$br] = [];
    if (!isset($hierarchy[$yr][$br][$bt])) $hierarchy[$yr][$br][$bt] = [];
    
    $hierarchy[$yr][$br][$bt][] = $rec;
}

// Year Dashboard Stats for Selected or Top Year
$active_year = $year_filter ?: (!empty($years_list) ? $years_list[0] : date('Y'));
$year_alumni_count = 0;
$top_companies_year = [];

if ($active_year) {
    $stmtYCount = $pdo->prepare("SELECT COUNT(*) FROM alumni_profiles WHERE passing_year = ? OR graduation_year = ?");
    $stmtYCount->execute([$active_year, $active_year]);
    $year_alumni_count = $stmtYCount->fetchColumn();

    $stmtYComp = $pdo->prepare("SELECT company, COUNT(*) as cnt FROM alumni_profiles WHERE (passing_year = ? OR graduation_year = ?) AND company IS NOT NULL AND company != '' GROUP BY company ORDER BY cnt DESC LIMIT 4");
    $stmtYComp->execute([$active_year, $active_year]);
    $top_companies_year = $stmtYComp->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .alumni-header-banner {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1));
        border-radius: 20px;
        padding: 2.5rem;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.05);
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .alumni-header-banner::before {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(99,102,241,0.1) 0%, transparent 40%);
        pointer-events: none;
        animation: rotateBanner 20s linear infinite;
    }
    @keyframes rotateBanner {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .alumni-member-card {
        background: var(--theme-surface);
        border: 1px solid var(--theme-border);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    .alumni-member-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.3), 0 0 20px rgba(99, 102, 241, 0.15);
        border-color: rgba(99, 102, 241, 0.5);
    }
    .alumni-avatar-container {
        position: relative;
        display: inline-block;
    }
    .alumni-avatar {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .alumni-member-card:hover .alumni-avatar {
        transform: scale(1.1);
        box-shadow: 0 0 20px rgba(99, 102, 241, 0.6);
    }
    .btn-archive {
        background: linear-gradient(135deg, rgba(168, 85, 247, 0.15), rgba(99, 102, 241, 0.15));
        border: 1px solid rgba(168, 85, 247, 0.3);
        color: #a855f7;
        transition: all 0.3s ease;
    }
    .btn-archive:hover {
        background: linear-gradient(135deg, #a855f7, #6366f1);
        color: white;
        box-shadow: 0 5px 15px rgba(168, 85, 247, 0.4);
    }
    .btn-archive:hover i {
        color: white !important;
    }
    .year-pill {
        transition: all 0.3s ease;
    }
    .year-pill:hover:not(.active) {
        background: rgba(255,255,255,0.15) !important;
        transform: translateY(-2px);
    }
    .hierarchy-header {
        position: relative;
        padding-left: 1.5rem;
    }
    .hierarchy-header::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: linear-gradient(to bottom, #6366f1, #a855f7);
        border-radius: 4px;
    }
    .linkedin-btn {
        background: rgba(0, 119, 181, 0.1); 
        border: 1px solid rgba(0, 119, 181, 0.3); 
        color: #0077b5; 
        transition: all 0.3s ease;
    }
    .linkedin-btn:hover {
        background: #0077b5;
        color: white;
        box-shadow: 0 5px 15px rgba(0, 119, 181, 0.4);
    }
</style>

<div class="dashboard-wrapper">
    <?php render_sidebar($active_page); ?>
    
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        
        <main class="dashboard-workspace" style="padding: 1.5rem;">
            <!-- Header Banner -->
            <div class="alumni-header-banner" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
                <div style="position: relative; z-index: 1;">
                    <h1 style="font-size: 2.4rem; font-weight: 800; background: linear-gradient(135deg, #fff, #e0e7ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0; letter-spacing: -0.5px; text-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        🎓 Alumni Directory & Digital Archive
                    </h1>
                    <p style="color: rgba(255,255,255,0.75); font-size: 1.05rem; margin-top: 0.75rem; max-width: 600px; line-height: 1.6;">
                        Browse and connect with verified alumni members organized by <strong>Passing Year → Branch → Batch</strong>. Discover their professional journeys and explore their digital archives.
                    </p>
                </div>
            </div>

            <!-- YEAR DASHBOARD PILLS & STATS -->
            <div class="card-glass" style="padding: 1.25rem; border-radius: 16px; margin-bottom: 1.5rem;">
                <div style="font-size: 0.85rem; font-weight: 700; color: var(--theme-text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-calendar-days" style="color: #818cf8;"></i> Select Graduation Batch Year
                </div>
                <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; -webkit-overflow-scrolling: touch;">
                    <a href="alumni.php" class="year-pill <?php echo empty($year_filter) ? 'active' : ''; ?>" style="padding: 0.5rem 1.25rem; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 0.85rem; white-space: nowrap; background: <?php echo empty($year_filter) ? 'linear-gradient(135deg, #6366f1, #a855f7)' : 'rgba(255,255,255,0.06)'; ?>; color: #fff;">
                        All Years
                    </a>
                    <?php foreach ($years_list as $yr): ?>
                    <a href="alumni.php?year=<?php echo urlencode($yr); ?>" class="year-pill <?php echo $year_filter == $yr ? 'active' : ''; ?>" style="padding: 0.5rem 1.25rem; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 0.85rem; white-space: nowrap; background: <?php echo $year_filter == $yr ? 'linear-gradient(135deg, #6366f1, #a855f7)' : 'rgba(255,255,255,0.06)'; ?>; color: #fff;">
                        Batch <?php echo $yr; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Year Metrics Bar -->
                <?php if ($active_year): ?>
                <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px;">
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

            <!-- SEARCH & FILTERS PANEL -->
            <div class="card-glass" style="padding: 1.25rem; border-radius: 16px; margin-bottom: 2rem;">
                <form method="GET" action="alumni.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
                    <div>
                        <label style="font-size: 0.8rem; color: var(--theme-text-muted); font-weight: 600; display: block; margin-bottom: 0.35rem;">Search Query</label>
                        <input type="text" name="search" class="form-control input-glass" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Reg No, Company, Skills, Designation...">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--theme-text-muted); font-weight: 600; display: block; margin-bottom: 0.35rem;">Branch / Department</label>
                        <select name="branch" class="form-control input-glass">
                            <option value="">All Branches</option>
                            <?php foreach ($branches_list as $br): ?>
                            <option value="<?php echo htmlspecialchars($br); ?>" <?php echo $branch_filter === $br ? 'selected' : ''; ?>><?php echo htmlspecialchars($br); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--theme-text-muted); font-weight: 600; display: block; margin-bottom: 0.35rem;">Company</label>
                        <select name="company" class="form-control input-glass">
                            <option value="">All Companies</option>
                            <?php foreach ($companies_list as $comp): ?>
                            <option value="<?php echo htmlspecialchars($comp); ?>" <?php echo $company_filter === $comp ? 'selected' : ''; ?>><?php echo htmlspecialchars($comp); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--theme-text-muted); font-weight: 600; display: block; margin-bottom: 0.35rem;">Status</label>
                        <select name="status" class="form-control input-glass">
                            <option value="">All Statuses</option>
                            <option value="Working" <?php echo $status_filter === 'Working' ? 'selected' : ''; ?>>Working</option>
                            <option value="Higher Studies" <?php echo $status_filter === 'Higher Studies' ? 'selected' : ''; ?>>Higher Studies</option>
                            <option value="Entrepreneur" <?php echo $status_filter === 'Entrepreneur' ? 'selected' : ''; ?>>Entrepreneur</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 10px; padding: 0.65rem;"><i class="fa-solid fa-magnifying-glass"></i> Filter Results</button>
                    </div>
                </form>
            </div>

            <!-- HIERARCHICAL ALUMNI DIRECTORY (Passing Year -> Branch -> Batch -> Cards) -->
            <?php if (empty($hierarchy)): ?>
            <div class="card-glass" style="padding: 3rem; text-align: center; border-radius: 16px;">
                <i class="fa-solid fa-users-slash" style="font-size: 3rem; color: var(--theme-text-muted); margin-bottom: 1rem;"></i>
                <h3>No Alumni Found</h3>
                <p style="color: var(--theme-text-muted);">Try adjusting your search criteria or selecting a different graduation year batch.</p>
                <a href="alumni.php" class="btn btn-secondary" style="margin-top: 1rem;">Reset Filters</a>
            </div>
            <?php else: ?>
            <?php foreach ($hierarchy as $yr => $branches): ?>
            <div style="margin-bottom: 2.5rem;">
                <!-- Passing Year Header -->
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; padding-bottom: 0.5rem;">
                    <span style="background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; padding: 0.6rem 2rem; border-radius: 30px; font-weight: 800; font-size: 1.1rem; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25); letter-spacing: 0.5px;">
                        🎓 Passing Year: <?php echo $yr; ?>
                    </span>
                </div>

                <?php foreach ($branches as $br => $batches): ?>
                <div class="hierarchy-header" style="margin-left: 1.5rem; margin-bottom: 2.5rem;">
                    <!-- Branch Sub-Header -->
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: #e0e7ff; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fa-solid fa-graduation-cap" style="color: #818cf8;"></i> <?php echo htmlspecialchars($br); ?>
                    </h3>

                    <?php foreach ($batches as $bt => $members): ?>
                    <div style="margin-bottom: 2rem;">
                        <!-- Batch Sub-Tag -->
                        <div style="font-size: 0.95rem; font-weight: 700; color: rgba(255,255,255,0.7); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fa-solid fa-layer-group" style="color: #a855f7;"></i> Batch: <?php echo htmlspecialchars($bt); ?>
                            <span style="padding: 0.25rem 0.75rem; background: rgba(255,255,255,0.1); border-radius: 20px; font-size: 0.8rem; box-shadow: inset 0 0 10px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.05);"><?php echo count($members); ?> Alumni</span>
                        </div>

                        <!-- Alumni Cards Grid -->
                        <div class="cards-catalog" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1.5rem;">
                            <?php foreach ($members as $alum): ?>
                            <div class="card-glass alumni-member-card" style="padding: 1.5rem; border-radius: 20px; position: relative;">
                                <div style="display: flex; gap: 1.2rem; align-items: flex-start;">
                                    <div class="alumni-avatar-container">
                                        <img src="<?php echo htmlspecialchars($alum['profile_pic']); ?>" alt="Profile" class="alumni-avatar" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid #818cf8; flex-shrink: 0; background: #fff;">
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <h4 style="font-size: 1.15rem; font-weight: 800; color: #fff; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.2px;">
                                            <?php echo htmlspecialchars($alum['name']); ?>
                                        </h4>
                                        <div style="font-size: 0.85rem; color: #818cf8; font-weight: 700; margin-top: 0.3rem; letter-spacing: 0.5px; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($alum['designation'] ?: 'Alumnus'); ?>
                                        </div>
                                        <div style="font-size: 0.9rem; color: rgba(255,255,255,0.7); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.25rem;">
                                            <i class="fa-solid fa-building" style="opacity: 0.6; margin-right: 4px;"></i> <?php echo htmlspecialchars($alum['company'] ?: 'Independent'); ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem;">
                                    <span style="color: rgba(255,255,255,0.6);"><i class="fa-solid fa-location-dot" style="color: #38bdf8; margin-right: 4px;"></i> <?php echo htmlspecialchars($alum['location'] ?: 'Pune, India'); ?></span>
                                    <?php if ($alum['mentorship_available']): ?>
                                    <span style="color: #10b981; font-weight: 800; background: rgba(16, 185, 129, 0.15); padding: 0.3rem 0.8rem; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.75rem; letter-spacing: 0.5px; text-transform: uppercase;"><i class="fa-solid fa-handshake" style="margin-right: 4px;"></i> Mentor</span>
                                    <?php endif; ?>
                                </div>

                                <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem;">
                                    <button type="button" class="btn btn-archive" onclick="openArchiveModal(<?php echo $alum['user_id']; ?>)" style="flex: 1; font-size: 0.85rem; font-weight: 700; padding: 0.6rem 0.8rem; border-radius: 12px; justify-content: center; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <i class="fa-solid fa-folder-open"></i> Digital Archive
                                    </button>
                                    <?php if (!empty($alum['linkedin'])): ?>
                                    <a href="<?php echo htmlspecialchars($alum['linkedin']); ?>" target="_blank" class="btn linkedin-btn" style="font-size: 0.95rem; padding: 0.6rem 0.8rem; border-radius: 12px;">
                                        <i class="fa-brands fa-linkedin"></i>
                                    </a>
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
            <h3 style="margin: 0; font-size: 1.25rem;"><i class="fa-solid fa-box-archive" style="color: #a855f7;"></i> Alumni Digital Archive & Verified Records</h3>
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
            document.getElementById('archiveModalBody').innerHTML = '<p style="color: #ef4444; text-align: center;">Failed to retrieve document archive.</p>';
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
            <div style="display: flex; gap: 1.5rem; align-items: center; margin-bottom: 1.5rem; background: rgba(0,0,0,0.2); padding: 1.25rem; border-radius: 12px;">
                <img src="${p.profile_pic || 'https://ui-avatars.com/api/?name=User'}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #818cf8;">
                <div>
                    <h3 style="margin:0; font-size: 1.2rem; color: var(--theme-text-primary);">${p.name}</h3>
                    <div style="color: #818cf8; font-weight:600; font-size: 0.9rem;">${p.position || 'Alumnus'} at ${p.company || 'N/A'}</div>
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

            <div style="margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 0.75rem; color: var(--theme-text-muted);">
                    <i class="fa-solid fa-lock" style="color: #10b981;"></i> Students have read-only access to archive records.
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

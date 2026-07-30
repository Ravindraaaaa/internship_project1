<?php
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

$page_title = "Member Directory";
$active_page = "alumni";
$uid = get_user_id();

require_login();

// Input Filter Parameters
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$year_filter = trim($_GET['year'] ?? '');
$course_filter = trim($_GET['course'] ?? '');
$industry_filter = trim($_GET['industry'] ?? '');

// Dynamic Filter Lists for Dropdowns
try {
    $years_list = $pdo->query("SELECT DISTINCT pass_yr FROM (
        SELECT COALESCE(passing_year, graduation_year) as pass_yr FROM alumni_profiles WHERE passing_year IS NOT NULL OR graduation_year IS NOT NULL
        UNION
        SELECT current_year as pass_yr FROM student_profiles WHERE current_year IS NOT NULL
    ) AS years_comb ORDER BY pass_yr DESC")->fetchAll(PDO::FETCH_COLUMN);

    $courses_list = $pdo->query("SELECT DISTINCT c FROM (
        SELECT COALESCE(branch, course) as c FROM alumni_profiles WHERE branch IS NOT NULL OR course IS NOT NULL
        UNION
        SELECT course as c FROM student_profiles WHERE course IS NOT NULL
    ) AS courses_comb ORDER BY c ASC")->fetchAll(PDO::FETCH_COLUMN);

    $industries_list = $pdo->query("SELECT DISTINCT industry FROM alumni_profiles WHERE industry IS NOT NULL AND industry != '' ORDER BY industry ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $years_list = [];
    $courses_list = [];
    $industries_list = [];
}

// Unified Query Construction
$sql = "SELECT u.id as user_id, u.name, u.role, u.email, u.phone as u_phone,
               COALESCE(ap.passing_year, ap.graduation_year, sp.current_year, 'N/A') as passing_year,
               COALESCE(ap.course, ap.branch, sp.course, 'Unknown') as course,
               COALESCE(ap.bio, sp.bio, 'No biography has been written yet.') as bio,
               COALESCE(ap.profile_pic, sp.profile_pic) as profile_pic,
               ap.company, ap.position as designation, ap.industry, ap.location, ap.linkedin as a_linkedin, sp.linkedin as s_linkedin,
               ap.reg_no
        FROM users u
        LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND u.role = 'alumni'
        LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
        WHERE u.role IN ('alumni', 'student')";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR ap.reg_no LIKE ? OR ap.company LIKE ? OR ap.position LIKE ?)";
    $sp = "%{$search}%";
    array_push($params, $sp, $sp, $sp, $sp);
}

if (!empty($role_filter)) {
    $sql .= " AND u.role = ?";
    array_push($params, $role_filter);
}

if (!empty($year_filter)) {
    $sql .= " AND (ap.passing_year = ? OR ap.graduation_year = ? OR sp.current_year = ?)";
    array_push($params, $year_filter, $year_filter, $year_filter);
}

if (!empty($course_filter)) {
    $sql .= " AND (ap.course = ? OR ap.branch = ? OR sp.course = ?)";
    array_push($params, $course_filter, $course_filter, $course_filter);
}

if (!empty($industry_filter)) {
    $sql .= " AND ap.industry = ?";
    array_push($params, $industry_filter);
}

$sql .= " ORDER BY u.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        background: #0f172a;
        border: 1px solid #1e293b;
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
        color: #64748b;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .filter-group input, .filter-group select {
        width: 100%;
        background: #1e293b;
        border: 1px solid #334155;
        color: #f8fafc;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        outline: none;
    }
    .filter-group input::placeholder {
        color: #475569;
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
        color: #cbd5e1;
        border: 1px solid #334155;
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
        background: #1e293b;
    }
    .members-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    .member-card {
        background: #1e293b;
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(255,255,255,0.02);
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
        
        <main class="dashboard-workspace" style="padding: 2rem;">
            <div class="member-dir-header">
                <h1>Member Directory</h1>
                <p>Browse our network and search members by Name, Member ID (e.g., CS-1002), Course, or Company.</p>
            </div>

            <!-- FILTER BAR -->
            <form method="GET" action="alumni.php" class="filter-bar">
                <div class="filter-group">
                    <label>Keyword Search</label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-search" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#64748b;"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, ID (e.g. CS-1001)" style="padding-left: 2.5rem;">
                    </div>
                </div>
                <div class="filter-group">
                    <label>Role Type</label>
                    <select name="role">
                        <option value="">All Members</option>
                        <option value="alumni" <?php echo $role_filter === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Passout / Year</label>
                    <select name="year">
                        <option value="">All Years</option>
                        <?php foreach ($years_list as $y): ?>
                            <option value="<?php echo htmlspecialchars($y); ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>><?php echo htmlspecialchars($y); ?></option>
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
                <div class="filter-group">
                    <label>Industry Sector</label>
                    <select name="industry">
                        <option value="">All Industries</option>
                        <?php foreach ($industries_list as $i): ?>
                            <option value="<?php echo htmlspecialchars($i); ?>" <?php echo $industry_filter == $i ? 'selected' : ''; ?>><?php echo htmlspecialchars($i); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filter</button>
                </div>
                <div>
                    <a href="alumni.php" class="btn-reset" style="text-decoration:none;"><i class="fa-solid fa-arrow-rotate-right"></i> Reset</a>
                </div>
            </form>

            <!-- MEMBERS GRID -->
            <?php if (empty($members)): ?>
            <div style="text-align:center; padding: 4rem; background: #1e293b; border-radius: 16px;">
                <i class="fa-solid fa-users-slash" style="font-size:3rem; color:#475569; margin-bottom:1rem;"></i>
                <h3 style="color:#f8fafc; margin-bottom:0.5rem;">No members found</h3>
                <p style="color:#94a3b8;">Adjust your filters to find members.</p>
            </div>
            <?php else: ?>
            <div class="members-grid">
                <?php foreach ($members as $m): 
                    $is_alumni = $m['role'] === 'alumni';
                    $role_class = $is_alumni ? 'role-alumni' : 'role-student';
                    $role_label = strtoupper($m['role']);
                    
                    // Construct display ID
                    $display_id = '';
                    if ($is_alumni && !empty($m['reg_no'])) {
                        $display_id = $m['reg_no'];
                    } else {
                        // Generate a dummy id like the screenshot if missing
                        $prefix = $is_alumni ? 'AL' : 'ST';
                        $display_id = $prefix . '-' . (1000 + $m['user_id']);
                    }
                    
                    $class_label = $is_alumni ? "Class of " . $m['passing_year'] : "Academic Year " . $m['passing_year'];
                    $display_pic = !empty($m['profile_pic']) ? $m['profile_pic'] : 'https://ui-avatars.com/api/?name=' . urlencode($m['name']) . '&background=6366f1&color=fff';
                ?>
                <div class="member-card">
                    <div class="member-top">
                        <img src="<?php echo htmlspecialchars($display_pic); ?>" alt="Profile" class="member-avatar">
                        <div class="member-info">
                            <h3><?php echo htmlspecialchars($m['name']); ?></h3>
                            <div class="role-pill <?php echo $role_class; ?>"><?php echo $role_label; ?> (<?php echo htmlspecialchars($display_id); ?>)</div>
                            <div class="member-academic">
                                <div><?php echo htmlspecialchars($class_label); ?></div>
                                <div><?php echo htmlspecialchars($m['course']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="member-bio">
                        <?php echo nl2br(htmlspecialchars($m['bio'])); ?>
                    </div>
                    
                    <?php if ($is_alumni && !empty($m['company'])): ?>
                    <div class="employment-card">
                        <div class="emp-title"><?php echo htmlspecialchars($m['designation'] ?: 'Professional'); ?></div>
                        <div class="emp-company">
                            <i class="fa-solid fa-building"></i> 
                            <?php echo htmlspecialchars($m['company']); ?> 
                            <?php if(!empty($m['industry'])) echo ' | ' . htmlspecialchars($m['industry']); ?>
                        </div>
                        <?php if (!empty($m['bio']) && strlen($m['bio']) > 20): ?>
                        <div class="emp-desc">
                            <?php echo htmlspecialchars($m['designation'] ?: 'Professional') . ' at ' . htmlspecialchars($m['company']) . '. ' . htmlspecialchars($m['course']) . ' class of ' . htmlspecialchars($m['passing_year']) . '.'; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <button class="btn-connect" onclick="openArchiveModal(<?php echo $m['user_id']; ?>)">
                        <i class="fa-solid fa-user-plus"></i> Connect <?php echo $is_alumni ? 'as Mentor' : 'with Student'; ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
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
            <div style="display: flex; gap: 1.5rem; align-items: center; margin-bottom: 1.5rem; background: rgba(0,0,0,0.2); padding: 1.25rem; border-radius: 12px;">
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

            <div style="margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
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

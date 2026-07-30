<?php
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = "Student Directory";
$active_page = "students";
$uid = get_user_id();

require_login();

// Total Student Count
try {
    $student_total_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $alumni_total_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'alumni'")->fetchColumn();
} catch (Exception $e) {
    $student_total_count = 0;
    $alumni_total_count = 0;
}

// Input Filter Parameters
$search = trim($_GET['search'] ?? '');
$year_filter = trim($_GET['year'] ?? '');
$course_filter = trim($_GET['course'] ?? '');

// Dynamic Filter Lists for Dropdowns
try {
    $years_list = ['1', '2', '3', '4'];
    $courses_list = $pdo->query("SELECT DISTINCT course FROM student_profiles WHERE course IS NOT NULL AND course != '' ORDER BY course ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $years_list = ['1', '2', '3', '4'];
    $courses_list = [];
}

// Query Construction for Students Only
$sql = "SELECT u.id as user_id, u.name, u.role, u.email, u.phone as u_phone, u.last_active,
               COALESCE(sp.current_year, '1') as current_year,
               COALESCE(sp.course, 'General Stream') as course,
               COALESCE(sp.bio, 'Active student profile.') as bio,
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

$sql .= " ORDER BY u.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouping by Academic Year -> Branch
$hierarchy = [];
foreach ($students as $m) {
    $yr = "Academic Year " . (!empty($m['current_year']) ? $m['current_year'] : '1');
    $br = (!empty($m['course']) && $m['course'] !== 'Unknown') ? $m['course'] : 'General Stream';
    
    if (!isset($hierarchy[$yr])) $hierarchy[$yr] = [];
    if (!isset($hierarchy[$yr][$br])) $hierarchy[$yr][$br] = [];
    
    $hierarchy[$yr][$br][] = $m;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php render_sidebar($active_page); ?>
    
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        
        <main class="dashboard-workspace" style="padding: 1.5rem;">
            <!-- Header Banner -->
            <div class="alumni-header-banner" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="position: relative; z-index: 1;">
                    <h1 style="font-size: 2.2rem; font-weight: 800; background: linear-gradient(135deg, #3b82f6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0; letter-spacing: -0.5px;">
                        🎓 Student Directory & Campus Network
                    </h1>
                    <p style="color: var(--theme-text-secondary); font-size: 0.98rem; margin-top: 0.5rem; max-width: 650px; line-height: 1.5;">
                        Explore active student members by <strong>Academic Year → Course Stream</strong>. Connect with peers for campus mentorship, project collaboration, and peer networking.
                    </p>
                </div>
            </div>

            <!-- DIRECTORY SELECTION CAPSULES -->
            <div style="display: flex; gap: 0.85rem; margin-bottom: 1.75rem; flex-wrap: wrap;">
                <a href="alumni.php" class="btn" style="padding: 0.75rem 1.6rem; border-radius: 30px; font-weight: 800; font-size: 0.92rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.6rem; background: var(--theme-card-bg); color: var(--theme-text-secondary); border: 1px solid var(--theme-border);">
                    <i class="fa-solid fa-user-graduate"></i> Alumni Directory (<span style="font-size: 0.85rem; opacity: 0.9;"><?php echo number_format($alumni_total_count); ?></span>)
                </a>
                <a href="students.php" class="btn" style="padding: 0.75rem 1.6rem; border-radius: 30px; font-weight: 800; font-size: 0.92rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.6rem; background: linear-gradient(135deg, #3b82f6, #06b6d4); color: #ffffff; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);">
                    <i class="fa-solid fa-graduation-cap"></i> Student Directory (<span style="font-size: 0.85rem; opacity: 0.9;"><?php echo number_format($student_total_count); ?></span>)
                </a>
            </div>

            <!-- YEAR DASHBOARD PILLS -->
            <div class="card-glass" style="padding: 1.25rem; border-radius: 16px; margin-bottom: 1.5rem;">
                <div style="font-size: 0.85rem; font-weight: 700; color: var(--theme-text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-calendar-days" style="color: #38bdf8;"></i> Select Academic Year
                </div>
                <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; -webkit-overflow-scrolling: touch;">
                    <a href="students.php" class="year-pill <?php echo empty($year_filter) ? 'active' : ''; ?>" style="padding: 0.5rem 1.25rem; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 0.85rem; white-space: nowrap; background: <?php echo empty($year_filter) ? 'linear-gradient(135deg, #3b82f6, #06b6d4)' : 'var(--theme-bg-secondary)'; ?>; color: <?php echo empty($year_filter) ? '#fff' : 'var(--theme-text)'; ?>; border: 1px solid <?php echo empty($year_filter) ? 'transparent' : 'var(--theme-border)'; ?>;">
                        All Academic Years
                    </a>
                    <?php foreach ($years_list as $yr): ?>
                    <a href="students.php?year=<?php echo urlencode($yr); ?>" class="year-pill <?php echo $year_filter == $yr ? 'active' : ''; ?>" style="padding: 0.5rem 1.25rem; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 0.85rem; white-space: nowrap; background: <?php echo $year_filter == $yr ? 'linear-gradient(135deg, #3b82f6, #06b6d4)' : 'var(--theme-bg-secondary)'; ?>; color: <?php echo $year_filter == $yr ? '#fff' : 'var(--theme-text)'; ?>; border: 1px solid <?php echo $year_filter == $yr ? 'transparent' : 'var(--theme-border)'; ?>;">
                        Year <?php echo $yr; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Filter Bar Form -->
            <form action="students.php" method="GET" class="filter-bar" style="background: var(--theme-card-bg); border: 1px solid var(--theme-border); border-radius: 12px; padding: 1.25rem; display: grid; grid-template-columns: 2fr 1fr 1fr auto auto; gap: 1rem; align-items: end; margin-bottom: 2rem;">
                <div class="filter-group">
                    <label style="display:block; font-size:0.75rem; color:var(--theme-text-secondary); margin-bottom:0.4rem;">Search Student Name / Stream</label>
                    <input type="text" name="search" placeholder="Search Name, Course Stream..." value="<?php echo htmlspecialchars($search); ?>" style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--theme-border); background:var(--theme-bg-secondary); color:var(--theme-text);">
                </div>

                <div class="filter-group">
                    <label style="display:block; font-size:0.75rem; color:var(--theme-text-secondary); margin-bottom:0.4rem;">Academic Year</label>
                    <select name="year" style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--theme-border); background:var(--theme-bg-secondary); color:var(--theme-text);">
                        <option value="">All Academic Years</option>
                        <?php foreach ($years_list as $y): ?>
                            <option value="<?php echo htmlspecialchars($y); ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>>Year <?php echo htmlspecialchars($y); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label style="display:block; font-size:0.75rem; color:var(--theme-text-secondary); margin-bottom:0.4rem;">Course Stream</label>
                    <select name="course" style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--theme-border); background:var(--theme-bg-secondary); color:var(--theme-text);">
                        <option value="">All Courses</option>
                        <?php foreach ($courses_list as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $course_filter == $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #3b82f6, #06b6d4); color:#fff; border:none; padding:0.65rem 1.25rem; border-radius:8px; font-weight:700; cursor:pointer;"><i class="fa-solid fa-filter"></i> Filter</button>
                </div>
                <div>
                    <a href="students.php" class="btn btn-secondary" style="text-decoration:none; padding:0.65rem 1.25rem; border-radius:8px; display:inline-block;"><i class="fa-solid fa-arrow-rotate-right"></i> Reset</a>
                </div>
            </form>

            <!-- MEMBERS CATALOG GRID -->
            <?php if (empty($hierarchy)): ?>
            <div style="text-align:center; padding: 4rem; background: var(--theme-card-bg); border-radius: 16px; border: 1px solid var(--theme-border);">
                <i class="fa-solid fa-users-slash" style="font-size:3rem; color:var(--theme-text-muted); margin-bottom:1rem;"></i>
                <h3 style="color:var(--theme-text); margin-bottom:0.5rem;">No Student members found</h3>
                <p style="color:var(--theme-text-secondary);">Adjust your filters or search keywords to locate student profiles.</p>
            </div>
            <?php else: ?>
            <?php foreach ($hierarchy as $yr => $branches): ?>
            <div style="margin-bottom: 2.5rem;">
                <!-- Section Header -->
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.75rem; padding-bottom: 0.5rem;">
                    <span style="background: linear-gradient(135deg, #3b82f6, #06b6d4); color: #fff; padding: 0.55rem 1.8rem; border-radius: 30px; font-weight: 800; font-size: 1.05rem; box-shadow: 0 10px 20px rgba(59, 130, 246, 0.25); letter-spacing: 0.5px;">
                        🎓 <?php echo htmlspecialchars($yr); ?>
                    </span>
                </div>

                <?php foreach ($branches as $br => $members): ?>
                <div style="margin-left: 1rem; margin-bottom: 2rem;">
                    <!-- Branch Sub-Header -->
                    <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--theme-text); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fa-solid fa-graduation-cap" style="color: #38bdf8;"></i> <?php echo htmlspecialchars($br); ?>
                        <span style="padding: 0.2rem 0.6rem; background: var(--theme-bg-secondary); color: var(--theme-text); border-radius: 20px; font-size: 0.78rem; border: 1px solid var(--theme-border);"><?php echo count($members); ?> Students</span>
                    </h3>

                    <!-- Student Cards Grid -->
                    <div class="cards-catalog" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($members as $std): 
                            $userAvatar = (!empty($std['profile_pic']) && file_exists(__DIR__ . '/../' . ltrim($std['profile_pic'], '/'))) ? htmlspecialchars($std['profile_pic']) : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                            $u_id = !empty($std['user_id']) ? $std['user_id'] : 0;
                            $isOnline = (!empty($std['last_active']) && (strtotime($std['last_active']) >= (time() - 300)));
                        ?>
                        <div class="card-glass alumni-member-card" style="padding: 1.5rem; border-radius: 20px; position: relative;">
                            <div style="display: flex; gap: 1.2rem; align-items: flex-start;">
                                <div class="alumni-avatar-container" style="position:relative;">
                                    <img src="<?php echo $userAvatar; ?>" alt="<?php echo htmlspecialchars($std['name']); ?>" class="alumni-avatar" style="width: 68px; height: 68px; border-radius: 50%; object-fit: cover; border: 3px solid #38bdf8; flex-shrink: 0; background: rgba(255,255,255,0.05);">
                                    <?php if ($isOnline): ?>
                                        <span style="position:absolute; bottom:2px; right:2px; width:14px; height:14px; background:#10b981; border:2px solid #0f172a; border-radius:50%;" title="Online now"></span>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <h4 style="font-size: 1.15rem; font-weight: 800; color: var(--theme-text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.2px;">
                                        <?php echo htmlspecialchars($std['name']); ?>
                                        <span style="font-size: 0.72rem; font-weight: 600; color: #38bdf8; display: block; margin-top: 2px; text-transform: uppercase;">(STUDENT)</span>
                                    </h4>
                                    <div style="font-size: 0.85rem; color: var(--theme-text-secondary); font-weight: 700; margin-top: 0.3rem; letter-spacing: 0.5px; text-transform: uppercase;">
                                        Academic Year <?php echo htmlspecialchars($std['current_year'] ?? '1'); ?>
                                    </div>

                                    <div style="font-size: 0.88rem; color: var(--theme-text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.45rem;">
                                        <i class="fa-solid fa-book-open" style="opacity: 0.6; color: #38bdf8;"></i>
                                        <span style="font-weight: 600; color: var(--theme-text);"><?php echo htmlspecialchars($std['course']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--theme-border); display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem;">
                                <span style="color: var(--theme-text-muted);">
                                    <?php if ($isOnline): ?>
                                        <span style="color:#10b981; font-weight:700;"><i class="fa-solid fa-circle" style="font-size:0.55rem; margin-right:4px;"></i> Online</span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;"><i class="fa-solid fa-circle" style="font-size:0.55rem; margin-right:4px;"></i> Offline</span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($std['cgpa']) && $std['cgpa'] > 0): ?>
                                <span style="color: #38bdf8; font-weight: 800; background: rgba(56, 189, 248, 0.15); padding: 0.3rem 0.8rem; border-radius: 20px; border: 1px solid rgba(56, 189, 248, 0.3); font-size: 0.75rem; letter-spacing: 0.5px;">CGPA: <?php echo htmlspecialchars($std['cgpa']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem;">
                                <a href="view_profile.php?id=<?php echo $u_id; ?>" onclick="openStudentModal(<?php echo htmlspecialchars(json_encode([
                                    'id' => $u_id,
                                    'name' => $std['name'],
                                    'email' => $std['email'],
                                    'current_year' => $std['current_year'] ?? '1',
                                    'course' => $std['course'],
                                    'cgpa' => $std['cgpa'] ?? '0.00',
                                    'bio' => $std['bio'],
                                    'avatar' => $userAvatar,
                                    'is_online' => $isOnline,
                                    'linkedin' => $std['s_linkedin'] ?? '',
                                    'github' => $std['github'] ?? ''
                                ])); ?>); return false;" class="btn btn-secondary" style="flex: 1; font-size: 0.85rem; font-weight: 700; padding: 0.6rem 0.8rem; border-radius: 12px; justify-content: center; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; background: linear-gradient(135deg, #0284c7, #38bdf8); color:#fff; border:none;">
                                    <i class="fa-solid fa-address-card"></i> View Profile
                                </a>
                                <?php if (!empty($std['s_linkedin'])): ?>
                                <a href="<?php echo htmlspecialchars($std['s_linkedin']); ?>" target="_blank" class="btn linkedin-btn" style="font-size: 0.95rem; padding: 0.6rem 0.8rem; border-radius: 12px;">
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
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- STUDENT QUICK VIEW MODAL -->
<div id="studentQuickViewModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); backdrop-filter:blur(8px); z-index:9999; justify-content:center; align-items:center; padding:1rem;">
    <div class="card-glass" style="max-width:520px; width:100%; padding:2rem; border-radius:24px; position:relative; background:var(--theme-card-bg); border:1px solid var(--theme-border); box-shadow:0 20px 50px rgba(0,0,0,0.5);">
        <button type="button" onclick="closeStudentModal()" style="position:absolute; top:1.25rem; right:1.25rem; background:rgba(255,255,255,0.1); border:none; color:var(--theme-text); width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:1.1rem; display:flex; align-items:center; justify-content:center;">&times;</button>
        
        <div style="text-align:center; margin-bottom:1.5rem;">
            <img id="modalStdAvatar" src="" alt="Avatar" style="width:90px; height:90px; border-radius:50%; object-fit:cover; border:4px solid #38bdf8; margin-bottom:0.75rem;">
            <h3 id="modalStdName" style="font-size:1.4rem; font-weight:800; color:var(--theme-text); margin:0;"></h3>
            <div id="modalStdRole" style="font-size:0.78rem; font-weight:700; color:#38bdf8; text-transform:uppercase; margin-top:2px;">(VERIFIED STUDENT)</div>
            <div id="modalStdStatus" style="margin-top:0.5rem;"></div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.85rem; margin-bottom:1.25rem; background:var(--theme-bg-secondary); padding:1rem; border-radius:14px; border:1px solid var(--theme-border);">
            <div>
                <div style="font-size:0.75rem; color:var(--theme-text-muted);">Academic Year</div>
                <div id="modalStdYear" style="font-weight:700; color:var(--theme-text); font-size:0.95rem;"></div>
            </div>
            <div>
                <div style="font-size:0.75rem; color:var(--theme-text-muted);">Course Stream</div>
                <div id="modalStdCourse" style="font-weight:700; color:var(--theme-text); font-size:0.95rem;"></div>
            </div>
            <div>
                <div style="font-size:0.75rem; color:var(--theme-text-muted);">Cumulative CGPA</div>
                <div id="modalStdCgpa" style="font-weight:700; color:#38bdf8; font-size:0.95rem;"></div>
            </div>
            <div>
                <div style="font-size:0.75rem; color:var(--theme-text-muted);">Email Address</div>
                <div id="modalStdEmail" style="font-weight:600; color:var(--theme-text); font-size:0.82rem; word-break:break-all;"></div>
            </div>
        </div>

        <div style="margin-bottom:1.5rem;">
            <div style="font-size:0.78rem; font-weight:700; color:var(--theme-text-muted); margin-bottom:0.4rem; text-transform:uppercase;">Biography / Summary</div>
            <p id="modalStdBio" style="font-size:0.88rem; color:var(--theme-text-secondary); line-height:1.5; margin:0; background:var(--theme-bg-secondary); padding:0.85rem; border-radius:12px; border:1px solid var(--theme-border);"></p>
        </div>

        <div style="display:flex; gap:0.75rem;">
            <a id="modalStdFullLink" href="" class="btn btn-primary" style="flex:1; justify-content:center; display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem; border-radius:12px; font-weight:700; text-decoration:none; background:linear-gradient(135deg, #3b82f6, #06b6d4); color:#fff;">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full Profile
            </a>
            <button type="button" onclick="closeStudentModal()" class="btn btn-secondary" style="padding:0.75rem 1.25rem; border-radius:12px; font-weight:700;">Close</button>
        </div>
    </div>
</div>

<script>
function openStudentModal(data) {
    document.getElementById('modalStdAvatar').src = data.avatar;
    document.getElementById('modalStdName').innerText = data.name;
    document.getElementById('modalStdYear').innerText = 'Year ' + data.current_year;
    document.getElementById('modalStdCourse').innerText = data.course;
    document.getElementById('modalStdCgpa').innerText = (data.cgpa && data.cgpa > 0) ? data.cgpa + ' / 10.00' : 'N/A';
    document.getElementById('modalStdEmail').innerText = data.email;
    document.getElementById('modalStdBio').innerText = data.bio ? data.bio : 'No student bio recorded.';
    document.getElementById('modalStdFullLink').href = 'view_profile.php?id=' + data.id;

    if (data.is_online) {
        document.getElementById('modalStdStatus').innerHTML = '<span style="color:#10b981; font-weight:700; font-size:0.85rem;"><i class="fa-solid fa-circle" style="font-size:0.55rem; margin-right:4px;"></i> Online Now</span>';
    } else {
        document.getElementById('modalStdStatus').innerHTML = '<span style="color:#94a3b8; font-weight:600; font-size:0.85rem;"><i class="fa-solid fa-circle" style="font-size:0.55rem; margin-right:4px;"></i> Offline</span>';
    }

    const modal = document.getElementById('studentQuickViewModal');
    modal.style.display = 'flex';
}

function closeStudentModal() {
    document.getElementById('studentQuickViewModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

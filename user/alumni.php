<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

$page_title = "Member Directory";

// 1. Fetch filter criteria dynamically
try {
    $filter_years = $pdo->query("
        SELECT DISTINCT graduation_year as yr FROM alumni_profiles 
        UNION 
        SELECT DISTINCT current_year as yr FROM student_profiles 
        ORDER BY yr DESC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $filter_courses = $pdo->query("
        SELECT DISTINCT course FROM alumni_profiles WHERE course IS NOT NULL AND course != '' 
        UNION 
        SELECT DISTINCT course FROM student_profiles WHERE course IS NOT NULL AND course != '' 
        ORDER BY course ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $filter_industries = $pdo->query("SELECT DISTINCT industry FROM alumni_profiles WHERE industry IS NOT NULL AND industry != '' ORDER BY industry ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $filter_years = [];
    $filter_courses = [];
    $filter_industries = [];
}

// 2. Filters input
$search = trim($_GET['search'] ?? '');
$year_filter = trim($_GET['year'] ?? '');
$course_filter = trim($_GET['course'] ?? '');
$industry_filter = trim($_GET['industry'] ?? '');
$role_filter = trim($_GET['role'] ?? 'all'); // 'all', 'alumni', 'student'

// 3. Dynamic query
$uid = get_user_id();

$query = "SELECT u.id as user_id, u.name, u.email, u.role,
                 COALESCE(ap.graduation_year, sp.current_year) as year_val,
                 COALESCE(ap.course, sp.course) as course,
                 ap.company, ap.position, ap.industry, ap.website,
                 sp.github,
                 COALESCE(ap.linkedin, sp.linkedin) as linkedin,
                 COALESCE(ap.bio, sp.bio) as bio,
                 COALESCE(ap.profile_pic, sp.profile_pic) as profile_pic
          FROM users u
          LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND u.role = 'alumni'
          LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
          WHERE u.status = 'approved' AND u.id != ?";

$params = [$uid];

if ($role_filter === 'alumni') {
    $query .= " AND u.role = 'alumni'";
} elseif ($role_filter === 'student') {
    $query .= " AND u.role = 'student'";
}

if (!empty($search)) {
    // Check if the search query matches the ID format: e.g. CS-1002 or 1002
    if (preg_match('/(?:[A-Za-z]+-)?(1\d{3,})/', $search, $matches)) {
        $parsed_id = intval($matches[1]) - 1000;
        $query .= " AND (u.id = ? OR u.name LIKE ? OR ap.company LIKE ? OR ap.position LIKE ? OR ap.bio LIKE ? OR sp.bio LIKE ?)";
        array_push($params, $parsed_id, "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%");
    } else {
        $query .= " AND (u.name LIKE ? OR ap.company LIKE ? OR ap.position LIKE ? OR ap.bio LIKE ? OR sp.bio LIKE ?)";
        $search_param = "%{$search}%";
        array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param);
    }
}

if (!empty($year_filter)) {
    $query .= " AND (ap.graduation_year = ? OR sp.current_year = ?)";
    array_push($params, $year_filter, $year_filter);
}

if (!empty($course_filter)) {
    $query .= " AND (ap.course = ? OR sp.course = ?)";
    array_push($params, $course_filter, $course_filter);
}

if (!empty($industry_filter)) {
    $query .= " AND ap.industry = ?";
    $params[] = $industry_filter;
}

$query .= " ORDER BY u.name ASC";

try {
    $stmtAlumni = $pdo->prepare($query);
    $stmtAlumni->execute($params);
    $alumni_list = $stmtAlumni->fetchAll();
} catch (Exception $e) {
    $alumni_list = [];
}

// 4. Connection status check
$existing_requests = [];
if (is_logged_in()) {
    try {
        $stmtReqs = $pdo->prepare("
            SELECT 
                CASE WHEN student_id = ? THEN alumni_id ELSE student_id END as peer_id, 
                status 
            FROM mentorship_requests 
            WHERE student_id = ? OR alumni_id = ?
        ");
        $stmtReqs->execute([$uid, $uid, $uid]);
        $existing_requests = $stmtReqs->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        $existing_requests = [];
    }
}

$is_subfolder = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <?php render_sidebar('alumni'); ?>

    <!-- Workspace Content -->
    <div class="dashboard-content-area">
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h2>Alumni Directory</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark / Bright Mode">
                    <i class="fa-solid fa-sun" id="theme-toggle-icon"></i>
                </button>
            </div>
        </nav>

        <main class="dashboard-workspace">
            <div class="page-header" style="margin-bottom: 2rem;">
                <h1>Connect with Alumni</h1>
                <p>Filter by graduation year, department, and industry to request mentorship & networking.</p>
            </div>
    <style>
    .alumni-card-avatar {
        width: 55px;
        height: 55px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--theme-border);
    }
    .alumni-card-header {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.25rem;
        align-items: center;
    }
    .alumni-card-info h3 {
        font-size: 1.05rem;
        font-weight: 700;
    }
    .alumni-card-info p {
        font-size: 0.78rem;
        color: var(--theme-text-secondary);
    }
    .alumni-work-box {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--theme-border);
        padding: 0.8rem 1rem;
        border-radius: var(--border-radius-md);
        margin-bottom: 1.25rem;
    }
    .alumni-links-row {
        display: flex;
        gap: 1rem;
        border-top: 1px solid var(--theme-border);
        padding-top: 1rem;
        margin-top: auto;
    }
    .social-btn {
        font-size: 1.1rem;
        color: var(--theme-text-secondary);
        transition: var(--transition-speed);
    }
    .social-btn:hover {
        color: var(--theme-text);
    }
    @media (max-width: 768px) {
        .page-wrapper {
            padding: 7rem 1.5rem 3rem 1.5rem;
        }
    }
</style>

<div class="page-wrapper">
    <header class="page-header gsap-reveal">
        <h1>Member Directory</h1>
        <p>Browse our network and search members by Name, Member ID (e.g., CS-1002), Course, or Company.</p>
    </header>

    <!-- Search / Filter panel -->
    <section class="card-glass gsap-reveal" style="margin-bottom: 3rem;">
        <form action="alumni.php" method="GET">
            <div class="filter-grid" style="display: grid; grid-template-columns: 2fr repeat(4, 1fr) auto; gap: 1rem; align-items: flex-end;">
                
                <div class="form-group">
                    <label for="search" class="form-label" style="font-size: 0.8rem; font-weight:600; color: var(--theme-text-secondary); margin-bottom: 0.4rem; display:block;">Keyword Search</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                        <input type="text" name="search" id="search" class="input-glass" style="padding-left: 2.6rem;" placeholder="Search name, ID (e.g. CS-1002)..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="role" class="form-label" style="font-size: 0.8rem; font-weight:600; color: var(--theme-text-secondary); margin-bottom: 0.4rem; display:block;">Role Type</label>
                    <select name="role" id="role" class="input-glass">
                        <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Members</option>
                        <option value="alumni" <?php echo $role_filter == 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                        <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Students</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="year" class="form-label" style="font-size: 0.8rem; font-weight:600; color: var(--theme-text-secondary); margin-bottom: 0.4rem; display:block;">Passout / Year</label>
                    <select name="year" id="year" class="input-glass">
                        <option value="">All Years</option>
                        <?php foreach ($filter_years as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo $year_filter == $yr ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="course" class="form-label" style="font-size: 0.8rem; font-weight:600; color: var(--theme-text-secondary); margin-bottom: 0.4rem; display:block;">Course Stream</label>
                    <select name="course" id="course" class="input-glass">
                        <option value="">All Courses</option>
                        <?php foreach ($filter_courses as $cr): ?>
                            <option value="<?php echo htmlspecialchars($cr); ?>" <?php echo $course_filter == $cr ? 'selected' : ''; ?>><?php echo htmlspecialchars($cr); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="industry" class="form-label" style="font-size: 0.8rem; font-weight:600; color: var(--theme-text-secondary); margin-bottom: 0.4rem; display:block;">Industry Sector</label>
                    <select name="industry" id="industry" class="input-glass">
                        <option value="">All Industries</option>
                        <?php foreach ($filter_industries as $ind): ?>
                            <option value="<?php echo htmlspecialchars($ind); ?>" <?php echo $industry_filter == $ind ? 'selected' : ''; ?>><?php echo htmlspecialchars($ind); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="alumni.php" class="btn btn-secondary" style="padding: 0.75rem 1.25rem;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>

            </div>
        </form>
    </section>

    <!-- Grid Listing -->
    <section>
        <?php if (!empty($alumni_list)): ?>
            <div class="cards-catalog">
                 <?php foreach ($alumni_list as $alumni): 
                    $avatar = get_avatar_url($alumni['profile_pic'] ?? '');
                    $display_id = get_student_id_string($alumni['user_id'], $alumni['course'] ?? '');
                ?>
                    <div class="card-glass gsap-reveal" style="display: flex; flex-direction: column;">
                        <div class="alumni-card-header">
                            <img src="<?php echo $avatar; ?>" alt="Member Avatar" class="alumni-card-avatar">
                            <div class="alumni-card-info">
                                <h3><a href="view_profile.php?id=<?php echo $alumni['user_id']; ?>" style="color: inherit; text-decoration: none;" class="profile-link"><?php echo htmlspecialchars($alumni['name']); ?></a></h3>
                                <?php if ($alumni['role'] === 'student'): ?>
                                    <span class="badge" style="font-size: 0.65rem; background: var(--theme-accent-purple); color: #fff;">Student (<?php echo htmlspecialchars($display_id); ?>)</span>
                                    <p style="margin-top:0.2rem; font-size: 0.72rem; color:var(--theme-text-secondary);">Academic Year <?php echo htmlspecialchars($alumni['year_val'] ?? '1'); ?></p>
                                <?php else: ?>
                                    <span class="badge badge-alumni" style="font-size: 0.65rem;">Alumni (<?php echo htmlspecialchars($display_id); ?>)</span>
                                    <p style="margin-top:0.2rem; font-size: 0.72rem; color:var(--theme-text-secondary);">Class of <?php echo htmlspecialchars($alumni['year_val'] ?? ''); ?></p>
                                <?php endif; ?>
                                <p style="margin-top:0.1rem; font-weight: 600; font-size: 0.82rem;"><?php echo htmlspecialchars($alumni['course']); ?></p>
                            </div>
                        </div>

                        <?php if ($alumni['role'] === 'alumni' && !empty($alumni['company'])): ?>
                            <div class="alumni-work-box">
                                <div style="font-size: 0.88rem; font-weight: 700;"><?php echo htmlspecialchars($alumni['position']); ?></div>
                                <div style="font-size: 0.78rem; color: var(--theme-text-secondary);"><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($alumni['company']); ?> | <small><?php echo htmlspecialchars($alumni['industry']); ?></small></div>
                            </div>
                        <?php endif; ?>

                        <p style="font-size: 0.85rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem; flex-grow: 1;">
                            <?php echo htmlspecialchars($alumni['bio'] ?? 'No biography has been written yet.'); ?>
                        </p>

                        <!-- Mentorship & Networking Connection -->
                        <?php if (is_logged_in() && $alumni['user_id'] != get_user_id()): ?>
                            <div style="margin-top: auto; margin-bottom: 1rem; border-top: 1px solid var(--theme-border); padding-top: 1rem;">
                                <?php if (isset($existing_requests[$alumni['user_id']])): 
                                    $status = $existing_requests[$alumni['user_id']];
                                ?>
                                    <button class="btn btn-secondary" style="width: 100%; pointer-events: none; opacity: 0.75;" disabled>
                                        <i class="fa-solid <?php echo $status == 'accepted' ? 'fa-circle-check' : ($status == 'pending' ? 'fa-hourglass-start' : 'fa-circle-xmark'); ?>"></i> 
                                        Connection <?php echo ucfirst($status); ?>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary" style="width: 100%;" onclick="openMentorshipSetup(<?php echo $alumni['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($alumni['name'])); ?>', '<?php echo $alumni['role']; ?>')">
                                        <i class="fa-solid <?php echo $alumni['role'] === 'alumni' ? 'fa-handshake-angle' : 'fa-user-plus'; ?>"></i> 
                                        Connect <?php echo $alumni['role'] === 'alumni' ? 'as Mentor' : 'with Student'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="alumni-links-row" style="align-items: center;">
                            <a href="view_profile.php?id=<?php echo $alumni['user_id']; ?>" class="btn btn-secondary btn-small" style="font-size: 0.72rem; padding: 0.4rem 0.8rem; display: flex; align-items: center; gap: 0.35rem;" title="View Full Profile"><i class="fa-solid fa-user"></i> View Profile</a>
                            <?php if (!empty($alumni['linkedin'])): ?>
                                <a href="<?php echo htmlspecialchars($alumni['linkedin']); ?>" target="_blank" class="social-btn" title="LinkedIn"><i class="fa-brands fa-linkedin"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($alumni['website'])): ?>
                                <a href="<?php echo htmlspecialchars($alumni['website']); ?>" target="_blank" class="social-btn" title="Portfolio"><i class="fa-solid fa-globe"></i></a>
                            <?php endif; ?>
                            <?php 
                            $can_chat = false;
                            if (is_admin()) {
                                $can_chat = true;
                            } elseif (isset($existing_requests[$alumni['user_id']]) && $existing_requests[$alumni['user_id']] === 'accepted') {
                                $can_chat = true;
                            }
                            if ($can_chat && $alumni['user_id'] != get_user_id()): 
                            ?>
                                <a href="chat.php?peer_id=<?php echo $alumni['user_id']; ?>" class="social-btn" title="Send Message" style="margin-left:auto; color:var(--theme-accent-blue);"><i class="fa-solid fa-comment-dots"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card-glass" style="text-align: center; padding: 5rem 2rem;">
                <i class="fa-solid fa-users-slash" style="font-size: 3.5rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem;"></i>
                <h2>No Alumni Matches Found</h2>
                <p style="color: var(--theme-text-secondary); margin-top: 0.5rem;">Verify filters or search keywords.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Mentorship Modal -->
<div class="modal" id="mentorshipRequestModal">
    <div class="modal-content" style="max-width: 500px;">
        <button class="modal-close" onclick="closeModal('mentorshipRequestModal')">&times;</button>
        <h2 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-graduation-cap" style="color: var(--theme-accent-purple);" id="modal-icon"></i> <span id="modal-title-text">Request Mentoring</span></h2>
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Requesting connection with: <strong id="mentor-name-title"></strong></p>
        
        <form action="mentorship.php" method="POST">
            <input type="hidden" name="action" value="request">
            <input type="hidden" name="alumni_id" id="modal-alumni-id" value="">
            
            <div class="form-group">
                <label for="message" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Introduction Message</label>
                <textarea name="message" id="message" class="input-glass" rows="4" placeholder="Briefly state your academic goals and how they can help..." required></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('mentorshipRequestModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
        </main>
    </div>
</div>

<script>
    function openMentorshipSetup(id, name, role) {
        document.getElementById('modal-alumni-id').value = id;
        document.getElementById('mentor-name-title').textContent = name;
        if (role === 'student') {
            document.getElementById('modal-title-text').textContent = 'Connect with Student';
            document.getElementById('modal-icon').className = 'fa-solid fa-user-plus';
            document.getElementById('message').placeholder = 'Introduce yourself and state why you would like to connect...';
        } else {
            document.getElementById('modal-title-text').textContent = 'Request Mentoring';
            document.getElementById('modal-icon').className = 'fa-solid fa-graduation-cap';
            document.getElementById('message').placeholder = 'Briefly state your academic goals and how they can help...';
        }
        openModal('mentorshipRequestModal');
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof ScrollTrigger !== 'undefined') {
            gsap.utils.toArray('.gsap-reveal').forEach(el => {
                gsap.from(el, {
                    scrollTrigger: {
                        trigger: el,
                        start: "top 90%",
                        toggleActions: "play none none none"
                    },
                    y: 35,
                    opacity: 0,
                    duration: 0.6,
                    ease: "power2.out"
                });
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

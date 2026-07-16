<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

$uid = get_user_id();

// 1. Process Event Creation (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_event') {
    require_admin();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['event_type'] ?? 'in-person');
    
    // Handle banner upload
    $banner_url = '';
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['banner']['tmp_name'];
        $fileName = $_FILES['banner']['name'];
        $fileSize = $_FILES['banner']['size'];
        $fileType = $_FILES['banner']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadFileDir = '../uploads/events/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            $newFileName = md5(time() . $title) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $banner_url = 'uploads/events/' . $newFileName;
            }
        }
    }

    if (empty($title) || empty($description) || empty($date) || empty($location)) {
        set_flash('error', 'All event creation fields are required.');
    } else {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO events (title, description, event_date, location, event_type, banner_image, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([$title, $description, $date, $location, $type, $banner_url, $uid]);
            set_flash('success', 'Event successfully scheduled!');
        } catch (Exception $e) {
            set_flash('error', 'Failed scheduling event: ' . $e->getMessage());
        }
    }
    header('Location: events.php');
    exit;
}

// 2. Process RSVP actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rsvp') {
    require_login();
    
    $event_id = intval($_POST['event_id'] ?? 0);
    $status = trim($_POST['rsvp_status'] ?? 'going'); // 'going', 'interested', 'not_going'

    if ($event_id > 0 && in_array($status, ['going', 'interested', 'not_going'])) {
        // Enforce eligibility
        $eligibility = check_user_eligibility($uid, $event_id, 'event');
        if (!$eligibility['eligible'] && $status !== 'not_going') {
            set_flash('error', 'RSVP failed: ' . $eligibility['reason']);
            header('Location: events.php');
            exit;
        }

        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM event_rsvps WHERE event_id = ? AND user_id = ?");
            $stmtCheck->execute([$event_id, $uid]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $stmtUpdate = $pdo->prepare("UPDATE event_rsvps SET status = ? WHERE event_id = ? AND user_id = ?");
                $stmtUpdate->execute([$status, $event_id, $uid]);
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?, ?, ?)");
                $stmtInsert->execute([$event_id, $uid, $status]);
            }
            set_flash('success', 'RSVP updated to: ' . strtoupper($status));
        } catch (Exception $e) {
            set_flash('error', 'RSVP failed: ' . $e->getMessage());
        }
    }
    header('Location: events.php');
    exit;
}

// 3. Load events data
$upcoming_events = [];
$past_events = [];
$rsvp_counts = [];
$my_rsvps = [];

try {
    $stmtUp = $pdo->query("SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC");
    $upcoming_events = $stmtUp->fetchAll();

    $stmtPast = $pdo->query("SELECT * FROM events WHERE event_date < NOW() ORDER BY event_date DESC");
    $past_events = $stmtPast->fetchAll();

    // Fetch RSVP totals for calculations
    $stmtCounts = $pdo->query("SELECT event_id, status, COUNT(*) as qty FROM event_rsvps GROUP BY event_id, status");
    $all_counts = $stmtCounts->fetchAll();
    foreach ($all_counts as $row) {
        $rsvp_counts[$row['event_id']][$row['status']] = $row['qty'];
    }

    // Fetch current user RSVPs
    if (is_logged_in()) {
        $stmtMy = $pdo->prepare("SELECT event_id, status FROM event_rsvps WHERE user_id = ?");
        $stmtMy->execute([$uid]);
        $my_rsvps = $stmtMy->fetchAll(PDO::FETCH_KEY_PAIR);
    }
} catch (Exception $e) {
    set_flash('error', 'Failed loading events list: ' . $e->getMessage());
}

$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
if (is_logged_in() && !is_admin()) {
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
            <li class="sidebar-item">
                <a href="jobs.php"><i class="fa-solid fa-briefcase"></i> <span class="link-text">Job Board</span></a>
            </li>
            <li class="sidebar-item active">
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
                <h2>Scheduled Community Events</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open visual settings">
                    <i class="fa-solid fa-palette"></i>
                </button>
                <?php if (is_admin()): ?>
                    <button class="btn btn-primary" onclick="openModal('createEventModal')"><i class="fa-solid fa-calendar-plus"></i> Schedule Event</button>
                <?php endif; ?>
            </div>
        </nav>

        <main class="dashboard-workspace">
            
            <!-- UPCOMING EVENTS Grid -->
            <section style="margin-bottom: 4rem;">
                <h3 style="font-size: 1.3rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-circle-play" style="color: var(--theme-accent-purple);"></i> Upcoming Networking Events</h3>
                
                <?php if (!empty($upcoming_events)): ?>
                    <div class="cards-catalog">
                        <?php foreach ($upcoming_events as $event): 
                            $banner = $event['banner_image'] ? htmlspecialchars($event['banner_image']) : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&fit=crop&q=80';
                            $ev_id = $event['id'];
                            $going_count = $rsvp_counts[$ev_id]['going'] ?? 0;
                            $interested_count = $rsvp_counts[$ev_id]['interested'] ?? 0;
                        ?>
                            <div class="card-glass" style="padding:0; display:flex; flex-direction:column;">
                                <img src="<?php echo $banner; ?>" alt="Event Banner" style="height: 180px; width: 100%; object-fit: cover; border-top-left-radius: inherit; border-top-right-radius: inherit;">
                                <div style="padding: 2rem; display: flex; flex-direction: column; flex-grow:1;">
                                    <span class="badge badge-alumni" style="align-self: flex-start; margin-bottom: 0.75rem;"><?php echo date('M d, Y - h:i A', strtotime($event['event_date'])); ?></span>
                                    <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <div style="font-size: 0.82rem; color: var(--theme-text-secondary); margin-bottom: 0.25rem;"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($event['location']); ?></div>
                                    <div style="font-size: 0.82rem; color: var(--theme-text-secondary); margin-bottom: 1rem;"><i class="fa-solid fa-users"></i> Going: <strong><?php echo $going_count; ?></strong> | Interested: <strong><?php echo $interested_count; ?></strong></div>
                                    
                                    <p style="font-size: 0.88rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem; flex-grow: 1;">
                                        <?php echo htmlspecialchars($event['description']); ?>
                                    </p>

                                    <!-- RSVP Form -->
                                    <?php if (is_logged_in()): 
                                        $my_status = $my_rsvps[$ev_id] ?? '';
                                        $eligibility = check_user_eligibility($uid, $ev_id, 'event');
                                    ?>
                                        <div style="border-top:1px solid var(--theme-border); padding-top: 1.25rem; margin-top: auto;">
                                            <?php if (!$eligibility['eligible']): ?>
                                                <div style="font-size:0.75rem; color:#f87171; background:rgba(239, 68, 68, 0.08); padding:0.55rem; border-radius:4px; border:1px solid rgba(239,68,68,0.2); width:100%;">
                                                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Ineligible to RSVP:</strong> <?php echo htmlspecialchars($eligibility['reason']); ?>
                                                </div>
                                            <?php else: ?>
                                                <form action="events.php" method="POST" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                                    <input type="hidden" name="action" value="rsvp">
                                                    <input type="hidden" name="event_id" value="<?php echo $ev_id; ?>">
                                                    
                                                    <div style="display:flex; gap: 0.35rem;">
                                                        <button type="submit" name="rsvp_status" value="going" class="btn <?php echo $my_status === 'going' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 6px;">Going</button>
                                                        <button type="submit" name="rsvp_status" value="interested" class="btn <?php echo $my_status === 'interested' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 6px;">Interested</button>
                                                        <button type="submit" name="rsvp_status" value="not_going" class="btn <?php echo $my_status === 'not_going' ? 'btn-danger' : 'btn-secondary'; ?>" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 6px;">Decline</button>
                                                    </div>
                                                    <?php if (!empty($my_status)): ?>
                                                        <span style="font-size: 0.7rem; color: var(--theme-accent-purple); font-weight:700;"><i class="fa-solid fa-circle-check"></i> Registered</span>
                                                    <?php endif; ?>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--theme-text-secondary);">No upcoming scheduled events currently.</p>
                <?php endif; ?>
            </section>

            <!-- PAST EVENTS Grid -->
            <section>
                <h3 style="font-size: 1.3rem; margin-bottom: 1.5rem;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--theme-text-secondary);"></i> Past Campus Gatherings</h3>
                <?php if (!empty($past_events)): ?>
                    <div class="cards-catalog">
                        <?php foreach ($past_events as $event): 
                            $banner = $event['banner_image'] ? htmlspecialchars($event['banner_image']) : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&fit=crop&q=80';
                        ?>
                            <div class="card-glass" style="padding:0; display:flex; flex-direction:column; opacity: 0.75;">
                                <img src="<?php echo $banner; ?>" alt="Event Banner" style="height: 150px; width: 100%; object-fit: cover; border-top-left-radius: inherit; border-top-right-radius: inherit; filter: grayscale(40%);">
                                <div style="padding: 1.5rem; display: flex; flex-direction: column; flex-grow:1;">
                                    <span class="badge badge-student" style="align-self: flex-start; margin-bottom: 0.5rem;"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                    <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <p style="font-size: 0.82rem; color: var(--theme-text-secondary); margin-bottom: 1rem; flex-grow:1;">
                                        <?php echo htmlspecialchars(substr($event['description'], 0, 140)) . '...'; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--theme-text-secondary);">No past events registered.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<!-- CREATE EVENT MODAL (ADMIN ONLY) -->
<div class="modal" id="createEventModal">
    <div class="modal-content" style="max-width: 550px;">
        <button class="modal-close" onclick="closeModal('createEventModal')">&times;</button>
        <h2 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-calendar-plus" style="color: var(--theme-accent-purple);"></i> Schedule Network Event</h2>
        <p style="color: var(--theme-text-secondary); font-size: 0.85rem; margin-bottom: 1.5rem;">Configure meeting timelines and links for verified users.</p>
        
        <form action="events.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_event">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Event Title</label>
                <input type="text" name="title" class="input-glass" placeholder="Grand Homecoming 2026" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Description</label>
                <textarea name="description" class="input-glass" rows="3" placeholder="Reunion agenda, banquet timings, update slides..." required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Date & Time</label>
                    <input type="datetime-local" name="event_date" class="input-glass" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Event Type</label>
                    <select name="event_type" class="input-glass">
                        <option value="in-person">In-Person</option>
                        <option value="online">Online webinar</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Location / URL Link</label>
                <input type="text" name="location" class="input-glass" placeholder="Campus Auditorium, or Zoom Webinar link" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-size:0.85rem; font-weight:600; margin-bottom:0.4rem; display:block;">Upload Banner Picture (Optional)</label>
                <input type="file" name="banner" accept="image/*" class="input-glass">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createEventModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Schedule Event</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

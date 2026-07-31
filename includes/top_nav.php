<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id_nav = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;
$user_role_nav = $_SESSION['user_role'] ?? (isset($_SESSION['admin_id']) ? 'admin' : 'unknown');

// Handle Mark as Read
if (isset($_GET['read_notif'])) {
    $read_id = intval($_GET['read_notif']);
    $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?")->execute([$read_id, $user_id_nav]);
    if (!empty($_GET['redirect_to'])) {
        $target = ltrim($_GET['redirect_to'], '/');
        // Handle subfolder pathing gracefully
        $dest = (strpos($target, 'user/') === 0 && basename(dirname($_SERVER['PHP_SELF'])) === 'user') ? substr($target, 5) : $target;
        header("Location: " . $path_prefix . $dest);
        exit;
    }
    $current_url = preg_replace('/([&?])read_notif=[0-9]+&?/', '$1', $_SERVER['REQUEST_URI']);
    $current_url = rtrim($current_url, '?&');
    header("Location: $current_url");
    exit;
}

// Handle Mark All as Read
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0")->execute([$user_id_nav]);
    $current_url = preg_replace('/([&?])mark_all_read=1&?/', '$1', $_SERVER['REQUEST_URI']);
    $current_url = rtrim($current_url, '?&');
    header("Location: $current_url");
    exit;
}

$unread_count = 0;
$notifications = [];

$user_ids = [];
if (!empty($_SESSION['user_id'])) $user_ids[] = (int)$_SESSION['user_id'];
if (!empty($_SESSION['admin_id'])) $user_ids[] = (int)$_SESSION['admin_id'];
$user_ids = array_values(array_unique(array_filter($user_ids)));
if (empty($user_ids)) $user_ids = [$user_id_nav];

$in_clause = implode(',', array_fill(0, count($user_ids), '?'));

if ($user_id_nav > 0 || !empty($user_ids)) {
    $stmt = $pdo->prepare("SELECT n.*, s.name as sender_name, s.role as sender_role 
                           FROM notifications n 
                           LEFT JOIN users s ON n.sender_id = s.id 
                           WHERE n.user_id IN ($in_clause) AND n.is_read = 0 
                           ORDER BY n.created_at DESC LIMIT 10");
    $stmt->execute($user_ids);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id IN ($in_clause) AND is_read = 0");
    $stmtCount->execute($user_ids);
    $unread_count = (int)$stmtCount->fetchColumn();
}

$profile_link = ($user_role_nav === 'admin') ? '#' : 'profile.php';
$change_pass_link = 'user/change_password.php';
$logout_link = '../logout.php';
// if we are in admin, profile_link doesn't exist, we just put #
if (basename(dirname($_SERVER['PHP_SELF'])) === 'admin') {
    $profile_link = '#';
    $change_pass_link = '../user/change_password.php';
    $logout_link = '../logout.php';
} else if (basename(dirname($_SERVER['PHP_SELF'])) === 'user') {
    $profile_link = 'profile.php';
    $change_pass_link = 'change_password.php';
    $logout_link = '../logout.php';
}

// Determine if user is in Admin portal or has admin role
$is_admin_portal = ($user_role_nav === 'admin') || (basename(dirname($_SERVER['PHP_SELF'])) === 'admin');

// Fetch Dynamic Live Notices (Announcements, Upcoming Events with Dates, Latest Jobs)
$marquee_items = [];
if (!$is_admin_portal) {
    $sub_prefix_nav = (basename(dirname($_SERVER['PHP_SELF'])) === 'user') ? '' : 'user/';

    // 0. Placement Spotlights (Highest Priority)
    try {
        $spotStmt = $pdo->query("SELECT title, message, link FROM notifications WHERE type = 'placement_spotlight' ORDER BY created_at DESC LIMIT 3");
        while ($spot = $spotStmt->fetch(PDO::FETCH_ASSOC)) {
            $dest_url = !empty($spot['link']) ? $spot['link'] : ($sub_prefix_nav . 'dashboard.php');
            if (basename(dirname($_SERVER['PHP_SELF'])) === 'user' && strpos($dest_url, 'user/') === 0) {
                $dest_url = substr($dest_url, 5);
            }
            $marquee_items[] = [
                'badge' => '🎉 CONGRATULATIONS ALUMNI',
                'badge_color' => '#a855f7',
                'title' => $spot['message'],
                'url' => $dest_url
            ];
        }
    } catch (Exception $e) {}

    // 1. Admin Announcements
    $placement_spotlights = [];
    $marquee_items = [];

    // 1. Announcements
    try {
        $annStmt = $pdo->query("SELECT id, title, content FROM announcements WHERE status = 'Publish' ORDER BY created_at DESC LIMIT 5");
        while ($ann = $annStmt->fetch(PDO::FETCH_ASSOC)) {
            if (strpos($ann['title'], 'Placement Spotlight') !== false || strpos($ann['title'], '🎉') !== false) {
                $placement_spotlights[] = [
                    'id' => 'ann_' . $ann['id'],
                    'badge' => '🏆 CONGRATULATIONS',
                    'badge_color' => '#10b981',
                    'title' => str_replace('🎉 Placement Spotlight: ', '', $ann['title']) . ' — Check it out!',
                    'url' => $sub_prefix_nav . 'notifications.php'
                ];
            } else {
                $marquee_items[] = [
                    'id' => 'ann_' . $ann['id'],
                    'badge' => '📢 ANNOUNCEMENT',
                    'badge_color' => '#fbbf24',
                    'title' => $ann['title'] . ' — Check Notifications!',
                    'url' => $sub_prefix_nav . 'notifications.php'
                ];
            }
        }
    } catch (Exception $e) {}

    // 2. Upcoming Events with Dates
    try {
        $evtStmt = $pdo->query("SELECT id, title, event_date FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 3");
        while ($evt = $evtStmt->fetch(PDO::FETCH_ASSOC)) {
            $date_fmt = date('d M Y', strtotime($evt['event_date']));
            $marquee_items[] = [
                'id' => 'evt_' . $evt['id'],
                'badge' => '🎉 EVENT (' . $date_fmt . ')',
                'badge_color' => '#34d399',
                'title' => $evt['title'],
                'url' => $sub_prefix_nav . 'events.php'
            ];
        }
    } catch (Exception $e) {}

    // 3. Latest Active Job & Internship Postings
    try {
        $jobStmt = $pdo->query("SELECT id, title, company FROM jobs WHERE status = 'active' ORDER BY created_at DESC LIMIT 3");
        while ($job = $jobStmt->fetch(PDO::FETCH_ASSOC)) {
            $marquee_items[] = [
                'id' => 'job_' . $job['id'],
                'badge' => '💼 JOB OPENING',
                'badge_color' => '#60a5fa',
                'title' => $job['title'] . ' at ' . $job['company'],
                'url' => $sub_prefix_nav . 'jobs.php'
            ];
        }
    } catch (Exception $e) {}

    // Merge placement spotlights at the very beginning
    $marquee_items = array_merge($placement_spotlights, $marquee_items);

    // 4. Feedback Acknowledgement Broadcast
    $marquee_items[] = [
        'id' => 'static_feedback',
        'badge' => '✨ THANK YOU',
        'badge_color' => '#a855f7',
        'title' => 'Thank you for your feedback! Our team reviews every submission.',
        'url' => $sub_prefix_nav . 'feedback.php'
    ];
}

?>

<?php if (!$is_admin_portal): ?>
<!-- Top Moving Announcement Marquee Banner (Student/Alumni Only) -->
<div class="announcement-marquee-bar" id="announcementMarqueeBar">
    <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #fbbf24; background: rgba(251, 191, 36, 0.15); padding: 3px 12px; border-radius: 20px; font-size: 0.75rem; border: 1px solid rgba(251, 191, 36, 0.3); flex-shrink: 0;">
        <i class="fa-solid fa-bullhorn fa-bounce"></i> NOTICES & LIVE BROADCAST
    </div>
    <div style="flex: 1; overflow: hidden; margin: 0 16px; position: relative; height: 24px; display: flex;">
        <div id="marqueeTrack" style="display: flex; white-space: nowrap; font-weight: 500; cursor: pointer; min-width: 100%; width: max-content;">
            <!-- JS Will Populate This -->
        </div>
    </div>
    <div style="font-size: 0.72rem; opacity: 0.8; font-weight: 600; flex-shrink: 0;" class="marquee-live-label">
        <span style="background: rgba(255,255,255,0.12); padding: 3px 9px; border-radius: 6px;">Live Broadcast</span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rawItems = <?php echo json_encode($marquee_items ?? []); ?>;
    const track = document.getElementById('marqueeTrack');
    const bar = document.getElementById('announcementMarqueeBar');
    
    let readBroadcasts = [];
    try {
        readBroadcasts = JSON.parse(localStorage.getItem('read_broadcasts')) || [];
    } catch (e) {}

    const unreadItems = rawItems.filter(item => !readBroadcasts.includes(item.id));

    if (unreadItems.length === 0) {
        bar.style.display = 'none';
        return;
    }

    let itemHtml = '';
    unreadItems.forEach(item => {
        itemHtml += `
            <a href="${item.url}" class="marquee-item-link" onclick="markBroadcastRead('${item.id}')" style="margin-right: 50px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: inherit; transition: opacity 0.2s ease;">
                <span style="font-weight: 700; color: ${item.badge_color}; background: rgba(255,255,255,0.12); padding: 2px 8px; border-radius: 12px; font-size: 0.72rem;">
                    ${item.badge}
                </span>
                <span style="font-weight: 500; font-size: 0.83rem;">
                    ${item.title}
                </span>
            </a>
        `;
    });

    // Duplicate content twice for seamless marquee loop
    track.innerHTML = itemHtml + itemHtml;

    // Calculate dynamic animation duration based on content length
    // Increase speed: Average 250px per second scroll speed
    const estimatedWidth = unreadItems.length * 400; // rough estimate
    const duration = Math.max(8, estimatedWidth / 250);
    track.style.animation = `marqueeScroll ${duration}s linear infinite`;
});

function markBroadcastRead(id) {
    let readBroadcasts = [];
    try {
        readBroadcasts = JSON.parse(localStorage.getItem('read_broadcasts')) || [];
    } catch (e) {}
    if (!readBroadcasts.includes(id)) {
        readBroadcasts.push(id);
        localStorage.setItem('read_broadcasts', JSON.stringify(readBroadcasts));
    }
}
</script>

<style>
/* Notice Banner Positioning & Light Mode Contrast Overrides */
.announcement-marquee-bar {
    background: linear-gradient(90deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
    color: #ffffff;
    padding: 7px 16px 7px 60px; /* Added 60px left padding so collapsed toggle button never overlaps */
    font-size: 0.83rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    z-index: 100;
    position: relative;
    box-sizing: border-box;
}

html.theme-light .announcement-marquee-bar,
body.theme-light .announcement-marquee-bar {
    background: linear-gradient(90deg, #e0e7ff 0%, #c7d2fe 50%, #e0e7ff 100%) !important;
    color: #0f172a !important;
    border-bottom: 1px solid #a5b4fc !important;
}

html.theme-light .announcement-marquee-bar .marquee-item-link,
body.theme-light .announcement-marquee-bar .marquee-item-link {
    color: #0f172a !important;
}

html.theme-light .announcement-marquee-bar .marquee-live-label span,
body.theme-light .announcement-marquee-bar .marquee-live-label span {
    background: rgba(15, 23, 42, 0.08) !important;
    color: #1e1b4b !important;
}

.marquee-item-link:hover {
    opacity: 0.8;
    text-decoration: underline !important;
}

.announcement-marquee-bar:hover #marqueeTrack {
    animation-play-state: paused !important;
}

@keyframes marqueeScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
#marqueeTrack:hover {
    animation-play-state: paused;
}
</style>
<?php endif; ?>

<nav class="top-nav">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
        <div class="top-nav-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <label for="global-nav-search-input" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;">Search platform</label>
            <input type="text" id="global-nav-search-input" name="global_search" class="input-glass" placeholder="Search platform..." autocomplete="off" aria-label="Search platform" title="Click or press Ctrl+K to search">
            <span class="search-kbd">⌘K</span>
        </div>
    </div>

    <div class="top-nav-actions">
        <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark/Bright Mode">
            <i class="fa-solid fa-moon"></i>
        </button>
        
        <!-- Direct Messages Shortcut -->
        <?php if ($user_role_nav !== 'admin'): ?>
        <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) === 'user') ? 'chat.php' : 'user/chat.php'; ?>" class="notif-bell-btn" title="Direct Chat & Messages" style="text-decoration: none;">
            <i class="fa-regular fa-comment-dots" style="font-size: 18px;"></i>
        </a>
        <?php endif; ?>

        <!-- Notification Bell -->
        <button class="notif-bell-btn" id="notifBellBtn" title="Notification Center">
            <i class="fa-regular fa-bell" style="font-size: 18px;"></i>
            <span class="notif-badge" id="notifBadge" style="<?php echo ($unread_count > 0) ? 'display:flex;' : 'display:none;'; ?>">
                <?php echo ($unread_count > 99) ? '99+' : $unread_count; ?>
            </span>
        </button>

        <!-- Visible Role Pill Badge in Top Navbar -->
        <div class="nav-role-badge">
            <?php if ($user_role_nav === 'admin'): ?>
                <span class="role-pill pill-admin" title="Administrator Role Panel"><i class="fa-solid fa-user-shield"></i> Admin</span>
            <?php elseif ($user_role_nav === 'alumni'): ?>
                <span class="role-pill pill-alumni" title="Alumni Member Network"><i class="fa-solid fa-user-tie"></i> Alumni</span>
            <?php else: ?>
                <span class="role-pill pill-student" title="Student Member Portal"><i class="fa-solid fa-user-graduate"></i> Student</span>
            <?php endif; ?>
        </div>

        <!-- User profile dropdown -->
        <div style="position: relative;">
            <img src="<?php echo htmlspecialchars($sidebar_avatar ?? 'https://cdn-icons-png.flaticon.com/512/149/149071.png'); ?>" alt="User Avatar" class="nav-user-avatar" id="profile-avatar-toggle">
            <div class="nav-dropdown-menu" id="profile-dropdown-menu">
                <div class="dropdown-header-info">
                    <h4><?php echo htmlspecialchars($user_name ?? 'User'); ?></h4>
                    <p style="margin-top: 0.25rem;">
                        <?php if ($user_role_nav === 'admin'): ?>
                            <span style="font-weight: 600; color: var(--theme-accent-purple); display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-user-shield"></i> Admin Panel</span>
                        <?php elseif ($user_role_nav === 'alumni'): ?>
                            <span style="font-weight: 600; color: var(--theme-accent-blue); display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-user-tie"></i> Alumni Member</span>
                        <?php else: ?>
                            <span style="font-weight: 600; color: #10b981; display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-user-graduate"></i> Student Member</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($profile_link !== '#'): ?>
                    <a href="<?php echo $profile_link; ?>" class="dropdown-item"><i data-lucide="user" style="width:16px;height:16px;"></i> My Profile</a>
                <?php endif; ?>
                <a href="<?php echo $change_pass_link; ?>" class="dropdown-item"><i data-lucide="key" style="width:16px;height:16px;"></i> Change Password</a>
                <div style="border-top: 1px solid var(--theme-border); margin: 0.25rem 0;"></div>
                <a href="<?php echo $logout_link; ?>" class="dropdown-item" style="color: var(--accent-danger);"><i data-lucide="log-out" style="width:16px;height:16px;"></i> Sign Out</a>
            </div>
        </div>
    </div>
</nav>

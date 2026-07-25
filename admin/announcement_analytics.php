<?php
$page_title = "Admin Notification Analytics";
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

if (!is_admin()) {
    header("Location: ../login.php");
    exit;
}

// POST Action Dispatcher
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_announcement') {
        $annc_id = (int)($_POST['announcement_id'] ?? 0);
        if ($annc_id > 0) {
            $pdo->prepare("DELETE FROM announcement_views WHERE announcement_id = ?")->execute([$annc_id]);
            $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$annc_id]);
            header("Location: announcement_analytics.php");
            exit;
        }
    } elseif ($action === 'create_announcement') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target_role = $_POST['target_role'] ?? 'all';
        $priority = $_POST['priority'] ?? 'medium';

        if ($title !== '' && $message !== '') {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, target_role, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$title, $message, $target_role]);
            $new_id = $pdo->lastInsertId();

            $queryUsers = "SELECT id FROM users WHERE status = 'approved'";
            if ($target_role !== 'all') {
                $queryUsers .= " AND role = " . $pdo->quote($target_role);
            }
            $targetUsers = $pdo->query($queryUsers)->fetchAll(PDO::FETCH_COLUMN);

            $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, priority, link, created_at) VALUES (?, ?, ?, 'announcement', ?, ?, NOW())");
            $link = "user/notifications.php?announcement_id=" . $new_id;
            foreach ($targetUsers as $uid) {
                $stmtNotif->execute([$uid, $title, $message, $priority, $link]);
            }

            header("Location: announcement_analytics.php?announcement_id=" . $new_id);
            exit;
        }
    }
}

// Fetch Announcements with metrics
$announcements = $pdo->query("SELECT a.*, 
                               (SELECT COUNT(*) FROM announcement_views v WHERE v.announcement_id = a.id) as total_views,
                               (SELECT COUNT(DISTINCT v.user_id) FROM announcement_views v WHERE v.announcement_id = a.id AND v.status = 'read') as unique_reads
                              FROM announcements a ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$total_notifications = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$read_notifications  = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 1")->fetchColumn();
$unread_notifications = $total_notifications - $read_notifications;
$total_approved_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn();

$selected_annc_id = (int)($_GET['announcement_id'] ?? ($announcements[0]['id'] ?? 0));

$viewed_users = [];
$unviewed_users = [];

if ($selected_annc_id > 0) {
    // 1. Who Viewed
    $stmtViewed = $pdo->prepare("
        SELECT v.*, u.name as user_name, u.email as user_email, u.role as user_role,
               COALESCE(ap.course, sp.course, 'General') as department
        FROM announcement_views v
        JOIN users u ON v.user_id = u.id
        LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND u.role = 'alumni'
        LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
        WHERE v.announcement_id = ?
        ORDER BY v.viewed_at DESC
    ");
    $stmtViewed->execute([$selected_annc_id]);
    $viewed_users = $stmtViewed->fetchAll(PDO::FETCH_ASSOC);

    // 2. Who Has Not Viewed
    $stmtUnviewed = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role,
               COALESCE(ap.course, sp.course, 'General') as department
        FROM users u
        LEFT JOIN alumni_profiles ap ON u.id = ap.user_id AND u.role = 'alumni'
        LEFT JOIN student_profiles sp ON u.id = sp.user_id AND u.role = 'student'
        WHERE u.status = 'approved' 
          AND u.id NOT IN (SELECT user_id FROM announcement_views WHERE announcement_id = ?)
        ORDER BY u.name ASC
    ");
    $stmtUnviewed->execute([$selected_annc_id]);
    $unviewed_users = $stmtUnviewed->fetchAll(PDO::FETCH_ASSOC);
}

// Device & Browser distribution
$deviceStats = $pdo->query("SELECT device, COUNT(*) as qty FROM announcement_views GROUP BY device")->fetchAll(PDO::FETCH_ASSOC);
$browserStats = $pdo->query("SELECT browser, COUNT(*) as qty FROM announcement_views GROUP BY browser")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php render_sidebar('notifications'); ?>
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        <main class="dashboard-workspace">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-chart-pie" style="color: #818cf8;"></i> Admin Notification & Announcement Analytics
            </h1>
            <p style="color: var(--theme-text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                Real-time delivery performance, view timestamps, read percentages, device metrics, and non-viewer tracking.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="admin_notifications.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> System Activity</a>
            <a href="audit_logs.php" class="btn btn-primary"><i class="fa-solid fa-clipboard-check"></i> Audit Logs</a>
        </div>
    </div>

    <!-- Overview Metric Cards (Linear / Vercel Grade KPI Cards) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="card-glass" style="padding: 1.5rem; border-radius: 16px; transition: transform 0.3s ease, box-shadow 0.3s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.85rem;">
                <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--theme-text-muted); letter-spacing: 0.05em;">Total System Alerts</span>
                <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #818cf8; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fa-solid fa-bell"></i>
                </div>
            </div>
            <div style="font-size: 2.1rem; font-weight: 800; color: var(--theme-text-primary); line-height: 1.1; margin-bottom: 0.4rem;"><?php echo number_format($total_notifications); ?></div>
            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: #10b981; font-weight: 600;">
                <i class="fa-solid fa-arrow-trend-up"></i> +12.4% <span style="color: var(--theme-text-muted); font-weight: 500;">vs last week</span>
            </div>
        </div>

        <div class="card-glass" style="padding: 1.5rem; border-radius: 16px; transition: transform 0.3s ease, box-shadow 0.3s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.85rem;">
                <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--theme-text-muted); letter-spacing: 0.05em;">Read Notifications</span>
                <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(34, 197, 94, 0.15); color: #4ade80; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
            <div style="font-size: 2.1rem; font-weight: 800; color: var(--theme-text-primary); line-height: 1.1; margin-bottom: 0.4rem;"><?php echo number_format($read_notifications); ?></div>
            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: #10b981; font-weight: 600;">
                <i class="fa-solid fa-circle-check"></i> <?php echo ($total_notifications > 0) ? round(($read_notifications/$total_notifications)*100, 1) : 0; ?>% <span style="color: var(--theme-text-muted); font-weight: 500;">overall read rate</span>
            </div>
        </div>

        <div class="card-glass" style="padding: 1.5rem; border-radius: 16px; transition: transform 0.3s ease, box-shadow 0.3s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.85rem;">
                <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--theme-text-muted); letter-spacing: 0.05em;">Unread / Pending</span>
                <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #fbbf24; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>
            </div>
            <div style="font-size: 2.1rem; font-weight: 800; color: var(--theme-text-primary); line-height: 1.1; margin-bottom: 0.4rem;"><?php echo number_format($unread_notifications); ?></div>
            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: #fbbf24; font-weight: 600;">
                <i class="fa-solid fa-clock"></i> Action required <span style="color: var(--theme-text-muted); font-weight: 500;">by audience</span>
            </div>
        </div>

        <div class="card-glass" style="padding: 1.5rem; border-radius: 16px; transition: transform 0.3s ease, box-shadow 0.3s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.85rem;">
                <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: var(--theme-text-muted); letter-spacing: 0.05em;">Target Audience</span>
                <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(56, 189, 248, 0.15); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div style="font-size: 2.1rem; font-weight: 800; color: var(--theme-text-primary); line-height: 1.1; margin-bottom: 0.4rem;"><?php echo number_format($total_approved_users); ?></div>
            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: #38bdf8; font-weight: 600;">
                <i class="fa-solid fa-user-check"></i> Approved members <span style="color: var(--theme-text-muted); font-weight: 500;">active in network</span>
            </div>
        </div>
    </div>

    <!-- Create & Dispatch Announcement Timeline Component -->
    <div class="card-glass" style="padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="document.getElementById('createAnncTimeline').style.display = document.getElementById('createAnncTimeline').style.display === 'none' ? 'block' : 'none'">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <i class="fa-solid fa-paper-plane" style="color: #6366f1;"></i> Create & Dispatch New Announcement Timeline
            </h3>
            <span class="btn btn-secondary btn-small"><i class="fa-solid fa-plus"></i> New Announcement</span>
        </div>

        <form method="POST" id="createAnncTimeline" style="display: none; margin-top: 1.5rem; border-top: 1px solid var(--theme-border); padding-top: 1.25rem;">
            <input type="hidden" name="action" value="create_announcement">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--theme-text-primary);">Announcement Title</label>
                    <input type="text" name="title" required placeholder="e.g. Annual Alumni Meet 2026 Registration Open" class="input-glass" style="width: 100%; padding: 10px 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--theme-text-primary);">Target Audience</label>
                    <select name="target_role" class="input-glass" style="width: 100%; padding: 10px 14px;">
                        <option value="all">All Members (Students & Alumni)</option>
                        <option value="student">Students Only</option>
                        <option value="alumni">Alumni Only</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--theme-text-primary);">Priority Level</label>
                    <select name="priority" class="input-glass" style="width: 100%; padding: 10px 14px;">
                        <option value="medium">Medium Priority</option>
                        <option value="high">High Priority</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--theme-text-primary);">Announcement Content / Message</label>
                <textarea name="message" rows="3" required placeholder="Enter full announcement details here..." class="input-glass" style="width: 100%; padding: 10px 14px;"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createAnncTimeline').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish & Dispatch Notifications</button>
            </div>
        </form>
    </div>

    <!-- Selected Announcement Radial Gauge & Breakdown Card -->
    <div class="card-glass" style="padding: 1.75rem; border-radius: 16px; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                    <i class="fa-solid fa-bullhorn" style="color: #f59e0b;"></i> Selected Announcement Performance
                </h3>
                <p style="font-size: 0.85rem; color: var(--theme-text-muted); margin-top: 0.25rem;">Real-time delivery progress ring, view percentages, and non-viewer tracking.</p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <form method="GET" style="display: flex; gap: 0.5rem; align-items: center; margin: 0;">
                    <select name="announcement_id" onchange="this.form.submit()" class="input-glass" style="padding: 10px 16px; font-size: 0.9rem; min-width: 300px; font-weight: 600;">
                        <?php foreach ($announcements as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $a['id'] == $selected_annc_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['title']); ?> (<?php echo date('M d', strtotime($a['created_at'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selected_annc_id > 0): ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this announcement? All related view analytics and logs will be removed.')" style="margin: 0;">
                    <input type="hidden" name="action" value="delete_announcement">
                    <input type="hidden" name="announcement_id" value="<?php echo $selected_annc_id; ?>">
                    <button type="submit" class="btn btn-secondary" style="color: #f87171; border-color: rgba(239, 68, 68, 0.4); padding: 9px 16px;">
                        <i class="fa-solid fa-trash-can"></i> Delete Announcement
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php 
            $currAnnc = null;
            foreach ($announcements as $a) {
                if ($a['id'] == $selected_annc_id) { $currAnnc = $a; break; }
            }
            if ($currAnnc): 
                $uniqueReads = (int)$currAnnc['unique_reads'];
                $readPct = ($total_approved_users > 0) ? round(($uniqueReads / $total_approved_users) * 100, 1) : 0;
                $ignoredPct = max(0, 100 - $readPct);
                $lastViewedTime = !empty($viewed_users[0]['viewed_at']) ? date('M d, Y H:i', strtotime($viewed_users[0]['viewed_at'])) : 'No views yet';
        ?>
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: center; background: rgba(0,0,0,0.15); border-radius: 14px; padding: 1.5rem; border: 1px solid var(--theme-border);">
                <!-- Radial Progress Ring Visual -->
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                    <div style="position: relative; width: 140px; height: 140px; display: flex; align-items: center; justify-content: center;">
                        <svg width="140" height="140" viewBox="0 0 140 140" style="transform: rotate(-90deg);">
                            <circle cx="70" cy="70" r="54" stroke="rgba(255,255,255,0.08)" stroke-width="12" fill="transparent" />
                            <circle cx="70" cy="70" r="54" stroke="url(#progressGradient)" stroke-width="12" fill="transparent" 
                                    stroke-dasharray="339.29" stroke-dashoffset="<?php echo 339.29 - (339.29 * $readPct / 100); ?>" stroke-linecap="round" style="transition: stroke-dashoffset 1s ease;" />
                            <defs>
                                <linearGradient id="progressGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#10b981" />
                                    <stop offset="100%" stop-color="#34d399" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div style="position: absolute; display: flex; flex-direction: column; align-items: center;">
                            <span style="font-size: 1.6rem; font-weight: 800; color: var(--theme-text-primary); line-height: 1;"><?php echo $readPct; ?>%</span>
                            <span style="font-size: 0.72rem; font-weight: 700; color: #4ade80; text-transform: uppercase; margin-top: 0.2rem;">Read Rate</span>
                        </div>
                    </div>
                </div>

                <!-- Comprehensive Metrics Grid -->
                <div>
                    <h4 style="font-size: 1.15rem; font-weight: 800; color: var(--theme-text-primary); margin-bottom: 0.75rem;"><?php echo htmlspecialchars($currAnnc['title']); ?></h4>
                    <p style="font-size: 0.88rem; color: var(--theme-text-secondary); line-height: 1.5; margin-bottom: 1.25rem;"><?php echo htmlspecialchars($currAnnc['content']); ?></p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem;">
                        <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 0.85rem; border-radius: 8px;">
                            <div style="font-size: 0.75rem; font-weight: 700; color: #10b981; text-transform: uppercase;">Viewed Members</div>
                            <div style="font-size: 1.3rem; font-weight: 800; color: var(--theme-text-primary); margin-top: 0.2rem;"><?php echo count($viewed_users); ?></div>
                        </div>

                        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 0.85rem; border-radius: 8px;">
                            <div style="font-size: 0.75rem; font-weight: 700; color: #f87171; text-transform: uppercase;">Pending / Unread</div>
                            <div style="font-size: 1.3rem; font-weight: 800; color: var(--theme-text-primary); margin-top: 0.2rem;"><?php echo count($unviewed_users); ?></div>
                        </div>

                        <div style="background: rgba(99, 102, 241, 0.1); border-left: 4px solid #6366f1; padding: 0.85rem; border-radius: 8px;">
                            <div style="font-size: 0.75rem; font-weight: 700; color: #818cf8; text-transform: uppercase;">Total Audience</div>
                            <div style="font-size: 1.3rem; font-weight: 800; color: var(--theme-text-primary); margin-top: 0.2rem;"><?php echo $total_approved_users; ?></div>
                        </div>

                        <div style="background: rgba(56, 189, 248, 0.1); border-left: 4px solid #38bdf8; padding: 0.85rem; border-radius: 8px;">
                            <div style="font-size: 0.75rem; font-weight: 700; color: #38bdf8; text-transform: uppercase;">Last Viewed Time</div>
                            <div style="font-size: 0.85rem; font-weight: 700; color: var(--theme-text-primary); margin-top: 0.35rem;"><?php echo $lastViewedTime; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Who Viewed vs Who Has Not Viewed Tables -->
    <div style="display: grid; grid-template-columns: 1fr; gap: 2rem; margin-bottom: 2rem;">
        <!-- Table 1: Who Viewed -->
        <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-eye" style="color: #4ade80;"></i> Users Who Viewed This Announcement (<?php echo count($viewed_users); ?>)
                </h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-secondary btn-small" onclick="exportTableToCSV('viewedTable', 'announcement_viewers.csv')"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
                    <button class="btn btn-secondary btn-small" onclick="printTable('viewedTable', 'Announcement Viewers Report')"><i class="fa-solid fa-print"></i> Print Report</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="viewedTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 22%;">User Name & Email</th>
                            <th style="width: 10%;">Role</th>
                            <th style="width: 18%;">Department</th>
                            <th style="width: 16%;">View Timestamp</th>
                            <th style="width: 10%;">Duration</th>
                            <th style="width: 8%;">Device</th>
                            <th style="width: 8%;">Browser</th>
                            <th style="width: 8%;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($viewed_users)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--theme-text-muted); padding: 2rem;">No users have viewed this announcement yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($viewed_users as $vu): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--theme-text-primary);"><?php echo htmlspecialchars($vu['user_name']); ?></strong><br>
                                        <span style="font-size: 0.78rem; color: var(--theme-text-muted);"><?php echo htmlspecialchars($vu['user_email']); ?></span>
                                    </td>
                                    <td><span class="role-pill pill-<?php echo strtolower($vu['user_role']); ?>"><?php echo ucfirst($vu['user_role']); ?></span></td>
                                    <td><?php echo htmlspecialchars($vu['department']); ?></td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($vu['viewed_at'])); ?></td>
                                    <td><span class="badge" style="background: rgba(99,102,241,0.15); color: #818cf8;"><?php echo (int)($vu['read_duration'] ?? 0); ?>s</span></td>
                                    <td><i class="fa-solid fa-desktop" style="font-size: 0.8rem;"></i> <?php echo htmlspecialchars($vu['device'] ?? 'Desktop'); ?></td>
                                    <td><i class="fa-solid fa-globe" style="font-size: 0.8rem;"></i> <?php echo htmlspecialchars($vu['browser'] ?? 'Browser'); ?></td>
                                    <td><code><?php echo htmlspecialchars($vu['ip_address'] ?? '127.0.0.1'); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Table 2: Who Has Not Viewed -->
        <div class="glass-card" style="padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-user-clock" style="color: #f87171;"></i> Pending / Users Who Have Not Viewed (<?php echo count($unviewed_users); ?>)
                </h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-secondary btn-small" onclick="exportTableToCSV('unviewedTable', 'pending_announcement_viewers.csv')"><i class="fa-solid fa-file-csv"></i> Export Pending List</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="unviewedTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 22%;">User Name</th>
                            <th style="width: 28%;">Email Address</th>
                            <th style="width: 12%;">Role</th>
                            <th style="width: 23%;">Department</th>
                            <th style="width: 15%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($unviewed_users)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #4ade80; padding: 2rem;">🎉 All target users have viewed this announcement!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($unviewed_users as $uu): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--theme-text-primary);"><?php echo htmlspecialchars($uu['name']); ?></td>
                                    <td><?php echo htmlspecialchars($uu['email']); ?></td>
                                    <td><span class="role-pill pill-<?php echo strtolower($uu['role']); ?>"><?php echo ucfirst($uu['role']); ?></span></td>
                                    <td><?php echo htmlspecialchars($uu['department']); ?></td>
                                    <td><span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">Pending View</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Table 3: Selected Announcement Notification Delivery & Read Tracker -->
        <?php 
            $anncTitle = $currAnnc['title'] ?? '';
            $stmtNotif = $pdo->prepare("
                SELECT n.*, u.name as user_name, u.email as user_email, u.role as user_role 
                FROM notifications n 
                JOIN users u ON n.user_id = u.id 
                WHERE n.title = ? OR n.message LIKE ? OR n.link LIKE ?
                ORDER BY n.created_at DESC LIMIT 100
            ");
            $stmtNotif->execute([$anncTitle, "%$anncTitle%", "%announcement_id=$selected_annc_id%"]);
            $notif_delivery_logs = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

            if (empty($notif_delivery_logs)) {
                $stmtFallback = $pdo->prepare("
                    SELECT n.*, u.name as user_name, u.email as user_email, u.role as user_role 
                    FROM notifications n 
                    JOIN users u ON n.user_id = u.id 
                    WHERE n.type = 'announcement' OR n.category = 'announcement'
                    ORDER BY n.created_at DESC LIMIT 100
                ");
                $stmtFallback->execute();
                $notif_delivery_logs = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
            }
        ?>
        <div class="card-glass" style="padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-bell" style="color: #38bdf8;"></i> Notification Delivery & Read Tracker Audit Log
                    </h3>
                    <p style="color: var(--theme-text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                        Audit log showing target users, alert messages, read statuses, priority ratings, and dispatch timestamps.
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-secondary btn-small" onclick="exportTableToCSV('notifDeliveryTable', 'notification_delivery_audit.csv')"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
                    <button class="btn btn-secondary btn-small" onclick="printTable('notifDeliveryTable', 'Notification Delivery Audit Report')"><i class="fa-solid fa-print"></i> Print</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="notifDeliveryTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Target User</th>
                            <th style="width: 20%;">Notification Title</th>
                            <th style="width: 30%;">Message</th>
                            <th style="width: 10%;">Priority</th>
                            <th style="width: 10%;">Read Status</th>
                            <th style="width: 8%;">Sent Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notif_delivery_logs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--theme-text-muted); padding: 2rem;">No notification logs recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notif_delivery_logs as $nl): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--theme-text-primary);"><?php echo htmlspecialchars($nl['user_name']); ?></strong><br>
                                        <span style="font-size: 0.78rem; color: var(--theme-text-muted);"><?php echo htmlspecialchars($nl['user_email']); ?> (<?php echo ucfirst($nl['user_role']); ?>)</span>
                                    </td>
                                    <td><strong style="color: var(--theme-accent-blue);"><?php echo htmlspecialchars($nl['title']); ?></strong></td>
                                    <td style="max-width: 280px; font-size: 0.85rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($nl['message']); ?></td>
                                    <td>
                                        <span class="priority-tag <?php echo htmlspecialchars($nl['priority'] ?? 'medium'); ?>">
                                            <?php echo strtoupper(htmlspecialchars($nl['priority'] ?? 'medium')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($nl['is_read'] == 1): ?>
                                            <span class="badge" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3);">
                                                <i class="fa-solid fa-eye"></i> READ
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(234, 179, 8, 0.15); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3);">
                                                <i class="fa-solid fa-eye-slash"></i> UNREAD
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--theme-text-muted);"><?php echo date('M d, Y H:i', strtotime($nl['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
$page_title = "Notification Center";
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_login();

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
                <i class="fa-solid fa-bell" style="color: #818cf8;"></i> Notification Center
            </h1>
            <p style="color: var(--theme-text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                Manage all your alerts, mentorship updates, job applications, and security notifications.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <button class="btn btn-secondary" onclick="NotificationApp.markAllAsRead()">
                <i class="fa-solid fa-check-double"></i> Mark All as Read
            </button>
            <button class="btn btn-secondary" onclick="NotificationApp.clearAllNotifications()" style="color: #f87171; border-color: rgba(239, 68, 68, 0.3);">
                <i class="fa-solid fa-trash-can"></i> Clear All Notifications
            </button>
            <a href="notification_settings.php" class="btn btn-primary">
                <i class="fa-solid fa-sliders"></i> Preferences
            </a>
        </div>
    </div>

    <!-- Notification Container Card -->
    <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
        <!-- Filters & Search -->
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <div class="notif-tabs" style="margin: 0;">
                <button class="notif-tab-pill active" data-cat="all">All</button>
                <button class="notif-tab-pill" data-cat="unread">Unread</button>
                <button class="notif-tab-pill" data-cat="mentorship">Mentorship</button>
                <button class="notif-tab-pill" data-cat="jobs">Jobs</button>
                <button class="notif-tab-pill" data-cat="events">Events</button>
                <button class="notif-tab-pill" data-cat="announcements">Announcements</button>
                <button class="notif-tab-pill" data-cat="messages">Messages</button>
                <button class="notif-tab-pill" data-cat="security">Security</button>
            </div>
            
            <div style="min-width: 240px; position: relative;">
                <input type="text" id="pageNotifSearch" class="input-glass" placeholder="Filter alerts..." style="width: 100%; padding-left: 2.2rem; height: 38px; font-size: 0.85rem;">
                <i class="fa-solid fa-search" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-muted); font-size: 0.85rem;"></i>
            </div>
        </div>

        <!-- Dynamic List Container -->
        <div id="fullNotifList" style="display: flex; flex-direction: column; gap: 0.85rem; min-height: 250px;">
            <div class="notif-empty-state">
                <i class="fas fa-spinner fa-spin notif-empty-icon"></i>
                <div class="notif-empty-title">Loading Notification Center...</div>
            </div>
        </div>
    <!-- Notification & Broadcast Read Analytics -->
    <?php 
        $user_id_curr = get_user_id();
        $annc_analytics_list = $pdo->query("
            SELECT a.id, a.title, a.content, a.created_at,
                   (SELECT COUNT(*) FROM announcement_views v WHERE v.announcement_id = a.id) as total_views,
                   (SELECT COUNT(DISTINCT v.user_id) FROM announcement_views v WHERE v.announcement_id = a.id AND v.status = 'read') as unique_reads,
                   (SELECT COUNT(*) FROM announcement_views v WHERE v.announcement_id = a.id AND v.user_id = {$user_id_curr}) as user_has_read
            FROM announcements a 
            ORDER BY a.created_at DESC LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        $total_aud = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn();
    ?>
    <div class="glass-card" style="padding: 1.5rem; border-radius: 16px; margin-top: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-chart-line" style="color: #f59e0b;"></i> System Announcements & Read Analytics
                </h3>
                <p style="color: var(--theme-text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                    Tracking broadcast delivery, unique readers, and view counts across the network.
                </p>
            </div>
            <?php if (is_admin()): ?>
                <a href="../admin/announcement_analytics.php" class="btn btn-secondary btn-small"><i class="fa-solid fa-chart-pie"></i> Detailed Admin Analytics</a>
            <?php endif; ?>
        </div>

        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Announcement Title</th>
                        <th>Sent Date</th>
                        <th>Viewed By</th>
                        <th>Read Rate (%)</th>
                        <th>Your Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($annc_analytics_list)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--theme-text-muted); padding: 2rem;">No system broadcasts posted yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($annc_analytics_list as $annc): ?>
                            <?php 
                                $uniqueR = (int)$annc['unique_reads'];
                                $pct = ($total_aud > 0) ? round(($uniqueR / $total_aud) * 100, 1) : 0;
                                $safeT = htmlspecialchars(addslashes($annc['title']));
                                $safeC = htmlspecialchars(addslashes($annc['content']));
                                $timeStr = date('M d, Y', strtotime($annc['created_at']));
                            ?>
                            <tr>
                                <td><strong style="color: var(--theme-text-primary);"><?php echo htmlspecialchars($annc['title']); ?></strong></td>
                                <td style="font-size: 0.85rem; color: var(--theme-text-muted);"><?php echo $timeStr; ?></td>
                                <td>
                                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-weight: 600;">
                                        <i class="fa-solid fa-eye"></i> <?php echo $uniqueR; ?> / <?php echo $total_aud; ?> Users
                                    </span>
                                </td>
                                <td style="min-width: 140px;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="flex: 1; height: 6px; background: rgba(255,255,255,0.08); border-radius: 4px; overflow: hidden;">
                                            <div style="width: <?php echo $pct; ?>%; height: 100%; background: linear-gradient(90deg, #10b981, #34d399);"></div>
                                        </div>
                                        <span style="font-size: 0.78rem; font-weight: 700; color: #34d399;"><?php echo $pct; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($annc['user_has_read'] > 0): ?>
                                        <span class="badge" style="background: rgba(34, 197, 94, 0.15); color: #22c55e;"><i class="fa-solid fa-check"></i> Read</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171;"><i class="fa-solid fa-clock"></i> Unread</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn btn-secondary btn-small" style="font-size: 0.78rem; padding: 4px 10px;" onclick="NotificationApp.openNotifDetails(0, '<?php echo $safeT; ?>', '<?php echo $safeC; ?>', '<?php echo $timeStr; ?>', 'high', 'announcements', 'fa-bullhorn', 'amber', '#')">
                                        <i class="fa-solid fa-circle-info"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentCat = 'all';
    let currentQuery = '';

    function loadPageNotifications() {
        const isUnreadOnly = (currentCat === 'unread');
        const catParam = isUnreadOnly ? 'all' : currentCat;
        const url = `../api/notifications.php?action=fetch&category=${encodeURIComponent(catParam)}&unread_only=${isUnreadOnly ? 1 : 0}&search=${encodeURIComponent(currentQuery)}&limit=50`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('fullNotifList');
                if (!list) return;

                if (data.status === 'success' && data.items.length > 0) {
                    let html = '';
                    data.items.forEach(item => {
                        const isUnread = !item.is_read;
                        const icon = item.icon || 'fa-bell';
                        const color = item.color || 'indigo';
                        const safeTitle = (item.title || '').replace(/'/g, "\\'");
                        const safeMsg = (item.message || '').replace(/'/g, "\\'");

                        html += `
                            <div class="notif-card ${isUnread ? 'unread' : ''}" style="padding: 1rem 1.25rem;" onclick="NotificationApp.handleCardClick(event, ${item.id}, '${targetUrl}', '${safeTitle}', '${safeMsg}', '${item.time_ago || ''}', '${item.priority || 'medium'}', '${item.category || ''}', '${item.icon || ''}', '${item.color || ''}')">
                                <div class="notif-avatar-box">
                                    <div class="notif-icon-badge ${color}" style="width: 44px; height: 44px; font-size: 1.1rem;">
                                        <i class="fa-solid ${icon}"></i>
                                    </div>
                                </div>
                                <div class="notif-info">
                                    <div class="notif-header-line">
                                        <span class="notif-card-title" style="font-size: 0.95rem;">${item.title}</span>
                                        <span class="notif-time">${item.time_ago}</span>
                                    </div>
                                    <div class="notif-card-desc" style="font-size: 0.88rem; margin-bottom: 0.5rem;">${item.message}</div>
                                    <div class="notif-footer-meta">
                                        <span class="priority-tag ${item.priority}">${item.priority}</span>
                                        <div class="notif-card-actions">
                                            ${isUnread ? `<button class="btn btn-secondary btn-small" style="padding: 3px 8px; font-size: 0.75rem;" onclick="event.stopPropagation(); NotificationApp.markSingleRead(${item.id}); setTimeout(loadPageNotifications, 300);"><i class="fa-solid fa-check"></i> Mark Read</button>` : ''}
                                            <button class="btn btn-secondary btn-small" style="padding: 3px 8px; font-size: 0.75rem; color: #f87171;" onclick="event.stopPropagation(); NotificationApp.deleteNotif(${item.id}); setTimeout(loadPageNotifications, 300);"><i class="fa-solid fa-trash"></i> Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    list.innerHTML = html;
                } else {
                    list.innerHTML = `
                        <div class="notif-empty-state" style="padding: 3rem 1rem;">
                            <i class="fa-solid fa-bell-slash notif-empty-icon" style="font-size: 3rem;"></i>
                            <div class="notif-empty-title" style="font-size: 1.1rem; margin-top: 0.5rem;">No notifications match your filter</div>
                            <div class="notif-empty-desc">Check back later or select a different category.</div>
                        </div>
                    `;
                }
            });
    }

    document.querySelectorAll('.notif-tab-pill').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.notif-tab-pill').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCat = this.getAttribute('data-cat');
            loadPageNotifications();
        });
    });

    document.getElementById('pageNotifSearch')?.addEventListener('input', function(e) {
        currentQuery = e.target.value;
        loadPageNotifications();
    });

    loadPageNotifications();
});
</script>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

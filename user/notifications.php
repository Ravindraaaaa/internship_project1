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
    <!-- System Announcements & Read Analytics section removed for user readability -->
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
                        const targetUrl = item.target_url || '#';

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

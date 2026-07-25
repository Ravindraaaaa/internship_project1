/**
 * Modern Notification & Activity System Client Engine
 * Features: Live Polling, Glassmorphism Drawer, Filter Tabs, Toasts, Unread Count Badge, AJAX Actions.
 */

const NotificationApp = (function() {
    let activeCategory = 'all';
    let searchQuery = '';
    let pollingInterval = null;
    let lastUnreadCount = -1;
    const POLL_TIME = 6000; // 6 seconds

    function init() {
        createUIElements();
        bindEvents();
        fetchNotifications();
        startPolling();
    }

    function createUIElements() {
        // Create Toast Container if missing
        if (!document.getElementById('toastContainer')) {
            const toastBox = document.createElement('div');
            toastBox.id = 'toastContainer';
            document.body.appendChild(toastBox);
        }

        // Create Notification Drawer Overlay & Drawer Container if missing
        if (!document.getElementById('notifDrawerOverlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'notifDrawerOverlay';
            overlay.className = 'notif-drawer-overlay';
            
            const drawer = document.createElement('div');
            drawer.id = 'notificationDrawer';
            drawer.className = 'notif-drawer';
            drawer.innerHTML = `
                <div class="notif-drawer-header">
                    <div class="notif-drawer-title">
                        <i class="fas fa-bell" style="color:#818cf8;"></i> Notifications
                    </div>
                    <div class="notif-drawer-actions">
                        <button class="btn-icon-soft" id="btnMarkAllRead">
                            <i class="fas fa-check-double"></i> Mark all read
                        </button>
                        <button class="btn-close-drawer" id="btnCloseDrawer">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="notif-controls">
                    <div class="notif-search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="notifSearchInput" class="notif-search-input" placeholder="Search notifications...">
                    </div>
                    
                    <div class="notif-tabs" id="notifTabs">
                        <button class="notif-tab-pill active" data-cat="all">All</button>
                        <button class="notif-tab-pill" data-cat="unread">Unread</button>
                        <button class="notif-tab-pill" data-cat="mentorship">Mentorship</button>
                        <button class="notif-tab-pill" data-cat="jobs">Jobs</button>
                        <button class="notif-tab-pill" data-cat="events">Events</button>
                        <button class="notif-tab-pill" data-cat="announcements">Announcements</button>
                        <button class="notif-tab-pill" data-cat="messages">Messages</button>
                        <button class="notif-tab-pill" data-cat="security">Security</button>
                        <button class="notif-tab-pill" data-cat="system">System</button>
                    </div>
                </div>

                <div class="notif-drawer-body" id="notifDrawerBody">
                    <div class="notif-empty-state">
                        <i class="fas fa-spinner fa-spin notif-empty-icon"></i>
                        <div class="notif-empty-title">Loading notifications...</div>
                    </div>
                </div>

                <div class="notif-drawer-footer">
                    <a href="${getNotificationCenterUrl()}" class="notif-view-all-link">
                        View Full Notification Center <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            `;

            document.body.appendChild(overlay);
            document.body.appendChild(drawer);
        }
    }

    function getNotificationCenterUrl() {
        const path = window.location.pathname;
        if (path.includes('/admin/')) return 'admin_notifications.php';
        if (path.includes('/user/')) return 'notifications.php';
        return 'user/notifications.php';
    }

    function getAppBaseUrl() {
        const path = window.location.pathname;
        if (path.includes('/user/')) return '..';
        if (path.includes('/admin/')) return '..';
        return '.';
    }

    function bindEvents() {
        // Toggle Bell Button
        document.addEventListener('click', function(e) {
            const bellBtn = e.target.closest('#notifBellBtn');
            if (bellBtn) {
                e.preventDefault();
                toggleDrawer(true);
            }
        });

        // Close Overlay & Close Button
        document.getElementById('notifDrawerOverlay')?.addEventListener('click', () => toggleDrawer(false));
        document.getElementById('btnCloseDrawer')?.addEventListener('click', () => toggleDrawer(false));

        // Mark All Read Button
        document.getElementById('btnMarkAllRead')?.addEventListener('click', function() {
            markAllAsRead();
        });

        // Search Input
        document.getElementById('notifSearchInput')?.addEventListener('input', function(e) {
            searchQuery = e.target.value;
            fetchNotifications();
        });

        // Filter Tabs
        document.getElementById('notifTabs')?.addEventListener('click', function(e) {
            const pill = e.target.closest('.notif-tab-pill');
            if (pill) {
                document.querySelectorAll('.notif-tab-pill').forEach(b => b.classList.remove('active'));
                pill.classList.add('active');
                activeCategory = pill.getAttribute('data-cat');
                fetchNotifications();
            }
        });
    }

    function toggleDrawer(open) {
        const overlay = document.getElementById('notifDrawerOverlay');
        const drawer = document.getElementById('notificationDrawer');
        if (!overlay || !drawer) return;

        if (open) {
            overlay.classList.add('active');
            drawer.classList.add('active');
            fetchNotifications();
        } else {
            overlay.classList.remove('active');
            drawer.classList.remove('active');
        }
    }

    function startPolling() {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(() => {
            fetchNotifications(true); // silent background fetch
        }, POLL_TIME);
    }

    function fetchNotifications(silent = false) {
        const isUnreadOnly = (activeCategory === 'unread');
        const cat = isUnreadOnly ? 'all' : activeCategory;

        const url = `${getAppBaseUrl()}/api/notifications.php?action=fetch&category=${encodeURIComponent(cat)}&unread_only=${isUnreadOnly ? 1 : 0}&search=${encodeURIComponent(searchQuery)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    updateBadge(data.unread_count);

                    // If new unread arrived and count increased, pulse bell & trigger toast
                    if (lastUnreadCount !== -1 && data.unread_count > lastUnreadCount) {
                        pulseBell();
                        const newest = data.items[0];
                        if (newest && !newest.is_read) {
                            showToast(newest.title, newest.message, newest.type || 'info');
                        }
                    }
                    lastUnreadCount = data.unread_count;

                    if (!silent || document.getElementById('notificationDrawer')?.classList.contains('active')) {
                        renderNotifications(data.items);
                    }
                }
            })
            .catch(err => console.error("Notif fetch error:", err));
    }

    function updateBadge(unreadCount) {
        const badge = document.getElementById('notifBadge');
        if (!badge) return;

        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function pulseBell() {
        const badge = document.getElementById('notifBadge');
        if (badge) {
            badge.classList.add('pulse');
            setTimeout(() => badge.classList.remove('pulse'), 3000);
        }
    }

    function renderNotifications(items) {
        const container = document.getElementById('notifDrawerBody');
        if (!container) return;

        if (!items || items.length === 0) {
            container.innerHTML = `
                <div class="notif-empty-state">
                    <i class="fas fa-bell-slash notif-empty-icon"></i>
                    <div class="notif-empty-title">No notifications yet</div>
                    <div class="notif-empty-desc">We'll alert you here when important updates arrive.</div>
                </div>
            `;
            return;
        }

        let html = '';
        items.forEach(item => {
            const isUnread = !item.is_read;
            const iconClass = getIconForCategory(item.category || item.type, item.icon);
            const colorClass = item.color || getColorForCategory(item.category);
            const targetUrl = item.url && item.url !== '#' ? resolveUrl(item.url) : 'javascript:void(0);';
            const safeTitle = escapeHtml(item.title || '').replace(/'/g, "\\'");
            const safeMsg = escapeHtml(item.message || '').replace(/'/g, "\\'");

            html += `
                <div class="notif-card ${isUnread ? 'unread' : ''}" data-id="${item.id}" onclick="NotificationApp.handleCardClick(event, ${item.id}, '${targetUrl}', '${safeTitle}', '${safeMsg}', '${item.time_ago || ''}', '${item.priority || 'medium'}', '${item.category || ''}', '${item.icon || ''}', '${item.color || ''}')">
                    <div class="notif-avatar-box">
                        <div class="notif-icon-badge ${colorClass}">
                            <i class="${iconClass}"></i>
                        </div>
                    </div>
                    <div class="notif-info">
                        <div class="notif-header-line">
                            <span class="notif-card-title">${escapeHtml(item.title)}</span>
                            <span class="notif-time">${item.time_ago || 'Just now'}</span>
                        </div>
                        <div class="notif-card-desc">${escapeHtml(item.message)}</div>
                        <div class="notif-footer-meta">
                            <span class="priority-tag ${item.priority || 'medium'}">${item.priority || 'medium'}</span>
                            <div class="notif-card-actions">
                                ${isUnread ? `<button class="notif-action-btn" title="Mark as read" onclick="event.stopPropagation(); NotificationApp.markSingleRead(${item.id})"><i class="fas fa-check"></i></button>` : ''}
                                <button class="notif-action-btn" title="Delete" onclick="event.stopPropagation(); NotificationApp.deleteNotif(${item.id})"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function resolveUrl(rawUrl) {
        if (!rawUrl || rawUrl === '#' || rawUrl === 'javascript:void(0);') return 'javascript:void(0);';
        if (rawUrl.startsWith('http://') || rawUrl.startsWith('https://')) return rawUrl;
        const path = window.location.pathname;
        const base = path.includes('/internship_project1') ? '/internship_project1/' : '/';
        return base + rawUrl.replace(/^\/+/, '');
    }

    function handleCardClick(event, id, url, title = '', message = '', timeAgo = '', priority = '', category = '', icon = '', color = '') {
        markSingleRead(id);
        openNotifDetails(id, title, message, timeAgo, priority, category, icon, color, url);
    }

    function openNotifDetails(id, title, message, timeAgo, priority, category, icon, color, url) {
        let modal = document.getElementById('notifDetailModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'notifDetailModal';
            modal.className = 'modal';
            modal.style.display = 'none';
            document.body.appendChild(modal);
        }

        const iconClass = getIconForCategory(category, icon);
        const colorClass = color || getColorForCategory(category);
        const finalUrl = resolveUrl(url);

        modal.innerHTML = `
            <div class="modal-content" style="max-width: 560px; padding: 2rem; border-radius: 20px; background: var(--theme-card); border: 1px solid var(--theme-border); box-shadow: 0 20px 50px rgba(0,0,0,0.4);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div class="notif-icon-badge ${colorClass}" style="width: 48px; height: 48px; font-size: 1.25rem; border-radius: 14px; display: flex; align-items: center; justify-content: center;">
                            <i class="${iconClass}"></i>
                        </div>
                        <div>
                            <span class="priority-tag ${priority || 'medium'}" style="text-transform: uppercase; font-size: 0.7rem; font-weight: 700;">${priority || 'medium'} PRIORITY</span>
                            <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--theme-text); margin-top: 0.2rem;">${escapeHtml(title)}</h3>
                        </div>
                    </div>
                    <button class="modal-close" onclick="document.getElementById('notifDetailModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--theme-text-secondary); cursor: pointer;">&times;</button>
                </div>

                <div style="background: rgba(0,0,0,0.15); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem; border: 1px solid var(--theme-border);">
                    <div style="font-size: 0.95rem; color: var(--theme-text); line-height: 1.6; white-space: pre-wrap;">${escapeHtml(message)}</div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--theme-border);">
                    <span><i class="fa-regular fa-clock"></i> Dispatched ${timeAgo || 'Just now'}</span>
                    <span><i class="fa-solid fa-layer-group"></i> Category: ${category || 'General'}</span>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button class="btn btn-secondary" onclick="document.getElementById('notifDetailModal').style.display='none'">Close</button>
                    ${finalUrl && finalUrl !== 'javascript:void(0);' ? `<a href="${finalUrl}" class="btn btn-primary"><i class="fa-solid fa-arrow-right-to-bracket"></i> Open Target Page</a>` : ''}
                </div>
            </div>
        `;

        modal.style.display = 'flex';
    }

    function markSingleRead(id) {
        // Optimistic UI update: decrement badge counter immediately
        const badge = document.getElementById('notifBadge');
        if (badge && badge.style.display !== 'none') {
            let currentVal = parseInt(badge.textContent) || 0;
            if (currentVal > 1) {
                badge.textContent = currentVal - 1;
            } else {
                badge.style.display = 'none';
            }
        }

        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('id', id);

        fetch(`${getAppBaseUrl()}/api/notifications.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                fetchNotifications(true);
            }
        });
    }

    function markAllAsRead() {
        const formData = new FormData();
        formData.append('action', 'mark_all_read');

        fetch(`${getAppBaseUrl()}/api/notifications.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('All Read', 'Marked all notifications as read', 'success');
                fetchNotifications();
            }
        });
    }

    function deleteNotif(id) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch(`${getAppBaseUrl()}/api/notifications.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                fetchNotifications();
            }
        });
    }

    function showToast(title, message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const icons = {
            success: 'fas fa-check-circle',
            info: 'fas fa-info-circle',
            warning: 'fas fa-exclamation-triangle',
            error: 'fas fa-times-circle'
        };

        const toast = document.createElement('div');
        toast.className = `toast-item ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="${icons[type] || icons.info}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${escapeHtml(title)}</div>
                <div class="toast-msg">${escapeHtml(message)}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('toast-exit');
            setTimeout(() => toast.remove(), 300);
        }, 4500);
    }

    function getIconForCategory(cat, fallback) {
        if (fallback && fallback.includes('fa-')) return fallback;
        switch (cat) {
            case 'mentorship': return 'fas fa-user-graduate';
            case 'jobs': case 'internships': return 'fas fa-briefcase';
            case 'events': return 'fas fa-calendar-alt';
            case 'announcements': return 'fas fa-bullhorn';
            case 'messages': case 'chat': return 'fas fa-comments';
            case 'security': return 'fas fa-shield-alt';
            default: return 'fas fa-bell';
        }
    }

    function getColorForCategory(cat) {
        switch (cat) {
            case 'mentorship': return 'purple';
            case 'jobs': return 'blue';
            case 'events': return 'emerald';
            case 'announcements': return 'amber';
            case 'messages': return 'indigo';
            case 'security': return 'rose';
            default: return 'indigo';
        }
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function clearAllNotifications() {
        if (!confirm("Are you sure you want to clear all notifications? This action cannot be undone.")) return;
        const formData = new FormData();
        formData.append('action', 'clear_all');

        fetch(`${getAppBaseUrl()}/api/notifications.php`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Cleared', 'All notifications cleared successfully', 'success');
                fetchNotifications();
            }
        });
    }

    return {
        init: init,
        showToast: showToast,
        markSingleRead: markSingleRead,
        markAllAsRead: markAllAsRead,
        clearAllNotifications: clearAllNotifications,
        deleteNotif: deleteNotif,
        handleCardClick: handleCardClick
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    NotificationApp.init();
});

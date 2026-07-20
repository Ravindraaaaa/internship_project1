document.addEventListener('DOMContentLoaded', function() {
    
    // ==================== 3. CHART.JS CONFIGURATIONS & REFERENCES ====================
    let userActivityChartInstance = null;
    let adminRegistrationsChartInstance = null;
    let adminJobsSectorChartInstance = null;

    function getChartThemeColors() {
        const isDark = !document.body.classList.contains('theme-glass-white');
        return {
            grid: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)',
            text: isDark ? '#94a3b8' : '#475569'
        };
    }

    // Initialize Chart configurations
    if (typeof Chart !== 'undefined') {
        const colors = getChartThemeColors();
        
        // Listen to theme adjustments and dynamically modify grids/labels
        const themeBtns = document.querySelectorAll('.theme-select-btn');
        themeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                setTimeout(() => {
                    const newColors = getChartThemeColors();
                    Chart.helpers.each(Chart.instances, function(instance) {
                        if (instance.options.scales) {
                            if (instance.options.scales.x) {
                                instance.options.scales.x.grid.color = newColors.grid;
                                instance.options.scales.x.ticks.color = newColors.text;
                            }
                            if (instance.options.scales.y) {
                                instance.options.scales.y.grid.color = newColors.grid;
                                instance.options.scales.y.ticks.color = newColors.text;
                            }
                        }
                        instance.update();
                    });
                }, 400);
            });
        });

        // --- USER ACTIVITY GRAPH (STUDENTS) ---
        const userActivityCanvas = document.getElementById('userActivityChart');
        if (userActivityCanvas) {
            userActivityChartInstance = new Chart(userActivityCanvas, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Logins / Activities',
                            data: [],
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Job Applications',
                            data: [],
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        x: { grid: { color: colors.grid }, ticks: { color: colors.text } },
                        y: { grid: { color: colors.grid }, ticks: { color: colors.text } }
                    }
                }
            });
        }

        // --- ADMIN REGISTRATIONS GRAPH ---
        const adminRegCanvas = document.getElementById('adminRegistrationsChart');
        if (adminRegCanvas) {
            adminRegistrationsChartInstance = new Chart(adminRegCanvas, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Alumni Registrations',
                            data: [],
                            backgroundColor: '#8b5cf6',
                            borderRadius: 6
                        },
                        {
                            label: 'Student Enrollments',
                            data: [],
                            backgroundColor: '#3b82f6',
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        x: { grid: { color: colors.grid }, ticks: { color: colors.text } },
                        y: { grid: { color: colors.grid }, ticks: { color: colors.text } }
                    }
                }
            });
        }

        // --- ADMIN JOBS SHARE BY COMPANY GRAPH ---
        const adminJobsSectorCanvas = document.getElementById('adminJobsSectorChart');
        if (adminJobsSectorCanvas) {
            adminJobsSectorChartInstance = new Chart(adminJobsSectorCanvas, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });
        }
    }

    // ==================== 4. LIVE DATABASE POLLING SYSTEM ====================
    
    // Helpers
    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' ' + 
               date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    }

    function formatTimeAgo(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        return date.toLocaleDateString();
    }

    function toggleChartPlaceholder(canvasId, hasData) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const parent = canvas.parentElement;
        let placeholder = parent.querySelector('.chart-placeholder');
        
        if (!hasData) {
            canvas.style.display = 'none';
            if (!placeholder) {
                placeholder = document.createElement('div');
                placeholder.className = 'chart-placeholder';
                placeholder.style.display = 'flex';
                placeholder.style.alignItems = 'center';
                placeholder.style.justifyContent = 'center';
                placeholder.style.height = '100%';
                placeholder.style.color = 'var(--theme-text-secondary)';
                placeholder.style.fontSize = '0.9rem';
                placeholder.innerText = 'No Data Available';
                parent.appendChild(placeholder);
            }
        } else {
            canvas.style.display = 'block';
            if (placeholder) {
                placeholder.remove();
            }
        }
    }

    // Polling function
    function pollLiveUpdates() {
        const pathPrefix = (document.querySelector('link[href*="assets/css/style.css"]')?.getAttribute('href') || '').split('assets/css/style.css')[0] || '';
        fetch(`${pathPrefix}api/live_updates.php`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // 1. Update Notification Bell Badge & Dropdown list
                    updateNotificationsUI(data);

                    // 2. Update Online Users Count & List
                    updateOnlineUsersUI(data);

                    // 3. Update Dashboard Counters
                    updateStatsCardsUI(data);

                    // 4. Update Charts
                    updateChartsUI(data);

                    // 5. Update Activity logs timeline (Admin only)
                    updateTimelineUI(data);

                    // 6. Update Messages Logs tab (Admin only)
                    updateMessagesUI(data);
                }
            })
            .catch(err => console.error("Error polling live updates:", err));
    }

    function updateMessagesUI(data) {
        const mentorshipTbody = document.getElementById('mentorship-logs-tbody');
        if (mentorshipTbody && data.mentorship_requests_logs) {
            let mentorshipHtml = '';
            data.mentorship_requests_logs.forEach(msg => {
                const statusClass = msg.status === 'accepted' ? 'approved' : (msg.status === 'pending' ? 'pending' : 'rejected');
                mentorshipHtml += `
                    <tr>
                        <td><strong>${escapeHTML(msg.student_name)}</strong></td>
                        <td><strong>${escapeHTML(msg.alumni_name)}</strong></td>
                        <td><span style="font-style: italic; font-size:0.85rem;">"${escapeHTML(msg.message)}"</span></td>
                        <td><span class="badge badge-${statusClass}">${escapeHTML(msg.status)}</span></td>
                        <td>${formatDate(msg.created_at)}</td>
                    </tr>
                `;
            });
            
            if (mentorshipTbody.getAttribute('data-raw') !== JSON.stringify(data.mentorship_requests_logs)) {
                mentorshipTbody.innerHTML = mentorshipHtml || '<tr><td colspan="5" style="text-align:center;color:var(--theme-text-secondary);">No mentorship requests logged.</td></tr>';
                mentorshipTbody.setAttribute('data-raw', JSON.stringify(data.mentorship_requests_logs));
                const table = document.getElementById('mentorship-logs-table');
                if (table) enhanceTable(table);
            }
        }

        const chatTbody = document.getElementById('chat-logs-tbody');
        if (chatTbody && data.chat_messages_logs) {
            let chatHtml = '';
            data.chat_messages_logs.forEach(msg => {
                chatHtml += `
                    <tr>
                        <td><strong>${escapeHTML(msg.sender_name)}</strong></td>
                        <td><strong>${escapeHTML(msg.receiver_name)}</strong></td>
                        <td><span style="font-size:0.85rem;">${escapeHTML(msg.message)}</span></td>
                        <td><span class="badge badge-${msg.is_read == 1 ? 'approved' : 'pending'}">${msg.is_read == 1 ? 'read' : 'unread'}</span></td>
                        <td>${formatDate(msg.created_at)}</td>
                    </tr>
                `;
            });

            if (chatTbody.getAttribute('data-raw') !== JSON.stringify(data.chat_messages_logs)) {
                chatTbody.innerHTML = chatHtml || '<tr><td colspan="5" style="text-align:center;color:var(--theme-text-secondary);">No direct chat messages logged.</td></tr>';
                chatTbody.setAttribute('data-raw', JSON.stringify(data.chat_messages_logs));
                const table = document.getElementById('chat-logs-table');
                if (table) enhanceTable(table);
            }
        }
    }

    window.switchMessageLogTab = function(tabType) {
        const mentorshipBtn = document.getElementById('btn-show-mentorship');
        const chatsBtn = document.getElementById('btn-show-chats');
        const mentorshipLogs = document.getElementById('mentorship-logs-container');
        const chatsLogs = document.getElementById('chats-logs-container');

        if (tabType === 'mentorship') {
            if (mentorshipBtn) {
                mentorshipBtn.style.background = 'var(--theme-accent-purple)';
                mentorshipBtn.style.color = '#ffffff';
            }
            if (chatsBtn) {
                chatsBtn.style.background = 'transparent';
                chatsBtn.style.color = 'var(--theme-text-secondary)';
            }
            if (mentorshipLogs) mentorshipLogs.style.display = 'block';
            if (chatsLogs) chatsLogs.style.display = 'none';
        } else {
            if (mentorshipBtn) {
                mentorshipBtn.style.background = 'transparent';
                mentorshipBtn.style.color = 'var(--theme-text-secondary)';
            }
            if (chatsBtn) {
                chatsBtn.style.background = 'var(--theme-accent-purple)';
                chatsBtn.style.color = '#ffffff';
            }
            if (mentorshipLogs) mentorshipLogs.style.display = 'none';
            if (chatsLogs) chatsLogs.style.display = 'block';
        }
    };

    // UI Updates
    function updateNotificationsUI(data) {
        const badge = document.querySelector('.top-nav-badge');
        if (badge) {
            badge.innerText = data.unread_notif_count;
            badge.style.display = data.unread_notif_count > 0 ? 'flex' : 'none';
        }
        
        const dropdownInfo = document.querySelector('#notif-dropdown-menu .dropdown-header-info p');
        if (dropdownInfo) {
            dropdownInfo.innerText = `You have ${data.unread_notif_count} new notice(s)`;
        }

        const dropdownMenu = document.getElementById('notif-dropdown-menu');
        if (dropdownMenu) {
            const header = dropdownMenu.querySelector('.dropdown-header-info');
            dropdownMenu.innerHTML = '';
            if (header) dropdownMenu.appendChild(header);

            if (data.notif_list && data.notif_list.length > 0) {
                data.notif_list.forEach(notif => {
                    const item = document.createElement('div');
                    item.className = 'notif-item';
                    item.style.cursor = 'pointer';
                    item.style.position = 'relative';
                    item.style.paddingRight = '2rem';
                    
                    let iconColor = 'var(--theme-accent-blue)';
                    if (notif.type === 'error') iconColor = 'var(--accent-danger)';
                    else if (notif.type === 'warning') iconColor = 'var(--accent-warning)';
                    else if (notif.type === 'success') iconColor = '#10b981';

                    item.innerHTML = `
                        <div class="notif-item-title">
                            <i class="fa-solid fa-circle-info" style="color: ${iconColor}; margin-right: 0.5rem;"></i>
                            <strong>${escapeHTML(notif.title)}</strong>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--theme-text-secondary); margin-top: 0.25rem;">
                            ${escapeHTML(notif.message)}
                        </div>
                        <div class="notif-item-time" style="font-size: 0.65rem; opacity: 0.6; margin-top: 0.25rem;">
                            ${formatDate(notif.created_at)}
                        </div>
                    `;

                    // Delete Button
                    const delBtn = document.createElement('span');
                    delBtn.className = 'notif-delete-btn';
                    delBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
                    delBtn.style.position = 'absolute';
                    delBtn.style.right = '12px';
                    delBtn.style.top = '12px';
                    delBtn.style.color = 'var(--accent-danger, #ef4444)';
                    delBtn.style.fontSize = '0.72rem';
                    delBtn.style.opacity = '0.4';
                    delBtn.style.transition = 'opacity 0.2s';
                    delBtn.style.cursor = 'pointer';
                    delBtn.title = 'Delete Notification';
                    
                    delBtn.addEventListener('mouseenter', () => delBtn.style.opacity = '1');
                    delBtn.addEventListener('mouseleave', () => delBtn.style.opacity = '0.4');
                    delBtn.addEventListener('click', (e) => {
                        e.stopPropagation(); // prevent mark_read click
                        if (confirm('Delete this notification?')) {
                            const pathPrefix = (document.querySelector('link[href*="assets/css/style.css"]')?.getAttribute('href') || '').split('assets/css/style.css')[0] || '';
                            const formData = new FormData();
                            formData.append('action', 'delete');
                            formData.append('id', notif.id);
                            fetch(`${pathPrefix}api/notifications.php`, {
                                method: 'POST',
                                body: formData
                            }).then(() => pollLiveUpdates());
                        }
                    });
                    item.appendChild(delBtn);

                    item.addEventListener('click', () => {
                        const pathPrefix = (document.querySelector('link[href*="assets/css/style.css"]')?.getAttribute('href') || '').split('assets/css/style.css')[0] || '';
                        const formData = new FormData();
                        formData.append('action', 'mark_read');
                        formData.append('id', notif.id);
                        fetch(`${pathPrefix}api/notifications.php`, {
                            method: 'POST',
                            body: formData
                        }).then(() => pollLiveUpdates());
                    });
                    dropdownMenu.appendChild(item);
                });
            } else {
                const noData = document.createElement('div');
                noData.className = 'notif-item';
                noData.style.padding = '1.5rem';
                noData.style.textAlign = 'center';
                noData.style.color = 'var(--theme-text-secondary)';
                noData.innerText = 'No Data Available';
                dropdownMenu.appendChild(noData);
            }
        }
    }

    function updateOnlineUsersUI(data) {
        const countEls = document.querySelectorAll('#online-users-count');
        countEls.forEach(el => {
            el.innerText = data.online_users_count;
        });

        const container = document.getElementById('online-users-container');
        if (!container) return;

        container.innerHTML = '';
        if (data.online_users_list && data.online_users_list.length > 0) {
            data.online_users_list.forEach(user => {
                const item = document.createElement('div');
                item.style.display = 'flex';
                item.style.alignItems = 'center';
                item.style.justifyContent = 'space-between';
                item.style.padding = '0.5rem 0.75rem';
                item.style.background = 'rgba(255,255,255,0.02)';
                item.style.border = '1px solid var(--theme-border)';
                item.style.borderRadius = '6px';
                
                let roleColor = 'var(--theme-accent-blue)';
                if (user.role === 'admin') roleColor = 'var(--accent-danger)';
                else if (user.role === 'alumni') roleColor = 'var(--theme-accent-purple)';

                item.innerHTML = `
                    <div style="display:flex; align-items:center; gap:0.5rem; width: 100%; justify-content: space-between;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; box-shadow: 0 0 6px #10b981;"></span>
                            <strong>${escapeHTML(user.name)}</strong>
                        </div>
                        <span style="font-size:0.68rem; color:${roleColor}; text-transform:uppercase; font-weight:600;">${user.role}</span>
                    </div>
                `;
                container.appendChild(item);
            });
        } else {
            const noData = document.createElement('div');
            noData.style.textAlign = 'center';
            noData.style.padding = '1.5rem';
            noData.style.color = 'var(--theme-text-secondary)';
            noData.innerText = 'No Data Available';
            container.appendChild(noData);
        }
    }

    function updateStatsCardsUI(data) {
        document.querySelectorAll('.stat-card-view').forEach(card => {
            const labelEl = card.querySelector('.stat-card-lbl');
            const valEl = card.querySelector('.stat-card-val');
            if (labelEl && valEl) {
                const label = labelEl.innerText.trim();
                
                if (data.admin_stats) {
                    if (label === 'Total Users') {
                        valEl.innerText = data.admin_stats.users;
                    } else if (label === 'Pending Approvals') {
                        valEl.innerText = data.admin_stats.pending;
                    } else if (label === 'Active Referrals') {
                        valEl.innerText = data.admin_stats.jobs;
                    } else if (label === 'Scheduled Events') {
                        valEl.innerText = data.admin_stats.events;
                    }
                } else if (data.user_stats) {
                    if (label === 'Referrals Posted') {
                        valEl.innerText = data.user_stats.jobs_posted;
                    } else if (label === 'Mentoring Requests' || label === 'Active Mentors') {
                        valEl.innerText = data.user_stats.mentorship_requests || data.user_stats.active_mentors;
                    } else if (label === 'RSVPs Reserved') {
                        valEl.innerText = data.user_stats.rsvps_reserved;
                    }
                }
            }
        });
    }

    function updateChartsUI(data) {
        // Line Chart (Student view)
        if (userActivityChartInstance && data.chart_student_activity) {
            const hasData = data.chart_student_activity.some(item => item.applications > 0 || item.activities > 0);
            toggleChartPlaceholder('userActivityChart', hasData);

            if (hasData) {
                userActivityChartInstance.data.labels = data.chart_student_activity.map(item => item.month);
                userActivityChartInstance.data.datasets[0].data = data.chart_student_activity.map(item => item.activities);
                userActivityChartInstance.data.datasets[1].data = data.chart_student_activity.map(item => item.applications);
                userActivityChartInstance.update();
            }
        }

        // Bar Chart (Admin view)
        if (adminRegistrationsChartInstance && data.chart_registrations) {
            const hasData = data.chart_registrations.some(item => item.alumni > 0 || item.students > 0);
            toggleChartPlaceholder('adminRegistrationsChart', hasData);

            if (hasData) {
                adminRegistrationsChartInstance.data.labels = data.chart_registrations.map(item => item.month);
                adminRegistrationsChartInstance.data.datasets[0].data = data.chart_registrations.map(item => item.alumni);
                adminRegistrationsChartInstance.data.datasets[1].data = data.chart_registrations.map(item => item.students);
                adminRegistrationsChartInstance.update();
            }
        }

        // Doughnut Chart (Admin view)
        if (adminJobsSectorChartInstance && data.chart_jobs_share) {
            const hasData = data.chart_jobs_share.length > 0;
            toggleChartPlaceholder('adminJobsSectorChart', hasData);

            if (hasData) {
                adminJobsSectorChartInstance.data.labels = data.chart_jobs_share.map(item => item.company);
                adminJobsSectorChartInstance.data.datasets[0].data = data.chart_jobs_share.map(item => item.qty);
                adminJobsSectorChartInstance.update();
            }
        }
    }

    function updateTimelineUI(data) {
        const timeline = document.getElementById('system-activity-timeline');
        if (!timeline) return;

        timeline.innerHTML = '';
        if (data.activity_timeline && data.activity_timeline.length > 0) {
            data.activity_timeline.forEach(log => {
                const item = document.createElement('li');
                item.className = 'timeline-item';
                item.innerHTML = `
                    <span class="timeline-marker success"></span>
                    <div class="timeline-time">${formatTimeAgo(log.created_at)}</div>
                    <div class="timeline-title">${escapeHTML(log.action)}</div>
                    <div class="timeline-desc">${escapeHTML(log.details || '')} by ${escapeHTML(log.user_name || 'System')}</div>
                `;
                timeline.appendChild(item);
            });
        } else {
            const noData = document.createElement('div');
            noData.style.textAlign = 'center';
            noData.style.padding = '1.5rem';
            noData.style.color = 'var(--theme-text-secondary)';
            noData.innerText = 'No Data Available';
            timeline.appendChild(noData);
        }
    }

    // Initial skeleton loaders for sidebar components
    const timeline = document.getElementById('system-activity-timeline');
    if (timeline) {
        timeline.innerHTML = `
            <div style="display:flex; flex-direction:column; gap:0.8rem; width:100%; padding:0.5rem;">
                <div class="skeleton-box" style="height:12px; width:45%;"></div>
                <div class="skeleton-box" style="height:16px; width:85%;"></div>
                <div class="skeleton-box" style="height:12px; width:65%;"></div>
            </div>
        `;
    }
    const onlineUsers = document.getElementById('online-users-container');
    if (onlineUsers) {
        onlineUsers.innerHTML = `
            <div style="display:flex; flex-direction:column; gap:0.8rem; width:100%;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="skeleton-box" style="height:14px; width:50%;"></div>
                    <div class="skeleton-box" style="height:14px; width:20%;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="skeleton-box" style="height:14px; width:60%;"></div>
                    <div class="skeleton-box" style="height:14px; width:15%;"></div>
                </div>
            </div>
        `;
    }

    // Animated Counters for metric values
    function animateCounters() {
        document.querySelectorAll('.stat-card-val').forEach(el => {
            const target = parseInt(el.innerText.replace(/\D/g, '')) || 0;
            if (target === 0) return;
            let current = 0;
            const duration = 1000; // ms
            const stepTime = 15;
            const steps = duration / stepTime;
            const increment = Math.ceil(target / steps);
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    el.innerText = target;
                    clearInterval(timer);
                } else {
                    el.innerText = current;
                }
            }, stepTime);
        });
    }

    // Premium Client-side Table Enhancer (Search, Filters, Excel/PDF Exports, Pagination)
    function enhanceTable(tableEl, options = {}) {
        if (!tableEl) return;
        
        const existingToolbar = tableEl.previousElementSibling;
        if (existingToolbar && existingToolbar.classList.contains('table-toolbar')) {
            existingToolbar.remove();
        }
        const existingPagination = tableEl.nextElementSibling;
        if (existingPagination && existingPagination.classList.contains('table-pagination')) {
            existingPagination.remove();
        }
        
        const tbody = tableEl.querySelector('tbody');
        if (!tbody) return;
        const allRows = Array.from(tbody.querySelectorAll('tr'));
        if (allRows.length === 0) return;

        let currentPage = 1;
        let pageSize = options.pageSize || 7;
        let filteredRows = [...allRows];
        let searchQuery = '';

        // Toolbar Container
        const toolbar = document.createElement('div');
        toolbar.className = 'table-toolbar card-glass';
        toolbar.style.display = 'flex';
        toolbar.style.justifyContent = 'space-between';
        toolbar.style.alignItems = 'center';
        toolbar.style.flexWrap = 'wrap';
        toolbar.style.gap = '1rem';
        toolbar.style.padding = '0.75rem 1rem';
        toolbar.style.marginBottom = '0.75rem';
        toolbar.style.borderRadius = '8px';
        toolbar.style.border = '1px solid var(--theme-border)';

        // Search Input
        const searchContainer = document.createElement('div');
        searchContainer.style.display = 'flex';
        searchContainer.style.alignItems = 'center';
        searchContainer.style.position = 'relative';
        searchContainer.style.flexGrow = '1';
        searchContainer.style.maxWidth = '280px';

        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'input-glass';
        searchInput.placeholder = 'Filter results...';
        searchInput.style.padding = '0.45rem 1rem 0.45rem 2.2rem';
        searchInput.style.fontSize = '0.82rem';

        const searchIcon = document.createElement('i');
        searchIcon.className = 'fa-solid fa-magnifying-glass';
        searchIcon.style.position = 'absolute';
        searchIcon.style.left = '0.8rem';
        searchIcon.style.color = 'var(--theme-text-secondary)';
        searchIcon.style.fontSize = '0.82rem';

        searchContainer.appendChild(searchIcon);
        searchContainer.appendChild(searchInput);

        // Exports Buttons
        const exportContainer = document.createElement('div');
        exportContainer.style.display = 'flex';
        exportContainer.style.gap = '0.5rem';

        const excelBtn = document.createElement('button');
        excelBtn.className = 'btn btn-secondary';
        excelBtn.style.padding = '0.45rem 0.75rem';
        excelBtn.style.fontSize = '0.78rem';
        excelBtn.style.borderRadius = '6px';
        excelBtn.innerHTML = '<i class="fa-solid fa-file-excel" style="color: #10b981; margin-right: 0.25rem;"></i> Excel';

        const pdfBtn = document.createElement('button');
        pdfBtn.className = 'btn btn-secondary';
        pdfBtn.style.padding = '0.45rem 0.75rem';
        pdfBtn.style.fontSize = '0.78rem';
        pdfBtn.style.borderRadius = '6px';
        pdfBtn.innerHTML = '<i class="fa-solid fa-file-pdf" style="color: #ef4444; margin-right: 0.25rem;"></i> PDF';

        exportContainer.appendChild(excelBtn);
        exportContainer.appendChild(pdfBtn);

        toolbar.appendChild(searchContainer);
        toolbar.appendChild(exportContainer);

        // Insert toolbar before the table
        tableEl.parentNode.insertBefore(toolbar, tableEl);

        // Pagination controls container
        const pagContainer = document.createElement('div');
        pagContainer.className = 'table-pagination';
        pagContainer.style.display = 'flex';
        pagContainer.style.justifyContent = 'space-between';
        pagContainer.style.alignItems = 'center';
        pagContainer.style.padding = '0.75rem 0';
        pagContainer.style.flexWrap = 'wrap';
        pagContainer.style.gap = '1rem';

        const pagInfo = document.createElement('span');
        pagInfo.style.fontSize = '0.8rem';
        pagInfo.style.color = 'var(--theme-text-secondary)';

        const pagControls = document.createElement('div');
        pagControls.style.display = 'flex';
        pagControls.style.gap = '0.25rem';

        pagContainer.appendChild(pagInfo);
        pagContainer.appendChild(pagControls);

        // Insert pagination after the table
        tableEl.parentNode.insertBefore(pagContainer, tableEl.nextSibling);

        // Search Input listener
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase();
            currentPage = 1;
            applyFilterAndRender();
        });

        // Excel Export
        excelBtn.addEventListener('click', (e) => {
            e.preventDefault();
            let csvContent = "data:text/csv;charset=utf-8,";
            const headers = Array.from(tableEl.querySelectorAll('thead th'))
                .map(th => `"${th.innerText.replace(/"/g, '""')}"`)
                .join(",");
            csvContent += headers + "\r\n";

            filteredRows.forEach(row => {
                const rowData = Array.from(row.querySelectorAll('td'))
                    .map(td => `"${td.innerText.trim().replace(/"/g, '""')}"`)
                    .join(",");
                csvContent += rowData + "\r\n";
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `AlumniNet_Export_${Date.now()}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // PDF Export
        pdfBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const printWindow = window.open('', '_blank');
            const themeStyles = document.querySelector('link[href*="assets/css/style.css"]')?.outerHTML || '';
            
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Table Export</title>
                        ${themeStyles}
                        <style>
                            body { background: #ffffff !important; color: #111827 !important; padding: 2rem; font-family: system-ui, sans-serif; }
                            table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
                            th, td { border: 1px solid #e5e7eb; padding: 0.75rem; text-align: left; font-size: 0.85rem; }
                            th { background: #f3f4f6; font-weight: bold; }
                            .btn, form, input, select { display: none !important; }
                        </style>
                    </head>
                    <body>
                        <h2>AlumniNet Exported Records</h2>
                        <p style="color: #6b7280; font-size: 0.85rem;">Generated on: ${new Date().toLocaleString()}</p>
                        <table>
                            <thead>
                                ${tableEl.querySelector('thead').innerHTML}
                            </thead>
                            <tbody>
                                ${filteredRows.map(r => r.outerHTML).join('')}
                            </tbody>
                        </table>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 800);
        });

        function applyFilterAndRender() {
            filteredRows = allRows.filter(row => {
                const text = row.innerText.toLowerCase();
                return text.includes(searchQuery);
            });

            const totalRows = filteredRows.length;
            const totalPages = Math.ceil(totalRows / pageSize);

            if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

            allRows.forEach(r => r.style.display = 'none');

            const start = (currentPage - 1) * pageSize;
            const end = Math.min(start + pageSize, totalRows);

            for (let i = start; i < end; i++) {
                filteredRows[i].style.display = '';
            }

            pagInfo.innerText = totalRows > 0 
                ? `Showing ${start + 1} to ${end} of ${totalRows} entries` 
                : 'No matching entries found';

            pagControls.innerHTML = '';
            if (totalPages > 1) {
                const prev = document.createElement('button');
                prev.className = `btn btn-secondary ${currentPage === 1 ? 'disabled' : ''}`;
                prev.innerText = 'Prev';
                prev.disabled = currentPage === 1;
                prev.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (currentPage > 1) {
                        currentPage--;
                        applyFilterAndRender();
                    }
                });
                pagControls.appendChild(prev);

                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.className = `btn ${currentPage === i ? 'btn-primary' : 'btn-secondary'}`;
                    btn.innerText = i;
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        currentPage = i;
                        applyFilterAndRender();
                    });
                    pagControls.appendChild(btn);
                }

                const next = document.createElement('button');
                next.className = `btn btn-secondary ${currentPage === totalPages ? 'disabled' : ''}`;
                next.innerText = 'Next';
                next.disabled = currentPage === totalPages;
                next.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (currentPage < totalPages) {
                        currentPage++;
                        applyFilterAndRender();
                    }
                });
                pagControls.appendChild(next);
            }
        }

        applyFilterAndRender();
    }

    // Auto-enhance active page tables
    document.querySelectorAll('.custom-table').forEach(table => {
        enhanceTable(table);
    });

    // Run animations
    animateCounters();

    // Start Live Update Polling
    pollLiveUpdates();
    setInterval(pollLiveUpdates, 10000); // refresh every 10 seconds
});


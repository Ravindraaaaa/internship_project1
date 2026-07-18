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
        const pathPrefix = document.querySelector('link[href*="assets/css/style.css"]')?.getAttribute('href')?.replace('assets/css/style.css', '') || '';
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
                }
            })
            .catch(err => console.error("Error polling live updates:", err));
    }

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
                    item.addEventListener('click', () => {
                        const pathPrefix = document.querySelector('link[href*="assets/css/style.css"]')?.getAttribute('href')?.replace('assets/css/style.css', '') || '';
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

    // Start Live Update Polling
    pollLiveUpdates();
    setInterval(pollLiveUpdates, 10000); // refresh every 10 seconds
});

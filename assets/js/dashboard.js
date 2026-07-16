document.addEventListener('DOMContentLoaded', function() {
    
    // ==================== 1. SIDEBAR TOGGLING ====================
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            
            // Adjust toggle icon class
            const icon = sidebarToggle.querySelector('i');
            if (icon) {
                if (sidebar.classList.contains('collapsed')) {
                    icon.className = 'fa-solid fa-chevron-right';
                } else {
                    icon.className = 'fa-solid fa-chevron-left';
                }
            }
            
            // Trigger resize for charts to fill layout width
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 300);
        });
    }

    const mobileSidebarBtn = document.getElementById('mobile-sidebar-toggle');
    if (mobileSidebarBtn && sidebar) {
        mobileSidebarBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // ==================== 2. NAV DROPDOWNS ====================
    function setupDropdown(toggleId, menuId) {
        const toggle = document.getElementById(toggleId);
        const menu = document.getElementById(menuId);
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Close other open dropdowns
                document.querySelectorAll('.nav-dropdown-menu').forEach(m => {
                    if (m.id !== menuId) m.classList.remove('show');
                });
                
                menu.classList.toggle('show');
            });
        }
    }

    setupDropdown('notif-bell-toggle', 'notif-dropdown-menu');
    setupDropdown('profile-avatar-toggle', 'profile-dropdown-menu');

    document.addEventListener('click', function() {
        document.querySelectorAll('.nav-dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    });

    // ==================== 3. GSAP DASHBOARD STATS COUNT UP ====================
    const counters = document.querySelectorAll('.stat-card-val');
    counters.forEach(counter => {
        const target = parseInt(counter.textContent, 10);
        if (!isNaN(target) && target > 0) {
            counter.textContent = '0';
            gsap.to(counter, {
                innerText: target,
                duration: 2,
                snap: { innerText: 1 },
                ease: 'power3.out'
            });
        }
    });

    // ==================== 4. CHART.JS CONFIGURATIONS ====================
    function getChartThemeColors() {
        const isDark = !document.body.classList.contains('theme-glass-white');
        return {
            grid: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)',
            text: isDark ? '#94a3b8' : '#475569'
        };
    }

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

        // --- USER ACTIVITY GRAPH ---
        const userActivityCanvas = document.getElementById('userActivityChart');
        if (userActivityCanvas) {
            new Chart(userActivityCanvas, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                    datasets: [
                        {
                            label: 'Profile Visits',
                            data: [65, 84, 120, 95, 140, 185, 210],
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Job Applications',
                            data: [5, 8, 12, 10, 18, 15, 24],
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
            new Chart(adminRegCanvas, {
                type: 'bar',
                data: {
                    labels: ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                    datasets: [
                        {
                            label: 'Alumni Registrations',
                            data: [45, 60, 55, 70, 85, 110],
                            backgroundColor: '#8b5cf6',
                            borderRadius: 6
                        },
                        {
                            label: 'Student Enrollments',
                            data: [60, 80, 75, 95, 120, 150],
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

        const adminJobsSectorCanvas = document.getElementById('adminJobsSectorChart');
        if (adminJobsSectorCanvas) {
            new Chart(adminJobsSectorCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Technology', 'Fintech', 'Healthcare', 'Consulting', 'Education'],
                    datasets: [{
                        data: [45, 25, 15, 10, 5],
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
});

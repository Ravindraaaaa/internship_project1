    <!-- ==================== BACK TO TOP FAB ==================== -->
    <button class="back-to-top" id="back-to-top" title="Back to Top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>


    <!-- Main JavaScript Core Asset -->
    <script src="<?php echo $path_prefix; ?>assets/js/main.js?v=<?php echo time(); ?>"></script>
    
    <!-- Fade in page load GSAP utility -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // GSAP page entrance reveal
            gsap.from("body", {
                opacity: 0,
                duration: 0.8,
                ease: "power2.out"
            });

            // Initialize Lucide Icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>

    <?php 
    $show_chatbot = false;
    if (is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student') {
        $show_chatbot = true;
    }
    ?>
    <?php if (is_logged_in()): ?>
        <!-- ==================== ENTERPRISE WIDGETS ==================== -->
        
        <?php if ($show_chatbot): ?>
            <!-- 1. AI Assistant Floating Widget -->
            <button class="ai-chat-fab" id="ai-chat-fab" onclick="toggleAIChat()" title="AlumniNet Intelligent Assistant">
                <i class="fa-solid fa-robot"></i>
            </button>

            <div class="ai-chat-window" id="ai-chat-window">
                <div class="ai-chat-header">
                    <div style="display:flex; align-items:center; gap:0.5rem; color:#ffffff;">
                        <i class="fa-solid fa-robot" style="color:var(--theme-accent-blue);"></i>
                        <div>
                            <h4 style="font-size:0.9rem; font-weight:700;">AI Chat Assistant</h4>
                            <span style="font-size:0.7rem; color:var(--theme-text-secondary);">Enterprise Help Desk</span>
                        </div>
                    </div>
                    <div style="display:flex; gap:0.4rem; align-items:center;">
                        <button onclick="clearAIChat()" style="background:none; border:none; color:var(--theme-text-secondary); cursor:pointer; font-size:0.8rem;" title="Clear History"><i class="fa-solid fa-trash-can"></i></button>
                        <button onclick="toggleAIChat()" style="background:none; border:none; color:#ffffff; cursor:pointer; font-size:1.15rem; line-height:1;">&times;</button>
                    </div>
                </div>

                <div class="ai-chat-body" id="ai-chat-body">
                    <!-- Message bubbles render here -->
                </div>

                <!-- Suggested chips -->
                <div class="ai-chat-suggested">
                    <button class="ai-suggested-btn" onclick="sendSuggestedQuery('Are there any active jobs?')">Jobs board?</button>
                    <button class="ai-suggested-btn" onclick="sendSuggestedQuery('Tell me about upcoming events.')">Upcoming events?</button>
                    <button class="ai-suggested-btn" onclick="sendSuggestedQuery('What is my profile score?')">Resume score?</button>
                    <button class="ai-suggested-btn" onclick="sendSuggestedQuery('How can I contact mentors?')">Mentorship?</button>
                </div>

                <div class="ai-chat-input-area">
                    <input type="text" id="ai-chat-input" class="input-glass" style="flex-grow:1; font-size:0.85rem; padding:0.45rem 0.75rem;" placeholder="Ask about events, jobs, resume tips..." onkeydown="if(event.key==='Enter') sendAIChatMessage()">
                    <button onclick="sendAIChatMessage()" class="btn btn-primary" style="padding:0.45rem 0.8rem; font-size:0.85rem;"><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. Global Search Overlay Modal -->
        <div class="search-modal-overlay" id="search-modal-overlay" onclick="if(event.target===this) toggleSearchModal(false)">
            <div class="search-modal-card">
                <div class="search-modal-header">
                    <i class="fa-solid fa-magnifying-glass" style="color:var(--theme-text-secondary);"></i>
                    <input type="text" id="global-search-input" class="input-glass" style="flex-grow:1; font-size:0.95rem; border:none; background:none; padding:0.5rem;" placeholder="Search users, jobs, alumni, companies..." onkeyup="performGlobalSearch(this.value)">
                    <button onclick="toggleSearchModal(false)" style="background:none; border:none; color:var(--theme-text-secondary); cursor:pointer; font-size:1.35rem; line-height:1;">&times;</button>
                </div>
                <div class="search-modal-results" id="search-modal-results">
                    <div style="text-align:center; color:var(--theme-text-secondary); font-size:0.85rem; padding:2rem 0;">
                        <i class="fa-solid fa-keyboard" style="font-size:1.5rem; margin-bottom:0.5rem; display:block;"></i>
                        Type at least 2 characters to trigger dynamic indexing...
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Scripts Integration -->
        <script>
            <?php if ($show_chatbot): ?>
            // --- AI ASSISTANT WIDGET LOGIC ---
            let aiChatLoaded = false;
            
            function toggleAIChat() {
                const win = document.getElementById('ai-chat-window');
                win.classList.toggle('active');
                if (win.classList.contains('active')) {
                    document.getElementById('ai-chat-input').focus();
                    if (!aiChatLoaded) {
                        loadAIChatHistory();
                    }
                }
            }

            function loadAIChatHistory() {
                const body = document.getElementById('ai-chat-body');
                body.innerHTML = '<div style="text-align:center;font-size:0.8rem;color:var(--theme-text-secondary);padding:1rem;"><i class="fa-solid fa-spinner fa-spin"></i> Syncing memory logs...</div>';
                
                fetch('<?php echo $path_prefix; ?>api/ai_chat.php?action=history')
                .then(res => res.json())
                .then(data => {
                    body.innerHTML = '';
                    if (data.status === 'success') {
                        if (data.history && data.history.length > 0) {
                            data.history.forEach(chat => {
                                appendMsgBubble(chat.query, 'user');
                                appendMsgBubble(chat.response, 'assistant');
                            });
                        } else {
                            // Default welcome bubble
                            appendMsgBubble("Hello! I am the AlumniNet Intelligent Assistant. Ask me anything about placement events, active jobs, matching mentors or profile details!", 'assistant');
                        }
                        aiChatLoaded = true;
                    }
                    scrollToBottom('ai-chat-body');
                });
            }

            function clearAIChat() {
                if (confirm('Clear assistant conversation logs?')) {
                    fetch('<?php echo $path_prefix; ?>api/ai_chat.php?action=clear', { method: 'POST' })
                    .then(res => res.json())
                    .then(() => {
                        document.getElementById('ai-chat-body').innerHTML = '';
                        appendMsgBubble("Conversation cleared. How can I help you today?", 'assistant');
                    });
                }
            }

            function sendAIChatMessage() {
                const input = document.getElementById('ai-chat-input');
                const query = input.value.trim();
                if (!query) return;
                
                input.value = '';
                appendMsgBubble(query, 'user');
                scrollToBottom('ai-chat-body');
                
                // Render typing indicator
                const body = document.getElementById('ai-chat-body');
                const indicator = document.createElement('div');
                indicator.id = 'ai-typing-indicator';
                indicator.className = 'ai-chat-msg assistant';
                indicator.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> AI is thinking...';
                body.appendChild(indicator);
                scrollToBottom('ai-chat-body');
                
                const formData = new FormData();
                formData.append('message', query);
                
                fetch('<?php echo $path_prefix; ?>api/ai_chat.php?action=message', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    indicator.remove();
                    if (data.status === 'success') {
                        appendMsgBubble(data.response, 'assistant');
                    } else {
                        appendMsgBubble("Apologies, I encountered an internal communication timeout: " + (data.error || 'Server error'), 'assistant');
                    }
                    scrollToBottom('ai-chat-body');
                })
                .catch(() => {
                    indicator.remove();
                    appendMsgBubble("Communication lost. Please check connection.", 'assistant');
                    scrollToBottom('ai-chat-body');
                });
            }

            function sendSuggestedQuery(text) {
                document.getElementById('ai-chat-input').value = text;
                sendAIChatMessage();
            }

            function appendMsgBubble(text, sender) {
                const body = document.getElementById('ai-chat-body');
                const div = document.createElement('div');
                div.className = `ai-chat-msg ${sender}`;
                
                // Simple parser for markdown-like bold/italic/links
                let formatted = text
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" style="color:var(--theme-accent-blue);font-weight:600;">$1</a>')
                    .replace(/\n/g, '<br>');
                
                div.innerHTML = formatted;
                
                // Add copy buttons for assistant messages
                if (sender === 'assistant') {
                    const btn = document.createElement('button');
                    btn.innerHTML = '<i class="fa-solid fa-copy"></i>';
                    btn.style = 'background:none; border:none; color:var(--theme-text-secondary); cursor:pointer; font-size:0.75rem; margin-top:0.4rem; float:right; display:block;';
                    btn.title = "Copy message";
                    btn.onclick = () => {
                        navigator.clipboard.writeText(text);
                        window.showToast ? window.showToast('Copied to clipboard!', 'info') : alert('Copied!');
                    };
                    div.appendChild(btn);
                }
                
                body.appendChild(div);
            }

            function scrollToBottom(id) {
                const el = document.getElementById(id);
                el.scrollTop = el.scrollHeight;
            }
            <?php endif; ?>

            // --- GLOBAL INSTANT SEARCH LOGIC ---
            function toggleSearchModal(show) {
                const overlay = document.getElementById('search-modal-overlay');
                const input = document.getElementById('global-search-input');
                if (show) {
                    overlay.classList.add('active');
                    input.focus();
                } else {
                    overlay.classList.remove('active');
                    input.value = '';
                }
            }

            // Wire input searches to trigger the overlay modal
            document.addEventListener('DOMContentLoaded', () => {
                const navSearch = document.querySelector('.top-nav-search input');
                if (navSearch) {
                    navSearch.addEventListener('focus', (e) => {
                        e.preventDefault();
                        navSearch.blur();
                        toggleSearchModal(true);
                    });
                }
                
                // ESC Key to close search modal
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        toggleSearchModal(false);
                        const win = document.getElementById('ai-chat-window');
                        if (win) win.classList.remove('active');
                    }
                });
                
                // Notification Sync triggers
                syncNotificationCount();
                setInterval(syncNotificationCount, 15000); // sync every 15s
                
                // Wire notification dropdown toggle to load lists
                const bell = document.getElementById('notif-bell-toggle');
                if (bell) {
                    bell.addEventListener('click', loadRecentNotificationsList);
                }
            });

            function performGlobalSearch(q) {
                const results = document.getElementById('search-modal-results');
                if (q.trim().length < 2) {
                    results.innerHTML = `
                        <div style="text-align:center; color:var(--theme-text-secondary); font-size:0.85rem; padding:2rem 0;">
                            <i class="fa-solid fa-keyboard" style="font-size:1.5rem; margin-bottom:0.5rem; display:block;"></i>
                            Type at least 2 characters to trigger dynamic indexing...
                        </div>`;
                    return;
                }
                
                results.innerHTML = '<div style="text-align:center; padding:2rem;"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:1.5rem;"></i> Searching system nodes...</div>';
                
                fetch('<?php echo $path_prefix; ?>api/search.php?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(data => {
                    results.innerHTML = '';
                    if (data.status === 'success') {
                        let total = 0;
                        
                        // 1. Render Users
                        if (data.users && data.users.length > 0) {
                            total += data.users.length;
                            const div = document.createElement('div');
                            div.innerHTML = '<div class="search-result-group-title"><i class="fa-solid fa-user-graduate"></i> Accounts & Members</div>';
                            data.users.forEach(u => {
                                const path = u.role === 'admin' ? '<?php echo $path_prefix; ?>admin/dashboard.php' : '<?php echo $path_prefix; ?>user/alumni.php?search=' + encodeURIComponent(u.name);
                                div.innerHTML += `<a href="${path}" class="search-result-item">
                                    <div>
                                        <strong>${u.name}</strong>
                                        <p style="font-size:0.75rem;color:var(--theme-text-secondary);margin:0.15rem 0;">${u.email}</p>
                                    </div>
                                    <span style="font-size:0.7rem;background:rgba(255,255,255,0.05);padding:0.1rem 0.35rem;border-radius:3px;text-transform:uppercase;">${u.role}</span>
                                </a>`;
                            });
                            results.appendChild(div);
                        }
                        
                        // 2. Render Jobs
                        if (data.jobs && data.jobs.length > 0) {
                            total += data.jobs.length;
                            const div = document.createElement('div');
                            div.innerHTML = '<div class="search-result-group-title" style="margin-top:1rem;"><i class="fa-solid fa-briefcase"></i> Placements & Internships</div>';
                            data.jobs.forEach(j => {
                                div.innerHTML += `<a href="<?php echo $path_prefix; ?>user/jobs.php" class="search-result-item">
                                    <div>
                                        <strong>${j.title}</strong>
                                        <p style="font-size:0.75rem;color:var(--theme-text-secondary);margin:0.15rem 0;">${j.company} | ${j.location}</p>
                                    </div>
                                    <span style="font-size:0.7rem;background:rgba(59,130,246,0.15);color:var(--theme-accent-blue);padding:0.1rem 0.35rem;border-radius:3px;text-transform:uppercase;">${j.type}</span>
                                </a>`;
                            });
                            results.appendChild(div);
                        }

                        // 3. Render Events
                        if (data.events && data.events.length > 0) {
                            total += data.events.length;
                            const div = document.createElement('div');
                            div.innerHTML = '<div class="search-result-group-title" style="margin-top:1rem;"><i class="fa-solid fa-calendar-days"></i> Upcoming Events</div>';
                            data.events.forEach(e => {
                                div.innerHTML += `<a href="<?php echo $path_prefix; ?>user/events.php" class="search-result-item">
                                    <div>
                                        <strong>${e.title}</strong>
                                        <p style="font-size:0.75rem;color:var(--theme-text-secondary);margin:0.15rem 0;">${e.location}</p>
                                    </div>
                                    <span style="font-size:0.7rem;background:rgba(168,85,247,0.15);color:var(--theme-accent-purple);padding:0.1rem 0.35rem;border-radius:3px;text-transform:uppercase;">${e.event_type}</span>
                                </a>`;
                            });
                            results.appendChild(div);
                        }
                        
                        if (total === 0) {
                            results.innerHTML = '<div style="text-align:center;color:var(--theme-text-secondary);padding:2rem;">No matching indexed files found.</div>';
                        }
                    }
                });
            }

            // --- DYNAMIC NOTIFICATIONS LOGIC ---
            function syncNotificationCount() {
                const badge = document.querySelector('.top-nav-badge');
                if (!badge) return;
                
                fetch('<?php echo $path_prefix; ?>api/notifications.php?action=count')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const count = data.count;
                        if (count > 0) {
                            badge.textContent = count;
                            badge.style.display = 'inline-flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
            }

            function loadRecentNotificationsList() {
                const list = document.getElementById('notif-dropdown-menu');
                if (!list) return;
                
                list.innerHTML = '<div style="text-align:center;padding:1.5rem;font-size:0.8rem;color:var(--theme-text-secondary);"><i class="fa-solid fa-circle-notch fa-spin"></i> Refreshing notifications...</div>';
                
                fetch('<?php echo $path_prefix; ?>api/notifications.php?action=list&status=unread')
                .then(res => res.json())
                .then(data => {
                    list.innerHTML = `<div class="dropdown-header-info">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <h4 style="margin:0;font-size:0.9rem;">Recent Alerts</h4>
                            <button onclick="markAllNotificationsRead(event)" style="background:none;border:none;color:var(--theme-accent-blue);font-size:0.72rem;font-weight:700;cursor:pointer;">Clear All</button>
                        </div>
                    </div>`;
                    
                    if (data.status === 'success') {
                        if (data.notifications && data.notifications.length > 0) {
                            data.notifications.forEach(notif => {
                                let icon = 'fa-info-circle';
                                let iconColor = 'var(--theme-accent-blue)';
                                if (notif.type === 'success') { icon = 'fa-circle-check'; iconColor = '#22c55e'; }
                                else if (notif.type === 'warning') { icon = 'fa-triangle-exclamation'; iconColor = '#eab308'; }
                                else if (notif.type === 'error') { icon = 'fa-circle-xmark'; iconColor = '#ef4444'; }
                                
                                const item = document.createElement('div');
                                item.className = 'notif-item';
                                item.style.borderLeft = `3px solid ${iconColor}`;
                                item.innerHTML = `
                                    <div class="notif-item-title"><i class="fa-solid ${icon}" style="color:${iconColor};margin-right:0.35rem;"></i> ${notif.title}</div>
                                    <div style="font-size:0.75rem;color:var(--theme-text-secondary);margin:0.25rem 0 0.25rem 1.25rem;">${notif.message}</div>
                                    <div class="notif-item-time" style="margin-left:1.25rem;">Priority: ${notif.priority}</div>
                                `;
                                list.appendChild(item);
                            });
                        } else {
                            list.innerHTML += '<div style="text-align:center;padding:1.5rem;font-size:0.8rem;color:var(--theme-text-secondary);font-style:italic;">No unread alerts found.</div>';
                        }
                    }
                });
            }

            function markAllNotificationsRead(e) {
                if (e) e.stopPropagation();
                
                const formData = new FormData();
                formData.append('action', 'mark_read');
                
                fetch('<?php echo $path_prefix; ?>api/notifications.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        syncNotificationCount();
                        loadRecentNotificationsList();
                        if (window.showToast) {
                            window.showToast('All notifications cleared.', 'success');
                        }
                    }
                });
            }
        </script>
    <?php endif; ?>

    <!-- ==================== PROFESSIONAL SYSTEM FOOTER ==================== -->
    <?php
    $team_members = [
        ['name' => 'Ashwin Pande', 'role' => 'Administrator', 'phone' => '9226830066', 'email' => 'alumninethelp@gmail.com'],
        ['name' => 'Ravindra Mude', 'role' => 'Administrator', 'phone' => '9209276332', 'email' => 'alumninethelp@gmail.com'],
        ['name' => 'Yashraj Nanaware', 'role' => 'Administrator', 'phone' => '9325818393', 'email' => 'alumninethelp@gmail.com'],
        ['name' => 'Kaif Khan', 'role' => 'Administrator', 'phone' => '9589904746', 'email' => 'alumninethelp@gmail.com'],
        ['name' => 'Srushti Mokashe', 'role' => 'Team Member', 'phone' => '7821995050', 'email' => 'srushtimokashe4@gmail.com'],
        ['name' => 'Mahesh Padse', 'role' => 'Team Member', 'phone' => '8237020804', 'email' => 'maheshpadse2005@gmail.com'],
        ['name' => 'Aishwarya Nirwal', 'role' => 'Team Member', 'phone' => '8087906522', 'email' => 'nirwalaishwarya7@gmail.com'],
        ['name' => 'Bhagyashree Patil', 'role' => 'Team Member', 'phone' => '7821809886', 'email' => 'vaishnavipatil0942007@gmail.com'],
        ['name' => 'Gital Patil', 'role' => 'Team Member', 'phone' => '7820803005', 'email' => 'gitalpatil07@gmail.com'],
        ['name' => 'Ishwari Nikam', 'role' => 'Team Member', 'phone' => '9834922170', 'email' => 'ishwarinikam7930@gmail.com'],
        ['name' => 'Nakul Waghmare', 'role' => 'Team Member', 'phone' => '8446644436', 'email' => 'nakulwaghmare007@gmail.com']
    ];

    $path_prefix = $path_prefix ?? '';
    ?>

    <!-- ==================== ABOUT & TEAM OVERLAY MODAL ==================== -->
    <div class="search-modal-overlay" id="about-modal-overlay" onclick="if(event.target===this) toggleAboutModal(false)">
        <div class="search-modal-card" style="max-width: 780px; max-height: 85vh; display: flex; flex-direction: column;">
            <div class="search-modal-header" style="justify-content: space-between; border-bottom: 1px solid var(--theme-border); padding: 1.25rem 1.5rem; background: var(--theme-bg-secondary, #1e293b);">
                <div style="display: flex; align-items: center; gap: 0.6rem; color: var(--theme-text, #f8fafc);">
                    <i class="fa-solid fa-circle-info" style="color: var(--theme-accent-purple, #818cf8); font-size: 1.3rem;"></i>
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--theme-text, #f8fafc);">About AlumniNet</h3>
                        <p style="font-size: 0.78rem; color: var(--theme-text-secondary, #94a3b8); margin: 0;">Platform Overview & Project Team Directory</p>
                    </div>
                </div>
                <button onclick="toggleAboutModal(false)" style="background: none; border: none; color: var(--theme-text-secondary); cursor: pointer; font-size: 1.4rem; line-height: 1;">&times;</button>
            </div>

            <div style="padding: 1.5rem; overflow-y: auto; flex-grow: 1; display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- Platform Overview -->
                <div style="background: rgba(129, 140, 248, 0.05); border: 1px solid rgba(129, 140, 248, 0.2); border-radius: 8px; padding: 1rem 1.25rem;">
                    <h4 style="color: var(--theme-accent-purple, #818cf8); font-size: 0.92rem; margin: 0 0 0.4rem 0; font-weight: 700;">
                        <i class="fa-solid fa-graduation-cap"></i> Enterprise Alumni Engagement & Mentorship Platform
                    </h4>
                    <p style="font-size: 0.85rem; color: var(--theme-text-secondary, #94a3b8); line-height: 1.55; margin: 0;">
                        AlumniNet is designed to seamlessly bridge academic generations by connecting university students with established alumni. Our platform powers real-world mentorship, exclusive job referrals, networking events, and institutional career development.
                    </p>
                </div>

                <!-- Team Directory -->
                <div>
                    <h4 style="color: var(--theme-text, #f8fafc); font-size: 0.95rem; font-weight: 700; margin: 0 0 0.8rem 0; display: flex; align-items: center; gap: 0.4rem;">
                        <i class="fa-solid fa-users" style="color: var(--theme-accent-blue, #38bdf8);"></i> Project Team & Administration
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 0.75rem;">
                        <?php foreach ($team_members as $member): ?>
                        <div style="background: var(--theme-card, rgba(255,255,255,0.03)); border: 1px solid var(--theme-border, rgba(255,255,255,0.08)); border-radius: 8px; padding: 0.75rem 0.9rem;">
                            <div style="font-weight: 700; color: var(--theme-text, #f8fafc); font-size: 0.88rem; display: flex; align-items: center; justify-content: space-between;">
                                <span><?php echo htmlspecialchars($member['name']); ?></span>
                                <span style="font-size: 0.68rem; padding: 0.15rem 0.45rem; border-radius: 12px; background: <?php echo $member['role'] === 'Administrator' ? 'rgba(129, 140, 248, 0.15)' : 'rgba(56, 189, 248, 0.15)'; ?>; color: <?php echo $member['role'] === 'Administrator' ? 'var(--theme-accent-purple, #818cf8)' : 'var(--theme-accent-blue, #38bdf8)'; ?>; font-weight: 600;">
                                    <?php echo htmlspecialchars($member['role']); ?>
                                </span>
                            </div>
                            <div style="margin-top: 0.4rem; font-size: 0.78rem; display: flex; flex-direction: column; gap: 0.2rem; color: var(--theme-text-secondary, #94a3b8);">
                                <?php if (!empty($member['phone'])): ?>
                                <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $member['phone']); ?>" style="color: var(--theme-text-secondary, #94a3b8); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;" onmouseover="this.style.color='var(--theme-accent-blue)'" onmouseout="this.style.color='var(--theme-text-secondary)'">
                                    <i class="fa-solid fa-phone" style="font-size: 0.72rem; color: var(--theme-accent-blue, #38bdf8);"></i> +91 <?php echo htmlspecialchars($member['phone']); ?>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($member['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" style="color: var(--theme-text-secondary, #94a3b8); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; word-break: break-all;" onmouseover="this.style.color='var(--theme-accent-blue)'" onmouseout="this.style.color='var(--theme-text-secondary)'">
                                    <i class="fa-solid fa-envelope" style="font-size: 0.72rem; color: var(--theme-accent-purple, #818cf8);"></i> <?php echo htmlspecialchars($member['email']); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--theme-border); background: var(--theme-bg-secondary, #1e293b); display: flex; justify-content: space-between; align-items: center;">
                <a href="<?php echo $path_prefix; ?>about.php" class="btn btn-secondary btn-small" style="font-size: 0.82rem;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Visit Full About Page
                </a>
                <button onclick="toggleAboutModal(false)" class="btn btn-primary btn-small" style="font-size: 0.82rem; padding: 0.4rem 1rem;">Close</button>
            </div>
        </div>
    </div>

    <!-- ==================== PROFESSIONAL COMPACT SYSTEM FOOTER ==================== -->
    <footer class="app-footer <?php echo (isset($GLOBALS['sidebar_rendered']) && $GLOBALS['sidebar_rendered']) ? 'has-sidebar' : ''; ?>" style="background: var(--theme-sidebar, #0f172a); border-top: 1px solid var(--theme-border, rgba(255,255,255,0.08)); padding: 0.85rem 1.5rem; margin-top: auto; color: var(--theme-text-secondary, #94a3b8); font-size: 0.83rem;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; padding-right: <?php echo (is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student') ? '4.5rem' : '0'; ?>;">
            
            <!-- Left Brand & Copyright -->
            <div style="display: flex; align-items: center; gap: 0.6rem; color: var(--theme-text, #f8fafc); font-weight: 600;">
                <i class="fa-solid fa-graduation-cap" style="color: var(--theme-accent-purple, #818cf8); font-size: 1.05rem;"></i>
                <span>AlumniNet</span>
                <span style="opacity: 0.4; font-weight: 400;">|</span>
                <span style="font-weight: 400; color: var(--theme-text-secondary, #94a3b8); font-size: 0.78rem;">&copy; <?php echo date('Y'); ?> All rights reserved.</span>
            </div>

            <!-- Right Action Links -->
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem; align-items: center; font-size: 0.82rem;">
                <a href="#" onclick="toggleAboutModal(true); return false;" style="color: var(--theme-text, #f8fafc); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-accent-purple, #818cf8)'" onmouseout="this.style.color='var(--theme-text, #f8fafc)'">
                    <i class="fa-solid fa-circle-info" style="color: var(--theme-accent-purple, #818cf8);"></i> About & Team
                </a>
                <a href="<?php echo $path_prefix; ?>user/help.php" style="color: var(--theme-text-secondary, #94a3b8); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-accent-blue, #38bdf8)'" onmouseout="this.style.color='var(--theme-text-secondary, #94a3b8)'">
                    <i class="fa-solid fa-headset" style="color: var(--theme-accent-blue, #38bdf8);"></i> Help Center
                </a>
                <a href="<?php echo $path_prefix; ?>user/feedback.php" style="color: var(--theme-text-secondary, #94a3b8); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-accent-blue, #38bdf8)'" onmouseout="this.style.color='var(--theme-text-secondary, #94a3b8)'">
                    <i class="fa-solid fa-comment-dots" style="color: var(--theme-accent-blue, #38bdf8);"></i> Feedback
                </a>
                <a href="mailto:alumninethelp@gmail.com" style="color: var(--theme-text-secondary, #94a3b8); text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-accent-blue, #38bdf8)'" onmouseout="this.style.color='var(--theme-text-secondary, #94a3b8)'">
                    <i class="fa-solid fa-envelope" style="color: var(--theme-accent-blue, #38bdf8);"></i> Contact
                </a>
            </div>

        </div>
    </footer>

    <script>
        function toggleAboutModal(show) {
            const overlay = document.getElementById('about-modal-overlay');
            if (overlay) {
                if (show) {
                    overlay.classList.add('active');
                } else {
                    overlay.classList.remove('active');
                }
            }
        }
    </script>

</body>
</html>


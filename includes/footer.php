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
    // Admin contact details
    $admin_display_name = 'Ashwin Pande';
    $admin_display_email = 'ashwinpande30092007@gmail.com';
    $admin_display_phone = '+91 9226830066';

    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt_admin_fetch = $pdo->query("SELECT name, email, phone FROM users WHERE role = 'admin' AND status = 'approved' ORDER BY id ASC LIMIT 1");
            $admin_row = $stmt_admin_fetch->fetch(PDO::FETCH_ASSOC);
            if ($admin_row) {
                if (!empty($admin_row['name'])) {
                    $admin_display_name = htmlspecialchars($admin_row['name']);
                }
                if (!empty($admin_row['email'])) {
                    $admin_display_email = htmlspecialchars($admin_row['email']);
                }
                if (!empty($admin_row['phone'])) {
                    $admin_display_phone = htmlspecialchars($admin_row['phone']);
                }
            }
        } catch (Exception $e) {
            // Fallback to defaults
        }
    }
    $path_prefix = $path_prefix ?? '';
    ?>

    <!-- ==================== COMPACT SIDE-BY-SIDE SYSTEM FOOTER ==================== -->
    <footer class="app-footer <?php echo (isset($GLOBALS['sidebar_rendered']) && $GLOBALS['sidebar_rendered']) ? 'has-sidebar' : ''; ?>" style="background: var(--theme-sidebar, #0f172a); border-top: 1px solid var(--theme-border, rgba(255,255,255,0.1)); padding: 1.25rem 1.5rem; margin-top: auto; color: var(--theme-text-secondary, #94a3b8); font-size: 0.85rem;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1.5rem; align-items: flex-start;">
            
            <!-- SECTION 1: HELP -->
            <div style="flex: 1; min-width: 280px;">
                <h4 style="color: var(--theme-text, #f8fafc); font-size: 0.95rem; margin: 0 0 0.5rem 0; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-circle-question" style="color: var(--theme-accent-blue, #38bdf8);"></i> 1. Help & Support
                </h4>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; align-items: center; font-size: 0.82rem; margin-top: 0.3rem;">
                    <a href="<?php echo $path_prefix; ?>user/help.php" style="color: var(--theme-text-secondary, #94a3b8); text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                        <i class="fa-solid fa-headset" style="color: var(--theme-accent-blue, #38bdf8);"></i> Help Center & FAQs
                    </a>
                    <span style="opacity: 0.3;">|</span>
                    <a href="<?php echo $path_prefix; ?>user/feedback.php" style="color: var(--theme-text-secondary, #94a3b8); text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
                        <i class="fa-solid fa-comment-dots" style="color: var(--theme-accent-blue, #38bdf8);"></i> Feedback & Tickets
                    </a>
                </div>
            </div>

            <!-- SECTION 2: ABOUT & ADMIN INFO -->
            <div style="flex: 1.2; min-width: 320px;">
                <h4 style="color: var(--theme-text, #f8fafc); font-size: 0.95rem; margin: 0 0 0.5rem 0; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-circle-info" style="color: var(--theme-accent-purple, #818cf8);"></i> 2. <a href="<?php echo $path_prefix; ?>about.php" style="color: inherit; text-decoration: underline;">About Us</a> & Contact
                </h4>
                <div style="font-size: 0.82rem; line-height: 1.5; color: var(--theme-text-secondary, #94a3b8);">
                    <div style="margin-bottom: 0.3rem; color: var(--theme-text, #f8fafc);">
                        <a href="<?php echo $path_prefix; ?>about.php" style="color: var(--theme-accent-purple, #818cf8); font-weight: 700; text-decoration: underline;">About AlumniNet:</a> Enterprise Alumni Engagement & Mentorship Platform.
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.3rem 0.8rem; align-items: center;">
                        <span><i class="fa-solid fa-user-shield" style="color: var(--theme-accent-purple, #818cf8);"></i> <strong>Admin:</strong> <?php echo $admin_display_name; ?></span>
                        <span style="opacity: 0.3;">|</span>
                        <span><i class="fa-solid fa-phone" style="color: var(--theme-accent-blue, #38bdf8);"></i> <strong>Contact:</strong> <a href="tel:9226830066" style="color: var(--theme-text, #f8fafc); text-decoration: none; font-weight: 500;"><?php echo $admin_display_phone; ?></a></span>
                        <span style="opacity: 0.3;">|</span>
                        <span><i class="fa-solid fa-envelope" style="color: var(--theme-accent-blue, #38bdf8);"></i> <strong>Email:</strong> <a href="mailto:<?php echo $admin_display_email; ?>" style="color: var(--theme-text, #f8fafc); text-decoration: none; font-weight: 500;"><?php echo $admin_display_email; ?></a></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- BOTTOM COPYRIGHT BAR -->
        <div style="max-width: 1200px; margin: 0.8rem auto 0 auto; padding-top: 0.6rem; border-top: 1px solid var(--theme-border, rgba(255,255,255,0.06)); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; font-size: 0.78rem; opacity: 0.85;">
            <div style="display: flex; align-items: center; gap: 0.4rem; color: var(--theme-text, #f8fafc); font-weight: 600;">
                <i class="fa-solid fa-graduation-cap" style="color: var(--theme-accent-purple, #818cf8);"></i> AlumniNet Platform
            </div>
            <div>&copy; <?php echo date('Y'); ?> AlumniNet. All rights reserved.</div>
        </div>
    </footer>

</body>
</html>


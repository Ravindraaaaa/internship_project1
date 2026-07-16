    <!-- ==================== BACK TO TOP FAB ==================== -->
    <button class="back-to-top" id="back-to-top" title="Back to Top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <!-- ==================== FAB OPEN SETTINGS DRAWER ==================== -->
    <button class="fab" onclick="openSettingsDrawer()" title="Customize Theme & Visual Backgrounds">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
    </button>

    <!-- ==================== SLIDING SETTINGS DRAWER ==================== -->
    <div class="settings-drawer" id="settings-drawer">
        <div class="drawer-header">
            <h3><i data-lucide="sliders" style="width:18px;height:18px;vertical-align:middle;margin-right:0.25rem;"></i> Visual Settings</h3>
            <button class="drawer-close-btn" onclick="closeSettingsDrawer()">&times;</button>
        </div>
        
        <div class="drawer-body">
            
            <!-- 1. Color Themes -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="palette" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 1. Color Theme</h4>
                <div class="customizer-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <button class="customizer-btn theme-select-btn" data-theme="theme-dark">Dark Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-light">Light Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-glass">Glass Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-midnight">Midnight Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-aurora">Aurora Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-cyber-blue">Cyber Blue</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-royal-purple">Royal Purple</button>
                </div>
            </div>

            <!-- 2. Accent Colors -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="droplet" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 2. Accent Color</h4>
                <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
                    <div class="accent-color-circle active" data-color="#3b82f6" style="background: #3b82f6;" title="Electric Blue"></div>
                    <div class="accent-color-circle" data-color="#8b5cf6" style="background: #8b5cf6;" title="Cyber Purple"></div>
                    <div class="accent-color-circle" data-color="#10b981" style="background: #10b981;" title="Neon Green"></div>
                    <div class="accent-color-circle" data-color="#ec4899" style="background: #ec4899;" title="Cyber Pink"></div>
                    <div class="accent-color-circle" data-color="#f59e0b" style="background: #f59e0b;" title="Golden Amber"></div>
                    <div class="accent-color-circle" data-color="#ef4444" style="background: #ef4444;" title="Crimson Red"></div>
                </div>
            </div>

            <!-- 3. Dynamic Background Selection -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="image" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 3. Canvas Background</h4>
                <div class="customizer-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <button class="customizer-btn bg-option-btn" data-bg="aurora">Animated Aurora</button>
                    <button class="customizer-btn bg-option-btn" data-bg="mesh">Mesh Gradient</button>
                    <button class="customizer-btn bg-option-btn" data-bg="gradient">Animated Gradient</button>
                    <button class="customizer-btn bg-option-btn" data-bg="glass-bg">Glass Background</button>
                    <button class="customizer-btn bg-option-btn" data-bg="blobs">Floating Blobs</button>
                    <button class="customizer-btn bg-option-btn" data-bg="particles">Particles</button>
                    <button class="customizer-btn bg-option-btn" data-bg="stars">Stars</button>
                    <button class="customizer-btn bg-option-btn" data-bg="waves">Waves</button>
                    <button class="customizer-btn bg-option-btn" data-bg="grid">Animated Grid</button>
                    <button class="customizer-btn bg-option-btn" data-bg="shapes">Abstract Shapes</button>
                    <button class="customizer-btn bg-option-btn" data-bg="bubbles">Floating Bubbles</button>
                    <button class="customizer-btn bg-option-btn" data-bg="galaxy">Galaxy</button>
                    <button class="customizer-btn bg-option-btn" data-bg="minimal-white">Minimal White</button>
                    <button class="customizer-btn bg-option-btn" data-bg="custom-image">Custom Upload</button>
                </div>
            </div>

            <!-- 4. Adjust Sliders & Custom Background -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="sliders" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 4. Adjust Parameters</h4>
                
                <div class="slider-group">
                    <label for="custom-bg-blur">Background Blur <span id="blur-val">15px</span></label>
                    <input type="range" id="custom-bg-blur" class="custom-range" min="0" max="30" value="15" oninput="document.getElementById('blur-val').textContent = this.value + 'px'">
                </div>

                <div class="slider-group">
                    <label for="custom-bg-opacity">Background Opacity <span id="opacity-val">100%</span></label>
                    <input type="range" id="custom-bg-opacity" class="custom-range" min="10" max="100" value="100" oninput="document.getElementById('opacity-val').textContent = this.value + '%'">
                </div>

                <div class="slider-group">
                    <label for="custom-bg-speed">Animation Speed <span id="speed-val">1.0x</span></label>
                    <input type="range" id="custom-bg-speed" class="custom-range" min="0.1" max="3" step="0.1" value="1.0" oninput="document.getElementById('speed-val').textContent = this.value + 'x'">
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: space-between; align-items: center;">
                    <label class="btn btn-secondary btn-small" style="font-size: 0.8rem; cursor: pointer; flex-grow: 1; text-align: center;">
                        <i class="fa-solid fa-image"></i> Upload Custom Image
                        <input type="file" id="custom-bg-upload" accept="image/*" style="display: none;">
                    </label>
                </div>
            </div>

            <!-- 5. Toggles -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="play" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 5. Performance Options</h4>
                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: var(--border-radius-sm); border: 1px solid var(--theme-border);">
                    <span style="font-size: 0.88rem; font-weight: 500;">Enable Animations</span>
                    <label class="switch-container">
                        <input type="checkbox" id="performance-toggle" checked>
                        <span class="switch-slider"></span>
                    </label>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 2rem; margin-bottom: 2rem;">
                <button class="btn btn-danger" id="custom-bg-reset" style="width: 100%; font-size: 0.85rem;"><i data-lucide="rotate-ccw" style="width:16px;height:16px;vertical-align:middle;margin-right:0.25rem;"></i> Reset Style</button>
            </div>

        </div>
    </div>

    <!-- Main JavaScript Core Asset -->
    <script src="<?php echo $path_prefix; ?>assets/js/main.js"></script>
    
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
            
            // Re-render slider displays on load
            const blurInput = document.getElementById('custom-bg-blur');
            const opacityInput = document.getElementById('custom-bg-opacity');
            const speedInput = document.getElementById('custom-bg-speed');
            
            if (blurInput) {
                blurInput.value = localStorage.getItem('bg-blur') || '15';
                document.getElementById('blur-val').textContent = blurInput.value + 'px';
            }
            if (opacityInput) {
                opacityInput.value = localStorage.getItem('bg-opacity') || '100';
                document.getElementById('opacity-val').textContent = opacityInput.value + '%';
            }
            if (speedInput) {
                speedInput.value = localStorage.getItem('bg-speed') || '1.0';
                document.getElementById('speed-val').textContent = speedInput.value + 'x';
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
                                elseif (notif.type === 'warning') { icon = 'fa-triangle-exclamation'; iconColor = '#eab308'; }
                                elseif (notif.type === 'error') { icon = 'fa-circle-xmark'; iconColor = '#ef4444'; }
                                
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

</body>
</html>


<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

require_login();
handle_session_timeout();

$uid = get_user_id();
$role = get_user_role();
$user_name = get_user_name();
$page_title = "AlumniNet Messenger";

$peer_id_preselect = intval($_GET['peer_id'] ?? 0);

$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
if ($role === 'alumni') {
    $stmtP = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
    $stmtP->execute([$uid]);
    $prof = $stmtP->fetch();
    if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/../' . $prof['profile_pic'])) {
        $sidebar_avatar = '../' . $prof['profile_pic'];
    }
} else if ($role === 'student') {
    $stmtP = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
    $stmtP->execute([$uid]);
    $prof = $stmtP->fetch();
    if ($prof && !empty($prof['profile_pic']) && file_exists(__DIR__ . '/../' . $prof['profile_pic'])) {
        $sidebar_avatar = '../' . $prof['profile_pic'];
    }
}

// Fetch all available network peers for creating new conversation
$stmtPeers = $pdo->prepare("SELECT id, name, role FROM users WHERE id != ? AND status = 'approved' ORDER BY name ASC");
$stmtPeers->execute([$uid]);
$network_peers = $stmtPeers->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- ==================== SIDEBAR ==================== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="logo logo-text">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <button class="sidebar-toggle-btn" id="sidebar-toggle">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; text-align: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;" class="sidebar-profile-box">
            <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--theme-accent-purple);" class="user-sidebar-avatar">
            <div style="margin-top: 0.75rem;" class="link-text">
                <h4 style="font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;"><?php echo htmlspecialchars($user_name); ?></h4>
                <p style="font-size: 0.72rem; color: var(--theme-text-secondary); text-transform: uppercase;"><?php echo htmlspecialchars($role); ?> member</p>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="dashboard.php"><i data-lucide="gauge"></i> <span class="link-text">Dashboard</span></a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php"><i data-lucide="user"></i> <span class="link-text">My Profile</span></a>
            </li>
            <li class="sidebar-item">
                <a href="mentorship.php"><i data-lucide="handshake"></i> <span class="link-text">Mentorship</span></a>
            </li>
            <li class="sidebar-item">
                <a href="alumni.php"><i data-lucide="users"></i> <span class="link-text">Alumni Directory</span></a>
            </li>
            <li class="sidebar-item">
                <a href="jobs.php"><i data-lucide="briefcase"></i> <span class="link-text">Job Board</span></a>
            </li>
            <li class="sidebar-item">
                <a href="events.php"><i data-lucide="calendar"></i> <span class="link-text">Events Board</span></a>
            </li>
            <li class="sidebar-item">
                <a href="portfolio.php"><i data-lucide="folder-kanban"></i> <span class="link-text">My Portfolio</span></a>
            </li>
            <li class="sidebar-item" style="margin-top: auto; border-top: 1px solid var(--theme-border); padding-top: 1rem;">
                <a href="../logout.php" style="color: var(--accent-danger);"><i data-lucide="log-out"></i> <span class="link-text">Sign Out</span></a>
            </li>
        </ul>
    </aside>

    <!-- ==================== MAIN WORKSPACE ==================== -->
    <div class="dashboard-content-area" style="display: flex; flex-direction: column; height: 100vh;">
        <!-- Top Navbar -->
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h2>Messenger Logs</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="openSettingsDrawer()" title="Open visual settings">
                    <i data-lucide="palette" style="width: 20px; height: 20px;"></i>
                </button>
                
                <!-- Start New Chat Dropdown -->
                <div style="position: relative;" class="new-chat-dropdown-container">
                    <button class="btn btn-primary btn-small" id="start-new-chat-btn"><i class="fa-solid fa-plus"></i> New Message</button>
                    <div class="nav-dropdown-menu" id="new-chat-menu" style="width: 260px; max-height: 350px; overflow-y: auto; padding: 0.5rem;">
                        <h4 style="font-size:0.82rem; margin:0.5rem; color:var(--theme-text-secondary);">Select Member</h4>
                        <div style="border-bottom:1px solid var(--theme-border); margin:0.25rem 0;"></div>
                        <?php if(!empty($network_peers)): ?>
                            <?php foreach($network_peers as $peer): ?>
                                <a href="#" class="dropdown-item select-peer-item" data-id="<?php echo $peer['id']; ?>" data-name="<?php echo htmlspecialchars($peer['name']); ?>">
                                    <strong><?php echo htmlspecialchars($peer['name']); ?></strong>
                                    <span style="font-size: 0.65rem; color: var(--theme-accent-blue); float: right; text-transform: uppercase;"><?php echo $peer['role']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding:1rem; text-align:center; color:var(--theme-text-secondary); font-size:0.8rem;">No contacts found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Messenger Layout Grid -->
        <div class="messenger-grid" style="display: flex; flex-grow: 1; overflow: hidden; border-top: 1px solid var(--theme-border);">
            
            <!-- Left Conversations Pane -->
            <div class="conversations-pane" style="width: 320px; border-right: 1px solid var(--theme-border); display: flex; flex-direction: column; background: rgba(255,255,255,0.01);">
                <div style="padding: 1rem; border-bottom: 1px solid var(--theme-border);">
                    <input type="text" id="convo-search" class="input-glass" style="font-size: 0.85rem; padding: 0.4rem 0.75rem; width:100%;" placeholder="Search chats...">
                </div>
                <div id="conversations-list-container" style="flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column;">
                    <div style="text-align: center; color: var(--theme-text-secondary); font-size: 0.85rem; padding: 2rem;">Loading chat logs...</div>
                </div>
            </div>

            <!-- Right Message Thread Pane -->
            <div class="thread-pane" style="flex-grow: 1; display: flex; flex-direction: column; background: rgba(0,0,0,0.05); position: relative;">
                
                <!-- Chat Window Header -->
                <div id="chat-thread-header" style="padding: 1rem; border-bottom: 1px solid var(--theme-border); background: rgba(255,255,255,0.01); display: none; align-items: center; gap: 0.75rem;">
                    <img id="active-peer-avatar" src="" alt="Avatar" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <h4 id="active-peer-name" style="font-size: 0.95rem; font-weight: 700;">Active User</h4>
                        <span id="active-peer-role" style="font-size: 0.7rem; color: var(--theme-accent-blue); text-transform: uppercase; font-weight: 600;">ALUMNI</span>
                    </div>
                </div>

                <!-- Chat Messages Stream -->
                <div id="chat-messages-stream" style="flex-grow: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                    <div id="chat-stream-placeholder" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--theme-text-secondary); text-align: center; padding: 2rem;">
                        <i class="fa-regular fa-comments" style="font-size: 3rem; margin-bottom: 1rem; color: var(--theme-accent-blue);"></i>
                        <h3>Your Conversations Inbox</h3>
                        <p style="font-size: 0.85rem; max-width: 320px; margin-top: 0.5rem;">Select a chat member from the sidebar or click "New Message" to begin real-time professional messaging.</p>
                    </div>
                </div>

                <!-- Chat Input Tray -->
                <div id="chat-input-container" style="padding: 1rem; border-top: 1px solid var(--theme-border); background: rgba(255,255,255,0.01); display: none;">
                    <div style="display: flex; gap: 0.75rem;">
                        <input type="text" id="chat-msg-input" class="input-glass" style="flex-grow: 1; font-size: 0.88rem; padding: 0.6rem 1rem;" placeholder="Type your professional message..." onkeydown="if(event.key==='Enter') sendChatMessage()">
                        <button onclick="sendChatMessage()" class="btn btn-primary" style="padding: 0.6rem 1.25rem;"><i class="fa-solid fa-paper-plane"></i> Send</button>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<style>
    /* Styling variables matching design tokens */
    .convo-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        border-bottom: 1px solid var(--theme-border);
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        color: var(--theme-text-primary);
    }
    .convo-item:hover, .convo-item.active {
        background: rgba(255,255,255,0.04);
        border-left: 3px solid var(--theme-accent-blue);
        padding-left: calc(1rem - 3px);
    }
    .convo-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        object-fit: cover;
    }
    .chat-bubble {
        max-width: 65%;
        padding: 0.75rem 1.25rem;
        border-radius: 16px;
        font-size: 0.88rem;
        line-height: 1.4;
        position: relative;
    }
    .chat-bubble.sent {
        align-self: flex-end;
        background: var(--theme-accent-gradient);
        color: #ffffff;
        border-bottom-right-radius: 2px;
    }
    .chat-bubble.received {
        align-self: flex-start;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--theme-border);
        color: var(--theme-text-primary);
        border-bottom-left-radius: 2px;
    }
</style>

<script>
    let activeConversationId = 0;
    let activePeerId = <?php echo $peer_id_preselect; ?>;
    let currentUserId = <?php echo $uid; ?>;
    let chatInterval = null;

    document.addEventListener('DOMContentLoaded', () => {
        // Toggle start new chat menu
        const newChatBtn = document.getElementById('start-new-chat-btn');
        const newChatMenu = document.getElementById('new-chat-menu');
        
        if (newChatBtn && newChatMenu) {
            newChatBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                newChatMenu.classList.toggle('show');
            });
            document.addEventListener('click', () => {
                newChatMenu.classList.remove('show');
            });
        }

        // Handle selecting peer from "New Message" dropdown
        const selectPeerItems = document.querySelectorAll('.select-peer-item');
        selectPeerItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const peerId = parseInt(item.getAttribute('data-id'));
                const peerName = item.getAttribute('data-name');
                startConversationWithPeer(peerId, peerName);
            });
        });

        // Search conversations list
        const searchInput = document.getElementById('convo-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase();
                document.querySelectorAll('.convo-item').forEach(item => {
                    const name = item.querySelector('.peer-name-el').innerText.toLowerCase();
                    if (name.includes(query)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        // Initialize Chat List
        loadConversationsList();
        setInterval(loadConversationsList, 10000); // refresh list every 10s

        // If peer pre-selected (from Alumni Directory direct message link)
        if (activePeerId > 0) {
            startConversationWithPeer(activePeerId, "Contacting Member...");
        }
    });

    function startConversationWithPeer(peerId, peerName) {
        activePeerId = peerId;
        activeConversationId = 0; // fetch/create via API send or thread
        
        // Show headers and placeholders
        document.getElementById('chat-thread-header').style.display = 'flex';
        document.getElementById('chat-input-container').style.display = 'block';
        document.getElementById('chat-stream-placeholder').style.display = 'none';

        document.getElementById('active-peer-name').innerText = peerName;
        document.getElementById('active-peer-role').innerText = 'CONTACT';
        document.getElementById('active-peer-avatar').src = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';

        loadThreadMessages();

        // Restart message poller
        if (chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(loadThreadMessages, 3000);
    }

    function selectConversation(convoId, peerId, peerName, peerRole, avatarUrl) {
        activeConversationId = convoId;
        activePeerId = peerId;

        document.getElementById('chat-thread-header').style.display = 'flex';
        document.getElementById('chat-input-container').style.display = 'block';
        document.getElementById('chat-stream-placeholder').style.display = 'none';

        document.getElementById('active-peer-name').innerText = peerName;
        document.getElementById('active-peer-role').innerText = peerRole;
        document.getElementById('active-peer-avatar').src = avatarUrl;

        // Highlight active convo item
        document.querySelectorAll('.convo-item').forEach(item => item.classList.remove('active'));
        const activeItem = document.querySelector(`.convo-item[data-convo-id="${convoId}"]`);
        if (activeItem) activeItem.classList.add('active');

        loadThreadMessages();

        // Restart message poller
        if (chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(loadThreadMessages, 3000); // Poll messages every 3s
    }

    function loadConversationsList() {
        fetch('../api/chat.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const listContainer = document.getElementById('conversations-list-container');
                    listContainer.innerHTML = '';
                    
                    if (data.conversations && data.conversations.length > 0) {
                        data.conversations.forEach(convo => {
                            const lastMsg = convo.last_message ? escapeHTML(convo.last_message) : 'No messages yet';
                            const badge = convo.unread_count > 0 
                                ? `<span class="badge" style="background:var(--accent-danger);color:#fff;font-size:0.65rem;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;">${convo.unread_count}</span>` 
                                : '';
                            const activeClass = (convo.conversation_id === activeConversationId) ? 'active' : '';

                            const link = document.createElement('a');
                            link.href = '#';
                            link.className = `convo-item ${activeClass}`;
                            link.setAttribute('data-convo-id', convo.conversation_id);
                            link.innerHTML = `
                                <img src="${convo.peer_avatar}" alt="Peer Avatar" class="convo-avatar">
                                <div style="flex-grow:1; min-width:0;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.15rem;">
                                        <strong class="peer-name-el" style="font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHTML(convo.peer_name)}</strong>
                                        <span style="font-size:0.68rem;color:var(--theme-text-secondary);">${formatMsgTime(convo.last_message_time || convo.created_at)}</span>
                                    </div>
                                    <p style="font-size:0.75rem;color:var(--theme-text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0;">${lastMsg}</p>
                                </div>
                                ${badge}
                            `;
                            link.addEventListener('click', (e) => {
                                e.preventDefault();
                                selectConversation(convo.conversation_id, convo.peer_id, convo.peer_name, convo.peer_role, convo.peer_avatar);
                            });
                            listContainer.appendChild(link);
                        });
                    } else {
                        listContainer.innerHTML = '<div style="text-align: center; color: var(--theme-text-secondary); font-size: 0.85rem; padding: 2rem;">No chats available.</div>';
                    }
                }
            })
            .catch(err => console.error("Error loading chat list:", err));
    }

    function loadThreadMessages() {
        let url = `../api/chat.php?action=thread&conversation_id=${activeConversationId}`;
        if (activeConversationId <= 0 && activePeerId > 0) {
            url += `&peer_id=${activePeerId}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.conversation_id > 0 && activeConversationId !== data.conversation_id) {
                        activeConversationId = data.conversation_id;
                        loadConversationsList();
                    }

                    const messagesContainer = document.getElementById('chat-messages-stream');
                    
                    // Filter out placeholders
                    const placeholder = document.getElementById('chat-stream-placeholder');
                    if (placeholder) placeholder.style.display = 'none';

                    // Track scroll position before redraw
                    const isScrolledToBottom = messagesContainer.scrollHeight - messagesContainer.clientHeight <= messagesContainer.scrollTop + 50;

                    messagesContainer.innerHTML = '';
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            const senderClass = (msg.sender_id === currentUserId) ? 'sent' : 'received';
                            const bubble = document.createElement('div');
                            bubble.className = `chat-bubble ${senderClass}`;
                            bubble.innerHTML = `
                                <div>${escapeHTML(msg.message)}</div>
                                <span style="display:block;font-size:0.6rem;opacity:0.6;margin-top:0.25rem;text-align:right;">${formatMsgTime(msg.created_at)}</span>
                            `;
                            messagesContainer.appendChild(bubble);
                        });
                    } else {
                        messagesContainer.innerHTML = '<div style="text-align:center;color:var(--theme-text-secondary);font-size:0.8rem;padding:2rem;">Send a message to open this connection!</div>';
                    }

                    if (isScrolledToBottom || activeConversationId === 0) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }
            })
            .catch(err => console.error("Error loading messages:", err));
    }

    function sendChatMessage() {
        const input = document.getElementById('chat-msg-input');
        const text = input.value.trim();
        if (!text) return;

        input.value = '';

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('conversation_id', activeConversationId);
        formData.append('peer_id', activePeerId);
        formData.append('message', text);

        fetch('../api/chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (activeConversationId === 0 && data.conversation_id > 0) {
                    activeConversationId = data.conversation_id;
                }
                loadThreadMessages();
                loadConversationsList();
            } else {
                window.showToast ? window.showToast(data.message, 'error') : alert(data.message);
            }
        })
        .catch(err => console.error("Error sending message:", err));
    }

    function formatMsgTime(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

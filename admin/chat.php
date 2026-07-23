<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

// Enforce admin permission
require_once __DIR__ . '/../middleware/admin.php';
check_admin();

$uid = get_user_id();
$role = 'admin';
$user_name = get_user_name();
$page_title = "Live Support Messenger";

$peer_id_preselect = intval($_GET['peer_id'] ?? 0);

$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png'; // default admin avatar

// Fetch all available network peers for creating new conversation (all users except this admin)
$stmtPeers = $pdo->prepare("SELECT id, name, role FROM users WHERE id != ? AND status = 'approved' ORDER BY name ASC");
$stmtPeers->execute([$uid]);
$network_peers = $stmtPeers->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <?php render_sidebar('chat'); ?>

    <!-- ==================== MAIN WORKSPACE ==================== -->
    <div class="dashboard-content-area" style="display: flex; flex-direction: column; height: 100vh;">
        <!-- Top Navbar -->
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>

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
                    <button class="theme-toggle-btn" id="chat-back-mobile-btn" onclick="backToConversationsList()" style="display: none; margin-right: 0.25rem; padding: 0.4rem 0.6rem;"><i class="fa-solid fa-chevron-left"></i></button>
                    <img id="active-peer-avatar" src="" alt="Avatar" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <h4 id="active-peer-name" style="font-size: 0.95rem; font-weight: 700;">Active User</h4>
                        <span id="active-peer-role" style="font-size: 0.7rem; color: var(--theme-accent-blue); text-transform: uppercase; font-weight: 600;">STUDENT</span>
                    </div>
                    
                    <!-- Search Messages inside thread -->
                    <div style="margin-left: auto; display: flex; align-items: center; gap: 0.5rem; position: relative;">
                        <input type="text" id="thread-msg-search" class="input-glass" style="font-size: 0.75rem; padding: 0.35rem 0.5rem 0.35rem 1.5rem; width: 120px;" placeholder="Search chat..." oninput="filterThreadMessages()">
                        <i class="fa-solid fa-magnifying-glass" style="font-size: 0.72rem; color: var(--theme-text-secondary); position: absolute; left: 8px; pointer-events: none;"></i>
                    </div>

                    <!-- Delete Conversation Button -->
                    <button class="theme-toggle-btn" id="delete-chat-btn" onclick="deleteActiveChat()" title="Delete Conversation" style="padding: 0.45rem 0.65rem; color: var(--accent-danger, #ef4444); border-color: rgba(239, 68, 68, 0.2);">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>

                <!-- Chat Messages Stream -->
                <div id="chat-messages-stream" style="flex-grow: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                    <div id="chat-stream-placeholder" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--theme-text-secondary); text-align: center; padding: 2rem;">
                        <i class="fa-regular fa-comments" style="font-size: 3rem; margin-bottom: 1rem; color: var(--theme-accent-blue);"></i>
                        <h3>Support Inbox</h3>
                        <p style="font-size: 0.85rem; max-width: 320px; margin-top: 0.5rem;">Select a member or click "New Message" to begin live real-time support chatting.</p>
                    </div>
                </div>

                <!-- Typing indicator -->
                <div id="typing-indicator" style="display:none; align-self:flex-start; margin-left:1.5rem; margin-bottom:0.5rem; background:rgba(255,255,255,0.03); border:1px solid var(--theme-border); padding:0.4rem 0.8rem; border-radius:12px; font-size:0.75rem; color:var(--theme-text-secondary);">
                    <span id="typing-name">Someone</span> is typing<span class="typing-dots"><span>.</span><span>.</span><span>.</span></span>
                </div>

                <!-- Chat Input Tray -->
                <div id="chat-input-container" style="padding: 1rem; border-top: 1px solid var(--theme-border); background: rgba(255,255,255,0.01); display: none; position: relative;">
                    <!-- Emoji Picker Grid Box -->
                    <div id="emoji-picker-box" class="card-glass" style="display: none; position: absolute; bottom: 65px; left: 1rem; width: 230px; padding: 0.5rem; grid-template-columns: repeat(6, 1fr); gap: 0.35rem; z-index: 1000; border: 1px solid var(--theme-border); border-radius: 8px;"></div>
                    
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <!-- Emoji Toggle Button -->
                        <button class="btn btn-secondary" onclick="toggleEmojiPicker()" style="padding: 0.6rem 0.75rem;" title="Insert Emoji"><i class="fa-regular fa-face-smile"></i></button>
                        
                        <!-- Upload File/Image Input & Button -->
                        <input type="file" id="chat-file-input" style="display:none;" onchange="handleChatFileSelect(this)" accept=".png,.jpg,.jpeg,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar">
                        <button class="btn btn-secondary" id="chat-file-btn" onclick="document.getElementById('chat-file-input').click()" style="padding: 0.6rem 0.75rem;" title="Attach File"><i class="fa-solid fa-paperclip"></i></button>
                        
                        <input type="text" id="chat-msg-input" class="input-glass" style="flex-grow: 1; font-size: 0.88rem; padding: 0.6rem 1rem;" placeholder="Type your support message..." onkeydown="if(event.key==='Enter') sendChatMessage()" oninput="handleTypingState()">
                        
                        <button id="send-chat-btn" onclick="sendChatMessage()" class="btn btn-primary" style="padding: 0.6rem 1.25rem;"><i class="fa-solid fa-paper-plane"></i> Send</button>
                    </div>
                    
                    <!-- Selected File Indicator Preview -->
                    <div id="selected-file-preview" style="display:none; align-items:center; gap:0.5rem; margin-top:0.5rem; font-size:0.75rem; color:var(--theme-text-secondary); background:rgba(255,255,255,0.02); padding:0.3rem 0.75rem; border-radius:6px;">
                        <i class="fa-solid fa-file-invoice"></i> <span id="selected-file-name" style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">file.pdf</span>
                        <button onclick="clearSelectedFile()" style="background:none; border:none; color:var(--accent-danger); cursor:pointer; font-size:0.88rem; margin-left:auto;"><i class="fa-solid fa-circle-xmark"></i></button>
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
    .typing-dots span {
        animation: typingPulse 1.4s infinite;
        display: inline-block;
        font-weight: bold;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typingPulse {
        0% { opacity: 0.2; }
        50% { opacity: 1; }
        100% { opacity: 0.2; }
    }
    .attachment-preview-img {
        max-width: 180px;
        border-radius: 8px;
        border: 1px solid var(--theme-border);
        margin-top: 0.4rem;
        display: block;
    }
    .attachment-file-card {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.75rem;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--theme-border);
        border-radius: 8px;
        margin-top: 0.4rem;
        color: var(--theme-text);
        text-decoration: none;
        font-size: 0.78rem;
    }
    .attachment-file-card:hover {
        background: rgba(255,255,255,0.06);
    }
    @media (max-width: 768px) {
        .conversations-pane {
            width: 100% !important;
        }
        .thread-pane {
            display: none !important;
            width: 100% !important;
        }
        .chat-active-mobile .conversations-pane {
            display: none !important;
        }
        .chat-active-mobile .thread-pane {
            display: flex !important;
        }
    }
</style>

<script>
    let activeConversationId = 0;
    let activePeerId = <?php echo $peer_id_preselect; ?>;
    let currentUserId = <?php echo $uid; ?>;
    let chatInterval = null;
    let selectedChatFile = null;
    let lastTypingTime = 0;

    const emojiList = ['😀', '😂', '😍', '👍', '🎉', '🔥', '👏', '🙌', '🌟', '💡', '🚀', '💯', '❤️', '💼', '🎓', '🤝', '📅', '📝'];

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

        // Initialize Emoji Picker
        initEmojiPicker();

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

        // If peer pre-selected (from Help Desk start chat link)
        if (activePeerId > 0) {
            startConversationWithPeer(activePeerId, "Opening support connection...");
        }
    });

    function initEmojiPicker() {
        const box = document.getElementById('emoji-picker-box');
        if (box) {
            box.innerHTML = '';
            emojiList.forEach(emoji => {
                const btn = document.createElement('span');
                btn.innerText = emoji;
                btn.style.fontSize = '1.25rem';
                btn.style.cursor = 'pointer';
                btn.style.textAlign = 'center';
                btn.style.padding = '0.2rem';
                btn.style.borderRadius = '4px';
                btn.style.transition = 'background 0.2s';
                btn.addEventListener('mouseenter', () => btn.style.background = 'rgba(255,255,255,0.08)');
                btn.addEventListener('mouseleave', () => btn.style.background = '');
                btn.addEventListener('click', () => {
                    const input = document.getElementById('chat-msg-input');
                    input.value += emoji;
                    box.style.display = 'none';
                    input.focus();
                });
                box.appendChild(btn);
            });
        }
    }

    function toggleEmojiPicker() {
        const box = document.getElementById('emoji-picker-box');
        if (box) {
            box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'grid' : 'none';
        }
    }

    function handleChatFileSelect(input) {
        if (input.files && input.files[0]) {
            selectedChatFile = input.files[0];
            document.getElementById('selected-file-name').innerText = selectedChatFile.name;
            document.getElementById('selected-file-preview').style.display = 'flex';
        }
    }

    function clearSelectedFile() {
        selectedChatFile = null;
        document.getElementById('chat-file-input').value = '';
        document.getElementById('selected-file-preview').style.display = 'none';
    }

    function handleTypingState() {
        const now = Date.now();
        if (now - lastTypingTime > 4000) {
            lastTypingTime = now;
            const pathPrefix = (document.querySelector('link[href*="assets/css/style.css"]')?.getAttribute('href') || '').split('assets/css/style.css')[0] || '';
            const formData = new FormData();
            formData.append('action', 'typing');
            formData.append('conversation_id', activeConversationId);
            fetch(`${pathPrefix}api/chat.php`, {
                method: 'POST',
                body: formData
            });
        }
    }

    function filterThreadMessages() {
        const query = document.getElementById('thread-msg-search').value.toLowerCase();
        document.querySelectorAll('#chat-messages-stream .chat-bubble').forEach(bubble => {
            const content = bubble.querySelector('.bubble-text-content')?.innerText.toLowerCase() || '';
            if (content.includes(query)) {
                bubble.style.display = '';
            } else {
                bubble.style.display = 'none';
            }
        });
    }

    function backToConversationsList() {
        const grid = document.querySelector('.messenger-grid');
        if (grid) {
            grid.classList.remove('chat-active-mobile');
        }
        if (chatInterval) {
            clearInterval(chatInterval);
            chatInterval = null;
        }
        activeConversationId = 0;
        activePeerId = 0;
    }

    function startConversationWithPeer(peerId, peerName) {
        activePeerId = peerId;
        activeConversationId = 0; // fetch/create via API send or thread
        
        // Show headers and placeholders
        document.getElementById('chat-thread-header').style.display = 'flex';
        document.getElementById('chat-input-container').style.display = 'block';
        document.getElementById('chat-stream-placeholder').style.display = 'none';

        document.getElementById('active-peer-name').innerText = peerName;
        document.getElementById('active-peer-role').innerText = 'CLIENT';
        document.getElementById('active-peer-avatar').src = 'https://cdn-icons-png.flaticon.com/512/149/149071.png';

        const grid = document.querySelector('.messenger-grid');
        if (grid) grid.classList.add('chat-active-mobile');

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

        const grid = document.querySelector('.messenger-grid');
        if (grid) grid.classList.add('chat-active-mobile');

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
                            
                            let attachmentHtml = '';
                            if (msg.attachment_path) {
                                if (msg.attachment_type === 'image') {
                                    attachmentHtml = `<a href="../${escapeHTML(msg.attachment_path)}" target="_blank"><img src="../${escapeHTML(msg.attachment_path)}" class="attachment-preview-img"></a>`;
                                } else {
                                    const filename = msg.attachment_path.split('/').pop().replace(/^chat_[0-9a-f]+_/, '');
                                    attachmentHtml = `<a href="../${escapeHTML(msg.attachment_path)}" download class="attachment-file-card"><i class="fa-solid fa-file-arrow-down"></i> ${escapeHTML(filename)}</a>`;
                                }
                            }

                            // Seen status visual check
                            let seenStatusHtml = '';
                            if (senderClass === 'sent') {
                                if (msg.is_read == 1) {
                                    seenStatusHtml = '<span style="color:#60a5fa;margin-left:0.25rem;" title="Seen"><i class="fa-solid fa-check-double"></i></span>';
                                } else {
                                    seenStatusHtml = '<span style="opacity:0.5;margin-left:0.25rem;" title="Delivered"><i class="fa-solid fa-check"></i></span>';
                                }
                            }

                            bubble.innerHTML = `
                                <div class="bubble-text-content">${escapeHTML(msg.message)}</div>
                                ${attachmentHtml}
                                <div style="display:flex; justify-content:flex-end; align-items:center; font-size:0.6rem; opacity:0.6; margin-top:0.25rem;">
                                    <span>${formatMsgTime(msg.created_at)}</span>
                                    ${seenStatusHtml}
                                </div>
                            `;
                            messagesContainer.appendChild(bubble);
                        });
                    } else {
                        messagesContainer.innerHTML = '<div style="text-align:center;color:var(--theme-text-secondary);font-size:0.8rem;padding:2rem;">Send a message to open this connection!</div>';
                    }

                    // Check typing status
                    const typingIndicator = document.getElementById('typing-indicator');
                    if (typingIndicator) {
                        if (data.peer_typing) {
                            document.getElementById('typing-name').innerText = document.getElementById('active-peer-name').innerText;
                            typingIndicator.style.display = 'block';
                        } else {
                            typingIndicator.style.display = 'none';
                        }
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
        if (!text && !selectedChatFile) return;

        input.value = '';

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('conversation_id', activeConversationId);
        formData.append('peer_id', activePeerId);
        formData.append('message', text);
        if (selectedChatFile) {
            formData.append('file', selectedChatFile);
        }

        clearSelectedFile();

        // Change button state to visual loading
        const sendBtn = document.getElementById('send-chat-btn');
        const originalContent = sendBtn.innerHTML;
        sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        sendBtn.disabled = true;

        fetch('../api/chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            sendBtn.innerHTML = originalContent;
            sendBtn.disabled = false;
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
        .catch(err => {
            sendBtn.innerHTML = originalContent;
            sendBtn.disabled = false;
            console.error("Error sending message:", err);
        });
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

    function deleteActiveChat() {
        if (activeConversationId <= 0) return;
        if (!confirm("Are you sure you want to delete this conversation? This will permanently delete all messages and attachments for both participants.")) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('conversation_id', activeConversationId);

        // Visual loading state
        const deleteBtn = document.getElementById('delete-chat-btn');
        const originalContent = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        deleteBtn.disabled = true;

        fetch('../api/chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            deleteBtn.innerHTML = originalContent;
            deleteBtn.disabled = false;
            if (data.status === 'success') {
                window.showToast ? window.showToast(data.message, 'success') : alert(data.message);
                
                // Hide header and inputs, and reset stream
                document.getElementById('chat-thread-header').style.display = 'none';
                document.getElementById('chat-input-container').style.display = 'none';
                document.getElementById('chat-stream-placeholder').style.display = 'flex';
                document.getElementById('chat-messages-stream').innerHTML = '';
                
                // Reset active chat variables and return to list
                backToConversationsList();
                loadConversationsList();
            } else {
                window.showToast ? window.showToast(data.message, 'error') : alert(data.message);
            }
        })
        .catch(err => {
            deleteBtn.innerHTML = originalContent;
            deleteBtn.disabled = false;
            console.error("Error deleting chat:", err);
            alert("An error occurred while deleting the chat.");
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

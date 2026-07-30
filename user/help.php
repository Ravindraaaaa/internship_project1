<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

require_login();
handle_session_timeout();

$uid = get_user_id();
$user_name = get_user_name();
$page_title = "Help & Support Center";

require_once __DIR__ . '/../includes/mailer_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

// Handle Support Ticket submission
$ticket_success = '';
$ticket_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!check_csrf($token)) {
        $ticket_error = 'CSRF verification failed.';
    } else {
        $category = trim(filter_var($_POST['category'] ?? 'General Support', FILTER_SANITIZE_SPECIAL_CHARS));
        $priority = trim(filter_var($_POST['priority'] ?? 'Medium', FILTER_SANITIZE_SPECIAL_CHARS));
        $subject  = trim(filter_var($_POST['subject'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $message  = trim(filter_var($_POST['message'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $attachment_path = null;

        if (empty($category) || empty($subject) || empty($message)) {
            $ticket_error = 'Please fill out all required ticket fields.';
        } else {
            try {
                $stmtDbUser = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                $stmtDbUser->execute([$uid]);
                $fetchedUser = $stmtDbUser->fetch();

                $user_email = !empty($fetchedUser['email']) ? $fetchedUser['email'] : ($_SESSION['user_email'] ?? get_user_email());
                if (empty($user_email)) {
                    $user_email = 'alumni@alumninet.edu';
                }
                if (!empty($fetchedUser['name'])) {
                    $user_name = $fetchedUser['name'];
                }

                $tkt_num = 'TKT-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

                // Screenshot / Attachment upload handling
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../uploads/attachments/';
                    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

                    $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
                    if (in_array($file_ext, $allowed_exts)) {
                        $saved_filename = 'tkt_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $target_file = $upload_dir . $saved_filename;
                        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                            $attachment_path = 'uploads/attachments/' . $saved_filename;
                        }
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO support_tickets (ticket_number, user_id, subject, category, priority, description, message, attachment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'New', NOW())");
                $stmt->execute([$tkt_num, $uid, $subject, $category, $priority, $message, $message, $attachment_path]);

                // 1. Send Email to User
                $user_email_html = build_enterprise_email_template(
                    "Support Ticket Created - #{$tkt_num}",
                    "<p>Hello <strong>" . htmlspecialchars($user_name) . "</strong>,</p>
                    <p>Your support ticket has been registered in our Enterprise Help Desk system.</p>
                    <div style='background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #3b82f6;'>
                        <p style='margin: 0 0 5px 0;'><strong>Ticket Number:</strong> {$tkt_num}</p>
                        <p style='margin: 0 0 5px 0;'><strong>Category:</strong> " . htmlspecialchars($category) . "</p>
                        <p style='margin: 0 0 5px 0;'><strong>Priority:</strong> " . htmlspecialchars($priority) . "</p>
                        <p style='margin: 0;'><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                    </div>
                    <p>Our support team will inspect your inquiry and reply shortly. You can track updates inside your Help Desk dashboard.</p>",
                    null,
                    null
                );
                send_logged_email($user_email, "Support Ticket Confirmation: {$tkt_num}", $user_email_html, $user_name, 'ticket_confirmation');

                // 2. Send Alert Email to Admin
                $admin_email_html = build_enterprise_email_template(
                    "New Support Ticket Received (#{$tkt_num})",
                    "<p>Admin Support Alert: A new support inquiry requires attention.</p>
                    <div style='background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ef4444;'>
                        <p style='margin: 0 0 5px 0;'><strong>User:</strong> " . htmlspecialchars($user_name) . " (" . htmlspecialchars($user_email) . ")</p>
                        <p style='margin: 0 0 5px 0;'><strong>Ticket Number:</strong> {$tkt_num}</p>
                        <p style='margin: 0 0 5px 0;'><strong>Category:</strong> " . htmlspecialchars($category) . " | <strong>Priority:</strong> " . htmlspecialchars($priority) . "</p>
                        <p style='margin: 0 0 5px 0;'><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                        <p style='margin: 0;'><strong>Description:</strong> " . nl2br(htmlspecialchars($message)) . "</p>
                    </div>",
                    null,
                    null
                );
                $admin_target_email = get_admin_email();
                send_logged_email($admin_target_email, "Admin Alert: New Ticket {$tkt_num}", $admin_email_html, 'Admin Team', 'admin_ticket_alert');

                // 3. In-App Notifications
                NotificationEngine::send([
                    'user_id' => $uid,
                    'type' => 'info',
                    'category' => 'support',
                    'title' => "Ticket Created ({$tkt_num})",
                    'message' => "Your ticket '{$subject}' was logged successfully.",
                    'icon' => 'headset',
                    'color' => 'indigo'
                ]);

                NotificationEngine::sendToRole('admin', [
                    'type' => 'warning',
                    'category' => 'support',
                    'title' => "New Ticket: {$tkt_num}",
                    'message' => "{$user_name} submitted a {$priority} priority ticket: '{$subject}'.",
                    'icon' => 'ticket',
                    'color' => 'rose'
                ]);

                log_activity($uid, 'submitted_help_ticket', "Ticket: $tkt_num - Category: $category - Subject: $subject");
                set_flash('success', "Your support ticket #{$tkt_num} has been submitted! A confirmation email was dispatched.");
                header('Location: help.php');
                exit;
            } catch (Exception $e) {
                $ticket_error = 'Failed to submit help ticket: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <?php render_sidebar('help'); ?>

    <!-- Main Workspace -->
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>

        <main class="dashboard-workspace">
            
            <!-- Hero Search Header -->
            <div class="card-glass" style="background: var(--theme-accent-gradient); border: none; color: #ffffff; padding: 2.5rem; border-radius: var(--border-radius-lg); margin-bottom: 2rem; text-align: center; position: relative; overflow: hidden;">
                <div style="position: relative; z-index: 2; max-width: 650px; margin: 0 auto;">
                    <i class="fa-solid fa-headset" style="font-size: 2.5rem; margin-bottom: 0.75rem;"></i>
                    <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: #ffffff;">How can we help you today?</h1>
                    <p style="opacity: 0.9; font-size: 0.95rem; margin-bottom: 1.5rem;">Explore user guides, system FAQs, or submit a support ticket directly to administrators.</p>
                    
                    <div style="position: relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: var(--theme-text-secondary);"></i>
                        <input type="text" id="help-search-input" class="input-glass" placeholder="Search help topics (e.g. 2FA, Mentorship, Passwords, Jobs)..." onkeyup="filterHelpTopics(this.value)" style="padding-left: 3rem; background: rgba(0, 0, 0, 0.4); border-color: rgba(255,255,255,0.2); color: #ffffff;">
                    </div>
                </div>
            </div>

            <?php if (!empty($ticket_error)): ?>
                <div class="card-glass" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25); color: #f87171; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-sm); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-circle-xmark"></i> <?php echo htmlspecialchars($ticket_error); ?>
                </div>
            <?php endif; ?>

            <!-- Quick FAQ Cards Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;" id="help-topics-grid">
                
                <!-- Topic 1 -->
                <div class="card-glass help-topic-card" data-keywords="account login 2fa password reset profile">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); display: flex; align-items: center; justify-content: center; color: var(--theme-accent-blue); font-size: 1.25rem;">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 1.1rem; font-weight: 700;">Account & Security</h3>
                            <span style="font-size: 0.75rem; color: var(--theme-text-secondary);">2FA, Passwords & Verification</span>
                        </div>
                    </div>
                    <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.88rem;">
                        <li><i class="fa-solid fa-angle-right" style="color: var(--theme-accent-purple); font-size: 0.75rem; margin-right: 0.4rem;"></i> <strong>How do I enable Mobile 2FA?</strong> Go to Profile Settings and toggle Two-Factor Auth.</li>
                        <li><i class="fa-solid fa-angle-right" style="color: var(--theme-accent-purple); font-size: 0.75rem; margin-right: 0.4rem;"></i> <strong>Forgot Password?</strong> Click 'Forgot Password' on the login screen to receive a secure link.</li>
                    </ul>
                </div>

                <!-- Topic 2 -->
                <div class="card-glass help-topic-card" data-keywords="mentorship alumni connect network messages chat">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(168, 85, 247, 0.1); border: 1px solid rgba(168, 85, 247, 0.3); display: flex; align-items: center; justify-content: center; color: var(--theme-accent-purple); font-size: 1.25rem;">
                            <i class="fa-solid fa-handshake-angle"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 1.1rem; font-weight: 700;">Mentorship & Chat</h3>
                            <span style="font-size: 0.75rem; color: var(--theme-text-secondary);">Alumni Connections & Messaging</span>
                        </div>
                    </div>
                    <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.88rem;">
                        <li><i class="fa-solid fa-angle-right" style="color: var(--theme-accent-purple); font-size: 0.75rem; margin-right: 0.4rem;"></i> <strong>Requesting a Mentor:</strong> Browse the Alumni Directory and click 'Request Mentorship'.</li>
                        <li><i class="fa-solid fa-angle-right" style="color: var(--theme-accent-purple); font-size: 0.75rem; margin-right: 0.4rem;"></i> <strong>Direct Messaging:</strong> Once connected, access real-time chat via the Messenger tab.</li>
                    </ul>
                </div>

                <!-- Topic 3 -->
                <div class="card-glass help-topic-card" data-keywords="jobs careers referral company placement internship">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); display: flex; align-items: center; justify-content: center; color: #22c55e; font-size: 1.25rem;">
                            <i class="fa-solid fa-briefcase"></i>
                        </div>
                        <div>
                            <h3 style="font-size: 1.1rem; font-weight: 700;">Job Board & Referrals</h3>
                            <span style="font-size: 0.75rem; color: var(--theme-text-secondary);">Career Postings & Applications</span>
                        </div>
                    </div>
                    <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.88rem;">
                        <li><i class="fa-solid fa-angle-right" style="color: var(--theme-accent-purple); font-size: 0.75rem; margin-right: 0.4rem;"></i> <strong>Posting Job Openings:</strong> Alumni and Admins can click 'Share Job Referral' on the Job Board.</li>
                        <li><i class="fa-solid fa-angle-right" style="color: var(--theme-accent-purple); font-size: 0.75rem; margin-right: 0.4rem;"></i> <strong>Applying:</strong> Click 'Apply Now' on active job cards to submit your profile.</li>
                    </ul>
                </div>

            </div>

            <!-- Submit Support Ticket Section -->
            <div class="card-glass" style="max-width: 800px; margin: 0 auto;">
                <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.65rem;">
                    <i class="fa-solid fa-envelope-open-text" style="color: var(--theme-accent-purple);"></i> Submit a Support Ticket
                </h3>
                <p style="color: var(--theme-text-secondary); font-size: 0.88rem; margin-bottom: 1.5rem;">
                    Can't find what you're looking for? Fill out the ticket below and our moderation team will respond promptly.
                </p>

                <form action="help.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="submit_ticket" value="1">

                    <div style="display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="category" class="form-label" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; display: block;">Topic Category</label>
                            <select name="category" id="category" class="input-glass" required>
                                <option value="Account & Login">Account & Login</option>
                                <option value="Mentorship & Chat">Mentorship & Chat</option>
                                <option value="Job Board & Referrals">Job Board & Referrals</option>
                                <option value="Events & RSVPs">Events & RSVPs</option>
                                <option value="General Query">General Query</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="priority" class="form-label" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; display: block;">Priority</label>
                            <select name="priority" id="priority" class="input-glass" required>
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="subject" class="form-label" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; display: block;">Ticket Subject</label>
                            <input type="text" name="subject" id="subject" class="input-glass" placeholder="Brief summary of your question..." required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message" class="form-label" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; display: block;">Detailed Explanation</label>
                        <textarea name="message" id="message" class="input-glass" rows="4" placeholder="Describe the issue or question in detail..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="attachment" class="form-label" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; display: block;">Screenshot / Attachment (Optional)</label>
                        <input type="file" name="attachment" id="attachment" class="input-glass" accept="image/*,.pdf,.doc,.docx,.zip">
                    </div>

                    <button type="submit" class="btn btn-primary" style="align-self: flex-start; padding: 0.75rem 1.8rem;">
                        <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                    </button>
                </form>
            </div>

            <?php
                $stmtHelpFeed = $pdo->prepare("SELECT * FROM feedback WHERE user_id = ? AND subject LIKE '[HELP TICKET%' ORDER BY created_at DESC LIMIT 10");
                $stmtHelpFeed->execute([$uid]);
                $help_tickets = $stmtHelpFeed->fetchAll();
            ?>

            <?php if ($help_tickets): ?>
                <div class="card-glass" style="margin-top: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-robot" style="color: var(--theme-accent-purple);"></i> Your Support Ticket AI Responses
                    </h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($help_tickets as $ht): ?>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); border-radius: 8px; padding: 1.25rem;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                    <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--theme-text); margin: 0;"><?php echo htmlspecialchars($ht['subject']); ?></h4>
                                    <span style="font-size: 0.75rem; color: var(--theme-text-secondary);"><?php echo date('M d, Y H:i', strtotime($ht['created_at'])); ?></span>
                                </div>
                                <p style="font-size: 0.85rem; color: var(--theme-text-secondary); margin-bottom: 0.85rem; line-height: 1.4;">
                                    <strong>Query:</strong> <?php echo htmlspecialchars($ht['message']); ?>
                                </p>

                                <?php if (!empty($ht['ai_reply'])): ?>
                                    <div style="background: rgba(168, 85, 247, 0.08); border-left: 3px solid var(--theme-accent-purple); padding: 0.85rem; border-radius: 0 6px 6px 0; font-size: 0.85rem;">
                                        <div style="font-weight: 700; color: var(--theme-accent-purple); margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fa-solid fa-envelope-circle-check"></i> AI Support Email Response Dispatched:
                                        </div>
                                        <div style="color: var(--theme-text); white-space: pre-line; line-height: 1.5; font-size: 0.82rem;">
                                            <?php echo htmlspecialchars($ht['ai_reply']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
    function filterHelpTopics(query) {
        const term = query.toLowerCase().trim();
        const cards = document.querySelectorAll('.help-topic-card');
        
        cards.forEach(card => {
            const keywords = card.getAttribute('data-keywords').toLowerCase();
            const text = card.textContent.toLowerCase();
            if (keywords.includes(term) || text.includes(term)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

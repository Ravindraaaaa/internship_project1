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

// Handle Support Ticket submission
$ticket_success = '';
$ticket_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!check_csrf($token)) {
        $ticket_error = 'CSRF verification failed.';
    } else {
        $category = trim(filter_var($_POST['category'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $subject = trim(filter_var($_POST['subject'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        $message = trim(filter_var($_POST['message'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));

        if (empty($category) || empty($subject) || empty($message)) {
            $ticket_error = 'Please fill out all required ticket fields.';
        } else {
            try {
                // Store support ticket in feedback table as help request
                $stmt = $pdo->prepare("INSERT INTO feedback (user_id, rating, subject, message) VALUES (?, 5, ?, ?)");
                $stmt->execute([$uid, "[HELP TICKET - $category] " . $subject, $message]);

                log_activity($uid, 'submitted_help_ticket', "Category: $category - Subject: $subject");
                set_flash('success', 'Your help ticket has been submitted! Support team will respond shortly.');
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
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h2>Help & Support Center</h2>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark / Bright Mode">
                    <i class="fa-solid fa-sun" id="theme-toggle-icon"></i>
                </button>
            </div>
        </nav>

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

                <form action="help.php" method="POST" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="submit_ticket" value="1">

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
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
                            <label for="subject" class="form-label" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; display: block;">Ticket Subject</label>
                            <input type="text" name="subject" id="subject" class="input-glass" placeholder="Brief summary of your question..." required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message" class="form-label" style="font-size: 0.82rem; font-weight: 600; margin-bottom: 0.4rem; display: block;">Detailed Explanation</label>
                        <textarea name="message" id="message" class="input-glass" rows="5" placeholder="Describe the issue or question in detail..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="align-self: flex-start; padding: 0.75rem 1.8rem;">
                        <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                    </button>
                </form>
            </div>

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

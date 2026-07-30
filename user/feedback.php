<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';

require_login();
handle_session_timeout();

$uid = get_user_id();
$role = get_user_role();
$user_name = get_user_name();
$page_title = "Submit Platform Feedback";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!check_csrf($token)) {
        set_flash('error', 'CSRF verification failed.');
    } else {
        $rating   = intval($_POST['rating'] ?? 5);
        $subject  = trim($_POST['subject'] ?? '');
        $category = trim($_POST['category'] ?? 'General Feedback');
        $message  = trim($_POST['message'] ?? '');
        $attachment_path = null;

        if ($rating < 1 || $rating > 5 || empty($subject) || empty($message)) {
            set_flash('error', 'Please fill in all required feedback form fields.');
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
                
                $feedback_id = 'FDB-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

                // File Attachment processing
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../uploads/attachments/';
                    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

                    $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
                    if (in_array($file_ext, $allowed_exts)) {
                        $saved_filename = 'fdb_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $target_file = $upload_dir . $saved_filename;
                        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                            $attachment_path = 'uploads/attachments/' . $saved_filename;
                        }
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO feedback (feedback_id, user_id, name, email, role, subject, category, rating, message, attachment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', NOW())");
                $stmt->execute([$feedback_id, $uid, $user_name, $user_email, $role, $subject, $category, $rating, $message, $attachment_path]);

                // 1. Send Confirmation Email to User
                $user_email_html = build_enterprise_email_template(
                    "Feedback Submitted - Ticket #{$feedback_id}",
                    "<p>Hello <strong>" . htmlspecialchars($user_name) . "</strong>,</p>
                    <p>Thank you for submitting feedback to AlumniNet. Your submission has been assigned Reference ID <strong>{$feedback_id}</strong>.</p>
                    <div style='background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #4f46e5;'>
                        <p style='margin: 0 0 5px 0;'><strong>Category:</strong> " . htmlspecialchars($category) . "</p>
                        <p style='margin: 0 0 5px 0;'><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                        <p style='margin: 0;'><strong>Rating:</strong> " . str_repeat('⭐', $rating) . "</p>
                    </div>
                    <p>Our platform administration team has been notified and will review your comments shortly.</p>",
                    null,
                    null
                );
                send_logged_email($user_email, "Feedback Received: {$feedback_id}", $user_email_html, $user_name, 'feedback_confirmation');

                // 2. Send Alert Email to Admin
                $admin_email_html = build_enterprise_email_template(
                    "New User Feedback Received ({$feedback_id})",
                    "<p>Admin Alert: A new feedback review has been posted on AlumniNet.</p>
                    <div style='background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #f59e0b;'>
                        <p style='margin: 0 0 5px 0;'><strong>User:</strong> " . htmlspecialchars($user_name) . " (" . htmlspecialchars($user_email) . " - " . ucfirst($role) . ")</p>
                        <p style='margin: 0 0 5px 0;'><strong>Category:</strong> " . htmlspecialchars($category) . "</p>
                        <p style='margin: 0 0 5px 0;'><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                        <p style='margin: 0 0 5px 0;'><strong>Rating:</strong> {$rating}/5 Stars</p>
                        <p style='margin: 0;'><strong>Message:</strong> " . nl2br(htmlspecialchars($message)) . "</p>
                    </div>",
                    null,
                    null
                );
                $admin_target_email = get_admin_email();
                send_logged_email($admin_target_email, "Admin Alert: New Feedback {$feedback_id}", $admin_email_html, 'Admin Team', 'admin_feedback_alert');

                // 3. In-App Notifications
                NotificationEngine::send([
                    'user_id' => $uid,
                    'type' => 'success',
                    'category' => 'system',
                    'title' => "Feedback Submitted ({$feedback_id})",
                    'message' => "Your feedback '{$subject}' has been received and sent to moderators.",
                    'icon' => 'comments',
                    'color' => 'emerald'
                ]);

                NotificationEngine::sendToRole('admin', [
                    'type' => 'info',
                    'category' => 'system',
                    'title' => "New Feedback: {$feedback_id}",
                    'message' => "{$user_name} ({$role}) submitted {$rating}-star feedback: '{$subject}'.",
                    'icon' => 'star',
                    'color' => 'amber'
                ]);

                log_activity($uid, 'submitted_feedback', "Ref: $feedback_id - Rating: $rating - Subject: $subject");
                set_flash('success', "Thank you! Your feedback (#{$feedback_id}) has been submitted and confirmation email sent.");
                header('Location: feedback.php');
                exit;
            } catch (Exception $e) {
                set_flash('error', 'Error submitting feedback: ' . $e->getMessage());
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <?php render_sidebar('feedback'); ?>

    <!-- Workspace Content -->
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>

        <main class="dashboard-workspace" style="max-width: 720px; margin: 0 auto; padding-top: 2rem;">
            
            <div class="card-glass">
                <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-comments" style="color: var(--theme-accent-purple); margin-right: 0.5rem;"></i> Write a Review</h3>
                <p style="color: var(--theme-text-secondary); font-size: 0.88rem; margin-bottom: 1.5rem;">
                    Help us improve AlumniNet. Share your experiences, report bugs, or request features directly to platform moderators.
                </p>

                <form action="feedback.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <!-- Star Rating Choice -->
                    <div class="form-group">
                        <label class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Overall Rating</label>
                        <div style="display: flex; gap: 0.5rem; font-size: 1.5rem;" class="rating-stars-row">
                            <input type="hidden" name="rating" id="rating-val" value="5">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="fa-solid fa-star star-btn" data-value="<?php echo $i; ?>" style="color: var(--theme-accent-purple); cursor:pointer;"></i>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="category" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Category</label>
                            <select name="category" id="category" class="input-glass" required>
                                <option value="General Feedback">General Feedback</option>
                                <option value="Feature Request">Feature Request</option>
                                <option value="UI & UX Bug">UI & UX Bug</option>
                                <option value="Mentorship Experience">Mentorship Experience</option>
                                <option value="Job Board Review">Job Board Review</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subject" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Topic / Subject</label>
                            <input type="text" name="subject" id="subject" class="input-glass" placeholder="e.g. Navigation issue, Mentorship feedback" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Your Detailed Review</label>
                        <textarea name="message" id="message" class="input-glass" rows="4" placeholder="Share your detailed feedback here..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="attachment" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.4rem; display:block;">Attachment / Screenshot (Optional)</label>
                        <input type="file" name="attachment" id="attachment" class="input-glass" accept="image/*,.pdf,.doc,.docx,.zip">
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem;"><i class="fa-solid fa-paper-plane"></i> Submit Feedback</button>
                    </div>
                </form>
            </div>

            <?php
                $stmtMyFeed = $pdo->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmtMyFeed->execute([$uid]);
                $my_tickets = $stmtMyFeed->fetchAll();
            ?>

            <?php if ($my_tickets): ?>
                <div class="card-glass" style="margin-top: 2rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-robot" style="color: var(--theme-accent-purple);"></i> Your AI Support Email Responses
                    </h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($my_tickets as $t): 
                            $f_st = strtolower($t['status'] ?? 'new');
                            $f_st_color = ($f_st === 'resolved') ? '#10b981' : (($f_st === 'pending' || $f_st === 'in progress') ? '#f59e0b' : '#38bdf8');
                            $f_st_bg = ($f_st === 'resolved') ? 'rgba(16,185,129,0.15)' : (($f_st === 'pending' || $f_st === 'in progress') ? 'rgba(245,158,11,0.15)' : 'rgba(56,189,248,0.15)');
                        ?>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); border-radius: 8px; padding: 1.25rem;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                    <div>
                                        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--theme-text); margin: 0; display: inline-block; margin-right: 0.5rem;"><?php echo htmlspecialchars($t['subject']); ?></h4>
                                        <span class="badge" style="background: <?php echo $f_st_bg; ?>; color: <?php echo $f_st_color; ?>; border: 1px solid <?php echo $f_st_color; ?>40; font-weight: 700; font-size: 0.72rem;">
                                            <?php echo htmlspecialchars(ucfirst($t['status'] ?? 'Pending')); ?>
                                        </span>
                                    </div>
                                    <span style="font-size: 0.75rem; color: var(--theme-text-secondary);"><?php echo date('M d, Y H:i', strtotime($t['created_at'])); ?></span>
                                </div>
                                <p style="font-size: 0.85rem; color: var(--theme-text-secondary); margin-bottom: 0.85rem; line-height: 1.4;">
                                    <strong>Your Request:</strong> <?php echo htmlspecialchars($t['message']); ?>
                                </p>

                                <?php if (!empty($t['admin_reply'])): ?>
                                    <div style="background: rgba(16, 185, 129, 0.08); border-left: 3px solid #10b981; padding: 0.85rem; border-radius: 0 6px 6px 0; font-size: 0.85rem; margin-top: 0.5rem;">
                                        <div style="font-weight: 700; color: #10b981; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fa-solid fa-comments"></i> Admin Resolution / Reply:
                                        </div>
                                        <div style="color: var(--theme-text); white-space: pre-line; line-height: 1.5; font-size: 0.82rem;">
                                            <?php echo htmlspecialchars($t['admin_reply']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($t['ai_reply'])): ?>
                                    <div style="background: rgba(168, 85, 247, 0.08); border-left: 3px solid var(--theme-accent-purple); padding: 0.85rem; border-radius: 0 6px 6px 0; font-size: 0.85rem; margin-top: 0.5rem;">
                                        <div style="font-weight: 700; color: var(--theme-accent-purple); margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fa-solid fa-paper-plane"></i> AI Support Email Response Dispatched:
                                        </div>
                                        <div style="color: var(--theme-text); white-space: pre-line; line-height: 1.5; font-size: 0.82rem;">
                                            <?php echo htmlspecialchars($t['ai_reply']); ?>
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
    document.addEventListener('DOMContentLoaded', () => {
        const stars = document.querySelectorAll('.star-btn');
        const ratingInput = document.getElementById('rating-val');

        stars.forEach(star => {
            star.addEventListener('click', () => {
                const val = parseInt(star.getAttribute('data-value'));
                ratingInput.value = val;

                // Color stars
                stars.forEach(s => {
                    const sVal = parseInt(s.getAttribute('data-value'));
                    if (sVal <= val) {
                        s.style.color = 'var(--theme-accent-purple)';
                    } else {
                        s.style.color = 'var(--theme-border)';
                    }
                });
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

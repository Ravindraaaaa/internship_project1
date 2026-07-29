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
$page_title = "Submit Platform Feedback";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    $token = $_POST['csrf_token'] ?? '';
    if (!check_csrf($token)) {
        set_flash('error', 'CSRF verification failed.');
    } else {
        $rating = intval($_POST['rating'] ?? 5);
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($rating < 1 || $rating > 5 || empty($subject) || empty($message)) {
            set_flash('error', 'Please fill in all feedback form fields.');
        } else {
            try {
                $user_email = $_SESSION['user_email'] ?? '';
                $ai_reply = generate_ai_support_reply($user_name, $user_email, $subject, $message, "Feedback ({$rating} Stars)");
                
                $stmt = $pdo->prepare("INSERT INTO feedback (user_id, rating, subject, message, ai_reply) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$uid, $rating, $subject, $message, $ai_reply]);
                
                // Dispatch automatic notifications with AI response
                create_notification($uid, "AI Support Response: " . $subject, "Our AI Support system analyzed your ticket and dispatched an intelligent resolution email response.", "success", "medium", "user/feedback.php");
                notify_admins("New Feedback Ticket", "User " . $user_name . " submitted a " . $rating . "-star ticket: '" . $subject . "'.", "info", "high");

                log_activity($uid, 'submitted_feedback', "Rating: $rating - Subject: $subject");
                set_flash('success', 'Thank you! Our AI Support Agent has generated and dispatched an automated resolution email response.');
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

                <form action="feedback.php" method="POST" style="display: flex; flex-direction: column; gap: 1.25rem;">
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

                    <div class="form-group">
                        <label for="subject" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Topic / Subject</label>
                        <input type="text" name="subject" id="subject" class="input-glass" placeholder="e.g. Navigation issue, Mentorship feedback" required>
                    </div>

                    <div class="form-group">
                        <label for="message" class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Your Detailed Review</label>
                        <textarea name="message" id="message" class="input-glass" rows="5" placeholder="Share your detailed feedback here..." required></textarea>
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
                        <?php foreach ($my_tickets as $t): ?>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); border-radius: 8px; padding: 1.25rem;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                    <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--theme-text); margin: 0;"><?php echo htmlspecialchars($t['subject']); ?></h4>
                                    <span style="font-size: 0.75rem; color: var(--theme-text-secondary);"><?php echo date('M d, Y H:i', strtotime($t['created_at'])); ?></span>
                                </div>
                                <p style="font-size: 0.85rem; color: var(--theme-text-secondary); margin-bottom: 0.85rem; line-height: 1.4;">
                                    <strong>Your Request:</strong> <?php echo htmlspecialchars($t['message']); ?>
                                </p>

                                <?php if (!empty($t['ai_reply'])): ?>
                                    <div style="background: rgba(168, 85, 247, 0.08); border-left: 3px solid var(--theme-accent-purple); padding: 0.85rem; border-radius: 0 6px 6px 0; font-size: 0.85rem;">
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

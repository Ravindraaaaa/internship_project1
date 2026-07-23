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
                $stmt = $pdo->prepare("INSERT INTO feedback (user_id, rating, subject, message) VALUES (?, ?, ?, ?)");
                $stmt->execute([$uid, $rating, $subject, $message]);
                
                log_activity($uid, 'submitted_feedback', "Rating: $rating - Subject: $subject");
                set_flash('success', 'Thank you for your valuable feedback!');
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

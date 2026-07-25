<?php
$page_title = "Notification Preferences";
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_login();

$user_id = get_user_id();

$stmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
$stmt->execute([$user_id]);
$prefs = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prefs) {
    $prefs = [
        'chat_notif' => 1,
        'announcement_notif' => 1,
        'job_notif' => 1,
        'mentorship_notif' => 1,
        'application_notif' => 1,
        'security_notif' => 1,
        'email_notif' => 1
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php render_sidebar('notifications'); ?>
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        <main class="dashboard-workspace">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-sliders" style="color: #a855f7;"></i> Notification Delivery Settings
            </h1>
            <p style="color: var(--theme-text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                Control which channels and categories trigger in-app alerts and notifications.
            </p>
        </div>
    </div>

    <div class="glass-card" style="padding: 2rem; border-radius: 16px; max-width: 680px;">
        <form id="notifPrefForm" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div>
                    <h4 style="color: var(--theme-text-primary); font-size: 1rem; margin-bottom: 0.25rem;"><i class="fa-solid fa-comments" style="color: #818cf8; width: 22px;"></i> Direct Messages & Chat</h4>
                    <p style="color: var(--theme-text-muted); font-size: 0.85rem;">Alert when someone sends you a direct message or replies.</p>
                </div>
                <input type="checkbox" name="chat_notif" value="1" <?php echo $prefs['chat_notif'] ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: #6366f1;">
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div>
                    <h4 style="color: var(--theme-text-primary); font-size: 1rem; margin-bottom: 0.25rem;"><i class="fa-solid fa-bullhorn" style="color: #f59e0b; width: 22px;"></i> Official Announcements</h4>
                    <p style="color: var(--theme-text-muted); font-size: 0.85rem;">Important campus news and admin broadcasts.</p>
                </div>
                <input type="checkbox" name="announcement_notif" value="1" <?php echo $prefs['announcement_notif'] ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: #6366f1;">
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div>
                    <h4 style="color: var(--theme-text-primary); font-size: 1rem; margin-bottom: 0.25rem;"><i class="fa-solid fa-briefcase" style="color: #38bdf8; width: 22px;"></i> Job & Internship Alerts</h4>
                    <p style="color: var(--theme-text-muted); font-size: 0.85rem;">Alerts when new job opportunities are posted.</p>
                </div>
                <input type="checkbox" name="job_notif" value="1" <?php echo $prefs['job_notif'] ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: #6366f1;">
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div>
                    <h4 style="color: var(--theme-text-primary); font-size: 1rem; margin-bottom: 0.25rem;"><i class="fa-solid fa-user-graduate" style="color: #c084fc; width: 22px;"></i> Mentorship Activity</h4>
                    <p style="color: var(--theme-text-muted); font-size: 0.85rem;">Status changes for mentorship requests, acceptances, and rejections.</p>
                </div>
                <input type="checkbox" name="mentorship_notif" value="1" <?php echo $prefs['mentorship_notif'] ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: #6366f1;">
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div>
                    <h4 style="color: var(--theme-text-primary); font-size: 1rem; margin-bottom: 0.25rem;"><i class="fa-solid fa-shield-halved" style="color: #f87171; width: 22px;"></i> Security & Login Alerts</h4>
                    <p style="color: var(--theme-text-muted); font-size: 0.85rem;">Alerts for new device logins, password changes, and 2FA activities.</p>
                </div>
                <input type="checkbox" name="security_notif" value="1" <?php echo $prefs['security_notif'] ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: #6366f1;">
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div>
                    <h4 style="color: var(--theme-text-primary); font-size: 1rem; margin-bottom: 0.25rem;"><i class="fa-solid fa-envelope" style="color: #4ade80; width: 22px;"></i> Email Digest Notifications</h4>
                    <p style="color: var(--theme-text-muted); font-size: 0.85rem;">Send urgent security and application updates to your registered email.</p>
                </div>
                <input type="checkbox" name="email_notif" value="1" <?php echo $prefs['email_notif'] ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: #6366f1;">
            </div>

            <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Preferences
                </button>
            </div>
        </form>
    </div>
</main>

<script>
document.getElementById('notifPrefForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'save_preferences');

    fetch('../api/notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            NotificationApp.showToast('Settings Saved', 'Notification delivery preferences updated successfully', 'success');
        } else {
            NotificationApp.showToast('Error', data.message || 'Failed to save preferences', 'error');
        }
    });
});
</script>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
$page_title = "Activity History";
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_logger.php';

require_login();

$user_id = get_user_id();
$history = ActivityLogger::getUserHistory($user_id, 100);

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
                <i class="fa-solid fa-clock-rotate-left" style="color: #38bdf8;"></i> Account Activity History
            </h1>
            <p style="color: var(--theme-text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                Detailed audit timeline of your logins, profile updates, applications, and security events.
            </p>
        </div>
    </div>

    <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
        <?php if (empty($history)): ?>
            <div class="notif-empty-state" style="padding: 3rem 1rem;">
                <i class="fa-solid fa-history notif-empty-icon" style="font-size: 3rem;"></i>
                <div class="notif-empty-title" style="font-size: 1.1rem; margin-top: 0.5rem;">No activity logged yet</div>
                <div class="notif-empty-desc">Your platform actions will be automatically tracked here.</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($history as $item): ?>
                    <?php 
                        $icon = 'fa-circle-dot';
                        $color = '#818cf8';
                        $cat = $item['category'] ?? 'general';
                        if ($cat === 'security') { $icon = 'fa-shield-halved'; $color = '#f87171'; }
                        elseif ($cat === 'jobs') { $icon = 'fa-briefcase'; $color = '#38bdf8'; }
                        elseif ($cat === 'mentorship') { $icon = 'fa-user-graduate'; $color = '#c084fc'; }
                        elseif ($cat === 'profile') { $icon = 'fa-user-gear'; $color = '#4ade80'; }
                    ?>
                    <div style="display: flex; align-items: flex-start; gap: 1rem; padding: 1rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px;">
                        <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 1rem; color: <?php echo $color; ?>; flex-shrink: 0;">
                            <i class="fa-solid <?php echo $icon; ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.25rem;">
                                <span style="font-weight: 600; color: var(--theme-text-primary); font-size: 0.95rem;"><?php echo htmlspecialchars($item['action']); ?></span>
                                <span style="font-size: 0.8rem; color: var(--theme-text-muted);"><?php echo date('M d, Y H:i:s', strtotime($item['created_at'])); ?></span>
                            </div>
                            <?php if (!empty($item['details'])): ?>
                                <div style="font-size: 0.85rem; color: var(--theme-text-muted); margin-bottom: 0.4rem;"><?php echo htmlspecialchars($item['details']); ?></div>
                            <?php endif; ?>
                            <div style="display: flex; gap: 1rem; font-size: 0.75rem; color: #64748b;">
                                <span><i class="fa-solid fa-network-wired"></i> IP: <?php echo htmlspecialchars($item['ip_address'] ?? 'Local'); ?></span>
                                <span><i class="fa-solid fa-laptop"></i> Device: <?php echo htmlspecialchars($item['browser'] ?? 'Browser'); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

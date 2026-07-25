<?php
$page_title = "Admin Notification & Activity Center";
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_logger.php';

if (!is_admin()) {
    header("Location: ../login.php");
    exit;
}

// Fetch Admin System Timeline Data
$recent_activities = $pdo->query("SELECT a.*, u.name as user_name, u.role as user_role 
                                 FROM activity_logs a 
                                 LEFT JOIN users u ON a.user_id = u.id 
                                 ORDER BY a.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$security_alerts = $pdo->query("SELECT * FROM activity_logs WHERE category = 'security' ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
$recent_registrations = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php render_sidebar('admin'); ?>
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        <main class="dashboard-workspace">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-user-shield" style="color: #a855f7;"></i> Admin Notification & System Activity Center
            </h1>
            <p style="color: var(--theme-text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                Unified real-time timeline of user registrations, security alerts, mentorship requests, and system actions.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="announcement_analytics.php" class="btn btn-secondary"><i class="fa-solid fa-chart-pie"></i> Announcement Analytics</a>
            <a href="audit_logs.php" class="btn btn-primary"><i class="fa-solid fa-clipboard-check"></i> Admin Audit Logs</a>
        </div>
    </div>

    <!-- Analytics Quick Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="glass-card" style="padding: 1.25rem; border-radius: 14px; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #818cf8; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--theme-text-primary);"><?php echo count($recent_registrations); ?></div>
                <div style="font-size: 0.85rem; color: var(--theme-text-muted);">Recent Sign-ups</div>
            </div>
        </div>

        <div class="glass-card" style="padding: 1.25rem; border-radius: 14px; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); color: #f87171; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--theme-text-primary);"><?php echo count($security_alerts); ?></div>
                <div style="font-size: 0.85rem; color: var(--theme-text-muted);">Security Events</div>
            </div>
        </div>

        <div class="glass-card" style="padding: 1.25rem; border-radius: 14px; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(56, 189, 248, 0.15); color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--theme-text-primary);"><?php echo count($recent_activities); ?></div>
                <div style="font-size: 0.85rem; color: var(--theme-text-muted);">Logged Activities</div>
            </div>
        </div>
    </div>

    <!-- Timeline Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        <!-- Left: Live System Activity Timeline -->
        <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--theme-text-primary); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-stream" style="color: #6366f1;"></i> Live System Activity Stream
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($recent_activities as $act): ?>
                    <div style="display: flex; gap: 1rem; padding: 0.85rem 1rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; align-items: center;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(99, 102, 241, 0.1); color: #818cf8; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fa-solid fa-activity"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.15rem;">
                                <span style="font-size: 0.9rem; font-weight: 600; color: var(--theme-text-primary);"><?php echo htmlspecialchars($act['user_name'] ?? 'System User'); ?></span>
                                <span style="font-size: 0.75rem; color: #64748b;"><?php echo date('M d, H:i', strtotime($act['created_at'])); ?></span>
                            </div>
                            <div style="font-size: 0.82rem; color: var(--theme-text-muted);"><?php echo htmlspecialchars($act['action']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Recent Registrations & Security -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Recent Signups -->
            <div class="glass-card" style="padding: 1.25rem; border-radius: 16px;">
                <h3 style="font-size: 1rem; font-weight: 700; color: var(--theme-text-primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-user-plus" style="color: #4ade80;"></i> Latest User Registrations
                </h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($recent_registrations as $user): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <div>
                                <div style="font-weight: 600; color: var(--theme-text-primary);"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--theme-text-muted);"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <span class="role-pill pill-<?php echo strtolower($user['role']); ?>" style="font-size: 0.7rem;"><?php echo ucfirst($user['role']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Security Log -->
            <div class="glass-card" style="padding: 1.25rem; border-radius: 16px;">
                <h3 style="font-size: 1rem; font-weight: 700; color: var(--theme-text-primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-shield-cat" style="color: #f87171;"></i> Security Events
                </h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach (array_slice($security_alerts, 0, 5) as $sec): ?>
                        <div style="font-size: 0.82rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <div style="color: #f87171; font-weight: 600;"><?php echo htmlspecialchars($sec['action']); ?></div>
                            <div style="font-size: 0.75rem; color: var(--theme-text-muted); margin-top: 2px;"><?php echo date('M d, H:i', strtotime($sec['created_at'])); ?> • IP: <?php echo htmlspecialchars($sec['ip_address'] ?? '127.0.0.1'); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

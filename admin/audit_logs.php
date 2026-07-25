<?php
$page_title = "Admin Audit Trail";
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_logger.php';

if (!is_admin()) {
    header("Location: ../login.php");
    exit;
}

$audit_logs = ActivityLogger::getAuditLogs(100);

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
                <i class="fa-solid fa-clipboard-check" style="color: #6366f1;"></i> Administrative Audit Trail
            </h1>
            <p style="color: var(--theme-text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                Complete security audit of administrative modifications, settings changes, user role shifts, and deletes.
            </p>
        </div>
        <div>
            <a href="admin_notifications.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Admin Dashboard</a>
        </div>
    </div>

    <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
        <?php if (empty($audit_logs)): ?>
            <div class="notif-empty-state" style="padding: 3rem 1rem;">
                <i class="fa-solid fa-clipboard-list notif-empty-icon" style="font-size: 3rem;"></i>
                <div class="notif-empty-title" style="font-size: 1.1rem; margin-top: 0.5rem;">No administrative audit logs recorded yet</div>
                <div class="notif-empty-desc">Future admin actions will automatically generate security audit logs here.</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($audit_logs as $log): ?>
                    <div style="padding: 1.25rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span class="role-pill pill-admin" style="font-size: 0.75rem;"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($log['admin_name'] ?? 'Admin'); ?></span>
                                <span style="font-weight: 700; color: var(--theme-text-primary); font-size: 1rem;"><?php echo htmlspecialchars($log['action']); ?></span>
                            </div>
                            <span style="font-size: 0.8rem; color: #64748b;"><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></span>
                        </div>

                        <?php if (!empty($log['affected_name'])): ?>
                            <div style="font-size: 0.85rem; color: var(--theme-text-muted); margin-bottom: 0.5rem;">
                                Affected User: <strong><?php echo htmlspecialchars($log['affected_name']); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($log['old_value']) || !empty($log['new_value'])): ?>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.75rem; background: rgba(0,0,0,0.2); padding: 0.75rem; border-radius: 8px; font-size: 0.8rem;">
                                <div>
                                    <div style="color: #f87171; font-weight: 600; margin-bottom: 0.25rem;">Old Value:</div>
                                    <code style="color: #cbd5e1; word-break: break-all;"><?php echo htmlspecialchars($log['old_value'] ?? 'None'); ?></code>
                                </div>
                                <div>
                                    <div style="color: #4ade80; font-weight: 600; margin-bottom: 0.25rem;">New Value:</div>
                                    <code style="color: #cbd5e1; word-break: break-all;"><?php echo htmlspecialchars($log['new_value'] ?? 'None'); ?></code>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; gap: 1.5rem; font-size: 0.75rem; color: #64748b; margin-top: 0.75rem;">
                            <span><i class="fa-solid fa-network-wired"></i> IP: <?php echo htmlspecialchars($log['ip_address'] ?? 'Local'); ?></span>
                            <span><i class="fa-solid fa-laptop"></i> Agent: <?php echo htmlspecialchars($log['browser'] ?? 'Browser'); ?></span>
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

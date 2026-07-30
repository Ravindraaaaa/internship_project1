<?php
$page_title = "Admin Notification & Broadcast Control Center";
$is_subfolder = true;
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) {
    header("Location: ../login.php");
    exit;
}

$admin_id = get_user_id();
$current_tab = $_GET['tab'] ?? 'broadcast';

// 1. Process Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'broadcast_notification') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target_audience = trim($_POST['target_audience'] ?? 'all');
        $type = trim($_POST['type'] ?? 'info');
        $priority = trim($_POST['priority'] ?? 'medium');
        $link = trim($_POST['link'] ?? '');

        if (empty($title) || empty($message)) {
            set_flash('error', 'Notification title and message body are required.');
        } else {
            try {
                $role_filter = ($target_audience === 'all') ? null : $target_audience;
                
                if ($target_audience === 'admin') {
                    notify_admins($title, $message, $type, $priority, $link);
                } else {
                    notify_all_users($title, $message, $type, $priority, $role_filter, $link);
                }

                log_admin_audit($admin_id, "Dispatched broadcast notification: {$title} to " . strtoupper($target_audience));
                set_flash('success', "Broadcast notification sent to " . strtoupper($target_audience) . "!");
            } catch (Exception $e) {
                set_flash('error', 'Failed to dispatch broadcast: ' . $e->getMessage());
            }
        }
        header("Location: admin_notifications.php?tab=history");
        exit;
    }

    if ($action === 'delete_notification') {
        $notif_id = intval($_POST['notification_id'] ?? 0);
        if ($notif_id > 0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                $stmtDel->execute([$notif_id]);
                log_admin_audit($admin_id, "Deleted notification ID #{$notif_id}");
                set_flash('success', 'Notification entry deleted successfully.');
            } catch (Exception $e) {
                set_flash('error', 'Failed to delete notification: ' . $e->getMessage());
            }
        }
        header("Location: admin_notifications.php?tab=history");
        exit;
    }
}

// 2. Fetch System Data & Counter Analytics
$total_notifications_count = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$students_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$alumni_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'alumni'")->fetchColumn();
$security_alerts_count = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE category = 'security'")->fetchColumn();

// Fetch Data for Sub-pages
$notifications_history = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

$recent_activities = $pdo->query("SELECT a.*, u.name as user_name, u.role as user_role 
                                 FROM activity_logs a 
                                 LEFT JOIN users u ON a.user_id = u.id 
                                 ORDER BY a.created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

$security_alerts = $pdo->query("SELECT * FROM activity_logs WHERE category = 'security' ORDER BY created_at DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
$recent_registrations = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <?php render_sidebar('admin'); ?>
    
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        
        <main class="dashboard-workspace">
            
            <!-- PAGE TOOLBAR HEADER -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--theme-text-primary); display: flex; align-items: center; gap: 0.65rem;">
                        <i class="fa-solid fa-bullhorn" style="color: #818cf8;"></i> Notification Manager
                    </h1>
                    <p style="color: var(--theme-text-muted); font-size: 0.88rem; margin-top: 0.2rem;">
                        Select a sub-page below to send broadcasts, view history logs, or audit security events.
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="announcement_analytics.php" class="btn btn-secondary btn-small"><i class="fa-solid fa-chart-line"></i> Analytics</a>
                    <a href="audit_logs.php" class="btn btn-secondary btn-small"><i class="fa-solid fa-clipboard-check"></i> Audit Logs</a>
                </div>
            </div>

            <!-- COMPACT KPI COUNTER BAR -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="card-glass" style="padding: 1rem; border-radius: 12px; display: flex; align-items: center; gap: 0.85rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(99, 102, 241, 0.18); color: #818cf8; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--theme-text);"><?php echo number_format($total_notifications_count); ?></div>
                        <div style="font-size: 0.76rem; color: var(--theme-text-secondary); font-weight:600;">Dispatches</div>
                    </div>
                </div>

                <div class="card-glass" style="padding: 1rem; border-radius: 12px; display: flex; align-items: center; gap: 0.85rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.18); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--theme-text);"><?php echo number_format($students_count); ?></div>
                        <div style="font-size: 0.76rem; color: var(--theme-text-secondary); font-weight:600;">Students</div>
                    </div>
                </div>

                <div class="card-glass" style="padding: 1rem; border-radius: 12px; display: flex; align-items: center; gap: 0.85rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(168, 85, 247, 0.18); color: #c084fc; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--theme-text);"><?php echo number_format($alumni_count); ?></div>
                        <div style="font-size: 0.76rem; color: var(--theme-text-secondary); font-weight:600;">Alumni</div>
                    </div>
                </div>

                <div class="card-glass" style="padding: 1rem; border-radius: 12px; display: flex; align-items: center; gap: 0.85rem;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.18); color: #f87171; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fa-solid fa-shield-cat"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--theme-text);"><?php echo number_format($security_alerts_count); ?></div>
                        <div style="font-size: 0.76rem; color: var(--theme-text-secondary); font-weight:600;">Security Alerts</div>
                    </div>
                </div>
            </div>

            <!-- SUB-PAGE NAV TABS (SMALL SMALL PAGES) -->
            <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--theme-border); padding-bottom: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <button type="button" id="tab-btn-broadcast" class="btn <?php echo $current_tab === 'broadcast' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="switchAdminTab('broadcast')" style="padding: 0.55rem 1.1rem; font-size: 0.85rem; border-radius: 8px;">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 0.4rem;"></i> 1. Send Broadcast
                </button>
                <button type="button" id="tab-btn-history" class="btn <?php echo $current_tab === 'history' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="switchAdminTab('history')" style="padding: 0.55rem 1.1rem; font-size: 0.85rem; border-radius: 8px;">
                    <i class="fa-solid fa-clock-rotate-left" style="margin-right: 0.4rem;"></i> 2. Sent History Log (<?php echo count($notifications_history); ?>)
                </button>
                <button type="button" id="tab-btn-registrations" class="btn <?php echo $current_tab === 'registrations' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="switchAdminTab('registrations')" style="padding: 0.55rem 1.1rem; font-size: 0.85rem; border-radius: 8px;">
                    <i class="fa-solid fa-user-plus" style="margin-right: 0.4rem;"></i> 3. Recent Sign-ups (<?php echo count($recent_registrations); ?>)
                </button>
                <button type="button" id="tab-btn-security" class="btn <?php echo $current_tab === 'security' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="switchAdminTab('security')" style="padding: 0.55rem 1.1rem; font-size: 0.85rem; border-radius: 8px;">
                    <i class="fa-solid fa-shield-halved" style="margin-right: 0.4rem;"></i> 4. Security & Activity (<?php echo count($security_alerts); ?>)
                </button>
            </div>

            <!-- ================= SUB-PAGE 1: SEND BROADCAST FORM ================= -->
            <div id="subpage-broadcast" class="admin-subpage" style="display: <?php echo $current_tab === 'broadcast' ? 'block' : 'none'; ?>;">
                <div class="card-glass" style="padding: 1.75rem; border-radius: 16px; max-width: 800px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--theme-border); padding-bottom: 0.75rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--theme-text);"><i class="fa-solid fa-paper-plane" style="color: #818cf8;"></i> Create & Send Broadcast</h3>
                        <span class="badge badge-student">Quick Composer</span>
                    </div>

                    <!-- 1-CLICK PRESETS -->
                    <div style="margin-bottom: 1.25rem;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--theme-text-secondary); text-transform: uppercase; margin-bottom: 0.4rem; display: block;">1-Click Template Fill:</label>
                        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                            <button type="button" class="btn btn-secondary btn-small template-preset-btn" id="btn-tpl-event" onclick="fillTemplate('event')"><i class="fa-solid fa-calendar-check" style="color:#60a5fa;"></i> Campus Event</button>
                            <button type="button" class="btn btn-secondary btn-small template-preset-btn" id="btn-tpl-job" onclick="fillTemplate('job')"><i class="fa-solid fa-briefcase" style="color:#34d399;"></i> Job Referral</button>
                            <button type="button" class="btn btn-secondary btn-small template-preset-btn" id="btn-tpl-maint" onclick="fillTemplate('maint')"><i class="fa-solid fa-screwdriver-wrench" style="color:#fbbf24;"></i> Maintenance</button>
                            <button type="button" class="btn btn-secondary btn-small template-preset-btn" id="btn-tpl-urgent" onclick="fillTemplate('urgent')"><i class="fa-solid fa-triangle-exclamation" style="color:#f87171;"></i> Urgent Alert</button>
                        </div>
                    </div>

                    <!-- Selected Template Banner Preview -->
                    <div id="selected-template-preview" style="display: none; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); padding: 1rem; border-radius: 10px; margin-bottom: 1.25rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #818cf8; margin-bottom: 0.3rem;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Selected Broadcast Template Preview
                        </div>
                        <div id="preview-tpl-title" style="font-weight: 700; color: var(--theme-text); font-size: 0.95rem;"></div>
                        <div id="preview-tpl-msg" style="font-size: 0.85rem; color: var(--theme-text-secondary); margin-top: 0.25rem;"></div>
                    </div>

                    <form action="admin_notifications.php" method="POST">
                        <input type="hidden" name="action" value="broadcast_notification">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.82rem; font-weight:700; margin-bottom: 0.35rem; display:block;">Target Audience</label>
                                <select name="target_audience" id="notif_target" class="input-glass" required>
                                    <option value="all">📢 All Members (Students & Alumni)</option>
                                    <option value="student">🎓 Students Only</option>
                                    <option value="alumni">💼 Alumni Only</option>
                                    <option value="admin">🛡️ Admins Only</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.82rem; font-weight:700; margin-bottom: 0.35rem; display:block;">Alert Style & Color</label>
                                <select name="type" id="notif_type" class="input-glass" required>
                                    <option value="info">🔵 Info (Blue)</option>
                                    <option value="success">🟢 Success (Green)</option>
                                    <option value="warning">🟡 Warning (Amber)</option>
                                    <option value="danger">🔴 Urgent Alert (Red)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="font-size: 0.82rem; font-weight:700; margin-bottom: 0.35rem; display:block;">Notification Title</label>
                            <input type="text" name="title" id="notif_title" class="input-glass" placeholder="e.g. Annual Alumni Meet Registration Open" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="font-size: 0.82rem; font-weight:700; margin-bottom: 0.35rem; display:block;">Message Body</label>
                            <textarea name="message" id="notif_message" class="input-glass" rows="3" placeholder="Enter notification message text..." required></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="font-size: 0.82rem; font-weight:600; margin-bottom: 0.35rem; display:block;">Optional Action URL Link</label>
                            <input type="url" name="link" id="notif_link" class="input-glass" placeholder="https://alumninet.org/events.php">
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="reset" class="btn btn-secondary">Reset</button>
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.4rem;"><i class="fa-solid fa-paper-plane"></i> Send Broadcast</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ================= SUB-PAGE 2: HISTORY LOG ================= -->
            <div id="subpage-history" class="admin-subpage" style="display: <?php echo $current_tab === 'history' ? 'block' : 'none'; ?>;">
                <div class="card-glass" style="padding: 1.75rem; border-radius: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--theme-text);"><i class="fa-solid fa-clock-rotate-left" style="color: #34d399;"></i> Delivered Notifications Log</h3>
                        <span class="badge badge-alumni"><?php echo count($notifications_history); ?> Total Delivered</span>
                    </div>

                    <?php if (!empty($notifications_history)): ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($notifications_history as $notif): 
                                $n_type = $notif['type'] ?? 'info';
                                $bg_color = 'rgba(99, 102, 241, 0.15)';
                                $txt_color = '#818cf8';
                                if ($n_type === 'success') { $bg_color = 'rgba(16, 185, 129, 0.15)'; $txt_color = '#34d399'; }
                                if ($n_type === 'warning') { $bg_color = 'rgba(245, 158, 11, 0.15)'; $txt_color = '#fbbf24'; }
                                if ($n_type === 'danger') { $bg_color = 'rgba(239, 68, 68, 0.15)'; $txt_color = '#f87171'; }
                            ?>
                                <div style="display: flex; gap: 1rem; padding: 0.9rem 1.1rem; background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); border-radius: 10px; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; gap: 0.85rem; align-items: center;">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: <?php echo $bg_color; ?>; color: <?php echo $txt_color; ?>; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                                            <i class="fa-solid fa-bell"></i>
                                        </div>
                                        <div>
                                            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                                <strong style="font-size: 0.92rem; color: var(--theme-text);"><?php echo htmlspecialchars($notif['title']); ?></strong>
                                                <span class="badge" style="background: <?php echo $bg_color; ?>; color: <?php echo $txt_color; ?>; font-size: 0.68rem; padding: 0.1rem 0.45rem; text-transform: uppercase;"><?php echo htmlspecialchars($n_type); ?></span>
                                                <span style="font-size: 0.74rem; color: var(--theme-text-secondary);">Target: <strong><?php echo htmlspecialchars(strtoupper($notif['receiver_role'] ?? 'ALL')); ?></strong></span>
                                            </div>
                                            <div style="font-size: 0.82rem; color: var(--theme-text-secondary); margin-top: 0.15rem;"><?php echo htmlspecialchars($notif['message']); ?></div>
                                            <div style="font-size: 0.72rem; color: #64748b; margin-top: 0.25rem;"><i class="fa-solid fa-clock"></i> Sent: <?php echo date('M d, Y - h:i A', strtotime($notif['created_at'])); ?></div>
                                        </div>
                                    </div>

                                    <form action="admin_notifications.php" method="POST" onsubmit="return confirm('Delete this notification log entry?');" style="margin: 0;">
                                        <input type="hidden" name="action" value="delete_notification">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-small" style="padding: 0.3rem 0.6rem; color: #f87171; border-color: rgba(239, 68, 68, 0.3);" title="Delete Entry"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--theme-text-secondary); text-align: center; padding: 2rem;">No notification log entries found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================= SUB-PAGE 3: RECENT SIGNUPS ================= -->
            <div id="subpage-registrations" class="admin-subpage" style="display: <?php echo $current_tab === 'registrations' ? 'block' : 'none'; ?>;">
                <div class="card-glass" style="padding: 1.75rem; border-radius: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--theme-text);"><i class="fa-solid fa-user-plus" style="color: #60a5fa;"></i> Recent User Registrations</h3>
                        <span class="badge badge-student"><?php echo count($recent_registrations); ?> New Sign-ups</span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($recent_registrations as $usr): ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); border-radius: 10px;">
                                <div>
                                    <div style="font-weight: 700; color: var(--theme-text); font-size: 0.92rem;"><?php echo htmlspecialchars($usr['name']); ?></div>
                                    <div style="font-size: 0.78rem; color: var(--theme-text-secondary);"><?php echo htmlspecialchars($usr['email']); ?> • Signed up: <?php echo date('M d, Y', strtotime($usr['created_at'])); ?></div>
                                </div>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <span class="role-pill pill-<?php echo strtolower($usr['role']); ?>" style="font-size: 0.72rem; padding: 0.2rem 0.65rem; text-transform: uppercase;"><?php echo ucfirst($usr['role']); ?></span>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.7rem; padding: 0.2rem 0.55rem;"><?php echo ucfirst($usr['status']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ================= SUB-PAGE 4: SECURITY & ACTIVITY AUDIT ================= -->
            <div id="subpage-security" class="admin-subpage" style="display: <?php echo $current_tab === 'security' ? 'block' : 'none'; ?>;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    
                    <!-- Security Events Card -->
                    <div class="card-glass" style="padding: 1.5rem; border-radius: 16px;">
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--theme-text); margin-bottom: 1rem;"><i class="fa-solid fa-shield-cat" style="color: #f87171;"></i> Security Events Feed</h3>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($security_alerts as $sec): ?>
                                <div style="padding: 0.65rem 0.85rem; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px;">
                                    <div style="color: #f87171; font-weight: 700; font-size: 0.85rem;"><?php echo htmlspecialchars($sec['action']); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--theme-text-secondary); margin-top: 0.15rem;"><?php echo date('M d, H:i:s', strtotime($sec['created_at'])); ?> • IP: <?php echo htmlspecialchars($sec['ip_address'] ?? '127.0.0.1'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- System Activity Stream Card -->
                    <div class="card-glass" style="padding: 1.5rem; border-radius: 16px;">
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--theme-text); margin-bottom: 1rem;"><i class="fa-solid fa-stream" style="color: #60a5fa;"></i> Live System Activity Log</h3>
                        <div style="display: flex; flex-direction: column; gap: 0.65rem; max-height: 420px; overflow-y: auto;">
                            <?php foreach ($recent_activities as $act): ?>
                                <div style="padding: 0.6rem 0.85rem; background: rgba(255,255,255,0.02); border: 1px solid var(--theme-border); border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <strong style="font-size: 0.85rem; color: var(--theme-text);"><?php echo htmlspecialchars($act['user_name'] ?? 'System User'); ?></strong>
                                        <span style="font-size: 0.72rem; color: #64748b;"><?php echo date('M d, H:i', strtotime($act['created_at'])); ?></span>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--theme-text-secondary); margin-top: 0.15rem;"><?php echo htmlspecialchars($act['action']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>

        </main>
    </div>
</div>

<script>
function switchAdminTab(tabName) {
    const tabs = ['broadcast', 'history', 'registrations', 'security'];
    tabs.forEach(t => {
        const subpage = document.getElementById('subpage-' + t);
        const btn = document.getElementById('tab-btn-' + t);
        if (subpage && btn) {
            if (t === tabName) {
                subpage.style.display = 'block';
                btn.className = 'btn btn-primary';
            } else {
                subpage.style.display = 'none';
                btn.className = 'btn btn-secondary';
            }
        }
    });
}

function fillTemplate(type) {
    const titleInput = document.getElementById('notif_title');
    const msgInput = document.getElementById('notif_message');
    const typeSelect = document.getElementById('notif_type');
    const targetSelect = document.getElementById('notif_target');
    const previewContainer = document.getElementById('selected-template-preview');
    const previewTitle = document.getElementById('preview-tpl-title');
    const previewMsg = document.getElementById('preview-tpl-msg');

    if (type === 'event') {
        titleInput.value = "🎓 Campus Tech Summit 2026 Registration Open!";
        msgInput.value = "Join us for the Annual Campus Tech Summit featuring keynote talks from alumni leaders. Register now on the Events portal!";
        typeSelect.value = "info";
        targetSelect.value = "all";
    } else if (type === 'job') {
        titleInput.value = "💼 New High-Priority Job Referral Posted";
        msgInput.value = "An alumni member has shared a new job referral opportunity for Software Engineering roles. Apply today on the Job Board!";
        typeSelect.value = "success";
        targetSelect.value = "student";
    } else if (type === 'maint') {
        titleInput.value = "⚡ Scheduled System Maintenance Alert";
        msgInput.value = "AlumniNet will undergo brief database optimization on Sunday between 02:00 AM - 03:00 AM UTC. Portals will remain fully accessible.";
        typeSelect.value = "warning";
        targetSelect.value = "all";
    } else if (type === 'urgent') {
        titleInput.value = "⚠️ Important Campus Announcement";
        msgInput.value = "Please review your profile details and verify your contact information to ensure seamless campus networking.";
        typeSelect.value = "danger";
        targetSelect.value = "all";
    }

    if (previewContainer && previewTitle && previewMsg) {
        previewTitle.textContent = titleInput.value;
        previewMsg.textContent = msgInput.value;
        previewContainer.style.display = 'block';
    }

    document.querySelectorAll('.template-preset-btn').forEach(btn => {
        btn.style.borderColor = 'var(--theme-border)';
    });
    const activeBtn = document.getElementById('btn-tpl-' + type);
    if (activeBtn) {
        activeBtn.style.borderColor = 'var(--theme-accent-purple)';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

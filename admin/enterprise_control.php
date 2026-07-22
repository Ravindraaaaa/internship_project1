<?php
$is_subfolder = true;

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

require_admin();
handle_session_timeout();

$admin_id = get_user_id();
$user_name = $_SESSION['admin_name'];
$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png';

$page_title = "Enterprise Control Center";
$tab = $_GET['tab'] ?? 'roles';

// --- POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Single User Control
    if ($action === 'user_control') {
        $target_id = intval($_POST['user_id'] ?? 0);
        $op = $_POST['operation'] ?? ''; // 'approve', 'reject', 'block', 'restore', 'delete', 'change_role'
        
        if ($target_id > 0 && $target_id !== $admin_id) {
            $pdo->beginTransaction();
            if ($op === 'approve') {
                $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?")->execute([$target_id]);
                log_activity($admin_id, 'approve_user', "Approved user ID: $target_id");
                set_flash('success', 'User approved successfully!');
            } elseif ($op === 'reject') {
                $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")->execute([$target_id]);
                log_activity($admin_id, 'reject_user', "Rejected user ID: $target_id");
                set_flash('warning', 'User registration rejected.');
            } elseif ($op === 'block') {
                $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ?")->execute([$target_id]);
                log_activity($admin_id, 'block_user', "Suspended/Blocked user ID: $target_id");
                set_flash('error', 'User account suspended.');
            } elseif ($op === 'restore') {
                $pdo->prepare("UPDATE users SET status = 'approved', failed_attempts = 0, lockout_until = NULL WHERE id = ?")->execute([$target_id]);
                log_activity($admin_id, 'restore_user', "Restored and unlocked user ID: $target_id");
                set_flash('success', 'User account restored and unlocked!');
            } elseif ($op === 'delete') {
                $pdo->prepare("DELETE FROM alumni_profiles WHERE user_id = ?")->execute([$target_id]);
                $pdo->prepare("DELETE FROM student_profiles WHERE user_id = ?")->execute([$target_id]);
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
                log_activity($admin_id, 'delete_user', "Deleted user ID: $target_id");
                set_flash('success', 'User permanently deleted.');
            } elseif ($op === 'change_role') {
                $new_role = $_POST['role'] ?? 'student';
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $target_id]);
                log_activity($admin_id, 'change_role', "Changed user ID $target_id role to $new_role");
                set_flash('success', 'User role updated.');
            }
            $pdo->commit();
        } else {
            set_flash('error', 'Cannot perform operation on yourself or invalid user.');
        }
        header("Location: enterprise_control.php?tab=" . urlencode($tab));
        exit;
    } 
    
    // 2. Bulk Operations
    elseif ($action === 'bulk_control') {
        $selected_ids = $_POST['selected_users'] ?? [];
        $op = $_POST['operation'] ?? ''; // 'bulk_approve', 'bulk_reject', 'bulk_delete'
        
        if ($selected_ids) {
            $pdo->beginTransaction();
            foreach ($selected_ids as $uid) {
                $uid = intval($uid);
                if ($uid === $admin_id) continue;
                
                if ($op === 'bulk_approve') {
                    $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?")->execute([$uid]);
                } elseif ($op === 'bulk_reject') {
                    $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")->execute([$uid]);
                } elseif ($op === 'bulk_delete') {
                    $pdo->prepare("DELETE FROM alumni_profiles WHERE user_id = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM student_profiles WHERE user_id = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
                }
            }
            $pdo->commit();
            log_activity($admin_id, $op, "Performed bulk operations on " . count($selected_ids) . " users.");
            set_flash('success', 'Bulk operation completed successfully!');
        } else {
            set_flash('error', 'No users selected.');
        }
        header("Location: enterprise_control.php?tab=" . urlencode($tab));
        exit;
    }
    
    // 3. Import CSV
    elseif ($action === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, "r");
            if ($handle !== FALSE) {
                $header = fgetcsv($handle, 1000, ","); // skip headers
                $imported = 0;
                $default_pass = password_hash('User@123', PASSWORD_BCRYPT);
                
                $pdo->beginTransaction();
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 4) {
                        $name = trim($data[0]);
                        $email = trim($data[1]);
                        $role = trim($data[2]); // 'student', 'alumni'
                        $status = trim($data[3] ?? 'approved');
                        
                        $username = explode('@', $email)[0];
                        
                        // Check uniqueness
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if (!$stmt->fetch()) {
                            $stmtIns = $pdo->prepare("INSERT INTO users (name, email, username, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmtIns->execute([$name, $email, $username, $default_pass, $role, $status]);
                            $new_uid = $pdo->lastInsertId();
                            
                            // Insert corresponding profile
                            if ($role === 'student') {
                                $pdo->prepare("INSERT INTO student_profiles (user_id, current_year, course, bio) VALUES (?, 1, 'Computer Science Engineering', 'Imported student profile.')")->execute([$new_uid]);
                            } else {
                                $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, course, bio) VALUES (?, 2024, 'Computer Science Engineering', 'Imported alumnus profile.')")->execute([$new_uid]);
                            }
                            $imported++;
                        }
                    }
                }
                $pdo->commit();
                fclose($handle);
                log_activity($admin_id, 'import_csv_users', "Imported $imported users via CSV file.");
                set_flash('success', "Successfully imported $imported users! Passwords set to 'User@123'");
            } else {
                set_flash('error', 'Failed to open uploaded CSV file.');
            }
        } else {
            set_flash('error', 'Please upload a valid CSV file.');
        }
        header("Location: enterprise_control.php?tab=csv");
        exit;
    }
    
    // 4. Restore Database
    elseif ($action === 'restore_db') {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $sql = file_get_contents($_FILES['backup_file']['tmp_name']);
            try {
                // Disable foreign keys for clean restoration
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                $pdo->exec($sql);
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                
                log_activity($admin_id, 'db_restoration', 'Completed database restoration.');
                set_flash('success', 'Database restored successfully!');
            } catch (Exception $e) {
                set_flash('error', 'Restore failed: ' . $e->getMessage());
            }
        } else {
            set_flash('error', 'Please upload a valid backup SQL file.');
        }
        header("Location: enterprise_control.php?tab=db");
        exit;
    }
    
    // 5. Update settings
    elseif ($action === 'update_settings') {
        $ai_prompt = trim($_POST['ai_prompt'] ?? '');
        $gemini_api_key = trim($_POST['gemini_api_key'] ?? '');
        
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('ai_prompt', ?) ON DUPLICATE KEY UPDATE `value` = ?");
        $stmt->execute([$ai_prompt, $ai_prompt]);
        
        $stmtKey = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('gemini_api_key', ?) ON DUPLICATE KEY UPDATE `value` = ?");
        $stmtKey->execute([$gemini_api_key, $gemini_api_key]);
        
        log_activity($admin_id, 'update_ai_settings', 'Updated custom prompt and Gemini API configurations.');
        set_flash('success', 'AI settings updated successfully!');
        header("Location: enterprise_control.php?tab=settings");
        exit;
    }
}

// --- EXPORT OPERATIONS ---
// 1. Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="alumninet_users_' . time() . '.csv"');
    
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['Name', 'Email', 'Username', 'Role', 'Status', 'Registered At']);
    
    $stmt = $pdo->query("SELECT name, email, username, role, status, created_at FROM users ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    exit;
}

// 2. Database Backup Download
if (isset($_GET['export']) && $_GET['export'] === 'db') {
    ob_end_clean();
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="alumninet_backup_' . date('Y-m-d_H-i-s') . '.sql"');
    
    $tables = ['departments', 'users', 'admins', 'alumni_profiles', 'student_profiles', 'companies', 'jobs', 'job_applications', 'events', 'event_rsvps', 'announcements', 'notifications', 'conversations', 'messages', 'skills', 'user_skills', 'education', 'experience', 'resumes', 'activity_logs', 'settings', 'themes', 'backgrounds', 'password_resets', 'login_history', 'mentorship_requests', 'requirements', 'job_requirements', 'event_requirements', 'user_certificates', 'ai_chats'];
    
    $sql_dump = "-- AlumniNet Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Fetch drop & create table structure
        try {
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch();
            if ($row) {
                $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql_dump .= $row[1] . ";\n\n";
                
                // Fetch data
                $stmtData = $pdo->query("SELECT * FROM `$table`");
                $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
                if ($rows) {
                    $sql_dump .= "INSERT INTO `$table` VALUES \n";
                    $insert_vals = [];
                    foreach ($rows as $r) {
                        $escaped_vals = array_map(function($v) use ($pdo) {
                            if ($v === null) return 'NULL';
                            return $pdo->quote($v);
                        }, $r);
                        $insert_vals[] = "(" . implode(", ", $escaped_vals) . ")";
                    }
                    $sql_dump .= implode(",\n", $insert_vals) . ";\n\n";
                }
            }
        } catch (Exception $e) {
            // Table doesn't exist yet, skip
        }
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    echo $sql_dump;
    exit;
}

// --- DATA FETCH ---
// User listing with pagination
$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$page = intval($_GET['p'] ?? 1);
$limit = 15;
$offset = ($page - 1) * $limit;

$query = "SELECT id, name, email, role, status, created_at FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($filter_role !== 'all') {
    $query .= " AND role = ?";
    $params[] = $filter_role;
}
if ($filter_status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter_status;
}

// Count total
$stmtCount = $pdo->prepare(str_replace("id, name, email, role, status, created_at", "COUNT(*)", $query));
$stmtCount->execute($params);
$total_users = $stmtCount->fetchColumn();
$total_pages = ceil($total_users / $limit);

$query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users_list = $stmt->fetchAll();

// Settings
$stmtPrompt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'ai_prompt'");
$stmtPrompt->execute();
$ai_prompt = $stmtPrompt->fetchColumn() ?: "Hello! I am the AlumniNet Intelligent Assistant. Ask me anything about placement events, active jobs or profile score!";

$stmtKey = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'gemini_api_key'");
$stmtKey->execute();
$gemini_api_key = $stmtKey->fetchColumn() ?: "";

// Charts stats
$stats_month = $pdo->query("SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as qty FROM users GROUP BY month ORDER BY created_at ASC LIMIT 6")->fetchAll();
$stats_jobs = $pdo->query("SELECT type, COUNT(*) as qty FROM jobs GROUP BY type")->fetchAll();
$stats_ratios = $pdo->query("SELECT role, COUNT(*) as qty FROM users GROUP BY role")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <!-- ==================== SIDEBAR ==================== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="logo logo-text">
                <i class="fa-solid fa-graduation-cap"></i> AlumniNet
            </a>
            <button class="sidebar-toggle-btn" id="sidebar-toggle">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; text-align: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;" class="sidebar-profile-box">
            <img src="<?php echo htmlspecialchars($sidebar_avatar); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--theme-accent-purple);" class="user-sidebar-avatar">
            <div style="margin-top: 0.75rem;" class="link-text">
                <h4 style="font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;"><?php echo htmlspecialchars($user_name); ?></h4>
                <p style="font-size: 0.72rem; color: var(--theme-text-secondary); text-transform: uppercase;">Admin Portal</p>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="dashboard.php?tab=overview"><i data-lucide="gauge"></i> <span class="link-text">Dashboard</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=alumni"><i data-lucide="user-check"></i> <span class="link-text">Manage Alumni</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=students"><i data-lucide="users"></i> <span class="link-text">Manage Students</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=jobs"><i data-lucide="briefcase"></i> <span class="link-text">Manage Jobs</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=events"><i data-lucide="calendar"></i> <span class="link-text">Manage Events</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=messages"><i data-lucide="messages-square"></i> <span class="link-text">System Messages</span></a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard.php?tab=reports"><i data-lucide="line-chart"></i> <span class="link-text">Reports</span></a>
            </li>
            <li class="sidebar-item">
                <a href="requirements.php"><i data-lucide="shield-check"></i> <span class="link-text">Requirements</span></a>
            </li>
            <li class="sidebar-item active">
                <a href="enterprise_control.php"><i data-lucide="settings-2"></i> <span class="link-text">Control Center</span></a>
            </li>
            <li class="sidebar-item" style="margin-top: auto; border-top: 1px solid var(--theme-border); padding-top: 1rem;">
                <a href="../logout.php" style="color: var(--accent-danger);"><i data-lucide="log-out"></i> <span class="link-text">Sign Out</span></a>
            </li>
        </ul>
    </aside>

    <!-- ==================== MAIN WORKSPACE ==================== -->
    <div class="dashboard-content-area">
        <!-- Top Navbar -->
        <nav class="top-nav">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--theme-text);">Enterprise Control Center</h3>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark/Bright Mode">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
        </nav>

        <main class="dashboard-workspace" style="padding: 2rem;">
            <!-- Horizontal Sub-Tabs -->
            <div style="display:flex; gap:0.5rem; margin-bottom: 2rem; border-bottom: 1px solid var(--theme-border); padding-bottom: 0.75rem;">
                <a href="enterprise_control.php?tab=roles" class="btn <?php echo $tab === 'roles' ? 'btn-primary' : 'btn-secondary'; ?> btn-small"><i class="fa-solid fa-users-gear"></i> Roles & User Admin</a>
                <a href="enterprise_control.php?tab=charts" class="btn <?php echo $tab === 'charts' ? 'btn-primary' : 'btn-secondary'; ?> btn-small"><i class="fa-solid fa-chart-pie"></i> Visual Charts</a>
                <a href="enterprise_control.php?tab=csv" class="btn <?php echo $tab === 'csv' ? 'btn-primary' : 'btn-secondary'; ?> btn-small"><i class="fa-solid fa-file-csv"></i> CSV Import/Export</a>
                <a href="enterprise_control.php?tab=db" class="btn <?php echo $tab === 'db' ? 'btn-primary' : 'btn-secondary'; ?> btn-small"><i class="fa-solid fa-database"></i> DB Utilities</a>
                <a href="enterprise_control.php?tab=settings" class="btn <?php echo $tab === 'settings' ? 'btn-primary' : 'btn-secondary'; ?> btn-small"><i class="fa-solid fa-sliders"></i> AI Settings</a>
            </div>

            <!-- TAB 1: ROLES & USER ADMIN -->
            <?php if ($tab === 'roles'): ?>
                <div class="card-glass" style="padding:2rem; border-radius:var(--border-radius-lg); background:var(--theme-card); border:1px solid var(--theme-border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <h3 style="font-size:1.15rem; font-weight:700; color:#ffffff;">System Accounts Directory</h3>
                        
                        <!-- Search / filters -->
                        <form action="enterprise_control.php" method="GET" style="display:flex; gap:0.5rem;">
                            <input type="hidden" name="tab" value="roles">
                            <input type="text" name="search" class="input-glass" style="padding:0.4rem 0.8rem; font-size:0.82rem;" placeholder="Search name/email..." value="<?php echo htmlspecialchars($search); ?>">
                            <select name="role" class="input-glass" style="padding:0.4rem; font-size:0.82rem;">
                                <option value="all">All Roles</option>
                                <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="alumni" <?php echo $filter_role === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <select name="status" class="input-glass" style="padding:0.4rem; font-size:0.82rem;">
                                <option value="all">All Status</option>
                                <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="blocked" <?php echo $filter_status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-small"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                        </form>
                    </div>

                    <!-- User management forms -->
                    <form action="enterprise_control.php" method="POST" id="bulk-users-form">
                        <input type="hidden" name="action" value="bulk_control">
                        <input type="hidden" name="operation" id="bulk-op-input" value="">
                        
                        <!-- Bulk action buttons -->
                        <div style="display:flex; gap:0.5rem; margin-bottom:1rem; align-items:center;">
                            <span style="font-size:0.82rem; color:var(--theme-text-secondary);">Bulk Operations:</span>
                            <button type="button" class="btn btn-secondary btn-small" onclick="runBulk('bulk_approve')" style="font-size:0.75rem; padding:0.35rem 0.7rem;"><i class="fa-solid fa-circle-check" style="color:#22c55e;"></i> Approve</button>
                            <button type="button" class="btn btn-secondary btn-small" onclick="runBulk('bulk_reject')" style="font-size:0.75rem; padding:0.35rem 0.7rem;"><i class="fa-solid fa-circle-xmark" style="color:#eab308;"></i> Reject</button>
                            <button type="button" class="btn btn-danger btn-small" onclick="runBulk('bulk_delete')" style="font-size:0.75rem; padding:0.35rem 0.7rem;"><i class="fa-solid fa-trash-can"></i> Delete</button>
                        </div>

                        <!-- Modern Table -->
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.85rem;" class="modern-table">
                                <thead>
                                    <tr style="border-bottom: 1px solid var(--theme-border); color:var(--theme-text-secondary);">
                                        <th style="padding:0.85rem;"><input type="checkbox" id="check-all-users" onclick="toggleCheckAll(this)"></th>
                                        <th style="padding:0.85rem;">Name</th>
                                        <th style="padding:0.85rem;">Email</th>
                                        <th style="padding:0.85rem;">Role</th>
                                        <th style="padding:0.85rem;">Status</th>
                                        <th style="padding:0.85rem; text-align:right;">Operations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users_list as $u): ?>
                                        <tr style="border-bottom:1px solid var(--theme-border); transition:background-color 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.01)'" onmouseout="this.style.background='none'">
                                            <td style="padding:0.85rem;">
                                                <?php if ($u['id'] !== $admin_id): ?>
                                                    <input type="checkbox" name="selected_users[]" value="<?php echo $u['id']; ?>" class="user-checkbox">
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:0.85rem; font-weight:600; color:#ffffff;"><?php echo htmlspecialchars($u['name']); ?></td>
                                            <td style="padding:0.85rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td style="padding:0.85rem; text-transform:uppercase;">
                                                <span style="font-size:0.75rem; background:rgba(255,255,255,0.05); padding:0.15rem 0.4rem; border-radius:3px; font-weight:600;"><?php echo htmlspecialchars($u['role']); ?></span>
                                            </td>
                                            <td style="padding:0.85rem;">
                                                <?php 
                                                    $c = '#3b82f6';
                                                    if ($u['status'] === 'approved') $c = '#22c55e';
                                                    elseif ($u['status'] === 'rejected') $c = '#eab308';
                                                    elseif ($u['status'] === 'blocked') $c = '#ef4444';
                                                ?>
                                                <span style="color:<?php echo $c; ?>; font-weight:700;"><i class="fa-solid fa-circle" style="font-size:0.5rem; margin-right:0.3rem;"></i> <?php echo ucfirst($u['status']); ?></span>
                                            </td>
                                            <td style="padding:0.85rem; text-align:right; display:flex; justify-content:flex-end; gap:0.4rem;">
                                                <?php if ($u['id'] !== $admin_id): ?>
                                                    <?php if ($u['status'] === 'pending' || $u['status'] === 'rejected'): ?>
                                                        <button type="button" class="btn btn-secondary btn-small" style="padding:0.3rem 0.6rem; font-size:0.75rem;" onclick="runSingle(<?php echo $u['id']; ?>, 'approve')"><i class="fa-solid fa-check"></i></button>
                                                    <?php endif; ?>
                                                    <?php if ($u['status'] === 'approved'): ?>
                                                        <button type="button" class="btn btn-secondary btn-small" style="padding:0.3rem 0.6rem; font-size:0.75rem;" onclick="runSingle(<?php echo $u['id']; ?>, 'block')" title="Block User"><i class="fa-solid fa-ban" style="color:#ef4444;"></i></button>
                                                    <?php endif; ?>
                                                    <?php if ($u['status'] === 'blocked'): ?>
                                                        <button type="button" class="btn btn-secondary btn-small" style="padding:0.3rem 0.6rem; font-size:0.75rem;" onclick="runSingle(<?php echo $u['id']; ?>, 'restore')" title="Unlock Account"><i class="fa-solid fa-key" style="color:#22c55e;"></i></button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-danger btn-small" style="padding:0.3rem 0.6rem; font-size:0.75rem;" onclick="runSingle(<?php echo $u['id']; ?>, 'delete')" title="Delete permanently"><i class="fa-solid fa-trash"></i></button>
                                                <?php else: ?>
                                                    <span style="font-size:0.72rem; color:var(--theme-text-secondary); font-style:italic;">Self Account</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div style="display:flex; justify-content:center; gap:0.25rem; margin-top:1.5rem;">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="enterprise_control.php?tab=roles&search=<?php echo urlencode($search); ?>&role=<?php echo $filter_role; ?>&status=<?php echo $filter_status; ?>&p=<?php echo $i; ?>" class="btn <?php echo $page === $i ? 'btn-primary' : 'btn-secondary'; ?> btn-small" style="padding:0.3rem 0.65rem;"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 2: VISUAL CHARTS -->
            <?php if ($tab === 'charts'): ?>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
                    <!-- Registrations Growth Chart -->
                    <div class="card-glass" style="padding:2rem; border-radius:var(--border-radius-lg); background:var(--theme-card); border:1px solid var(--theme-border);">
                        <h3 style="font-size:1.05rem; font-weight:700; color:#ffffff; margin-bottom:1.5rem;"><i class="fa-solid fa-chart-line" style="color:var(--theme-accent-blue);"></i> Student & Alumni Signups (Growth)</h3>
                        <canvas id="chart-growth" style="max-height:260px;"></canvas>
                    </div>

                    <!-- Job post classifications -->
                    <div class="card-glass" style="padding:2rem; border-radius:var(--border-radius-lg); background:var(--theme-card); border:1px solid var(--theme-border);">
                        <h3 style="font-size:1.05rem; font-weight:700; color:#ffffff; margin-bottom:1.5rem;"><i class="fa-solid fa-chart-pie" style="color:var(--theme-accent-purple);"></i> Job Categories Classification</h3>
                        <canvas id="chart-jobs" style="max-height:260px;"></canvas>
                    </div>
                </div>

                <!-- Chart.js scripts -->
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // 1. Growth chart
                        const ctxGrowth = document.getElementById('chart-growth').getContext('2d');
                        new Chart(ctxGrowth, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode(array_column($stats_month, 'month')); ?>,
                                datasets: [{
                                    label: 'Registrations',
                                    data: <?php echo json_encode(array_column($stats_month, 'qty')); ?>,
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.05)',
                                    fill: true,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: { legend: { display: false } },
                                scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.03)' } }, x: { grid: { color: 'rgba(255,255,255,0.03)' } } }
                            }
                        });

                        // 2. Jobs Classification chart
                        const ctxJobs = document.getElementById('chart-jobs').getContext('2d');
                        new Chart(ctxJobs, {
                            type: 'doughnut',
                            data: {
                                labels: <?php echo json_encode(array_column($stats_jobs, 'type')); ?>,
                                datasets: [{
                                    data: <?php echo json_encode(array_column($stats_jobs, 'qty')); ?>,
                                    backgroundColor: ['#2563eb', '#8b5cf6', '#10b981', '#f59e0b']
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: { legend: { position: 'bottom', labels: { color: '#9ca3af' } } }
                            }
                        });
                    });
                </script>
            <?php endif; ?>

            <!-- TAB 3: CSV IMPORT/EXPORT -->
            <?php if ($tab === 'csv'): ?>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
                    <!-- Import CSV form -->
                    <div class="card-glass" style="padding:2rem; border-radius:var(--border-radius-lg); background:var(--theme-card); border:1px solid var(--theme-border);">
                        <h3 style="font-size:1.15rem; font-weight:700; color:#ffffff; margin-bottom:1.25rem;"><i class="fa-solid fa-file-arrow-up" style="color:var(--theme-accent-blue);"></i> Bulk Import Users CSV</h3>
                        <p style="font-size:0.82rem; color:var(--theme-text-secondary); margin-bottom:1.5rem; line-height:1.5;">
                            Upload a spreadsheet CSV file containing new user rows. Required columns structure (CSV format without header):<br>
                            <code style="display:block; background:rgba(0,0,0,0.2); padding:0.5rem; border-radius:4px; margin-top:0.5rem; font-size:0.75rem; color:#fde047;">Name, Email, Role (student/alumni), Status (approved/pending)</code>
                        </p>
                        
                        <form action="enterprise_control.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="import_csv">
                            <div class="form-group" style="margin-bottom:1.5rem;">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Select CSV File</label>
                                <input type="file" name="csv_file" accept=".csv" class="input-glass" style="width:100%; padding:0.4rem;" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Process CSV Rows</button>
                        </form>
                    </div>

                    <!-- Export CSV tools -->
                    <div class="card-glass" style="padding:2rem; border-radius:var(--border-radius-lg); background:var(--theme-card); border:1px solid var(--theme-border); display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center;">
                        <i class="fa-solid fa-file-csv" style="font-size:3.5rem; color:var(--theme-accent-purple); margin-bottom:1rem;"></i>
                        <h3 style="font-size:1.25rem; font-weight:700; color:#ffffff; margin-bottom:0.5rem;">Export User Directory</h3>
                        <p style="font-size:0.82rem; color:var(--theme-text-secondary); margin-bottom:1.5rem; max-width:80%;">Download the complete system user accounts list as a raw CSV file compatible with Excel.</p>
                        <a href="enterprise_control.php?export=csv" class="btn btn-primary"><i class="fa-solid fa-download"></i> Download CSV File</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 4: DB UTILITIES -->
            <?php if ($tab === 'db'): ?>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
                    <!-- SQL backup tool -->
                    <div class="card-glass" style="padding:2rem; border-radius:var(--border-radius-lg); background:var(--theme-card); border:1px solid var(--theme-border); display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center;">
                        <i class="fa-solid fa-cloud-arrow-down" style="font-size:3.5rem; color:var(--theme-accent-blue); margin-bottom:1rem;"></i>
                        <h3 style="font-size:1.25rem; font-weight:700; color:#ffffff; margin-bottom:0.5rem;">Backup SQL Database</h3>
                        <p style="font-size:0.82rem; color:var(--theme-text-secondary); margin-bottom:1.5rem; max-width:80%;">Single-click utility exporting all schema scripts, tables, structures, and current rows.</p>
                        <a href="enterprise_control.php?export=db" class="btn btn-primary"><i class="fa-solid fa-download"></i> Backup Database (SQL)</a>
                    </div>

                    <!-- SQL restoration form -->
                    <div class="card-glass" style="padding:2rem; border-radius:var(--border-radius-lg); background:var(--theme-card); border:1px solid var(--theme-border);">
                        <h3 style="font-size:1.15rem; font-weight:700; color:#ffffff; margin-bottom:1rem;"><i class="fa-solid fa-rotate-left" style="color:var(--accent-danger);"></i> Restore Database State</h3>
                        <p style="font-size:0.82rem; color:var(--theme-text-secondary); margin-bottom:1.5rem; line-height:1.5;">
                            Upload a backup SQL script file. Executing this will drop existing structures and reset them to the uploaded backup script parameters.
                        </p>
                        <form action="enterprise_control.php" method="POST" enctype="multipart/form-data" onsubmit="return confirm('WARNING: Executing database restorations overrides all current system records. Proceed?');">
                            <input type="hidden" name="action" value="restore_db">
                            <div class="form-group" style="margin-bottom:1.5rem;">
                                <label style="display:block; font-size: 0.8rem; margin-bottom: 0.4rem; color: var(--theme-text-secondary);">Select SQL Backup file</label>
                                <input type="file" name="backup_file" accept=".sql" class="input-glass" style="width:100%; padding:0.4rem;" required>
                            </div>
                            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-database"></i> Trigger Restore</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 5: AI SETTINGS -->
            <?php if ($tab === 'settings'): ?>
                <div class="card-glass" style="padding:2rem; border-radius:var(--border-radius-lg); background:var(--theme-card); border:1px solid var(--theme-border);">
                    <h3 style="font-size:1.15rem; font-weight:700; color:#ffffff; margin-bottom:1.25rem;"><i class="fa-solid fa-robot" style="color:var(--theme-accent-purple);"></i> Configure Chat Assistant</h3>
                    
                    <form action="enterprise_control.php" method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="form-group" style="margin-bottom:1.5rem;">
                            <label style="display:block; font-size: 0.88rem; margin-bottom: 0.5rem; color: var(--theme-text-secondary);">Gemini API Key</label>
                            <input type="password" name="gemini_api_key" value="<?php echo htmlspecialchars($gemini_api_key); ?>" class="input-glass" style="width:100%; padding:0.6rem 0.75rem; font-size:0.9rem;" placeholder="AIzaSy...">
                            <span style="font-size:0.75rem; color:var(--theme-text-secondary); margin-top:0.4rem; display:block;">Configure a valid Gemini API key to activate natural language chat capabilities. Leave empty to use local rule-based intelligence.</span>
                        </div>

                        <div class="form-group" style="margin-bottom:1.5rem;">
                            <label style="display:block; font-size: 0.88rem; margin-bottom: 0.5rem; color: var(--theme-text-secondary);">Intelligent Greeting / Context Prompt</label>
                            <textarea name="ai_prompt" class="input-glass" style="width:100%; height:120px; padding:0.75rem; font-size:0.9rem; line-height:1.5;" required><?php echo htmlspecialchars($ai_prompt); ?></textarea>
                            <span style="font-size:0.75rem; color:var(--theme-text-secondary); margin-top:0.4rem; display:block;">This prompt configures the standard fallback greeting and personality context parameters for the floating chatbot widget.</span>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Configurations</button>
                    </form>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Forms for single user operations -->
<form id="single-user-form" action="enterprise_control.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="user_control">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
    <input type="hidden" name="user_id" id="single-uid-input">
    <input type="hidden" name="operation" id="single-op-input">
</form>

<script>
function toggleCheckAll(source) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

function runSingle(uid, operation) {
    let confirmMsg = 'Confirm this operation?';
    if (operation === 'delete') confirmMsg = 'Are you sure you want to permanently delete this user profile?';
    
    if (confirm(confirmMsg)) {
        document.getElementById('single-uid-input').value = uid;
        document.getElementById('single-op-input').value = operation;
        document.getElementById('single-user-form').submit();
    }
}

function runBulk(operation) {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one user.');
        return;
    }
    
    let confirmMsg = `Are you sure you want to run bulk ${operation.replace('bulk_', '')} on ${checkboxes.length} selected users?`;
    if (confirm(confirmMsg)) {
        document.getElementById('bulk-op-input').value = operation;
        document.getElementById('bulk-users-form').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

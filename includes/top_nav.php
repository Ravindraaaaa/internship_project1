<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id_nav = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;
$user_role_nav = $_SESSION['user_role'] ?? (isset($_SESSION['admin_id']) ? 'admin' : 'unknown');

// Handle Mark as Read
if (isset($_GET['read_notif'])) {
    $read_id = intval($_GET['read_notif']);
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$read_id, $user_id_nav]);
    if (!empty($_GET['redirect_to'])) {
        $target = ltrim($_GET['redirect_to'], '/');
        header("Location: " . $path_prefix . $target);
        exit;
    }
    $current_url = preg_replace('/([&?])read_notif=[0-9]+&?/', '$1', $_SERVER['REQUEST_URI']);
    $current_url = rtrim($current_url, '?&');
    header("Location: $current_url");
    exit;
}

$unread_count = 0;
$notifications = [];

if ($user_id_nav > 0) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id_nav]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmtCount->execute([$user_id_nav]);
    $unread_count = $stmtCount->fetchColumn();
}

$profile_link = ($user_role_nav === 'admin') ? '#' : 'profile.php';
$logout_link = ($user_role_nav === 'admin') ? '../logout.php' : '../logout.php';
// if we are in admin, profile_link doesn't exist, we just put #
if (basename(dirname($_SERVER['PHP_SELF'])) === 'admin') {
    $profile_link = '#';
    $logout_link = '../logout.php';
} else if (basename(dirname($_SERVER['PHP_SELF'])) === 'user') {
    $profile_link = 'profile.php';
    $logout_link = '../logout.php';
}

?>
<nav class="top-nav">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <button class="theme-toggle-btn" id="mobile-sidebar-toggle" style="display: none;"><i class="fa-solid fa-bars"></i></button>
        <div class="top-nav-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" class="input-glass" placeholder="Search platform..." title="Click or press Ctrl+K to search">
            <span class="search-kbd">⌘K</span>
        </div>
    </div>

    <div class="top-nav-actions">
        <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark/Bright Mode">
            <i class="fa-solid fa-moon"></i>
        </button>
        
        <!-- Direct Messages Shortcut -->
        <?php if ($user_role_nav !== 'admin'): ?>
        <a href="<?php echo (basename(dirname($_SERVER['PHP_SELF'])) === 'user') ? 'chat.php' : 'user/chat.php'; ?>" class="notif-bell-btn" title="Direct Chat & Messages" style="text-decoration: none;">
            <i class="fa-regular fa-comment-dots" style="font-size: 18px;"></i>
        </a>
        <?php endif; ?>

        <!-- Notification Bell -->
        <button class="notif-bell-btn" id="notifBellBtn" title="Notification Center">
            <i class="fa-regular fa-bell" style="font-size: 18px;"></i>
            <span class="notif-badge" id="notifBadge" style="<?php echo ($unread_count > 0) ? 'display:flex;' : 'display:none;'; ?>">
                <?php echo ($unread_count > 99) ? '99+' : $unread_count; ?>
            </span>
        </button>

        <!-- Visible Role Pill Badge in Top Navbar -->
        <div class="nav-role-badge">
            <?php if ($user_role_nav === 'admin'): ?>
                <span class="role-pill pill-admin" title="Administrator Role Panel"><i class="fa-solid fa-user-shield"></i> Admin</span>
            <?php elseif ($user_role_nav === 'alumni'): ?>
                <span class="role-pill pill-alumni" title="Alumni Member Network"><i class="fa-solid fa-user-tie"></i> Alumni</span>
            <?php else: ?>
                <span class="role-pill pill-student" title="Student Member Portal"><i class="fa-solid fa-user-graduate"></i> Student</span>
            <?php endif; ?>
        </div>

        <!-- User profile dropdown -->
        <div style="position: relative;">
            <img src="<?php echo htmlspecialchars($sidebar_avatar ?? 'https://cdn-icons-png.flaticon.com/512/149/149071.png'); ?>" alt="User Avatar" class="nav-user-avatar" id="profile-avatar-toggle">
            <div class="nav-dropdown-menu" id="profile-dropdown-menu">
                <div class="dropdown-header-info">
                    <h4><?php echo htmlspecialchars($user_name ?? 'User'); ?></h4>
                    <p style="margin-top: 0.25rem;">
                        <?php if ($user_role_nav === 'admin'): ?>
                            <span style="font-weight: 600; color: var(--theme-accent-purple); display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-user-shield"></i> Admin Panel</span>
                        <?php elseif ($user_role_nav === 'alumni'): ?>
                            <span style="font-weight: 600; color: var(--theme-accent-blue); display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-user-tie"></i> Alumni Member</span>
                        <?php else: ?>
                            <span style="font-weight: 600; color: #10b981; display: inline-flex; align-items: center; gap: 0.35rem;"><i class="fa-solid fa-user-graduate"></i> Student Member</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($profile_link !== '#'): ?>
                    <a href="<?php echo $profile_link; ?>" class="dropdown-item"><i data-lucide="user" style="width:16px;height:16px;"></i> My Profile</a>
                <?php endif; ?>
                <div style="border-top: 1px solid var(--theme-border); margin: 0.25rem 0;"></div>
                <a href="<?php echo $logout_link; ?>" class="dropdown-item" style="color: var(--accent-danger);"><i data-lucide="log-out" style="width:16px;height:16px;"></i> Sign Out</a>
            </div>
        </div>
    </div>
</nav>

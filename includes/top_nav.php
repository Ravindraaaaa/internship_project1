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
    // Redirect back to same page without query param
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
        
        <!-- Notification Bell -->
        <div class="top-nav-icon-wrapper" id="notif-bell-toggle">
            <i data-lucide="bell" style="width: 20px; height: 20px;"></i>
            <?php if ($unread_count > 0): ?>
                <span class="top-nav-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
            <div class="nav-dropdown-menu" id="notif-dropdown-menu" style="max-height: 400px; overflow-y: auto;">
                <div class="dropdown-header-info">
                    <h4>Recent Alerts</h4>
                    <p>You have <?php echo $unread_count; ?> new notice<?php echo $unread_count != 1 ? 's' : ''; ?></p>
                </div>
                
                <?php if ($unread_count == 0): ?>
                    <div class="notif-item">
                        <div class="notif-item-title" style="text-align: center; color: var(--theme-text-secondary);">No new notifications</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notif-item" style="cursor: pointer;" onclick="window.location.href='?read_notif=<?php echo $notif['id']; ?>'">
                            <div class="notif-item-title">
                                <?php 
                                    $icon = 'fa-info-circle';
                                    $color = 'var(--theme-accent-blue)';
                                    if ($notif['type'] == 'success') { $icon = 'fa-check-circle'; $color = 'var(--accent-success)'; }
                                    if ($notif['type'] == 'warning') { $icon = 'fa-exclamation-triangle'; $color = 'var(--accent-warning)'; }
                                ?>
                                <i class="fa-solid <?php echo $icon; ?>" style="color: <?php echo $color; ?>;"></i> 
                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong><br>
                                <span style="font-size: 0.85rem; font-weight: normal;"><?php echo htmlspecialchars($notif['message']); ?></span>
                            </div>
                            <div class="notif-item-time"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- User profile dropdown -->
        <div style="position: relative;">
            <img src="<?php echo htmlspecialchars($sidebar_avatar ?? 'https://cdn-icons-png.flaticon.com/512/149/149071.png'); ?>" alt="User Avatar" class="nav-user-avatar" id="profile-avatar-toggle">
            <div class="nav-dropdown-menu" id="profile-dropdown-menu">
                <div class="dropdown-header-info">
                    <h4><?php echo htmlspecialchars($user_name ?? 'User'); ?></h4>
                    <p><?php echo htmlspecialchars($user_role_nav); ?> portal</p>
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

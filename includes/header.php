<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

// Fetch profile picture path for active session displays
$navbar_avatar = 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; // default avatar

$path_prefix = (isset($is_subfolder) && $is_subfolder) ? '../' : '';

if (is_logged_in()) {
    $uid = get_user_id();
    $role = get_user_role();
    
    if (is_admin()) {
        $navbar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png'; // Admin icon
    } else if ($role === 'alumni') {
        $stmt = $pdo->prepare("SELECT profile_pic FROM alumni_profiles WHERE user_id = ?");
        $stmt->execute([$uid]);
        $header_profile = $stmt->fetch();
        $navbar_avatar = get_avatar_url($header_profile['profile_pic'] ?? '');
    } else if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT profile_pic FROM student_profiles WHERE user_id = ?");
        $stmt->execute([$uid]);
        $header_profile = $stmt->fetch();
        $navbar_avatar = get_avatar_url($header_profile['profile_pic'] ?? '');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - AlumniNet" : "AlumniNet - Connecting Generations"; ?></title>
    <meta name="description" content="AlumniNet is a high-fidelity platform designed to bridge the gap between academic generations.">
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill='%23818cf8' d='M256 32L32 144l224 112 224-112L256 32z'/><path fill='%2338bdf8' d='M256 288l-176-88v120c0 44.2 78.8 80 176 80s176-35.8 176-80V200L256 288z'/><path fill='%23a855f7' d='M448 184v168h32V184l-32 0z'/></svg>">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Custom Style System CSS -->
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/style.css?v=<?php echo time(); ?>">
    <!-- GSAP CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <!-- Lenis Smooth Scroll (Loaded locally to prevent Tracking Prevention warnings) -->
    <script src="<?php echo $path_prefix; ?>assets/js/lenis.min.js"></script>
</head>
<body <?php echo is_logged_in() ? 'class="page-loading"' : ''; ?>>

    <!-- ==================== PAGE LOADER ==================== -->
    <?php if (!is_logged_in()): ?>
    <div class="loader-overlay" id="page-loader">
        <div class="loader-spinner"></div>
    </div>
    <?php endif; ?>

    <!-- ==================== CLEAN STATIC BACKDROP LAYER ==================== -->
    <div class="custom-bg-overlay"></div>

    <!-- ==================== PUBLIC STICKY NAV HEADER ==================== -->
    <?php 
    // Show public nav only on public landing page index.php, or if not logged in
    $current_script = basename($_SERVER['PHP_SELF']);
    if ($current_script === 'index.php' || !is_logged_in()): 
    ?>
    <header class="header-public">
        <a href="<?php echo $path_prefix; ?>index.php" class="logo">
            <i class="fa-solid fa-graduation-cap"></i> AlumniNet
        </a>
        <ul class="nav-public-links">
            <li><a href="<?php echo $path_prefix; ?>index.php" class="nav-public-link <?php echo $current_script === 'index.php' ? 'active' : ''; ?>">Home</a></li>
            <li><a href="<?php echo $path_prefix; ?>user/alumni.php" class="nav-public-link <?php echo $current_script === 'alumni.php' ? 'active' : ''; ?>">Alumni</a></li>
            <li><a href="<?php echo $path_prefix; ?>user/jobs.php" class="nav-public-link <?php echo $current_script === 'jobs.php' ? 'active' : ''; ?>">Jobs</a></li>
            <li><a href="<?php echo $path_prefix; ?>user/events.php" class="nav-public-link <?php echo $current_script === 'events.php' ? 'active' : ''; ?>">Events</a></li>
            <li>
                <button class="theme-toggle-btn" onclick="toggleThemeMode()" title="Toggle Dark/Bright Mode">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </li>
            
            <?php if (is_logged_in()): ?>
                <li><a href="<?php echo $path_prefix; ?>dashboard.php" class="btn btn-secondary btn-small"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <?php else: ?>
                <li><a href="<?php echo $path_prefix; ?>login.php" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Sign In</a></li>
            <?php endif; ?>
        </ul>
    </header>
    <?php endif; ?>

    <!-- Display alerts dynamically matching toast trigger functions -->
    <?php display_flash(); ?>

<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';

require_login();

if (is_admin()) {
    header('Location: admin/dashboard.php');
    exit;
} else {
    header('Location: user/dashboard.php');
    exit;
}
?>

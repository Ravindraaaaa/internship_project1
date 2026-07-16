<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_admin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: admin_login.php');
        exit;
    }
}
?>

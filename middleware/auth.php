<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_auth() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
        header('Location: ../login.php');
        exit;
    }
}
?>

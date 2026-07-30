<?php
require_once 'includes/auth_helper.php';
require_once 'includes/db.php';
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'student';
$_POST['action'] = 'message';
$_POST['message'] = 'hi';
include 'api/ai_chat.php';
?>

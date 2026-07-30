<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/user/alumni.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET = [];

session_start();
$_SESSION['user_id'] = 2; // Assuming 2 is an alumni
$_SESSION['user_role'] = 'alumni';
$_SESSION['user_name'] = 'Test Alumni';

ob_start();
require __DIR__ . '/user/alumni.php';
$html = ob_get_clean();

file_put_contents(__DIR__ . '/test_alumni_output.html', $html);
echo "HTML written to test_alumni_output.html\n";

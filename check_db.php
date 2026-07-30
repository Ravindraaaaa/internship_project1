<?php
require_once 'includes/db.php';
$res = $pdo->query('SHOW COLUMNS FROM alumni_profiles');
echo "alumni_profiles columns: ";
while($row = $res->fetch()) { echo $row['Field'] . ", "; }

echo "\nannouncements columns: ";
$res = $pdo->query('SHOW COLUMNS FROM announcements');
while($row = $res->fetch()) { echo $row['Field'] . ", "; }

echo "\nannouncement_views columns: ";
try {
    $res = $pdo->query('SHOW COLUMNS FROM announcement_views');
    while($row = $res->fetch()) { echo $row['Field'] . ", "; }
} catch (Exception $e) { echo "Table not found."; }
?>

<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_admin();

$filename = "alumni_import_template_" . date('Ymd') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Required Headers
$headers = [
    'First Name',
    'Last Name',
    'Email',
    'Phone',
    'Graduation Year',
    'Enrollment ID',
    'Course',
    'Company (Optional)',
    'Position (Optional)',
    'Industry (Optional)',
    'LinkedIn URL (Optional)'
];

fputcsv($output, $headers);

// Sample row to guide the admin
$sample = [
    'John',
    'Doe',
    'john.doe@example.com',
    '9876543210',
    '2024',
    'ENR-12345',
    'B.Tech Computer Science',
    'Tech Corp',
    'Software Engineer',
    'IT',
    'https://linkedin.com/in/johndoe'
];
fputcsv($output, $sample);

fclose($output);
exit;

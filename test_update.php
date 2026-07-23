<?php
require_once __DIR__ . '/includes/db.php';
session_start();
\['user_id'] = 2; // Demo Student User
\['role'] = 'student';

\['action'] = 'update_profile';
\['bio'] = 'Test bio update';
\['linkedin'] = 'https://linkedin.com/in/test';
\['current_year'] = '2';
\['course'] = 'Information Technology';
\['github'] = 'https://github.com/test';
\['cgpa'] = '8.50';
\['name'] = 'Demo Student User Updated';

// mock the logic
try {
    \ = 2;
    \ = intval(\['current_year']);
    \ = trim(\['course']);
    \ = trim(\['linkedin']);
    \ = trim(\['github']);
    \ = trim(\['bio']);
    \ = floatval(\['cgpa']);
    
    \ = \->prepare("SELECT COUNT(*) FROM student_profiles WHERE user_id = ?");
    \->execute([\]);
    if (\->fetchColumn() > 0) {
        \ = \->prepare("UPDATE student_profiles SET current_year = ?, course = ?, linkedin = ?, github = ?, bio = ?, profile_pic = ?, cgpa = ? WHERE user_id = ?");
        \->execute([\, \, \, \, \, null, \, \]);
        echo "Update successful\n";
    } else {
        \ = \->prepare("INSERT INTO student_profiles (user_id, current_year, course, linkedin, github, bio, profile_pic, cgpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        \->execute([\, \, \, \, \, \, null, \]);
        echo "Insert successful\n";
    }
} catch (Exception \) {
    echo "Error: " . \->getMessage() . "\n";
}
?>

<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([
        'users' => [],
        'jobs' => [],
        'companies' => [],
        'events' => []
    ]);
    exit;
}

try {
    $search_term = '%' . $query . '%';
    
    // 1. Search Users
    $stmtUsers = $pdo->prepare("SELECT id, name, email, role, username FROM users WHERE name LIKE ? OR email LIKE ? OR role LIKE ? LIMIT 5");
    $stmtUsers->execute([$search_term, $search_term, $search_term]);
    $users = $stmtUsers->fetchAll();
    
    // 2. Search Jobs
    $stmtJobs = $pdo->prepare("SELECT id, title, company, location, type FROM jobs WHERE title LIKE ? OR company LIKE ? OR location LIKE ? LIMIT 5");
    $stmtJobs->execute([$search_term, $search_term, $search_term]);
    $jobs = $stmtJobs->fetchAll();
    
    // 3. Search Companies
    $stmtCompanies = $pdo->prepare("SELECT id, name, location, website FROM companies WHERE name LIKE ? OR location LIKE ? LIMIT 5");
    $stmtCompanies->execute([$search_term, $search_term]);
    $companies = $stmtCompanies->fetchAll();
    
    // 4. Search Events
    $stmtEvents = $pdo->prepare("SELECT id, title, location, event_date, event_type FROM events WHERE title LIKE ? OR location LIKE ? LIMIT 5");
    $stmtEvents->execute([$search_term, $search_term]);
    $events = $stmtEvents->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'users' => $users,
        'jobs' => $jobs,
        'companies' => $companies,
        'events' => $events
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

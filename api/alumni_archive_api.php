<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? 'documents';

if ($action === 'documents') {
    $user_id = intval($_GET['user_id'] ?? 0);
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
        exit;
    }

    $stmtDoc = $pdo->prepare("SELECT * FROM alumni_documents WHERE user_id = ? ORDER BY uploaded_at DESC");
    $stmtDoc->execute([$user_id]);
    $docs = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    $stmtProf = $pdo->prepare("SELECT u.name, u.email, u.phone, ap.* FROM users u LEFT JOIN alumni_profiles ap ON u.id = ap.user_id WHERE u.id = ?");
    $stmtProf->execute([$user_id]);
    $profile = $stmtProf->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'profile' => $profile,
        'documents' => $docs
    ]);
    exit;
}

if ($action === 'hierarchy') {
    // Return Passing Year -> Branch -> Count hierarchy
    $stmt = $pdo->query("SELECT COALESCE(passing_year, graduation_year, 2024) as yr, COALESCE(branch, course, 'General') as br, COUNT(*) as qty 
                        FROM alumni_profiles 
                        GROUP BY yr, br 
                        ORDER BY yr DESC, br ASC");
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hierarchy = [];
    foreach ($raw as $row) {
        $yr = $row['yr'];
        $br = $row['br'];
        $qty = $row['qty'];
        if (!isset($hierarchy[$yr])) {
            $hierarchy[$yr] = [
                'year' => $yr,
                'total_alumni' => 0,
                'branches' => []
            ];
        }
        $hierarchy[$yr]['total_alumni'] += $qty;
        $hierarchy[$yr]['branches'][] = [
            'branch' => $br,
            'count' => $qty
        ];
    }

    echo json_encode([
        'success' => true,
        'hierarchy' => array_values($hierarchy)
    ]);
    exit;
}

if ($action === 'year_stats') {
    $year = intval($_GET['year'] ?? date('Y'));

    $stmtTot = $pdo->prepare("SELECT COUNT(*) FROM alumni_profiles WHERE passing_year = ? OR graduation_year = ?");
    $stmtTot->execute([$year, $year]);
    $total = $stmtTot->fetchColumn();

    $stmtComp = $pdo->prepare("SELECT company, COUNT(*) as cnt FROM alumni_profiles WHERE (passing_year = ? OR graduation_year = ?) AND company IS NOT NULL AND company != '' GROUP BY company ORDER BY cnt DESC LIMIT 5");
    $stmtComp->execute([$year, $year]);
    $top_companies = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

    $stmtDesig = $pdo->prepare("SELECT position as designation, COUNT(*) as cnt FROM alumni_profiles WHERE (passing_year = ? OR graduation_year = ?) AND position IS NOT NULL AND position != '' GROUP BY position ORDER BY cnt DESC LIMIT 5");
    $stmtDesig->execute([$year, $year]);
    $top_designations = $stmtDesig->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'year' => $year,
        'total_alumni' => $total,
        'top_companies' => $top_companies,
        'top_designations' => $top_designations
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;

<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

require_admin();
handle_session_timeout();

$type = $_GET['type'] ?? 'users'; // 'users', 'placements', 'events', 'applications'
$format = $_GET['format'] ?? 'csv'; // 'csv', 'excel', 'print'

try {
    // 1. Gather Data based on Type
    $data = [];
    $filename = "alumninet_report_" . $type . "_" . date('Ymd_His');
    
    if ($type === 'users') {
        $headers = ['ID', 'Name', 'Email', 'Role', 'Status', 'Registered At'];
        $stmt = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY id ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    
    elseif ($type === 'placements') {
        $headers = ['Job ID', 'Title', 'Company', 'Location', 'Job Type', 'Salary Range', 'Posted By', 'Posted At'];
        $stmt = $pdo->query("SELECT j.id, j.title, j.company, j.location, j.type, j.salary_range, u.name as poster_name, j.created_at 
                             FROM jobs j 
                             LEFT JOIN users u ON j.posted_by = u.id 
                             ORDER BY j.id ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    
    elseif ($type === 'events') {
        $headers = ['Event ID', 'Title', 'Date & Time', 'Location', 'Type', 'RSVP Attending Count'];
        $stmt = $pdo->query("SELECT e.id, e.title, e.event_date, e.location, e.event_type, 
                             (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'attending') as attending_count 
                             FROM events e 
                             ORDER BY e.id ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    
    elseif ($type === 'applications') {
        $headers = ['Application ID', 'Job Title', 'Company', 'Applicant Name', 'Email', 'Status', 'Applied At'];
        $stmt = $pdo->query("SELECT ja.id, j.title, j.company, u.name as applicant_name, u.email, ja.status, ja.applied_at 
                             FROM job_applications ja 
                             JOIN jobs j ON ja.job_id = j.id 
                             JOIN users u ON ja.user_id = u.id 
                             ORDER BY ja.id ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Generate CSV or Excel Format
    if ($format === 'csv' || $format === 'excel') {
        ob_end_clean();
        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        } else {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        }
        
        $fp = fopen('php://output', 'w');
        // Add headers
        fputcsv($fp, $headers);
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        exit;
    } 
    
    // 3. Generate HTML/Print Layout (optimized for print/PDF conversion)
    elseif ($format === 'print') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>System Report - <?php echo ucfirst($type); ?></title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    color: #333333;
                    padding: 2rem;
                }
                .report-header {
                    border-bottom: 2px solid #333;
                    padding-bottom: 1rem;
                    margin-bottom: 2rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                }
                .report-header h1 {
                    margin: 0;
                    font-size: 1.75rem;
                }
                .report-header p {
                    margin: 0.25rem 0 0 0;
                    font-size: 0.88rem;
                    color: #666;
                }
                .report-meta {
                    text-align: right;
                    font-size: 0.85rem;
                    color: #555;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 2rem;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 0.65rem 0.85rem;
                    font-size: 0.85rem;
                    text-align: left;
                }
                th {
                    background-color: #f5f5f5;
                    font-weight: 700;
                }
                tr:nth-child(even) {
                    background-color: #fafafa;
                }
                .no-print-panel {
                    background-color: #e2e8f0;
                    padding: 1rem;
                    border-radius: 4px;
                    margin-bottom: 1.5rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .btn {
                    padding: 0.4rem 0.8rem;
                    background-color: #2563eb;
                    color: #ffffff;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 0.82rem;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                }
                .btn-secondary {
                    background-color: #475569;
                }
                @media print {
                    .no-print-panel {
                        display: none;
                    }
                    body {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print-panel">
                <span><strong>Report Viewer:</strong> Print or Save this sheet to PDF.</span>
                <div style="display:flex; gap:0.5rem;">
                    <a href="dashboard.php?tab=reports" class="btn btn-secondary">Back to Reports</a>
                    <button onclick="window.print()" class="btn">Print / Save PDF</button>
                </div>
            </div>
            
            <header class="report-header">
                <div>
                    <h1>AlumniNet Platform Report</h1>
                    <p>Generated data logs for: <strong><?php echo ucfirst($type); ?></strong></p>
                </div>
                <div class="report-meta">
                    <div>Generated: <?php echo date('M d, Y H:i A'); ?></div>
                    <div>Total Records: <?php echo count($data); ?></div>
                </div>
            </header>
            
            <table>
                <thead>
                    <tr>
                        <?php foreach ($headers as $h): ?>
                            <th><?php echo htmlspecialchars($h); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($data): ?>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <?php foreach ($row as $val): ?>
                                    <td><?php echo htmlspecialchars($val ?? ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo count($headers); ?>" style="text-align:center; font-style:italic;">No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    echo "Failed to generate report: " . $e->getMessage();
}

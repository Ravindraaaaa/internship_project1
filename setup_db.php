<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (PHP_SAPI === 'cli') {
    try {
        $config_file = __DIR__ . '/config/db.php';
        if (!file_exists($config_file)) {
            throw new Exception("Configuration file not found at: $config_file");
        }
        require_once $config_file;
        $sql_file = __DIR__ . '/database/database.sql';
        if (!file_exists($sql_file)) {
            $sql_file = __DIR__ . '/database.sql';
        }
        if (!file_exists($sql_file)) {
            throw new Exception("SQL database file not found.");
        }
        $sql = file_get_contents($sql_file);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $pdo->exec($sql);
        echo "Database imported and seeded successfully via CLI!\n";
        exit(0);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - AlumniNet</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            max-width: 600px;
            width: 100%;
            border-top: 5px solid #007bff;
        }
        h1 {
            color: #007bff;
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 20px;
        }
        p {
            margin-bottom: 15px;
        }
        .status {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        code {
            background-color: #f1f3f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 85%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AlumniNet Database Setup</h1>
        
        <?php
        $db_configured = false;
        try {
            // 1. Attempt to connect to the MySQL database
            $config_file = __DIR__ . '/config/db.php';
            if (!file_exists($config_file)) {
                throw new Exception("Configuration file not found at: $config_file");
            }
            
            // Temporary connection check
            require_once $config_file;
            $db_configured = true;
            echo "<p>✓ Connected to MySQL database configuration successfully.</p>";
            
            // 2. Locate SQL file
            $sql_file = __DIR__ . '/database/database.sql';
            if (!file_exists($sql_file)) {
                $sql_file = __DIR__ . '/database.sql';
            }
            
            if (!file_exists($sql_file)) {
                throw new Exception("SQL database file not found in 'database/database.sql' or root.");
            }
            
            echo "<p>✓ Found SQL schema at: <code>" . basename($sql_file) . "</code></p>";
            
            if (isset($_POST['setup'])) {
                $sql = file_get_contents($sql_file);
                
                // Set emulate prepares to execute multi-queries
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                
                // Execute SQL script
                $pdo->exec($sql);
                
                echo "<div class='status status-success'>";
                echo "🎉 Success! The SQL database schema has been successfully imported, and all seed data has been created.";
                echo "</div>";
                echo "<p>You can now browse the site and log in with the test accounts:</p>";
                echo "<ul>";
                echo "<li><strong>Admin:</strong> admin (Password: <code>Admin@123</code>)</li>";
                echo "<li><strong>Student:</strong> user (Password: <code>User@123</code>)</li>";
                echo "</ul>";
                echo "<a href='index.php' class='btn'>Go to Homepage</a>";
            } else {
                echo "<p>Ready to initialize your database tables and insert initial test data (alumni profiles, student profiles, jobs, events, and announcements).</p>";
                echo "<form method='POST'>";
                echo "<button type='submit' name='setup' class='btn'>Run Setup & Import SQL</button>";
                echo "</form>";
            }
            
        } catch (Exception $e) {
            echo "<div class='status status-error'>";
            echo "Error setting up database:\n\n";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
            
            echo "<p><strong>How to fix this:</strong></p>";
            echo "<ol>";
            echo "<li>Make sure your MySQL server is running in the <strong>XAMPP Control Panel</strong>.</li>";
            echo "<li>Open <code>config/db.php</code> and verify that the database credentials match your local MySQL settings (host, user, pass).</li>";
            echo "</ol>";
            echo "<a href='setup_db.php' class='btn'>Try Again</a>";
        }
        ?>
    </div>
</body>
</html>

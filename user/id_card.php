<?php
ob_start();
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$uid = get_user_id();
$role = get_user_role();
$user_name = get_user_name();

// Only students are supposed to have a Student ID card, but we can allow alumni to have it too if needed.
if ($role !== 'user') {
    header("Location: dashboard.php");
    exit;
}

try {
    $stmtC = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmtC->execute([$uid]);
    $user_core = $stmtC->fetch();

    $stmtP = $pdo->prepare("SELECT course, current_year, profile_pic FROM student_profiles WHERE user_id = ?");
    $stmtP->execute([$uid]);
    $profile = $stmtP->fetch() ?: [];
    
} catch (Exception $e) {
    die("Database error");
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain . rtrim(dirname($_SERVER['PHP_SELF'], 2), '/\\');
$verify_url = $base_url . "/admin/scan_id.php?verify_student=" . urlencode(base64_encode($uid));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital ID Card - AlumniNet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            background-color: #0f172a; /* Slate 900 */
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-image: radial-gradient(circle at 50% -20%, #312e81, #0f172a 60%);
        }
        .id-card-wrapper {
            perspective: 1000px;
        }
        .id-card {
            width: 320px;
            height: 500px;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 20px rgba(99, 102, 241, 0.3);
            overflow: hidden;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }
        .id-card:hover {
            transform: translateY(-10px) rotateX(2deg) rotateY(2deg);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.6), 0 0 30px rgba(99, 102, 241, 0.4);
        }
        .id-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 20px;
            text-align: center;
            border-bottom: 2px solid rgba(255,255,255,0.2);
        }
        .id-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .id-header p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #e2e8f0;
            opacity: 0.8;
        }
        .id-body {
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid #4f46e5;
            object-fit: cover;
            margin-bottom: 15px;
            background-color: #1e293b;
            box-shadow: 0 0 15px rgba(79, 70, 229, 0.5);
        }
        .student-name {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            color: #ffffff;
        }
        .student-details {
            margin-top: 10px;
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .student-details span {
            display: block;
            color: #cbd5e1;
            font-weight: 500;
        }
        .qr-section {
            margin-top: 25px;
            background: #ffffff;
            padding: 10px;
            border-radius: 12px;
            display: inline-block;
        }
        .id-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(15, 23, 42, 0.8);
            text-align: center;
            padding: 12px;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .back-btn {
            position: fixed;
            top: 30px;
            left: 30px;
            color: #94a3b8;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .back-btn:hover {
            color: #ffffff;
        }
        #qrcode img {
            display: block;
            margin: auto;
        }
    </style>
</head>
<body>

    <a href="profile.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Profile
    </a>

    <div class="id-card-wrapper">
        <div class="id-card">
            <div class="id-header">
                <h2>AlumniNet</h2>
                <p>Digital Student Identity Card</p>
            </div>
            
            <div class="id-body">
                <?php
                    $pic_url = (!empty($profile['profile_pic'])) ? htmlspecialchars($profile['profile_pic']) : '../assets/images/default-avatar.png';
                ?>
                <img src="<?php echo $pic_url; ?>" alt="Profile Picture" class="profile-img" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user_core['name']); ?>&background=4f46e5&color=fff'">
                
                <h3 class="student-name"><?php echo htmlspecialchars($user_core['name']); ?></h3>
                
                <div class="student-details">
                    ID: <span>STU-<?php echo str_pad($uid, 5, '0', STR_PAD_LEFT); ?></span>
                    Course: <span><?php echo htmlspecialchars($profile['course'] ?? 'Not Set'); ?></span>
                    Batch: <span>Year <?php echo htmlspecialchars($profile['current_year'] ?? 'N/A'); ?></span>
                </div>

                <div class="qr-section" id="qrcode"></div>
            </div>

            <div class="id-footer">
                Official Campus E-ID • Scan to Verify
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var qrUrl = "<?php echo addslashes($verify_url); ?>";
            var qrcode = new QRCode(document.getElementById("qrcode"), {
                text: qrUrl,
                width: 120,
                height: 120,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        });
    </script>
</body>
</html>

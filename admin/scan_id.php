<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/admin.php';
require_once __DIR__ . '/../includes/auth_helper.php';

check_admin();

$page_title = "Scan Digital ID";

$student_data = null;
$error = null;

if (isset($_GET['verify_student'])) {
    $uid_encoded = $_GET['verify_student'];
    $uid = base64_decode($uid_encoded);
    
    if ($uid && is_numeric($uid)) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, sp.course, sp.current_year, sp.profile_pic 
                FROM users u 
                LEFT JOIN student_profiles sp ON u.id = sp.user_id 
                WHERE u.id = ? AND u.role = 'user'
            ");
            $stmt->execute([$uid]);
            $student_data = $stmt->fetch();
            
            if (!$student_data) {
                $error = "Student not found or invalid QR code.";
            }
        } catch (Exception $e) {
            $error = "Database error verifying student.";
        }
    } else {
        $error = "Invalid QR code payload.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital ID Scanner - AlumniNet Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }
        .scanner-container {
            max-width: 600px;
            margin: 2rem auto;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        #reader {
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
        }
        #reader video {
            object-fit: cover;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #94a3b8;
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        .btn-back:hover {
            color: #ffffff;
        }
        .result-card {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.4);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            margin-top: 1rem;
        }
        .result-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            margin-top: 1rem;
        }
        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid #10b981;
            object-fit: cover;
            margin: 0 auto 1rem;
        }
    </style>
</head>
<body>

    <div class="container mx-auto px-4">
        <div class="scanner-container">
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            
            <h2 class="text-2xl font-bold mb-4 text-center">Student ID Scanner</h2>
            <p class="text-slate-400 text-center mb-6 text-sm">Scan a student's Digital ID Card QR code to verify their identity.</p>

            <?php if ($student_data): ?>
                <div class="result-card">
                    <i class="fas fa-check-circle text-4xl text-emerald-500 mb-3"></i>
                    <h3 class="text-xl font-bold text-emerald-400 mb-4">Identity Verified</h3>
                    
                    <?php
                        $pic_url = (!empty($student_data['profile_pic'])) ? htmlspecialchars($student_data['profile_pic']) : '../assets/images/default-avatar.png';
                        if (strpos($pic_url, '../') === 0) {
                            $pic_url = $pic_url;
                        } else {
                            $pic_url = '../' . $pic_url;
                        }
                    ?>
                    <img src="<?php echo $pic_url; ?>" class="profile-img" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($student_data['name']); ?>&background=10b981&color=fff'">
                    
                    <h4 class="text-2xl font-bold mb-1"><?php echo htmlspecialchars($student_data['name']); ?></h4>
                    <p class="text-slate-300 mb-1">STU-<?php echo str_pad($student_data['id'], 5, '0', STR_PAD_LEFT); ?></p>
                    <p class="text-emerald-300 font-medium"><?php echo htmlspecialchars($student_data['course'] ?? 'Course Not Set'); ?></p>
                    <p class="text-slate-400 text-sm mt-2">Batch: Year <?php echo htmlspecialchars($student_data['current_year'] ?? 'N/A'); ?></p>
                    
                    <a href="scan_id.php" class="mt-6 inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                        Scan Another
                    </a>
                </div>
            <?php elseif ($error): ?>
                <div class="result-error">
                    <i class="fas fa-times-circle text-4xl text-red-500 mb-3"></i>
                    <h3 class="text-xl font-bold text-red-400 mb-2">Verification Failed</h3>
                    <p class="text-slate-300"><?php echo htmlspecialchars($error); ?></p>
                    <a href="scan_id.php" class="mt-6 inline-block bg-slate-700 hover:bg-slate-600 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                        Try Again
                    </a>
                </div>
            <?php else: ?>
                <!-- Scanner UI -->
                <div id="reader"></div>
                <div id="scan-status" class="text-center mt-4 text-emerald-400 font-medium hidden">
                    <i class="fas fa-spinner fa-spin mr-2"></i> Scanning...
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$student_data && !$error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const html5QrCode = new Html5Qrcode("reader");
            
            const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                html5QrCode.stop().then((ignore) => {
                    document.getElementById('scan-status').classList.remove('hidden');
                    window.location.href = decodedText;
                }).catch((err) => {
                    console.log("Stop failed", err);
                    window.location.href = decodedText;
                });
            };
            
            const config = { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 };
            
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    // Default to the first camera, but if there's a back camera we try to use it
                    let cameraId = devices[0].id;
                    for (let i = 0; i < devices.length; i++) {
                        if (devices[i].label.toLowerCase().includes('back') || devices[i].label.toLowerCase().includes('environment')) {
                            cameraId = devices[i].id;
                            break;
                        }
                    }
                    
                    html5QrCode.start(cameraId, config, qrCodeSuccessCallback)
                    .catch(err => {
                        document.getElementById('reader').innerHTML = '<div class="text-red-400 text-center p-4">Camera start failed: ' + err + '</div>';
                    });
                } else {
                    document.getElementById('reader').innerHTML = '<div class="text-red-400 text-center p-4">No cameras found on this device.</div>';
                }
            }).catch(err => {
                // This happens if permission is denied or no secure context
                document.getElementById('reader').innerHTML = '<div class="text-red-400 text-center p-4">Camera access failed. Please ensure you have granted camera permissions. If you are on desktop, check if the browser blocks access over non-HTTPS. Error: ' + err + '</div>';
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>

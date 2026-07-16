<?php
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$user_id = get_user_id();
$action = $_GET['action'] ?? $_POST['action'] ?? 'message';

try {
    if ($action === 'history') {
        $stmt = $pdo->prepare("SELECT query, response, created_at FROM ai_chats WHERE user_id = ? ORDER BY created_at ASC LIMIT 30");
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'history' => $history]);
        exit;
    } 
    
    elseif ($action === 'clear') {
        $stmt = $pdo->prepare("DELETE FROM ai_chats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        echo json_encode(['status' => 'success']);
        exit;
    } 
    
    elseif ($action === 'message') {
        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            echo json_encode(['error' => 'Message is required.']);
            exit;
        }
        
        $response = "";
        $lower_message = strtolower($message);
        
        // 1. PLACEMENT & INTERNSHIP QUERIES
        if (strpos($lower_message, 'job') !== false || strpos($lower_message, 'internship') !== false || strpos($lower_message, 'placement') !== false || strpos($lower_message, 'vacancy') !== false) {
            $stmt = $pdo->query("SELECT title, company, location, type FROM jobs WHERE status = 'active' ORDER BY created_at DESC LIMIT 3");
            $jobs = $stmt->fetchAll();
            if ($jobs) {
                $response = "Here are some of the latest opportunities posted on the board:\n\n";
                foreach ($jobs as $j) {
                    $response .= "* **" . htmlspecialchars($j['title']) . "** at *" . htmlspecialchars($j['company']) . "* (" . htmlspecialchars($j['location']) . ") — " . ucfirst($j['type']) . "\n";
                }
                $response .= "\nYou can view and apply for these in the [Job Board](../user/jobs.php). Let me know if you need specific branch requirements!";
            } else {
                $response = "There are currently no active job postings on the portal. Admins and verified alumni will post placements soon!";
            }
        }
        
        // 2. EVENT DETAILS
        elseif (strpos($lower_message, 'event') !== false || strpos($lower_message, 'seminar') !== false || strpos($lower_message, 'meet') !== false || strpos($lower_message, 'reunion') !== false) {
            $stmt = $pdo->query("SELECT title, event_date, location, event_type FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 3");
            $events = $stmt->fetchAll();
            if ($events) {
                $response = "I found these upcoming events scheduled on the platform:\n\n";
                foreach ($events as $e) {
                    $date = date('M d, Y h:i A', strtotime($e['event_date']));
                    $response .= "* **" . htmlspecialchars($e['title']) . "**\n  * 📅 " . $date . "\n  * 📍 " . htmlspecialchars($e['location']) . " (" . ucfirst($e['event_type']) . ")\n";
                }
                $response .= "\nYou can RSVP and confirm your attendance in the [Events Board](../user/events.php).";
            } else {
                $response = "There are no upcoming events listed right now. Keep an eye on your notifications for admin announcements!";
            }
        }
        
        // 3. ALUMNI / MENTOR DIRECTORY
        elseif (strpos($lower_message, 'alumni') !== false || strpos($lower_message, 'mentor') !== false || strpos($lower_message, 'connect') !== false) {
            $stmt = $pdo->query("SELECT u.name, ap.company, ap.position, ap.course FROM users u JOIN alumni_profiles ap ON u.id = ap.user_id WHERE u.role = 'alumni' AND u.status = 'approved' LIMIT 3");
            $alumni = $stmt->fetchAll();
            if ($alumni) {
                $response = "Here are a few verified alumni registered on AlumniNet:\n\n";
                foreach ($alumni as $a) {
                    $response .= "* **" . htmlspecialchars($a['name']) . "** (" . htmlspecialchars($a['course']) . ")\n  * Currently " . htmlspecialchars($a['position']) . " at *" . htmlspecialchars($a['company']) . "*\n";
                }
                $response .= "\nYou can browse the complete [Alumni Directory](../user/alumni.php) to request mentorship sessions.";
            } else {
                $response = "No verified alumni profiles found. They will appear here once approved by an administrator.";
            }
        }
        
        // 4. RESUME TIPS & PROFILE GUIDANCE
        elseif (strpos($lower_message, 'resume') !== false || strpos($lower_message, 'profile') !== false || strpos($lower_message, 'portfolio') !== false || strpos($lower_message, 'cv') !== false) {
            // Fetch profile score
            $role = $_SESSION['user_role'] ?? 'student';
            $score = 30;
            if ($role === 'student') {
                $stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $prof = $stmt->fetch();
                if ($prof) {
                    if (!empty($prof['bio'])) $score += 20;
                    if (!empty($prof['profile_pic'])) $score += 20;
                    if (!empty($prof['linkedin'])) $score += 15;
                    if (!empty($prof['github'])) $score += 15;
                }
            } else {
                $stmt = $pdo->prepare("SELECT * FROM alumni_profiles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $prof = $stmt->fetch();
                if ($prof) {
                    if (!empty($prof['bio'])) $score += 15;
                    if (!empty($prof['profile_pic'])) $score += 15;
                    if (!empty($prof['linkedin'])) $score += 10;
                    if (!empty($prof['company'])) $score += 15;
                    if (!empty($prof['position'])) $score += 15;
                }
            }
            
            $response = "Based on your current profile details, your profile completeness score is **" . $score . "%**.\n\n";
            $response .= "💡 **Here are 3 tips to boost your resume and visibility:**\n";
            $response .= "1. **Fill all profile links**: Link your LinkedIn and GitHub accounts in the profile editor.\n";
            $response .= "2. **Upload Certificates**: Go to your [Portfolio](../user/portfolio.php) to organize and highlight course credentials.\n";
            $response .= "3. **Download CV**: Use the automatic [Resume Builder](../api/resume_builder.php) to generate a clean, print-ready document.";
        }
        
        // 5. HELPFUL PLATFORM NAVIGATION
        elseif (strpos($lower_message, 'navigation') !== false || strpos($lower_message, 'help') !== false || strpos($lower_message, 'dashboard') !== false) {
            $response = "Need help navigating AlumniNet? Here is a quick reference map:\n\n";
            $response .= "* 📊 **Dashboard:** Metrics and recent highlights ([Dashboard](../dashboard.php))\n";
            $response .= "* 💼 **Job Board:** Internship and placement vacancies ([Job Board](../user/jobs.php))\n";
            $response .= "* 📅 **Events Board:** Check agendas and RSVP ([Events Board](../user/events.php))\n";
            $response .= "* 🤝 **Mentorship:** Send messages and requests to alumni ([Mentorship](../user/mentorship.php))\n";
            $response .= "* 📁 **Portfolio:** Manage resumes, certificates, and settings ([Portfolio](../user/portfolio.php))\n";
            $response .= "* ⚙️ **Visual Customizations:** Press the floating magic wand button to choose themes or canvas backgrounds.";
        }
        
        // 6. DEFAULT GENERAL CHATBOT FALLBACK
        else {
            // Check if there is an AI prompt override in database
            $stmtPrompt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'ai_prompt'");
            $stmtPrompt->execute();
            $custom_prompt = $stmtPrompt->fetchColumn();
            
            $greeting = "I am the AlumniNet Assistant. How can I help you today?";
            if ($custom_prompt) {
                $greeting = htmlspecialchars($custom_prompt);
            }
            
            $response = $greeting . "\n\nTry asking me questions like:\n";
            $response .= "* *\"Are there any jobs available?\"*\n";
            $response .= "* *\"Tell me about upcoming events.\"*\n";
            $response .= "* *\"How can I connect with alumni?\"*\n";
            $response .= "* *\"What is my profile completion score?\"*";
        }
        
        // Record into history
        $stmtSave = $pdo->prepare("INSERT INTO ai_chats (user_id, query, response) VALUES (?, ?, ?)");
        $stmtSave->execute([$user_id, $message, $response]);
        
        echo json_encode([
            'status' => 'success',
            'query' => $message,
            'response' => $response
        ]);
        exit;
    }
    
    echo json_encode(['error' => 'Invalid AI action.']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

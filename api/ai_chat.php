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
        
        $role = $_SESSION['user_role'] ?? 'student';
        
        // Fetch User details for context
        $stmtUser = $pdo->prepare("SELECT u.name, u.email, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
        $stmtUser->execute([$user_id]);
        $userData = $stmtUser->fetch();
        
        $user_name = $userData['name'] ?? 'User';
        $user_email = $userData['email'] ?? '';
        $user_dept = $userData['department_name'] ?? ($role === 'alumni' ? 'Alumni' : 'Student');
        
        // Fetch Profile Details
        $user_bio = '';
        $user_linkedin = '';
        $user_github = '';
        $user_website = '';
        
        if ($role === 'alumni') {
            $stmtProf = $pdo->prepare("SELECT bio, linkedin, website FROM alumni_profiles WHERE user_id = ?");
            $stmtProf->execute([$user_id]);
            $profData = $stmtProf->fetch();
            $user_bio = $profData['bio'] ?? '';
            $user_linkedin = $profData['linkedin'] ?? '';
            $user_website = $profData['website'] ?? '';
        } else {
            $stmtProf = $pdo->prepare("SELECT bio, linkedin, github FROM student_profiles WHERE user_id = ?");
            $stmtProf->execute([$user_id]);
            $profData = $stmtProf->fetch();
            $user_bio = $profData['bio'] ?? '';
            $user_linkedin = $profData['linkedin'] ?? '';
            $user_github = $profData['github'] ?? '';
        }
        
        // Fetch Education
        $stmtEdu = $pdo->prepare("SELECT school, degree, field_of_study, start_year, end_year FROM education WHERE user_id = ? ORDER BY start_year DESC");
        $stmtEdu->execute([$user_id]);
        $eduData = $stmtEdu->fetchAll();
        
        // Fetch Experience
        $stmtExp = $pdo->prepare("SELECT position, company, location, start_date, end_date, description FROM experience WHERE user_id = ? ORDER BY start_date DESC");
        $stmtExp->execute([$user_id]);
        $expData = $stmtExp->fetchAll();
        
        // Fetch Skills
        $stmtSkills = $pdo->prepare("SELECT s.name, us.progress FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ?");
        $stmtSkills->execute([$user_id]);
        $skillsData = $stmtSkills->fetchAll();

        // 1. Build profile details text block for context
        $profile_context = "CANDIDATE PROFILE DETAILS:\n" .
                           "- Name: " . $user_name . "\n" .
                           "- Email: " . $user_email . "\n" .
                           "- Department/Major: " . $user_dept . "\n";
        if ($user_bio) $profile_context .= "- Summary/Bio: " . $user_bio . "\n";
        if ($user_linkedin) $profile_context .= "- LinkedIn: " . $user_linkedin . "\n";
        if ($user_github) $profile_context .= "- GitHub: " . $user_github . "\n";
        if ($user_website) $profile_context .= "- Website: " . $user_website . "\n";
        
        if ($eduData) {
            $profile_context .= "- Education:\n";
            foreach ($eduData as $edu) {
                $profile_context .= "  * " . $edu['degree'] . " in " . $edu['field_of_study'] . " at " . $edu['school'] . " (" . $edu['start_year'] . " - " . ($edu['end_year'] ?: 'Present') . ")\n";
            }
        }
        if ($expData) {
            $profile_context .= "- Experience:\n";
            foreach ($expData as $exp) {
                $profile_context .= "  * " . $exp['position'] . " at " . $exp['company'] . " (" . $exp['start_date'] . " - " . ($exp['end_date'] ?: 'Present') . ")\n";
                if ($exp['description']) $profile_context .= "    Description: " . $exp['description'] . "\n";
            }
        }
        if ($skillsData) {
            $profile_context .= "- Skills:\n";
            foreach ($skillsData as $sk) {
                $profile_context .= "  * " . $sk['name'] . " (" . $sk['progress'] . "% progress)\n";
            }
        }

        // 2. Build custom prompt copy-pasteable text
        $resume_prompt_text = "Act as an expert resume builder and executive resume writer. Write a professional, recruiter-ready resume in clean Markdown format using the following candidate profile details:\n\n" .
                              "**Candidate Information:**\n" .
                              "- **Name:** " . $user_name . "\n" .
                              "- **Email:** " . $user_email . "\n" .
                              "- **Department/Major:** " . $user_dept . "\n";
        if ($user_bio) $resume_prompt_text .= "- **Professional Summary:** " . $user_bio . "\n";
        if ($user_linkedin) $resume_prompt_text .= "- **LinkedIn:** " . $user_linkedin . "\n";
        if ($user_github) $resume_prompt_text .= "- **GitHub:** " . $user_github . "\n";
        if ($user_website) $resume_prompt_text .= "- **Website:** " . $user_website . "\n";
        
        if ($eduData) {
            $resume_prompt_text .= "\n**Education History:**\n";
            foreach ($eduData as $edu) {
                $resume_prompt_text .= "* **" . $edu['degree'] . "** in " . $edu['field_of_study'] . " at *" . $edu['school'] . "* (" . $edu['start_year'] . " - " . ($edu['end_year'] ?: 'Present') . ")\n";
            }
        }
        if ($expData) {
            $resume_prompt_text .= "\n**Professional Experience:**\n";
            foreach ($expData as $exp) {
                $resume_prompt_text .= "* **" . $exp['position'] . "** at *" . $exp['company'] . "* (" . $exp['start_date'] . " - " . ($exp['end_date'] ?: 'Present') . ")\n";
                if ($exp['description']) $resume_prompt_text .= "  *Description:* " . $exp['description'] . "\n";
            }
        }
        if ($skillsData) {
            $resume_prompt_text .= "\n**Skills & Expertise:**\n";
            foreach ($skillsData as $sk) {
                $resume_prompt_text .= "* " . $sk['name'] . " (Expertise: " . $sk['progress'] . "%)\n";
            }
        }
        $resume_prompt_text .= "\nFormatting Guidelines:\n1. Organize with clean headings.\n2. Use bullet points and action-oriented verbs.\n3. Make it highlight business impact and technical depth.";

        $response = "";
        $gemini_success = false;

        // Try to load Gemini API key
        $apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? null);
        if (!$apiKey) {
            try {
                $stmtKey = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'gemini_api_key'");
                $stmtKey->execute();
                $apiKey = $stmtKey->fetchColumn();
            } catch (Exception $e) {
                // ignore
            }
        }

        if ($apiKey && $apiKey !== 'GEMINI_API_KEY_FALLBACK') {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);
            
            // Build specialized system-prompt context
            $current_time = date('F j, Y, g:i A');
            $promptText = "System Context: Your name is AlumniNet Assistant. You are a supportive human helper representing AlumniNet. Never state that you are an AI assistant, an AI, or a large language model. Reply dynamically in a warm, encouraging, human tone. The current date/time is: " . $current_time . ". You can help users build a proper resume with a prompt. If the user asks for a prompt to create their resume, or to generate a prompt based on their information/profile details, generate a copy-pasteable prompt template containing their details. Always respond in markdown.\n\n" .
                          $profile_context . "\n\n" .
                          "User Question: " . $message;
            
            $postData = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $promptText]
                        ]
                    ]
                ]
            ];
            
            $options = [
                'http' => [
                    'header'  => "Content-Type: application/json\r\n",
                    'method'  => 'POST',
                    'content' => json_encode($postData),
                    'timeout' => 8, // 8 seconds timeout
                    'ignore_errors' => true
                ]
            ];
            
            try {
                $context  = stream_context_create($options);
                $apiResult = @file_get_contents($url, false, $context);
                if ($apiResult) {
                    $json = json_decode($apiResult, true);
                    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                        $response = $json['candidates'][0]['content']['parts'][0]['text'];
                        $gemini_success = true;
                    }
                }
            } catch (Exception $e) {
                // fail-silent and fallback
            }
        }

        // Rule-based fallback system if Gemini is not configured or fails
        if (!$gemini_success) {
            $lower_message = strtolower($message);
            
            // 0.0 RESUME PROMPT GENERATION
            if (preg_match('/\b(prompt)\b/i', $lower_message) && preg_match('/\b(resume|cv)\b/i', $lower_message)) {
                $response = "### 📝 Custom Resume Writing Prompt\n\n" .
                            "Here is a custom prompt containing your verified profile information. You can copy and paste this into ChatGPT or Gemini to write a professional, recruiter-ready resume:\n\n" .
                            "```text\n" .
                            $resume_prompt_text . "\n" .
                            "```\n\n" .
                            "💡 *Tip:* You can also launch the **[AlumniNet Resume Builder](../api/resume_builder.php)** to instantly generate a print-ready PDF resume directly on our platform.";
            }

            // 0.1 PROGRAMMING & CODING TOPICS
            elseif (preg_match('/\b(python|java|javascript|js|php|c\+\+|cpp|c#|html|css|sql|ruby|swift|rust|go|code|coding|program|programming|loop|function|array|database|quicksort|binary search)\b/i', $lower_message)) {
                $response = "### 💻 Technical & Programming Guide\n\nI detected a coding/technical query! While I am currently running on a rule-based engine, here is a structured path and code advice for students:\n\n" .
                            "#### 🚀 Essential Developer Roadmap:\n" .
                            "1. **Fundamentals first:** Master concepts like variables, loops, arrays, and standard control structures.\n" .
                            "2. **Version Control:** Learn `git` immediately. Every repository project requires committing and pushing changes.\n" .
                            "3. **Data Structures & Algorithms:** Key for cracking tech placements (practicing Quicksort, BFS/DFS, Binary Search).\n\n" .
                            "#### 📦 Quick Code Example (Python - Binary Search):\n" .
                            "```python\n" .
                            "def binary_search(arr, target):\n" .
                            "    low, high = 0, len(arr) - 1\n" .
                            "    while low <= high:\n" .
                            "        mid = (low + high) // 2\n" .
                            "        if arr[mid] == target:\n" .
                            "            return mid\n" .
                            "        elif arr[mid] < target:\n" .
                            "            low = mid + 1\n" .
                            "        else:\n" .
                            "            high = mid - 1\n" .
                            "    return -1\n" .
                            "```\n\n" .
                            "💡 *Tip:* Check out registered alumni at the **[Alumni Directory](../user/alumni.php)** who work at tech firms to ask them for a code review or mentorship session!";
            }
            
            // 0.2 COVER LETTERS & RESUME FORMATTING
            elseif (preg_match('/\b(cover letter|letter template|resume template|cv format|resume format|write resume|how to write cv|cv template)\b/i', $lower_message)) {
                $response = "### 📄 Professional Cover Letter & Resume Structure\n\nHere is a modern, high-conversion cover letter template you can adapt for your job applications:\n\n" .
                            "```markdown\n" .
                            "[Your Name]\n" .
                            "[Your Email] | [Your Phone] | [LinkedIn Link]\n\n" .
                            "Date: [Current Date]\n\n" .
                            "To:\n" .
                            "Hiring Team\n" .
                            "[Company Name]\n\n" .
                            "Dear Hiring Committee,\n\n" .
                            "I am writing to express my strong interest in the [Position Name] role at [Company Name]. As a student majoring in [Your Major] at AlumniNet, I have built practical experience in [Key Skill 1] and [Key Skill 2].\n\n" .
                            "In my portfolio, I completed [Project Name] which involved [brief impact/results]. I am excited to apply my dedication and problem-solving skills to help [Company Name] achieve its goals.\n\n" .
                            "Thank you for your time. I look forward to discussing how my background aligns with your requirements.\n\n" .
                            "Sincerely,\n" .
                            "[Your Name]\n" .
                            "```\n\n" .
                            "🌟 **Resume Builder Link:** You don't need to write one from scratch! Launch the **[AlumniNet Resume Builder](../api/resume_builder.php)** to instantly generate a professional, A4 printable resume containing your verified credentials.";
            }
            
            // 0.3 INTERVIEW QUESTIONS & MOCK PRACTICE
            elseif (preg_match('/\b(interview|mock|prep|questions|interview tips|interview prep|cracking)\b/i', $lower_message)) {
                $response = "### 🤝 Interview Preparation Guide\n\nHere are the top 3 behavioral questions you will encounter and how to tackle them:\n\n" .
                            "1. **\"Tell me about yourself.\"**\n" .
                            "   * *Strategy:* Use the **Present-Past-Future** framework. Summarize your current studies, past key projects/skills, and why you are excited about this specific job.\n" .
                            "2. **\"Describe a challenging technical problem you solved.\"**\n" .
                            "   * *Strategy:* Use the **STAR** method (Situation, Task, Action, Result). Quantify results whenever possible (e.g. \"improved database speed by 35%\").\n" .
                            "3. **\"Why do you want to work here?\"**\n" .
                            "   * *Strategy:* Research the company's product line and mention how it inspires you.\n\n" .
                            "💡 *Tip:* You can request mock interviews directly from verified alumni! Head to the **[Alumni Directory](../user/alumni.php)**, connect with an approved alum working in your target industry, and send them a mentorship request.";
            }

            // 1. PLACEMENT & INTERNSHIP QUERIES
            elseif (strpos($lower_message, 'job') !== false || strpos($lower_message, 'internship') !== false || strpos($lower_message, 'placement') !== false || strpos($lower_message, 'vacancy') !== false) {
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
            
            // 6. GREETINGS
            elseif (preg_match('/\b(hi|hello|hey|hola|greetings)\b/', $lower_message)) {
                $response = "Hello! Hope you are doing well today. It is currently " . date('g:i A') . ". I am the AlumniNet Assistant. How can I help you today? You can ask me about placements, events, directory connections, or even ask me to generate a custom resume prompt based on your profile details!";
            }
            
            // 7. ABOUT / CREATOR
            elseif (strpos($lower_message, 'who created') !== false || strpos($lower_message, 'about') !== false || strpos($lower_message, 'antigravity') !== false) {
                $response = "I am the AlumniNet Assistant, built as part of the advanced ARMS mini-project update. I help students navigate jobs, check event details, and scoring requirements!";
            }
            
            // 8. CHAT / MESSENGER
            elseif (strpos($lower_message, 'chat') !== false || strpos($lower_message, 'message') !== false || strpos($lower_message, 'messenger') !== false) {
                $response = "You can chat with alumni by visiting the [Alumni Directory](../user/alumni.php) and clicking the 'Chat' button on their profile card. Or open the [Messenger](../user/chat.php) directly from your sidebar!";
            }
            
            // 9. CERTIFICATES / UPLOADS
            elseif (strpos($lower_message, 'certificate') !== false || strpos($lower_message, 'upload') !== false || strpos($lower_message, 'achievement') !== false) {
                $response = "You can manage certificates, build resumes, and showcase honors in your [My Portfolio](../user/portfolio.php) dashboard. Try uploading PDF documents or image credentials!";
            }
            
            // 10. FEEDBACK
            elseif (strpos($lower_message, 'feedback') !== false || strpos($lower_message, 'review') !== false || strpos($lower_message, 'bug') !== false) {
                $response = "We value your input! Go to the [Feedback](../user/feedback.php) page in the sidebar to rate our system, report bugs, or share feature requests directly with the administrator.";
            }
            
            // 11. DEFAULT VARIANT FALLBACKS (Resolves repeating answers bug)
            else {
                $stmtPrompt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'ai_prompt'");
                $stmtPrompt->execute();
                $custom_prompt = $stmtPrompt->fetchColumn();
                
                $greeting = "I am the AlumniNet Assistant. How can I help you today?";
                if ($custom_prompt) {
                    $greeting = htmlspecialchars($custom_prompt);
                }
                
                $querySnippet = htmlspecialchars(strlen($message) > 40 ? substr($message, 0, 40) . '...' : $message);
                
                $response = "### ℹ️ AlumniNet Assistant (Local Mode)\n\n" .
                            "You asked: *\"" . $querySnippet . "\"*\n\n" .
                            "Currently, the AI Assistant is running on the **local rule-based engine**. To enable **Advanced General AI Answers** (capable of solving coding bugs, writing customized content, and answering any query):\n\n" .
                            "1. **Get an API Key:** Register a free API Key on [Google AI Studio](https://aistudio.google.com/).\n" .
                            "2. **Configure Settings:** Log in as an Administrator, go to **Admin Dashboard -> Enterprise Control**, and save your Gemini API Key in the settings.\n\n" .
                            "💡 *Resume Prompt Tip:* Did you know you can build a proper resume with a prompt? Ask me to *\"give me a prompt to build my resume\"* and I will compile your details into a custom prompt for ChatGPT/Gemini!\n\n" .
                            "**In local mode, here are things you can ask me about:**\n" .
                            "* 💼 *\"Are there any jobs available?\"* - Show active internship postings.\n" .
                            "* 📅 *\"Tell me about upcoming events.\"* - View the next campus reunions/meetups.\n" .
                            "* 📂 *\"How do I download my CV?\"* - Open the [Resume Builder](../api/resume_builder.php).\n" .
                            "* 💻 *\"Help me with Python coding / interview prep.\"* - Open interactive guides.";
            }
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

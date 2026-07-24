<?php
require_once __DIR__ . '/includes/db.php';

try {
    // Disable foreign key checks for clean truncation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Clear data from tables
    $pdo->exec("TRUNCATE TABLE event_rsvps;");
    $pdo->exec("TRUNCATE TABLE mentorship_requests;");
    $pdo->exec("TRUNCATE TABLE alumni_profiles;");
    $pdo->exec("DELETE FROM student_profiles WHERE user_id != 1;");
    $pdo->exec("TRUNCATE TABLE jobs;");
    $pdo->exec("TRUNCATE TABLE events;");
    $pdo->exec("DELETE FROM users WHERE id != 1;");
    
    // Enable foreign key checks back
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Standard Bcrypt password hash for mock users: 'password123'
    $mock_password = password_hash('password123', PASSWORD_BCRYPT);
    $mock_password_user = password_hash('User@123', PASSWORD_BCRYPT);
    $admin_password_hash = password_hash('Admin@123', PASSWORD_BCRYPT);

    // Update Admin user (ID = 1) - Ashwin Pande
    $stmtAdminUpdate = $pdo->prepare("UPDATE users SET name = 'Ashwin Pande', email = 'admin@internship.com', username = 'admin', password = ?, phone = '9226830066', role = 'admin', status = 'approved' WHERE id = 1");
    $stmtAdminUpdate->execute([$admin_password_hash]);

    // Also update in admins table if exists
    $stmtAdminTableUpdate = $pdo->prepare("UPDATE admins SET name = 'Ashwin Pande', email = 'admin@internship.com', username = 'admin', password = ? WHERE user_id = 1");
    $stmtAdminTableUpdate->execute([$admin_password_hash]);

    // Seed other admins
    $other_admins = [
        ['Ravindra Mude', 'ravindramude44@gmail.com', 'ravindra', $admin_password_hash, '9209276332'],
        ['Yashraj Nanaware', 'yashrajnanaware0@gmail.com', 'yashraj', $admin_password_hash, '9325818393'],
        ['Kaif Khan', 'alikaif8585@gmail.com', 'kaif', $admin_password_hash, '9589904746']
    ];

    $insert_admin_user = $pdo->prepare("INSERT INTO users (name, email, username, password, phone, role, status, department_id) VALUES (?, ?, ?, ?, ?, 'admin', 'approved', 1)");
    $insert_admin_record = $pdo->prepare("INSERT INTO admins (user_id, username, name, email, password, role) VALUES (?, ?, ?, ?, ?, 'superadmin')");

    foreach ($other_admins as $oa) {
        $insert_admin_user->execute([$oa[0], $oa[1], $oa[2], $oa[3], $oa[4]]);
        $new_user_id = $pdo->lastInsertId();
        $insert_admin_record->execute([$new_user_id, $oa[2], $oa[0], $oa[1], $oa[3]]);
    }

    // 1. Insert mock users (Alumni and Students)
    $mock_users = [
        // Alumni (Roles = 'alumni', Status = 'approved')
        ['Sarah Jenkins', 'sarah@google.com', $mock_password, 'alumni', 'approved'],
        ['David Miller', 'david@microsoft.com', $mock_password, 'alumni', 'approved'],
        ['Emily Chen', 'emily@stripe.com', $mock_password, 'alumni', 'approved'],
        ['Michael Scott', 'michael@dundermifflin.com', $mock_password, 'alumni', 'approved'],
        ['Jessica Taylor', 'jessica@netflix.com', $mock_password, 'alumni', 'approved'],
        ['Marcus Aurelius', 'marcus@stoic.com', $mock_password, 'alumni', 'pending'], // Pending approval!
        ['Robert Stark', 'robert@winterfell.org', $mock_password, 'alumni', 'rejected'], // Rejected approval!
        
        // Students (Roles = 'student', Status = 'approved')
        ['Demo Student User', 'user@internship.com', $mock_password_user, 'student', 'approved'],
        ['Charlie Brown', 'charlie@student.com', $mock_password, 'student', 'approved'],
        ['Diana Prince', 'diana@student.com', $mock_password, 'student', 'approved'],
        ['Peter Parker', 'peter@dailybugle.com', $mock_password, 'student', 'approved'],

        // New Alumni from registration forms
        ['Namrata Shankar Parab', 'nam20parab@gmail.com', $mock_password, 'alumni', 'approved', '8308758413', 4],
        ['Vaishnavi Valmik Pawar', 'vp171666@gmail.com', $mock_password, 'alumni', 'approved', '9175742480', 4],
        ['Mayur Ganesh Todkar', 'mayurt2312@gmail.com', $mock_password, 'alumni', 'approved', '9359724105', 4],
        ['Tanaya Khare', 'kharetanaya67@gmail.com', $mock_password, 'alumni', 'approved', '7385966019', 4],
        ['Supkar Darpan Rajeshree', 'darpan.supkar.12@gmail.com', $mock_password, 'alumni', 'approved', '8999375490', 4],
        ['Atharv Rahul Taware', 'atharvtaware@gmail.com', $mock_password, 'alumni', 'approved', '7218945407', 4],
        ['More Pratiket Vijaykumar', 'pratiketmore@gmail.com', $mock_password, 'alumni', 'approved', '8975025652', 4],
        ['Sumeet Nathuji Satpute', 'sumeetsatpute2562@gmail.com', $mock_password, 'alumni', 'approved', '9359128011', 4],
        ['Shaktiprasad Sadanand Patra', 'shaktiprasadpatra4@gmail.com', $mock_password, 'alumni', 'approved', '7028162381', 4]
    ];

    $insert_user_stmt = $pdo->prepare("INSERT INTO users (name, email, username, password, role, status, phone, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $userIds = [];
    foreach ($mock_users as $u) {
        $username = explode('@', $u[1])[0];
        $phone = $u[5] ?? null;
        $dept_id = $u[6] ?? null;
        $insert_user_stmt->execute([$u[0], $u[1], $username, $u[2], $u[3], $u[4], $phone, $dept_id]);
        $userIds[$u[1]] = $pdo->lastInsertId();
    }

    // Add references
    $userIds['ravindramude44@gmail.com'] = $userIds['user@internship.com'];

    // 2. Insert Alumni Profiles
    $alumni_profiles = [
        [
            $userIds['sarah@google.com'], 
            2021, 
            'Computer Science Engineering', 
            'Google', 
            'Senior Software Engineer', 
            'Technology', 
            'https://linkedin.com/in/sarah-jenkins-mock', 
            'https://sarahj.dev', 
            'Passionate about distributed systems, backend architectures, and machine learning. Always happy to mentor students interested in tech giants.',
            'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&fit=crop&q=80'
        ],
        [
            $userIds['david@microsoft.com'], 
            2019, 
            'Information Technology', 
            'Microsoft', 
            'Product Manager', 
            'Software & PM', 
            'https://linkedin.com/in/david-miller-mock', 
            '', 
            'Experienced in launching cloud products. Helping students transition from coding to product strategy, agile methods, and system design.',
            'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400&fit=crop&q=80'
        ],
        [
            $userIds['emily@stripe.com'], 
            2022, 
            'Electrical & Electronics Engineering', 
            'Stripe', 
            'Frontend Engineer', 
            'Fintech', 
            'https://linkedin.com/in/emily-chen-mock', 
            'https://emilychen.io', 
            'Specializing in modern JavaScript frameworks, UX design, and developer tools. Let’s talk about design systems and state management!',
            'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=400&fit=crop&q=80'
        ],
        [
            $userIds['michael@dundermifflin.com'], 
            2015, 
            'Business Administration', 
            'Dunder Mifflin Paper Co.', 
            'Regional Manager', 
            'Sales', 
            'https://linkedin.com/in/michael-scott-mock', 
            '', 
            'Management guru, improvisational actor, and expert paper salesman. You miss 100% of the shots you don’t take.',
            'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=400&fit=crop&q=80'
        ],
        [
            $userIds['jessica@netflix.com'], 
            2020, 
            'Computer Science Engineering', 
            'Netflix', 
            'Infrastructure Engineer', 
            'Streaming Media', 
            'https://linkedin.com/in/jessica-taylor-mock', 
            '', 
            'Keeping systems scaling under massive load. Focusing on Kubernetes, AWS ecosystem, and site reliability.',
            'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=400&fit=crop&q=80'
        ],
        [
            $userIds['marcus@stoic.com'], 
            2018, 
            'Philosophy & Humanities', 
            'Rome Advisory Group', 
            'Lead Philosopher', 
            'Education', 
            '', 
            '', 
            'Meditations on leadership, patience, and logic in software engineering environments.',
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&fit=crop&q=80'
        ],
        [
            $userIds['robert@winterfell.org'], 
            2012, 
            'Mechanical Engineering', 
            'Winterfell Industries', 
            'Chief Warden', 
            'Heavy Industry', 
            '', 
            '', 
            'Winter is coming, and so are industrial challenges. Mechanical structures and heavy dynamics.',
            'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&fit=crop&q=80'
        ],
        [
            $userIds['nam20parab@gmail.com'],
            2023,
            'Information Technology',
            'ATOS Global',
            'Trainee Engineer',
            'Tech',
            '',
            '',
            'Trainee Engineer at ATOS Global. Specialized in Information Technology, graduated in 2023.',
            ''
        ],
        [
            $userIds['vp171666@gmail.com'],
            2023,
            'Information Technology',
            'TCS',
            'Assistant Software Engineer',
            'Tech',
            '',
            '',
            'Assistant Software Engineer at TCS. Information Technology graduate class of 2023.',
            ''
        ],
        [
            $userIds['mayurt2312@gmail.com'],
            2023,
            'Information Technology',
            'ATOS',
            'Software Trainee',
            'Tech',
            '',
            '',
            'Software Trainee at ATOS. Graduated in Information Technology, class of 2023.',
            ''
        ],
        [
            $userIds['kharetanaya67@gmail.com'],
            2023,
            'Information Technology',
            'e-Emphasys Pvt. Ltd.',
            'Associate System Engineer',
            'Tech',
            '',
            '',
            'Associate System Engineer at e-Emphasys Pvt. Ltd. Information Technology class of 2023.',
            ''
        ],
        [
            $userIds['darpan.supkar.12@gmail.com'],
            2023,
            'Information Technology',
            'Intellipaat',
            'BDA',
            'EdTech',
            '',
            '',
            'Business Development Associate (BDA) at Intellipaat. Information Technology class of 2023.',
            ''
        ],
        [
            $userIds['atharvtaware@gmail.com'],
            2023,
            'Information Technology',
            'ATOS',
            'Software Trainee',
            'Tech',
            '',
            '',
            'Software Trainee at ATOS. Information Technology class of 2023.',
            ''
        ],
        [
            $userIds['pratiketmore@gmail.com'],
            2023,
            'Information Technology',
            'Academor',
            'Academic Counsellor',
            'EdTech',
            '',
            '',
            'Academic Counsellor at Academor. Information Technology class of 2023.',
            ''
        ],
        [
            $userIds['sumeetsatpute2562@gmail.com'],
            2023,
            'Information Technology',
            'TCS',
            'Software Eng. Trainee',
            'Tech',
            '',
            '',
            'Software Eng. Trainee at TCS. Information Technology class of 2023.',
            ''
        ],
        [
            $userIds['shaktiprasadpatra4@gmail.com'],
            2023,
            'Information Technology',
            'Xsymplify',
            'BDM',
            'Tech',
            '',
            '',
            'Business Development Manager (BDM) at Xsymplify. Information Technology class of 2023.',
            ''
        ]
    ];

    $insert_alumni_stmt = $pdo->prepare("INSERT INTO alumni_profiles (user_id, graduation_year, course, company, position, industry, linkedin, website, bio, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($alumni_profiles as $ap) {
        $insert_alumni_stmt->execute($ap);
    }

    // 3. Insert Student Profiles
    $student_profiles = [
        [
            $userIds['user@internship.com'], 
            3, 
            'Computer Science Engineering', 
            'Passionate full stack developer learning cloud native app architectures.', 
            'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=400&fit=crop&q=80',
            'https://linkedin.com/in/demostudent',
            'https://github.com/demostudent'
        ],
        [
            $userIds['charlie@student.com'], 
            2, 
            'Computer Science Engineering', 
            'Aspiring Android developer. Love building mobile apps and designing beautiful interfaces.', 
            'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&fit=crop&q=80',
            'https://linkedin.com/in/charlie-brown-mock',
            'https://github.com/charlie-brown-mock'
        ],
        [
            $userIds['diana@student.com'], 
            4, 
            'Electronics & Communication Engineering', 
            'Senior student focusing on embedded hardware programming and IoT devices. Interested in tech hardware sectors.', 
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&fit=crop&q=80',
            'https://linkedin.com/in/diana-prince-mock',
            'https://github.com/diana-prince-mock'
        ],
        [
            $userIds['peter@dailybugle.com'], 
            1, 
            'Physics & Applied Sciences', 
            'First year student. Interested in biophysics and photojournalism.', 
            'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&fit=crop&q=80',
            'https://linkedin.com/in/peter-parker-mock',
            'https://github.com/peter-parker-mock'
        ]
    ];

    $insert_student_stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, current_year, course, bio, profile_pic, linkedin, github) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($student_profiles as $sp) {
        $insert_student_stmt->execute($sp);
    }

    // 4. Insert Job Postings (Active, Filled, Expired)
    $jobs = [
        [
            $userIds['sarah@google.com'],
            'user',
            'Cloud Software Engineering Intern',
            'Google',
            'Bangalore, India (Hybrid)',
            'internship',
            '₹45,000 - ₹60,000 / mo',
            'Google is hiring software engineering interns to build components for Google Cloud Platform. You will work on real APIs and features.',
            'Currently pursuing a degree in CS or equivalent. Experience in Go, Java, or C++. Understanding of algorithms and database design.',
            'https://careers.google.com',
            'active'
        ],
        [
            $userIds['emily@stripe.com'],
            'user',
            'Full Stack Developer (React & Ruby)',
            'Stripe',
            'Remote (India)',
            'full-time',
            '₹15L - ₹22L / year',
            'Join our billing integrations team to build modern UI checkouts, API connectors, and dashboard analytics tools.',
            '3+ years experience with React/TypeScript and Ruby on Rails or Node.js. Great testing habits and experience with payment flows.',
            'https://stripe.com/jobs',
            'active'
        ],
        [
            1, // Posted by Admin
            'admin',
            'Junior IT Systems Associate',
            'Infosys Technologies',
            'Pune, India',
            'full-time',
            '₹6L - ₹8L / year',
            'Manage company server setups, assist in local network routing issues, and configure workspace endpoints.',
            'B.Tech/BE in CS/IT/ECE. Solid knowledge of Linux administration, shell scripting, and basic network protocols.',
            'https://infosys.com/careers',
            'active'
        ],
        [
            $userIds['david@microsoft.com'],
            'user',
            'Associate Product Manager',
            'Microsoft',
            'Hyderabad, India',
            'full-time',
            '₹18L - ₹24L / year',
            'Own roadmap execution, gather customer requirements, and coordinate engineering cycles.',
            '1+ year of engineering or analyst experience. Excellent verbal communication and analytic presentation skills.',
            'https://careers.microsoft.com',
            'filled'
        ]
    ];

    $insert_job_stmt = $pdo->prepare("INSERT INTO jobs (posted_by, poster_role, title, company, location, type, salary_range, description, requirements, application_link, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($jobs as $j) {
        $insert_job_stmt->execute($j);
    }

    // 5. Insert Events (Upcoming and Past)
    $events = [
        [
            'Grand Annual Homecoming 2026',
            'Welcome back, alumni! Join us for a night of networking, institutional updates from the Dean, and dynamic dinner panels with classmates.',
            date('Y-m-d H:i:s', strtotime('+30 days')),
            'Grand Campus Auditorium, Main Wing',
            'in-person',
            'https://images.unsplash.com/photo-1511578314322-379afb476865?w=800&fit=crop&q=80',
            1
        ],
        [
            'Tech Transition: Students to Startups',
            'An online interactive fireside chat with alumni working in high-growth startups. Discussing captable funding, tech stacks, and career growth.',
            date('Y-m-d H:i:s', strtotime('+10 days')),
            'Zoom Webinar Link will be sent upon RSVP',
            'online',
            'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&fit=crop&q=80',
            1
        ],
        [
            'Mentoring Open Office Hours',
            'One-on-one virtual breakout rooms matching junior students with engineering managers for code review and resume polishing.',
            date('Y-m-d H:i:s', strtotime('+5 days')),
            'Virtual Gather.Town Campus Space',
            'online',
            'https://images.unsplash.com/photo-1515187029135-18ee286d815b?w=800&fit=crop&q=80',
            1
        ],
        [
            'Spring Homecoming Dinner 2026',
            'A retrospective gathering looking back at structural milestones of the Engineering department over the past 20 years.',
            date('Y-m-d H:i:s', strtotime('-15 days')), // PAST EVENT
            'Campus Gardens & Lounge Area',
            'in-person',
            'https://images.unsplash.com/photo-1469371670807-013ccf25f16a?w=800&fit=crop&q=80',
            1
        ]
    ];

    $insert_event_stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, location, event_type, banner_image, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($events as $ev) {
        $insert_event_stmt->execute($ev);
    }

    // 6. Insert Mock RSVPs
    // Fetch newly created event IDs
    $stmtEvents = $pdo->query("SELECT id FROM events");
    $event_ids = $stmtEvents->fetchAll(PDO::FETCH_COLUMN);

    $insert_rsvp_stmt = $pdo->prepare("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?, ?, ?)");
    
    // RSVP students to upcoming events
    if (count($event_ids) >= 3) {
        $insert_rsvp_stmt->execute([$event_ids[0], $userIds['user@internship.com'], 'going']); // Default user going to Homecoming
        $insert_rsvp_stmt->execute([$event_ids[0], $userIds['charlie@student.com'], 'interested']);
        $insert_rsvp_stmt->execute([$event_ids[1], $userIds['user@internship.com'], 'going']); // Default user going to Tech Transition
        $insert_rsvp_stmt->execute([$event_ids[1], $userIds['diana@student.com'], 'going']);
        $insert_rsvp_stmt->execute([$event_ids[2], $userIds['sarah@google.com'], 'going']); // Sarah as mentor going
    }

    // 7. Insert Mock Mentorship Requests
    $mentorship_requests = [
        [$userIds['user@internship.com'], $userIds['sarah@google.com'], 'Hi Sarah! I am very interested in Google Cloud technologies and hope to get your guidance on prep.', 'pending'],
        [$userIds['user@internship.com'], $userIds['david@microsoft.com'], 'Hello David, I would appreciate your input on transition into PM roles. Thanks!', 'accepted'],
        [$userIds['charlie@student.com'], $userIds['sarah@google.com'], 'Hey Sarah, I want to learn more about golang servers.', 'accepted'],
        [$userIds['diana@student.com'], $userIds['emily@stripe.com'], 'Hi Emily! I am working on a React dashboard, can you review it?', 'pending']
    ];

    $insert_mentorship_stmt = $pdo->prepare("INSERT INTO mentorship_requests (student_id, alumni_id, message, status) VALUES (?, ?, ?, ?)");
    foreach ($mentorship_requests as $mr) {
        $insert_mentorship_stmt->execute($mr);
    }

    echo "Database successfully seeded with realistic mock data!\n";
} catch (Exception $e) {
    echo "Seeding failed with error: " . $e->getMessage() . "\n";
}
?>

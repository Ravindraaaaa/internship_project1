CREATE DATABASE IF NOT EXISTS internship_project1;
USE internship_project1;

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    code VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users Table (Handles Students, Alumni, and Admin Accounts)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    role ENUM('student', 'alumni', 'admin') NOT NULL DEFAULT 'student',
    status ENUM('pending', 'approved', 'rejected', 'blocked') NOT NULL DEFAULT 'pending',
    department_id INT,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Dedicated Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    username VARCHAR(150) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'moderator') NOT NULL DEFAULT 'moderator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Alumni Profiles Table
CREATE TABLE IF NOT EXISTS alumni_profiles (
    user_id INT PRIMARY KEY,
    graduation_year INT NOT NULL,
    course VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    position VARCHAR(255),
    industry VARCHAR(255),
    linkedin VARCHAR(255),
    website VARCHAR(255),
    bio TEXT,
    profile_pic VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Student Profiles Table
CREATE TABLE IF NOT EXISTS student_profiles (
    user_id INT PRIMARY KEY,
    current_year INT NOT NULL,
    course VARCHAR(255) NOT NULL,
    bio TEXT,
    profile_pic VARCHAR(255),
    linkedin VARCHAR(255),
    github VARCHAR(255),
    cgpa DECIMAL(3,2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Companies Table
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    website VARCHAR(255),
    location VARCHAR(255),
    logo VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Jobs Table
CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    company_id INT,
    company VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT,
    location VARCHAR(255) NOT NULL,
    salary_range VARCHAR(100),
    type VARCHAR(100) NOT NULL DEFAULT 'full-time',
    application_link VARCHAR(255) NOT NULL DEFAULT '',
    posted_by INT NOT NULL,
    poster_role ENUM('admin', 'user') DEFAULT 'user',
    status ENUM('active', 'filled', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Job Applications Table
CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    resume_path VARCHAR(255),
    status ENUM('applied', 'reviewing', 'interviewing', 'offered', 'rejected') DEFAULT 'applied',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Events Table
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    event_date DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    event_type ENUM('in-person', 'online') NOT NULL DEFAULT 'in-person',
    banner_image VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Event RSVPs Table
CREATE TABLE IF NOT EXISTS event_rsvps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('attending', 'interested', 'declined', 'going', 'not_going') DEFAULT 'attending',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Event Registrations (Alias View)
CREATE OR REPLACE VIEW event_registrations AS SELECT * FROM event_rsvps;

-- 12. Announcements Table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    audience ENUM('all', 'students', 'alumni') DEFAULT 'all',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Conversations Table
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Messages Table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Skills Table
CREATE TABLE IF NOT EXISTS skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. User Skills Mapping Table
CREATE TABLE IF NOT EXISTS user_skills (
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    progress INT NOT NULL DEFAULT 50,
    PRIMARY KEY (user_id, skill_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Education Table
CREATE TABLE IF NOT EXISTS education (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    school VARCHAR(255) NOT NULL,
    degree VARCHAR(255),
    field_of_study VARCHAR(255),
    start_year INT,
    end_year INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Experience Table
CREATE TABLE IF NOT EXISTS experience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company VARCHAR(255) NOT NULL,
    position VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    start_date DATE,
    end_date DATE,
    description TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Resumes Table
CREATE TABLE IF NOT EXISTS resumes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. Resume (Alias View)
CREATE OR REPLACE VIEW resume AS SELECT * FROM resumes;

-- 22. Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. Settings Table
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24. Themes Table
CREATE TABLE IF NOT EXISTS themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    class_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 25. Backgrounds Table
CREATE TABLE IF NOT EXISTS backgrounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    value VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 26. Password Resets Table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 27. Login History Table
CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    status ENUM('success', 'failed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 28. Mentorship Requests Table (Core connection feature)
CREATE TABLE IF NOT EXISTS mentorship_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    alumni_id INT NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (alumni_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====================================================
-- SEED DATA
-- ====================================================

-- Departments Seeding
INSERT INTO departments (id, name, code) VALUES
(1, 'Computer Science and Engineering', 'CSE'),
(2, 'Electronics and Communication', 'ECE'),
(3, 'Mechanical Engineering', 'ME'),
(4, 'Information Technology', 'IT');

-- Users Seeding
-- Admin Password hash for Admin@123 -> $2y$10$Nhr7x4dbWSYTv24IweLQ/exOFoHzfkQtnZAec.ATnodInY.PN0zla
-- User Password hash for User@123 -> $2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6
INSERT INTO users (id, name, email, username, password, role, status, department_id) VALUES
(1, 'System Administrator', 'admin@internship.com', 'admin', '$2y$10$Nhr7x4dbWSYTv24IweLQ/exOFoHzfkQtnZAec.ATnodInY.PN0zla', 'admin', 'approved', 1),
(2, 'Demo Student User', 'user@internship.com', 'user', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'student', 'approved', 1),
(3, 'Jane Doe (Alumni)', 'jane@alumni.com', 'jane_alumni', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'alumni', 'approved', 1),
(4, 'John Smith (Alumni)', 'john@alumni.com', 'john_alumni', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'alumni', 'approved', 2),
(5, 'Alice Johnson (Student)', 'alice@alumni.com', 'alice_std', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'student', 'approved', 3),
(6, 'Bob Wilson (Alumni)', 'bob@alumni.com', 'bob_alumni', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'alumni', 'approved', 4),
(7, 'Charlie Brown (Student)', 'charlie@alumni.com', 'charlie_std', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'student', 'approved', 1),
(8, 'David Miller (Alumni)', 'david@alumni.com', 'david_alumni', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'alumni', 'pending', 1),
(9, 'Emily Davis (Alumni)', 'emily@alumni.com', 'emily_alumni', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'alumni', 'rejected', 2),
(10, 'Frank Thomas (Student)', 'frank@alumni.com', 'frank_std', '$2y$10$PVbiinkikEIi8EXrAuHsFuPfk/BtLHQu4RBjT32IbELTZKcv3SsQ6', 'student', 'approved', 4);

-- Admins Seeding
INSERT INTO admins (id, user_id, username, name, email, password, role) VALUES
(1, 1, 'admin', 'System Administrator', 'admin@internship.com', '$2y$10$Nhr7x4dbWSYTv24IweLQ/exOFoHzfkQtnZAec.ATnodInY.PN0zla', 'superadmin');

-- Alumni Profiles Seeding
INSERT INTO alumni_profiles (user_id, graduation_year, course, company, position, industry, linkedin, website, bio) VALUES
(3, 2021, 'Computer Science Engineering', 'Google', 'Software Engineer', 'Tech', 'linkedin.com/in/jane', 'jane.dev', 'Building highly scalable search architectures.'),
(4, 2020, 'Electronics & Communication', 'Intel', 'Hardware Designer', 'Hardware', 'linkedin.com/in/john', 'john.dev', 'Designing low latency CPU memory micro-controllers.'),
(6, 2019, 'Information Technology', 'Microsoft', 'Senior Product Engineer', 'Tech', 'linkedin.com/in/bob', 'bob.dev', 'Specializing in Azure cloud developer productivity services.'),
(8, 2022, 'Computer Science Engineering', 'Meta', 'Production Engineer', 'Tech', 'linkedin.com/in/david', 'david.dev', 'System engineering and site reliability optimization.'),
(9, 2018, 'Electronics & Communication', 'Tesla', 'Firmware Engineer', 'Tech', 'linkedin.com/in/emily', 'emily.dev', 'Autonomous battery system management integration.');

-- Student Profiles Seeding
INSERT INTO student_profiles (user_id, current_year, course, bio, linkedin, github) VALUES
(2, 3, 'Computer Science Engineering', 'Passionate full stack developer learning cloud native app architectures.', 'linkedin.com/in/demostudent', 'github.com/demostudent'),
(5, 4, 'Mechanical Engineering', 'Robotics explorer focused on CAD automation and manufacturing loops.', 'linkedin.com/in/alice', 'github.com/alice'),
(7, 2, 'Computer Science Engineering', 'Algorithm developer and competitive programmer with a passion for web assembly.', 'linkedin.com/in/charlie', 'github.com/charlie'),
(10, 3, 'Information Technology', 'Security analyst interested in network penetration testing and IAM policies.', 'linkedin.com/in/frank', 'github.com/frank');

-- Companies Seeding
INSERT INTO companies (id, name, website, location) VALUES
(1, 'Google', 'google.com', 'Mountain View, CA'),
(2, 'Intel', 'intel.com', 'Santa Clara, CA'),
(3, 'Microsoft', 'microsoft.com', 'Redmond, WA'),
(4, 'Meta', 'meta.com', 'Menlo Park, CA'),
(5, 'Tesla', 'tesla.com', 'Austin, TX');

-- Jobs Seeding
INSERT INTO jobs (id, title, company_id, company, location, type, salary_range, description, requirements, application_link, status, posted_by, poster_role) VALUES
(1, 'Full Stack Web Developer', 1, 'Google', 'Mountain View, CA (Hybrid)', 'full-time', '$135,000 - $165,000', 'Develop premium SaaS dashboard systems using GSAP, CSS, and modern framework layers.', 'HTML, CSS, JavaScript, PHP, MySQL', 'https://careers.google.com', 'active', 3, 'user'),
(2, 'Hardware Designer', 2, 'Intel', 'Santa Clara, CA', 'full-time', '$140,000 - $170,000', 'Integrate micro-assembly memory architectures and low latency chip grids.', 'Verilog, VHDL, Assembly, C++', 'https://intel.com/jobs', 'active', 4, 'user'),
(3, 'Cloud Architect', 3, 'Microsoft', 'Redmond, WA (Remote)', 'full-time', '$150,000 - $190,000', 'Deploy cloud templates, sync distributed database structures and configure IAM policies.', 'Azure, Terraform, Docker, Kubernetes', 'https://careers.microsoft.com', 'active', 6, 'user'),
(4, 'Production Engineer', 4, 'Meta', 'Menlo Park, CA', 'full-time', '$160,000 - $210,000', 'Optimize Linux kernels and deploy CI/CD scripts for low latency messaging pipelines.', 'Python, Go, Linux Kernels, AWS/GCP', 'https://meta.com/jobs', 'active', 1, 'admin'),
(5, 'Battery Control Engineer', 5, 'Tesla', 'Austin, TX', 'full-time', '$120,000 - $150,000', 'Develop firmware modules for vehicle autonomy battery pack cooling systems.', 'C, Embedded Systems, RTOS', 'https://tesla.com/jobs', 'active', 1, 'admin'),
(6, 'Frontend Specialist', 1, 'Google', 'San Francisco, CA', 'full-time', '$115,000 - $140,000', 'Redesign existing legacy portal dashboards into responsive SaaS configurations.', 'CSS, GSAP, SVG, Canvas, JS', 'https://careers.google.com', 'active', 3, 'user'),
(7, 'Security Analyst', 3, 'Microsoft', 'Remote', 'full-time', '$105,000 - $130,000', 'Audit system architectures, set firewall rules, and track activity loops.', 'OWASP, Kali Linux, Networking, Wireshark', 'https://careers.microsoft.com', 'active', 6, 'user'),
(8, 'Site Reliability Engineer', 4, 'Meta', 'Seattle, WA', 'full-time', '$145,000 - $180,000', 'Maintain high availability levels and monitor network packet routing logs.', 'Prometheus, Grafana, Ansible', 'https://meta.com/jobs', 'active', 1, 'admin'),
(9, 'Data Coordinator', 2, 'Intel', 'Fremont, CA', 'full-time', '$95,000 - $120,000', 'Structure high fidelity database logs and execute complex SQL aggregations.', 'SQL, Postgres, Python, Pandas', 'https://intel.com/jobs', 'active', 4, 'user'),
(10, 'Product Lead', 5, 'Tesla', 'Palo Alto, CA', 'full-time', '$130,000 - $160,000', 'Define product specifications for next-generation charging grid controllers.', 'Scrum, Agile, JIRA, Technical Writing', 'https://tesla.com/jobs', 'active', 1, 'admin');

-- Events Seeding
INSERT INTO events (id, title, description, event_date, location, event_type, banner_image, created_by) VALUES
(1, 'Tech Reunion & Networking Evening', 'Reunite with computing graduates and explore mentorship programs and referrals.', '2026-10-15 18:00:00', 'Campus Auditorium', 'in-person', '', 1),
(2, 'Careers in Autonomy & Embedded Systems', 'Fireside panel with alumni working in hardware and electrical automation circles.', '2026-11-20 14:00:00', 'Zoom Webinar Link', 'online', '', 1),
(3, 'Alumni Mentorship Roundtable', 'Interactive matching session connecting student members with industry experts.', '2026-08-05 10:00:00', 'Seminar Hall C', 'in-person', '', 1),
(4, 'Cloud Native Architecture Trends', 'Technical deep-dive on microservices patterns and site reliability metrics.', '2026-09-12 17:00:00', 'MS Teams Link', 'online', '', 1),
(5, 'Robotics and Manufacturing Expo', 'Showcase of automation loops, mechanical designs, and embedded firmware designs.', '2026-12-05 09:30:00', 'Main Exhibition Arena', 'in-person', '', 1);

-- Announcements Seeding
INSERT INTO announcements (id, title, content, audience, created_by) VALUES
(1, 'System Maintenance Notice', 'The AlumniNet server will undergo standard database indexes optimization tonight.', 'all', 1),
(2, 'Mentorship Program Active', 'Verify your profile statistics to match with experienced alumni mentors today.', 'students', 1),
(3, 'Call for Job Referral Posts', 'Alumni members are requested to post open tech and hardware career roles.', 'alumni', 1);

-- Notifications Seeding
INSERT INTO notifications (user_id, title, message) VALUES
(2, 'Welcome to AlumniNet', 'Your student member account is fully configured and ready!'),
(3, 'Registration Approved', 'Your alumnus account has been approved by the admin team.'),
(5, 'Event RSVP Confirmed', 'You have registered successfully for the Tech Reunion & Networking Evening.');

-- Conversations & Messages Seeding
INSERT INTO conversations (id, sender_id, receiver_id) VALUES
(1, 2, 3); -- Student (2) to Alumni (3)

INSERT INTO messages (conversation_id, sender_id, message) VALUES
(1, 2, 'Hi Jane, I saw your Google profile and would love to ask for a referral!'),
(1, 3, 'Hi! Sure, please share your resume path and I will look into it.');

-- Skills Seeding
INSERT INTO skills (id, name) VALUES
(1, 'PHP'),
(2, 'MySQL'),
(3, 'JavaScript'),
(4, 'GSAP'),
(5, 'CSS3'),
(6, 'Embedded Systems'),
(7, 'Robotics'),
(8, 'Kubernetes');

-- 29. Feedback Table
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rating INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 30. Achievements Table
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    date_achieved DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 31. Add Remember Me Token column to users
ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL;

-- 32. Requirements Table
CREATE TABLE IF NOT EXISTS requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type ENUM('internship', 'placement') NOT NULL,
    min_cgpa DECIMAL(3,2) DEFAULT 0.00,
    allowed_departments VARCHAR(255) DEFAULT '',
    skills_required TEXT DEFAULT NULL,
    deadline DATETIME DEFAULT NULL,
    required_documents TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 33. Job Requirements Mapping Table
CREATE TABLE IF NOT EXISTS job_requirements (
    job_id INT NOT NULL,
    requirement_id INT NOT NULL,
    PRIMARY KEY (job_id, requirement_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (requirement_id) REFERENCES requirements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 34. Event Requirements Mapping Table
CREATE TABLE IF NOT EXISTS event_requirements (
    event_id INT NOT NULL,
    requirement_id INT NOT NULL,
    PRIMARY KEY (event_id, requirement_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (requirement_id) REFERENCES requirements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 35. User Certificates Table
CREATE TABLE IF NOT EXISTS user_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    issuer VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 36. AI Chats History Table
CREATE TABLE IF NOT EXISTS ai_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    query TEXT NOT NULL,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 37. Bookmarked Jobs Table
CREATE TABLE IF NOT EXISTS bookmarked_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 38. Saved Events Table
CREATE TABLE IF NOT EXISTS saved_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 39. User Certificates Table
CREATE TABLE IF NOT EXISTS user_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    issuer VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


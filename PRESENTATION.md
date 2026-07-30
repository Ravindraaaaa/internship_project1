# Alumni Record Management System (ARMS)
## Software Requirements Specification & Project Presentation Guide

---

## 📌 Executive Presentation Overview
- **Project Title:** Alumni Record Management System (ARMS)
- **Course:** College Mini-Project (Computer Science & Engineering)
- **Version:** 1.0 (Production-Ready)
- **Environment:** PHP 8.2 + MySQL 8.0 (XAMPP Server)
- **Presentation Assets Created:**
  1. `Alumni_Record_Management_System_Presentation.pptx` (Native PowerPoint File)
  2. `presentation.html` (Interactive Web Presentation Slide Deck - Available live at `http://localhost:8080/Internship%20Project/internship_project1/presentation.html`)

---

## 📽️ Slide-by-Slide Presentation Structure & Speaker Script

### Slide 1: Title & Overview
- **Title:** Alumni Record Management System (ARMS)
- **Subtitle:** Modern Web-Based Institutional Networking & Administration Platform
- **Key Points:**
  - Designed for College Mini-Project (Computer Science & Engineering).
  - Bridges the communication gap between graduating students, former alumni, and college administration.
- **Speaker Script:** "Good morning/afternoon everyone. Today we present our mini-project, the Alumni Record Management System, or ARMS. ARMS is a web platform that replaces manual paper and spreadsheet tracking with an automated, secure institutional network."

---

### Slide 2: Executive Summary & Problem Statement
- **Left Column (Legacy Challenges):**
  - Manual paper/spreadsheet tracking leads to stale and duplicate data.
  - Graduating students lose touch with alumni mentors.
  - Institutional administration struggles to aggregate placement statistics for NBA/NAAC accreditations.
  - Spreadsheet lists lack access security and 2FA protection.
- **Right Column (The ARMS Solution):**
  - Centralized web portal with self-registration and admin approval verification.
  - Multi-parameter dynamic peer search engine.
  - Built-in job board, mentorship connections, and 1-on-1 direct messaging.
  - Automated PDF/CSV reports generator for placement auditing.

---

### Slide 3: Core Objectives & Project Scope
- **1. Profile & Portfolio:** Alumni & students manage dynamic portfolios (CGPA, skills, work history, projects, certifications).
- **2. Peer Search Engine:** Fast filtering by batch year, department, employer organization, and location.
- **3. Jobs & Mentorship Hub:** Alumni post job referrals; students apply directly and request mentorship.
- **4. Admin Governance:** Admin verification workflow, security audit logs, event broadcasting, and placement analytics.

---

### Slide 4: User Roles & Permissions Matrix
- **System Administrator:** Root access, account approvals/rejections, campus event broadcasts, audit logs, placement stats.
- **Alumni:** Update occupational status/location, post job openings, accept mentorship requests, send direct messages.
- **Enrolled Students:** Profile showcase, search alumni directory, apply to jobs/internships, request 1-on-1 mentoring.

---

### Slide 5: 3-Tier System Architecture
- **Presentation Tier (Client):** HTML5, Vanilla CSS, JavaScript — Glassmorphic responsive web dashboards.
- **Application Logic Tier (Server):** PHP 8.2 Engine — Handles session auth, 2FA/OTP verification, password hashing, and business logic.
- **Data Storage Tier (Database):** MySQL 8.0 RDBMS — Normalized (3NF) relational tables ensuring data integrity.

---

### Slide 6: Data Flow Diagrams (DFD Level 0 & Level 1)
- **DFD Level 0 (Context Level):**
  - External Entities (Alumni, Students, System Admin) interacting with ARMS process.
- **DFD Level 1 (Process Decomposition):**
  - `1.0 Authentication` &rarr; Validates credentials & OTP against `Users` table.
  - `2.0 Profile Management` &rarr; Updates personal/career fields in `Alumni_Profile`.
  - `3.0 Search Engine` &rarr; Queries indexed database columns.
  - `4.0 Event & Job Portal` &rarr; Manages campus event announcements and RSVPs.

---

### Slide 7: Database Design & Entity Relationship (ERD)
- **Primary Entities:**
  - `USERS`: `user_id` (PK), `email`, `password_hash`, `role`, `status`.
  - `ALUMNI_PROFILE`: `profile_id` (PK), `user_id` (FK), `batch_year`, `department`, `company`, `location`, `cgpa`.
  - `EVENTS & JOBS`: `event_id` / `job_id` (PK), `created_by` (FK), `title`, `description`, `date`.
  - `MESSAGES & LOGS`: `msg_id` (PK), `sender_id` (FK), `receiver_id` (FK), `message_text`, `audit_logs`.
- **Normalization:** Conforms to Third Normal Form (3NF) to eliminate data redundancy.

---

### Slide 8: Security, 2FA & Authentication Framework
- **Password Cryptography:** Encrypted using strong `bcrypt` / `Argon2` hashing.
- **Two-Factor Authentication (2FA):** 6-digit email/SMS OTP verification during login and password resets.
- **Admin Approval Gate:** New registrations placed in "Pending Approval" state until verified by college administration.
- **Threat Mitigation:** Server-side input sanitization protecting against SQL Injection & Cross-Site Scripting (XSS).

---

### Slide 9: Alumni Directory & Smart Search Engine
- **Multi-Parameter Filtering (FR-3):**
  - Search by Graduation Batch Year (e.g. 2020, 2022, 2024).
  - Search by Department / Branch (CSE, IT, ECE, ME, CE).
  - Search by Employer Organization (Google, TCS, Infosys, Microsoft).
  - Search by Location & Technical Skills (Python, React, AWS).
- **Direct Actions:** View detailed portfolios, download resumes, send direct messages, request mentorship.

---

### Slide 10: Portfolios, Job Board & Mentorship Bridge
- **Dynamic Portfolios:** Display student/alumni achievements, CGPA, projects, work experience, certifications, and GitHub/LinkedIn links.
- **Job & Internship Board:** Alumni post job opportunities; students apply directly with profile data.
- **Mentorship Connection:** Peer-to-peer mentoring requests with status tracking.

---

### Slide 11: Real-Time Communication & Campus Events
- **Direct & Group Messaging:** 1-on-1 private messaging, departmental discussion channels, and unread notification alerts.
- **Event Broadcasting Timeline:** Post notice bulletins for campus reunions, industry talks, and webinars with interactive RSVP buttons.

---

### Slide 12: Admin Governance & Reports Generator
- **Interactive Dashboard:** Live user registration counts, active sessions, and security event monitors.
- **Report Generator (FR-5):** Computes placement distributions, company distributions, and geographical locations.
- **One-Click Export:** Download placement metrics in PDF and CSV formats for college accreditation.

---

### Slide 13: Non-Functional Requirements & Infrastructure
- **Performance:** Response time < 2 seconds under 50 concurrent users; 99% uptime target.
- **Minimum System Requirements:** Dual-Core 2.0 GHz CPU, 4 GB RAM, 20 GB SSD storage.
- **Recommended Deployment Stack:** Quad-Core CPU, 8-16 GB RAM, 50 GB NVMe SSD, XAMPP / Linux Ubuntu Server 22.04 LTS.

---

### Slide 14: Verification, Testing & QA
- **TC-01 (Account Creation Gate):** Valid student ID input &rarr; Result: Account created with status "Pending Approval" (PASSED).
- **TC-02 (SQL Injection Test):** `' OR '1'='1` in login input &rarr; Result: Access denied; input sanitized (PASSED).
- **TC-03 (2FA OTP Verification):** Valid 6-digit OTP vs expired code &rarr; Result: Validates correct OTP (PASSED).
- **TC-04 (Multi-Filter Search):** Query Dept='CSE', Company='Google' &rarr; Result: Loads matching cards in < 1s (PASSED).

---

### Slide 15: Future Roadmap & Conclusion
- **Future Roadmap:**
  - Automated LinkedIn API sync to update employment records.
  - Payment gateway module (Stripe/PayPal) for alumni association donations.
  - AI-assisted mentorship skill matching.
- **Conclusion:** ARMS modernizes college alumni management into a secure, responsive, 3-tier web solution ready for institutional deployment.

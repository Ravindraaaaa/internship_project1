const pptxgen = require("pptxgenjs");
const path = require("path");

const pptx = new pptxgen();

// Set Presentation Properties
pptx.layout = "LAYOUT_16x9";
pptx.title = "Alumni Record Management System (ARMS)";
pptx.subject = "Software Requirements Specification & System Presentation";
pptx.author = "Project Team Group B";
pptx.company = "Computer Science & Engineering";

// Design Palette
const C_DARK_BG = "0F172A";    // Slate 900
const C_BLUE = "2563EB";       // Blue 600
const C_TEAL = "0D9488";       // Teal 600
const C_PURPLE = "7C3AED";     // Purple 600
const C_TEXT_DARK = "1E293B";  // Slate 800
const C_TEXT_MUTED = "64748B"; // Slate 500
const C_BG_LIGHT = "F8FAFC";   // Slate 50
const C_CARD_BG = "FFFFFF";    // White
const C_CARD_BORDER = "E2E8F0";

// Helper for adding master headers to content slides
function addHeader(slide, titleText, categoryText = "ALUMNI RECORD MANAGEMENT SYSTEM (ARMS)") {
  // Category / Kicker
  slide.addText(categoryText.toUpperCase(), {
    x: 0.8,
    y: 0.4,
    w: 11.5,
    h: 0.3,
    fontSize: 10,
    bold: true,
    color: C_BLUE,
    fontFace: "Arial"
  });

  // Slide Title
  slide.addText(titleText, {
    x: 0.8,
    y: 0.7,
    w: 11.5,
    h: 0.6,
    fontSize: 22,
    bold: true,
    color: C_TEXT_DARK,
    fontFace: "Arial"
  });

  // Top Accent Line
  slide.addShape(pptx.shapes.LINE, {
    x: 0.8,
    y: 1.35,
    w: 11.7,
    h: 0,
    line: { color: C_BLUE, width: 2 }
  });
}

// ----------------------------------------------------
// SLIDE 1: Title Slide (Dark Theme)
// ----------------------------------------------------
let s1 = pptx.addSlide();
s1.background = { color: C_DARK_BG };

// Geometric Accent Box
s1.addShape(pptx.shapes.RECTANGLE, {
  x: 0.8, y: 0.8, w: 1.2, h: 0.1, fill: { color: C_BLUE }
});

s1.addText("PROJECT PRESENTATION & SRS OVERVIEW", {
  x: 0.8, y: 1.2, w: 11.0, h: 0.4,
  fontSize: 12, bold: true, color: "60A5FA", fontFace: "Arial", tracking: 2
});

s1.addText("Alumni Record Management System", {
  x: 0.8, y: 1.7, w: 11.5, h: 1.1,
  fontSize: 36, bold: true, color: "FFFFFF", fontFace: "Arial"
});

s1.addText("(ARMS) — Modern Institutional Networking Platform", {
  x: 0.8, y: 2.7, w: 11.5, h: 0.5,
  fontSize: 20, color: "94A3B8", fontFace: "Arial"
});

// Card container for metadata
s1.addShape(pptx.shapes.RECTANGLE, {
  x: 0.8, y: 4.2, w: 11.7, h: 2.2,
  fill: { color: "1E293B" }, line: { color: "334155", width: 1 }
});

s1.addText([
  { text: "Course: ", options: { bold: true, color: "60A5FA" } },
  { text: "College Mini-Project (Computer Science & Engineering)\n\n", options: { color: "E2E8F0" } },
  { text: "Version: ", options: { bold: true, color: "60A5FA" } },
  { text: "1.0 (Production Ready)  |  ", options: { color: "E2E8F0" } },
  { text: "Date: ", options: { bold: true, color: "60A5FA" } },
  { text: "Academic Year 2026\n\n", options: { color: "E2E8F0" } },
  { text: "Prepared By: ", options: { bold: true, color: "60A5FA" } },
  { text: "Project Team Group B", options: { color: "FFFFFF", bold: true } }
], {
  x: 1.2, y: 4.4, w: 11.0, h: 1.8,
  fontSize: 14, fontFace: "Arial"
});


// ----------------------------------------------------
// SLIDE 2: Executive Summary & Problem Statement
// ----------------------------------------------------
let s2 = pptx.addSlide();
s2.background = { color: C_BG_LIGHT };
addHeader(s2, "Executive Summary & Problem Statement");

// Left Box - The Legacy Challenge
s2.addShape(pptx.shapes.RECTANGLE, {
  x: 0.8, y: 1.6, w: 5.6, h: 5.0,
  fill: { color: C_CARD_BG }, line: { color: "EF4444", width: 1.5 }
});

s2.addText("⚠️ The Legacy Challenge", {
  x: 1.1, y: 1.9, w: 5.0, h: 0.4,
  fontSize: 16, bold: true, color: "DC2626", fontFace: "Arial"
});

s2.addText([
  { text: "• Manual Paperwork & Spreadsheets: ", options: { bold: true } },
  { text: "Tracking thousands of graduating students using static files leads to missing data.\n\n" },
  { text: "• Lost Alumni Connections: ", options: { bold: true } },
  { text: "No direct communication bridge between current students and experienced alumni.\n\n" },
  { text: "• No Placement Auditing: ", options: { bold: true } },
  { text: "Difficulty generating metrics for NBA/NAAC institutional accreditations.\n\n" },
  { text: "• Security & Privacy Risks: ", options: { bold: true } },
  { text: "Unprotected contact sheets vulnerable to data leaks." }
], {
  x: 1.1, y: 2.5, w: 5.0, h: 3.8,
  fontSize: 12, color: C_TEXT_DARK, fontFace: "Arial"
});

// Right Box - The ARMS Solution
s2.addShape(pptx.shapes.RECTANGLE, {
  x: 6.9, y: 1.6, w: 5.6, h: 5.0,
  fill: { color: C_CARD_BG }, line: { color: C_TEAL, width: 1.5 }
});

s2.addText("🚀 The ARMS Solution", {
  x: 7.2, y: 1.9, w: 5.0, h: 0.4,
  fontSize: 16, bold: true, color: C_TEAL, fontFace: "Arial"
});

s2.addText([
  { text: "• Centralized Web Portal: ", options: { bold: true } },
  { text: "Self-service onboarding with admin verification flow.\n\n" },
  { text: "• Dynamic Peer Directory: ", options: { bold: true } },
  { text: "Instant search by batch, branch, employer company, or location.\n\n" },
  { text: "• Real-Time Mentorship & Messaging: ", options: { bold: true } },
  { text: "Built-in 1-on-1 chat, job board, and mentorship requests.\n\n" },
  { text: "• Automated Analytics & Reports: ", options: { bold: true } },
  { text: "Instant PDF/CSV export for college placement auditing." }
], {
  x: 7.2, y: 2.5, w: 5.0, h: 3.8,
  fontSize: 12, color: C_TEXT_DARK, fontFace: "Arial"
});


// ----------------------------------------------------
// SLIDE 3: Key Objectives & Scope
// ----------------------------------------------------
let s3 = pptx.addSlide();
s3.background = { color: C_BG_LIGHT };
addHeader(s3, "Core Objectives & Project Scope");

const objBoxes = [
  { title: "1. Profile & Portfolio", desc: "Enable alumni to manage professional profiles, CGPA, work history, projects, and skills.", color: C_BLUE },
  { title: "2. Peer Search Engine", desc: "Multi-parameter filter to discover alumni by graduation year, department, company, and location.", color: C_TEAL },
  { title: "3. Jobs & Mentorship", desc: "Direct job/internship postings by alumni and structured mentorship connection requests.", color: C_PURPLE },
  { title: "4. Admin Governance", desc: "Verification workflow for new accounts, security audit logs, event broadcasting, and statistics.", color: "D97706" }
];

objBoxes.forEach((item, index) => {
  let col = index % 2;
  let row = Math.floor(index / 2);
  let xPos = 0.8 + col * 5.9;
  let yPos = 1.6 + row * 2.6;

  s3.addShape(pptx.shapes.RECTANGLE, {
    x: xPos, y: yPos, w: 5.6, h: 2.3,
    fill: { color: C_CARD_BG }, line: { color: C_CARD_BORDER, width: 1 }
  });

  // Top Accent Bar
  s3.addShape(pptx.shapes.RECTANGLE, {
    x: xPos, y: yPos, w: 5.6, h: 0.1, fill: { color: item.color }
  });

  s3.addText(item.title, {
    x: xPos + 0.3, y: yPos + 0.3, w: 5.0, h: 0.4,
    fontSize: 16, bold: true, color: C_TEXT_DARK, fontFace: "Arial"
  });

  s3.addText(item.desc, {
    x: xPos + 0.3, y: yPos + 0.8, w: 5.0, h: 1.2,
    fontSize: 12, color: C_TEXT_MUTED, fontFace: "Arial"
  });
});


// ----------------------------------------------------
// SLIDE 4: User Roles & Access Control Matrix
// ----------------------------------------------------
let s4 = pptx.addSlide();
s4.background = { color: C_BG_LIGHT };
addHeader(s4, "User Roles & Permissions Matrix");

const tableRows = [
  [
    { text: "User Class", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } },
    { text: "Primary Role & Focus", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } },
    { text: "System Privileges & Permissions", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } }
  ],
  [
    { text: "System Administrator", options: { bold: true, color: C_TEXT_DARK } },
    { text: "Root access, global site management, audit compliance.", options: { color: C_TEXT_MUTED } },
    { text: "• Approve / Reject pending registrations\n• Broadcast campus events & announcements\n• Generate placement stats & audit logs\n• Enterprise database control", options: { color: C_TEXT_DARK } }
  ],
  [
    { text: "Alumni", options: { bold: true, color: C_TEXT_DARK } },
    { text: "Graduated former students maintaining professional records.", options: { color: C_TEXT_MUTED } },
    { text: "• Update employment, location & portfolio\n• Search peer directory & send messages\n• Post job & internship openings\n• Accept mentorship requests", options: { color: C_TEXT_DARK } }
  ],
  [
    { text: "Enrolled Students", options: { bold: true, color: C_TEXT_DARK } },
    { text: "Current students seeking guidance & career opportunities.", options: { color: C_TEXT_MUTED } },
    { text: "• Create student profile & showcase skills\n• Search alumni profiles for networking\n• Apply to posted jobs/internships\n• Request 1-on-1 mentorship", options: { color: C_TEXT_DARK } }
  ]
];

s4.addTable(tableRows, {
  x: 0.8, y: 1.6, w: 11.7, colW: [2.5, 4.0, 5.2],
  border: { pt: 1, color: "CBD5E1" },
  fontFace: "Arial", fontSize: 11
});


// ----------------------------------------------------
// SLIDE 5: 3-Tier System Architecture
// ----------------------------------------------------
let s5 = pptx.addSlide();
s5.background = { color: C_BG_LIGHT };
addHeader(s5, "3-Tier System Architecture");

const archTiers = [
  { tier: "PRESENTATION TIER", badge: "Client Side", desc: "HTML5 | Vanilla CSS | JavaScript | Responsive UI", details: "Renders modern glassmorphic web dashboards, interactive forms, responsive search tables, and client-side form validations.", color: C_BLUE },
  { tier: "APPLICATION LOGIC TIER", badge: "Web Server", desc: "PHP 8.2 Engine | Session Auth | Security Middleware", details: "Executes business logic, manages 2FA / OTP verification, processes dynamic SQL queries, handles password hashing, and routes API calls.", color: C_TEAL },
  { tier: "DATA STORAGE TIER", badge: "Database", desc: "MySQL 8.0 RDBMS | 3NF Normalized Layout", details: "Persists core application data: Users, Alumni Profiles, Events, Jobs, Mentorship, Messages, and System Audit Logs.", color: C_PURPLE }
];

archTiers.forEach((item, index) => {
  let yPos = 1.6 + index * 1.7;

  s5.addShape(pptx.shapes.RECTANGLE, {
    x: 0.8, y: yPos, w: 11.7, h: 1.4,
    fill: { color: C_CARD_BG }, line: { color: item.color, width: 1.5 }
  });

  // Badge
  s5.addShape(pptx.shapes.RECTANGLE, {
    x: 1.1, y: yPos + 0.2, w: 2.6, h: 0.4, fill: { color: item.color }
  });
  s5.addText(item.tier, {
    x: 1.1, y: yPos + 0.2, w: 2.6, h: 0.4,
    fontSize: 10, bold: true, color: "FFFFFF", align: "center", fontFace: "Arial"
  });

  s5.addText(item.desc, {
    x: 3.9, y: yPos + 0.2, w: 8.3, h: 0.4,
    fontSize: 14, bold: true, color: C_TEXT_DARK, fontFace: "Arial"
  });

  s5.addText(item.details, {
    x: 1.1, y: yPos + 0.7, w: 11.0, h: 0.6,
    fontSize: 11, color: C_TEXT_MUTED, fontFace: "Arial"
  });
});


// ----------------------------------------------------
// SLIDE 6: Data Flow Diagrams (DFD Level 0 & Level 1)
// ----------------------------------------------------
let s6 = pptx.addSlide();
s6.background = { color: C_BG_LIGHT };
addHeader(s6, "Data Flow Diagrams (DFD Level 0 & Level 1)");

// Box 1: DFD Level 0
s6.addShape(pptx.shapes.RECTANGLE, {
  x: 0.8, y: 1.6, w: 5.6, h: 5.0,
  fill: { color: C_CARD_BG }, line: { color: C_BLUE, width: 1.5 }
});
s6.addText("DFD Level 0 — Context Diagram", {
  x: 1.1, y: 1.9, w: 5.0, h: 0.4,
  fontSize: 15, bold: true, color: C_BLUE, fontFace: "Arial"
});
s6.addText([
  { text: "External Entities:\n", options: { bold: true } },
  { text: "• Alumni: ", options: { bold: true } }, { text: "Sends registration info & profile updates -> Receives directory results & event notices.\n\n" },
  { text: "• Student: ", options: { bold: true } }, { text: "Sends search queries & mentorship requests -> Receives matching profile cards & job listings.\n\n" },
  { text: "• System Admin: ", options: { bold: true } }, { text: "Sends approval decisions & event updates -> Receives audit logs & statistical reports." }
], {
  x: 1.1, y: 2.5, w: 5.0, h: 3.8,
  fontSize: 11, color: C_TEXT_DARK, fontFace: "Arial"
});

// Box 2: DFD Level 1
s6.addShape(pptx.shapes.RECTANGLE, {
  x: 6.9, y: 1.6, w: 5.6, h: 5.0,
  fill: { color: C_CARD_BG }, line: { color: C_TEAL, width: 1.5 }
});
s6.addText("DFD Level 1 — Process Decomposition", {
  x: 7.2, y: 1.9, w: 5.0, h: 0.4,
  fontSize: 15, bold: true, color: C_TEAL, fontFace: "Arial"
});
s6.addText([
  { text: "Core Sub-Processes:\n", options: { bold: true } },
  { text: "1.0 Authentication: ", options: { bold: true } }, { text: "Validates credentials & 2FA OTP against D1: Users table.\n\n" },
  { text: "2.0 Profile Management: ", options: { bold: true } }, { text: "Updates personal & career fields in D2: Alumni Info table.\n\n" },
  { text: "3.0 Search Engine: ", options: { bold: true } }, { text: "Queries indexed columns in D2: Alumni Info.\n\n" },
  { text: "4.0 Event & Job Portal: ", options: { bold: true } }, { text: "Reads/Writes D3: Events & D4: Jobs tables." }
], {
  x: 7.2, y: 2.5, w: 5.0, h: 3.8,
  fontSize: 11, color: C_TEXT_DARK, fontFace: "Arial"
});


// ----------------------------------------------------
// SLIDE 7: Database Design & Entity Relationship (ERD)
// ----------------------------------------------------
let s7 = pptx.addSlide();
s7.background = { color: C_BG_LIGHT };
addHeader(s7, "Database Schema & Entity Relationships (3NF)");

const erdTables = [
  { name: "USERS (Primary Table)", fields: "PK user_id | email | password_hash | role | status | created_at", color: C_BLUE },
  { name: "ALUMNI_PROFILE", fields: "PK profile_id | FK user_id | batch_year | department | company | location | phone | cgpa", color: C_TEAL },
  { name: "EVENTS & JOBS", fields: "PK event_id/job_id | FK created_by | title | description | company | event_date", color: C_PURPLE },
  { name: "MENTORSHIP & MESSAGES", fields: "PK msg_id | FK sender_id | FK receiver_id | message_text | status | timestamp", color: "D97706" }
];

erdTables.forEach((t, index) => {
  let col = index % 2;
  let row = Math.floor(index / 2);
  let xPos = 0.8 + col * 5.9;
  let yPos = 1.6 + row * 2.6;

  s7.addShape(pptx.shapes.RECTANGLE, {
    x: xPos, y: yPos, w: 5.6, h: 2.3,
    fill: { color: C_CARD_BG }, line: { color: t.color, width: 1.5 }
  });

  s7.addText(t.name, {
    x: xPos + 0.3, y: yPos + 0.3, w: 5.0, h: 0.4,
    fontSize: 14, bold: true, color: t.color, fontFace: "Arial"
  });

  s7.addText("Fields & Constraints:\n" + t.fields.split(" | ").join("\n• "), {
    x: xPos + 0.3, y: yPos + 0.8, w: 5.0, h: 1.3,
    fontSize: 11, color: C_TEXT_DARK, fontFace: "Arial"
  });
});


// ----------------------------------------------------
// SLIDE 8: Authentication, 2FA & Security Architecture
// ----------------------------------------------------
let s8 = pptx.addSlide();
s8.background = { color: C_BG_LIGHT };
addHeader(s8, "Security, 2FA & Authentication Framework");

const secFeatures = [
  { title: "🔐 Password Cryptography", desc: "All user passwords are encrypted using strong bcrypt/Argon2 hashing before storing in MySQL. Plaintext passwords are never saved.", color: C_BLUE },
  { title: "📲 Two-Factor Auth (OTP)", desc: "Integrated OTP verification via email/SMS for secondary user validation during login and password reset flows.", color: C_TEAL },
  { title: "🛡️ Admin Approval Gate", desc: "New alumni signups remain in 'Pending Approval' state until institutional admins verify student ID credentials.", color: C_PURPLE },
  { title: "📜 Audit Logging & Sanitization", desc: "Server-side string sanitization protects against SQL Injection & XSS. System actions recorded in security audit logs.", color: "DC2626" }
];

secFeatures.forEach((item, index) => {
  let col = index % 2;
  let row = Math.floor(index / 2);
  let xPos = 0.8 + col * 5.9;
  let yPos = 1.6 + row * 2.6;

  s8.addShape(pptx.shapes.RECTANGLE, {
    x: xPos, y: yPos, w: 5.6, h: 2.3,
    fill: { color: C_CARD_BG }, line: { color: C_CARD_BORDER, width: 1 }
  });

  s8.addShape(pptx.shapes.RECTANGLE, {
    x: xPos, y: yPos, w: 5.6, h: 0.08, fill: { color: item.color }
  });

  s8.addText(item.title, {
    x: xPos + 0.3, y: yPos + 0.3, w: 5.0, h: 0.4,
    fontSize: 15, bold: true, color: C_TEXT_DARK, fontFace: "Arial"
  });

  s8.addText(item.desc, {
    x: xPos + 0.3, y: yPos + 0.8, w: 5.0, h: 1.3,
    fontSize: 11, color: C_TEXT_MUTED, fontFace: "Arial"
  });
});


// ----------------------------------------------------
// SLIDE 9: Alumni Directory & Smart Filter Search
// ----------------------------------------------------
let s9 = pptx.addSlide();
s9.background = { color: C_BG_LIGHT };
addHeader(s9, "Alumni Directory & Smart Search Engine");

s9.addShape(pptx.shapes.RECTANGLE, {
  x: 0.8, y: 1.6, w: 11.7, h: 5.0,
  fill: { color: C_CARD_BG }, line: { color: C_BLUE, width: 1.5 }
});

s9.addText("🔍 Multi-Parameter Search Engine (FR-3)", {
  x: 1.2, y: 1.9, w: 10.5, h: 0.4,
  fontSize: 16, bold: true, color: C_BLUE, fontFace: "Arial"
});

s9.addText([
  { text: "Search Filters & Criteria:\n", options: { bold: true, fontSize: 13 } },
  { text: "• Graduation Batch Year: ", options: { bold: true } }, { text: "Filter alumni by exact graduating class (e.g. 2020, 2022, 2024).\n" },
  { text: "• Department / Branch: ", options: { bold: true } }, { text: "Filter by Computer Science, IT, Electronics, Mechanical, Civil.\n" },
  { text: "• Employer Organization: ", options: { bold: true } }, { text: "Search alumni working at Google, TCS, Infosys, Microsoft, Amazon.\n" },
  { text: "• Geographic Location: ", options: { bold: true } }, { text: "Filter by city or country (e.g. Bangalore, Pune, New York, London).\n" },
  { text: "• Technical Skills: ", options: { bold: true } }, { text: "Query alumni with specific skills (PHP, Python, React, AWS, Data Science).\n\n" },
  { text: "Instant Actions: ", options: { bold: true, fontSize: 13 } },
  { text: "View complete professional profile, download resume, send direct message, or request mentorship connection." }
], {
  x: 1.2, y: 2.4, w: 10.8, h: 3.9,
  fontSize: 11, color: C_TEXT_DARK, fontFace: "Arial"
});


// ----------------------------------------------------
// SLIDE 10: Portfolios, Job Board & Mentorship Bridge
// ----------------------------------------------------
let s10 = pptx.addSlide();
s10.background = { color: C_BG_LIGHT };
addHeader(s10, "Portfolios, Job Board & Mentorship Bridge");

const pBoxes = [
  { title: "📄 Dynamic Portfolios", desc: "Showcases CGPA, technical skills, completed projects, certifications, work history, and social links (GitHub/LinkedIn).", color: C_BLUE },
  { title: "💼 Jobs & Internships", desc: "Alumni post exclusive job openings and internship referrals. Students submit applications directly through the portal.", color: C_TEAL },
  { title: "🤝 Mentorship Program", desc: "Students request 1-on-1 career guidance from alumni in their target industry. Alumni manage connection requests.", color: C_PURPLE }
];

pBoxes.forEach((item, index) => {
  let xPos = 0.8 + index * 3.95;

  s10.addShape(pptx.shapes.RECTANGLE, {
    x: xPos, y: 1.6, w: 3.7, h: 5.0,
    fill: { color: C_CARD_BG }, line: { color: item.color, width: 1.5 }
  });

  s10.addText(item.title, {
    x: xPos + 0.3, y: 1.9, w: 3.1, h: 0.5,
    fontSize: 15, bold: true, color: item.color, fontFace: "Arial"
  });

  s10.addText(item.desc, {
    x: xPos + 0.3, y: 2.6, w: 3.1, h: 3.7,
    fontSize: 12, color: C_TEXT_DARK, fontFace: "Arial"
  });
});


// ----------------------------------------------------
// SLIDE 11: Real-Time Chat & Event Broadcasting
// ----------------------------------------------------
let s11 = pptx.addSlide();
s11.background = { color: C_BG_LIGHT };
addHeader(s11, "Real-Time Communication & Campus Events");

s11.addShape(pptx.shapes.RECTANGLE, {
  x: 0.8, y: 1.6, w: 5.6, h: 5.0,
  fill: { color: C_CARD_BG }, line: { color: C_BLUE, width: 1.5 }
});
s11.addText("💬 Direct & Group Messaging", {
  x: 1.1, y: 1.9, w: 5.0, h: 0.4,
  fontSize: 16, bold: true, color: C_BLUE, fontFace: "Arial"
});
s11.addText([
  { text: "• Direct 1-on-1 Chat: ", options: { bold: true } },
  { text: "Instant private messaging between students and alumni.\n\n" },
  { text: "• Group Channels: ", options: { bold: true } },
  { text: "Departmental and batch-wise discussion threads.\n\n" },
  { text: "• Unread Indicators & Notifications: ", options: { bold: true } },
  { text: "In-app alerts for incoming messages and connection updates." }
], {
  x: 1.1, y: 2.5, w: 5.0, h: 3.8,
  fontSize: 12, color: C_TEXT_DARK, fontFace: "Arial"
});

s11.addShape(pptx.shapes.RECTANGLE, {
  x: 6.9, y: 1.6, w: 5.6, h: 5.0,
  fill: { color: C_CARD_BG }, line: { color: C_TEAL, width: 1.5 }
});
s11.addText("📅 Event Broadcasting Bulletin", {
  x: 7.2, y: 1.9, w: 5.0, h: 0.4,
  fontSize: 16, bold: true, color: C_TEAL, fontFace: "Arial"
});
s11.addText([
  { text: "• Campus Reunions & Industry Talks: ", options: { bold: true } },
  { text: "Admins and alumni post upcoming event details.\n\n" },
  { text: "• Interactive RSVP & Event Timeline: ", options: { bold: true } },
  { text: "Users register for events and view schedule updates.\n\n" },
  { text: "• Announcement Analytics: ", options: { bold: true } },
  { text: "Track views and engagement metrics across posted events." }
], {
  x: 7.2, y: 2.5, w: 5.0, h: 3.8,
  fontSize: 12, color: C_TEXT_DARK, fontFace: "Arial"
});


// ----------------------------------------------------
// SLIDE 12: Admin Dashboard & Reports Generator
// ----------------------------------------------------
let s12 = pptx.addSlide();
s12.background = { color: C_BG_LIGHT };
addHeader(s12, "Admin Governance & Institutional Reports");

s12.addShape(pptx.shapes.RECTANGLE, {
  x: 0.8, y: 1.6, w: 11.7, h: 5.0,
  fill: { color: C_CARD_BG }, line: { color: C_PURPLE, width: 1.5 }
});

s12.addText("📊 Comprehensive Admin Dashboard & Analytics Engine", {
  x: 1.2, y: 1.9, w: 10.5, h: 0.4,
  fontSize: 16, bold: true, color: C_PURPLE, fontFace: "Arial"
});

s12.addText([
  { text: "Key Administrative Capabilities:\n", options: { bold: true, fontSize: 13 } },
  { text: "1. Account Approval Queue: ", options: { bold: true } }, { text: "Verify incoming registration requests against master student database.\n" },
  { text: "2. Enterprise Management: ", options: { bold: true } }, { text: "Control user roles, update profiles, and manage system parameters.\n" },
  { text: "3. System Audit Logs: ", options: { bold: true } }, { text: "Monitor security events, login attempts, and data modifications.\n" },
  { text: "4. Institutional Report Generator (FR-5): ", options: { bold: true } },
  { text: "Generate formatted placement distributions, employer company stats, and location demographics.\n" },
  { text: "5. Export Formats: ", options: { bold: true } }, { text: "One-click export to PDF, CSV, and Excel for accreditation documentation." }
], {
  x: 1.2, y: 2.4, w: 10.8, h: 3.9,
  fontSize: 11, color: C_TEXT_DARK, fontFace: "Arial"
});


// ----------------------------------------------------
// SLIDE 13: Non-Functional Requirements & Infrastructure
// ----------------------------------------------------
let s13 = pptx.addSlide();
s13.background = { color: C_BG_LIGHT };
addHeader(s13, "Non-Functional Requirements & Deployment");

const nfTableRows = [
  [
    { text: "Requirement Category", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } },
    { text: "System Specification & Metrics", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } }
  ],
  [
    { text: "Performance Requirements", options: { bold: true } },
    { text: "Search queries process in < 2 seconds under 50 concurrent users. 99% target uptime during operational hours.", options: { color: C_TEXT_DARK } }
  ],
  [
    { text: "Security Requirements", options: { bold: true } },
    { text: "bcrypt password hashing, server-side string sanitization (protecting against XSS & SQLi), session timeout protection.", options: { color: C_TEXT_DARK } }
  ],
  [
    { text: "Hardware Minimum Specs", options: { bold: true } },
    { text: "Dual-Core 2.0 GHz CPU | 4 GB RAM | 20 GB SSD Storage Space | Standard Network Connection.", options: { color: C_TEXT_DARK } }
  ],
  [
    { text: "Recommended Deployment Stack", options: { bold: true } },
    { text: "Quad-Core CPU | 8/16 GB RAM | 50 GB NVMe SSD | XAMPP / Ubuntu Server 22.04 LTS | MySQL 8.0+.", options: { color: C_TEXT_DARK } }
  ]
];

s13.addTable(nfTableRows, {
  x: 0.8, y: 1.6, w: 11.7, colW: [3.5, 8.2],
  border: { pt: 1, color: "CBD5E1" },
  fontFace: "Arial", fontSize: 11
});


// ----------------------------------------------------
// SLIDE 14: Verification, Testing & QA
// ----------------------------------------------------
let s14 = pptx.addSlide();
s14.background = { color: C_BG_LIGHT };
addHeader(s14, "Testing, Verification & Quality Assurance");

const testTableRows = [
  [
    { text: "Test ID", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } },
    { text: "Test Description", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } },
    { text: "Input Parameters", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } },
    { text: "Expected Result / Status", options: { bold: true, color: "FFFFFF", fill: { color: C_BLUE } } }
  ],
  [
    { text: "TC-01", options: { bold: true } },
    { text: "Account Creation & Approval", options: { color: C_TEXT_DARK } },
    { text: "Valid student ID, formatted email & password.", options: { color: C_TEXT_MUTED } },
    { text: "✅ Passed: Account created with status 'Pending Approval'.", options: { color: "16A34A", bold: true } }
  ],
  [
    { text: "TC-02", options: { bold: true } },
    { text: "SQL Parameter Injection Test", options: { color: C_TEXT_DARK } },
    { text: "' OR '1'='1 injected into login form.", options: { color: C_TEXT_MUTED } },
    { text: "✅ Passed: Access denied; input sanitized gracefully.", options: { color: "16A34A", bold: true } }
  ],
  [
    { text: "TC-03", options: { bold: true } },
    { text: "2FA / OTP Verification Flow", options: { color: C_TEXT_DARK } },
    { text: "Correct 6-digit OTP vs Expired OTP code.", options: { color: C_TEXT_MUTED } },
    { text: "✅ Passed: Validates correct OTP; blocks expired codes.", options: { color: "16A34A", bold: true } }
  ],
  [
    { text: "TC-04", options: { bold: true } },
    { text: "Multi-Parameter Search", options: { color: C_TEXT_DARK } },
    { text: "Dept='CSE', Company='Google', Batch='2022'.", options: { color: C_TEXT_MUTED } },
    { text: "✅ Passed: Returns exact matching profiles in < 1s.", options: { color: "16A34A", bold: true } }
  ]
];

s14.addTable(testTableRows, {
  x: 0.8, y: 1.6, w: 11.7, colW: [1.2, 3.2, 3.8, 3.5],
  border: { pt: 1, color: "CBD5E1" },
  fontFace: "Arial", fontSize: 11
});


// ----------------------------------------------------
// SLIDE 15: Future Roadmap & Conclusion (Dark Theme)
// ----------------------------------------------------
let s15 = pptx.addSlide();
s15.background = { color: C_DARK_BG };

s15.addText("FUTURE ENHANCEMENTS & CONCLUSION", {
  x: 0.8, y: 0.5, w: 11.0, h: 0.3,
  fontSize: 10, bold: true, color: "60A5FA", fontFace: "Arial"
});

s15.addText("Future Roadmap & Project Conclusion", {
  x: 0.8, y: 0.8, w: 11.5, h: 0.6,
  fontSize: 24, bold: true, color: "FFFFFF", fontFace: "Arial"
});

// Left Card - Roadmap
s15.addShape(pptx.shapes.RECTANGLE, {
  x: 0.8, y: 1.6, w: 5.6, h: 4.8,
  fill: { color: "1E293B" }, line: { color: "334155", width: 1 }
});

s15.addText("🚀 Future Roadmap", {
  x: 1.1, y: 1.9, w: 5.0, h: 0.4,
  fontSize: 16, bold: true, color: "60A5FA", fontFace: "Arial"
});

s15.addText([
  { text: "• Automated LinkedIn Sync: ", options: { bold: true, color: "38BDF8" } },
  { text: "Integrate LinkedIn OAuth API to auto-fetch current employment & profile updates.\n\n", options: { color: "CBD5E1" } },
  { text: "• Payment Gateway Module: ", options: { bold: true, color: "38BDF8" } },
  { text: "Embed Stripe / PayPal portals for alumni donations and alumni association dues.\n\n", options: { color: "CBD5E1" } },
  { text: "• AI Career Matching: ", options: { bold: true, color: "38BDF8" } },
  { text: "AI-based recommendations connecting students with mentors based on skill compatibility.", options: { color: "CBD5E1" } }
], {
  x: 1.1, y: 2.5, w: 5.0, h: 3.6,
  fontSize: 11, fontFace: "Arial"
});

// Right Card - Conclusion
s15.addShape(pptx.shapes.RECTANGLE, {
  x: 6.9, y: 1.6, w: 5.6, h: 4.8,
  fill: { color: "1E293B" }, line: { color: "334155", width: 1 }
});

s15.addText("🎯 Conclusion", {
  x: 7.2, y: 1.9, w: 5.0, h: 0.4,
  fontSize: 16, bold: true, color: "34D399", fontFace: "Arial"
});

s15.addText([
  { text: "• Complete Modernization: ", options: { bold: true, color: "34D399" } },
  { text: "ARMS transitions college administrative networking from manual spreadsheet storage to a secure, modern web directory.\n\n", options: { color: "CBD5E1" } },
  { text: "• Scalable & Secure: ", options: { bold: true, color: "34D399" } },
  { text: "Separates capabilities across distinct user roles, enforces 2FA/bcrypt security, and satisfies NBA/NAAC institutional audit criteria.\n\n", options: { color: "CBD5E1" } },
  { text: "• Production Ready: ", options: { bold: true, color: "34D399" } },
  { text: "Successfully deployed and tested on PHP 8.2 + MySQL XAMPP environment.", options: { color: "CBD5E1" } }
], {
  x: 7.2, y: 2.5, w: 5.0, h: 3.6,
  fontSize: 11, fontFace: "Arial"
});

// Save Presentation
const outputFile = path.join(__dirname, "Alumni_Record_Management_System_Presentation.pptx");
pptx.writeFile({ fileName: outputFile })
  .then(filename => console.log(`PPT generated successfully: ${filename}`))
  .catch(err => console.error("Error generating PPT:", err));

<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';

$page_title = "About Us";

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .about-hero {
        padding: 10rem 4rem 6rem 4rem;
        text-align: center;
        position: relative;
    }
    .about-hero h1 {
        font-size: 3.5rem;
        font-weight: 800;
        line-height: 1.15;
        margin-bottom: 1.5rem;
        letter-spacing: -0.03em;
    }
    .about-hero h1 span {
        background: var(--theme-accent-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .about-hero p {
        font-size: 1.2rem;
        color: var(--theme-text-secondary);
        max-width: 700px;
        margin: 0 auto 3rem auto;
    }
    .about-section {
        padding: 5rem 4rem;
        max-width: 1200px;
        margin: 0 auto;
    }
    .about-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }
    .feature-card {
        padding: 2.5rem;
        border-radius: var(--border-radius-lg);
        background: var(--theme-card);
        border: 1px solid var(--theme-border);
        transition: transform 0.3s ease, border-color 0.3s ease;
    }
    .feature-card:hover {
        transform: translateY(-5px);
        border-color: var(--theme-accent-purple);
    }
    .feature-icon {
        font-size: 2.2rem;
        margin-bottom: 1.5rem;
        background: var(--theme-accent-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: inline-block;
    }
    .feature-card h3 {
        font-size: 1.3rem;
        margin-bottom: 0.75rem;
        color: var(--theme-text);
    }
    .feature-card p {
        color: var(--theme-text-secondary);
        font-size: 0.95rem;
        line-height: 1.6;
    }
    .mission-statement {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--theme-border);
        border-radius: var(--border-radius-lg);
        padding: 4rem;
        text-align: center;
        margin-top: 4rem;
    }
    .mission-statement h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    .mission-statement p {
        color: var(--theme-text-secondary);
        max-width: 800px;
        margin: 0 auto;
        font-size: 1.1rem;
        line-height: 1.7;
    }
</style>

<div class="about-hero">
    <h1>Bridging Academic <span>Generations</span></h1>
    <p>AlumniNet is a high-fidelity platform designed to seamlessly connect university students with established alumni, empowering mentorship, job referrals, and professional opportunities.</p>
    <a href="register.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Join the Network</a>
</div>

<div class="about-section">
    <div class="mission-statement">
        <h2>Our Mission</h2>
        <p>We believe that mentorship and shared experiences are the cornerstones of successful careers. AlumniNet bridges the gap between those who have walked the path and those who are embarking on it, facilitating genuine professional growth through technology.</p>
    </div>

    <div class="about-grid">
        <div class="feature-card">
            <span class="feature-icon"><i class="fa-solid fa-handshake-angle"></i></span>
            <h3>Guiding Mentorship</h3>
            <p>Students can connect directly with alumni in their field of interest, receiving real-world guidance, portfolio reviews, and career counseling directly from active professionals.</p>
        </div>

        <div class="feature-card">
            <span class="feature-icon"><i class="fa-solid fa-briefcase"></i></span>
            <h3>Referrals & Opportunities</h3>
            <p>Our jobs board allows alumni to post referrals and corporate job opportunities, giving current students an exclusive channel to kickstart their industry placements.</p>
        </div>

        <div class="feature-card">
            <span class="feature-icon"><i class="fa-solid fa-calendar-days"></i></span>
            <h3>Networking Events</h3>
            <p>Coordinate reunions, professional webinars, and technical workshops to keep the institutional community connected, aligned, and collaborative.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

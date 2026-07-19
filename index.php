<?php
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/db.php';

$page_title = "Welcome to AlumniNet";

// 1. Fetch Dynamic Statistics
try {
    $stmtAlumni = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'alumni' AND status = 'approved'");
    $total_alumni = $stmtAlumni->fetchColumn();

    $stmtStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'approved'");
    $total_students = $stmtStudents->fetchColumn();

    $stmtJobs = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'");
    $total_jobs = $stmtJobs->fetchColumn();

    $stmtEvents = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= NOW()");
    $total_events = $stmtEvents->fetchColumn();
} catch (Exception $e) {
    $total_alumni = 0;
    $total_students = 0;
    $total_jobs = 0;
    $total_events = 0;
}

// 2. Fetch Latest 3 Active Jobs
try {
    $stmtLatestJobs = $pdo->query("SELECT * FROM jobs WHERE status = 'active' ORDER BY created_at DESC LIMIT 3");
    $latest_jobs = $stmtLatestJobs->fetchAll();
} catch (Exception $e) {
    $latest_jobs = [];
}

// 3. Fetch Next 3 Upcoming Events
try {
    $stmtUpcomingEvents = $pdo->query("SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 3");
    $upcoming_events = $stmtUpcomingEvents->fetchAll();
} catch (Exception $e) {
    $upcoming_events = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Hero styling matching Apple-style margins and Poppins fonts */
    .landing-hero {
        padding: 10rem 4rem 6rem 4rem;
        min-height: 95vh;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        gap: 2rem;
    }
    .hero-text {
        max-width: 600px;
    }
    .hero-text h1 {
        font-size: 3.8rem;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 1.5rem;
    }
    .hero-text h1 span {
        background: var(--theme-accent-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero-text p {
        font-size: 1.15rem;
        color: var(--theme-text-secondary);
        margin-bottom: 2.5rem;
    }
    .hero-actions {
        display: flex;
        gap: 1.25rem;
        margin-bottom: 3.5rem;
        flex-wrap: wrap;
    }
    .hero-visual {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 1;
    }
    .hero-visual-card {
        width: 85%;
        max-width: 480px;
        animation: float 6s ease-in-out infinite;
    }
    .section-padding {
        padding: 7rem 4rem;
    }
    .section-header {
        text-align: center;
        max-width: 800px;
        margin: 0 auto 5rem auto;
    }
    .section-header h2 {
        font-size: 2.8rem;
        margin-bottom: 1rem;
    }
    .section-header p {
        color: var(--theme-text-secondary);
        font-size: 1.05rem;
    }
    .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2.5rem;
    }
    .testimonial-slider {
        display: flex;
        gap: 2rem;
        overflow-x: auto;
        padding: 1.5rem 0;
        scroll-snap-type: x mandatory;
    }
    .testimonial-card {
        flex: 0 0 380px;
        scroll-snap-align: start;
    }
    .testimonial-user {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    .testimonial-user img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--theme-accent-blue);
    }
    .testimonial-user h4 { font-size: 0.95rem; }
    .testimonial-user p { font-size: 0.8rem; color: var(--theme-text-secondary); }
    
    .contact-wrapper {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
    }

    @media (max-width: 992px) {
        .landing-hero {
            flex-direction: column;
            padding-top: 8rem;
            text-align: center;
        }
        .hero-actions {
            justify-content: center;
        }
        .hero-visual-card {
            width: 70%;
        }
        .contact-wrapper {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
    }
    @media (max-width: 768px) {
        .section-padding {
            padding: 5rem 1.5rem;
        }
        .hero-text h1 {
            font-size: 2.8rem;
        }
    }
</style>

<!-- ==================== HERO SECTION ==================== -->
<section class="landing-hero" id="home">
    <div class="hero-text fade-in-up">
        <h1>Stay Connected, <br><span>Build Your Future</span></h1>
        <p>
            Connect with verified alumni and top students. Explore job postings, schedule mentorship sessions, and join grand campus reunions.
        </p>
        <div class="hero-actions">
            <?php if (is_logged_in()): ?>
                <a href="dashboard.php" class="btn btn-primary"><i class="fa-solid fa-gauge"></i> Go to Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Join the Community</a>
            <?php endif; ?>
            <a href="user/alumni.php" class="btn btn-secondary"><i class="fa-solid fa-users"></i> Search Alumni</a>
        </div>
        
        <!-- Live statistics counters -->
        <div style="display: flex; gap: 2.5rem; border-top: 1px solid var(--theme-border); padding-top: 2.25rem;">
            <div>
                <h3 style="font-size: 2rem; background: var(--theme-accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;" class="stat-counter-lbl"><?php echo $total_alumni; ?></h3>
                <p style="font-size: 0.85rem; color: var(--theme-text-secondary);">Registered Alumni</p>
            </div>
            <div>
                <h3 style="font-size: 2rem; background: var(--theme-accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;" class="stat-counter-lbl"><?php echo $total_students; ?></h3>
                <p style="font-size: 0.85rem; color: var(--theme-text-secondary);">Students Mentored</p>
            </div>
            <div>
                <h3 style="font-size: 2rem; background: var(--theme-accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;" class="stat-counter-lbl"><?php echo $total_jobs; ?></h3>
                <p style="font-size: 0.85rem; color: var(--theme-text-secondary);">Active Referrals</p>
            </div>
        </div>
    </div>

    <!-- Floating visual dashboard preview card -->
    <div class="hero-visual fade-in-up" style="animation-delay: 0.2s;">
        <div class="card-glass hero-visual-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div style="display: flex; gap: 0.5rem;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #ef4444;"></span>
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #f59e0b;"></span>
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981;"></span>
                </div>
                <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: var(--theme-accent-purple); font-size: 0.75rem; font-weight: 600;">Direct Mentoring</span>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem;">
                <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&fit=crop&q=80" alt="Sarah" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                <div>
                    <h4 style="font-size: 0.95rem;">Sarah Jenkins</h4>
                    <p style="font-size: 0.75rem; color: var(--theme-text-secondary);">Senior Engineer, Google</p>
                </div>
            </div>
            <p style="font-size: 0.82rem; color: var(--theme-text-secondary); background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.25rem;">
                "Hi Ravindra! I will review your full-stack roadmap and resume this week. Let's setup a call."
            </p>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; color: var(--theme-text-secondary); font-weight: 500;"><i class="fa-solid fa-circle-check" style="color:#10b981;"></i> Connection accepted</span>
                <a href="login.php" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px;">Explore</a>
            </div>
        </div>
    </div>
</section>

<!-- ==================== ABOUT & MISSION SECTION ==================== -->
<section class="section-padding" id="about" style="background: var(--theme-bg-secondary);">
    <div class="contact-wrapper">
        <div class="gsap-reveal">
            <span style="color: var(--theme-accent-purple); font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.05em;">Our Platform</span>
            <h2 style="font-size: 2.4rem; margin-top: 0.5rem; margin-bottom: 1.5rem;">Fostering structured alumni networking</h2>
            <p style="color: var(--theme-text-secondary); margin-bottom: 1rem; font-size: 1rem;">
                AlumniNet is designed to match students with graduates from the same college. The platform operates on verified professional credentials, enabling genuine referrals and secure data tracking.
            </p>
            <p style="color: var(--theme-text-secondary); font-size: 1rem;">
                Whether you are looking for job listings at Stripe or Microsoft, seeking mechanical engineering advice, or hosting an anniversary reunion, AlumniNet handles the entire cycle seamlessly.
            </p>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="card-glass gsap-reveal" style="padding: 1.75rem;">
                <i class="fa-solid fa-user-shield" style="font-size: 2rem; color: var(--theme-accent-blue); margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Verified Profiles</h4>
                <p style="font-size: 0.82rem; color: var(--theme-text-secondary);">Profiles are approved by administrators to secure networks from spam.</p>
            </div>
            <div class="card-glass gsap-reveal" style="padding: 1.75rem;">
                <i class="fa-solid fa-briefcase" style="font-size: 2rem; color: var(--theme-accent-purple); margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Direct Referrals</h4>
                <p style="font-size: 0.82rem; color: var(--theme-text-secondary);">Alumni share direct referral pathways for high-quality student candidates.</p>
            </div>
            <div class="card-glass gsap-reveal" style="padding: 1.75rem;">
                <i class="fa-solid fa-calendar-check" style="font-size: 2rem; color: #10b981; margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Interactive RSVPs</h4>
                <p style="font-size: 0.82rem; color: var(--theme-text-secondary);">One-click reservation updates for all webinars and grand campus homecomings.</p>
            </div>
            <div class="card-glass gsap-reveal" style="padding: 1.75rem;">
                <i class="fa-solid fa-handshake-angle" style="font-size: 2rem; color: #f59e0b; margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Mentorship Link</h4>
                <p style="font-size: 0.82rem; color: var(--theme-text-secondary);">Submit direct coaching requests with custom messages to prospective mentors.</p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== LATEST JOBS FEED ==================== -->
<section class="section-padding">
    <div class="section-header">
        <span style="color: var(--theme-accent-purple); font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.05em;">Career Board</span>
        <h2>Featured Referral Opportunities</h2>
        <p>Explore latest listings referred directly by our verified graduates.</p>
    </div>

    <?php if (!empty($latest_jobs)): ?>
        <div class="cards-catalog">
            <?php foreach ($latest_jobs as $job): ?>
                <div class="card-glass gsap-reveal" style="display: flex; flex-direction: column;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <h3 style="font-size: 1.2rem; font-weight:700; margin-bottom:0.15rem;"><?php echo htmlspecialchars($job['title']); ?></h3>
                            <div style="color: var(--theme-accent-purple); font-weight: 600; font-size: 0.95rem;"><?php echo htmlspecialchars($job['company']); ?></div>
                        </div>
                        <span class="badge badge-student" style="text-transform: uppercase;"><?php echo htmlspecialchars($job['type']); ?></span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem;">
                        <span style="font-size: 0.8rem; color: var(--theme-text-secondary); background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); padding: 0.2rem 0.6rem; border-radius: 4px;"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                        <?php if ($job['salary_range']): ?>
                            <span style="font-size: 0.8rem; color: var(--theme-text-secondary); background: rgba(255,255,255,0.03); border: 1px solid var(--theme-border); padding: 0.2rem 0.6rem; border-radius: 4px;"><i class="fa-solid fa-wallet"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                        <?php endif; ?>
                    </div>
                    <p style="font-size: 0.88rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem; flex-grow: 1;">
                        <?php echo htmlspecialchars(substr($job['description'], 0, 160)) . '...'; ?>
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--theme-border); padding-top: 1rem; margin-top: auto;">
                        <span style="font-size: 0.78rem; color: var(--theme-text-secondary);"><i class="fa-solid fa-calendar-day"></i> <?php echo date('M d, Y', strtotime($job['created_at'])); ?></span>
                        <a href="user/jobs.php" class="btn btn-secondary btn-small" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card-glass" style="text-align: center; padding: 4rem 2rem;">
            <i class="fa-solid fa-briefcase" style="font-size: 3rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem;"></i>
            <p style="color: var(--theme-text-secondary);">No active job listings found. Check back later!</p>
        </div>
    <?php endif; ?>
</section>

<!-- ==================== UPCOMING EVENTS SECTION ==================== -->
<section class="section-padding" style="background: var(--theme-bg-secondary);">
    <div class="section-header">
        <span style="color: var(--theme-accent-purple); font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.05em;">Community Schedule</span>
        <h2>Upcoming Webinars & Reunions</h2>
        <p>Participate in career panels and homecoming networking banquets.</p>
    </div>

    <?php if (!empty($upcoming_events)): ?>
        <div class="cards-catalog">
            <?php foreach ($upcoming_events as $event): 
                $banner = $event['banner_image'] ? htmlspecialchars($event['banner_image']) : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&fit=crop&q=80';
            ?>
                <div class="card-glass gsap-reveal" style="padding: 0; display:flex; flex-direction: column;">
                    <img src="<?php echo $banner; ?>" alt="Event banner" style="height: 180px; width: 100%; object-fit: cover; border-top-left-radius: inherit; border-top-right-radius: inherit;">
                    <div style="padding: 2rem; display: flex; flex-direction: column; flex-grow:1;">
                        <span class="badge badge-alumni" style="align-self: flex-start; margin-bottom: 0.75rem;"><?php echo date('M d, Y - h:i A', strtotime($event['event_date'])); ?></span>
                        <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($event['title']); ?></h3>
                        <div style="font-size: 0.82rem; color: var(--theme-text-secondary); margin-bottom: 0.25rem;"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($event['location']); ?></div>
                        <p style="font-size: 0.88rem; color: var(--theme-text-secondary); margin: 1rem 0; flex-grow: 1;">
                            <?php echo htmlspecialchars(substr($event['description'], 0, 120)) . '...'; ?>
                        </p>
                        <a href="user/events.php" class="btn btn-primary btn-small" style="width: 100%; text-align: center; margin-top: auto;">Details & RSVP</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card-glass" style="text-align: center; padding: 4rem 2rem;">
            <i class="fa-solid fa-calendar-xmark" style="font-size: 3rem; color: var(--theme-text-secondary); margin-bottom: 1.5rem;"></i>
            <p style="color: var(--theme-text-secondary);">No upcoming network events currently scheduled.</p>
        </div>
    <?php endif; ?>
</section>

<!-- ==================== CONTACT FORM ==================== -->
<section class="section-padding" id="contact">
    <div class="contact-wrapper">
        <div class="gsap-reveal">
            <h2>Reach Out To Our Support</h2>
            <p style="color: var(--theme-text-secondary); margin-top: 1rem; margin-bottom: 2rem;">
                Need assistance with your verification status, or have ideas to improve AlumniNet? Fill in details and send a query directly.
            </p>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <i class="fa-solid fa-envelope" style="color: var(--theme-accent-blue); font-size:1.25rem;"></i>
                    <span>support@alumninet.com</span>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <i class="fa-solid fa-phone" style="color: var(--theme-accent-purple); font-size:1.25rem;"></i>
                    <span>+91 98765 43210</span>
                </div>
            </div>
        </div>
        
        <div class="card-glass gsap-reveal">
            <form onsubmit="event.preventDefault(); showToast('Message Sent successfully!', 'success'); this.reset();">
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Your Full Name</label>
                    <input type="text" class="input-glass" placeholder="John Doe" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Email Address</label>
                    <input type="email" class="input-glass" placeholder="name@example.com" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="font-size: 0.85rem; font-weight:600; margin-bottom: 0.5rem; display:block;">Query / Suggestion</label>
                    <textarea class="input-glass" rows="4" placeholder="Explain details..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fa-solid fa-paper-plane"></i> Send Message</button>
            </form>
        </div>
    </div>
</section>

<!-- GSAP ScrollReveal hook scripts -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof gsap !== 'undefined') {
            // Count up numbers in hero
            const statCounters = document.querySelectorAll('.stat-counter-lbl');
            statCounters.forEach(cnt => {
                const val = parseInt(cnt.textContent, 10);
                if (!isNaN(val) && val > 0) {
                    cnt.textContent = '0';
                    gsap.to(cnt, {
                        innerText: val,
                        duration: 1.8,
                        snap: { innerText: 1 },
                        ease: "power2.out"
                    });
                }
            });

            // GSAP ScrollTrigger reveals
            if (typeof ScrollTrigger !== 'undefined') {
                gsap.utils.toArray('.gsap-reveal').forEach(el => {
                    gsap.from(el, {
                        scrollTrigger: {
                            trigger: el,
                            start: "top 85%",
                            toggleActions: "play none none none"
                        },
                        y: 40,
                        opacity: 0,
                        duration: 0.8,
                        ease: "power2.out"
                    });
                });
            }
        }
    });
</script>

<?php 
require_once __DIR__ . '/includes/footer.php'; 
?>

document.addEventListener('DOMContentLoaded', function() {
    
    // ==================== 0. SIDEBAR & DROPDOWNS GLOBAL ====================
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = sidebarToggle.querySelector('i');
            if (icon) {
                if (sidebar.classList.contains('collapsed')) {
                    icon.className = 'fa-solid fa-chevron-right';
                } else {
                    icon.className = 'fa-solid fa-chevron-left';
                }
            }
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 300);
        });
    }

    const mobileSidebarBtn = document.getElementById('mobile-sidebar-toggle');
    if (mobileSidebarBtn && sidebar) {
        mobileSidebarBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    function setupGlobalDropdown(toggleId, menuId) {
        const toggle = document.getElementById(toggleId);
        const menu = document.getElementById(menuId);
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                document.querySelectorAll('.nav-dropdown-menu').forEach(m => {
                    if (m.id !== menuId) m.classList.remove('show');
                });
                menu.classList.toggle('show');
            });
        }
    }

    setupGlobalDropdown('notif-bell-toggle', 'notif-dropdown-menu');
    setupGlobalDropdown('profile-avatar-toggle', 'profile-dropdown-menu');

    document.addEventListener('click', function() {
        document.querySelectorAll('.nav-dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    });

    // ==================== 1. PERFORMANCE ANIMATIONS TOGGLE ====================
    let animationsEnabled = localStorage.getItem('animations-enabled') !== 'false';
    const perfToggle = document.getElementById('performance-toggle');
    
    if (perfToggle) {
        perfToggle.checked = animationsEnabled;
        perfToggle.addEventListener('change', function() {
            animationsEnabled = this.checked;
            localStorage.setItem('animations-enabled', animationsEnabled ? 'true' : 'false');
            showToast(animationsEnabled ? 'Animations enabled (60 FPS)' : 'Animations disabled (Performance mode)', 'info');
            
            // Reapply background config to refresh loop
            applyBackgroundConfig(currentBgMode, currentBlur, currentOpacity, customBgImage);
            
            if (lenis) {
                if (animationsEnabled) lenis.start();
                else lenis.destroy();
            }
        });
    }

    // ==================== 2. LENIS SMOOTH SCROLL INTEGRATION ====================
    let lenis;
    if (typeof Lenis !== 'undefined' && animationsEnabled) {
        lenis = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            direction: 'vertical',
            gestureDirection: 'vertical',
            smooth: true,
            mouseMultiplier: 1,
            touchMultiplier: 2,
            infinite: false
        });

        lenis.on('scroll', () => {
            if (typeof ScrollTrigger !== 'undefined') {
                ScrollTrigger.update();
            }
        });

        gsap.ticker.add((time) => {
            if (animationsEnabled && lenis) {
                lenis.raf(time * 1000);
            }
        });

        gsap.ticker.lagSmoothing(0);
    }

    // ==================== 3. CUSTOM ANIMATED CURSOR WITH TRAIL ====================
    const cursorGlow = document.getElementById('custom-cursor-glow');
    let cursorDot = document.getElementById('custom-cursor-dot');
    
    // Dynamically spawn cursor dot if missing
    if (!cursorDot) {
        cursorDot = document.createElement('div');
        cursorDot.id = 'custom-cursor-dot';
        cursorDot.style.position = 'fixed';
        cursorDot.style.width = '8px';
        cursorDot.style.height = '8px';
        cursorDot.style.backgroundColor = 'var(--theme-accent-blue)';
        cursorDot.style.borderRadius = '50%';
        cursorDot.style.pointerEvents = 'none';
        cursorDot.style.zIndex = '100000';
        cursorDot.style.transform = 'translate(-50%, -50%)';
        document.body.appendChild(cursorDot);
    }

    // Hide custom cursor on mobile touchscreens
    const isMobile = window.matchMedia("(max-width: 768px)").matches || 'ontouchstart' in window;
    if (isMobile) {
        if (cursorGlow) cursorGlow.style.display = 'none';
        if (cursorDot) cursorDot.style.display = 'none';
    } else {
        window.addEventListener('mousemove', (e) => {
            // Immediate cursor dot tracking
            gsap.set(cursorDot, { x: e.clientX, y: e.clientY });

            // Lagged glow cursor trail
            if (cursorGlow) {
                if (animationsEnabled) {
                    gsap.to(cursorGlow, {
                        x: e.clientX,
                        y: e.clientY,
                        duration: 0.45,
                        ease: 'power2.out'
                    });
                } else {
                    gsap.set(cursorGlow, { x: e.clientX, y: e.clientY });
                }
            }
        });

        // Enlarge cursor on interactive links/buttons
        const interactives = document.querySelectorAll('a, button, .btn, .card-glass, .sidebar-item');
        interactives.forEach(el => {
            el.addEventListener('mouseenter', () => {
                gsap.to(cursorDot, { scale: 2.2, backgroundColor: 'transparent', borderColor: 'var(--theme-accent-blue)', borderWidth: '1.5px', duration: 0.25 });
                if (cursorGlow) gsap.to(cursorGlow, { scale: 1.25, duration: 0.25 });
            });
            el.addEventListener('mouseleave', () => {
                gsap.to(cursorDot, { scale: 1, backgroundColor: 'var(--theme-accent-blue)', borderWidth: '0px', duration: 0.25 });
                if (cursorGlow) gsap.to(cursorGlow, { scale: 1, duration: 0.25 });
            });
        });
    }

    // ==================== 4. SETTINGS SLIDING DRAWER ====================
    window.openSettingsDrawer = function() {
        gsap.to('#settings-drawer', {
            right: 0,
            duration: 0.5,
            ease: 'power3.out'
        });
    };

    window.closeSettingsDrawer = function() {
        gsap.to('#settings-drawer', {
            right: -390,
            duration: 0.4,
            ease: 'power3.in'
        });
    };

    // Close settings drawer on click outside
    document.addEventListener('click', function(e) {
        const drawer = document.getElementById('settings-drawer');
        const trigger = document.querySelector('.fab');
        const navTriggers = document.querySelectorAll('.theme-toggle-btn');
        
        let clickedTrigger = false;
        navTriggers.forEach(nt => {
            if (nt.contains(e.target)) clickedTrigger = true;
        });

        if (drawer && !drawer.contains(e.target) && trigger && !trigger.contains(e.target) && !clickedTrigger) {
            closeSettingsDrawer();
        }
    });

    // ==================== 5. MULTI-THEME ENGINE ====================
    const themeButtons = document.querySelectorAll('.theme-select-btn');
    const savedTheme = localStorage.getItem('theme-style') || 'theme-dark';
    
    applyTheme(savedTheme);

    themeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const themeClass = this.getAttribute('data-theme');
            applyTheme(themeClass);
            showToast(`Theme updated to: ${themeClass.replace('theme-', '').toUpperCase()}`, 'info');
        });
    });

    function applyTheme(themeClass) {
        // Clear old classes
        document.body.className = document.body.className.replace(/\btheme-\S+/g, '');
        document.body.classList.add(themeClass);
        localStorage.setItem('theme-style', themeClass);
        
        themeButtons.forEach(btn => {
            if (btn.getAttribute('data-theme') === themeClass) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        if (animationsEnabled) {
            gsap.fromTo('body', { opacity: 0.95 }, { opacity: 1, duration: 0.45, ease: 'sine.out' });
        }
    }

    // ==================== 6. ACCENT COLOR ENGINE ====================
    const accentCircles = document.querySelectorAll('.accent-color-circle');
    const savedAccent = localStorage.getItem('accent-color') || '#3b82f6';

    applyAccentColor(savedAccent);

    accentCircles.forEach(circle => {
        circle.addEventListener('click', function() {
            const color = this.getAttribute('data-color');
            applyAccentColor(color);
            showToast(`Accent color updated to: ${color.toUpperCase()}`, 'success');
        });
    });

    function applyAccentColor(hexColor) {
        document.documentElement.style.setProperty('--theme-accent-blue', hexColor);
        document.documentElement.style.setProperty('--theme-accent-purple', hexColor);
        
        const gradient = `linear-gradient(135deg, ${hexColor} 0%, #8b5cf6 100%)`;
        document.documentElement.style.setProperty('--theme-accent-gradient', gradient);
        
        const glow = hexColor.startsWith('#') ? hexColor + '40' : hexColor;
        document.documentElement.style.setProperty('--theme-accent-glow', glow);
        
        accentCircles.forEach(circle => {
            if (circle.getAttribute('data-color') === hexColor) {
                circle.classList.add('active');
            } else {
                circle.classList.remove('active');
            }
        });
        localStorage.setItem('accent-color', hexColor);
    }

    // ==================== 7. BACKGROUND CONFIGURATION ====================
    const bgButtons = document.querySelectorAll('.bg-option-btn');
    const blurSlider = document.getElementById('custom-bg-blur');
    const opacitySlider = document.getElementById('custom-bg-opacity');
    const speedSlider = document.getElementById('custom-bg-speed');
    const fileInput = document.getElementById('custom-bg-upload');
    const resetBtn = document.getElementById('custom-bg-reset');

    let currentBgMode = localStorage.getItem('bg-mode') || 'mesh';
    let currentBlur = localStorage.getItem('bg-blur') || '15';
    let currentOpacity = localStorage.getItem('bg-opacity') || '100';
    let animationSpeed = parseFloat(localStorage.getItem('bg-speed')) || 1.0;
    let customBgImage = localStorage.getItem('bg-custom-image') || '';

    applyBackgroundConfig(currentBgMode, currentBlur, currentOpacity, customBgImage);

    bgButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const mode = this.getAttribute('data-bg');
            currentBgMode = mode;
            applyBackgroundConfig(mode, currentBlur, currentOpacity, customBgImage);
            showToast(`Background mode: ${mode.toUpperCase()}`, 'info');
        });
    });

    if (blurSlider) {
        blurSlider.addEventListener('input', function() {
            currentBlur = this.value;
            applyBackgroundConfig(currentBgMode, currentBlur, currentOpacity, customBgImage);
        });
    }

    if (opacitySlider) {
        opacitySlider.addEventListener('input', function() {
            currentOpacity = this.value;
            applyBackgroundConfig(currentBgMode, currentBlur, currentOpacity, customBgImage);
        });
    }

    if (speedSlider) {
        speedSlider.addEventListener('input', function() {
            animationSpeed = parseFloat(this.value);
            localStorage.setItem('bg-speed', this.value);
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    customBgImage = e.target.result;
                    currentBgMode = 'custom-image';
                    applyBackgroundConfig('custom-image', currentBlur, currentOpacity, customBgImage);
                    showToast('Custom background layout saved!', 'success');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            currentBgMode = 'mesh';
            currentBlur = '15';
            currentOpacity = '100';
            animationSpeed = 1.0;
            customBgImage = '';
            applyAccentColor('#3b82f6');
            applyTheme('theme-dark');
            applyBackgroundConfig('mesh', '15', '100', '');
            if (speedSlider) speedSlider.value = '1.0';
            showToast('Reset all styles to default values.', 'info');
        });
    }

    function applyBackgroundConfig(mode, blurVal, opacityVal, imageBase64) {
        localStorage.setItem('bg-mode', mode);
        localStorage.setItem('bg-blur', blurVal);
        localStorage.setItem('bg-opacity', opacityVal);
        
        if (imageBase64) {
            localStorage.setItem('bg-custom-image', imageBase64);
        } else {
            localStorage.removeItem('bg-custom-image');
        }

        document.documentElement.style.setProperty('--bg-blur', `${blurVal}px`);
        document.documentElement.style.setProperty('--bg-opacity', opacityVal / 100);

        if (blurSlider) blurSlider.value = blurVal;
        if (opacitySlider) opacitySlider.value = opacityVal;

        bgButtons.forEach(btn => {
            if (btn.getAttribute('data-bg') === mode) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        initCanvasBackground(mode, imageBase64);
    }

    // ==================== 8. 14-MODE CANVAS VISUALIZER ENGINE ====================
    let canvasAnimationId = null;
    
    function initCanvasBackground(mode, customImageSrc) {
        const canvas = document.getElementById('custom-bg-canvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        let width = canvas.width = window.innerWidth;
        let height = canvas.height = window.innerHeight;

        window.addEventListener('resize', () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        });

        if (canvasAnimationId) {
            cancelAnimationFrame(canvasAnimationId);
        }

        // Particle configuration arrays
        let particles = [];
        let time = 0;
        const colorAccent = localStorage.getItem('accent-color') || '#3b82f6';

        // Prepare particle sets depending on the mode
        if (mode === 'stars' || mode === 'galaxy') {
            const count = mode === 'stars' ? 120 : 250;
            for (let i = 0; i < count; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    size: Math.random() * 2 + 0.5,
                    alpha: Math.random(),
                    speed: 0.005 + Math.random() * 0.015,
                    angle: Math.random() * Math.PI * 2,
                    distance: Math.random() * width * 0.5
                });
            }
        } else if (mode === 'rain') {
            for (let i = 0; i < 90; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    length: Math.random() * 15 + 10,
                    vy: Math.random() * 10 + 6
                });
            }
        } else if (mode === 'snow') {
            for (let i = 0; i < 65; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    radius: Math.random() * 3 + 1,
                    vy: Math.random() * 1.5 + 0.5,
                    vx: (Math.random() - 0.5) * 1
                });
            }
        } else if (mode === 'bubbles') {
            for (let i = 0; i < 30; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: height + Math.random() * 100,
                    radius: Math.random() * 20 + 8,
                    vy: Math.random() * 0.8 + 0.4,
                    vx: (Math.random() - 0.5) * 0.4
                });
            }
        } else if (mode === 'particles') {
            for (let i = 0; i < 65; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    radius: Math.random() * 3 + 1,
                    vx: (Math.random() - 0.5) * 1.5,
                    vy: (Math.random() - 0.5) * 1.5
                });
            }
        } else if (mode === 'shapes') {
            for (let i = 0; i < 15; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    size: Math.random() * 25 + 12,
                    type: ['triangle', 'square', 'circle'][Math.floor(Math.random() * 3)],
                    vx: (Math.random() - 0.5) * 1,
                    vy: (Math.random() - 0.5) * 1,
                    angle: Math.random() * Math.PI,
                    spin: (Math.random() - 0.5) * 0.02
                });
            }
        } else if (mode === 'aurora' || mode === 'mesh' || mode === 'blobs' || mode === 'glass-bg' || mode === 'glass') {
            for (let i = 0; i < 4; i++) {
                particles.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    radius: 250 + Math.random() * 250,
                    vx: (Math.random() - 0.5) * 1,
                    vy: (Math.random() - 0.5) * 1,
                    color: i === 0 ? 'rgba(59, 130, 246, 0.12)' : (i === 1 ? 'rgba(139, 92, 246, 0.12)' : (i === 2 ? 'rgba(236, 72, 153, 0.08)' : 'rgba(16, 185, 129, 0.06)'))
                });
            }
        }

        let customImg = null;
        if (mode === 'custom-image' && customImageSrc) {
            customImg = new Image();
            customImg.src = customImageSrc;
        }

        function drawLoop() {
            ctx.clearRect(0, 0, width, height);

            if (!animationsEnabled) {
                drawStaticFrame(mode, customImg);
                return;
            }

            time += 0.015 * animationSpeed;

            if (mode === 'custom-image' && customImg && customImg.complete) {
                ctx.drawImage(customImg, 0, 0, width, height);
            } 
            
            else if (mode === 'aurora') {
                particles.forEach((blob, idx) => {
                    ctx.fillStyle = blob.color;
                    ctx.beginPath();
                    blob.x += Math.sin(time + idx) * 0.45;
                    blob.y += Math.cos(time + idx) * 0.45;
                    ctx.arc(blob.x, blob.y, blob.radius * 1.6, 0, Math.PI * 2);
                    ctx.fill();
                });
            } 
            
            else if (mode === 'mesh' || mode === 'blobs') {
                particles.forEach(blob => {
                    ctx.fillStyle = blob.color;
                    ctx.beginPath();
                    ctx.arc(blob.x, blob.y, blob.radius, 0, Math.PI * 2);
                    ctx.fill();
                    
                    blob.x += blob.vx * animationSpeed;
                    blob.y += blob.vy * animationSpeed;
                    
                    if (blob.x - blob.radius < 0 || blob.x + blob.radius > width) blob.vx = -blob.vx;
                    if (blob.y - blob.radius < 0 || blob.y + blob.radius > height) blob.vy = -blob.vy;
                });
            } 
            
            else if (mode === 'gradient') {
                let x1 = width / 2 + Math.cos(time * 0.4) * (width / 2);
                let y1 = height / 2 + Math.sin(time * 0.4) * (height / 2);
                let x2 = width / 2 - Math.cos(time * 0.4) * (width / 2);
                let y2 = height / 2 - Math.sin(time * 0.4) * (height / 2);
                
                let grad = ctx.createLinearGradient(x1, y1, x2, y2);
                grad.addColorStop(0, 'rgba(11, 15, 25, 0.95)');
                grad.addColorStop(0.5, 'rgba(23, 21, 45, 0.95)');
                grad.addColorStop(1, 'rgba(9, 5, 15, 0.95)');
                
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, width, height);
            }

            else if (mode === 'glass-bg' || mode === 'glass') {
                particles.forEach(blob => {
                    ctx.fillStyle = blob.color;
                    ctx.beginPath();
                    ctx.arc(blob.x, blob.y, blob.radius * 1.5, 0, Math.PI * 2);
                    ctx.fill();
                });
            }

            else if (mode === 'galaxy') {
                ctx.fillStyle = '#ffffff';
                const cx = width / 2;
                const cy = height / 2;
                particles.forEach(star => {
                    star.angle += star.speed * 0.15 * animationSpeed;
                    const x = cx + Math.cos(star.angle) * star.distance;
                    const y = cy + Math.sin(star.angle) * star.distance * 0.65;
                    
                    ctx.globalAlpha = star.alpha;
                    ctx.beginPath();
                    ctx.arc(x, y, star.size, 0, Math.PI * 2);
                    ctx.fill();
                });
                ctx.globalAlpha = 1;
            }

            else if (mode === 'stars') {
                ctx.fillStyle = '#ffffff';
                particles.forEach(star => {
                    ctx.globalAlpha = star.alpha;
                    ctx.beginPath();
                    ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                    ctx.fill();
                    
                    star.alpha += star.speed * animationSpeed;
                    if (star.alpha > 1 || star.alpha < 0) {
                        star.speed = -star.speed;
                    }
                });
                ctx.globalAlpha = 1;
            }

            else if (mode === 'rain') {
                ctx.strokeStyle = colorAccent + '30';
                ctx.lineWidth = 1;
                particles.forEach(drop => {
                    ctx.beginPath();
                    ctx.moveTo(drop.x, drop.y);
                    ctx.lineTo(drop.x, drop.y + drop.length);
                    ctx.stroke();
                    
                    drop.y += drop.vy * animationSpeed;
                    if (drop.y > height) {
                        drop.y = -drop.length;
                        drop.x = Math.random() * width;
                    }
                });
            }

            else if (mode === 'snow') {
                ctx.fillStyle = '#ffffff';
                particles.forEach(flake => {
                    ctx.beginPath();
                    ctx.arc(flake.x, flake.y, flake.radius, 0, Math.PI * 2);
                    ctx.fill();
                    
                    flake.y += flake.vy * animationSpeed;
                    flake.x += flake.vx * animationSpeed + Math.sin(time + flake.x) * 0.15;
                    
                    if (flake.y > height) {
                        flake.y = -flake.radius;
                        flake.x = Math.random() * width;
                    }
                });
            }

            else if (mode === 'bubbles') {
                ctx.strokeStyle = colorAccent + '25';
                ctx.lineWidth = 1;
                particles.forEach(bubble => {
                    ctx.beginPath();
                    ctx.arc(bubble.x, bubble.y, bubble.radius, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.01)';
                    ctx.fill();
                    ctx.stroke();
                    
                    bubble.y -= bubble.vy * animationSpeed;
                    bubble.x += bubble.vx * animationSpeed + Math.sin(time * 0.3) * 0.1;
                    
                    if (bubble.y < -bubble.radius) {
                        bubble.y = height + bubble.radius;
                        bubble.x = Math.random() * width;
                    }
                });
            }

            else if (mode === 'particles') {
                ctx.fillStyle = colorAccent + '60';
                particles.forEach(node => {
                    ctx.beginPath();
                    ctx.arc(node.x, node.y, node.radius, 0, Math.PI * 2);
                    ctx.fill();
                    
                    node.x += node.vx * animationSpeed;
                    node.y += node.vy * animationSpeed;
                    
                    if (node.x < 0 || node.x > width) node.vx = -node.vx;
                    if (node.y < 0 || node.y > height) node.vy = -node.vy;
                });
                
                ctx.strokeStyle = colorAccent + '15';
                ctx.lineWidth = 0.8;
                for (let i = 0; i < particles.length; i++) {
                    for (let j = i + 1; j < particles.length; j++) {
                        const dist = Math.hypot(particles[i].x - particles[j].x, particles[i].y - particles[j].y);
                        if (dist < 100) {
                            ctx.beginPath();
                            ctx.moveTo(particles[i].x, particles[i].y);
                            ctx.lineTo(particles[j].x, particles[j].y);
                            ctx.stroke();
                        }
                    }
                }
            }

            else if (mode === 'waves') {
                ctx.strokeStyle = colorAccent + '20';
                ctx.lineWidth = 1.2;
                for (let i = 0; i < 3; i++) {
                    ctx.beginPath();
                    for (let x = 0; x < width; x += 5) {
                        const y = height * 0.55 + i * 35 + Math.sin(x * 0.003 + time + i) * 45;
                        if (x === 0) ctx.moveTo(x, y);
                        else ctx.lineTo(x, y);
                    }
                    ctx.stroke();
                }
            }

            else if (mode === 'grid') {
                ctx.strokeStyle = colorAccent + '15';
                ctx.lineWidth = 1;
                const step = 45;
                const horizon = height * 0.45;
                for (let x = 0; x < width; x += 60) {
                    ctx.beginPath();
                    ctx.moveTo(x, height);
                    ctx.lineTo(width / 2 + (x - width / 2) * 0.08, horizon);
                    ctx.stroke();
                }
                const gridOffset = (time * 30) % step;
                for (let y = horizon + gridOffset; y < height; y += step) {
                    ctx.beginPath();
                    ctx.moveTo(0, y);
                    ctx.lineTo(width, y);
                    ctx.stroke();
                }
            }

            else if (mode === 'shapes') {
                ctx.strokeStyle = colorAccent + '35';
                ctx.lineWidth = 1.2;
                particles.forEach(shape => {
                    ctx.save();
                    ctx.translate(shape.x, shape.y);
                    ctx.rotate(shape.angle);
                    
                    ctx.beginPath();
                    if (shape.type === 'triangle') {
                        ctx.moveTo(0, -shape.size / 2);
                        ctx.lineTo(shape.size / 2, shape.size / 2);
                        ctx.lineTo(-shape.size / 2, shape.size / 2);
                        ctx.closePath();
                    } else if (shape.type === 'square') {
                        ctx.rect(-shape.size / 2, -shape.size / 2, shape.size, shape.size);
                    } else {
                        ctx.arc(0, 0, shape.size / 2, 0, Math.PI * 2);
                    }
                    ctx.stroke();
                    ctx.restore();

                    shape.x += shape.vx * animationSpeed;
                    shape.y += shape.vy * animationSpeed;
                    shape.angle += shape.spin * animationSpeed;

                    if (shape.x < 0 || shape.x > width) shape.vx = -shape.vx;
                    if (shape.y < 0 || shape.y > height) shape.vy = -shape.vy;
                });
            }

            else if (mode === 'minimal-white') {
                drawStaticFrame('minimal-white', null);
                return;
            }

            canvasAnimationId = requestAnimationFrame(drawLoop);
        }

        // Draw static representation
        function drawStaticFrame(currentMode, customImg) {
            ctx.clearRect(0, 0, width, height);
            if (currentMode === 'custom-image' && customImg && customImg.complete) {
                ctx.drawImage(customImg, 0, 0, width, height);
            } else if (currentMode === 'minimal-white') {
                ctx.fillStyle = '#f8fafc';
                ctx.fillRect(0, 0, width, height);
                // Draw a soft radial light center
                const grad = ctx.createRadialGradient(width/2, height/2, 100, width/2, height/2, width*0.5);
                grad.addColorStop(0, '#ffffff');
                grad.addColorStop(1, '#f1f5f9');
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, width, height);
            } else {
                const gradient = ctx.createRadialGradient(width/2, height/2, 10, width/2, height/2, width*0.8);
                gradient.addColorStop(0, 'rgba(31, 41, 55, 0.05)');
                gradient.addColorStop(1, 'rgba(11, 15, 25, 0.02)');
                ctx.fillStyle = gradient;
                ctx.fillRect(0, 0, width, height);
            }
        }
        
        drawLoop();
    }

    // ==================== 9. CARD TILT EFFECT (3D HOVER) ====================
    const cards = document.querySelectorAll('.card-glass');
    cards.forEach(card => {
        card.addEventListener('mousemove', function(e) {
            if (!animationsEnabled) return;
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const xc = rect.width / 2;
            const yc = rect.height / 2;
            
            const rotateX = -(y - yc) / 15;
            const rotateY = (x - xc) / 15;

            gsap.to(this, {
                rotateX: rotateX,
                rotateY: rotateY,
                duration: 0.3,
                ease: 'power2.out',
                transformPerspective: 800
            });
        });

        card.addEventListener('mouseleave', function() {
            gsap.to(this, {
                rotateX: 0,
                rotateY: 0,
                duration: 0.5,
                ease: 'power2.out'
            });
        });
    });

    // ==================== 10. MAGNETIC BUTTONS ====================
    const magneticBtns = document.querySelectorAll('.btn-primary, .btn-secondary');
    magneticBtns.forEach(btn => {
        btn.addEventListener('mousemove', function(e) {
            if (!animationsEnabled) return;
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;

            gsap.to(this, {
                x: x * 0.35,
                y: y * 0.35,
                duration: 0.3,
                ease: 'power2.out'
            });
        });

        btn.addEventListener('mouseleave', function() {
            gsap.to(this, {
                x: 0,
                y: 0,
                duration: 0.4,
                ease: 'elastic.out(1, 0.3)'
            });
        });
    });

    // ==================== 11. TOAST NOTIFICATIONS FACTORY ====================
    window.showToast = function(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let iconClass = 'fa-circle-check';
        let iconColor = 'var(--accent-success)';
        if (type === 'error') {
            iconClass = 'fa-circle-xmark';
            iconColor = 'var(--accent-danger)';
        } else if (type === 'warning') {
            iconClass = 'fa-circle-exclamation';
            iconColor = 'var(--accent-warning)';
        } else if (type === 'info') {
            iconClass = 'fa-circle-info';
            iconColor = 'var(--theme-accent-blue)';
        }

        toast.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <i class="fa-solid ${iconClass}" style="font-size: 1.35rem; color: ${iconColor};"></i>
                <span style="font-size: 0.92rem; font-weight: 600;">${message}</span>
            </div>
            <button class="toast-close" style="transition: color 0.2s ease;">&times;</button>
        `;

        container.appendChild(toast);

        // Entrance animation: slide and spring bounce
        gsap.fromTo(toast, 
            { x: 150, opacity: 0, scale: 0.85 },
            { x: 0, opacity: 1, scale: 1, duration: 0.5, ease: 'back.out(1.2)' }
        );

        const closeToast = () => {
            gsap.to(toast, {
                x: 150,
                opacity: 0,
                scale: 0.85,
                duration: 0.35,
                ease: 'power2.in',
                onComplete: () => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }
            });
        };

        toast.querySelector('.toast-close').addEventListener('click', closeToast);

        setTimeout(() => {
            if (toast.parentElement) {
                closeToast();
            }
        }, 4500);
    };

    // Button click ripple effect
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const x = e.clientX - e.target.getBoundingClientRect().left;
            const y = e.clientY - e.target.getBoundingClientRect().top;
            
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // ==================== 12. BACK TO TOP BUTTON ====================
    const backToTopBtn = document.getElementById('back-to-top');
    if (backToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 400) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });

        backToTopBtn.addEventListener('click', function() {
            if (lenis && animationsEnabled) {
                lenis.scrollTo(0);
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }

    // ==================== 13. MODALS ====================
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            if (animationsEnabled) {
                gsap.fromTo(modal.querySelector('.modal-content'), {
                    scale: 0.9,
                    opacity: 0
                }, {
                    scale: 1,
                    opacity: 1,
                    duration: 0.4,
                    ease: 'back.out(1.7)'
                });
            } else {
                gsap.set(modal.querySelector('.modal-content'), { scale: 1, opacity: 1 });
            }
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            if (animationsEnabled) {
                gsap.to(modal.querySelector('.modal-content'), {
                    scale: 0.9,
                    opacity: 0,
                    duration: 0.3,
                    onComplete: () => {
                        modal.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                });
            } else {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
    };
});

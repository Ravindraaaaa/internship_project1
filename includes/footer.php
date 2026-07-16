    <!-- ==================== BACK TO TOP FAB ==================== -->
    <button class="back-to-top" id="back-to-top" title="Back to Top">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <!-- ==================== FAB OPEN SETTINGS DRAWER ==================== -->
    <button class="fab" onclick="openSettingsDrawer()" title="Customize Theme & Visual Backgrounds">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
    </button>

    <!-- ==================== SLIDING SETTINGS DRAWER ==================== -->
    <div class="settings-drawer" id="settings-drawer">
        <div class="drawer-header">
            <h3><i data-lucide="sliders" style="width:18px;height:18px;vertical-align:middle;margin-right:0.25rem;"></i> Visual Settings</h3>
            <button class="drawer-close-btn" onclick="closeSettingsDrawer()">&times;</button>
        </div>
        
        <div class="drawer-body">
            
            <!-- 1. Color Themes -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="palette" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 1. Color Theme</h4>
                <div class="customizer-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <button class="customizer-btn theme-select-btn" data-theme="theme-dark">Dark Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-light">Light Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-glass">Glass Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-midnight">Midnight Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-aurora">Aurora Theme</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-cyber-blue">Cyber Blue</button>
                    <button class="customizer-btn theme-select-btn" data-theme="theme-royal-purple">Royal Purple</button>
                </div>
            </div>

            <!-- 2. Accent Colors -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="droplet" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 2. Accent Color</h4>
                <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
                    <div class="accent-color-circle active" data-color="#3b82f6" style="background: #3b82f6;" title="Electric Blue"></div>
                    <div class="accent-color-circle" data-color="#8b5cf6" style="background: #8b5cf6;" title="Cyber Purple"></div>
                    <div class="accent-color-circle" data-color="#10b981" style="background: #10b981;" title="Neon Green"></div>
                    <div class="accent-color-circle" data-color="#ec4899" style="background: #ec4899;" title="Cyber Pink"></div>
                    <div class="accent-color-circle" data-color="#f59e0b" style="background: #f59e0b;" title="Golden Amber"></div>
                    <div class="accent-color-circle" data-color="#ef4444" style="background: #ef4444;" title="Crimson Red"></div>
                </div>
            </div>

            <!-- 3. Dynamic Background Selection -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="image" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 3. Canvas Background</h4>
                <div class="customizer-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <button class="customizer-btn bg-option-btn" data-bg="aurora">Animated Aurora</button>
                    <button class="customizer-btn bg-option-btn" data-bg="mesh">Mesh Gradient</button>
                    <button class="customizer-btn bg-option-btn" data-bg="gradient">Animated Gradient</button>
                    <button class="customizer-btn bg-option-btn" data-bg="glass-bg">Glass Background</button>
                    <button class="customizer-btn bg-option-btn" data-bg="blobs">Floating Blobs</button>
                    <button class="customizer-btn bg-option-btn" data-bg="particles">Particles</button>
                    <button class="customizer-btn bg-option-btn" data-bg="stars">Stars</button>
                    <button class="customizer-btn bg-option-btn" data-bg="waves">Waves</button>
                    <button class="customizer-btn bg-option-btn" data-bg="grid">Animated Grid</button>
                    <button class="customizer-btn bg-option-btn" data-bg="shapes">Abstract Shapes</button>
                    <button class="customizer-btn bg-option-btn" data-bg="bubbles">Floating Bubbles</button>
                    <button class="customizer-btn bg-option-btn" data-bg="galaxy">Galaxy</button>
                    <button class="customizer-btn bg-option-btn" data-bg="minimal-white">Minimal White</button>
                    <button class="customizer-btn bg-option-btn" data-bg="custom-image">Custom Upload</button>
                </div>
            </div>

            <!-- 4. Adjust Sliders & Custom Background -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="sliders" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 4. Adjust Parameters</h4>
                
                <div class="slider-group">
                    <label for="custom-bg-blur">Background Blur <span id="blur-val">15px</span></label>
                    <input type="range" id="custom-bg-blur" class="custom-range" min="0" max="30" value="15" oninput="document.getElementById('blur-val').textContent = this.value + 'px'">
                </div>

                <div class="slider-group">
                    <label for="custom-bg-opacity">Background Opacity <span id="opacity-val">100%</span></label>
                    <input type="range" id="custom-bg-opacity" class="custom-range" min="10" max="100" value="100" oninput="document.getElementById('opacity-val').textContent = this.value + '%'">
                </div>

                <div class="slider-group">
                    <label for="custom-bg-speed">Animation Speed <span id="speed-val">1.0x</span></label>
                    <input type="range" id="custom-bg-speed" class="custom-range" min="0.1" max="3" step="0.1" value="1.0" oninput="document.getElementById('speed-val').textContent = this.value + 'x'">
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: space-between; align-items: center;">
                    <label class="btn btn-secondary btn-small" style="font-size: 0.8rem; cursor: pointer; flex-grow: 1; text-align: center;">
                        <i class="fa-solid fa-image"></i> Upload Custom Image
                        <input type="file" id="custom-bg-upload" accept="image/*" style="display: none;">
                    </label>
                </div>
            </div>

            <!-- 5. Toggles -->
            <div class="drawer-section">
                <h4 class="drawer-section-title"><i data-lucide="play" style="width:14px;height:14px;vertical-align:middle;margin-right:0.25rem;"></i> 5. Performance Options</h4>
                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: var(--border-radius-sm); border: 1px solid var(--theme-border);">
                    <span style="font-size: 0.88rem; font-weight: 500;">Enable Animations</span>
                    <label class="switch-container">
                        <input type="checkbox" id="performance-toggle" checked>
                        <span class="switch-slider"></span>
                    </label>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 2rem; margin-bottom: 2rem;">
                <button class="btn btn-danger" id="custom-bg-reset" style="width: 100%; font-size: 0.85rem;"><i data-lucide="rotate-ccw" style="width:16px;height:16px;vertical-align:middle;margin-right:0.25rem;"></i> Reset Style</button>
            </div>

        </div>
    </div>

    <!-- Main JavaScript Core Asset -->
    <script src="<?php echo $path_prefix; ?>assets/js/main.js"></script>
    
    <!-- Fade in page load GSAP utility -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // GSAP page entrance reveal
            gsap.from("body", {
                opacity: 0,
                duration: 0.8,
                ease: "power2.out"
            });

            // Initialize Lucide Icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Re-render slider displays on load
            const blurInput = document.getElementById('custom-bg-blur');
            const opacityInput = document.getElementById('custom-bg-opacity');
            const speedInput = document.getElementById('custom-bg-speed');
            
            if (blurInput) {
                blurInput.value = localStorage.getItem('bg-blur') || '15';
                document.getElementById('blur-val').textContent = blurInput.value + 'px';
            }
            if (opacityInput) {
                opacityInput.value = localStorage.getItem('bg-opacity') || '100';
                document.getElementById('opacity-val').textContent = opacityInput.value + '%';
            }
            if (speedInput) {
                speedInput.value = localStorage.getItem('bg-speed') || '1.0';
                document.getElementById('speed-val').textContent = speedInput.value + 'x';
            }
        });
    </script>
</body>
</html>

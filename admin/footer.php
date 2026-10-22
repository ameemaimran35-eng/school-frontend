<?php
/**
 * admin/footer.php
 * Reusable footer for admin pages.
 * Includes closing tags, shared JavaScript for theme toggle persistence and sidebar handling.
 */
?>
    </main>
</div>

    <!-- Footer Scripts -->
    <script>
        // Theme toggle persistence
        const body = document.body;
        const html = document.documentElement;
        const toggle = document.getElementById('themeToggle');

        function applyTheme(isLight) {
            if (isLight) {
                body.classList.add('light-mode');
                html.classList.add('light-mode');
            } else {
                body.classList.remove('light-mode');
                html.classList.remove('light-mode');
            }
        }

        // Apply persisted theme on load
        try {
            var savedTheme = localStorage.getItem('adminTheme');
            if (savedTheme === 'light') {
                applyTheme(true);
            } else if (savedTheme === 'dark') {
                applyTheme(false);
            }
        } catch(e) {}

        if (toggle) {
            toggle.addEventListener('click', function () {
                var nextIsLight = !body.classList.contains('light-mode');
                applyTheme(nextIsLight);
                try {
                    localStorage.setItem('adminTheme', nextIsLight ? 'light' : 'dark');
                } catch(e) {}
            });
        }
        // Sidebar handling (mobile)
        (function () {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var toggleBtn = document.getElementById('mobileToggle');
            function openSidebar() { sidebar.classList.add('is-open'); overlay.classList.add('is-open'); }
            function closeSidebar() { sidebar.classList.remove('is-open'); overlay.classList.remove('is-open'); }
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
                });
            }
            if (overlay) overlay.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSidebar(); });
        })();

        // ── Preserve sidebar scroll position between page navigations ──
        (function () {
            var sidebarNav = document.querySelector('.sidebar-nav');
            if (!sidebarNav) return;
            // Restore saved position immediately
            try {
                var savedPos = sessionStorage.getItem('sidebarScrollPos');
                if (savedPos !== null) {
                    sidebarNav.scrollTop = parseInt(savedPos, 10);
                }
            } catch(e) {}
            // Save position before any link click
            var links = document.querySelectorAll('.sidebar-nav__link');
            links.forEach(function (link) {
                link.addEventListener('click', function () {
                    try {
                        sessionStorage.setItem('sidebarScrollPos', sidebarNav.scrollTop);
                    } catch(e) {}
                });
            });
        })();
    </script>
</body>
</html>

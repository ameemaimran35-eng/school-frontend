<?php
/**
 * accountant/footer.php
 * Reusable footer for accountant pages — includes closing tags and shared JS.
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
            if (isLight) { body.classList.add('light-mode'); html.classList.add('light-mode'); }
            else { body.classList.remove('light-mode'); html.classList.remove('light-mode'); }
        }
        try {
            var savedTheme = localStorage.getItem('adminTheme');
            if (savedTheme === 'light') applyTheme(true);
            else if (savedTheme === 'dark') applyTheme(false);
        } catch(e) {}

        if (toggle) {
            toggle.addEventListener('click', function () {
                var nextIsLight = !body.classList.contains('light-mode');
                applyTheme(nextIsLight);
                try { localStorage.setItem('adminTheme', nextIsLight ? 'light' : 'dark'); } catch(e) {}
            });
        }

        // Sidebar mobile toggle
        (function () {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var toggleBtn = document.getElementById('mobileToggle');
            function openSidebar()  { sidebar.classList.add('is-open');    overlay.classList.add('is-open'); }
            function closeSidebar() { sidebar.classList.remove('is-open'); overlay.classList.remove('is-open'); }
            if (toggleBtn) toggleBtn.addEventListener('click', function () {
                sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
            });
            if (overlay) overlay.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSidebar(); });
        })();

        // Sidebar scroll position
        (function () {
            var sidebarNav = document.querySelector('.sidebar-nav');
            if (!sidebarNav) return;
            try { var pos = sessionStorage.getItem('accSidebarScroll'); if (pos) sidebarNav.scrollTop = parseInt(pos, 10); } catch(e) {}
            document.querySelectorAll('.sidebar-nav__link').forEach(function (link) {
                link.addEventListener('click', function () {
                    try { sessionStorage.setItem('accSidebarScroll', sidebarNav.scrollTop); } catch(e) {}
                });
            });
        })();

        // Auto-dismiss flash messages after 5 seconds
        (function () {
            var alerts = document.querySelectorAll('.flash-auto-dismiss');
            alerts.forEach(function(a) {
                setTimeout(function() {
                    a.style.transition = 'opacity .5s';
                    a.style.opacity = '0';
                    setTimeout(function(){ a.remove(); }, 500);
                }, 5000);
            });
        })();
    </script>
</body>
</html>

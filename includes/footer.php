        </main>
    </div>
</div>

<script>
(function () {
    'use strict';

    var sidebar        = document.getElementById('sidebar');
    var backdrop       = document.getElementById('sidebarBackdrop');
    var menuToggle     = document.getElementById('menuToggle');
    var sidebarClose   = document.getElementById('sidebarClose');
    var body           = document.body;

    if (!sidebar) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | OPEN
    |--------------------------------------------------------------------------
    */

    function openSidebar() {
        sidebar.classList.add('open');

        if (backdrop) {
            backdrop.classList.add('show');
        }

        body.classList.add('sidebar-open');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'true');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE
    |--------------------------------------------------------------------------
    */

    function closeSidebar() {
        sidebar.classList.remove('open');

        if (backdrop) {
            backdrop.classList.remove('show');
        }

        body.classList.remove('sidebar-open');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE
    |--------------------------------------------------------------------------
    */

    function toggleSidebar() {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | WIRE UP EVENTS
    |--------------------------------------------------------------------------
    */

    if (menuToggle) {
        menuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            toggleSidebar();
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', function (e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            closeSidebar();
        });
    }

    /*
     * Close the drawer when a nav link is tapped.
     * Uses event delegation so it works even if you add links later.
     */

    sidebar.addEventListener('click', function (e) {
        var link = e.target.closest('a');

        if (!link) {
            return;
        }

        // Ignore links that are just anchors on the same page
        var href = link.getAttribute('href') || '';

        if (href === '' || href.charAt(0) === '#') {
            return;
        }

        closeSidebar();
    });

    /*
     * Escape key closes the drawer
     */

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    /*
     * If the window is resized past the mobile breakpoint,
     * reset the drawer state so layout doesn't get stuck.
     */

    var lastIsMobile = window.matchMedia('(max-width: 767.98px)').matches;

    window.addEventListener('resize', function () {
        var isMobile = window.matchMedia('(max-width: 767.98px)').matches;

        if (lastIsMobile && !isMobile) {
            // Switched from mobile → desktop
            closeSidebar();
        }

        lastIsMobile = isMobile;
    });


    /*
    |--------------------------------------------------------------------------
    | HIGHLIGHT CURRENT PAGE IN NAV
    |--------------------------------------------------------------------------
    */

    var currentFile = window.location.pathname.split('/').pop() || 'index.php';

    // Treat a few aliases as equivalent
    var aliases = {
        '': 'index.php',
        'lead.php': 'leads.php',
        'import_google_leads.php': 'search.php'
    };

    if (aliases[currentFile]) {
        currentFile = aliases[currentFile];
    }

    var links = sidebar.querySelectorAll('a[href]');

    for (var i = 0; i < links.length; i++) {
        var href = links[i].getAttribute('href');

        if (!href || href.charAt(0) === '#') {
            continue;
        }

        // Strip query strings and fragments for comparison
        var target = href.split('?')[0].split('#')[0].split('/').pop();

        if (target === currentFile) {
            links[i].classList.add('active');
            links[i].setAttribute('aria-current', 'page');
        }
    }

})();
</script>

</body>
</html>
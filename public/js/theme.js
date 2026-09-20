(function () {
    const storageKey = 'jadidaring-theme';

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function syncButtons(theme) {
        const next = theme === 'dark' ? 'light' : 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            button.setAttribute('aria-label', next === 'dark' ? 'Switch to dark mode' : 'Switch to light mode');
            button.setAttribute('title', button.getAttribute('aria-label'));
        });
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.colorScheme = theme;
        syncButtons(theme);
    }

    window.__setTheme = function (theme) {
        const next = theme === 'dark' ? 'dark' : 'light';
        try {
            localStorage.setItem(storageKey, next);
        } catch (error) {
            // Ignore private-mode storage failures.
        }
        applyTheme(next);
    };

    window.__toggleTheme = function () {
        window.__setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    };

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-theme-toggle]');
        if (toggle) {
            window.__toggleTheme();
        }

        const navToggle = event.target.closest('[data-nav-toggle]');
        if (navToggle) {
            const header = navToggle.closest('.app-header');
            const open = header?.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    });

    applyTheme(currentTheme());
})();

<script>
    (function () {
        try {
            var stored = localStorage.getItem('jadidaring-theme');
            var theme = (stored === 'light' || stored === 'dark')
                ? stored
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>

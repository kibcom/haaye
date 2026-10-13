<script>
(function () {
    var theme = 'system';
    var sidebar = 'expanded';
    try {
        theme = localStorage.getItem('workflowpro_theme') || 'system';
        sidebar = localStorage.getItem('workflowpro_sidebar') || 'expanded';
    } catch (error) {}
    if (theme !== 'light' && theme !== 'dark' && theme !== 'system') {
        theme = 'system';
    }
    if (sidebar !== 'collapsed') {
        sidebar = 'expanded';
    }
    var resolved = theme === 'system'
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : theme;
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.setAttribute('data-resolved', resolved);
    document.documentElement.setAttribute('data-bs-theme', resolved);
    document.documentElement.setAttribute('data-sidebar', sidebar);
})();
</script>

<script>
(function () {
    var key = 'psg-appearance';
    var stored = localStorage.getItem(key);
    var dark = stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches);

    if (dark) {
        document.documentElement.classList.add('dark');
        document.documentElement.dataset.theme = 'dark';
        document.documentElement.style.colorScheme = 'dark';
    } else {
        document.documentElement.dataset.theme = 'light';
        document.documentElement.style.colorScheme = 'light';
    }
})();
</script>

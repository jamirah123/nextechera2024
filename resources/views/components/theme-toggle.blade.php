<div
    x-data="{
        mode: localStorage.getItem('psg-appearance') || 'system',
        resolved: document.documentElement.dataset.theme || 'light',
        sync() {
            this.mode = localStorage.getItem('psg-appearance') || 'system';
            this.resolved = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        },
        toggle() {
            const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
            localStorage.setItem('psg-appearance', next);
            document.documentElement.classList.toggle('dark', next === 'dark');
            document.documentElement.dataset.theme = next;
            document.documentElement.style.colorScheme = next;
            this.sync();
        },
    }"
    x-init="sync()"
    class="relative"
>
    <button
        type="button"
        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900"
        @click="toggle()"
        :aria-label="resolved === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
        :title="resolved === 'dark' ? 'Light mode' : 'Dark mode'"
    >
        <x-icon x-show="resolved === 'dark'" x-cloak name="sun" class="h-4 w-4" />
        <x-icon x-show="resolved !== 'dark'" name="moon" class="h-4 w-4" />
    </button>
</div>

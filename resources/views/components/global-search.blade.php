@props([])

@php
    $searchUrl = route('search');
@endphp

<div
    class="relative mx-auto w-full max-w-xl"
    x-data="globalSearch(@js($searchUrl))"
    x-on:keydown.escape.window="close()"
    x-on:click.outside="close()"
>
    <div class="relative flex items-center">
        <input
            id="global-search"
            type="search"
            x-model="query"
            x-ref="input"
            x-on:focus="open = query.length >= 2"
            x-on:input.debounce.300ms="run()"
            x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="go()"
            placeholder="Search invoices, payroll, guards, sites…"
            autocomplete="off"
            aria-label="Search"
            class="h-8 w-full rounded-lg border border-slate-200 bg-white py-1.5 pl-3 pr-8 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 transition hover:bg-slate-50 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:hover:bg-slate-800 dark:focus:bg-slate-800"
        >
        <button
            type="button"
            class="absolute inset-y-0 right-0 z-10 flex items-center pr-3 text-slate-400 hover:text-slate-600"
            x-show="query.length"
            x-cloak
            x-on:click="clear()"
            aria-label="Clear search"
        >
            <x-icon name="close" class="h-3.5 w-3.5" />
        </button>
    </div>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top
        class="absolute left-0 right-0 z-50 mt-1 max-h-[20rem] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800"
    >
        <div class="border-b border-slate-100 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:border-slate-700">
            <span x-show="loading">Searching…</span>
            <span x-show="!loading && query.length < 2">Type at least 2 characters</span>
            <span x-show="!loading && query.length >= 2" x-text="results.length ? (results.length + ' result' + (results.length === 1 ? '' : 's')) : 'No matches'"></span>
        </div>

        <ul class="max-h-[20rem] overflow-y-auto py-1" role="listbox">
            <template x-for="(item, index) in results" :key="item.type + '-' + item.url">
                <li>
                    <a
                        :href="item.url"
                        class="flex items-start gap-3 px-3 py-2.5 transition"
                        :class="index === active ? 'bg-brand-50 dark:bg-brand-950/50' : 'hover:bg-slate-50 dark:hover:bg-slate-700'"
                        x-on:mouseenter="active = index"
                        role="option"
                    >
                        <span class="mt-0.5 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600" x-text="item.label"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-slate-900" x-text="item.title"></span>
                            <span class="block truncate text-xs text-slate-500" x-text="item.subtitle"></span>
                        </span>
                        <span class="shrink-0 text-[10px] font-semibold text-slate-400" x-text="item.badge"></span>
                    </a>
                </li>
            </template>
        </ul>

        <div class="border-t border-slate-100 px-3 py-2 text-[11px] text-slate-400" x-show="!loading && query.length >= 2 && !results.length">
            Try a site code, client name, region, or supervisor.
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('globalSearch', (url) => ({
            url,
            query: '',
            results: [],
            open: false,
            loading: false,
            active: -1,
            controller: null,

            async run() {
                const q = this.query.trim();
                this.active = -1;

                if (q.length < 2) {
                    this.results = [];
                    this.open = q.length > 0;
                    this.loading = false;
                    return;
                }

                this.loading = true;
                this.open = true;

                if (this.controller) {
                    this.controller.abort();
                }
                this.controller = new AbortController();

                try {
                    const response = await fetch(`${this.url}?q=${encodeURIComponent(q)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: this.controller.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Search failed');
                    }

                    const data = await response.json();
                    this.results = data.results || [];
                    this.active = this.results.length ? 0 : -1;
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        this.results = [];
                    }
                } finally {
                    this.loading = false;
                }
            },

            move(step) {
                if (!this.results.length) return;
                const next = this.active + step;
                this.active = (next + this.results.length) % this.results.length;
            },

            go() {
                if (this.active >= 0 && this.results[this.active]) {
                    window.location.href = this.results[this.active].url;
                }
            },

            clear() {
                this.query = '';
                this.results = [];
                this.open = false;
                this.active = -1;
                this.$refs.input?.focus();
            },

            close() {
                this.open = false;
            },
        }));
    });
</script>

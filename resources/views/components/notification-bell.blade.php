@props([])

@php
    $feedUrl = route('notifications.index');
    $readUrl = route('notifications.mark-read');
    $pollSeconds = (int) config('psg.notifications.poll_seconds', 30);
@endphp

<div
    class="relative"
    x-data="notificationBell(@js($feedUrl), @js($readUrl), @js($pollSeconds))"
    x-init="init()"
    @keydown.escape.window="close()"
    @click.outside="close()"
>
    <button
        type="button"
        class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900"
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
        aria-label="Notifications"
        :title="unreadCount ? (unreadCount + ' unread notification' + (unreadCount === 1 ? '' : 's')) : 'Notifications'"
    >
        <span class="relative inline-flex">
            <x-icon name="bell" class="h-4 w-4" />
            <span
                x-cloak
                x-show="unreadCount > 0"
                x-transition.scale.origin.top
                class="absolute -right-2.5 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-slate-900"
                :class="pulse ? 'animate-pulse' : ''"
                x-text="unreadCount > 99 ? '99+' : unreadCount"
            ></span>
        </span>
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800 sm:w-96"
        role="menu"
    >
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-slate-900">Notifications</p>
                <p class="text-xs text-slate-500" x-text="statusLabel"></p>
            </div>
            <button
                type="button"
                class="rounded-lg px-2 py-1 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40"
                x-show="unreadCount > 0"
                :disabled="markingRead"
                @click="markAllRead()"
            >
                Mark all read
            </button>
        </div>

        <div class="max-h-[24rem] overflow-y-auto" x-show="loading && !items.length">
            <p class="px-4 py-8 text-center text-sm text-slate-500">Loading notifications…</p>
        </div>

        <ul class="divide-y divide-slate-100" x-show="items.length" role="list">
            <template x-for="item in items" :key="item.id">
                <li>
                    <a
                        :href="item.url || '#'"
                        class="block px-4 py-3 transition"
                        :class="[
                            item.is_unread ? 'bg-brand-50/60 hover:bg-brand-50' : 'hover:bg-slate-50',
                            !item.url ? 'cursor-default' : '',
                        ]"
                        @click="!item.url && $event.preventDefault()"
                        role="menuitem"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                                :class="item.is_unread ? 'bg-brand-600' : 'bg-transparent'"
                            ></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="item.category_badge_class"
                                        x-text="item.category"
                                    ></span>
                                    <span
                                        x-show="item.is_override"
                                        class="rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700"
                                    >
                                        Override
                                    </span>
                                </span>
                                <span class="mt-1 block text-sm font-medium text-slate-900" x-text="item.summary"></span>
                                <span class="mt-1 block text-xs text-slate-500">
                                    <span x-text="item.actor"></span>
                                    <span aria-hidden="true"> · </span>
                                    <span x-text="item.time_ago"></span>
                                </span>
                            </span>
                        </div>
                    </a>
                </li>
            </template>
        </ul>

        <div class="border-t border-slate-100 px-4 py-6 text-center text-sm text-slate-500" x-show="!loading && !items.length">
            You're all caught up.
        </div>

        <div class="border-t border-slate-100 px-4 py-2 text-center" x-show="auditUrl">
            <a
                :href="auditUrl"
                class="text-xs font-semibold text-brand-700 hover:text-brand-800"
                @click="close()"
            >
                View full audit log
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('notificationBell', (feedUrl, readUrl, pollSeconds) => ({
            feedUrl,
            readUrl,
            pollSeconds,
            open: false,
            loading: false,
            markingRead: false,
            items: [],
            unreadCount: 0,
            auditUrl: null,
            pollTimer: null,
            controller: null,
            pulse: false,
            lastUnreadCount: 0,

            get statusLabel() {
                if (this.loading && !this.items.length) {
                    return 'Checking for updates…';
                }

                if (this.unreadCount > 0) {
                    return `${this.unreadCount} unread`;
                }

                return 'Up to date';
            },

            init() {
                this.fetchFeed();

                this.pollTimer = window.setInterval(() => {
                    if (document.hidden) {
                        return;
                    }

                    this.fetchFeed();
                }, this.pollSeconds * 1000);

                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        this.fetchFeed();
                    }
                });
            },

            async fetchFeed() {
                if (this.controller) {
                    this.controller.abort();
                }

                this.controller = new AbortController();
                this.loading = true;

                try {
                    const response = await fetch(this.feedUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: this.controller.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Notification feed failed');
                    }

                    const data = await response.json();
                    this.items = data.notifications || [];
                    this.auditUrl = data.audit_url || null;

                    const nextCount = data.unread_count || 0;
                    this.pulse = nextCount > this.lastUnreadCount;
                    this.unreadCount = nextCount;
                    this.lastUnreadCount = nextCount;

                    if (this.pulse) {
                        window.setTimeout(() => {
                            this.pulse = false;
                        }, 3000);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        // Keep the last known state on transient failures.
                    }
                } finally {
                    this.loading = false;
                }
            },

            toggle() {
                this.open = !this.open;

                if (this.open) {
                    this.fetchFeed();
                }
            },

            close() {
                this.open = false;
            },

            async markAllRead() {
                if (this.markingRead || this.unreadCount === 0) {
                    return;
                }

                this.markingRead = true;

                try {
                    const response = await fetch(this.readUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Mark read failed');
                    }

                    this.unreadCount = 0;
                    this.lastUnreadCount = 0;
                    this.items = this.items.map((item) => ({
                        ...item,
                        is_unread: false,
                    }));
                } catch (error) {
                    // Ignore — badge will refresh on next poll.
                } finally {
                    this.markingRead = false;
                }
            },
        }));
    });
</script>

@props([])

@php
    $feedUrl = route('notifications.index');
    $readUrl = route('notifications.mark-read');
    $historyUrl = route('notifications.index');
    $pollSeconds = (int) config('psg.notifications.poll_seconds', 45);
@endphp

<div
    class="relative"
    x-data="notificationBell(@js($feedUrl), @js($readUrl), @js($historyUrl), @js($pollSeconds))"
    x-init="init()"
    @keydown.escape.window="close()"
>
    <button
        type="button"
        class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900"
        @click="toggle()"
        @keydown.escape.prevent="close()"
        :aria-expanded="open.toString()"
        aria-haspopup="dialog"
        aria-controls="notification-panel"
        aria-label="Notifications"
        :title="unreadLabel()"
    >
        <span class="relative inline-flex">
            <x-icon name="bell" class="h-4 w-4" />
            <span
                x-cloak
                x-show="unreadCount > 0"
                class="absolute -right-2.5 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-slate-900"
                :class="badgeTone === 'attention' ? 'bg-amber-600' : 'bg-brand-700'"
                x-text="badgeLabel()"
                aria-hidden="true"
            ></span>
        </span>
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        @click.outside="close()"
        id="notification-panel"
        x-ref="panel"
        class="notification-panel absolute right-0 z-50 mt-2 flex w-96 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
        role="dialog"
        aria-label="Notifications"
    >
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <div>
                <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">Notifications</p>
                <p class="text-[10px] text-slate-500" x-text="unreadLabel()"></p>
            </div>
            <button
                type="button"
                class="text-[11px] font-semibold text-brand-700 hover:text-brand-800 disabled:opacity-40"
                x-show="unreadCount > 0"
                :disabled="markingRead"
                @click="markAllRead()"
            >
                Mark all as read
            </button>
        </div>

        <div class="flex gap-1 border-b border-slate-100 px-3 py-2 dark:border-slate-800">
            <template x-for="tab in tabs" :key="tab.id">
                <button
                    type="button"
                    class="rounded-full px-2.5 py-1 text-[11px] font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500"
                    :class="panel === tab.id ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    data-panel-tab
                    @click="setPanel(tab.id)"
                    x-text="tab.label"
                ></button>
            </template>
        </div>

        <div class="px-4 py-8 text-center text-sm text-slate-500" x-show="loading && !items.length">
            Loading notifications...
        </div>

        <div class="px-4 py-8 text-center" x-show="failed && !items.length">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Notifications temporarily unavailable</p>
            <p class="mt-1 text-xs text-slate-500">Please try again.</p>
            <button type="button" class="btn btn-secondary mt-3" @click="fetchFeed()">Retry</button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto" x-show="items.length">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="item in items" :key="item.id">
                    <li class="px-3 py-2.5" :class="item.is_unread ? 'bg-slate-50 dark:bg-slate-800/70' : ''">
                        <div class="flex items-start gap-2.5">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" :title="item.group">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(item.icon)" /></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="item.category_badge_class">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="!item.is_unread ? 'bg-transparent' : (item.priority === 'urgent' ? 'bg-rose-600' : (item.priority === 'important' ? 'bg-amber-600' : 'bg-brand-600'))"></span>
                                        <span x-text="item.group"></span>
                                    </span>
                                    <span class="shrink-0 text-[10px] text-slate-400" x-text="item.time_ago"></span>
                                </div>
                                <p class="mt-1 text-xs text-slate-900 dark:text-slate-100" :class="item.is_unread ? 'font-semibold' : 'font-medium text-slate-600'" x-text="item.title"></p>
                                <p class="mt-0.5 text-[11px] leading-4 text-slate-500" x-show="item.body" x-text="item.body"></p>
                                <p class="mt-0.5 text-[10px] font-medium text-slate-400" x-show="item.reference" x-text="item.reference"></p>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <a
                                        x-show="item.url"
                                        :href="item.url"
                                        class="text-[11px] font-semibold text-brand-700 hover:text-brand-800"
                                        @click="openItem(item)"
                                        x-text="(item.action_label || 'Open') + ' →'"
                                    ></a>
                                    <span class="flex items-center gap-2">
                                        <button type="button" class="text-[11px] font-semibold text-slate-400 hover:text-slate-700" @click="setState(item, item.is_unread ? 'read' : 'unread')" x-text="item.is_unread ? 'Mark read' : 'Mark unread'"></button>
                                        <button type="button" class="text-[11px] font-semibold text-slate-400 hover:text-rose-700" @click="setState(item, 'dismiss')">Dismiss</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <div class="px-4 py-10 text-center" x-show="!loading && !failed && !items.length">
            <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                <x-icon name="bell" class="h-4 w-4" />
            </div>
            <p class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">You're all caught up</p>
            <p class="mt-0.5 text-xs text-slate-500">No new notifications at the moment.</p>
        </div>

        <a :href="historyUrl" class="border-t border-slate-100 px-4 py-2.5 text-center text-xs font-semibold text-brand-700 hover:bg-slate-50 dark:border-slate-800" @click="close()">
            View all notifications
        </a>
    </div>

    <div class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="pointer-events-auto rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-lg dark:border-slate-700 dark:bg-slate-900" role="status">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-900 dark:text-slate-100" x-text="toast.title"></p>
                        <p class="mt-0.5 text-[11px] text-slate-500" x-show="toast.body" x-text="toast.body"></p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="dismissToast(toast.id)" aria-label="Dismiss">×</button>
                </div>
            </div>
        </template>
    </div>
</div>

<style>
    .notification-panel { max-height: min(24rem, calc(100vh - 5rem)); max-width: calc(100vw - 1.5rem); }
    @media (max-width: 639px) {
        .notification-panel { position: fixed; left: 0.75rem; right: 0.75rem; top: 4rem; width: auto; margin-top: 0; }
    }
</style>
<style>
    .notification-panel {
        max-height: min(24rem, calc(100vh - 5rem));
        max-width: calc(100vw - 1.5rem);
    }
    @media (max-width: 639px) {
        .notification-panel {
            position: fixed;
            left: 0.75rem;
            right: 0.75rem;
            top: 4rem;
            width: auto;
            margin-top: 0;
        }
    }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        if (Alpine.store && window.__psgNotificationBell) {
            return;
        }
        window.__psgNotificationBell = true;

        Alpine.data('notificationBell', (feedUrl, readUrl, historyUrl, pollSeconds) => ({
            feedUrl,
            readUrl,
            historyUrl,
            pollSeconds,
            open: false,
            loading: false,
            failed: false,
            markingRead: false,
            items: [],
            unreadCount: 0,
            badgeTone: 'normal',
            panel: 'all',
            tabs: [
                { id: 'all', label: 'All' },
                { id: 'unread', label: 'Unread' },
                { id: 'important', label: 'Important' },
            ],
            pollTimer: null,
            controller: null,
            lastFetchedAt: 0,
            seenIds: [],
            booted: false,
            toasts: [],
            toastPreferences: true,

            init() {
                const start = () => {
                    this.fetchFeed();
                    this.pollTimer = window.setInterval(() => {
                        if (document.hidden) {
                            return;
                        }
                        this.fetchFeed();
                    }, Math.max(this.pollSeconds, 15) * 1000);
                };

                if (typeof window.requestIdleCallback === 'function') {
                    window.requestIdleCallback(start, { timeout: 1200 });
                } else {
                    window.setTimeout(start, 600);
                }

                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden && Date.now() - this.lastFetchedAt > 10000) {
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
                this.failed = false;

                try {
                    const url = new URL(this.feedUrl, window.location.origin);
                    if (this.panel !== 'all') {
                        url.searchParams.set('panel', this.panel);
                    }

                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: this.controller.signal,
                    });

                    if (!response.ok) {
                        throw new Error('unavailable');
                    }

                    const data = await response.json();
                    const nextItems = data.notifications || [];
                    this.unreadCount = data.unread_count || 0;
                    this.badgeTone = data.badge_tone || 'normal';
                    this.toastPreferences = data.toasts !== false;
                    this.items = nextItems;
                    this.lastFetchedAt = Date.now();
                    this.noticeNew(nextItems);
                    this.booted = true;
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        this.failed = this.items.length === 0;
                    }
                } finally {
                    this.loading = false;
                }
            },

            noticeNew(items) {
                const ids = items.map((item) => item.id);
                if (!this.booted || !this.toastPreferences) {
                    this.seenIds = ids;
                    return;
                }

                items
                    .filter((item) => !this.seenIds.includes(item.id) && (item.priority === 'important' || item.priority === 'urgent'))
                    .slice(0, 2)
                    .forEach((item) => this.pushToast(item));

                this.seenIds = ids;
            },

            pushToast(item) {
                const toast = { id: item.id + '-' + Date.now(), title: item.title, body: item.body };
                this.toasts.push(toast);
                window.setTimeout(() => this.dismissToast(toast.id), 4000);
            },

            dismissToast(id) {
                this.toasts = this.toasts.filter((toast) => toast.id !== id);
            },

            setPanel(panel) {
                this.panel = panel;
                this.fetchFeed();
            },

            badgeLabel() {
                if (this.unreadCount > 99) {
                    return '99+';
                }

                return String(this.unreadCount);
            },

            unreadLabel() {
                if (this.unreadCount === 0) {
                    return 'Notifications';
                }

                const count = this.unreadCount > 99 ? '99+' : String(this.unreadCount);

                return count + ' unread';
            },

            iconPath(name) {
                const paths = {
                    swap: 'M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4',
                    alert: 'M12 9v4m0 4h.01M10.3 4.1L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 4.1a2 2 0 00-3.4 0z',
                    leave: 'M8 7V3m8 4V3M4 11h16M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z',
                    payroll: 'M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8V6m0 12v-2M4 7a8 8 0 1016 0 8 8 0 00-16 0z',
                    invoice: 'M9 7h6M9 11h6M9 15h4M7 3h8l4 4v14a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z',
                    shield: 'M12 3l8 3v6c0 5-3.4 8.4-8 9.5C7.4 20.4 4 17 4 12V6l8-3z',
                    map: 'M9 20l-5-2V5l5 2 6-2 5 2v13l-5-2-6 2zM9 7v13M15 5v13',
                };

                return paths[name] || paths.shield;
            },

            toggle() {
                this.open = !this.open;
                if (this.open && Date.now() - this.lastFetchedAt > 10000) {
                    this.fetchFeed();
                }
                if (this.open) {
                    this.$nextTick(() => this.$refs.panel?.querySelector('[data-panel-tab]')?.focus());
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
                        headers: this.jsonHeaders(),
                    });
                    if (!response.ok) {
                        throw new Error('unavailable');
                    }
                    this.unreadCount = 0;
                    this.badgeTone = 'normal';
                    this.items = this.items.map((item) => ({ ...item, is_unread: false }));
                    if (this.panel === 'unread') {
                        this.items = [];
                    }
                } catch (error) {
                    this.failed = true;
                } finally {
                    this.markingRead = false;
                }
            },

            async openItem(item) {
                if (!item.requires_ack && item.is_unread) {
                    await this.setState(item, 'read');
                }
                this.close();
            },

            async setState(item, action) {
                try {
                    const response = await fetch(item.state_url, {
                        method: 'POST',
                        headers: this.jsonHeaders(),
                        body: JSON.stringify({ action }),
                    });
                    if (!response.ok) {
                        throw new Error('unavailable');
                    }
                    const data = await response.json();
                    this.unreadCount = data.unread_count || 0;
                    if (action === 'dismiss') {
                        this.items = this.items.filter((row) => row.id !== item.id);
                    } else {
                        this.items = this.items.map((row) => row.id === item.id ? { ...row, is_unread: action === 'unread' } : row);
                    }
                } catch (error) {
                    this.failed = true;
                }
            },

            jsonHeaders() {
                return {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                };
            },
        }));
    });
</script>

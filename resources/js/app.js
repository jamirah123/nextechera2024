import Alpine from 'alpinejs';
import './searchable-selects';
import { initAppearance } from './theme';
import { registerDashboardCharts } from './charts';
import { initAutoHideScrollbars } from './scrollbars';
import { watchPhoneTables } from './phone-tables';

window.Alpine = Alpine;

initAppearance();
registerDashboardCharts();
initAutoHideScrollbars();
watchPhoneTables();

const SIDEBAR_COLLAPSED_KEY = 'psg.sidebar.collapsed';

Alpine.store('sidebar', {
    collapsed: (() => {
        try {
            return window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
        } catch {
            return false;
        }
    })(),
    toggle() {
        this.collapsed = ! this.collapsed;
        try {
            window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, this.collapsed ? '1' : '0');
        } catch {
            // Ignore private-mode storage failures.
        }
    },
    expand() {
        if (this.collapsed) {
            this.collapsed = false;
            try {
                window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, '0');
            } catch {
                // Ignore.
            }
        }
    },
});

Alpine.data('sidebarNav', (config = {}) => ({
    query: '',
    accountOpen: false,
    activeGroups: Array.isArray(config.activeGroups) ? config.activeGroups : [],
    haystacks: Array.isArray(config.haystacks) ? config.haystacks : [],
    groupState: {},
    storageKey: config.storageKey || 'psg.sidebar.groups',
    badges: config.badges && typeof config.badges === 'object' ? { ...config.badges } : {},
    pollUrl: config.pollUrl || null,
    pollSeconds: Number(config.pollSeconds) > 0 ? Number(config.pollSeconds) : 30,
    pollTimer: null,
    badgeController: null,

    init() {
        try {
            this.groupState = JSON.parse(window.localStorage.getItem(this.storageKey) || '{}') || {};
        } catch {
            this.groupState = {};
        }

        this.activeGroups.forEach((key) => {
            if (this.groupState[key] === undefined) {
                this.groupState[key] = true;
            }
        });

        if (! this.pollUrl) {
            return;
        }

        const startBadges = () => {
            this.fetchBadges();
            this.pollTimer = window.setInterval(() => {
                if (document.hidden) {
                    return;
                }

                this.fetchBadges();
            }, this.pollSeconds * 1000);
        };

        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(startBadges, { timeout: 1200 });
        } else {
            window.setTimeout(startBadges, 600);
        }

        document.addEventListener('visibilitychange', () => {
            if (! document.hidden) {
                this.fetchBadges();
            }
        });
    },

    badgeCount(href) {
        const count = Number(this.badges?.[href] || 0);

        return Number.isFinite(count) && count > 0 ? count : 0;
    },

    badgeText(href) {
        const count = this.badgeCount(href);

        return count > 99 ? '99+' : String(count);
    },

    async fetchBadges() {
        if (! this.pollUrl) {
            return;
        }

        if (this.badgeController) {
            this.badgeController.abort();
        }

        this.badgeController = new AbortController();

        try {
            const response = await fetch(this.pollUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: this.badgeController.signal,
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            this.badges = data.badges || {};
        } catch (error) {
            if (error.name !== 'AbortError') {
                // Keep the last counts if a poll fails.
            }
        }
    },

    isGroupOpen(key) {
        if (this.groupState[key] !== undefined) {
            return !! this.groupState[key];
        }

        // First visit: keep sections expanded; active groups stay open after toggles.
        return true;
    },

    toggleGroup(key, open) {
        this.groupState[key] = open;
        try {
            window.localStorage.setItem(this.storageKey, JSON.stringify(this.groupState));
        } catch {
            // Ignore.
        }
    },

    itemMatches(haystack) {
        const q = this.query.trim().toLowerCase();
        if (! q) {
            return true;
        }

        return String(haystack || '').includes(q);
    },

    groupVisible(items) {
        const q = this.query.trim().toLowerCase();
        if (! q) {
            return true;
        }

        return (items || []).some((item) => String(item.haystack || item.label || '').includes(q));
    },

    anyVisible() {
        const q = this.query.trim().toLowerCase();
        if (! q) {
            return true;
        }

        return (this.haystacks || []).some((haystack) => String(haystack).includes(q));
    },
}));

/**
 * Show a clear busy state on POST/PUT/PATCH/DELETE forms so users get feedback
 * during saves, exports, restores, and other slower actions.
 */
function initFormBusyState() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get') {
            return;
        }

        if (form.dataset.noBusy === 'true' || form.id === 'idle-logout-form') {
            return;
        }

        if (form.dataset.busy === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.busy = 'true';
        document.documentElement.classList.add('psg-busy');

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((el) => {
            el.setAttribute('aria-busy', 'true');
            el.setAttribute('disabled', 'disabled');
            if (el instanceof HTMLButtonElement && !el.dataset.originalLabel) {
                el.dataset.originalLabel = el.innerHTML;
                el.innerHTML = '<span class="inline-flex items-center gap-2"><span class="psg-spinner" aria-hidden="true"></span> Working…</span>';
            }
        });
    }, true);
}

initFormBusyState();

Alpine.data('idleSession', (config = {}) => ({
    idleMinutes: Math.max(1, Number(config.idleMinutes || 30)),
    warningMinutes: Math.max(0, Number(config.warningMinutes || 2)),
    lastActiveAt: Date.now(),
    warningVisible: false,
    remainingLabel: '',
    loggingOut: false,
    timer: null,

    start() {
        const bump = () => this.poke();
        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach((eventName) => {
            window.addEventListener(eventName, bump, { passive: true });
        });
        this.timer = window.setInterval(() => this.tick(), 1000);
        this.poke();
    },

    poke() {
        this.lastActiveAt = Date.now();
        this.warningVisible = false;
    },

    tick() {
        if (this.loggingOut) {
            return;
        }

        const idleMs = Date.now() - this.lastActiveAt;
        const idleLimitMs = this.idleMinutes * 60 * 1000;
        const warningMs = this.warningMinutes * 60 * 1000;
        const remainingMs = idleLimitMs - idleMs;

        if (remainingMs <= 0) {
            this.logout();
            return;
        }

        if (warningMs > 0 && remainingMs <= warningMs) {
            this.warningVisible = true;
            const secs = Math.max(1, Math.ceil(remainingMs / 1000));
            const mins = Math.floor(secs / 60);
            const rem = secs % 60;
            this.remainingLabel = mins > 0
                ? `${mins}m ${String(rem).padStart(2, '0')}s`
                : `${secs}s`;
        } else {
            this.warningVisible = false;
        }
    },

    logout() {
        this.loggingOut = true;
        const form = document.getElementById('idle-logout-form');
        if (form) {
            form.submit();
        }
    },
}));

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (! (form instanceof HTMLFormElement) || form.dataset.allowRepeat === 'true') {
        return;
    }

    window.setTimeout(() => {
        if (event.defaultPrevented) {
            return;
        }

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
            control.disabled = true;
        });
    }, 0);
});

Alpine.start();

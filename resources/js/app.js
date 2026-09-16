import Alpine from 'alpinejs';
import './searchable-selects';
import { initAppearance } from './theme';
import { registerDashboardCharts } from './charts';

window.Alpine = Alpine;

initAppearance();
registerDashboardCharts();

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

Alpine.start();

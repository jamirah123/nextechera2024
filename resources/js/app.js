import Alpine from 'alpinejs';
import './searchable-selects';
import { initAppearance } from './theme';

window.Alpine = Alpine;

initAppearance();

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

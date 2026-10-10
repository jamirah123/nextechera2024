@once
    @php
        $toastItems = [];
        $status = session('status');
        $allowedTones = ['success', 'info', 'warning', 'error'];
        $requestedTone = session('toast_tone');

        if (is_string($status) && trim($status) !== '') {
            $tone = is_string($requestedTone) && in_array($requestedTone, $allowedTones, true)
                ? $requestedTone
                : 'success';

            if ($tone === 'success' && filled(session('deployment_errors'))) {
                $tone = 'warning';
            }

            $lower = strtolower($status);
            if ($tone === 'success' && (str_contains($lower, 'queued') || str_contains($lower, 'refresh this page'))) {
                $tone = 'info';
            }

            $toastItems[] = [
                'id' => 'flash-status',
                'tone' => $tone,
                'message' => $status,
            ];
        }

        $error = session('error');
        if (is_string($error) && trim($error) !== '') {
            $toastItems[] = [
                'id' => 'flash-error',
                'tone' => 'error',
                'message' => $error,
            ];
        }
    @endphp

    <div
        class="psg-toasts"
        x-data="psgToasts(@js($toastItems))"
        x-init="boot()"
        @psg-toast.window="push($event.detail)"
    >
        <template x-for="toast in items" :key="toast.id">
            <div
                class="psg-toast pointer-events-auto flex items-start gap-2.5 rounded-lg border border-slate-200 bg-white px-3 py-2.5 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                :class="toast.tone === 'error' ? 'border-rose-200 dark:border-rose-900/60' : ''"
                :data-toast-tone="toast.tone"
                :role="toast.tone === 'error' ? 'alert' : 'status'"
                :aria-live="toast.tone === 'error' ? 'assertive' : 'polite'"
                aria-atomic="true"
                @mouseenter="pause(toast.id)"
                @mouseleave="resume(toast.id)"
            >
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center" aria-hidden="true">
                    <svg x-show="toast.tone === 'success'" class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg x-show="toast.tone === 'info'" class="h-5 w-5 text-brand-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg x-show="toast.tone === 'warning'" class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.1L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 4.1a2 2 0 00-3.4 0z" /></svg>
                    <svg x-show="toast.tone === 'error'" class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
                <p class="min-w-0 flex-1 pt-0.5 text-xs leading-5 text-slate-800 dark:text-slate-100" x-text="toast.message"></p>
                <button type="button" class="shrink-0 rounded text-slate-400 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:text-slate-200" @click="dismiss(toast.id)" aria-label="Dismiss notification">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>
        </template>
    </div>

    <style>
        .psg-toasts {
            position: fixed;
            z-index: 70;
            top: 4.5rem;
            right: 1rem;
            display: flex;
            width: min(22rem, calc(100vw - 1.5rem));
            flex-direction: column;
            gap: 0.5rem;
            pointer-events: none;
        }
        .psg-toast {
            border-top: 2px solid #b45309;
        }
        .psg-toast[data-toast-tone="success"] { border-top-color: #059669; }
        .psg-toast[data-toast-tone="info"] { border-top-color: #1d4e89; }
        .psg-toast[data-toast-tone="warning"] { border-top-color: #d97706; }
        .psg-toast[data-toast-tone="error"] { border-top-color: #e11d48; }
        @media (max-width: 639px) {
            .psg-toasts {
                top: 4.25rem;
                right: 0.75rem;
                left: 0.75rem;
                width: auto;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .psg-toast {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            if (window.__psgToasts) {
                return;
            }
            window.__psgToasts = true;

            Alpine.data('psgToasts', (seed) => ({
                items: [],
                timers: {},
                remaining: {},
                deadlines: {},
                duration: 4500,

                boot() {
                    (seed || []).forEach((item) => this.push(item));
                },

                push(detail) {
                    const toast = {
                        id: (detail && detail.id) ? String(detail.id) : 't-' + Date.now(),
                        tone: (detail && detail.tone) ? detail.tone : 'success',
                        message: (detail && detail.message) ? String(detail.message) : '',
                    };

                    if (toast.message.trim() === '') {
                        return;
                    }

                    if (this.items.some((row) => row.message === toast.message && row.tone === toast.tone)) {
                        return;
                    }

                    this.items.push(toast);
                    while (this.items.length > 3) {
                        this.dismiss(this.items[0].id);
                    }
                    this.arm(toast.id, this.duration);
                },

                arm(id, ms) {
                    window.clearTimeout(this.timers[id]);
                    this.remaining[id] = ms;
                    this.deadlines[id] = Date.now() + ms;
                    this.timers[id] = window.setTimeout(() => this.dismiss(id), ms);
                },

                pause(id) {
                    window.clearTimeout(this.timers[id]);
                    this.remaining[id] = Math.max(0, (this.deadlines[id] || Date.now()) - Date.now());
                },

                resume(id) {
                    if (!this.items.some((row) => row.id === id)) {
                        return;
                    }
                    this.arm(id, this.remaining[id] ?? this.duration);
                },

                dismiss(id) {
                    window.clearTimeout(this.timers[id]);
                    delete this.timers[id];
                    delete this.remaining[id];
                    delete this.deadlines[id];
                    this.items = this.items.filter((row) => row.id !== id);
                },
            }));
        });
    </script>
@endonce

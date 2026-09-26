/**
 * Auto-hide scrollbars: show on hover / scroll, fade after idle.
 * Fine-pointer devices only — touch keeps native overlay scrollbars.
 */

const IDLE_MS = 1600;
const SCROLLABLE = new Set(['auto', 'scroll', 'overlay']);
const timers = new WeakMap();

function prefersFinePointer() {
    return window.matchMedia('(hover: hover) and (pointer: fine)').matches;
}

function canScroll(el) {
    if (!(el instanceof HTMLElement)) {
        return false;
    }

    const style = window.getComputedStyle(el);
    const overflowY = SCROLLABLE.has(style.overflowY);
    const overflowX = SCROLLABLE.has(style.overflowX);

    if (! overflowY && ! overflowX) {
        return false;
    }

    return (overflowY && el.scrollHeight > el.clientHeight + 1)
        || (overflowX && el.scrollWidth > el.clientWidth + 1);
}

function scheduleHide(el) {
    const existing = timers.get(el);
    if (existing) {
        window.clearTimeout(existing);
    }

    const id = window.setTimeout(() => {
        timers.delete(el);

        // Still interacting — keep visible and check again after idle.
        if (el.matches(':hover') || el.matches(':focus-within')) {
            scheduleHide(el);

            return;
        }

        el.classList.remove('psg-scrollbar-visible');
    }, IDLE_MS);

    timers.set(el, id);
}

function reveal(el) {
    if (! canScroll(el)) {
        return;
    }

    el.classList.add('psg-autohide-scroll', 'psg-scrollbar-visible');
    scheduleHide(el);
}

function revealAncestors(from) {
    let el = from instanceof Element ? from : null;

    while (el && el !== document.documentElement) {
        if (canScroll(el)) {
            reveal(el);
        }
        el = el.parentElement;
    }
}

function onScroll(event) {
    if (! prefersFinePointer()) {
        return;
    }

    const target = event.target;
    if (target instanceof Element) {
        reveal(target);
        return;
    }

    const root = document.scrollingElement;
    if (root instanceof HTMLElement) {
        reveal(root);
    }
}

function onPointerOver(event) {
    if (! prefersFinePointer()) {
        return;
    }

    revealAncestors(event.target);
}

function onFocusIn(event) {
    if (! prefersFinePointer()) {
        return;
    }

    revealAncestors(event.target);
}

function markKnownScrollers(root = document) {
    root.querySelectorAll(
        'main, .overflow-y-auto, .overflow-x-auto, .overflow-auto, .overflow-y-scroll, .overflow-x-scroll, .ts-dropdown-content, [data-psg-scroll]',
    ).forEach((el) => {
        if (el instanceof HTMLElement) {
            el.classList.add('psg-autohide-scroll');
        }
    });
}

export function initAutoHideScrollbars() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    markKnownScrollers();

    document.addEventListener('scroll', onScroll, { capture: true, passive: true });
    document.addEventListener('pointerover', onPointerOver, { capture: true, passive: true });
    document.addEventListener('focusin', onFocusIn, { capture: true, passive: true });

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) {
                    markKnownScrollers(node);
                    if (
                        node.matches?.(
                            'main, .overflow-y-auto, .overflow-x-auto, .overflow-auto, .overflow-y-scroll, .overflow-x-scroll, .ts-dropdown-content, [data-psg-scroll]',
                        )
                    ) {
                        node.classList.add('psg-autohide-scroll');
                    }
                }
            });
        }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
}

const STORAGE_KEY = 'psg-appearance';

export function systemPrefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function getStoredAppearance() {
    return localStorage.getItem(STORAGE_KEY);
}

export function resolveAppearance(mode) {
    if (mode === 'light' || mode === 'dark') {
        return mode;
    }

    if (mode === 'system' || !mode) {
        return systemPrefersDark() ? 'dark' : 'light';
    }

    return 'light';
}

export function applyAppearance(mode) {
    const resolved = resolveAppearance(mode);
    const root = document.documentElement;

    root.classList.toggle('dark', resolved === 'dark');
    root.dataset.theme = resolved;
    root.style.colorScheme = resolved;

    return resolved;
}

export function setAppearance(mode) {
    localStorage.setItem(STORAGE_KEY, mode);
    return applyAppearance(mode);
}

export function toggleAppearance() {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

    return setAppearance(next);
}

export function initAppearance() {
    return applyAppearance(getStoredAppearance());
}

export function appearanceLabel(mode) {
    const resolved = resolveAppearance(mode);

    return resolved === 'dark' ? 'Dark' : 'Light';
}

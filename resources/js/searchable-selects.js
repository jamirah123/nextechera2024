import TomSelect from 'tom-select';

const SELECTOR = 'select[data-searchable="true"]:not(.ts-hidden-accessible)';

function shouldSkip(el) {
    if (! el || el.tomselect) {
        return true;
    }

    if (el.dataset.searchable !== 'true') {
        return true;
    }

    if (el.disabled && el.options.length === 0) {
        return true;
    }

    return false;
}

function syncAlpineValue(el, ts) {
    const descriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');

    if (! descriptor?.get || ! descriptor?.set) {
        return;
    }

    Object.defineProperty(el, 'value', {
        configurable: true,
        enumerable: true,
        get() {
            return descriptor.get.call(this);
        },
        set(next) {
            descriptor.set.call(this, next);
            const normalized = next == null ? '' : String(next);
            const current = String(ts.getValue() ?? '');

            if (current !== normalized) {
                ts.setValue(normalized, true);
            }
        },
    });
}

function watchOptionState(el, ts) {
    let refreshing = false;

    const refresh = () => {
        if (refreshing || ! el.tomselect) {
            return;
        }

        refreshing = true;
        window.requestAnimationFrame(() => {
            try {
                const current = el.value;
                ts.clearOptions();
                ts.sync();
                if (current !== undefined && current !== null) {
                    ts.setValue(String(current), true);
                }
            } finally {
                refreshing = false;
            }
        });
    };

    const observer = new MutationObserver(refresh);
    observer.observe(el, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['disabled', 'hidden', 'selected', 'value', 'label'],
    });

    el._tomSelectObserver = observer;
}

function initSelect(el) {
    if (shouldSkip(el)) {
        return null;
    }

    const placeholderOption = Array.from(el.options).find((option) => option.value === '');
    const placeholder = placeholderOption?.textContent?.trim() || 'Search…';

    const ts = new TomSelect(el, {
        allowEmptyOption: true,
        create: false,
        maxOptions: null,
        maxItems: 1,
        hideSelected: false,
        closeAfterSelect: true,
        placeholder,
        searchField: ['text'],
        sortField: [{ field: '$score' }, { field: '$order' }],
        plugins: {
            clear_button: {
                title: 'Clear selection',
            },
            dropdown_input: {},
        },
        render: {
            item(data, escape) {
                if (! data.value) {
                    return `<div class="item text-slate-400">${escape(placeholder)}</div>`;
                }

                return `<div class="item">${escape(data.text)}</div>`;
            },
            option(data, escape) {
                return `<div class="option" data-selectable>${escape(data.text)}</div>`;
            },
            no_results() {
                return '<div class="no-results">No matching options</div>';
            },
        },
        onInitialize() {
            this.control_input?.setAttribute('aria-label', `Search ${placeholder}`);
            // Avoid duplicate empty-option text + placeholder in the control.
            if (! this.getValue()) {
                this.clear(true);
            }
        },
        onChange() {
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });

    syncAlpineValue(el, ts);
    watchOptionState(el, ts);

    return ts;
}

export function initSearchableSelects(root = document) {
    root.querySelectorAll(SELECTOR).forEach((el) => initSelect(el));
}

export function refreshSearchableSelect(el) {
    if (! el?.tomselect) {
        initSelect(el);

        return;
    }

    const current = el.value;
    el.tomselect.clearOptions();
    el.tomselect.sync();
    if (current) {
        el.tomselect.setValue(current, true);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initSearchableSelects();
});

document.addEventListener('livewire:navigated', () => {
    initSearchableSelects();
});

const bodyObserver = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        mutation.addedNodes.forEach((node) => {
            if (! (node instanceof HTMLElement)) {
                return;
            }

            if (node.matches?.(SELECTOR)) {
                initSelect(node);
            }

            node.querySelectorAll?.(SELECTOR).forEach((el) => initSelect(el));
        });
    }
});

bodyObserver.observe(document.documentElement, {
    childList: true,
    subtree: true,
});

window.initSearchableSelects = initSearchableSelects;
window.refreshSearchableSelect = refreshSearchableSelect;

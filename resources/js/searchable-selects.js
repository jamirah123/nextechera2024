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

function splitLabel(text) {
    const raw = String(text ?? '').trim();
    const parts = raw.split(/\s+[—–-]\s+/);

    if (parts.length >= 2) {
        return {
            code: parts[0].trim(),
            name: parts.slice(1).join(' — ').trim(),
        };
    }

    return { code: null, name: raw };
}

function initSelect(el) {
    if (shouldSkip(el)) {
        return null;
    }

    const placeholderOption = Array.from(el.options).find((option) => option.value === '');
    const placeholder = placeholderOption?.textContent?.trim() || 'Search…';
    const label = el.closest('.field')?.querySelector('.field__label')?.textContent?.trim()
        || el.getAttribute('aria-label')
        || placeholder;

    const ts = new TomSelect(el, {
        allowEmptyOption: true,
        create: false,
        maxOptions: null,
        maxItems: 1,
        hideSelected: false,
        closeAfterSelect: true,
        openOnFocus: true,
        placeholder,
        searchField: ['text'],
        sortField: [{ field: '$score' }, { field: '$order' }],
        controlInput: '<input type="text" autocomplete="off" size="1" />',
        plugins: {
            clear_button: {
                title: 'Clear selection',
            },
        },
        render: {
            item(data, escape) {
                if (! data.value) {
                    return `<div class="item item--placeholder">${escape(placeholder)}</div>`;
                }

                const { code, name } = splitLabel(data.text);

                if (code) {
                    return `<div class="item"><span class="item__code">${escape(code)}</span><span class="item__name">${escape(name)}</span></div>`;
                }

                return `<div class="item">${escape(data.text)}</div>`;
            },
            option(data, escape) {
                if (! data.value) {
                    return `<div class="option option--all" data-selectable>${escape(data.text || placeholder)}</div>`;
                }

                const { code, name } = splitLabel(data.text);

                if (code) {
                    return `<div class="option" data-selectable>
                        <span class="option__code">${escape(code)}</span>
                        <span class="option__name">${escape(name)}</span>
                    </div>`;
                }

                return `<div class="option" data-selectable>${escape(data.text)}</div>`;
            },
            no_results() {
                return '<div class="no-results">No matching options</div>';
            },
        },
        onInitialize() {
            this.wrapper?.classList.add('ts-professional');
            this.wrapper?.classList.remove(
                'field__control',
                'field__control--select',
                'field__control--error',
                'field__control--textarea',
                'field__control--readonly',
            );
            this.control_input?.setAttribute('aria-label', `Search ${label}`);
            this.control_input?.setAttribute('placeholder', '');

            if (! this.getValue()) {
                this.clear(true);
            }
        },
        onDropdownOpen() {
            this.control_input?.focus({ preventScroll: true });
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

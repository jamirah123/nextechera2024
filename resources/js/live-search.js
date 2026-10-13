function isLiveSearchField(field, form) {
    if (! (field instanceof HTMLInputElement) || field.form !== form) {
        return false;
    }

    if (field.type === 'hidden' || field.type === 'date' || field.type === 'checkbox' || field.type === 'radio') {
        return false;
    }

    return field.type === 'search' || field.name === 'q';
}

function sameAction(candidate, action) {
    try {
        return new URL(candidate.getAttribute('action') || '', window.location.origin).href === action;
    } catch {
        return false;
    }
}

function branchIndex(children, action) {
    return children.findIndex((child) => {
        if (child.matches('form') && sameAction(child, action)) {
            return true;
        }

        return [...child.querySelectorAll('form')].some((nested) => sameAction(nested, action));
    });
}

function destroySelects(node) {
    node.querySelectorAll('select').forEach((select) => {
        try {
            select.tomselect?.destroy();
        } catch {
            // The control can already be gone during a fast follow-up search.
        }
    });
}

function replaceSiblings(oldParent, newParent, action) {
    const oldChildren = [...oldParent.children];
    const newChildren = [...newParent.children];
    const oldIndex = branchIndex(oldChildren, action);
    const newIndex = branchIndex(newChildren, action);

    if (oldIndex < 0 || newIndex < 0) {
        return null;
    }

    const kept = oldChildren[oldIndex];

    oldChildren.forEach((child) => {
        if (child === kept) {
            return;
        }

        destroySelects(child);
        child.remove();
    });

    for (let index = newIndex - 1; index >= 0; index -= 1) {
        kept.before(newChildren[index]);
    }

    let cursor = kept;

    for (let index = newIndex + 1; index < newChildren.length; index += 1) {
        cursor.after(newChildren[index]);
        cursor = newChildren[index];
    }

    [...oldParent.children].forEach((child) => {
        if (child !== kept && window.Alpine) {
            window.Alpine.initTree(child);
        }
    });

    return { kept, next: newChildren[newIndex] };
}

function swapAroundForm(form, newMain) {
    const main = form.closest('main');
    if (! main) {
        return false;
    }

    let oldParent = main;
    let newParent = newMain;

    for (let depth = 0; depth < 12; depth += 1) {
        const step = replaceSiblings(oldParent, newParent, form.action);
        if (! step) {
            return false;
        }

        if (step.kept === form) {
            return true;
        }

        oldParent = step.kept;
        newParent = step.next;
    }

    return false;
}

function searchUrl(form) {
    const url = new URL(form.action, window.location.origin);
    const params = new URLSearchParams();

    new FormData(form).forEach((value, key) => {
        if (value !== '') {
            params.append(key, value);
        }
    });

    url.search = params.toString();

    return url;
}

function keepCaret(input, caret, caretEnd, typed) {
    const restore = () => {
        if (! input.isConnected || document.activeElement === input) {
            return;
        }

        input.focus({ preventScroll: true });

        if (input.value === typed && typeof caret === 'number' && typeof caretEnd === 'number') {
            input.setSelectionRange(caret, caretEnd);
        }
    };

    restore();
    window.requestAnimationFrame(restore);
}

let searchAbort = null;

function runLiveSearch(form, input) {
    const url = searchUrl(form);
    const caret = input.selectionStart;
    const caretEnd = input.selectionEnd;
    const typed = input.value;

    if (searchAbort) {
        searchAbort.abort();
    }

    const controller = new AbortController();
    searchAbort = controller;

    fetch(url.toString(), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html',
        },
        credentials: 'same-origin',
        signal: controller.signal,
    }).then(async (response) => {
        if (controller.signal.aborted) {
            return;
        }

        if (response.redirected && /\/login(?:\?|$)/.test(response.url)) {
            window.location.assign(response.url);
            return;
        }

        if (! response.ok) {
            window.location.assign(url);
            return;
        }

        const html = await response.text();

        if (controller.signal.aborted) {
            return;
        }

        const doc = new DOMParser().parseFromString(html, 'text/html');
        const nextMain = doc.querySelector('main');

        if (! nextMain || ! swapAroundForm(form, nextMain)) {
            window.location.assign(url);
            return;
        }

        if (doc.title) {
            document.title = doc.title;
        }

        window.history.replaceState(null, '', url);
        keepCaret(input, caret, caretEnd, typed);
    }).catch((error) => {
        if (error?.name === 'AbortError') {
            return;
        }

        window.location.assign(url);
    });
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (! (form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') {
        return;
    }

    if (form.closest('[data-posting-board]')) {
        return;
    }

    const field = document.activeElement;

    if (! isLiveSearchField(field, form)) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    runLiveSearch(form, field);
}, true);

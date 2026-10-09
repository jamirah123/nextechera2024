function headerLabels(table) {
    return [...table.querySelectorAll('thead th')].map((header) => header.textContent.replace(/\s+/g, ' ').trim());
}

function labelTable(table) {
    const labels = headerLabels(table);
    if (labels.length === 0) {
        return;
    }

    table.querySelectorAll('tbody tr').forEach((row) => {
        let index = 0;

        row.querySelectorAll(':scope > td').forEach((cell) => {
            const span = Number(cell.getAttribute('colspan') || 1);

            if (! cell.hasAttribute('data-label')) {
                cell.setAttribute('data-label', span > 1 ? '' : (labels[index] || ''));
            }

            index += span;
        });
    });
}

export function labelPhoneTables(root = document) {
    root.querySelectorAll('main table').forEach(labelTable);
}

export function watchPhoneTables() {
    const main = document.querySelector('main');
    if (! main) {
        return;
    }

    labelPhoneTables(main);

    const observer = new MutationObserver((records) => {
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (! (node instanceof Element)) {
                    return;
                }

                if (node.matches('table, tr, td, tbody')) {
                    const table = node.closest('table') || node;
                    if (table instanceof HTMLTableElement) {
                        labelTable(table);
                    }
                }

                node.querySelectorAll?.('table').forEach(labelTable);
            });
        });
    });

    observer.observe(main, { childList: true, subtree: true });
}

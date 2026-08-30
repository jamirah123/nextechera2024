@props([
    'attachment',
    'streamRoute',
])

<div class="bg-slate-50 p-3 sm:p-5">
    @if ($attachment->previewType() === 'image')
        <div class="flex min-h-[60vh] items-center justify-center">
            <img
                src="{{ $streamRoute }}"
                alt="{{ $attachment->displayName() }}"
                class="max-h-[75vh] w-auto max-w-full rounded-xl border border-slate-200 bg-white shadow-sm"
            >
        </div>
    @elseif ($attachment->previewType() === 'pdf')
        <iframe
            src="{{ $streamRoute }}"
            title="{{ $attachment->displayName() }}"
            class="h-[75vh] w-full rounded-xl border border-slate-200 bg-white shadow-sm"
        ></iframe>
    @elseif ($attachment->previewType() === 'docx')
        <div
            class="min-h-[60vh] overflow-auto rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
            x-data="wordPreview(@js($streamRoute))"
            x-init="render()"
        >
            <p x-show="loading" class="text-sm text-slate-500">Loading document preview…</p>
            <p x-show="error" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" x-text="error"></p>
            <div x-show="!loading && !error" x-cloak class="prose prose-slate max-w-none text-sm leading-relaxed" x-html="html"></div>
        </div>
        @once
            @push('scripts')
                <script src="https://cdn.jsdelivr.net/npm/mammoth@1.8.0/mammoth.browser.min.js"></script>
                <script>
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('wordPreview', (streamUrl) => ({
                            html: '',
                            loading: true,
                            error: null,
                            async render() {
                                if (typeof mammoth === 'undefined') {
                                    this.loading = false;
                                    this.error = 'Could not load the document preview. Open the file in a new browser tab instead.';
                                    return;
                                }

                                try {
                                    const response = await fetch(streamUrl, { credentials: 'same-origin' });
                                    if (! response.ok) {
                                        throw new Error('Could not load the document.');
                                    }

                                    const buffer = await response.arrayBuffer();
                                    const result = await mammoth.convertToHtml({ arrayBuffer: buffer });
                                    this.html = result.value || '<p>Document is empty.</p>';
                                } catch (error) {
                                    this.error = 'Could not render this Word document in the browser. Open it in a new browser tab instead.';
                                } finally {
                                    this.loading = false;
                                }
                            },
                        }));
                    });
                </script>
            @endpush
        @endonce
    @elseif ($attachment->previewType() === 'doc')
        <iframe
            src="{{ $streamRoute }}"
            title="{{ $attachment->displayName() }}"
            class="h-[75vh] w-full rounded-xl border border-slate-200 bg-white shadow-sm"
        ></iframe>
        <p class="mt-3 text-xs text-slate-500">
            If the preview does not appear, use <strong class="font-semibold text-slate-700">Open in browser tab</strong> above or download the file.
        </p>
    @else
        <iframe
            src="{{ $streamRoute }}"
            title="{{ $attachment->displayName() }}"
            class="h-[75vh] w-full rounded-xl border border-slate-200 bg-white shadow-sm"
        ></iframe>
    @endif
</div>

@props([
    'attachment',
    'streamRoute',
    'downloadRoute',
    'destroyRoute' => null,
    'backRoute',
    'subtitle',
    'canManage' => false,
])

<div class="mx-auto max-w-6xl space-y-3">
    <x-page-header
        :title="$attachment->displayName()"
        :subtitle="$subtitle"
        :back="$backRoute"
    >
        <x-slot:actions>
            <a
                href="{{ $streamRoute }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
            >
                <x-icon name="eye" class="h-3.5 w-3.5" />
                Open in browser tab
            </a>
            <a
                href="{{ $downloadRoute }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
            >
                <x-icon name="download" class="h-3.5 w-3.5" />
                Download
            </a>
            @if ($canManage && $destroyRoute)
                <x-delete-button
                    :action="$destroyRoute"
                    label="Remove"
                    confirm-label="Yes, remove"
                    title="Remove document"
                    confirm="This document will be permanently deleted."
                    size="md"
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-3 py-2.5">
            <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">File name</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $attachment->original_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Size</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $attachment->humanSize() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Uploaded</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ optional($attachment->created_at)->format('d M Y, H:i') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Uploaded by</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $attachment->uploader?->name ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <x-attachment-browser-viewer :attachment="$attachment" :stream-route="$streamRoute" />
    </section>
</div>

@props([
    'title',
    'reference' => null,
    'subtitle' => null,
    'statusTone' => 'brand',
    'statusLabel' => null,
    'meta' => [],
    'footerNote' => null,
    'compact' => false,
])

<article {{ $attributes->merge(['class' => 'finance-document overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900']) }}>
    <div @class([
        'px-4 pt-4' => $compact,
        'px-6 pt-6 sm:px-8 sm:pt-8' => ! $compact,
    ])>
        <x-print.letterhead
            :document-title="$title"
            :document-reference="$reference"
            :compact="$compact"
        />

        <div @class([
            'mt-2 flex flex-wrap items-center justify-between gap-2' => $compact,
            'mt-5 flex flex-wrap items-start justify-between gap-3' => ! $compact,
        ])>
            <div class="min-w-0">
                @if ($subtitle)
                    <p @class([
                        'text-xs font-medium text-slate-600 dark:text-slate-400' => $compact,
                        'text-sm font-medium text-slate-700 dark:text-slate-300' => ! $compact,
                    ])>{{ $subtitle }}</p>
                @endif
            </div>
            @if ($statusLabel)
                <x-status-badge :tone="$statusTone" :label="$statusLabel" />
            @endif
        </div>

        @if (! empty($meta))
            <dl @class([
                'mt-2 grid gap-1.5 sm:grid-cols-2' => $compact,
                'mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-4' => ! $compact,
            ])>
                @foreach ($meta as $item)
                    <div @class([
                        'rounded-lg border border-slate-200 bg-slate-50/80 px-2.5 py-2 dark:border-slate-700 dark:bg-slate-800/50' => $compact,
                        'rounded-xl border border-slate-200 bg-slate-50/80 px-3.5 py-3 dark:border-slate-700 dark:bg-slate-800/50' => ! $compact,
                    ])>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</dt>
                        <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $item['value'] }}</dd>
                        @if (! empty($item['hint']))
                            <dd class="text-[10px] text-slate-500">{{ $item['hint'] }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    <div @class([
        'px-4 py-3' => $compact,
        'px-6 py-6 sm:px-8' => ! $compact,
    ])>
        {{ $slot }}

        @if ($footerNote)
            <x-print.footer :note="$footerNote" @class(['mt-3 text-[11px]' => $compact, 'mt-6' => ! $compact]) />
        @endif
    </div>
</article>

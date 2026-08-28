@props([
    'title',
    'reference' => null,
    'subtitle' => null,
    'statusTone' => 'brand',
    'statusLabel' => null,
    'meta' => [],
    'footerNote' => null,
])

<article {{ $attributes->merge(['class' => 'finance-document overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    <div class="px-6 pt-6 sm:px-8 sm:pt-8">
        <x-print.letterhead
            :document-title="$title"
            :document-reference="$reference"
        />

        <div class="mt-5 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($subtitle)
                    <p class="text-sm font-medium text-slate-700">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($statusLabel)
                <x-status-badge :tone="$statusTone" :label="$statusLabel" />
            @endif
        </div>

        @if (! empty($meta))
            <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($meta as $item)
                    <div class="rounded-xl border border-slate-150 border-slate-200 bg-slate-50/80 px-3.5 py-3">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $item['value'] }}</dd>
                        @if (! empty($item['hint']))
                            <dd class="mt-0.5 text-xs text-slate-500">{{ $item['hint'] }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    <div class="px-6 py-6 sm:px-8">
        {{ $slot }}

        <x-print.footer :note="$footerNote" />
    </div>
</article>

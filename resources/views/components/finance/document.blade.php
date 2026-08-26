@props([
    'title',
    'reference' => null,
    'subtitle' => null,
    'statusTone' => 'brand',
    'statusLabel' => null,
    'meta' => [],
])

<article {{ $attributes->merge(['class' => 'finance-document overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    <header class="border-b border-slate-100 bg-gradient-to-br from-steel-950 via-brand-950 to-emerald-900 px-6 py-6 text-white sm:px-8 sm:py-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/70">{{ config('psg.company') }}</p>
                @if (filled(config('psg.support_email')) || filled(config('psg.support_phone')))
                    <p class="mt-1 text-xs text-white/60">
                        @if (filled(config('psg.support_email'))){{ config('psg.support_email') }}@endif
                        @if (filled(config('psg.support_email')) && filled(config('psg.support_phone'))) · @endif
                        @if (filled(config('psg.support_phone'))){{ config('psg.support_phone') }}@endif
                    </p>
                @endif
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $title }}</h1>
                @if ($reference)
                    <p class="mt-1 font-mono text-sm text-emerald-100/90">{{ $reference }}</p>
                @endif
                @if ($subtitle)
                    <p class="mt-2 text-sm text-white/80">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($statusLabel)
                <x-status-badge :tone="$statusTone" :label="$statusLabel" class="!border-white/20 !bg-white/10 !text-white" />
            @endif
        </div>
        @if (! empty($meta))
            <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($meta as $item)
                    <div class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/60">{{ $item['label'] }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold">{{ $item['value'] }}</dd>
                        @if (! empty($item['hint']))
                            <dd class="text-xs text-white/60">{{ $item['hint'] }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        @endif
    </header>
    <div class="px-6 py-6 sm:px-8">
        {{ $slot }}
    </div>
</article>

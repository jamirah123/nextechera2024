@props(['entries', 'title' => 'Activity timeline', 'compact' => false])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900']) }}>
    <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
        <h2 @class([
            'font-semibold text-slate-900 dark:text-slate-100',
            'text-xs' => $compact,
            'text-base' => ! $compact,
        ])>{{ $title }}</h2>
        <p @class([
            'mt-0.5 text-slate-500',
            'text-[11px]' => $compact,
            'text-sm' => ! $compact,
        ])>Shifts, deployments, HR, finance and audit events in one feed.</p>
    </div>

    @if ($entries->isEmpty())
        <p @class([
            'text-slate-500',
            'px-3 py-4 text-xs' => $compact,
            'px-5 py-6 text-sm sm:px-6' => ! $compact,
        ])>No activity recorded yet.</p>
    @else
        <ol class="divide-y divide-slate-100">
            @foreach ($entries as $entry)
                <li class="flex gap-4 px-3 py-2.5 sm:px-4">
                    <div class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full ring-4 {{ $entry['source'] === 'status_history' ? 'bg-emerald-600 ring-emerald-50' : 'bg-brand-600 ring-brand-50' }}"></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($entry['url'])
                                <a href="{{ $entry['url'] }}" @class(['font-semibold text-brand-700 hover:text-brand-800', 'text-xs' => $compact])>{{ $entry['summary'] }}</a>
                            @else
                                <p @class(['font-semibold text-slate-900 dark:text-slate-100', 'text-xs' => $compact])>{{ $entry['summary'] }}</p>
                            @endif
                            <span @class([
                                'rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                'bg-brand-50 text-brand-700' => ($entry['category_tone'] ?? 'slate') === 'brand',
                                'bg-emerald-50 text-emerald-700' => ($entry['category_tone'] ?? '') === 'emerald',
                                'bg-sky-50 text-sky-700' => ($entry['category_tone'] ?? '') === 'sky',
                                'bg-amber-50 text-amber-700' => ($entry['category_tone'] ?? '') === 'amber',
                                'bg-violet-50 text-violet-700' => ($entry['category_tone'] ?? '') === 'violet',
                                'bg-rose-50 text-rose-700' => ($entry['category_tone'] ?? '') === 'rose',
                                'bg-slate-100 text-slate-600' => ! in_array($entry['category_tone'] ?? 'slate', ['brand', 'emerald', 'sky', 'amber', 'violet', 'rose'], true),
                            ])>{{ $entry['category'] }}</span>
                            @if ($entry['severity_label'])
                                <x-status-badge :tone="$entry['severity_tone'] ?? 'slate'" :label="$entry['severity_label']" />
                            @endif
                        </div>
                        <p @class(['text-slate-500', 'mt-0.5 text-[10px]' => $compact, 'mt-1 text-xs' => ! $compact])>
                            @if ($entry['occurred_at'])
                                {{ $entry['occurred_at']->timezone(config('app.timezone'))->format('d M Y, H:i T') }}
                            @else
                                —
                            @endif
                            · {{ $entry['actor'] }}
                            @if ($entry['action'])
                                · <span class="font-mono">{{ $entry['action'] }}</span>
                            @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>

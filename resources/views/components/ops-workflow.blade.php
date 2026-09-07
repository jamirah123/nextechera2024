{{-- Ugandan PSC / PSG ops: Site posting records the shift taken --}}
@php
    $opsStep = $opsStep ?? null;
    $opsDate = $opsDate ?? now()->toDateString();
@endphp
<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/60">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">PSC operations principle</p>
        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">A site posting for a duty date creates a Shift recorded entry. Outcomes (completed, absent, cancelled, incomplete) update that record — never delete it.</p>
    </div>
    <ol class="grid gap-0 sm:grid-cols-3">
        @foreach ([
            [
                'key' => 'posting',
                'step' => '1',
                'title' => 'Site posting',
                'body' => 'Guard → site → duty date → Day/Night. Creates Shift recorded (backdate allowed).',
                'href' => route('deployments.board'),
                'cta' => 'Posting board',
            ],
            [
                'key' => 'roster',
                'step' => '2',
                'title' => 'Duty roster',
                'body' => 'Optional extras for rotating cover or additional duties on a date.',
                'href' => route('shifts.allocate', ['date' => $opsDate]),
                'cta' => 'Open roster',
            ],
            [
                'key' => 'register',
                'step' => '3',
                'title' => 'Duty register',
                'body' => 'Set outcomes: Completed, Absent/No-show, Cancelled, Incomplete. Audited.',
                'href' => route('shifts.index', ['date' => $opsDate]),
                'cta' => 'Open register',
            ],
        ] as $item)
            <li @class([
                'relative border-b border-slate-100 p-3 sm:border-b-0 sm:border-r sm:last:border-r-0 dark:border-slate-800',
                'bg-brand-50/50 dark:bg-brand-950/20' => $opsStep === $item['key'],
            ])>
                <div class="flex items-start gap-2.5">
                    <span @class([
                        'inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                        'bg-brand-700 text-white' => $opsStep === $item['key'],
                        'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' => $opsStep !== $item['key'],
                    ])>{{ $item['step'] }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $item['title'] }}</p>
                        <p class="mt-0.5 text-[11px] leading-snug text-slate-600 dark:text-slate-400">{{ $item['body'] }}</p>
                        @if ($opsStep !== $item['key'])
                            <a href="{{ $item['href'] }}" class="mt-1.5 inline-flex text-[11px] font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-300">
                                {{ $item['cta'] }} →
                            </a>
                        @else
                            <p class="mt-1.5 text-[11px] font-semibold text-brand-800 dark:text-brand-300">You are here</p>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
</section>

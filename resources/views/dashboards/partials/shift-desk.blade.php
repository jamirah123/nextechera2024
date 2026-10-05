@php
    /** @var array{
     *     date: string,
     *     awaiting_deployment: int,
     *     needs_allocation: int,
     *     missed_today: int,
     *     recorded_today: int,
     *     deployed: int,
     *     links: array<string, string>
     * } $shiftDesk
     */
@endphp

<section class="mt-3 rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Today’s work queue</h3>
        <span class="text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($shiftDesk['date'])->format('D, j M Y') }}</span>
    </div>

    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ $shiftDesk['links']['deploy_board'] }}" class="rounded-md border border-brand-200 bg-brand-50 px-3 py-2 transition hover:bg-brand-100/70">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-brand-700">Awaiting deploy</p>
            <p class="text-lg font-semibold text-brand-950">{{ number_format($shiftDesk['awaiting_deployment']) }}</p>
        </a>
        <a href="{{ $shiftDesk['links']['allocate'] }}" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 transition hover:bg-amber-100/70">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Needs allocation</p>
            <p class="text-lg font-semibold text-amber-950">{{ number_format($shiftDesk['needs_allocation']) }}</p>
        </a>
        <a href="{{ $shiftDesk['links']['missed_shifts'] }}" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 transition hover:bg-rose-100/70">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-800">Missed</p>
            <p class="text-lg font-semibold text-rose-950">{{ number_format($shiftDesk['missed_today']) }}</p>
        </a>
        <a href="{{ $shiftDesk['links']['shifts_today'] }}" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 transition hover:bg-emerald-100/70">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800">Shifts recorded</p>
            <p class="text-lg font-semibold text-emerald-950">{{ number_format($shiftDesk['recorded_today']) }}</p>
        </a>
    </div>
</section>

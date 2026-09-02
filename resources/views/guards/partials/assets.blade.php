<section id="assets" class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-2.5">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Assets & uniforms</h2>
            <p class="text-[10px] text-slate-500">Issued kit, radios, boots and weapons.</p>
        </div>
        @if ($canManageAssets ?? false)
            <a href="{{ route('assets.create', ['guard_id' => $guard->id]) }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">Issue assets</a>
        @endif
    </div>

    @php
        $outstanding = $guard->assetIssuances->flatMap->lines->filter(
            fn ($line) => $line->quantityOutstanding() > 0 && ! in_array($line->status, [\App\Enums\AssetLineStatus::Returned, \App\Enums\AssetLineStatus::WrittenOff, \App\Enums\AssetLineStatus::Lost], true)
        );
        $recoveryDue = $guard->assetRecoveries->where('is_active', true)->where('balance_remaining', '>', 0)->sum('balance_remaining');
    @endphp

    @if ($guard->assetIssuances->isEmpty())
        <div class="p-5">
            <x-empty-state title="No assets issued" description="Record uniforms, radios, boots or weapons when kit is handed over." icon="shield" />
        </div>
    @else
        <div class="grid gap-0 border-b border-slate-100 sm:grid-cols-3">
            <div class="border-b border-slate-100 px-4 py-3 sm:border-b-0 sm:border-r">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Outstanding items</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900">{{ $outstanding->count() }}</p>
            </div>
            <div class="border-b border-slate-100 px-4 py-3 sm:border-b-0 sm:border-r">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">Recovery due</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900">{{ \App\Support\Money::format($recoveryDue) }}</p>
            </div>
            <div class="px-4 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-brand-700">Issuances</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900">{{ $guard->assetIssuances->count() }}</p>
            </div>
        </div>

        <ul class="divide-y divide-slate-100">
            @foreach ($guard->assetIssuances->take(6) as $issuance)
                @foreach ($issuance->lines as $line)
                    <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <a href="{{ route('assets.show', $issuance) }}" class="text-sm font-semibold text-brand-800 hover:underline">{{ $line->displayLabel() }}</a>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $issuance->reference }} · {{ $issuance->issued_at->format('d M Y') }}
                                · {{ $line->quantity_returned }}/{{ $line->quantity }} returned
                            </p>
                        </div>
                        <x-status-badge :tone="$line->status->tone()" :label="$line->status->label()" />
                    </li>
                @endforeach
            @endforeach
        </ul>

        @if ($outstanding->isNotEmpty())
            <div class="border-t border-amber-100 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                <span class="font-semibold">Exit clearance:</span> {{ $outstanding->count() }} item(s) still outstanding. Record returns before final clearance.
            </div>
        @endif
    @endif
</section>

@if ($canViewAssets ?? false)
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Issued assets and uniforms</h2>
            @if (($outstandingAssets ?? 0) > 0)
                <span class="text-[10px] font-semibold text-amber-700">{{ $outstandingAssets }} still out</span>
            @endif
        </div>
        @if (! $staff->isEmploymentActive() && ($outstandingAssets ?? 0) > 0)
            <p class="border-b border-amber-100 bg-amber-50 px-3 py-2 text-[11px] text-amber-900">This employee is {{ $staff->employment_status->label() }} and still holds company property. Record a return. The assignment history stays in place.</p>
        @endif
        @if ($assetIssuances->isEmpty())
            <p class="px-3 py-2 text-xs text-slate-500">No assets issued to this staff member.</p>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($assetIssuances as $issuance)
                    <li class="px-3 py-2">
                        <a href="{{ route('assets.show', $issuance) }}" class="text-xs font-semibold text-brand-800 hover:underline">{{ $issuance->reference }}</a>
                        <p class="text-[11px] text-slate-500">
                            {{ $issuance->issued_at->format('d M Y') }}
                            · {{ $issuance->status->label() }}
                            · {{ $issuance->lines->count() }} item(s)
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

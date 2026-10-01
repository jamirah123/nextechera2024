@php
    $today = now()->startOfDay();
    $revisions = $guard->uniformChargeRevisions
        ->sortBy(fn ($revision) => $revision->effective_from->toDateString().'-'.str_pad((string) $revision->id, 8, '0', STR_PAD_LEFT))
        ->values();
    $currentRevision = $revisions->first(fn ($revision) => $revision->covers($today));
    $exempt = $uniformStatus === \App\Enums\UniformChargeStatus::Exempt;
    $chargeNow = $exempt ? 0 : $companyUniformCharge;
@endphp

<section id="uniform-charge" class="form-card">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Uniform charge</h2>
            <p class="mt-1 text-xs text-slate-500">Payroll uses the status in force for each month. Approved payroll is left as calculated.</p>
        </div>
        <x-status-badge class="shrink-0" :tone="$exempt ? 'emerald' : 'slate'" :label="$uniformStatus->label()" />
    </div>

    <div class="mt-3 grid gap-2 sm:grid-cols-3">
        <div @class([
            'rounded-lg border px-3 py-2',
            'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30' => $exempt,
            'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50' => ! $exempt,
        ])>
            <p @class([
                'text-[10px] font-semibold uppercase tracking-wide',
                'text-emerald-700 dark:text-emerald-300' => $exempt,
                'text-slate-500' => ! $exempt,
            ])>This guard</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($chargeNow) }}</p>
            <p class="mt-0.5 text-[10px] text-slate-500">{{ $exempt ? 'Exempt from uniform charge' : 'Subject to the company charge' }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Company standard</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($companyUniformCharge) }}</p>
            <p class="mt-0.5 text-[10px] text-slate-500">Applies when this guard is not exempt</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">In force</p>
            @if ($currentRevision)
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $currentRevision->effective_from->format('d M Y') }}</p>
                <p class="mt-0.5 text-[10px] text-slate-500">{{ $currentRevision->effective_to ? 'Until '.$currentRevision->effective_to->format('d M Y') : 'Open ended' }}</p>
            @else
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">Company policy</p>
                <p class="mt-0.5 text-[10px] text-slate-500">No exemption on file</p>
            @endif
        </div>
    </div>

    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">History</h3>

    @if ($revisions->isEmpty())
        <p class="mt-2 rounded-lg border border-dashed border-slate-300 px-3 py-3 text-xs text-slate-500 dark:border-slate-600">No exemption has been recorded. Payroll uses the company uniform charge of {{ \App\Support\Money::format($companyUniformCharge) }}.</p>
    @else
        <ol class="mt-2 space-y-2">
            @foreach ($revisions->reverse() as $revision)
                @php $isCurrent = $currentRevision && $revision->id === $currentRevision->id; @endphp
                <li @class([
                    'rounded-lg border px-3 py-2',
                    'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30' => $isCurrent && $revision->status === \App\Enums\UniformChargeStatus::Exempt,
                    'border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-800/50' => $isCurrent && $revision->status !== \App\Enums\UniformChargeStatus::Exempt,
                    'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' => ! $isCurrent,
                ])>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">
                            {{ $revision->effective_from->format('d M Y') }}
                            <span class="font-normal text-slate-400">–</span>
                            {{ $revision->effective_to?->format('d M Y') ?? 'Open' }}
                        </p>
                        <span class="inline-flex items-center gap-1">
                            @if ($isCurrent)
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Current</span>
                            @endif
                            @if ($revision->status === \App\Enums\UniformChargeStatus::Exempt)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50">Exempt · {{ \App\Support\Money::format(0) }}</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">Subject · {{ \App\Support\Money::format($companyUniformCharge) }}</span>
                            @endif
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-700 dark:text-slate-200">{{ $revision->reason }}</p>
                    @if ($revision->notes)
                        <p class="mt-1 text-xs text-slate-500">{{ $revision->notes }}</p>
                    @endif
                    <p class="mt-1 text-[10px] text-slate-500">
                        Approved by {{ $revision->approver?->name ?? '—' }}
                        @if ($revision->approver?->role)
                            · {{ $revision->approver->role->label() }}
                        @endif
                        · {{ $revision->created_at?->format('d M Y H:i') ?? '—' }}
                    </p>
                </li>
            @endforeach
            <li class="rounded-lg border border-dashed border-slate-300 px-3 py-2 dark:border-slate-600">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">Before {{ $revisions->first()->effective_from->format('d M Y') }}</p>
                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">Subject · {{ \App\Support\Money::format($companyUniformCharge) }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Company uniform charge. No exemption was on file.</p>
            </li>
        </ol>
    @endif

    @if ($canManageUniformCharge)
        <form method="POST" action="{{ route('guards.uniform-charge-revisions.store', $guard) }}" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
            @csrf
            <h3 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Record a uniform charge change</h3>
            <p class="mt-1 text-xs text-slate-500">You are recorded as the approver. Reason and effective date are required. The company charge of {{ \App\Support\Money::format($companyUniformCharge) }} stays in settings.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <x-form-field label="Uniform charge" name="status" type="select" :required="true">
                    <option value="">Select a status</option>
                    @foreach (\App\Enums\UniformChargeStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Effective from" name="effective_from" type="date" :required="true" :value="old('effective_from')" />
                <x-form-field label="Reason" name="reason" :required="true" :value="old('reason')" class="sm:col-span-2" />
                <x-form-field label="Supporting note" name="notes" :value="old('notes')" class="sm:col-span-2" help="Optional. Attach a supporting document on this profile when one is required." />
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Record uniform charge change</button>
            </div>
        </form>
    @endif
</section>

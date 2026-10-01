@php
    $promotions = $guard->promotions
        ->sortBy(fn ($promotion) => $promotion->effective_from->toDateString().'-'.str_pad((string) $promotion->id, 8, '0', STR_PAD_LEFT))
        ->values();
    $currentPromotion = $promotions->first(fn ($promotion) => $promotion->isCurrent());
    $scheduledPromotion = $promotions->first(fn ($promotion) => $promotion->isScheduled());
    $salaryNow = $currentSalary ?? $guard->base_shift_rate;
    $positionName = $guard->position?->name ?? $guard->rank_designation ?? 'Security guard';
    $salaryType = $guard->position?->salaryLabel() ?? 'Variable (shift)';
    $showSalary = $canViewSalary ?? true;
    $onRoster = $currentPromotion === null;
    $badgeTone = $scheduledPromotion && $onRoster ? 'amber' : ($onRoster ? 'slate' : 'emerald');
    $badgeLabel = $scheduledPromotion && $onRoster
        ? 'Promotion scheduled'
        : ($onRoster ? 'On guard roster' : ($currentPromotion->position?->name ?? 'Promoted'));
@endphp

<section id="current-employment" class="form-card">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Current employment</h2>
            <p class="mt-1 text-xs text-slate-500">Employee ID stays the same when the position changes. A future date keeps them on the guard roster until that date.</p>
        </div>
        <x-status-badge class="shrink-0" :tone="$badgeTone" :label="$badgeLabel" />
    </div>

    <div @class([
        'mt-3 grid gap-2 sm:grid-cols-2',
        'lg:grid-cols-4' => $showSalary,
        'lg:grid-cols-3' => ! $showSalary,
    ])>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Employee ID</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $guard->employment_id }}</p>
            <p class="mt-0.5 text-[10px] text-slate-500">Unchanged by a promotion</p>
        </div>
        <div @class([
            'rounded-lg border px-3 py-2',
            'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30' => ! $onRoster,
            'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50' => $onRoster,
        ])>
            <p @class([
                'text-[10px] font-semibold uppercase tracking-wide',
                'text-emerald-700 dark:text-emerald-300' => ! $onRoster,
                'text-slate-500' => $onRoster,
            ])>Current position</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $positionName }}</p>
            <p class="mt-0.5 text-[10px] text-slate-500">
                @if ($currentPromotion)
                    Since {{ $currentPromotion->effective_from->format('d M Y') }}
                @else
                    Still on the guard roster
                @endif
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Salary type</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $salaryType }}</p>
            <p class="mt-0.5 text-[10px] text-slate-500">{{ $salaryType === 'Fixed monthly' ? 'Fixed amount for the month' : 'Pay follows recorded shifts' }}</p>
        </div>
        @if ($showSalary)
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Current salary</p>
                <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($salaryNow) }}</p>
                <p class="mt-0.5 text-[10px] text-slate-500">Starting point for the next change</p>
            </div>
        @endif
    </div>

    @if ($scheduledPromotion)
        <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
            {{ $scheduledPromotion->position?->name ?? 'New position' }} starts {{ $scheduledPromotion->effective_from->format('d M Y') }}.
            Until then this employee stays on the guard roster.
        </p>
    @endif

    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Position history</h3>

    @if ($promotions->isEmpty())
        <p class="mt-2 rounded-lg border border-dashed border-slate-300 px-3 py-3 text-xs text-slate-500 dark:border-slate-600">No position changes have been recorded. This employee is still on the guard roster.</p>
    @else
        <ol class="mt-2 space-y-2">
            @foreach ($promotions->reverse() as $promotion)
                @php
                    $isCurrent = $promotion->isCurrent();
                    $isScheduled = $promotion->isScheduled();
                @endphp
                <li @class([
                    'rounded-lg border px-3 py-2',
                    'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/30' => $isScheduled,
                    'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30' => $isCurrent,
                    'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' => ! $isScheduled && ! $isCurrent,
                ])>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">
                            {{ $promotion->effective_from->format('d M Y') }}
                            <span class="font-normal text-slate-400">–</span>
                            {{ $promotion->effective_to?->format('d M Y') ?? 'Open' }}
                        </p>
                        <span class="inline-flex items-center gap-1">
                            @if ($isScheduled)
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 ring-1 ring-amber-100">Scheduled</span>
                            @elseif ($isCurrent)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50">Current</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">Historical</span>
                            @endif
                        </span>
                    </div>
                    <p class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">
                        {{ $promotion->previous_position ?: 'Guard' }}
                        <span class="font-normal text-slate-400">→</span>
                        {{ $promotion->position?->name ?? '—' }}
                    </p>
                    @if ($showSalary)
                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                            {{ $promotion->previous_salary !== null ? \App\Support\Money::format($promotion->previous_salary) : '—' }}
                            <span class="text-slate-400">→</span>
                            {{ \App\Support\Money::format($promotion->new_salary) }}
                        </p>
                    @endif
                    <p class="mt-1 text-xs text-slate-700 dark:text-slate-200">{{ $promotion->reason }}</p>
                    @if ($promotion->remarks)
                        <p class="mt-1 text-xs text-slate-500">{{ $promotion->remarks }}</p>
                    @endif
                    <p class="mt-1 text-[10px] text-slate-500">
                        @if ($promotion->region)
                            {{ $promotion->region->name }}
                            ·
                        @endif
                        @if ($promotion->reference)
                            Ref {{ $promotion->reference }}
                            ·
                        @endif
                        Authorized by {{ $promotion->approver?->name ?? '—' }}
                        · {{ $promotion->created_at?->format('d M Y H:i') ?? '—' }}
                    </p>
                </li>
            @endforeach
            <li class="rounded-lg border border-dashed border-slate-300 px-3 py-2 dark:border-slate-600">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">Before {{ $promotions->first()->effective_from->format('d M Y') }}</p>
                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">Guard roster</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $promotions->first()->previous_position ?: 'Security guard' }}
                    @if ($showSalary && $promotions->first()->previous_salary !== null)
                        · {{ \App\Support\Money::format($promotions->first()->previous_salary) }}
                    @endif
                    . No position change was on file.
                </p>
            </li>
        </ol>
    @endif

    @if ($canPromote ?? false)
        <form method="POST" action="{{ route('guards.promotions.store', $guard) }}" enctype="multipart/form-data" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
            @csrf
            <h3 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Promote employee</h3>
            <p class="mt-1 text-xs text-slate-500">You are recorded as the person who authorized this change. Employee ID {{ $guard->employment_id }} stays the same.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Previous position</p>
                    <p class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $positionName }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Previous salary</p>
                    <p class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($salaryNow) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Authorized by</p>
                    <p class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ auth()->user()->name }}</p>
                </div>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <x-form-field label="New position" name="position_id" type="select" :required="true">
                    <option value="">Select position</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected((string) old('position_id') === (string) $position->id)>
                            {{ $position->name }} · {{ $position->salaryLabel() }}
                        </option>
                    @endforeach
                </x-form-field>
                <x-form-field label="New salary ({{ config('psg.currency') }})" name="new_salary" type="number" step="0.01" min="0" :value="old('new_salary')" :required="true" />
                <x-form-field label="Effective date" name="effective_from" type="date" :value="old('effective_from')" :required="true" />
                <x-form-field label="Region of operation" name="region_id" type="select" help="Required for a supervisor position.">
                    <option value="">No region change</option>
                    @foreach ($promotionRegions as $region)
                        <option value="{{ $region->id }}" @selected((string) old('region_id', $guard->region_id) === (string) $region->id)>{{ $region->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Reason" name="reason" :value="old('reason')" :required="true" class="sm:col-span-2" />
                <x-form-field label="Appointment reference" name="reference" :value="old('reference')" />
                <x-form-field label="Supporting document" name="document" type="file" />
                <x-form-field label="Remarks" name="remarks" type="textarea" :value="old('remarks')" class="sm:col-span-2" />
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Record promotion</button>
            </div>
        </form>
    @endif
</section>

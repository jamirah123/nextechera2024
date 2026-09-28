@php
    $currentPromotion = $guard->promotions->first(fn ($promotion) => $promotion->isCurrent());
    $previousSalary = $currentSalary ?? $guard->base_shift_rate;
@endphp

<section class="form-card">
    <div>
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Current employment</h2>
        <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Employee ID</dt>
                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ $guard->employment_id }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Current position</dt>
                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ $guard->position?->name ?? $guard->rank_designation ?? 'Guard' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Salary type</dt>
                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ $guard->position?->salaryLabel() ?? 'Variable (shift)' }}</dd>
            </div>
        </dl>
        @if ($currentPromotion)
            <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                Previous position: {{ $currentPromotion->previous_position ?: 'Guard' }}.
                Current position: {{ $currentPromotion->position?->name }}.
                Promotion date: {{ $currentPromotion->effective_from->format('d M Y') }}.
            </p>
        @endif
    </div>

    <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-slate-100">Position history</h3>
    @if ($guard->promotions->isNotEmpty())
        <div class="psg-stack mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="data-table min-w-full">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Effective from</th>
                        <th>Effective to</th>
                        <th>Previous position</th>
                        <th>New position</th>
                        <th>Previous salary</th>
                        <th>New salary</th>
                        <th>Reason</th>
                        <th>Reference</th>
                        <th>Region</th>
                        <th>Authorized by</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($guard->promotions->sortByDesc(fn ($promotion) => $promotion->effective_from->toDateString()) as $promotion)
                        <tr>
                            <td data-label="Status">{{ $promotion->isScheduled() ? 'Scheduled' : ($promotion->isCurrent() ? 'Current' : 'Historical') }}</td>
                            <td data-label="Effective from">{{ $promotion->effective_from->format('Y-m-d') }}</td>
                            <td data-label="Effective to">{{ $promotion->effective_to?->format('Y-m-d') ?? '—' }}</td>
                            <td data-label="Previous position">{{ $promotion->previous_position ?: '—' }}</td>
                            <td data-label="New position">{{ $promotion->position?->name }}</td>
                            <td data-label="Previous salary">
                                @if ($canViewSalary ?? true)
                                    {{ $promotion->previous_salary !== null ? \App\Support\Money::format($promotion->previous_salary) : '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="New salary">
                                @if ($canViewSalary ?? true)
                                    {{ \App\Support\Money::format($promotion->new_salary) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="Reason">{{ $promotion->reason }}</td>
                            <td data-label="Reference">{{ $promotion->reference ?: '—' }}</td>
                            <td data-label="Region">{{ $promotion->region?->name ?? '—' }}</td>
                            <td data-label="Authorized by">{{ $promotion->approver?->name ?? '—' }}</td>
                            <td data-label="Recorded">{{ $promotion->created_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-2 text-sm text-slate-500">No position changes have been recorded. This employee is still on the guard roster.</p>
    @endif

    @if ($canPromote ?? false)
        <form method="POST" action="{{ route('guards.promotions.store', $guard) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 dark:border-slate-700">
            @csrf
            <div class="sm:col-span-2">
                <h3 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Promote employee</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Previous position: {{ $guard->position?->name ?? $guard->rank_designation ?? 'Guard' }}.
                    Previous salary: {{ \App\Support\Money::format($previousSalary) }}.
                    Employee ID {{ $guard->employment_id }} stays the same. A future date keeps them on the guard roster until that date.
                    Authorized by {{ auth()->user()->name }}.
                </p>
            </div>
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
            <div class="sm:col-span-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Record promotion</button>
            </div>
        </form>
    @endif
</section>

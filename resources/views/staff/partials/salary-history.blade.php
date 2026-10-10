@php
    $revisions = $staff->salaryRevisions;
    $currentRevision = $currentRevision ?? $revisions->first(fn ($revision) => $revision->isCurrent());
    $currentSalary = $currentSalary ?? \App\Support\Finance\PayrollRates::staffSalaryOn($staff, now());
    $canManageSalary = $canManageSalary ?? (auth()->user()?->can('manageSalary', $staff) ?? false);
    $position = $currentRevision?->job_title ?: $staff->job_title;
    $grade = $currentRevision?->grade ?: $staff->job_grade;
@endphp

<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
        <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Current employment</h2>
    </div>
    <dl class="grid gap-0 sm:grid-cols-3">
        <div class="border-b border-slate-100 px-3 py-2 sm:border-b-0 sm:border-r dark:border-slate-800">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Position</dt>
            <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                {{ $position ?: 'Not recorded' }}
                @if ($grade)
                    <span class="mt-0.5 block text-[10px] font-normal text-slate-500">Grade {{ $grade }}</span>
                @endif
            </dd>
        </div>
        <div class="border-b border-slate-100 px-3 py-2 sm:border-b-0 sm:border-r dark:border-slate-800">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Salary</dt>
            <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($currentSalary) }}</dd>
        </div>
        <div class="px-3 py-2">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Effective from</dt>
            <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                {{ $currentRevision?->effective_from?->format('d M Y') ?? 'Not recorded' }}
            </dd>
        </div>
    </dl>

    <div class="border-t border-slate-100 px-3 py-2 dark:border-slate-800">
        <h3 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Salary &amp; employment history</h3>
        <p class="mt-0.5 text-[11px] leading-snug text-slate-500">Each change stays on file. Payroll uses the salary in force during the pay period.</p>

        @if ($revisions->isEmpty())
            <p class="mt-2 text-[11px] text-slate-500">No salary changes have been recorded.</p>
        @else
            <ul class="mt-2 divide-y divide-slate-100 overflow-hidden rounded-md border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                @foreach ($revisions->sortByDesc(fn ($revision) => $revision->effective_from->toDateString()) as $revision)
                    <li class="px-2.5 py-2">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            @if ($revision->isCurrent())
                                <x-status-badge tone="emerald" label="Current" />
                            @elseif ($revision->isScheduled())
                                <x-status-badge tone="amber" label="Scheduled" />
                            @else
                                <x-status-badge tone="slate" label="Historical" />
                            @endif
                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">
                                {{ $revision->job_title ?: 'Position not recorded' }}
                                <span class="font-normal text-slate-500">· {{ \App\Support\Money::format($revision->salary) }}</span>
                            </p>
                        </div>
                        <p class="mt-1 text-[11px] leading-snug text-slate-600 dark:text-slate-300">
                            {{ $revision->effective_from->format('d M Y') }}
                            –
                            {{ $revision->effective_to?->format('d M Y') ?? 'Current' }}
                            · {{ $revision->change_type->label() }}
                            @if ($revision->previous_salary !== null)
                                · was {{ \App\Support\Money::format($revision->previous_salary) }}
                            @endif
                            @if ($revision->grade)
                                · grade {{ $revision->grade }}
                            @endif
                        </p>
                        <p class="mt-0.5 text-[10px] leading-snug text-slate-500">
                            @if ($revision->reason)
                                {{ $revision->reason }}
                            @endif
                            @if ($revision->approver)
                                · {{ $revision->approver->name }}
                                @if ($revision->approved_at)
                                    {{ $revision->approved_at->format('d M Y H:i') }}
                                @endif
                            @endif
                            @if ($revision->creator)
                                · recorded by {{ $revision->creator->name }}
                                @if ($revision->created_at)
                                    {{ $revision->created_at->format('d M Y H:i') }}
                                @endif
                            @endif
                            @if ($revision->notes)
                                · {{ $revision->notes }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($canManageSalary)
        <form method="POST" action="{{ route('staff.salary-revisions.store', $staff) }}" class="grid gap-2 border-t border-slate-100 px-3 py-3 sm:grid-cols-2 dark:border-slate-800">
            @csrf
            <div class="sm:col-span-2">
                <h3 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Record a salary or position change</h3>
                <p class="mt-0.5 text-[11px] leading-snug text-slate-500">
                    Current salary: {{ \App\Support\Money::format($currentSalary) }}.
                    Enter the same salary when only the position or grade changes.
                </p>
            </div>
            <x-form-field label="New salary ({{ config('psg.currency') }})" name="salary" type="number" step="0.01" min="0" :value="old('salary', $currentSalary)" :required="true" />
            <x-form-field label="Effective from" name="effective_from" type="date" :value="old('effective_from')" :required="true" />
            <x-form-field label="Change type" name="change_type" type="select" :required="true">
                @foreach (\App\Enums\StaffSalaryChangeType::cases() as $type)
                    @if ($type !== \App\Enums\StaffSalaryChangeType::Initial)
                        <option value="{{ $type->value }}" @selected(old('change_type') === $type->value)>{{ $type->label() }}</option>
                    @endif
                @endforeach
            </x-form-field>
            <x-form-field label="New position" name="job_title" :value="old('job_title', $position)" placeholder="Leave blank to keep the current position" />
            <x-form-field label="New grade" name="grade" :value="old('grade', $grade)" placeholder="Optional" />
            <x-form-field label="Reason" name="reason" :value="old('reason')" :required="true" class="sm:col-span-2" />
            <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" class="sm:col-span-2" />
            <div class="sm:col-span-2">
                <button type="submit" class="btn btn-primary">Record salary change</button>
            </div>
        </form>
    @endif
</section>

@php
    $revisions = $staff->salaryRevisions;
    $currentRevision = $currentRevision ?? $revisions->first(fn ($revision) => $revision->isCurrent());
    $currentSalary = $currentSalary ?? \App\Support\Finance\PayrollRates::staffSalaryOn($staff, now());
    $canManageSalary = $canManageSalary ?? (auth()->user()?->can('manageSalary', $staff) ?? false);
    $position = $currentRevision?->job_title ?: $staff->job_title;
    $grade = $currentRevision?->grade ?: $staff->job_grade;
@endphp

<section class="form-card">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Current employment</h2>
            <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-3">
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Position</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ $position ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Salary</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($currentSalary) }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Effective from</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">
                        {{ $currentRevision?->effective_from?->format('d M Y') ?? '—' }}
                    </dd>
                </div>
            </dl>
            @if ($grade)
                <p class="mt-2 text-[11px] text-slate-500">Grade {{ $grade }}</p>
            @endif
        </div>
    </div>

    <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-slate-100">Salary &amp; employment history</h3>
    <p class="mt-1 text-xs text-slate-500">Each change stays on file. Payroll uses the salary in force during the pay period.</p>

    @if ($revisions->isNotEmpty())
        <div class="psg-stack mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="data-table min-w-full">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Effective from</th>
                        <th>Effective to</th>
                        <th>Position</th>
                        <th>Grade</th>
                        <th>Previous salary</th>
                        <th>Salary</th>
                        <th>Change</th>
                        <th>Reason</th>
                        <th>Approved by</th>
                        <th>Approval date</th>
                        <th>Created by</th>
                        <th>Created</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($revisions->sortByDesc(fn ($revision) => $revision->effective_from->toDateString()) as $revision)
                        <tr>
                            <td data-label="Status">
                                @if ($revision->isCurrent())
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50">Current</span>
                                @elseif ($revision->isScheduled())
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 ring-1 ring-amber-100">Scheduled</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">Historical</span>
                                @endif
                            </td>
                            <td data-label="Effective from">{{ $revision->effective_from->format('Y-m-d') }}</td>
                            <td data-label="Effective to">{{ $revision->effective_to?->format('Y-m-d') ?? '—' }}</td>
                            <td data-label="Position">{{ $revision->job_title ?: '—' }}</td>
                            <td data-label="Grade">{{ $revision->grade ?: '—' }}</td>
                            <td data-label="Previous salary">{{ $revision->previous_salary !== null ? \App\Support\Money::format($revision->previous_salary) : '—' }}</td>
                            <td data-label="Salary" class="font-medium">{{ \App\Support\Money::format($revision->salary) }}</td>
                            <td data-label="Change">{{ $revision->change_type->label() }}</td>
                            <td data-label="Reason">{{ $revision->reason ?: '—' }}</td>
                            <td data-label="Approved by">{{ $revision->approver?->name ?? '—' }}</td>
                            <td data-label="Approval date">{{ $revision->approved_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td data-label="Created by">{{ $revision->creator?->name ?? '—' }}</td>
                            <td data-label="Created">{{ $revision->created_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td data-label="Notes">{{ $revision->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-3 text-sm text-slate-500">No salary changes have been recorded.</p>
    @endif

    @if ($canManageSalary)
        <form method="POST" action="{{ route('staff.salary-revisions.store', $staff) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 dark:border-slate-700">
            @csrf
            <div class="sm:col-span-2">
                <h3 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Record a salary or position change</h3>
                <p class="mt-1 text-xs text-slate-500">
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
                <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Record salary change</button>
            </div>
        </form>
    @endif
</section>

<section class="form-card">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Salary history</h2>
            <p class="mt-1 text-xs text-slate-500">Each change is kept. Payroll uses the salary in force on the work date.</p>
        </div>
        <div class="text-right">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Current salary</p>
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Money::format($currentSalary) }}</p>
            <p class="text-[10px] text-slate-500">
                @if ($currentRevision)
                    Effective {{ $currentRevision->effective_from->format('d M Y') }}
                @else
                    No salary history yet
                @endif
            </p>
        </div>
    </div>

    @if ($guard->salaryRevisions->isNotEmpty())
        <div class="psg-stack mt-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="data-table min-w-full">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Effective from</th>
                        <th>Effective to</th>
                        <th>Previous salary</th>
                        <th>New salary</th>
                        <th>Reason</th>
                        <th>Approved by</th>
                        <th>Approval date</th>
                        <th>Notes</th>
                        <th>Created by</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($guard->salaryRevisions->sortByDesc(fn ($revision) => $revision->effective_from->toDateString()) as $revision)
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
                            <td data-label="Effective from">{{ $revision->effective_from->format('d M Y') }}</td>
                            <td data-label="Effective to">{{ $revision->effective_to?->format('d M Y') ?? '—' }}</td>
                            <td data-label="Previous salary">{{ $revision->previous_salary !== null ? \App\Support\Money::format($revision->previous_salary) : '—' }}</td>
                            <td data-label="New salary" class="font-medium">{{ \App\Support\Money::format($revision->salary) }}</td>
                            <td data-label="Reason">{{ $revision->reason->label() }}</td>
                            <td data-label="Approved by">{{ $revision->approver?->name ?? '—' }}</td>
                            <td data-label="Approval date">{{ $revision->approved_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td data-label="Notes">{{ $revision->notes ?: '—' }}</td>
                            <td data-label="Created by">{{ $revision->creator?->name ?? '—' }}</td>
                            <td data-label="Created">{{ $revision->created_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-4 text-sm text-slate-500">No salary changes have been recorded.</p>
    @endif

    @if ($canManageSalary)
        <form method="POST" action="{{ route('guards.salary-revisions.store', $guard) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 dark:border-slate-700">
            @csrf
            <div class="sm:col-span-2">
                <h3 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Record a salary change</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Current salary before this change: {{ \App\Support\Money::format($currentSalary) }}.
                    @if ($currentRevision)
                        It has applied since {{ $currentRevision->effective_from->format('d M Y') }}.
                    @endif
                </p>
            </div>
            <x-form-field label="New monthly salary ({{ config('psg.currency') }})" name="salary" type="number" step="0.01" min="0" :required="true" :value="old('salary')" />
            <x-form-field label="Effective from" name="effective_from" type="date" :required="true" :value="old('effective_from')" />
            <x-form-field label="Reason" name="reason" type="select" :required="true">
                <option value="">Select a reason</option>
                @foreach (\App\Enums\SalaryChangeReason::cases() as $reason)
                    @if ($reason !== \App\Enums\SalaryChangeReason::Initial)
                        <option value="{{ $reason->value }}" @selected(old('reason') === $reason->value)>{{ $reason->label() }}</option>
                    @endif
                @endforeach
            </x-form-field>
            <x-form-field label="Notes" name="notes" :value="old('notes')" help="Optional context for the approval." />
            <div class="sm:col-span-2">
                <button type="submit" class="btn btn-primary">Record salary change</button>
            </div>
        </form>
    @endif
</section>

@if ($canManageFinance ?? false)
<section class="form-card">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Salary advances</h2>
            <p class="mt-1 text-xs text-slate-500">Outstanding advances auto-deduct on payroll calculate (installment or full balance).</p>
        </div>
    </div>

    @error('advance')
        <p class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $message }}</p>
    @enderror

    @if ($guard->salaryAdvances->where('is_active', true)->where('balance_remaining', '>', 0)->isNotEmpty())
        <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Advance</th>
                        <th>Balance</th>
                        <th>Installment</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($guard->salaryAdvances->where('is_active', true)->where('balance_remaining', '>', 0) as $advance)
                        <tr>
                            <td>
                                <p class="font-medium">{{ $advance->label }}</p>
                                <p class="text-[10px] text-slate-500">Original {{ \App\Support\Money::format($advance->original_amount) }}</p>
                            </td>
                            <td>{{ \App\Support\Money::format($advance->balance_remaining) }}</td>
                            <td>{{ $advance->monthly_installment ? \App\Support\Money::format($advance->monthly_installment) : 'Full balance' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('guards.advances.write-off', [$guard, $advance]) }}" class="inline" onsubmit="return confirm('Write off remaining balance on this advance?');">
                                    @csrf
                                    <button type="submit" class="text-xs text-rose-600 hover:underline">Write off</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-4 text-sm text-slate-500">No active salary advances.</p>
    @endif

    <form method="POST" action="{{ route('guards.advances.store', $guard) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 dark:border-slate-700">
        @csrf
        <x-form-field label="Description" name="label" :value="old('label')" :required="true" placeholder="e.g. Emergency advance" />
        <x-form-field label="Amount ({{ config('psg.currency') }})" name="original_amount" type="number" step="0.01" min="0.01" :required="true" />
        <x-form-field label="Monthly installment" name="monthly_installment" type="number" step="0.01" min="0.01" help="Optional — leave blank to recover full balance on next payroll." />
        <x-form-field label="Notes" name="notes" :value="old('notes')" />
        <div class="sm:col-span-2">
            <button type="submit" class="btn btn-primary">Record advance</button>
        </div>
    </form>
</section>
@endif

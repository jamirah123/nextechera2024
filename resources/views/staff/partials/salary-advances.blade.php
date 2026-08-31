@if ($canManageFinance ?? false)
<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
    <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
        <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Salary advances</h2>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Deducted on payroll (PAYE + NSSF; no uniform).</p>
    </div>

    <div class="px-3 py-2">
        @error('advance')
            <p class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">{{ $message }}</p>
        @enderror

        @if ($staff->salaryAdvances->where('is_active', true)->where('balance_remaining', '>', 0)->isNotEmpty())
            <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="data-table text-xs">
                    <thead>
                        <tr>
                            <th>Advance</th>
                            <th>Balance</th>
                            <th>Installment</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff->salaryAdvances->where('is_active', true)->where('balance_remaining', '>', 0) as $advance)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $advance->label }}</p>
                                    <p class="text-[10px] text-slate-500">Original {{ \App\Support\Money::format($advance->original_amount) }}</p>
                                </td>
                                <td>{{ \App\Support\Money::format($advance->balance_remaining) }}</td>
                                <td>{{ $advance->monthly_installment ? \App\Support\Money::format($advance->monthly_installment) : 'Full balance' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('staff.advances.write-off', [$staff, $advance]) }}" class="inline" onsubmit="return confirm('Write off remaining balance on this advance?');">
                                        @csrf
                                        <button type="submit" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Write off</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-xs text-slate-500 dark:text-slate-400">No active salary advances.</p>
        @endif

        <form method="POST" action="{{ route('staff.advances.store', $staff) }}" class="mt-2 grid gap-2 border-t border-slate-100 pt-2 sm:grid-cols-2 dark:border-slate-700">
            @csrf
            <x-form-field label="Description" name="label" :value="old('label')" :required="true" placeholder="e.g. Emergency advance" />
            <x-form-field label="Amount ({{ config('psg.currency') }})" name="original_amount" type="number" step="0.01" min="0.01" :required="true" />
            <x-form-field label="Monthly installment" name="monthly_installment" type="number" step="0.01" min="0.01" help="Optional — leave blank to recover full balance on next payroll." />
            <x-form-field label="Notes" name="notes" :value="old('notes')" />
            <div class="sm:col-span-2">
                <button type="submit" class="btn btn-primary">Record advance</button>
            </div>
        </form>
    </div>
</section>
@endif

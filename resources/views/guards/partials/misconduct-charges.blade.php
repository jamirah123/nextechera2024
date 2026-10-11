@if ($canManageFinance ?? false)
<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
        <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Misconduct charges</h2>
        <p class="text-[10px] text-slate-500 dark:text-slate-400">Recovered on payroll, separate from salary advances.</p>
    </div>

    <div class="space-y-2 px-3 py-2">
        @error('charge')
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">{{ $message }}</p>
        @enderror

        @if ($guard->misconductCharges->where('is_active', true)->where('balance_remaining', '>', 0)->isNotEmpty())
            <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="data-table text-xs">
                    <thead>
                        <tr>
                            <th>Charge</th>
                            <th>Incident</th>
                            <th>Balance</th>
                            <th>Installment</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($guard->misconductCharges->where('is_active', true)->where('balance_remaining', '>', 0) as $charge)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $charge->reason }}</p>
                                    <p class="text-[10px] text-slate-500">Original {{ \App\Support\Money::format($charge->original_amount) }}</p>
                                </td>
                                <td>{{ $charge->incident_on?->format('d M Y') ?? '—' }}</td>
                                <td class="font-semibold tabular-nums">{{ \App\Support\Money::format($charge->balance_remaining) }}</td>
                                <td>{{ $charge->monthly_installment ? \App\Support\Money::format($charge->monthly_installment) : 'Full balance' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('guards.charges.write-off', [$guard, $charge]) }}" class="inline" onsubmit="return confirm('Write off the remaining balance on this charge?');">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-400">Write off</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-xs text-slate-500 dark:text-slate-400">No active misconduct charges.</p>
        @endif

        <form method="POST" action="{{ route('guards.charges.store', $guard) }}" class="grid gap-2 border-t border-slate-100 pt-2 sm:grid-cols-4 dark:border-slate-700">
            @csrf
            <x-form-field label="Reason" name="charge_reason" :value="old('charge_reason')" :required="true" placeholder="Lost company radio" />
            <x-form-field label="Incident date" name="charge_incident_on" type="date" :value="old('charge_incident_on')" />
            <x-form-field label="Amount ({{ config('psg.currency') }})" name="charge_amount" type="number" step="0.01" min="0.01" :required="true" placeholder="0.00" />
            <x-form-field label="Monthly installment" name="charge_installment" type="number" step="0.01" min="0.01" placeholder="Full balance if blank" />
            <x-form-field label="Notes" name="charge_notes" :value="old('charge_notes')" placeholder="Optional" class="sm:col-span-3" />
            <div class="field">
                <span class="field__label hidden sm:block" aria-hidden="true">&nbsp;</span>
                <button type="submit" class="btn btn-primary w-full">Record charge</button>
            </div>
        </form>
    </div>
</section>
@endif

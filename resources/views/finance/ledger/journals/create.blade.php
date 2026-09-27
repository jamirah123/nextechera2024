@extends('layouts.app')

@section('title', 'New journal')
@section('page-title', 'New journal')

@section('content')
<div class="space-y-3" x-data="manualJournal()">
    <x-page-header title="Manual journal" subtitle="Post a balanced double-entry journal into an open accounting period.">
        <x-slot:actions>
            <a href="{{ route('ledger.journals.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Back</a>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->has('journal'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ $errors->first('journal') }}</div>
    @endif

    <form method="POST" action="{{ route('ledger.journals.store') }}" class="space-y-3">
        @csrf
        <section class="grid gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-900">
            <x-form-field label="Date" name="journal_date" type="date" :value="old('journal_date', now()->toDateString())" required />
            <x-form-field label="Description" name="description" :value="old('description')" class="sm:col-span-2" required />
        </section>

        <section class="psg-stack overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Account</th>
                        <th class="px-3 py-2">Memo</th>
                        <th class="px-3 py-2">Debit</th>
                        <th class="px-3 py-2">Credit</th>
                        <th class="w-12 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, index) in lines" :key="index">
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-2" data-label="Account">
                                <select :name="'lines['+index+'][account_id]'" x-model="line.account_id" class="field__control" required>
                                    <option value="">Select</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2" data-label="Memo"><input type="text" :name="'lines['+index+'][memo]'" x-model="line.memo" class="field__control"></td>
                            <td class="px-3 py-2" data-label="Debit"><input type="number" step="0.01" min="0" :name="'lines['+index+'][debit]'" x-model="line.debit" class="field__control"></td>
                            <td class="px-3 py-2" data-label="Credit"><input type="number" step="0.01" min="0" :name="'lines['+index+'][credit]'" x-model="line.credit" class="field__control"></td>
                            <td class="px-3 py-2" data-label=""><button type="button" class="text-rose-600" @click="remove(index)" x-show="lines.length > 2">Remove line</button></td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="bg-slate-50 text-xs font-semibold">
                    <tr>
                        <td class="px-3 py-2" colspan="2" data-label="">Totals</td>
                        <td class="px-3 py-2 tabular-nums" data-label="Debit" x-text="format(debitTotal())"></td>
                        <td class="px-3 py-2 tabular-nums" data-label="Credit" x-text="format(creditTotal())"></td>
                        <td data-label="#"></td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <div class="flex items-center justify-between gap-2">
            <button type="button" class="text-xs font-semibold text-brand-700 hover:underline" @click="add()">Add line</button>
            <p class="text-[11px]" :class="balanced() ? 'text-emerald-700' : 'text-rose-700'" x-text="balanced() ? 'Balanced' : 'Out of balance'"></p>
            <button type="submit" class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800" :disabled="!balanced()">Post journal</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('manualJournal', () => ({
        lines: [{account_id:'', memo:'', debit:'', credit:''}, {account_id:'', memo:'', debit:'', credit:''}],
        add() { this.lines.push({account_id:'', memo:'', debit:'', credit:''}); },
        remove(index) { if (this.lines.length > 2) this.lines.splice(index, 1); },
        num(value) { return parseFloat(value || 0) || 0; },
        debitTotal() { return this.lines.reduce((s, l) => s + this.num(l.debit), 0); },
        creditTotal() { return this.lines.reduce((s, l) => s + this.num(l.credit), 0); },
        balanced() { return this.debitTotal() > 0 && Math.abs(this.debitTotal() - this.creditTotal()) < 0.009; },
        format(value) { return value.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 }); },
    }));
});
</script>
@endpush
@endsection

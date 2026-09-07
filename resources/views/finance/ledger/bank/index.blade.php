@extends('layouts.app')

@section('title', 'Bank reconciliation')
@section('page-title', 'Bank reconciliation')

@section('content')
<div class="space-y-3">
    <x-page-header title="Bank reconciliation" subtitle="Multiple bank accounts, each posting to its own cash GL account.">
        <x-slot:actions>
            <a href="{{ route('ledger.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">Ledger hub</a>
        </x-slot:actions>
    </x-page-header>

    @if ($canManage)
        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Add bank account</h2>
            <p class="mt-1 text-[11px] text-slate-500">Leave GL blank to auto-create a 11xx cash account for this bank.</p>
            <form method="POST" action="{{ route('ledger.bank.store') }}" class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
                @csrf
                <x-form-field label="Name" name="name" :value="old('name')" required />
                <x-form-field label="Bank" name="bank_name" :value="old('bank_name')" />
                <x-form-field label="Account number" name="account_number" :value="old('account_number')" />
                <x-form-field label="Opening balance" name="opening_balance" type="number" :value="old('opening_balance', 0)" step="0.01" />
                <x-form-field label="GL account" name="gl_account_id" type="select">
                    <option value="">Auto-create</option>
                    @foreach ($glAccounts as $gl)
                        <option value="{{ $gl->id }}" @selected((string) old('gl_account_id') === (string) $gl->id)>{{ $gl->label() }}</option>
                    @endforeach
                </x-form-field>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-700 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-800">Create</button>
            </form>
        </section>
    @endif

    @if ($accounts->isEmpty())
        <x-empty-state title="No bank accounts" description="A default operating account is created with the ledger migration." icon="wallet" />
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($accounts as $account)
                <a href="{{ route('ledger.bank.show', $account) }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition hover:border-brand-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $account->name }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">{{ $account->bank_name }} · {{ $account->account_number ?: 'No account number' }}</p>
                    <p class="mt-2 text-[11px] text-slate-500">GL {{ $account->glAccount?->label() }}</p>
                    <p class="mt-3 text-xs font-semibold {{ $account->unmatched_count > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                        {{ number_format($account->unmatched_count) }} unmatched
                    </p>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection

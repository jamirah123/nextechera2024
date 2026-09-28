@extends('layouts.app')

@section('title', 'Leave types')
@section('page-title', 'Leave types')

@section('content')
<div class="space-y-3">
    <x-page-header title="Leave types" subtitle="Paid rules, entitlements, and whether a document or approval is required." :back="route('leaves.index')" />

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
            <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Pay</th>
                    <th class="px-3 py-2">Max days</th>
                    <th class="px-3 py-2">Rules</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($types as $type)
                    <tr>
                        <td class="px-3 py-2">
                            <p class="font-semibold text-slate-900">{{ $type->name }}</p>
                            <p class="text-[10px] text-slate-500">{{ $type->code }}</p>
                        </td>
                        <td class="px-3 py-2">{{ $type->is_paid ? $type->pay_percent.'% paid' : 'Unpaid' }}</td>
                        <td class="px-3 py-2">{{ $type->max_days_per_year ?? 'Unlimited' }}</td>
                        <td class="px-3 py-2 text-slate-600">
                            {{ $type->requires_approval ? 'Approval' : 'No approval' }}
                            · {{ $type->requires_document ? 'Document' : 'No document' }}
                            · {{ $type->count_weekends ? 'Weekends count' : 'Weekdays' }}
                        </td>
                        <td class="px-3 py-2">{{ $type->is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @if ($canManage)
        <form method="POST" action="{{ route('leave-types.store') }}" class="form-card grid gap-3 sm:grid-cols-2">
            @csrf
            <h2 class="text-sm font-semibold text-slate-900 sm:col-span-2">Add a leave type</h2>
            <x-form-field label="Code" name="code" :value="old('code')" :required="true" />
            <x-form-field label="Name" name="name" :value="old('name')" :required="true" />
            <x-form-field label="Pay percent" name="pay_percent" type="number" min="0" max="100" step="0.01" :value="old('pay_percent', 100)" :required="true" />
            <x-form-field label="Maximum days per year" name="max_days_per_year" type="number" min="0" step="0.5" :value="old('max_days_per_year')" />
            <x-form-field label="Eligibility" name="eligibility" type="select" :required="true">
                <option value="all">All employees</option>
                <option value="guards">Guards</option>
                <option value="staff">Staff</option>
            </x-form-field>
            <x-form-field label="Description" name="description" :value="old('description')" class="sm:col-span-2" />
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="requires_approval" value="1" checked> Approval required</label>
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="requires_document" value="1"> Supporting document required</label>
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="count_weekends" value="1"> Count weekends</label>
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="count_public_holidays" value="1"> Count public holidays</label>
            <div class="sm:col-span-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white">Save leave type</button>
            </div>
        </form>
    @endif
</div>
@endsection

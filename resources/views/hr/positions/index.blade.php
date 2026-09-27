@extends('layouts.app')

@section('title', 'Positions')
@section('page-title', 'Positions')
@section('page-subtitle', 'How each job is classified and paid')

@section('content')
<div class="space-y-4">
    <x-page-header title="Positions" subtitle="Guard, staff, supervisor and management positions. Payroll and the rosters follow these settings." :back="route('staff.index')" />

    <section class="form-card overflow-x-auto">
        <table class="data-table min-w-full">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Guard</th>
                    <th>Staff</th>
                    <th>Supervisor</th>
                    <th>Management</th>
                    <th>Deployment</th>
                    <th>Shifts</th>
                    <th>Overtime</th>
                    <th>Salary</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($positions as $position)
                    <tr>
                        <td class="font-medium">{{ $position->name }}</td>
                        <td>{{ $position->is_guard_position ? 'Yes' : '—' }}</td>
                        <td>{{ $position->is_staff_position ? 'Yes' : '—' }}</td>
                        <td>{{ $position->is_supervisor_position ? 'Yes' : '—' }}</td>
                        <td>{{ $position->is_management_position ? 'Yes' : '—' }}</td>
                        <td>{{ $position->eligible_for_deployment ? 'Yes' : '—' }}</td>
                        <td>{{ $position->eligible_for_shifts ? 'Yes' : '—' }}</td>
                        <td>{{ $position->eligible_for_overtime ? 'Yes' : '—' }}</td>
                        <td>{{ $position->salaryLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @if ($canManage)
        <form method="POST" action="{{ route('positions.store') }}" class="form-card grid gap-3 sm:grid-cols-2">
            @csrf
            <div class="sm:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900">Add a position</h2>
                <p class="mt-1 text-xs text-slate-500">New positions are available on the promotion form without a code change.</p>
            </div>
            <x-form-field label="Name" name="name" :value="old('name')" :required="true" />
            <x-form-field label="Code" name="code" :value="old('code')" :required="true" help="Short unique code, such as senior_guard." />
            <x-form-field label="Salary type" name="salary_type" type="select" :required="true">
                <option value="fixed" @selected(old('salary_type') === 'fixed')>Fixed monthly</option>
                <option value="variable" @selected(old('salary_type', 'variable') === 'variable')>Variable (shift)</option>
            </x-form-field>
            <div class="grid gap-2 text-xs sm:col-span-2 sm:grid-cols-2">
                @foreach ([
                    'is_guard_position' => 'Guard position (stays on the guard roster)',
                    'is_staff_position' => 'Staff position',
                    'is_supervisor_position' => 'Supervisor position',
                    'is_management_position' => 'Management position',
                    'eligible_for_deployment' => 'Eligible for deployment',
                    'eligible_for_shifts' => 'Eligible for shift scheduling',
                    'eligible_for_overtime' => 'Eligible for overtime when a shift is recorded as overtime',
                ] as $field => $label)
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Save position</button>
            </div>
        </form>
    @endif
</div>
@endsection

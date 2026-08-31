@extends('layouts.app')

@section('title', 'New Payroll Run')
@section('page-title', 'New Payroll Run')

@section('content')
<div class="space-y-3">
    <x-page-header title="Open payroll period" subtitle="Calculate pulls completed shifts for guards and fixed salaries for registered staff." :back="route('payroll.index')" />

    <div class="form-page space-y-3">
        <section class="form-card space-y-4">
            <p class="form-alert form-alert--info text-xs">
                Payroll can only be opened for completed months. The latest eligible period is <strong>{{ $lastClosedLabel }}</strong>.
            </p>

            @if ($existingRuns->isNotEmpty())
                <div class="space-y-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Existing payroll periods</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Paid months are closed for the same scope. Other statuses mean a run is already open for that period.</p>
                    </div>
                    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="data-table text-xs">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Scope</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($existingRuns as $existingRun)
                                    <tr>
                                        <td class="font-semibold">{{ $existingRun->periodLabel() }}</td>
                                        <td class="text-slate-600 dark:text-slate-400">
                                            @if ($existingRun->site)
                                                {{ $existingRun->site->name }}
                                            @elseif ($existingRun->region)
                                                {{ $existingRun->region->name }}
                                            @else
                                                Company-wide
                                            @endif
                                        </td>
                                        <td>
                                            <x-status-badge :tone="$existingRun->status->tone()" :label="$existingRun->status->label()" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($existingRuns->contains(fn ($run) => $run->status === \App\Enums\PayrollRunStatus::Paid))
                        <p class="text-xs text-slate-500 dark:text-slate-400">Months marked <strong class="text-slate-700 dark:text-slate-200">Paid</strong> cannot be opened again unless the Managing Director deletes that run.</p>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('payroll.store') }}" class="space-y-4 border-t border-slate-100 pt-4 dark:border-slate-700">
                @csrf

                @error('payroll')
                    <p class="form-alert form-alert--error text-sm">{{ $message }}</p>
                @enderror

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-form-field label="Year" name="period_year" type="number" :value="$defaultYear" :required="true" min="2020" :max="now()->year" />
                    <x-form-field label="Month" name="period_month" type="select" :required="true">
                        @for ($m = 1; $m <= 12; $m++)
                            @php($closed = \App\Services\Finance\PayrollRunService::isPeriodClosed($defaultYear, $m))
                            <option value="{{ $m }}" @selected($defaultMonth === $m) @disabled(! $closed)>
                                {{ \Carbon\Carbon::create(null, $m)->format('F') }}@unless($closed) (in progress)@endunless
                            </option>
                        @endfor
                    </x-form-field>
                    <x-form-field label="Region (optional)" name="region_id" type="select">
                        <option value="">All regions</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->name }}</option>
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Site (optional)" name="site_id" type="select">
                        <option value="">All sites</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->name }} ({{ $site->code }})</option>
                        @endforeach
                    </x-form-field>
                </div>

                <x-form-field label="Notes" name="notes" type="textarea" :value="old('notes')" placeholder="Optional notes for finance review." />

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Create payroll run</button>
                    <a href="{{ route('payroll.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection

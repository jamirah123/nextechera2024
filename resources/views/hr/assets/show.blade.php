@extends('layouts.app')

@section('title', $issuance->reference)
@section('page-title', 'Asset issuance')
@section('page-subtitle', $issuance->recipientName())

@section('content')
@php $empty = '<span class="font-normal text-slate-400">Not recorded</span>'; @endphp
<div class="space-y-3">
    <x-page-header
        size="sm"
        :title="$issuance->reference"
        :subtitle="$issuance->recipient_type?->label().' · '.$issuance->recipientName().' · '.$issuance->issued_at->format('d M Y')"
        :back="route('assets.index')"
    >
        <x-slot:actions>
            <x-status-badge :tone="$issuance->status->tone()" :label="$issuance->status->label()" />
            @if ($issuance->isStaffRecipient() && $issuance->assignedStaff)
                @can('view', $issuance->assignedStaff)
                    <a href="{{ route('staff.show', $issuance->assignedStaff) }}" class="btn btn-secondary">Staff profile</a>
                @endcan
            @elseif ($issuance->assignedGuard)
                @can('view', $issuance->assignedGuard)
                    <a href="{{ route('guards.show', $issuance->assignedGuard) }}" class="btn btn-secondary">Guard profile</a>
                @endcan
            @endif
            @if ($canManage && $canModify)
                <a href="{{ route('assets.edit', $issuance) }}" class="btn btn-secondary">Edit</a>
            @endif
            @if (($canDelete ?? false) && $canModify)
                <x-delete-button
                    :action="route('assets.destroy', $issuance)"
                    label="Delete"
                    title="Delete issuance?"
                    confirm="Permanently delete this asset issuance? Use this if assets were issued to the wrong guard by mistake. This cannot be undone."
                    confirm-label="Yes, delete issuance"
                />
            @endif
            @if ($canManage && $issuance->outstandingLineCount() > 0)
                <a href="#return-form" class="btn btn-primary">Record return</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($canManage && ! $canModify && ($modificationBlockers ?? []) !== [])
        <p class="form-alert form-alert--warning">{{ ($modificationBlockers[0] ?? 'This issuance can no longer be edited or deleted.').' Use returns to adjust outstanding items.' }}</p>
    @endif

    @error('asset')
        <p class="form-alert form-alert--error">{{ $message }}</p>
    @enderror

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Issuance details</h2>
        </div>
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $issuance->recipient_type?->label() ?? 'Recipient' }}</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $issuance->recipientName() }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Employment ID</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($issuance->recipientEmploymentId())
                        {{ $issuance->recipientEmploymentId() }}
                    @else
                        {!! $empty !!}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Role</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    @if ($issuance->isStaffRecipient())
                        {{ $issuance->assignedStaff?->job_title ?: $issuance->assignedStaff?->position?->name ?: 'Staff' }}
                    @else
                        {{ $issuance->assignedGuard?->rank_designation ?: 'Guard' }}
                    @endif
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Issuance type</dt>
                <dd class="mt-0.5"><x-status-badge :tone="$issuance->issuance_type->tone()" :label="$issuance->issuance_type->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:border-r dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Issued on</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $issuance->issued_at->format('d M Y') }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Issued by</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $issuance->issuer?->name ?? 'Not recorded' }}</dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2 sm:col-span-2 lg:col-span-3 dark:border-slate-800">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{!! filled($issuance->notes) ? e($issuance->notes) : $empty !!}</dd>
            </div>
        </dl>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50">
            <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Issued items</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs dark:divide-slate-800">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-3 py-2">Item</th>
                        <th class="px-3 py-2">Size</th>
                        <th class="px-3 py-2">Serial</th>
                        <th class="px-3 py-2">Qty</th>
                        <th class="px-3 py-2">Returned</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($issuance->lines as $line)
                        @php $lineValue = (float) $line->unit_value * (int) $line->quantity; @endphp
                        <tr>
                            <td class="px-3 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $line->asset_category->label() }}</p>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $line->description ?: 'Not recorded' }}</p>
                                @if ((float) $line->recovery_amount > 0)
                                    <p class="mt-0.5 text-[10px] text-slate-500">Recovery {{ \App\Support\Money::format($line->recoveryBalance()) }} of {{ \App\Support\Money::format($line->recovery_amount) }}</p>
                                @endif
                                @if ($line->notes)
                                    <p class="mt-0.5 text-[10px] text-slate-500">{{ $line->notes }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $line->size ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $line->serial_number ?: '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $line->quantity }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $line->quantity_returned }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $lineValue > 0 ? \App\Support\Money::format($lineValue) : '—' }}</td>
                            <td class="px-3 py-2"><x-status-badge :tone="$line->status->tone()" :label="$line->status->label()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage && $issuance->outstandingLineCount() > 0)
        <section id="return-form" class="rounded-lg border border-emerald-200 bg-white p-2 shadow-sm dark:border-emerald-800 dark:bg-slate-800">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Record return</h2>
            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Mark lost or written-off items, or tick termination return to clear the whole issue.</p>

            <form method="POST" action="{{ route('assets.return', $issuance) }}" class="mt-2 space-y-2">
                @csrf

                @foreach ($issuance->lines as $line)
                    @continue($line->quantityOutstanding() <= 0 || in_array($line->status, [\App\Enums\AssetLineStatus::WrittenOff, \App\Enums\AssetLineStatus::Lost], true))
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-2 dark:border-slate-700 dark:bg-slate-900/40">
                        <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $line->displayLabel() }} <span class="font-normal text-slate-500">· {{ $line->quantityOutstanding() }} of {{ $line->quantity }} out</span></p>
                        <div class="mt-1 grid gap-2 sm:grid-cols-3">
                            <x-form-field
                                label="Return qty"
                                :name="'lines['.$line->id.'][return_qty]'"
                                type="number"
                                :value="old('lines.'.$line->id.'.return_qty', $line->quantityOutstanding())"
                                min="0"
                                :max="$line->quantityOutstanding()"
                            />
                            <x-form-field label="Disposition" :name="'lines['.$line->id.'][disposition]'" type="select">
                                <option value="returned">Returned to stores</option>
                                <option value="lost">Lost / not returned</option>
                                <option value="written_off">Written off</option>
                            </x-form-field>
                            <x-form-field label="Notes" :name="'lines['.$line->id.'][notes]'" :value="old('lines.'.$line->id.'.notes')" />
                        </div>
                    </div>
                @endforeach

                <label class="form-checkbox border-amber-200 bg-amber-50 hover:border-amber-300 hover:bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 dark:hover:border-amber-700 dark:hover:bg-amber-950/50">
                    <input type="hidden" name="termination_return" value="0">
                    <input type="checkbox" name="termination_return" value="1" class="form-checkbox__input" @checked(old('termination_return'))>
                    <span class="form-checkbox__content">
                        <span class="form-checkbox__label text-amber-950 dark:text-amber-100">Termination return</span>
                        <span class="form-checkbox__help text-amber-800 dark:text-amber-200/80">Return all outstanding quantities on this issuance (for exit clearance).</span>
                    </span>
                </label>

                <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Save return</button>
            </form>
        </section>
    @endif
</div>
@endsection

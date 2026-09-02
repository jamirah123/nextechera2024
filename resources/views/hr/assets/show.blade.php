@extends('layouts.app')

@section('title', $issuance->reference)
@section('page-title', 'Asset issuance')

@section('content')
<div class="space-y-3">
    <x-page-header
        :title="$issuance->reference"
        :subtitle="$issuance->assignedGuard?->full_name.' · '.$issuance->issued_at->format('d M Y')"
        :back="route('assets.index')"
    >
        <x-slot:actions>
            <a href="{{ route('guards.show', $issuance->assignedGuard) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Guard profile</a>
            @if ($canManage && $canModify)
                <a href="{{ route('assets.edit', $issuance) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    <x-icon name="pencil" class="h-3.5 w-3.5" />
                    Edit
                </a>
                <x-delete-button
                    :action="route('assets.destroy', $issuance)"
                    label="Delete"
                    title="Delete issuance?"
                    confirm="Permanently delete this asset issuance? Use this if assets were issued to the wrong guard by mistake. This cannot be undone."
                    confirm-label="Yes, delete issuance"
                />
            @endif
            @if ($canManage && $issuance->outstandingLineCount() > 0)
                <a href="#return-form" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800">Record return</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($canManage && ! $canModify && ($modificationBlockers ?? []) !== [])
        <p class="form-alert form-alert--warning">{{ ($modificationBlockers[0] ?? 'This issuance can no longer be edited or deleted.').' Use returns to adjust outstanding items.' }}</p>
    @endif

    @error('asset')
        <p class="form-alert form-alert--error">{{ $message }}</p>
    @enderror

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <dl class="grid gap-0 sm:grid-cols-2">
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Guard</dt>
                <dd class="mt-1 font-semibold">
                    <a href="{{ route('guards.show', $issuance->assignedGuard) }}" class="text-brand-800 hover:underline">{{ $issuance->assignedGuard?->full_name }}</a>
                </dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Issuance type</dt>
                <dd class="mt-1"><x-status-badge :tone="$issuance->issuance_type->tone()" :label="$issuance->issuance_type->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5 sm:border-r">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1"><x-status-badge :tone="$issuance->status->tone()" :label="$issuance->status->label()" /></dd>
            </div>
            <div class="border-b border-slate-100 px-3 py-2.5">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Issued by</dt>
                <dd class="mt-1 font-semibold">{{ $issuance->issuer?->name ?? '—' }}</dd>
            </div>
            @if ($issuance->notes)
                <div class="px-3 py-2.5 sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Notes</dt>
                    <dd class="mt-1 text-sm text-slate-700">{{ $issuance->notes }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-3 py-2.5">
            <h2 class="text-sm font-semibold text-slate-900">Issued items</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Item</th>
                        <th class="px-3 py-2">Qty</th>
                        <th class="px-3 py-2">Returned</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2">Recovery</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($issuance->lines as $line)
                        <tr>
                            <td class="px-3 py-2">
                                <p class="font-semibold text-slate-900">{{ $line->displayLabel() }}</p>
                                @if ($line->notes)
                                    <p class="mt-0.5 text-[10px] text-slate-500">{{ $line->notes }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 tabular-nums">{{ $line->quantity }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $line->quantity_returned }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($line->unit_value * $line->quantity) }}</td>
                            <td class="px-3 py-2">
                                @if ((float) $line->recovery_amount > 0)
                                    <p class="tabular-nums">{{ \App\Support\Money::format($line->recoveryBalance()) }} due</p>
                                    <p class="text-[10px] text-slate-500">of {{ \App\Support\Money::format($line->recovery_amount) }}</p>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2"><x-status-badge :tone="$line->status->tone()" :label="$line->status->label()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage && $issuance->outstandingLineCount() > 0)
        <section id="return-form" class="rounded-lg border border-emerald-200 bg-white p-4 shadow-sm dark:border-emerald-800 dark:bg-slate-800">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Record return</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Use termination return when a guard is leaving employment. Mark items as lost if they will not be returned.</p>

            <form method="POST" action="{{ route('assets.return', $issuance) }}" class="mt-4 space-y-4">
                @csrf

                @foreach ($issuance->lines as $line)
                    @continue($line->quantityOutstanding() <= 0 || in_array($line->status, [\App\Enums\AssetLineStatus::WrittenOff, \App\Enums\AssetLineStatus::Lost], true))
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-900/40">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $line->displayLabel() }}</p>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ $line->quantityOutstanding() }} outstanding of {{ $line->quantity }}</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
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

@extends('layouts.app')

@section('title', $item->name)
@section('page-title', 'Asset')

@section('content')
@php $empty = '<span class="font-normal text-slate-400">Not recorded</span>'; @endphp
<div class="space-y-3">
    <x-page-header :title="$item->name" :subtitle="($item->asset_tag ?: $item->reference).' · '.$item->tracking_type->label()" size="sm" :back="route('procurement.stock.index')">
        <x-slot:actions>
            <x-status-badge :tone="$item->operational_status->tone()" :label="$item->operational_status->label()" />
            @if ($canManage && $item->isIssuable())
                <a href="{{ route('assets.create', ['stock_item_id' => $item->id]) }}" class="btn btn-primary">Issue</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($missing = $item->missingPurchaseDetails())
        <p class="form-alert form-alert--warning">Purchasing details still needed: {{ implode(', ', $missing) }}.</p>
    @endif

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Category' => $item->category->label(),
                'Condition' => $item->condition->label(),
                'On hand' => $item->quantity_on_hand.' '.$item->unit,
                'Issued' => (string) $item->quantity_issued,
                'Lost' => (string) $item->quantity_lost,
                'Damaged' => (string) $item->quantity_damaged,
                'Brand' => $item->brand,
                'Model' => $item->model,
                'Serial' => $item->serial_number,
                'Source' => $item->acquisition_source->label(),
                'Acquired' => $item->acquisition_date?->format('d M Y'),
                'Supplier' => $item->supplier?->name,
                'Purchase reference' => $item->purchase_reference,
                'Warranty' => $item->warranty_ends_on?->format('d M Y'),
                'Location' => $item->location,
                'Region' => $item->region?->name,
                'Registered by' => $item->creator?->name,
                'Registered' => $item->created_at?->format('d M Y H:i'),
            ] as $label => $value)
                <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                    <dd class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-slate-100">{!! filled($value) ? e($value) : $empty !!}</dd>
                </div>
            @endforeach
            @if ($canSeeCost)
                <div class="border-b border-slate-100 px-3 py-2">
                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Acquisition cost</dt>
                    <dd class="mt-0.5 text-xs font-semibold">{{ $item->unit_cost !== null ? \App\Support\Money::format($item->unit_cost) : 'Not recorded' }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Assignment history</h2>
        @if ($item->lines->isEmpty())
            <p class="mt-2 text-xs text-slate-500">Not assigned yet.</p>
        @else
            <ul class="mt-2 divide-y divide-slate-100 text-xs dark:divide-slate-800">
                @foreach ($item->lines as $line)
                    <li class="py-1.5">
                        <a href="{{ route('assets.show', $line->issuance) }}" class="font-semibold text-brand-800 hover:underline">{{ $line->issuance->reference }}</a>
                        · {{ $line->issuance->recipientName() }}
                        · {{ $line->status->label() }}
                        · {{ $line->issuance->issued_at->format('d M Y') }}
                        @if ($line->transferred_from_line_id)
                            · linked to previous assignment
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h2 class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Stock movements</h2>
        <ul class="mt-2 divide-y divide-slate-100 text-xs dark:divide-slate-800">
            @forelse ($item->movements as $movement)
                <li class="py-1.5">
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $movement->created_at->format('d M Y H:i') }}</span>
                    <span class="text-slate-600 dark:text-slate-300"> — {{ $movement->summary() }}</span>
                </li>
            @empty
                <li class="py-1.5 text-slate-500">No movements yet.</li>
            @endforelse
        </ul>
    </section>

    @if ($item->attachment_path)
        <p class="text-xs"><a href="{{ route('procurement.stock.attachment', $item) }}" class="font-semibold text-brand-700">Download supporting document</a></p>
    @endif

    @if ($canManage)
        @php
            $holder = $item->lines->first(fn ($line) => in_array($line->status, [\App\Enums\AssetLineStatus::Issued, \App\Enums\AssetLineStatus::PartiallyReturned], true));
        @endphp
        <div class="grid items-start gap-2 lg:grid-cols-2">
            <form method="POST" action="{{ route('procurement.stock.status', $item) }}" class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                @csrf
                <p class="text-[11px] font-semibold uppercase text-slate-500">Change status</p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Current status is {{ $item->operational_status->label() }}. Choose the status to apply next.</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <x-form-field label="New status" name="operational_status" type="select" required>
                        <option value="">Select status</option>
                        @foreach ($statuses as $status)
                            @if ($status !== \App\Enums\AssetOperationalStatus::Assigned && $status !== $item->operational_status)
                                <option value="{{ $status->value }}" @selected(old('operational_status') === $status->value)>{{ $status->label() }}</option>
                            @endif
                        @endforeach
                    </x-form-field>
                    <x-form-field label="Reason" name="reason" id="status_reason" required />
                </div>
                <button class="btn btn-primary mt-2">Update status</button>
            </form>
            @unless ($item->isIndividual())
                <form method="POST" action="{{ route('procurement.stock.receive', $item) }}" class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                    @csrf
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Add quantity</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <x-form-field label="Quantity" name="quantity" type="number" min="1" required />
                        <x-form-field label="Reason" name="reason" id="receive_reason" required />
                    </div>
                    <button class="btn btn-secondary mt-2">Add to stock</button>
                </form>
            @endunless
            @if ($item->operational_status === \App\Enums\AssetOperationalStatus::Assigned)
                <form method="POST" action="{{ route('procurement.stock.transfer', $item) }}" class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900" x-data="{
                    recipientType: 'guard',
                    guards: @js($guards->map(fn ($guard) => ['id' => $guard->id, 'label' => $guard->employment_id.' — '.$guard->full_name])->values()),
                    staffMembers: @js($staffMembers->map(fn ($member) => ['id' => $member->id, 'label' => $member->employment_id.' — '.$member->full_name])->values()),
                    fill() {
                        const selected = this.$root.querySelector('input[name=recipient_type]:checked');
                        if (selected) {
                            this.recipientType = selected.value;
                        }
                        this.$nextTick(() => {
                            const select = this.$refs.person;
                            if (select.tomselect) {
                                select.tomselect.destroy();
                            }
                            const choices = this.recipientType === 'staff' ? this.staffMembers : this.guards;
                            const placeholder = this.recipientType === 'staff' ? 'Select staff' : 'Select guard';
                            select.name = this.recipientType === 'staff' ? 'staff_id' : 'guard_id';
                            select.replaceChildren();
                            select.add(new Option(placeholder, ''));
                            choices.forEach((choice) => select.add(new Option(choice.label, String(choice.id))));
                            window.initSearchableSelects(select.closest('.field'));
                        });
                    },
                }" x-init="fill()">
                    @csrf
                    <p class="text-[11px] font-semibold uppercase text-slate-500">Transfer assignment</p>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                        @if ($holder)
                            Currently with {{ $holder->issuance->recipientName() }}.
                        @endif
                        Move this assignment to another person. The asset stays assigned.
                    </p>
                    <div class="mt-2 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs font-semibold dark:border-slate-700 dark:bg-slate-800">
                        <label class="cursor-pointer rounded-md px-3 py-1" :class="recipientType === 'guard' ? 'bg-white text-brand-800 shadow-sm dark:bg-slate-900' : 'text-slate-600'">
                            <input type="radio" name="recipient_type" value="guard" class="sr-only" x-model="recipientType" @change="fill()" checked> Guard
                        </label>
                        <label class="cursor-pointer rounded-md px-3 py-1" :class="recipientType === 'staff' ? 'bg-white text-brand-800 shadow-sm dark:bg-slate-900' : 'text-slate-600'">
                            <input type="radio" name="recipient_type" value="staff" class="sr-only" x-model="recipientType" @change="fill()"> Staff
                        </label>
                    </div>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <div class="field">
                            <label class="field__label" for="transfer_person" x-text="recipientType === 'staff' ? 'Staff' : 'Guard'"></label>
                            <select id="transfer_person" x-ref="person" class="field__control field__control--select"></select>
                        </div>
                        <x-form-field label="Reason" name="reason" id="transfer_reason" required />
                    </div>
                    <button class="btn btn-primary mt-2">Transfer</button>
                </form>
            @endif
        </div>
    @endif
</div>
@endsection

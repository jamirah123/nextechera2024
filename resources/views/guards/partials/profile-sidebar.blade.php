@php
    $hasPayrollDetails = filled($guard->bank_name)
        || filled($guard->bank_account)
        || filled($guard->nssf_number)
        || filled($guard->emergency_contact_name)
        || filled($guard->emergency_contact_phone);

    $hasContactDetails = filled($guard->alternative_phone)
        || filled($guard->address)
        || filled($guard->notes);
@endphp

@if ($hasPayrollDetails || $hasContactDetails)
    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Additional details</h2>

        @if ($hasPayrollDetails)
            <dl class="mt-3 space-y-2.5">
                @if (filled($guard->emergency_contact_name) || filled($guard->emergency_contact_phone))
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Emergency contact</dt>
                        <dd class="mt-0.5 text-sm text-slate-900">
                            {{ $guard->emergency_contact_name }}
                            @if ($guard->emergency_contact_phone)
                                <span class="block text-xs text-slate-500">{{ $guard->emergency_contact_phone }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if (filled($guard->bank_name) || filled($guard->bank_account))
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Bank</dt>
                        <dd class="mt-0.5 text-sm text-slate-900">
                            {{ $guard->bank_name }}
                            @if ($guard->bank_account)
                                <span class="block text-xs font-mono text-slate-500">{{ $guard->bank_account }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if (filled($guard->nssf_number))
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">NSSF number</dt>
                        <dd class="mt-0.5 text-sm text-slate-900">{{ $guard->nssf_number }}</dd>
                    </div>
                @endif
            </dl>
        @endif

        @if ($hasContactDetails)
            <dl @class(['mt-3 space-y-2.5', 'border-t border-slate-100 pt-3' => $hasPayrollDetails])>
                @if (filled($guard->alternative_phone))
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Alt. phone</dt>
                        <dd class="mt-0.5 text-sm text-slate-900">{{ $guard->alternative_phone }}</dd>
                    </div>
                @endif
                @if (filled($guard->address))
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-0.5 text-sm text-slate-700">{{ $guard->address }}</dd>
                    </div>
                @endif
                @if (filled($guard->notes))
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</dt>
                        <dd class="mt-0.5 text-sm text-slate-700">{{ $guard->notes }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </section>
@endif

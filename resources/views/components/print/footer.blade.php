@props([
    'note' => null,
])

@php
    $company = config('psg.company', 'Platinum Security Group');
    $note = $note ?? 'This document was issued by '.$company.'. For queries, contact the billing office using the details above.';
@endphp

<footer {{ $attributes->merge(['class' => 'print-footer mt-8 border-t border-slate-200 pt-5']) }}>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-xl">
            <p class="text-xs leading-relaxed text-slate-500">{{ $note }}</p>
            <p class="mt-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                {{ $company }} · Confidential
            </p>
        </div>
        <div class="text-right text-[10px] text-slate-400">
            <p>Printed {{ now()->timezone(config('app.timezone'))->format('d M Y, H:i') }}</p>
        </div>
    </div>
</footer>

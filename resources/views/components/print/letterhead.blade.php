@props([
    'documentTitle' => null,
    'documentReference' => null,
    'compact' => false,
])

@php
    $company = config('psg.company', 'Platinum Security Group');
    $logo = config('psg.logo_url', asset(config('psg.logo', 'images/logo.jpeg')));
    $email = config('psg.support_email');
    $phone = config('psg.support_phone');
@endphp

<div {{ $attributes->merge(['class' => 'print-letterhead']) }}>
    <div class="flex flex-wrap items-start justify-between gap-3 {{ $compact ? 'pb-3' : 'pb-6' }}">
        <div class="flex min-w-0 items-start gap-3">
            <img
                src="{{ $logo }}"
                alt="{{ $company }}"
                @class([
                    'print-logo h-10 w-auto shrink-0 object-contain' => $compact,
                    'print-logo h-16 w-auto shrink-0 object-contain sm:h-20' => ! $compact,
                ])
            >
            <div class="min-w-0 pt-0.5">
                <p @class([
                    'text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100' => $compact,
                    'text-base font-bold tracking-tight text-slate-900 sm:text-lg dark:text-slate-100' => ! $compact,
                ])>{{ $company }}</p>
                @unless ($compact)
                    <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8B1E1E]">
                        {{ config('psg.tagline', 'New Age Security and Protection') }}
                    </p>
                @endunless
                @if (! $compact && (filled($email) || filled($phone)))
                    <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                        @if (filled($email))
                            <span>{{ $email }}</span>
                        @endif
                        @if (filled($email) && filled($phone))
                            <span class="mx-1.5 text-slate-300">|</span>
                        @endif
                        @if (filled($phone))
                            <span>{{ $phone }}</span>
                        @endif
                    </p>
                @endif
            </div>
        </div>

        @if ($documentTitle || $documentReference)
            <div class="min-w-[8rem] text-left sm:text-right">
                @if ($documentTitle)
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#1E5D48]">Official document</p>
                    <p @class([
                        'mt-0.5 text-base font-semibold tracking-tight text-slate-900 dark:text-slate-100' => $compact,
                        'mt-1 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100' => ! $compact,
                    ])>{{ $documentTitle }}</p>
                @endif
                @if ($documentReference)
                    <p class="mt-0.5 font-mono text-xs font-medium text-slate-600 dark:text-slate-400">{{ $documentReference }}</p>
                @endif
            </div>
        @endif
    </div>
    <div class="h-0.5 w-full rounded-full bg-gradient-to-r from-[#1E5D48] via-[#1E5D48]/70 to-[#8B1E1E]/80"></div>
</div>

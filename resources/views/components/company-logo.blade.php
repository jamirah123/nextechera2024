@props([
    'size' => 'md',
    'rounded' => 'lg',
    'plain' => false,
])

@php
    $brand = $brand ?? app(\App\Services\SystemSettingService::class)->branding();
    $sizes = [
        'sm' => 'h-8 w-8',
        'md' => 'h-9 w-9',
        'lg' => 'h-12 w-12',
        'xl' => 'h-24 w-auto max-w-[10rem] shrink-0',
    ];
    $plainSizes = [
        'sm' => 'h-8 w-auto max-w-[5.5rem] shrink-0',
        'md' => 'h-10 w-auto max-w-[6.5rem] shrink-0',
        'lg' => 'h-16 w-auto max-w-[8.5rem] shrink-0',
        'xl' => 'h-24 w-auto max-w-[10rem] shrink-0',
    ];
    $sizeClass = ($plain ? $plainSizes : $sizes)[$size] ?? ($plain ? $plainSizes['md'] : $sizes['md']);
    $roundClass = match ($rounded) {
        'full' => 'rounded-full',
        'xl' => 'rounded-xl',
        default => 'rounded-lg',
    };
    $frameClass = $plain
        ? 'object-contain'
        : "{$roundClass} object-contain bg-white p-0.5 shadow-sm ring-1 ring-slate-200/80";
@endphp

<img
    {{ $attributes->merge(['class' => "{$sizeClass} {$frameClass}"]) }}
    src="{{ $brand['logo_url'] }}"
    alt="{{ $brand['name'] }}"
>

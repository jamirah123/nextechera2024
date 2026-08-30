@props([
    'size' => 'md',
    'rounded' => 'lg',
])

@php
    $brand = $brand ?? app(\App\Services\SystemSettingService::class)->branding();
    $sizes = [
        'sm' => 'h-8 w-8',
        'md' => 'h-9 w-9',
        'lg' => 'h-12 w-12',
        'xl' => 'h-16 w-16',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $roundClass = match ($rounded) {
        'full' => 'rounded-full',
        'xl' => 'rounded-xl',
        default => 'rounded-lg',
    };
@endphp

<img
    {{ $attributes->merge(['class' => "{$sizeClass} {$roundClass} object-contain bg-white p-0.5 shadow-sm ring-1 ring-slate-200/80"]) }}
    src="{{ $brand['logo_url'] }}"
    alt="{{ $brand['name'] }}"
>

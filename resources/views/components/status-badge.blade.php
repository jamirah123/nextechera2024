@props([
    'tone' => 'slate',
    'label' => null,
])

@php
    $allowed = ['emerald', 'amber', 'rose', 'sky', 'indigo', 'brand', 'slate', 'violet'];
    $tone = in_array($tone, $allowed, true) ? $tone : 'slate';
@endphp

<span {{ $attributes->merge(['class' => 'status-badge status-badge--'.$tone]) }}>
    {{ $label ?? $slot }}
</span>

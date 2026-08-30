@props([
    'guard',
    'attachment',
    'size' => 'sm',
])

@php
    $buttonClass = $size === 'md'
        ? 'inline-flex rounded-lg border border-brand-200 bg-brand-50 px-4 py-2.5 text-sm font-semibold text-brand-800 hover:bg-brand-100'
        : 'inline-flex rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-800 hover:bg-brand-100';
@endphp

<a href="{{ route('guards.attachments.show', [$guard, $attachment]) }}" class="{{ $buttonClass }}">
    View
</a>

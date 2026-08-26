@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
])

@php
    $fieldValue = old($name, $value);
    $hasError = $errors->has($name);
    $baseClass = 'block w-full rounded-xl border px-3.5 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 '.($hasError
        ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20'
        : 'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20');
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-slate-700">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500">*</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            rows="3"
            @if ($required) required @endif
            {{ $attributes->except('class') }}
            class="{{ $baseClass }}"
        >{{ $fieldValue }}</textarea>
    @elseif ($type === 'select')
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($required) required @endif
            {{ $attributes->except('class') }}
            class="{{ $baseClass }} bg-white"
        >
            {{ $slot }}
        </select>
    @else
        <input
            id="{{ $name }}"
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ $fieldValue }}"
            @if ($required) required @endif
            {{ $attributes->except('class') }}
            class="{{ $baseClass }}"
        >
    @endif

    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>

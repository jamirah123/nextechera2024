@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
    'placeholder' => null,
])

@php
    $fieldValue = old($name, $value);
    $hasError = $errors->has($name);
@endphp

<div {{ $attributes->only('class')->class(['field']) }}>
    <label for="{{ $name }}" class="field__label">
        {{ $label }}
        @if ($required)
            <span class="field__required" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            rows="2"
            @if ($required) required @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->except('class') }}
            @class(['field__control', 'field__control--textarea', 'field__control--error' => $hasError])
        >{{ $fieldValue }}</textarea>
    @elseif ($type === 'select')
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($required) required @endif
            {{ $attributes->except('class') }}
            @class(['field__control', 'field__control--select', 'field__control--error' => $hasError])
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
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->except('class') }}
            @class(['field__control', 'field__control--error' => $hasError])
        >
    @endif

    @if ($help)
        <p class="field__help">{{ $help }}</p>
    @endif
    @error($name)
        <p class="field__error">{{ $message }}</p>
    @enderror
</div>

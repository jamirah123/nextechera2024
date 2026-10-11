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
    $fieldId = $attributes->get('id', $name);

    $selectAttributes = $attributes->except(['class', 'id']);
    if ($type === 'select' && ! $selectAttributes->has('data-searchable')) {
        $selectAttributes = $selectAttributes->merge(['data-searchable' => 'true']);
    }
@endphp

<div {{ $attributes->only('class')->class(['field']) }}>
    <label for="{{ $fieldId }}" class="field__label">
        {{ $label }}
        @if ($required)
            <span class="field__required" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea
            id="{{ $fieldId }}"
            name="{{ $name }}"
            rows="2"
            @if ($required) required @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->except(['class', 'id']) }}
            @class(['field__control', 'field__control--textarea', 'field__control--error' => $hasError])
        >{{ $fieldValue }}</textarea>
    @elseif ($type === 'select')
        <select
            id="{{ $fieldId }}"
            name="{{ $name }}"
            @if ($required) required @endif
            {{ $selectAttributes }}
            @class(['field__control', 'field__control--select', 'field__control--error' => $hasError])
        >
            {{ $slot }}
        </select>
    @elseif ($type === 'password')
        <div class="relative" x-data="{ show: false }">
            <input
                id="{{ $fieldId }}"
                type="password"
                :type="show ? 'text' : 'password'"
                name="{{ $name }}"
                value="{{ $fieldValue }}"
                @if ($required) required @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                {{ $attributes->except(['class', 'id']) }}
                @class(['field__control field__control--password', 'field__control--error' => $hasError])
            >
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                @click="show = !show"
                :aria-label="show ? 'Hide password' : 'Show password'"
                :aria-pressed="show"
            >
                <x-icon name="eye" class="h-3.5 w-3.5" x-show="!show" />
                <x-icon name="eye-off" class="h-3.5 w-3.5" x-cloak x-show="show" />
            </button>
        </div>
    @else
        <input
            id="{{ $fieldId }}"
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ $fieldValue }}"
            @if ($required) required @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->except(['class', 'id']) }}
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

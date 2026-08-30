@props([
    'label',
    'name',
    'value' => '1',
    'checked' => false,
    'help' => null,
    'inline' => false,
])

<label {{ $attributes->class(['form-checkbox', 'form-checkbox--inline' => $inline]) }}>
    <input
        type="checkbox"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        class="form-checkbox__input"
    >
    <span class="form-checkbox__content">
        <span class="form-checkbox__label">{{ $label }}</span>
        @if ($help)
            <span class="form-checkbox__help">{{ $help }}</span>
        @endif
    </span>
</label>

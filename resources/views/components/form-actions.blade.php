@props([
    'cancel' => null,
    'cancelLabel' => 'Cancel',
    'submitLabel' => 'Save',
])

<div {{ $attributes->class(['form-actions']) }}>
    <div class="form-actions__inner">
        @if ($cancel)
            <a href="{{ $cancel }}" class="btn btn-secondary">{{ $cancelLabel }}</a>
        @endif
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    </div>
</div>

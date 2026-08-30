@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->class(['form-group']) }}>
    @if ($title || $description)
        <div class="form-group__header">
            @if ($title)
                <p class="form-group__title">{{ $title }}</p>
            @endif
            @if ($description)
                <p class="form-group__description">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div class="form-group__fields">
        {{ $slot }}
    </div>
</div>

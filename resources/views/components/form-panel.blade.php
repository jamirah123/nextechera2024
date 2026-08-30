@props([
    'title',
    'subtitle' => null,
    'back' => null,
])

<div {{ $attributes->class(['form-panel']) }}>
    <header class="form-panel__header">
        <div class="form-panel__heading">
            @if ($back)
                <a href="{{ $back }}" class="form-panel__back">
                    <x-icon name="chevron" class="h-3 w-3 rotate-180" />
                    Back
                </a>
            @endif
            <div>
                <h1 class="form-panel__title">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="form-panel__subtitle">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @isset($actions)
            <div class="form-panel__actions">{{ $actions }}</div>
        @endisset
    </header>

    <div class="form-panel__body">
        {{ $slot }}
    </div>
</div>

@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class(['form-section']) }}>
    @if ($title || $description)
        <header class="form-section__header">
            @if ($title)
                <h2 class="form-section__title">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="form-section__description">{{ $description }}</p>
            @endif
        </header>
    @endif
    <div class="form-section__body">
        {{ $slot }}
    </div>
</section>

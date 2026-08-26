@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'icon' => 'building',
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center']) }}>
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
        <x-icon :name="$icon" class="h-6 w-6" />
    </div>
    <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-5 flex flex-wrap justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>

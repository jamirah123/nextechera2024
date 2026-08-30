@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'icon' => 'building',
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center dark:border-slate-600 dark:bg-slate-800']) }}>
    <div class="mx-auto flex h-7 w-7 items-center justify-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300">
        <x-icon :name="$icon" class="h-3.5 w-3.5" />
    </div>
    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-1 max-w-md text-xs text-slate-500">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-3 flex flex-wrap justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>

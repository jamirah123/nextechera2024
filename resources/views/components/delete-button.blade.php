@props([
    'action',
    'confirm' => 'Delete this record? It will be archived and can be restored by an administrator.',
    'title' => 'Confirm delete',
    'label' => 'Delete',
    'confirmLabel' => 'Yes, delete',
    'size' => 'sm',
    'iconOnly' => false,
])

@php
    if ($iconOnly) {
        $buttonClass = 'inline-flex h-7 w-7 items-center justify-center rounded-md border border-rose-200 bg-rose-50 text-rose-700 shadow-sm transition hover:bg-rose-100';
        $iconClass = 'h-3.5 w-3.5';
    } elseif ($size === 'md') {
        $buttonClass = 'inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100';
        $iconClass = 'h-3.5 w-3.5';
    } else {
        $buttonClass = 'inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100';
        $iconClass = 'h-3.5 w-3.5';
    }
@endphp

<div
    class="inline"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="{{ $buttonClass }}"
        @click="open = true"
        title="{{ $label }}"
        aria-label="{{ $label }}"
    >
        <x-icon name="trash" class="{{ $iconClass }}" />
        @unless ($iconOnly)
            {{ $label }}
        @endunless
    </button>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-confirm-title-{{ md5($action) }}"
        >
            <div
                class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"
                x-show="open"
                x-transition.opacity
                @click="open = false"
            ></div>

            <div
                class="relative w-full max-w-md overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl"
                x-show="open"
                x-transition
                @click.stop
            >
                <div class="border-b border-slate-100 px-3 py-2.5">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                            <x-icon name="warning" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 id="delete-confirm-title-{{ md5($action) }}" class="text-base font-semibold text-slate-900">
                                {{ $title }}
                            </h3>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                {{ $confirm }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 bg-slate-50 px-3 py-2.5 sm:flex-row sm:justify-end sm:px-6">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="open = false"
                    >
                        Cancel
                    </button>
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 sm:w-auto"
                        >
                            {{ $confirmLabel }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>

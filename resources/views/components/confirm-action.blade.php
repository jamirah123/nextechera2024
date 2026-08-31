@props([
    'action',
    'method' => 'POST',
    'confirm' => 'Are you sure you want to continue?',
    'title' => 'Confirm action',
    'label' => 'Confirm',
    'confirmLabel' => 'Yes, continue',
    'cancelLabel' => 'Go back',
    'buttonClass' => 'inline-flex rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-800',
    'variant' => 'danger',
])

@php
    $dialogId = 'confirm-action-'.md5($action.$label);
    $httpMethod = strtoupper($method);
    $iconWrapperClass = match ($variant) {
        'primary' => 'bg-brand-50 text-brand-600 ring-brand-100 dark:bg-brand-950/50 dark:text-brand-400 dark:ring-brand-900',
        'warning' => 'bg-amber-50 text-amber-600 ring-amber-100 dark:bg-amber-950/50 dark:text-amber-400 dark:ring-amber-900',
        default => 'bg-rose-50 text-rose-600 ring-rose-100 dark:bg-rose-950/50 dark:text-rose-400 dark:ring-rose-900',
    };
    $confirmButtonClass = match ($variant) {
        'primary' => 'bg-brand-700 hover:bg-brand-800',
        'warning' => 'bg-amber-700 hover:bg-amber-800',
        default => 'bg-rose-600 hover:bg-rose-700',
    };
@endphp

<div
    class="inline-flex"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="{{ $buttonClass }}"
        @click="open = true"
    >
        {{ $label }}
    </button>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $dialogId }}-title"
        >
            <div
                class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"
                x-show="open"
                x-transition.opacity
                @click="open = false"
            ></div>

            <div
                class="relative w-full max-w-md overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
                x-show="open"
                x-transition
                @click.stop
            >
                <div class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 {{ $iconWrapperClass }}">
                            <x-icon name="warning" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 id="{{ $dialogId }}-title" class="text-base font-semibold text-slate-900 dark:text-slate-100">
                                {{ $title }}
                            </h3>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                {{ $confirm }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 bg-slate-50 px-3 py-2.5 sm:flex-row sm:justify-end sm:px-6 dark:bg-slate-950/50">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        @click="open = false"
                    >
                        {{ $cancelLabel }}
                    </button>
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @if ($httpMethod !== 'POST')
                            @method($httpMethod)
                        @endif
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white sm:w-auto {{ $confirmButtonClass }}"
                        >
                            {{ $confirmLabel }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>

@props([
    'action',
    'reference' => null,
])

@php
    $dialogId = 'payroll-reject-'.md5($action);
    $openOnLoad = $errors->has('reason');
@endphp

<div
    class="inline-flex"
    x-data="{ open: @json($openOnLoad) }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="inline-flex rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200"
        @click="open = true"
    >
        Return to finance
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
                class="relative w-full max-w-lg overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
                x-show="open"
                x-transition
                @click.stop
            >
                <form method="POST" action="{{ $action }}">
                    @csrf
                    <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-900">
                                <x-icon name="warning" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 id="{{ $dialogId }}-title" class="text-base font-semibold text-slate-900 dark:text-slate-100">
                                    Return payroll to finance
                                </h3>
                                <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                    @if ($reference)
                                        {{ $reference }} will go back to <strong>Calculated</strong> so finance can revise payslips and resubmit.
                                    @else
                                        This run will go back to <strong>Calculated</strong> so finance can revise payslips and resubmit.
                                    @endif
                                    Please explain what needs to be corrected.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-3">
                        <label for="{{ $dialogId }}-reason" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Rejection reason <span class="text-rose-600">*</span>
                        </label>
                        <textarea
                            id="{{ $dialogId }}-reason"
                            name="reason"
                            rows="4"
                            required
                            maxlength="500"
                            placeholder="e.g. Verify advance deductions for STF0003 before resubmitting."
                            class="mt-1.5 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 @error('reason') border-rose-300 ring-2 ring-rose-100 @enderror"
                        >{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-[10px] text-slate-500">Shown to finance on the payroll run. Max 500 characters.</p>
                        @enderror
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3 sm:flex-row sm:justify-end dark:border-slate-800 dark:bg-slate-950/50">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            @click="open = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800"
                        >
                            Return to finance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>

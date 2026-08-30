@php
    /** @var \App\Models\Guard|null $guard */
    $guard = $guard ?? null;
    $existingAttachments = $guard?->attachments ?? collect();
@endphp

<section>
    <div class="mb-4 flex items-center gap-2">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-xs font-bold text-emerald-700">4</span>
        <h3 class="text-sm font-semibold text-slate-900">Documents & attachments</h3>
    </div>

    @if ($existingAttachments->isNotEmpty())
        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Existing files</p>
            <ul class="mt-3 space-y-2">
                @foreach ($existingAttachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $attachment->displayName() }}</p>
                            <p class="text-xs text-slate-500">{{ $attachment->humanSize() }} · {{ optional($attachment->created_at)->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-guard-attachment-view-button :guard="$guard" :attachment="$attachment" />
                            <a
                                href="{{ route('guards.attachments.download', [$guard, $attachment]) }}"
                                class="text-xs font-semibold text-brand-700 hover:text-brand-800"
                            >
                                Download
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <label for="attachments" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ $guard ? 'Add more documents' : 'Upload documents' }}
        </label>
        <input
            id="attachments"
            type="file"
            name="attachments[]"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf,image/*"
            class="block w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-800 hover:file:bg-brand-100 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('attachments') border-red-400 @enderror @error('attachments.*') border-red-400 @enderror"
        >
        <p class="mt-1 text-xs text-slate-500">
            National ID, contract, certificates, or other HR documents. PDF, images, or Word files up to 5 MB each (max 10 files).
        </p>
        @error('attachments')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
        @error('attachments.*')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</section>

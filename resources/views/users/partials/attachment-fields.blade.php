@php
    /** @var \App\Models\User $user */
    $existingAttachments = $user->attachments ?? collect();
    $uploadLabel = $uploadLabel ?? ($existingAttachments->isEmpty() ? 'Upload documents' : 'Add more documents');
@endphp

<section>
    @if ($existingAttachments->isNotEmpty())
        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Existing files</p>
            <ul class="mt-3 space-y-2">
                @foreach ($existingAttachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <div class="min-w-0">
                            <a
                                href="{{ $context === 'admin'
                                    ? route('users.attachments.show', [$user, $attachment])
                                    : route('profile.attachments.show', $attachment) }}"
                                class="truncate text-sm font-medium text-brand-700 hover:text-brand-800"
                            >
                                {{ $attachment->displayName() }}
                            </a>
                            <p class="text-xs text-slate-500">{{ $attachment->humanSize() }} · {{ optional($attachment->created_at)->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-user-attachment-view-button
                                :attachment="$attachment"
                                :show-route="$context === 'admin'
                                    ? route('users.attachments.show', [$user, $attachment])
                                    : route('profile.attachments.show', $attachment)"
                            />
                            <a
                                href="{{ $context === 'admin'
                                    ? route('users.attachments.download', [$user, $attachment])
                                    : route('profile.attachments.download', $attachment) }}"
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
        <label for="attachments" class="mb-1.5 block text-sm font-medium text-slate-700">{{ $uploadLabel }}</label>
        <input
            id="attachments"
            type="file"
            name="attachments[]"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf,image/*"
            class="block w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-800 hover:file:bg-brand-100 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('attachments') border-red-400 @enderror @error('attachments.*') border-red-400 @enderror"
        >
        <p class="mt-1 text-xs text-slate-500">
            {{ $user->role?->documentGuidance() ?? 'Store personal or role-related documents.' }}
            PDF, images, or Word files up to 5 MB each (max 10 files).
        </p>
        @error('attachments')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
        @error('attachments.*')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</section>

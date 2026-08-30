@php
    /** @var \App\Models\User $user */
    $attachments = $user->attachments ?? collect();
@endphp

<section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
    <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
        <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">My documents</h2>
        <p class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400">{{ $user->role?->documentGuidance() }}</p>
    </div>

    @if ($attachments->isEmpty())
        <div class="px-3 py-3">
            <div class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-center dark:border-slate-600">
                <x-icon name="report" class="mx-auto h-4 w-4 text-slate-400" />
                <p class="mt-1.5 text-xs font-semibold text-slate-900 dark:text-slate-100">No documents stored</p>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Upload ID copies, certificates, or role-related files.</p>
            </div>
        </div>
    @else
        <ul class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach ($attachments as $attachment)
                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                    <div class="min-w-0">
                        <a
                            href="{{ route('profile.attachments.show', $attachment) }}"
                            class="text-xs font-semibold text-brand-700 hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-200"
                        >
                            {{ $attachment->displayName() }}
                        </a>
                        <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">
                            {{ $attachment->original_name }}
                            · {{ $attachment->humanSize() }}
                            · {{ optional($attachment->created_at)->format('d M Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <x-user-attachment-view-button
                            :attachment="$attachment"
                            :show-route="route('profile.attachments.show', $attachment)"
                        />
                        <a
                            href="{{ route('profile.attachments.download', $attachment) }}"
                            class="btn btn-secondary"
                        >
                            Download
                        </a>
                        @if ($canManageDocuments ?? true)
                            <x-delete-button
                                :action="route('profile.attachments.destroy', $attachment)"
                                label="Remove"
                                confirm-label="Yes, remove"
                                title="Remove document"
                                confirm="This document will be permanently deleted from your account."
                            />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canManageDocuments ?? true)
        <div class="border-t border-slate-100 p-3 dark:border-slate-700">
            <form method="POST" action="{{ route('profile.attachments.store') }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                @include('users.partials.attachment-fields', [
                    'user' => $user,
                    'context' => 'profile',
                    'uploadLabel' => 'Upload documents',
                ])
                <button type="submit" class="btn btn-primary">
                    Save documents
                </button>
            </form>
        </div>
    @endif
</section>

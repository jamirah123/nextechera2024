<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UserAttachmentService
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string|null>  $labels
     */
    public function storeMany(User $user, array $files, array $labels = []): void
    {
        DB::transaction(function () use ($user, $files, $labels): void {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }

                $path = $file->store('users/'.$user->id, 'local');
                $label = trim((string) ($labels[$index] ?? ''));

                UserAttachment::query()->create([
                    'user_id' => $user->id,
                    'label' => $label !== '' ? $label : null,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize() ?: 0,
                    'uploaded_by' => auth()->id(),
                ]);
            }
        });
    }

    public function delete(UserAttachment $attachment): void
    {
        DB::transaction(fn () => $attachment->delete());
    }
}

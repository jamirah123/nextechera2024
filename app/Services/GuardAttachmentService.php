<?php

namespace App\Services;

use App\Models\Guard;
use App\Models\GuardAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class GuardAttachmentService
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string|null>  $labels
     */
    public function storeMany(Guard $guard, array $files, array $labels = []): void
    {
        DB::transaction(function () use ($guard, $files, $labels): void {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }

                $path = $file->store('guards/'.$guard->id, 'local');
                $label = trim((string) ($labels[$index] ?? ''));

                GuardAttachment::query()->create([
                    'guard_id' => $guard->id,
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

    public function delete(GuardAttachment $attachment): void
    {
        DB::transaction(fn () => $attachment->delete());
    }
}

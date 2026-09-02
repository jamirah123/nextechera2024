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
     * @param  list<string|null>  $documentTypes
     * @param  list<string|null>  $expiresAt
     */
    public function storeMany(Guard $guard, array $files, array $labels = [], array $documentTypes = [], array $expiresAt = []): void
    {
        DB::transaction(function () use ($guard, $files, $labels, $documentTypes, $expiresAt): void {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }

                $path = $file->store('guards/'.$guard->id, 'local');
                $label = trim((string) ($labels[$index] ?? ''));
                $documentType = trim((string) ($documentTypes[$index] ?? ''));
                $expiry = trim((string) ($expiresAt[$index] ?? ''));

                GuardAttachment::query()->create([
                    'guard_id' => $guard->id,
                    'label' => $label !== '' ? $label : null,
                    'document_type' => $documentType !== '' ? $documentType : null,
                    'expires_at' => $expiry !== '' ? $expiry : null,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize() ?: 0,
                    'uploaded_by' => auth()->id(),
                ]);
            }
        });
    }

    /**
     * @param  array{document_type?: string|null, expires_at?: string|null, label?: string|null}  $data
     */
    public function updateMetadata(GuardAttachment $attachment, array $data): GuardAttachment
    {
        $attachment->update([
            'label' => array_key_exists('label', $data) ? ($data['label'] ?: null) : $attachment->label,
            'document_type' => array_key_exists('document_type', $data)
                ? ($data['document_type'] ?: null)
                : $attachment->document_type,
            'expires_at' => array_key_exists('expires_at', $data)
                ? ($data['expires_at'] ?: null)
                : $attachment->expires_at,
        ]);

        return $attachment->fresh();
    }

    public function delete(GuardAttachment $attachment): void
    {
        DB::transaction(fn () => $attachment->delete());
    }
}

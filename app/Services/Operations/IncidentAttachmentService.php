<?php

namespace App\Services\Operations;

use App\Models\Incident;
use App\Models\IncidentAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class IncidentAttachmentService
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string|null>  $labels
     */
    public function storeMany(Incident $incident, array $files, array $labels = []): void
    {
        DB::transaction(function () use ($incident, $files, $labels): void {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }

                $path = $file->store('incidents/'.$incident->id, 'local');
                $label = trim((string) ($labels[$index] ?? ''));

                IncidentAttachment::query()->create([
                    'incident_id' => $incident->id,
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

    public function delete(IncidentAttachment $attachment): void
    {
        DB::transaction(fn () => $attachment->delete());
    }
}

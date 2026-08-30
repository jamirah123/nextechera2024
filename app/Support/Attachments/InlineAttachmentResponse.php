<?php

namespace App\Support\Attachments;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InlineAttachmentResponse implements Responsable
{
    public function __construct(
        private object $attachment,
    ) {
    }

    public function toResponse($request): StreamedResponse
    {
        return Storage::disk('local')->response(
            $this->attachment->path,
            $this->attachment->original_name,
            [
                'Content-Type' => $this->attachment->browserMimeType(),
                'Content-Disposition' => 'inline; filename="'.$this->attachment->original_name.'"',
            ],
        );
    }
}

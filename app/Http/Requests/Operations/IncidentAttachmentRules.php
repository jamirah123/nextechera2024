<?php

namespace App\Http\Requests\Operations;

class IncidentAttachmentRules
{
    /** @return array<string, mixed> */
    public static function upload(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'attachment_labels' => ['nullable', 'array'],
            'attachment_labels.*' => ['nullable', 'string', 'max:120'],
        ];
    }
}

<?php

namespace App\Http\Requests\Guards;

use Illuminate\Foundation\Http\FormRequest;

class GuardAttachmentRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:5120',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx',
            ],
            'attachment_labels' => ['nullable', 'array', 'max:10'],
            'attachment_labels.*' => ['nullable', 'string', 'max:191'],
        ];
    }
}

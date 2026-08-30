<?php

namespace App\Support\Attachments;

trait AttachmentPreview
{
    public function isPreviewable(): bool
    {
        return $this->previewType() !== null;
    }

    public function previewType(): ?string
    {
        $mime = strtolower((string) $this->mime_type);
        $extension = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));

        if (str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return 'image';
        }

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        if ($extension === 'docx' || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return 'docx';
        }

        if ($extension === 'doc' || $mime === 'application/msword') {
            return 'doc';
        }

        return null;
    }

    public function browserMimeType(): string
    {
        if (filled($this->mime_type)) {
            return (string) $this->mime_type;
        }

        return match ($this->previewType()) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'image' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }
}

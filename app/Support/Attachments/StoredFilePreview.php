<?php

namespace App\Support\Attachments;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class StoredFilePreview
{
    use AttachmentPreview;

    public function __construct(
        public string $path,
        public string $original_name,
        public ?string $mime_type,
        public int $size,
        public ?Carbon $created_at,
        public ?object $uploader,
        public string $label,
    ) {}

    public static function fromPath(string $path, string $label, ?object $uploader = null): self
    {
        $disk = Storage::disk('local');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $original = $extension !== '' ? $label.'.'.$extension : $label;

        return new self(
            path: $path,
            original_name: $original,
            mime_type: $disk->mimeType($path) ?: null,
            size: (int) $disk->size($path),
            created_at: Carbon::createFromTimestamp($disk->lastModified($path)),
            uploader: $uploader,
            label: $label,
        );
    }

    public function displayName(): string
    {
        return $this->label;
    }

    public function humanSize(): string
    {
        $bytes = max(0, $this->size);

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}

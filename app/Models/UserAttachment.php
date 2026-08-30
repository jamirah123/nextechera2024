<?php

namespace App\Models;

use App\Support\Attachments\AttachmentPreview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserAttachment extends Model
{
    use AttachmentPreview;

    protected $fillable = [
        'user_id',
        'label',
        'original_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function userRecord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function displayName(): string
    {
        return filled($this->label) ? $this->label : $this->original_name;
    }

    public function humanSize(): string
    {
        $bytes = max(0, (int) $this->size);

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function deleteFile(): void
    {
        Storage::disk('local')->delete($this->path);
    }

    protected static function booted(): void
    {
        static::deleting(function (UserAttachment $attachment): void {
            $attachment->deleteFile();
        });
    }
}

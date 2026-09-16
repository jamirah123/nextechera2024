<?php

namespace App\Models;

use App\Enums\GuardDocumentType;
use App\Services\ArchiveService;
use App\Support\Attachments\AttachmentPreview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GuardAttachment extends Model
{
    use AttachmentPreview;

    protected $fillable = [
        'guard_id',
        'label',
        'document_type',
        'expires_at',
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
            'expires_at' => 'date',
            'document_type' => GuardDocumentType::class,
        ];
    }

    public function guardRecord(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
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

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $withinDays = 30): bool
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return false;
        }

        return $this->expires_at->lte(now()->addDays($withinDays)->startOfDay());
    }

    public function deleteFile(): void
    {
        Storage::disk('local')->delete($this->path);
    }

    protected static function booted(): void
    {
        static::deleting(function (GuardAttachment $attachment): void {
            app(ArchiveService::class)->recordSnapshot($attachment);
            $attachment->deleteFile();
        });

        static::deleted(function (GuardAttachment $attachment): void {
            app(ArchiveService::class)->logDeletion($attachment);
        });
    }
}

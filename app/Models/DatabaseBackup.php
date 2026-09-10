<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'reference',
        'type',
        'status',
        'disk',
        'relative_path',
        'filename',
        'driver',
        'size_bytes',
        'checksum_sha256',
        'offsite_disk',
        'offsite_path',
        'offsite_synced_at',
        'started_at',
        'completed_at',
        'verified_at',
        'restored_at',
        'created_by',
        'verified_by',
        'restored_by',
        'error_message',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => BackupType::class,
            'status' => BackupStatus::class,
            'size_bytes' => 'integer',
            'offsite_synced_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function restorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->relative_path);
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->relative_path);
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhere('filename', 'like', $like)
                ->orWhere('notes', 'like', $like);
        });
    }
}

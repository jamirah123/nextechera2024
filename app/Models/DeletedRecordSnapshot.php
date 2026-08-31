<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeletedRecordSnapshot extends Model
{
    protected $fillable = [
        'record_type',
        'model_class',
        'record_id',
        'label',
        'attributes',
        'relations',
        'was_soft_deleted',
        'source_action',
        'deleted_by',
        'restored_at',
        'restored_by',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'relations' => 'array',
            'was_soft_deleted' => 'boolean',
            'restored_at' => 'datetime',
        ];
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function restorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function isRestored(): bool
    {
        return $this->restored_at !== null;
    }

    public function isRestorable(): bool
    {
        return ! $this->isRestored() && \App\Support\Archive\DeletedRecordRegistry::canRestore($this->model_class);
    }

    public function typeLabel(): string
    {
        return \App\Support\Archive\DeletedRecordRegistry::typeLabel($this->record_type);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('restored_at');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($inner) use ($like): void {
            $inner->where('label', 'like', $like)
                ->orWhere('record_type', 'like', $like)
                ->orWhere('source_action', 'like', $like);
        });
    }

    /** @return array<string, string> */
    public function displayAttributes(): array
    {
        $hidden = [
            'id', 'password', 'remember_token',
            'created_at', 'updated_at', 'deleted_at',
            'created_by', 'updated_by',
        ];

        $rows = [];

        foreach ($this->attributes ?? [] as $key => $value) {
            if (in_array($key, $hidden, true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $rows[Str::headline(str_replace('_', ' ', (string) $key))] = (string) $value;
        }

        return $rows;
    }

    /** @return list<string> */
    public function relationSummaries(): array
    {
        $relations = $this->relations ?? [];
        $lines = [];

        if (isset($relations['deductions']) && is_array($relations['deductions'])) {
            $lines[] = count($relations['deductions']).' payroll deduction(s)';
        }

        if (isset($relations['shift_ids']) && is_array($relations['shift_ids'])) {
            $lines[] = count($relations['shift_ids']).' linked shift(s)';
        }

        if (isset($relations['archived_file_path']) && is_string($relations['archived_file_path'])) {
            $lines[] = 'Attachment file preserved in archive storage';
        }

        return $lines;
    }

    public function backupModeLabel(): string
    {
        return $this->was_soft_deleted ? 'Soft delete' : 'Snapshot only';
    }
}

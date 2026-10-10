<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportArchive extends Model
{
    /** @var array<string, string> */
    public const LABELS = [
        'monthly_shifts' => 'Monthly shift summary',
        'daily_shifts' => 'Daily shifts',
        'weekly_shifts' => 'Weekly shifts',
        'guards' => 'Guards report',
        'deployments' => 'Deployments report',
        'hr' => 'HR summary',
    ];

    public static function labelFor(string $key): string
    {
        return self::LABELS[$key] ?? str_replace('_', ' ', $key);
    }

    protected $fillable = [
        'user_id',
        'report_key',
        'title',
        'period_label',
        'filters',
        'search_text',
        'filename',
        'storage_path',
        'row_count',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($inner) use ($like): void {
            $inner->where('search_text', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('period_label', 'like', $like)
                ->orWhereHas('author', function ($author) use ($like): void {
                    $author->where('name', 'like', $like);
                });
        });
    }
}

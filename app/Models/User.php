<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'supervisor_id',
    'phone',
    'is_active',
    'last_login_at',
    'last_login_ip',
    'notifications_read_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'notifications_read_at' => 'datetime',
        ];
    }

    public function supervisorProfile(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_id');
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function hasRole(UserRole|string $role): bool
    {
        $value = $role instanceof UserRole ? $role : UserRole::from($role);

        return $this->role === $value;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isRegionSupervisor(): bool
    {
        return $this->role === UserRole::RegionSupervisor;
    }

    public function regionId(): ?int
    {
        if (! $this->isRegionSupervisor()) {
            return null;
        }

        $this->loadMissing('supervisorProfile:id,region_id');

        return $this->supervisorProfile?->region_id;
    }

    public function mustStayInOwnRegion(): bool
    {
        return $this->isRegionSupervisor() && $this->regionId() !== null;
    }

    public function canAccessRegion(?int $regionId): bool
    {
        if (! $this->mustStayInOwnRegion()) {
            return true;
        }

        return $regionId !== null && $regionId === $this->regionId();
    }

    public function scopeToOwnRegion(Builder $query, string $column = 'region_id'): Builder
    {
        if (! $this->mustStayInOwnRegion()) {
            return $query;
        }

        return $query->where($column, $this->regionId());
    }

    public function roleLabel(): string
    {
        return $this->role?->label() ?? 'Unknown';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        $letters = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : 'PS';
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}

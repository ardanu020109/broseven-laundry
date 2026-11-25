<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** Foto profil hasil unggahan aplikasi: avatars/<40 karakter acak>.png */
    public const AVATAR_PATTERN = '~^avatars/[A-Za-z0-9]{40}\.png$~';

    /**
     * role, is_active, dan kolom login sengaja TIDAK mass-assignable
     * agar tidak bisa dimanipulasi lewat request.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'avatar_path',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::Employee;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function receivedTransactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class, 'received_by');
    }

    public function completedTransactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class, 'completed_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(LaundryActivityLog::class, 'user_id');
    }

    /** Foto profil disajikan lewat route yang membutuhkan login (bukan URL publik). */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->avatar_path && preg_match(self::AVATAR_PATTERN, $this->avatar_path)
                ? '/api/avatars/'.basename($this->avatar_path)
                : null
        );
    }
}
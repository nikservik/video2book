<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ACCESS_LEVEL_USER = 0;

    public const ACCESS_LEVEL_ADMIN = 1;

    public const ACCESS_LEVEL_SUPERADMIN = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'access_token',
        'access_level',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'access_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'access_level' => 'integer',
        ];
    }

    public function canAccessLevel(int $requiredAccessLevel): bool
    {
        return (int) $this->access_level >= $requiredAccessLevel;
    }

    public function isSuperAdmin(): bool
    {
        return (int) $this->access_level === self::ACCESS_LEVEL_SUPERADMIN;
    }
}

<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_DIRECTOR = 'directeur';

    public const ROLES = [self::ROLE_SUPERVISOR, self::ROLE_ADMIN, self::ROLE_DIRECTOR];

    protected $attributes = [
        'role' => self::ROLE_SUPERVISOR,
        'active' => true,
        'must_change_password' => false,
        'auth_version' => 0,
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'nom',
        'prenom',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'auth_version',
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
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'auth_version' => 'integer',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->prenom.' '.$this->nom));
    }

    public function isAdministrator(): bool
    {
        return $this->active && $this->role === self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return match ($this->role) { self::ROLE_ADMIN => 'Administrateur', self::ROLE_DIRECTOR => 'Directeur', default => 'Superviseur' };
    }

    public function homeRoute(): string
    {
        if ($this->must_change_password) {
            return 'password.change';
        }

        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.supervisors.index',
            self::ROLE_DIRECTOR => 'director.dashboard',
            default => 'dashboard',
        };
    }

    public function loginRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.login',
            self::ROLE_DIRECTOR => 'director.login',
            default => 'login',
        };
    }

    public function parkings(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Parking::class)->withTimestamps();
    }
}

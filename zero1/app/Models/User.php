<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function barber(): HasOne
    {
        return $this->hasOne(Barber::class);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(Role::Owner->value);
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(Role::staff());
    }

    public function isBarber(): bool
    {
        return $this->hasRole(Role::Barber->value);
    }

    /** Destino padrão após login, conforme papel. */
    public function homeRoute(): string
    {
        return match (true) {
            $this->isStaff() => route('admin.dashboard'),
            $this->isBarber() => route('barber.dashboard'),
            default => route('account.dashboard'),
        };
    }
}

<?php

namespace App\Models;

use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CompanyPermissions;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_users')->withPivot('role_id', 'is_active')->withTimestamps();
    }

    public function hasCompanyPermission(string $permission): bool
    {
        return in_array($permission, app(CompanyPermissions::class)->names($this, app(CompanyContext::class)->id()), true);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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
        ];
    }
}

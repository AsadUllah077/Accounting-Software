<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name', 'legal_name', 'email', 'phone', 'currency', 'timezone', 'financial_year_start_date'];

    protected function casts(): array
    {
        return ['financial_year_start_date' => 'date', 'locked_through' => 'date'];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_users')->withPivot('role_id', 'is_active')->withTimestamps();
    }

    public function settings()
    {
        return $this->hasMany(CompanySetting::class);
    }
}

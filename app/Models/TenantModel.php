<?php

namespace App\Models;

use App\Services\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class TenantModel extends Model
{
    protected $guarded = ['id', 'company_id'];

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $query->where($query->getModel()->qualifyColumn('company_id'), app(CompanyContext::class)->id());
        });
        static::creating(function (self $model) {
            $id = app(CompanyContext::class)->id();
            if ($model->company_id && (int) $model->company_id !== $id) {
                throw new LogicException('Company mismatch.');
            }
            $model->company_id = $id;
        });
        static::updating(function (self $model) {
            if ($model->isDirty('company_id') || (int) $model->company_id !== app(CompanyContext::class)->id()) {
                throw new LogicException('Company mismatch.');
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

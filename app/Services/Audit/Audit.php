<?php

namespace App\Services\Audit;

use App\Services\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function record(string $event, Model $model, array $old = [], array $new = [], ?int $actorId = null): void
    {
        DB::table('audit_logs')->insert(['company_id' => app(CompanyContext::class)->id(), 'user_id' => $actorId ?? auth()->id(), 'event' => $event, 'model_type' => $model::class, 'model_id' => $model->id, 'old_values' => json_encode($old), 'new_values' => json_encode($new), 'created_at' => now()]);
    }
}

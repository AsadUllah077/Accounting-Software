<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_platform_admin')->default(false));
        Schema::table('subscription_invoices', function (Blueprint $t) {
            $t->date('period_start');
            $t->date('period_end');
            $t->unique(['company_id', 'subscription_id', 'period_start']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX one_live_subscription ON subscriptions(company_id) WHERE status IN ('trialing','active','past_due')");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS one_live_subscription');
        }
        Schema::table('subscription_invoices', function (Blueprint $t) {
            $t->dropUnique(['company_id', 'subscription_id', 'period_start']);
            $t->dropColumn(['period_start', 'period_end']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_platform_admin'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', fn (Blueprint $t) => $t->string('cash_flow_class')->default('operating'));
        DB::table('accounts')->where('type', 'equity')->update(['cash_flow_class' => 'financing']);
    }

    public function down(): void
    {
        Schema::table('accounts', fn (Blueprint $t) => $t->dropColumn('cash_flow_class'));
    }
};

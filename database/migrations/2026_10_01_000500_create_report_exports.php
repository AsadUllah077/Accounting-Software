<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('report');
            $t->string('format', 8);
            $t->json('filters');
            $t->string('status')->default('queued');
            $t->string('disk')->default('local');
            $t->string('path')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};

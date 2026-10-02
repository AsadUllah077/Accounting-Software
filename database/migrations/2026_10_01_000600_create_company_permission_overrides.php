<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_permission_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('role_id')->constrained();
            $t->foreignId('permission_id')->constrained();
            $t->boolean('allowed');
            $t->timestamps();
            $t->unique(['company_id', 'role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_permission_overrides');
    }
};

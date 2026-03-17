<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_case_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['user_id', 'case_id']);
            $table->index(['user_id', 'tenant_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_case_history');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_sync_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['google', 'microsoft']);
            $table->dateTime('last_pull_at')->nullable();
            $table->dateTime('last_push_at')->nullable();
            $table->string('sync_token')->nullable();
            $table->enum('status', ['active', 'error', 'paused'])->default('active');
            $table->text('last_error')->nullable();
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sync_status');
    }
};

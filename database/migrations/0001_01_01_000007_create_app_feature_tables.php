<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Todos ────────────────────────────────────────────────────────
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tag', 20)->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('low');
            $table->enum('status', ['pending', 'complete', 'important', 'trash'])->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to_id']);
            $table->index('case_id');
        });

        // ── Scrum Columns ────────────────────────────────────────────────
        Schema::create('scrum_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'order_index']);
        });

        // ── Scrum Tasks ──────────────────────────────────────────────────
        Schema::create('scrum_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scrum_column_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('category', 100)->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->nullOnDelete();
            $table->index(['scrum_column_id', 'order_index']);
            $table->index(['tenant_id', 'assigned_to_id']);
            $table->index('case_id');
            $table->index('due_date');
        });

        // ── User Case History ────────────────────────────────────────────
        Schema::create('user_case_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['user_id', 'case_id']);
            $table->index(['user_id', 'tenant_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_case_history');
        Schema::dropIfExists('scrum_tasks');
        Schema::dropIfExists('scrum_columns');
        Schema::dropIfExists('todos');
    }
};

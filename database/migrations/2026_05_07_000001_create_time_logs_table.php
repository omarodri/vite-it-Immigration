<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('case_id')->constrained('cases')->restrictOnDelete();
            $table->foreignId('case_task_id')->nullable()->constrained('case_tasks')->nullOnDelete();
            $table->foreignId('todo_id')->nullable()->constrained('todos')->nullOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->text('description')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->enum('source', ['manual', 'timer'])->default('manual');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'case_id', 'deleted_at'], 'idx_logs_case');
            $table->index(['tenant_id', 'user_id', 'deleted_at'], 'idx_logs_user');
            $table->index(['tenant_id', 'case_id', 'work_date'], 'idx_logs_case_date');
            $table->index(['user_id', 'ended_at'], 'idx_active_timer');
            $table->index(['case_task_id'], 'idx_logs_task');
            $table->index(['todo_id'], 'idx_logs_todo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};

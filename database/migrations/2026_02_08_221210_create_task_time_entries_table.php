<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->decimal('hours', 5, 2);
            $table->date('work_date');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_entries');
    }
};

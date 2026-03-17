<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrum_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('scrum_column_id')->constrained('scrum_columns')->cascadeOnDelete();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('scrum_tasks');
    }
};

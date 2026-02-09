<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('type', ['translation', 'case_creation', 'accounting', 'filing', 'document', 'other'])->default('other');
            $table->enum('priority', ['urgent', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['new', 'assigned', 'in_progress', 'rejected', 'resolved', 'closed'])->default('new');

            $table->dateTime('due_date')->nullable();
            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->decimal('actual_hours', 5, 2)->nullable();

            // Document attachment (foreign key added in later migration)
            $table->unsignedBigInteger('document_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index(['tenant_id', 'case_id']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

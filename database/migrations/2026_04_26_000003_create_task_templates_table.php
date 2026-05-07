<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->string('code', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('blocks_stage_completion')->default(true);
            $table->enum('default_type', ['translation', 'case_creation', 'accounting', 'filing', 'document', 'other'])
                  ->default('other');
            $table->enum('default_priority', ['urgent', 'high', 'medium', 'low'])->default('medium');
            $table->unsignedSmallInteger('due_offset_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workflow_stage_id', 'code']);
            $table->index(['tenant_id', 'workflow_stage_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};

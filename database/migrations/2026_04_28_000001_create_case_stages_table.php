<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('workflow_stage_id')->nullable()
                  ->constrained('workflow_stages')->nullOnDelete();
            $table->string('code', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->string('color', 20)->default('primary');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['case_id', 'code']);
            $table->index(['tenant_id', 'case_id', 'sort_order'], 'case_stages_tenant_case_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_stages');
    }
};

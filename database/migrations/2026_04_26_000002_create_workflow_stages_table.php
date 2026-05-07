<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_type_id')->constrained('case_types')->cascadeOnDelete();
            $table->string('code', 50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->string('color', 20)->default('primary');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['case_type_id', 'code']);
            $table->index(['tenant_id', 'case_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stages');
    }
};

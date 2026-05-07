<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            // MySQL won't drop a unique index that backs a FK (G2 gotcha).
            // Pattern: drop FK → drop unique → add new unique → re-add FK.
            $table->dropForeign(['case_type_id']);
            $table->dropUnique(['case_type_id', 'code']);
            $table->unique(['tenant_id', 'case_type_id', 'code'], 'workflow_stages_tenant_type_code_unique');
            $table->foreign('case_type_id')->references('id')->on('case_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropForeign(['case_type_id']);
            $table->dropUnique('workflow_stages_tenant_type_code_unique');
            $table->unique(['case_type_id', 'code']);
            $table->foreign('case_type_id')->references('id')->on('case_types')->cascadeOnDelete();
        });
    }
};

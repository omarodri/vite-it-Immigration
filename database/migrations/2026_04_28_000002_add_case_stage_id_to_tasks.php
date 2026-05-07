<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('case_stage_id')->nullable()->after('workflow_stage_id')
                  ->constrained('case_stages')->nullOnDelete();
            $table->index(['case_id', 'case_stage_id', 'sort_order'], 'tasks_case_case_stage_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_case_case_stage_order_idx');
            $table->dropForeign(['case_stage_id']);
            $table->dropColumn('case_stage_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('workflow_stage_id')->nullable()->after('case_id')
                  ->constrained('workflow_stages')->nullOnDelete();
            $table->foreignId('task_template_id')->nullable()->after('workflow_stage_id')
                  ->constrained('task_templates')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0)->after('status');

            $table->index(['case_id', 'workflow_stage_id', 'sort_order'], 'tasks_case_stage_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_case_stage_order_idx');
            $table->dropForeign(['workflow_stage_id']);
            $table->dropForeign(['task_template_id']);
            $table->dropColumn(['workflow_stage_id', 'task_template_id', 'sort_order']);
        });
    }
};

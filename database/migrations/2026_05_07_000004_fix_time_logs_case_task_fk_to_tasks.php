<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_logs', function (Blueprint $table) {
            // Drop the FK that incorrectly pointed to case_tasks
            $table->dropForeign(['case_task_id']);
            // Add the correct FK pointing to tasks
            $table->foreign('case_task_id')->references('id')->on('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('time_logs', function (Blueprint $table) {
            $table->dropForeign(['case_task_id']);
            $table->foreign('case_task_id')->references('id')->on('case_tasks')->nullOnDelete();
        });
    }
};

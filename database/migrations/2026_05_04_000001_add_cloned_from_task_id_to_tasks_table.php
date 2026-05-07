<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('cloned_from_task_id')
                  ->nullable()
                  ->after('task_template_id');

            $table->foreign('cloned_from_task_id')
                  ->references('id')
                  ->on('tasks')
                  ->nullOnDelete();

            $table->index(['case_id', 'cloned_from_task_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['cloned_from_task_id']);
            $table->dropIndex(['case_id', 'cloned_from_task_id']);
            $table->dropColumn('cloned_from_task_id');
        });
    }
};

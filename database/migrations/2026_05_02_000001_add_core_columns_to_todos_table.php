<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::table('todos', function (Blueprint $table) {
            if (! Schema::hasColumn('todos', 'task_id')) {
                $table->foreignId('task_id')
                    ->nullable()
                    ->after('case_id')
                    ->constrained('tasks')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('todos', 'task_template_id')) {
                $table->foreignId('task_template_id')
                    ->nullable()
                    ->after('task_id')
                    ->constrained('task_templates')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('todos', 'is_core')) {
                $table->boolean('is_core')->default(false)->after('task_template_id');
            }

            if (! Schema::hasColumn('todos', 'source')) {
                $table->enum('source', ['manual', 'wizard', 'workflow_add', 'workflow_sync'])
                    ->default('manual')
                    ->after('is_core');
            }

            if (! Schema::hasColumn('todos', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        $indexes = collect(Schema::getIndexes('todos'))->pluck('name')->toArray();

        Schema::table('todos', function (Blueprint $table) use ($indexes) {
            if (! in_array('todos_tenant_id_is_core_status_index', $indexes, true)) {
                $table->index(['tenant_id', 'is_core', 'status'], 'todos_tenant_id_is_core_status_index');
            }

            if (! in_array('todos_core_per_task_unique', $indexes, true)) {
                $table->unique(['task_id', 'is_core'], 'todos_core_per_task_unique');
            }
        });
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        $indexes = collect(Schema::getIndexes('todos'))->pluck('name')->toArray();

        // 1) Drop the unique index. Because task_id has an FK, drop the FK first,
        //    then the unique, and re-create the FK without the unique.
        Schema::table('todos', function (Blueprint $table) use ($indexes, $isSqlite) {
            if (in_array('todos_core_per_task_unique', $indexes, true)) {
                if (! $isSqlite && Schema::hasColumn('todos', 'task_id')) {
                    $table->dropForeign(['task_id']);
                }
                $table->dropUnique('todos_core_per_task_unique');

                if (! $isSqlite && Schema::hasColumn('todos', 'task_id')) {
                    // Recreate FK so subsequent dropForeign in column-drop block works.
                    $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
                }
            }

            if (in_array('todos_tenant_id_is_core_status_index', $indexes, true)) {
                $table->dropIndex('todos_tenant_id_is_core_status_index');
            }
        });

        // 2) Drop columns (which also drops their auto-created FKs/indexes).
        Schema::table('todos', function (Blueprint $table) use ($isSqlite) {
            if (Schema::hasColumn('todos', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('todos', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('todos', 'is_core')) {
                $table->dropColumn('is_core');
            }

            if (Schema::hasColumn('todos', 'task_template_id')) {
                if (! $isSqlite) {
                    $table->dropForeign(['task_template_id']);
                }
                $table->dropColumn('task_template_id');
            }

            if (Schema::hasColumn('todos', 'task_id')) {
                if (! $isSqlite) {
                    $table->dropForeign(['task_id']);
                }
                $table->dropColumn('task_id');
            }
        });
    }
};

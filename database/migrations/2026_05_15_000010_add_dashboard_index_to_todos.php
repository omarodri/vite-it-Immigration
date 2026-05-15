<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'assigned_to_id', 'status', 'due_date'],
                'idx_todos_dashboard'
            );
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex('idx_todos_dashboard');
        });
    }
};

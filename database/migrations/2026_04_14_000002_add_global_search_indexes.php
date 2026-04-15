<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['tenant_id', 'first_name', 'last_name'], 'clients_search_name_idx');
            $table->index(['tenant_id', 'email'], 'clients_search_email_idx');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->index(['tenant_id', 'case_number'], 'cases_search_number_idx');
        });

        Schema::table('companions', function (Blueprint $table) {
            $table->index(['tenant_id', 'first_name', 'last_name'], 'companions_search_name_idx');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index(['tenant_id', 'title'], 'events_search_title_idx');
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->index(['tenant_id', 'assigned_to_id'], 'todos_search_assignee_idx');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('todos', function (Blueprint $table) {
                $table->dropIndex('todos_search_assignee_idx');
            });
        } catch (\Throwable) {}

        try {
            Schema::table('events', function (Blueprint $table) {
                $table->dropIndex('events_search_title_idx');
            });
        } catch (\Throwable) {}

        try {
            Schema::table('companions', function (Blueprint $table) {
                $table->dropIndex('companions_search_name_idx');
            });
        } catch (\Throwable) {}

        try {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropIndex('cases_search_number_idx');
            });
        } catch (\Throwable) {}

        try {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropIndex('clients_search_name_idx');
                $table->dropIndex('clients_search_email_idx');
            });
        } catch (\Throwable) {}
    }
};

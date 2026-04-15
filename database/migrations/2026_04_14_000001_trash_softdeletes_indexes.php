<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add soft deletes to legal_documents
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->softDeletes()->after('notes');
        });

        // 2. Change unique constraints on clients (so soft-deleted rows don't block new ones)
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropUnique(['tenant_id', 'phone']);
            $table->unique(['tenant_id', 'email', 'deleted_at']);
            $table->unique(['tenant_id', 'phone', 'deleted_at']);
        });

        // 3. Change unique on cases.case_number
        Schema::table('cases', function (Blueprint $table) {
            $table->dropUnique(['case_number']);
            $table->unique(['case_number', 'deleted_at']);
        });

        // 4. Performance indexes for soft-delete queries
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['tenant_id', 'deleted_at'], 'idx_clients_tenant_deleted');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->index(['tenant_id', 'deleted_at'], 'idx_cases_tenant_deleted');
        });

        Schema::table('companions', function (Blueprint $table) {
            $table->index(['tenant_id', 'deleted_at'], 'idx_companions_tenant_deleted');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index(['tenant_id', 'deleted_at'], 'idx_documents_tenant_deleted');
        });

        Schema::table('legal_documents', function (Blueprint $table) {
            $table->index(['tenant_id', 'deleted_at'], 'idx_legal_documents_tenant_deleted');
        });
    }

    public function down(): void
    {
        // Remove performance indexes
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropIndex('idx_legal_documents_tenant_deleted');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_tenant_deleted');
        });

        Schema::table('companions', function (Blueprint $table) {
            $table->dropIndex('idx_companions_tenant_deleted');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex('idx_cases_tenant_deleted');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_tenant_deleted');
        });

        // Restore original unique on cases.case_number
        Schema::table('cases', function (Blueprint $table) {
            $table->dropUnique(['case_number', 'deleted_at']);
            $table->unique(['case_number']);
        });

        // Restore original unique constraints on clients
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email', 'deleted_at']);
            $table->dropUnique(['tenant_id', 'phone', 'deleted_at']);
            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'phone']);
        });

        // Remove soft deletes from legal_documents
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

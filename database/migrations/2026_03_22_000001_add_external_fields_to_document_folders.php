<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_folders', function (Blueprint $table) {
            $table->string('external_id', 255)->nullable()->after('category');
            $table->string('external_url', 512)->nullable()->after('external_id');
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending')->after('external_url');
            $table->timestamp('synced_at')->nullable()->after('sync_status');

            $table->index(['external_id', 'tenant_id'], 'doc_folders_external_tenant_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_folders', function (Blueprint $table) {
            $table->dropIndex('doc_folders_external_tenant_idx');
            $table->dropColumn(['external_id', 'external_url', 'sync_status', 'synced_at']);
        });
    }
};

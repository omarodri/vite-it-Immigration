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
        Schema::table('cases', function (Blueprint $table) {
            $table->string('root_external_folder_id', 255)->nullable()->after('closure_notes');
            $table->enum('folder_sync_status', ['pending', 'synced', 'failed', 'not_applicable'])
                ->default('not_applicable')
                ->after('root_external_folder_id');
            $table->timestamp('folder_synced_at')->nullable()->after('folder_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['root_external_folder_id', 'folder_sync_status', 'folder_synced_at']);
        });
    }
};

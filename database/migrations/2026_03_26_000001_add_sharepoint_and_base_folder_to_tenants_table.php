<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('sharepoint_site_id', 255)->nullable()->after('storage_type');
            $table->string('sharepoint_drive_id', 255)->nullable()->after('sharepoint_site_id');
            $table->string('sharepoint_site_url', 500)->nullable()->after('sharepoint_drive_id');
            $table->string('base_folder_path', 500)->nullable()->after('sharepoint_site_url');
            $table->string('base_folder_external_id', 500)->nullable()->after('base_folder_path');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'sharepoint_site_id',
                'sharepoint_drive_id',
                'sharepoint_site_url',
                'base_folder_path',
                'base_folder_external_id',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE documents MODIFY COLUMN storage_type ENUM('local', 'onedrive', 'google_drive', 'sharepoint') NOT NULL DEFAULT 'local'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE documents MODIFY COLUMN storage_type ENUM('local', 'onedrive', 'google_drive') NOT NULL DEFAULT 'local'");
    }
};

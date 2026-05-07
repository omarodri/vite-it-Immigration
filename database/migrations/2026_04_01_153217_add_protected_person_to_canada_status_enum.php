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

        DB::statement("ALTER TABLE clients MODIFY COLUMN canada_status ENUM(
            'asylum_seeker', 'refugee', 'protected_person',
            'temporary_resident', 'permanent_resident',
            'citizen', 'visitor', 'student', 'worker', 'other'
        ) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE clients MODIFY COLUMN canada_status ENUM(
            'asylum_seeker', 'refugee',
            'temporary_resident', 'permanent_resident',
            'citizen', 'visitor', 'student', 'worker', 'other'
        ) NULL DEFAULT NULL");
    }
};

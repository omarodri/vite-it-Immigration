<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // MySQL: change ENUM to VARCHAR(50) to support all relationship types
            DB::statement("ALTER TABLE `companions` MODIFY `relationship` VARCHAR(50) NOT NULL DEFAULT 'other'");
        } else {
            // SQLite (tests): column type is not enforced, no action needed
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE `companions` SET `relationship` = 'other' WHERE `relationship` NOT IN ('spouse', 'child', 'parent', 'sibling', 'other')");
            DB::statement("ALTER TABLE `companions` MODIFY `relationship` ENUM('spouse', 'child', 'parent', 'sibling', 'other') NOT NULL DEFAULT 'other'");
        }
    }
};

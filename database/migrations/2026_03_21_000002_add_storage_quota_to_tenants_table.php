<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('storage_quota_mb')->default(5000)->after('storage_type');
            $table->unsignedBigInteger('storage_used_bytes')->default(0)->after('storage_quota_mb');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['storage_quota_mb', 'storage_used_bytes']);
        });
    }
};

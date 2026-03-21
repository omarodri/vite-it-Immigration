<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('scanned_at')->nullable()->after('checksum');
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'error'])
                ->nullable()
                ->after('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['scanned_at', 'scan_status']);
        });
    }
};

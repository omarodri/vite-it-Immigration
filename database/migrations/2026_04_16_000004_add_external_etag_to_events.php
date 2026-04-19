<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('external_etag')->nullable()->after('last_synced_at');
            $table->foreignId('synced_by_user_id')
                  ->nullable()
                  ->after('external_etag')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['synced_by_user_id']);
            $table->dropColumn(['external_etag', 'synced_by_user_id']);
        });
    }
};

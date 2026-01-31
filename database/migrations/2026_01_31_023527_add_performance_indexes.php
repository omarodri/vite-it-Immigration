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
        // Activity log table: critical for filtering and querying audit logs
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['log_name', 'event']);
        });

        // Users table: sorting by created_at in admin user listing
        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at');
        });

        // Login attempts: composite index for security monitoring queries
        Schema::table('login_attempts', function (Blueprint $table) {
            $table->index(['email', 'successful', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['log_name', 'event']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('login_attempts', function (Blueprint $table) {
            $table->dropIndex(['email', 'successful', 'attempted_at']);
        });
    }
};

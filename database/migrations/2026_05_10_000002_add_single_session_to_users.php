<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('current_session_id', 64)->nullable()->after('remember_token');
            $table->timestamp('session_invalidated_at')->nullable()->after('current_session_id');
            $table->index('current_session_id', 'users_current_session_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_current_session_id_index');
            $table->dropColumn(['current_session_id', 'session_invalidated_at']);
        });
    }
};

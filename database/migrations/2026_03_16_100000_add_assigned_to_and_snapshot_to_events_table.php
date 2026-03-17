<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('assigned_to_id')
                  ->nullable()
                  ->after('created_by')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->string('client_name_snapshot')
                  ->nullable()
                  ->after('case_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_id']);
            $table->dropColumn(['assigned_to_id', 'client_name_snapshot']);
        });
    }
};

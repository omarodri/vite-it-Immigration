<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('promoted_from_companion_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('companions')
                  ->nullOnDelete();
            $table->unique('promoted_from_companion_id', 'clients_promoted_companion_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_promoted_companion_unique');
            $table->dropForeign(['promoted_from_companion_id']);
            $table->dropColumn('promoted_from_companion_id');
        });
    }
};

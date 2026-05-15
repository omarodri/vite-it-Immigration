<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Change security_questions from JSON to TEXT.
     *
     * The `encrypted:array` cast stores a base64-encoded ciphertext string,
     * not plain JSON. MySQL's JSON column type validates the content on INSERT
     * and rejects ciphertext with "Invalid JSON text" (SQLSTATE 22032).
     * All encrypted fields must be TEXT — see CLAUDE.md gotcha G13.
     */
    public function up(): void
    {
        Schema::table('case_ircc_credentials', function (Blueprint $table) {
            $table->text('security_questions')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('case_ircc_credentials', function (Blueprint $table) {
            $table->json('security_questions')->nullable()->change();
        });
    }
};

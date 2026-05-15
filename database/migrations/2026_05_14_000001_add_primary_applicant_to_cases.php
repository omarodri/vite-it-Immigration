<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->enum('primary_applicant_type', ['client', 'companion'])
                  ->default('client')
                  ->after('client_id');

            $table->foreignId('primary_applicant_companion_id')
                  ->nullable()
                  ->after('primary_applicant_type')
                  ->constrained('companions')
                  ->nullOnDelete();

            $table->index(['tenant_id', 'primary_applicant_type'], 'idx_cases_tenant_pa_type');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['primary_applicant_companion_id']);
            $table->dropIndex('idx_cases_tenant_pa_type');
            $table->dropColumn(['primary_applicant_type', 'primary_applicant_companion_id']);
        });
    }
};

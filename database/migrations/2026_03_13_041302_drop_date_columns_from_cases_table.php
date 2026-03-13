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
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'hearing_date']);
            $table->dropColumn(['hearing_date', 'fda_deadline', 'brown_sheet_date', 'evidence_deadline']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->datetime('hearing_date')->nullable()->comment('Fecha de audiencia');
            $table->datetime('fda_deadline')->nullable()->comment('Plazo para deposito FDA');
            $table->datetime('brown_sheet_date')->nullable()->comment('Fecha hoja marron');
            $table->datetime('evidence_deadline')->nullable()->comment('Plazo envio doc de pruebas');

            $table->index(['tenant_id', 'hearing_date']);
        });
    }
};

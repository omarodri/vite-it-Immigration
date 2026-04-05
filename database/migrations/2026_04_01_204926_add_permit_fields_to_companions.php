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
        Schema::table('companions', function (Blueprint $table) {
            $table->string('work_permit_number', 50)->nullable()->after('canada_status_other');
            $table->string('study_permit_number', 50)->nullable()->after('work_permit_number');
            $table->date('permit_expiry_date')->nullable()->after('study_permit_number');
            $table->date('arrival_date')->nullable()->after('permit_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->dropColumn(['work_permit_number', 'study_permit_number', 'permit_expiry_date', 'arrival_date']);
        });
    }
};

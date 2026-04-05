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
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'passport_number',
                'passport_issue_date',
                'passport_country',
                'passport_expiry_date',
                'work_permit_number',
                'study_permit_number',
                'permit_expiry_date',
            ]);
        });

        Schema::table('companions', function (Blueprint $table) {
            $table->dropColumn([
                'passport_number',
                'passport_issue_date',
                'passport_country',
                'passport_expiry_date',
                'work_permit_number',
                'study_permit_number',
                'permit_expiry_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('passport_number', 50)->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->string('passport_country', 100)->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('work_permit_number', 50)->nullable();
            $table->string('study_permit_number', 50)->nullable();
            $table->date('permit_expiry_date')->nullable();
        });

        Schema::table('companions', function (Blueprint $table) {
            $table->string('passport_number', 50)->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->string('passport_country', 100)->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('work_permit_number', 50)->nullable();
            $table->string('study_permit_number', 50)->nullable();
            $table->date('permit_expiry_date')->nullable();
        });
    }
};

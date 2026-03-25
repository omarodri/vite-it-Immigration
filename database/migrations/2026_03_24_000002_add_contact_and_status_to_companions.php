<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('iuc');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('phone_country_code', 6)->nullable()->default('+1')->after('phone');
            $table->string('canada_status', 50)->nullable()->after('phone_country_code');
            $table->string('canada_status_other', 255)->nullable()->after('canada_status');
        });
    }

    public function down(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'phone_country_code', 'canada_status', 'canada_status_other']);
        });
    }
};

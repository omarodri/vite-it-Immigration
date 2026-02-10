<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->string('relationship_other')->nullable()->after('relationship');
            $table->string('passport_country')->nullable()->after('passport_number');
            $table->date('passport_expiry_date')->nullable()->after('passport_country');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->dropColumn(['relationship_other', 'passport_country', 'passport_expiry_date', 'gender']);
        });
    }
};

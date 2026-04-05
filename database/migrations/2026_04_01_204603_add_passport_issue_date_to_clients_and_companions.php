<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('passport_issue_date')->nullable()->after('passport_number');
        });

        Schema::table('companions', function (Blueprint $table) {
            $table->date('passport_issue_date')->nullable()->after('passport_number');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('passport_issue_date');
        });

        Schema::table('companions', function (Blueprint $table) {
            $table->dropColumn('passport_issue_date');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (! Schema::hasColumn('cases', 'total_time_spent_seconds')) {
                $table->unsignedInteger('total_time_spent_seconds')
                    ->default(0)
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (Schema::hasColumn('cases', 'total_time_spent_seconds')) {
                $table->dropColumn('total_time_spent_seconds');
            }
        });
    }
};

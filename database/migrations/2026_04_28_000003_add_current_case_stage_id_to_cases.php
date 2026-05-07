<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('current_case_stage_id')->nullable()->after('current_stage_id')
                  ->constrained('case_stages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['current_case_stage_id']);
            $table->dropColumn('current_case_stage_id');
        });
    }
};

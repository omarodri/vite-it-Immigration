<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('current_stage_id')->nullable()->after('case_type_id')
                  ->constrained('workflow_stages')->nullOnDelete();
            $table->json('workflow_snapshot')->nullable()->after('current_stage_id');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['current_stage_id']);
            $table->dropColumn(['current_stage_id', 'workflow_snapshot']);
        });
    }
};

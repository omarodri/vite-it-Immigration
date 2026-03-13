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
            // Operational tracking — after 'language' column
            $table->string('stage', 50)->nullable()->default(null)->after('language');
            $table->string('ircc_status', 50)->nullable()->default(null)->after('stage');
            $table->string('final_result', 20)->nullable()->default(null)->after('ircc_status');
            $table->string('ircc_code', 50)->nullable()->default(null)->after('final_result');

            // Financial/Admin — after 'archive_box_number'
            $table->string('contract_number', 50)->nullable()->default(null)->after('archive_box_number');
            $table->string('service_type', 20)->default('fee_based')->after('contract_number');
            $table->decimal('fees', 10, 2)->nullable()->default(null)->after('service_type');

            // Indexes
            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'ircc_status']);
            $table->index(['tenant_id', 'service_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'stage']);
            $table->dropIndex(['tenant_id', 'ircc_status']);
            $table->dropIndex(['tenant_id', 'service_type']);
            $table->dropColumn(['stage', 'ircc_status', 'final_result', 'ircc_code', 'contract_number', 'service_type', 'fees']);
        });
    }
};

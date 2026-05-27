<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec 65 — Add a dedicated sequence column to the cases table.
     *
     * G11: the physical table is `cases`, not `immigration_cases`.
     *
     * Step 1: add the column as nullable.
     * Step 2: backfill with ROW_NUMBER() OVER (PARTITION BY tenant_id ORDER BY created_at, id)
     *         so every existing row gets a deterministic per-tenant sequence.
     * Step 3: make it NOT NULL and add UNIQUE(tenant_id, case_number_seq).
     */
    public function up(): void
    {
        // Step 1 — add the column (nullable so the backfill can run)
        Schema::table('cases', function (Blueprint $table) {
            $table->unsignedInteger('case_number_seq')
                  ->nullable()
                  ->after('case_number');
        });

        // Step 2 — backfill per-tenant sequence by chronological order
        DB::statement('
            UPDATE cases c
            JOIN (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY tenant_id
                           ORDER BY created_at, id
                       ) AS rn
                FROM cases
            ) r ON r.id = c.id
            SET c.case_number_seq = r.rn
        ');

        // Step 3 — enforce NOT NULL + UNIQUE (tenant_id, case_number_seq)
        Schema::table('cases', function (Blueprint $table) {
            $table->unsignedInteger('case_number_seq')->nullable(false)->change();
            $table->unique(
                ['tenant_id', 'case_number_seq'],
                'cases_tenant_seq_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropUnique('cases_tenant_seq_unique');
            $table->dropColumn('case_number_seq');
        });
    }
};

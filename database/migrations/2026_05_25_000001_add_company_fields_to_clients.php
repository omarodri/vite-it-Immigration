<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 64 — Clientes Empresa.
 *
 * Add company-type fields to the `clients` table (Single Table Inheritance).
 * Existing persona records remain untouched and default to type='person'.
 *
 * NOTE: This migration uses raw DB::statement() to make `first_name` and
 * `last_name` nullable, because `->change()` requires the optional
 * doctrine/dbal package which is not installed in this project. Raw SQL
 * is safe here because we target MySQL 8.0 exclusively.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // 1. Add the company-related columns.
        // ---------------------------------------------------------------
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('type', ['person', 'company'])
                ->default('person')
                ->after('tenant_id');

            $table->string('company_name', 255)->nullable()->after('type');
            $table->string('trade_name', 255)->nullable()->after('company_name');
            $table->string('tax_id', 50)->nullable()->after('trade_name');
            $table->string('industry', 100)->nullable()->after('tax_id');
            $table->string('website', 255)->nullable()->after('industry');
            $table->string('legal_rep_name', 255)->nullable()->after('website');
            $table->string('legal_rep_title', 100)->nullable()->after('legal_rep_name');
        });

        // ---------------------------------------------------------------
        // 2. Make first_name / last_name nullable.
        //    Preferred path: Schema Blueprint ->change() (needs doctrine/dbal).
        //    Fallback path (active here): raw ALTER TABLE — keeps the migration
        //    runnable without adding a new composer dependency.
        // ---------------------------------------------------------------
        // Schema::table('clients', function (Blueprint $table) {
        //     $table->string('first_name', 255)->nullable()->change();
        //     $table->string('last_name', 255)->nullable()->change();
        // });
        DB::statement('ALTER TABLE `clients` MODIFY COLUMN `first_name` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `clients` MODIFY COLUMN `last_name` VARCHAR(255) NULL');

        // ---------------------------------------------------------------
        // 3. Add supporting indexes.
        // ---------------------------------------------------------------
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['tenant_id', 'type'], 'idx_clients_tenant_type');
            $table->index(['tenant_id', 'tax_id'], 'idx_clients_tenant_tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_tenant_tax_id');
            $table->dropIndex('idx_clients_tenant_type');
        });

        // Restore NOT NULL on first_name / last_name. Any existing company-only
        // rows would block this; the production rollback procedure must clear
        // those rows first.
        DB::statement('ALTER TABLE `clients` MODIFY COLUMN `last_name` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `clients` MODIFY COLUMN `first_name` VARCHAR(255) NOT NULL');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'legal_rep_title',
                'legal_rep_name',
                'website',
                'industry',
                'tax_id',
                'trade_name',
                'company_name',
                'type',
            ]);
        });
    }
};

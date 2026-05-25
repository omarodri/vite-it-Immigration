<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 64 — Clientes Empresa.
 *
 * Adds a generated, stored `display_name` column on `clients` so search,
 * sorting, and listings can rely on a single canonical name regardless of
 * whether the row represents a person or a company.
 *
 * Persona  → "first_name last_name" (trimmed, NULL-safe)
 * Empresa  → trade_name, falling back to company_name
 *
 * Uses raw SQL because Laravel Blueprint does not natively support
 * `GENERATED ALWAYS AS (...) STORED`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `clients`
            ADD COLUMN `display_name` VARCHAR(255)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN `type` = 'company'
                            THEN COALESCE(`trade_name`, `company_name`, '')
                        ELSE
                            TRIM(CONCAT(
                                COALESCE(`first_name`, ''), ' ',
                                COALESCE(`last_name`, '')
                            ))
                    END
                ) STORED
        SQL);

        DB::statement('ALTER TABLE `clients` ADD FULLTEXT INDEX `ft_clients_display_name` (`display_name`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `clients` DROP INDEX `ft_clients_display_name`');
        DB::statement('ALTER TABLE `clients` DROP COLUMN `display_name`');
    }
};

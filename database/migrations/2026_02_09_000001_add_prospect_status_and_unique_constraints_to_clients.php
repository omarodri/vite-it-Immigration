<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL: Modify the enum column to include 'prospect'
            DB::statement("ALTER TABLE clients MODIFY COLUMN status ENUM('prospect', 'active', 'inactive', 'archived') NOT NULL DEFAULT 'prospect'");
        }
        // SQLite: enum is stored as string, no modification needed - the model/validation handles the values

        // Add unique constraints
        Schema::table('clients', function (Blueprint $table) use ($driver) {
            // Drop the existing email index if it exists (MySQL only - SQLite handles this differently)
            if ($driver === 'mysql') {
                $table->dropIndex(['tenant_id', 'email']);
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            // Add unique constraint for tenant_id + email combination
            $table->unique(['tenant_id', 'email'], 'clients_tenant_email_unique');

            // Add unique constraint for tenant_id + phone combination
            $table->unique(['tenant_id', 'phone'], 'clients_tenant_phone_unique');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_tenant_email_unique');
            $table->dropUnique('clients_tenant_phone_unique');
        });

        if ($driver === 'mysql') {
            Schema::table('clients', function (Blueprint $table) {
                // Recreate the original index
                $table->index(['tenant_id', 'email']);
            });

            // Revert status enum
            DB::statement("ALTER TABLE clients MODIFY COLUMN status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active'");
        }
    }
};

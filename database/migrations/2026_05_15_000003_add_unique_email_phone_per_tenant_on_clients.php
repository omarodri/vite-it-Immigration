<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The base business-tables migration already enforces tenant-scoped uniqueness on
        // (tenant_id, email, deleted_at) and (tenant_id, phone, deleted_at). If those
        // constraints (or equivalent) already exist, do nothing — this migration is a no-op.
        if ($this->equivalentUniqueExists(['tenant_id', 'email']) === false) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique(['tenant_id', 'email'], 'clients_tenant_email_unique');
            });
        }

        if ($this->equivalentUniqueExists(['tenant_id', 'phone']) === false) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique(['tenant_id', 'phone'], 'clients_tenant_phone_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if ($this->indexExists('clients_tenant_email_unique')) {
                $table->dropUnique('clients_tenant_email_unique');
            }
            if ($this->indexExists('clients_tenant_phone_unique')) {
                $table->dropUnique('clients_tenant_phone_unique');
            }
        });
    }

    /**
     * True if there is any UNIQUE index on `clients` covering exactly the requested columns
     * (any extra columns count as a stricter equivalent and still satisfy the intent).
     */
    private function equivalentUniqueExists(array $columns): bool
    {
        $rows = DB::select('SHOW INDEX FROM clients WHERE Non_unique = 0');

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->Key_name][] = $row->Column_name;
        }

        foreach ($byKey as $cols) {
            $matchesAll = true;
            foreach ($columns as $needle) {
                if (! in_array($needle, $cols, true)) {
                    $matchesAll = false;
                    break;
                }
            }
            if ($matchesAll) {
                return true;
            }
        }

        return false;
    }

    private function indexExists(string $name): bool
    {
        $rows = DB::select('SHOW INDEX FROM clients WHERE Key_name = ?', [$name]);
        return count($rows) > 0;
    }
};

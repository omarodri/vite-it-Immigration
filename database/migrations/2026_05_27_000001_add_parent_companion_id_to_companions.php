<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->foreignId('parent_companion_id')
                ->nullable()
                ->after('client_id')
                ->constrained('companions')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'parent_companion_id'], 'idx_companions_tenant_parent');
        });

        DB::statement("
            ALTER TABLE companions
            ADD CONSTRAINT chk_companions_family_not_beneficiary
            CHECK (parent_companion_id IS NULL OR relationship <> 'beneficiary')
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE companions DROP CHECK chk_companions_family_not_beneficiary');

        Schema::table('companions', function (Blueprint $table) {
            $table->dropForeign(['parent_companion_id']);
            $table->dropIndex('idx_companions_tenant_parent');
            $table->dropColumn('parent_companion_id');
        });
    }
};

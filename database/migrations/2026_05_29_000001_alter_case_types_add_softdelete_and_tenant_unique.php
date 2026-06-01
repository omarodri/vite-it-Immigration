<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_types', function (Blueprint $table) {
            // Soft deletes
            $table->softDeletes()->after('is_active');

            // Migrar unique global → unique por tenant con deleted_at.
            // Patrón G2: no hay FK sobre code, así que solo dropUnique.
            $table->dropUnique(['code']);

            // Unique compuesta incluyendo deleted_at para permitir reutilizar
            // el mismo code después de un soft-delete.
            $table->unique(['tenant_id', 'code', 'deleted_at'], 'case_types_tenant_code_deleted_unique');
        });
    }

    public function down(): void
    {
        Schema::table('case_types', function (Blueprint $table) {
            $table->dropUnique('case_types_tenant_code_deleted_unique');
            $table->unique(['code']);
            $table->dropSoftDeletes();
        });
    }
};

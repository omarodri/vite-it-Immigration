<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('oauth_tokens', 'purpose')) {
            Schema::table('oauth_tokens', function (Blueprint $table) {
                $table->string('purpose', 20)->default('storage')->after('provider');
            });
        }

        $isSqlite = DB::getDriverName() === 'sqlite';

        $indexes = collect(Schema::getIndexes('oauth_tokens'))->pluck('name')->toArray();

        Schema::table('oauth_tokens', function (Blueprint $table) use ($indexes, $isSqlite) {
            if (!$isSqlite) {
                $table->dropForeign(['user_id']);
            }

            if (in_array('oauth_tokens_user_id_provider_unique', $indexes)) {
                $table->dropUnique(['user_id', 'provider']);
            }

            if (!in_array('oauth_tokens_user_id_provider_purpose_unique', $indexes)) {
                $table->unique(['user_id', 'provider', 'purpose']);
            }

            if (!$isSqlite) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }
        });

        // Re-fetch indexes after first block
        $indexes = collect(Schema::getIndexes('oauth_tokens'))->pluck('name')->toArray();

        Schema::table('oauth_tokens', function (Blueprint $table) use ($indexes, $isSqlite) {
            if (in_array('oauth_tokens_tenant_id_provider_index', $indexes)) {
                if (!$isSqlite) {
                    $table->dropForeign(['tenant_id']);
                }
                $table->dropIndex(['tenant_id', 'provider']);
            }

            if (!in_array('oauth_tokens_tenant_id_provider_purpose_index', $indexes)) {
                $table->index(['tenant_id', 'provider', 'purpose']);
            }

            if (!$isSqlite) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::table('oauth_tokens', function (Blueprint $table) use ($isSqlite) {
            if (!$isSqlite) {
                $table->dropForeign(['user_id']);
            }
            $table->dropUnique(['user_id', 'provider', 'purpose']);
            $table->unique(['user_id', 'provider']);
            $table->dropIndex(['tenant_id', 'provider', 'purpose']);
            $table->index(['tenant_id', 'provider']);
            if (!$isSqlite) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }
            $table->dropColumn('purpose');
        });
    }
};

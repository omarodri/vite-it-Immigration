<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add purpose column only if it doesn't exist (migration may have
        // partially run if a previous attempt failed on the index step)
        if (!Schema::hasColumn('oauth_tokens', 'purpose')) {
            Schema::table('oauth_tokens', function (Blueprint $table) {
                $table->string('purpose', 20)->default('storage')->after('provider');
            });
        }

        Schema::table('oauth_tokens', function (Blueprint $table) {
            // MySQL won't drop the unique index while a FK uses it as its backing
            // index. Drop the FK first, then swap the indexes, then restore the FK.
            $table->dropForeign(['user_id']);

            // Only drop if the old (2-column) unique index still exists
            $indexes = collect(\DB::select("SHOW INDEX FROM oauth_tokens"))
                ->pluck('Column_name', 'Key_name')
                ->keys()
                ->unique()
                ->toArray();

            if (in_array('oauth_tokens_user_id_provider_unique', $indexes)) {
                $table->dropUnique(['user_id', 'provider']);
            }

            if (!in_array('oauth_tokens_user_id_provider_purpose_unique', $indexes)) {
                $table->unique(['user_id', 'provider', 'purpose']);
            }

            // Restore the FK on user_id
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('oauth_tokens', function (Blueprint $table) {
            $indexes = collect(\DB::select("SHOW INDEX FROM oauth_tokens"))
                ->pluck('Column_name', 'Key_name')
                ->keys()
                ->unique()
                ->toArray();

            if (in_array('oauth_tokens_tenant_id_provider_index', $indexes)) {
                // Drop the tenant FK first so MySQL allows dropping the index
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'provider']);
            }

            if (!in_array('oauth_tokens_tenant_id_provider_purpose_index', $indexes)) {
                $table->index(['tenant_id', 'provider', 'purpose']);
            }

            // Restore the tenant FK
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'provider', 'purpose']);
            $table->unique(['user_id', 'provider']);
            $table->dropIndex(['tenant_id', 'provider', 'purpose']);
            $table->index(['tenant_id', 'provider']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn('purpose');
        });
    }
};

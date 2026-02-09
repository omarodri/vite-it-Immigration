<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('settings')->nullable();
            $table->text('ms_client_id')->nullable();
            $table->text('ms_client_secret')->nullable();
            $table->text('google_client_id')->nullable();
            $table->text('google_client_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add tenant_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::dropIfExists('tenants');
    }
};

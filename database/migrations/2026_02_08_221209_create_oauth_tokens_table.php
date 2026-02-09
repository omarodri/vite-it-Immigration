<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('provider', ['microsoft', 'google']);
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->dateTime('expires_at');
            $table->json('scopes')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['tenant_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_tokens');
    }
};

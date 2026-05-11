<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_revocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('victim_user_id');
            $table->string('revoking_ip', 45)->nullable();
            $table->text('revoking_user_agent')->nullable();
            $table->string('stop_reason', 32)->default('new_login');
            $table->timestamps();

            $table->index(['victim_user_id', 'created_at'], 'sr_victim_created_idx');
            $table->foreign('victim_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_revocations');
    }
};

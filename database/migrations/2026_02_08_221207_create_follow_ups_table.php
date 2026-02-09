<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('channel', ['phone', 'email', 'meeting', 'video_call', 'other'])->default('phone');
            $table->enum('type', ['task', 'follow_up', 'note'])->default('follow_up');
            $table->dateTime('contact_date');
            $table->decimal('duration_hours', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('category')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'case_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'contact_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->enum('relationship', ['spouse', 'child', 'parent', 'sibling', 'other'])->default('other');
            $table->date('date_of_birth')->nullable();
            $table->string('passport_number')->nullable();
            $table->string('nationality')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companions');
    }
};

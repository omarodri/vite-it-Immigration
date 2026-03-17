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
            $table->string('relationship', 50)->default('other');
            $table->string('relationship_other')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('passport_number')->nullable();
            $table->string('passport_country')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('nationality')->nullable();
            $table->text('notes')->nullable();
            $table->string('iuc')->nullable()->comment('Unique Companion Identifier');

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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('case_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('label', 150);
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('case_id');
            $table->index(['case_id', 'is_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_tasks');
    }
};

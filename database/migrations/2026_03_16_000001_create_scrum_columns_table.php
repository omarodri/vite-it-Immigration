<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrum_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('title', 100);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrum_columns');
    }
};

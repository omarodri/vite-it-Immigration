<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Polymorphic relation (Client or Companion)
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');

            $table->string('document_type', 60);
            $table->string('document_name', 150)->nullable();
            $table->string('document_number', 80)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->smallInteger('alert_days_before')->default(60);
            $table->boolean('alert_active')->default(true);
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'expiry_date']);
            $table->index(['documentable_type', 'documentable_id']);
            $table->index(['tenant_id', 'alert_active', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
